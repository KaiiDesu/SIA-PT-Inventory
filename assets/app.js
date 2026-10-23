// Page behaviour for the Inventory pages: the "Add stock" pop-up, clickable rows, instant search,
// busy buttons, and a small motion layer (staggered reveal, count-up numbers) skipped for reduced motion.
(() => {
    // "Add stock" buttons share one pop-up (views/partials/add_stock_dialog.php).
    const dialog = document.getElementById('add-stock');
    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-add-stock]');
        if (opener && dialog) {
            dialog.querySelector('form').reset();   // clear what was typed for the previous item
            dialog.querySelector('[name=inventory_id]').value = opener.dataset.addStock;
            dialog.querySelector('[data-item-name]').textContent = opener.dataset.name;
            dialog.showModal();
            dialog.querySelector('[name=quantity]').focus();
            return;
        }
        if (event.target.closest('[data-close]')) {
            event.target.closest('dialog').close();
            return;
        }
        // Whole table rows open the item, unless a button or link inside the row was clicked.
        const row = event.target.closest('tr[data-href]');
        if (row && !event.target.closest('a, button, input, select, label')) {
            location.href = row.dataset.href;
        }
    });
    // Clicking the dimmed backdrop closes the pop-up.
    dialog?.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });

    // Instant search: <input data-filter="id"> hides rows inside #id whose data-search doesn't match.
    document.querySelectorAll('input[data-filter]').forEach((input) => {
        const scope = document.getElementById(input.dataset.filter);
        if (!scope) return;
        const rows = [...scope.querySelectorAll('tr[data-search]')];
        let none = scope.querySelector('tr.no-match');
        if (!none && rows.length) {
            none = document.createElement('tr');
            none.className = 'no-match';
            none.hidden = true;
            none.innerHTML = `<td colspan="${rows[0].cells.length}" class="empty">Nothing matches your search.</td>`;
            rows[0].parentElement.append(none);
        }
        input.addEventListener('input', () => {
            const words = input.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
            let shown = 0;
            rows.forEach((row) => {
                const match = words.every((w) => row.dataset.search.includes(w));
                row.hidden = !match;
                shown += match;
            });
            if (none) none.hidden = shown > 0;
        });
    });

    // Busy state: stops double submits and shows the spinner while the next page loads.
    document.addEventListener('submit', (event) => {
        const button = event.target.querySelector('button:not([type=button])');
        if (button) button.setAttribute('aria-busy', 'true');
    });
    // Coming back with the browser's Back button restores the page as it was; clear the spinner.
    addEventListener('pageshow', () => {
        document.querySelectorAll('[aria-busy=true]').forEach((b) => b.removeAttribute('aria-busy'));
    });

    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    // Page blocks rise in one after another; the overview's own sections count as blocks.
    document.querySelectorAll('.content > :not(.overview):not(.stats):not(.notice-bar), .overview > :not(.stats)')
        .forEach((el, i) => { el.classList.add('reveal'); el.style.setProperty('--i', i); });
    document.querySelectorAll('.stats').forEach((group) =>
        [...group.children].forEach((card, i) => card.style.setProperty('--i', i)));
    // Table rows fade in top to bottom; long tables stop staggering after 15 rows.
    document.querySelectorAll('tbody').forEach((body) =>
        [...body.rows].forEach((row, i) => row.style.setProperty('--r', Math.min(i, 15))));

    document.querySelectorAll('.stat strong').forEach(countUp);

    /** Animates "468" or "₱41,132.00" up from zero, keeping the prefix, separators and decimals. */
    function countUp(el) {
        const match = el.textContent.trim().match(/^(\D*)([\d,]+(?:\.\d+)?)(.*)$/);
        if (!match) return;
        const [, prefix, digits, suffix] = match;
        const target = parseFloat(digits.replace(/,/g, ''));
        if (!target) return;
        const decimals = (digits.split('.')[1] || '').length;
        const format = (v) => prefix + v.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + suffix;
        const start = performance.now();
        const duration = 750;

        const final = el.textContent;
        let done = false;
        const frame = (now) => {
            if (done) return;
            const t = Math.min(1, (now - start) / duration);
            el.textContent = format(target * (1 - Math.pow(1 - t, 3)));   // ease-out cubic
            if (t < 1) requestAnimationFrame(frame);
        };
        el.textContent = format(0);
        requestAnimationFrame(frame);
        // Frames can be paused (background tab, slow device); always land on the exact server value.
        setTimeout(() => { done = true; el.textContent = final; }, duration + 50);
    }
})();
