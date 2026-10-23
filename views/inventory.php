<?php
$id = int_in($_GET, 'id');

if ($id && ($shelf = $inventory->find($id))):
    $recent = $inventory->movements(['inventory_id' => $id]);
    $rows   = $inventory->reservations(['inventory_id' => $id]);
    $hideItem = true;
    $why    = array_filter([
        $shelf['seller_status']  !== 'active' ? 'the seller is inactive'      : null,
        $shelf['product_status'] !== 'active' ? 'the product is discontinued' : null,
        $shelf['offer_status']   !== 'active' ? 'the listing is switched off' : null,
    ]);
    $hidden = fn (string $action) => csrf_field()
        . '<input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="inventory_id" value="' . $id . '">';
?>
<a class="crumb" href="?page=inventory"><?= icon('back') ?>All stock</a>
<?= page_head('', $shelf['product_name'],
    $shelf['seller_name'] . ' · ' . $shelf['category_name'] . ' · ' . peso($shelf['selling_price']) . ' each',
    badge(stock_state($shelf))) ?>

<?php if ($why): ?>
    <div class="notice-bar info"><?= icon('alert') ?><div>Customers can't order this right now because <?= implode(' and ', $why) ?>. Orders already placed can still ship.</div></div>
<?php endif; ?>

<section class="equation">
    <div class="term"><small>In warehouse</small><strong><?= qty($shelf['quantity_on_hand']) ?></strong><span>on the shelves</span></div>
    <div class="op">−</div>
    <div class="term"><small>Reserved for orders</small><strong><?= qty($shelf['quantity_reserved']) ?></strong><span>waiting to ship</span></div>
    <div class="op">=</div>
    <div class="term result"><small>Available to sell</small><strong><?= qty($shelf['quantity_available']) ?></strong><?= level_bar($shelf) ?></div>
    <form class="term restock-point" method="post">
        <?= $hidden('set_reorder') ?>
        <small>Warn me when available drops to</small>
        <div class="inline-input"><input type="number" name="reorder_level" min="0" value="<?= $shelf['reorder_level'] ?>" required aria-label="Restock point"><button class="btn btn-sm btn-ghost">Save</button></div>
    </form>
</section>

<div class="grid-2">
    <form class="panel" method="post">
        <div class="panel-head"><div><h2>Add stock</h2><p>A delivery arrived from the supplier.</p></div></div>
        <div class="panel-body">
            <?= $hidden('restock') ?>
            <label>How many units arrived?<input type="number" name="quantity" min="1" required inputmode="numeric"></label>
            <label><span>Note <i>optional</i></span><input name="remarks" maxlength="255" placeholder="e.g. Delivery receipt DR-1042"></label>
            <button class="btn"><?= icon('plus') ?>Add to stock</button>
        </div>
    </form>

    <form class="panel" method="post">
        <div class="panel-head"><div><h2>Fix the count</h2><p>The shelf doesn't match the number above.</p></div></div>
        <div class="panel-body">
            <?= $hidden('adjust') ?>
            <div class="choice">
                <label><input type="radio" name="direction" value="out" checked><span><b>Fewer than recorded</b><small>Damaged, lost or missing</small></span></label>
                <label><input type="radio" name="direction" value="in"><span><b>More than recorded</b><small>Found extra units</small></span></label>
            </div>
            <div class="row">
                <label>How many units?<input type="number" name="quantity" min="1" required inputmode="numeric"></label>
                <label>Reason<input name="remarks" maxlength="255" required placeholder="e.g. Water damage"></label>
            </div>
            <button class="btn btn-ghost">Save correction</button>
            <?php if ($shelf['quantity_reserved'] > 0): ?><p class="hint">You can't remove the <?= qty($shelf['quantity_reserved']) ?> units already reserved for orders.</p><?php endif; ?>
        </div>
    </form>
</div>

<section class="panel">
    <div class="panel-head"><h2>History</h2></div>
    <?php require __DIR__ . '/partials/movement_table.php'; ?>
</section>

<section class="panel">
    <div class="panel-head"><h2>Reserved for orders</h2></div>
    <?php require __DIR__ . '/partials/reservation_table.php'; ?>
</section>

<?php else:
    $tabs = ['' => 'All', 'low' => 'Running low', 'out' => 'Out of stock', 'off' => 'Not for sale'];
    $tab  = (string) ($_GET['status'] ?? '');
    $tab  = isset($tabs[$tab]) ? $tab : '';
    $all  = $inventory->stock();
    $in   = fn (string $t) => fn ($r) => match ($t) {
        'low'   => stock_state($r) === 'low_stock',
        'out'   => stock_state($r) === 'out_of_stock',
        'off'   => stock_state($r) === 'not_for_sale',
        default => true,
    };
    $rows   = array_filter($all, $in($tab));
    $offers = $inventory->unstockedOffers();
?>
<?= page_head('', 'Stock', 'Every item you sell, how many are left, and when to restock.') ?>

<section class="equation compact" aria-label="How the numbers work">
    <div class="term"><small>In warehouse</small><span>units on the shelves</span></div>
    <div class="op">−</div>
    <div class="term"><small>Reserved for orders</small><span>promised, not shipped yet</span></div>
    <div class="op">=</div>
    <div class="term result"><small>Available to sell</small><span>what customers can still buy</span></div>
</section>

<section class="panel">
    <div class="status-tabs">
        <?php foreach ($tabs as $key => $text): ?>
            <a class="status-tab<?= $tab === $key ? ' active' : '' ?>" href="?page=inventory<?= $key !== '' ? "&status=$key" : '' ?>"><?= $text ?> <span><?= count(array_filter($all, $in($key))) ?></span></a>
        <?php endforeach; ?>
    </div>
    <div class="toolbar">
        <label class="grow search"><?= icon('search') ?><input type="search" data-filter="stock-rows" placeholder="Search by item or seller" aria-label="Search stock"></label>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Item</th>
                <th title="In warehouse minus reserved">Available to sell</th>
                <th class="num">In warehouse</th>
                <th class="num">Reserved</th>
                <th>Status</th><th></th>
            </tr></thead>
            <tbody id="stock-rows">
            <?php foreach ($rows as $r): ?>
                <tr class="clickable" data-href="?page=inventory&id=<?= $r['inventory_id'] ?>" data-search="<?= h(strtolower($r['product_name'] . ' ' . $r['seller_name'] . ' ' . $r['category_name'])) ?>">
                    <td><a href="?page=inventory&id=<?= $r['inventory_id'] ?>"><strong><?= h($r['product_name']) ?></strong></a><small><?= h($r['seller_name']) ?></small></td>
                    <td><span class="level-line"><b><?= qty($r['quantity_available']) ?></b><?= level_bar($r) ?></span><small>Restock at <?= qty($r['reorder_level']) ?></small></td>
                    <td class="num"><?= qty($r['quantity_on_hand']) ?></td>
                    <td class="num"><?= qty($r['quantity_reserved']) ?></td>
                    <td><?= badge(stock_state($r)) ?></td>
                    <td class="right nowrap"><?= add_stock_button($r, 'btn btn-sm btn-ghost') ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="no-match" hidden><td colspan="6" class="empty">No items match your search.</td></tr>
            <?php if (!$rows): ?><tr><td colspan="6" class="empty">Nothing here.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($offers): ?>
<details class="panel narrow fold">
    <summary><?= icon('plus') ?>Start tracking a new item <span class="muted">(<?= plural(count($offers), 'item') ?> not tracked yet)</span></summary>
    <form class="panel-body" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_inventory">
        <label>Item
            <select name="offer_id" required>
                <?php foreach ($offers as $o): ?>
                    <option value="<?= $o['offer_id'] ?>"><?= h($o['product_name']) ?> — <?= h($o['seller_name']) ?><?= $o['offer_status'] !== 'active' ? ' (switched off)' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="row">
            <label>Units in the warehouse now<input type="number" name="initial_qty" min="0" value="0"></label>
            <label>Warn me when available drops to<input type="number" name="reorder_level" min="0" value="5"></label>
        </div>
        <button class="btn"><?= icon('plus') ?>Start tracking</button>
    </form>
</details>
<?php endif; ?>
<?php endif; ?>
