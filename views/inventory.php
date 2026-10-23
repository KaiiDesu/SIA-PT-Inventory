<?php
$id = int_in($_GET, 'id');

if ($id && ($shelf = $inventory->find($id))):
    $back   = "?page=inventory&id=$id";
    $recent = $inventory->movements(['inventory_id' => $id]);
    $rows   = $inventory->reservations(['inventory_id' => $id]);
    $blocked = array_filter([
        $shelf['seller_status']  !== 'active' ? 'the seller is inactive'      : null,
        $shelf['product_status'] !== 'active' ? 'the product is discontinued' : null,
        $shelf['offer_status']   !== 'active' ? 'the offer is inactive'       : null,
    ]);
    $hidden = fn (string $action) => csrf_field()
        . '<input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="inventory_id" value="' . $id . '">'
        . '<input type="hidden" name="back" value="' . h($back) . '">';
?>
<a class="crumb" href="?page=inventory"><?= icon('back') ?>Back to stock records</a>
<?= page_head('Stock record #' . $id, $shelf['product_name'],
    $shelf['seller_name'] . ' · Offer #' . $shelf['offer_id'] . ' · ' . $shelf['category_name'] . ' · ' . peso($shelf['selling_price']),
    badge($shelf['stock_status'])) ?>

<?php if ($blocked): ?>
    <div class="notice-bar info"><?= icon('alert') ?><div>New reservations are blocked because <?= implode(' and ', $blocked) ?> in the Catalogue. Existing holds can still ship.</div></div>
<?php endif; ?>

<section class="stats">
    <div class="stat"><small>On hand</small><strong><?= qty($shelf['quantity_on_hand']) ?></strong><span class="sub">Physically with the seller</span><span class="stat-icon"><?= icon('box') ?></span></div>
    <div class="stat"><small>Reserved</small><strong><?= qty($shelf['quantity_reserved']) ?></strong><span class="sub">Promised to unshipped orders</span><span class="stat-icon"><?= icon('clock') ?></span></div>
    <div class="stat feature"><small>Available</small><strong><?= qty($shelf['quantity_available']) ?></strong><span class="sub">On hand − reserved</span><span class="stat-icon"><?= icon('check') ?></span></div>
    <div class="stat"><small>Reorder level</small><strong><?= qty($shelf['reorder_level']) ?></strong><span class="sub">Updated <?= when($shelf['updated_at']) ?></span><span class="stat-icon"><?= icon('alert') ?></span></div>
</section>
<p class="scope-note">On hand changes only through a recorded movement below.</p>

<div class="grid-3">
    <form class="panel" method="post">
        <div class="panel-head"><h2>Restock</h2></div>
        <div class="panel-body">
            <?= $hidden('restock') ?>
            <label>Units received<input type="number" name="quantity" min="1" required></label>
            <label><span>Remarks <i>optional</i></span><input name="remarks" maxlength="255" placeholder="e.g. Supplier delivery DR-1042"></label>
            <button class="btn"><?= icon('plus') ?>Add stock</button>
        </div>
    </form>

    <form class="panel" method="post">
        <div class="panel-head"><h2>Adjust count</h2></div>
        <div class="panel-body">
            <?= $hidden('adjust') ?>
            <div class="row">
                <label>Direction<select name="direction"><option value="out">Remove (−)</option><option value="in">Add (+)</option></select></label>
                <label>Units<input type="number" name="quantity" min="1" required></label>
            </div>
            <label><span>Reason <i>required</i></span><input name="remarks" maxlength="255" required placeholder="Damaged, lost, miscounted…"></label>
            <button class="btn btn-ghost">Record adjustment</button>
            <p class="hint">Can't remove below the <?= qty($shelf['quantity_reserved']) ?> units already reserved.</p>
        </div>
    </form>

    <form class="panel" method="post">
        <div class="panel-head"><h2>Reorder level</h2></div>
        <div class="panel-body">
            <?= $hidden('set_reorder') ?>
            <label>Warn when available is at or below<input type="number" name="reorder_level" min="0" value="<?= $shelf['reorder_level'] ?>" required></label>
            <button class="btn btn-ghost">Save threshold</button>
            <p class="hint">The threshold doesn't change stock, so no movement is recorded.</p>
        </div>
    </form>
</div>

<section class="panel">
    <div class="panel-head"><h2>Movement history</h2><span class="result-count"><?= plural(count($recent), 'line') ?></span></div>
    <?php require __DIR__ . '/partials/movement_table.php'; ?>
</section>

<section class="panel">
    <div class="panel-head"><h2>Reservations on this listing</h2><span class="result-count"><?= plural(count($rows), 'hold') ?></span></div>
    <?php require __DIR__ . '/partials/reservation_table.php'; ?>
</section>

<?php else:
    $filters = ['q' => trim($_GET['q'] ?? ''), 'status' => $_GET['status'] ?? ''];
    $rows    = $inventory->stock($filters);
    $offers  = $inventory->unstockedOffers();
?>
<?= page_head('Inventory workspace', 'Stock records',
    'One record per seller offer. Available is always on hand minus reserved.') ?>

<section class="panel">
    <div class="panel-head"><div><h2>All listings</h2><p>Open a record to restock, adjust or see its history.</p></div><span class="result-count"><?= plural(count($rows), 'result') ?></span></div>
    <form class="toolbar" method="get">
        <input type="hidden" name="page" value="inventory">
        <label class="grow">Search<input type="search" name="q" value="<?= h($filters['q']) ?>" placeholder="Product, seller or category"></label>
        <label>Status
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach (['in_stock', 'low_stock', 'out_of_stock'] as $st): ?>
                    <option value="<?= $st ?>"<?= selected($filters['status'], $st) ?>><?= label($st) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn">Apply</button>
        <?php if ($filters['q'] || $filters['status']): ?><a class="btn btn-ghost" href="?page=inventory">Reset</a><?php endif; ?>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Product / seller</th><th>Offer</th>
                <th class="num">On hand</th><th class="num">Reserved</th><th class="num">Available</th><th class="num">Reorder at</th>
                <th>Status</th><th>Updated</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="clickable" onclick="location='?page=inventory&id=<?= $r['inventory_id'] ?>'">
                    <td><a href="?page=inventory&id=<?= $r['inventory_id'] ?>"><strong><?= h($r['product_name']) ?></strong></a>
                        <small><?= h($r['seller_name']) ?><?= $r['sellable'] ? '' : ' · <span class="neg">not sellable</span>' ?></small></td>
                    <td><small>#<?= $r['offer_id'] ?> · <?= peso($r['selling_price']) ?></small></td>
                    <td class="num"><?= qty($r['quantity_on_hand']) ?></td>
                    <td class="num"><?= qty($r['quantity_reserved']) ?></td>
                    <td class="num strong"><?= qty($r['quantity_available']) ?></td>
                    <td class="num muted"><?= qty($r['reorder_level']) ?></td>
                    <td><?= badge($r['stock_status']) ?></td>
                    <td class="date"><small><?= when($r['updated_at']) ?></small></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="8" class="empty">No stock records match.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<form class="panel narrow" method="post">
    <div class="panel-head"><div><h2>New stock record</h2><p>Start counting a System 1 seller offer.</p></div></div>
    <div class="panel-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_inventory">
        <?php if (!$offers): ?>
            <p class="empty">Every seller offer already has a stock record. Add an offer on the <a href="?page=catalog">Catalogue</a> page first.</p>
        <?php else: ?>
            <label>Seller offer
                <select name="offer_id" required>
                    <?php foreach ($offers as $o): ?>
                        <option value="<?= $o['offer_id'] ?>">#<?= $o['offer_id'] ?> · <?= h($o['product_name']) ?> — <?= h($o['seller_name']) ?><?= $o['offer_status'] !== 'active' ? ' (inactive)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="row">
                <label>Initial count<input type="number" name="initial_qty" min="0" value="0"></label>
                <label>Reorder level<input type="number" name="reorder_level" min="0" value="5"></label>
            </div>
            <button class="btn"><?= icon('plus') ?>Create record</button>
            <p class="hint">A non-zero initial count is logged as a restock movement.</p>
        <?php endif; ?>
    </div>
</form>
<?php endif; ?>
