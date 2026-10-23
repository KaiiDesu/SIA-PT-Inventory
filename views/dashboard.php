<?php
$s      = $inventory->summary();
$alerts = $inventory->lowStock();
$recent = $inventory->movements([], 8);
$rows   = $inventory->reservations(['status' => 'active'], 6);
?>
<?= page_head('Inventory workspace', 'Inventory overview',
    "How many units of each seller's product can still be sold right now.",
    '<a class="btn" href="?page=inventory">' . icon('box') . 'Stock records</a>') ?>

<div class="overview">
    <section class="stats">
        <div class="stat"><small>Units on hand</small><strong><?= qty($s['on_hand']) ?></strong><span class="sub">Across <?= qty($s['listings']) ?> listings</span><span class="stat-icon"><?= icon('box') ?></span></div>
        <div class="stat"><small>Reserved for orders</small><strong><?= qty($s['reserved']) ?></strong><span class="sub"><?= qty($s['active_holds']) ?> active holds</span><span class="stat-icon"><?= icon('clock') ?></span></div>
        <div class="stat feature"><small>Available to sell</small><strong><?= qty($s['available']) ?></strong><span class="sub"><?= peso($s['available_value']) ?> at selling price</span><span class="stat-icon"><?= icon('check') ?></span></div>
        <div class="stat <?= $s['out_of_stock'] ? 'bad' : ($s['low_stock'] ? 'warn' : '') ?>"><small>Need restocking</small><strong><?= qty($s['low_stock'] + $s['out_of_stock']) ?></strong><span class="sub"><?= qty($s['low_stock']) ?> low · <?= qty($s['out_of_stock']) ?> out of stock</span><span class="stat-icon"><?= icon('alert') ?></span></div>
    </section>
    <p class="scope-note">Available is always on hand minus reserved — it is calculated, never stored. A listing is low when available is at or below its reorder level.</p>

    <div class="grid-2">
        <section class="panel">
            <div class="panel-head"><h2>Needs restocking</h2><small>Lowest available first</small></div>
            <?php if (!$alerts): ?>
                <p class="empty">Every listing is above its reorder level.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Listing</th><th class="num">Available</th><th class="num">Reorder at</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($alerts as $r): ?>
                            <tr>
                                <td><a href="?page=inventory&id=<?= $r['inventory_id'] ?>"><?= h($r['product_name']) ?></a><small><?= h($r['seller_name']) ?> · Offer #<?= $r['offer_id'] ?></small></td>
                                <td class="num strong"><?= qty($r['quantity_available']) ?></td>
                                <td class="num"><?= qty($r['reorder_level']) ?></td>
                                <td><?= badge($r['stock_status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Active holds</h2><a class="more" href="?page=reservations&status=active">View all</a></div>
            <?php $compact = true; require __DIR__ . '/partials/reservation_table.php'; ?>
        </section>
    </div>

    <section class="panel">
        <div class="panel-head"><h2>Recent stock movements</h2><a class="more" href="?page=movements">Full ledger</a></div>
        <?php require __DIR__ . '/partials/movement_table.php'; ?>
    </section>
</div>
