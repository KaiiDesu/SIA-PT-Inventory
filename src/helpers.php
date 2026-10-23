<?php
declare(strict_types=1);

function h(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function flash(string $type, string $message, array $details = []): void
{
    $_SESSION['flash'][] = compact('type', 'message', 'details');
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Everyday names for the statuses and movement types stored in the database. */
function label(string $value): string
{
    static $names = [
        // Stock levels
        'in_stock'             => 'In stock',
        'low_stock'            => 'Running low',
        'out_of_stock'         => 'Out of stock',
        'not_for_sale'         => 'Not for sale',
        // Reservations
        'fulfilled'            => 'Shipped',
        'released'             => 'Released',
        // Stock history
        'restock'              => 'Stock added',
        'sale'                 => 'Shipped to customer',
        'return'               => 'Returned',
        'adjustment_in'        => 'Count fixed (+)',
        'adjustment_out'       => 'Count fixed (−)',
        // System 2 order statuses
        'pending_stock'        => 'Checking stock',
        'awaiting_payment'     => 'Awaiting payment',
        'confirmed'            => 'Paid',
        'fulfilling'           => 'Out for delivery',
        'completed'            => 'Delivered',
        'cancellation_pending' => 'Cancelling',
    ];
    return $names[$value] ?? ucfirst(str_replace('_', ' ', $value));
}

/** The status a person cares about: a listing that can't be ordered is "Not for sale", whatever its count. */
function stock_state(array $row): string
{
    return $row['sellable'] ? $row['stock_status'] : 'not_for_sale';
}

/** A small fill bar for available stock, with a tick at the restock point. */
function level_bar(array $row): string
{
    $available = max(0, (int) $row['quantity_available']);
    $reorder   = (int) $row['reorder_level'];
    $scale     = max($reorder * 3, $available, 1);
    $tick      = $reorder > 0 ? '<i style="left:' . round(100 * $reorder / $scale) . '%"></i>' : '';
    return '<span class="level ' . h(stock_state($row)) . '" title="Restock point: ' . $reorder . '">'
         . '<span style="width:' . round(100 * $available / $scale) . '%"></span>' . $tick . '</span>';
}

/** Opens the shared "Add stock" pop-up (views/partials/add_stock_dialog.php) for one item. */
function add_stock_button(array $row, string $class = 'btn btn-sm'): string
{
    return '<button type="button" class="' . $class . '" data-add-stock="' . (int) $row['inventory_id'] . '" data-name="'
         . h($row['product_name'] . ' · ' . $row['seller_name']) . '">' . icon('plus') . 'Add stock</button>';
}

/** The current URL's query, so a form can send the user back to exactly where they were. */
function here(): string
{
    return '?' . ($_SERVER['QUERY_STRING'] ?? '');
}

/** Status pill in System 2's style: a coloured badge with a leading dot. */
function badge(string $status): string
{
    return '<span class="badge ' . h($status) . '"><span class="status-dot"></span>' . h(label($status)) . '</span>';
}

/** Line icons. The paths are System 2's set (public/app.js) so both apps share one icon language. */
function icon(string $name, string $class = ''): string
{
    static $paths = [
        'chart'    => 'M4 3v18h17 M8 16v-5 M13 16V7 M18 16V4',
        'orders'   => 'M4 5h16v16H4z M8 5V3h8v2 M8 10h8 M8 14h8 M8 18h5',
        'plus'     => 'M12 5v14 M5 12h14',
        'arrow'    => 'M5 12h14 M14 7l5 5-5 5',
        'box'      => 'm3 7 9-4 9 4v10l-9 4-9-4z M3 7l9 4 9-4 M12 11v10',
        'check'    => 'm5 12 4 4 10-10',
        'card'     => 'M3 5h18v14H3z M3 9h18 M7 15h4',
        'truck'    => 'M2 6h12v12H2z M14 10h4l4 4v4h-8 M5 18a2 2 0 1 0 4 0 M16 18a2 2 0 1 0 4 0',
        'search'   => 'M10 3a7 7 0 1 0 0 14 7 7 0 0 0 0-14 M15 15l6 6',
        'chevron'  => 'm9 5 7 7-7 7',
        'clock'    => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18 M12 7v5l3 2',
        'alert'    => 'm12 3 10 18H2z M12 9v5 M12 17h.01',
        'refresh'  => 'M20 7V3l-4 4 M20 7a9 9 0 1 0 1 8',
        'back'     => 'M19 12H5 M10 7l-5 5 5 5',
        'settings' => 'M4 7h16 M4 17h16 M8 4v6 M16 14v6',
        'user'     => 'M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M4 21v-3a8 8 0 0 1 16 0v3',
        // Additions drawn in the same 24px, 1.7-stroke style.
        'tag'      => 'M3 3h8l10 10-8 8L3 11z M7.5 7.5h.01',
        'ledger'   => 'M5 3h14v18H5z M9 7h6 M9 11h6 M9 15h3',
    ];
    return '<svg class="icon ' . h($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
         . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($paths[$name] ?? $paths['box']) . '"/></svg>';
}

/** The dark-green page banner System 2 puts at the top of every screen. The eyebrow line is optional. */
function page_head(string $eyebrow, string $title, string $intro, string $aside = ''): string
{
    $eyebrow = $eyebrow !== '' ? '<div class="eyebrow">' . h(strtoupper($eyebrow)) . '</div>' : '';
    return '<header class="page-head"><div>' . $eyebrow . '<h1>' . h($title)
         . '</h1><p>' . h($intro) . '</p></div>' . $aside . '</header>';
}

function qty(int|float|string $n): string
{
    return number_format((float) $n);
}

/** "1 order", "3 orders". */
function plural(int $n, string $word): string
{
    return number_format($n) . ' ' . $word . ($n === 1 ? '' : 's');
}

function peso(int|float|string $n): string
{
    return '₱' . number_format((float) $n, 2);
}

function when(?string $dt): string
{
    return $dt ? date('M j, Y g:i A', strtotime($dt)) : '—';
}

function int_in(array $src, string $key): int
{
    return (int) ($src[$key] ?? 0);
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}
