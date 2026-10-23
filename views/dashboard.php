<?php
$s      = $inventory->summary();
$alerts = array_filter($inventory->lowStock(), fn ($r) => $r['sellable']);
$recent = $inventory->movements([], 6);
$rows   = $inventory->reservations(['status' => 'active'], 5);
$need   = count($alerts);
?>
<?= page_head('', 'Dashboard', "Today's stock at a glance, and what needs your attention.") ?>

<div class="overview">
    <section class="stats">
        <div class="stat feature"><small>Available to sell</small><strong><?= qty($s['available']) ?></strong><span class="sub">units customers can still order</span><span class="stat-icon"><?= icon('check') ?></span></div>
        <div class="stat"><small>In warehouse</small><strong><?= qty($s['on_hand']) ?></strong><span class="sub">units physically on the shelves</span><span class="stat-icon"><?= icon('box') ?></span></div>
        <div class="stat"><small>Reserved for orders</small><strong><?= qty($s['reserved']) ?></strong><span class="sub">units waiting to ship</span><span class="stat-icon"><?= icon('clock') ?></span></div>
        <div class="stat <?= $need ? 'warn' : '' ?>"><small>Need restocking</small><strong><?= $need ?></strong><span class="sub"><?= $need ? 'items running low or out' : 'everything is well stocked' ?></span><span class="stat-icon"><?= icon('alert') ?></span></div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Needs restocking</h2><a class="more" href="?page=inventory&status=low">See all stock</a></div>
        <?php if (!$alerts): ?>
            <div class="empty-state"><?= icon('check') ?><p><b>All good.</b> Every item is above its restock point.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Item</th><th>Available to sell</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($alerts as $r): ?>
                        <tr>
                            <td><a href="?page=inventory&id=<?= $r['inventory_id'] ?>"><strong><?= h($r['product_name']) ?></strong></a><small><?= h($r['seller_name']) ?></small></td>
                            <td><span class="level-line"><b><?= qty($r['quantity_available']) ?></b><?= level_bar($r) ?></span><small>Restock when it reaches <?= qty($r['reorder_level']) ?></small></td>
                            <td><?= badge(stock_state($r)) ?></td>
                            <td class="right"><?= add_stock_button($r) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Waiting to ship</h2><a class="more" href="?page=reservations">All reserved stock</a></div>
        <?php require __DIR__ . '/partials/reservation_table.php'; ?>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Recent activity</h2><a class="more" href="?page=movements">Full history</a></div>
        <?php $compact = true; require __DIR__ . '/partials/movement_table.php'; ?>
    </section>
</div>
