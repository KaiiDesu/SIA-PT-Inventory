<?php
$filters = ['status' => $_GET['status'] ?? '', 'order_id' => $_GET['order_id'] ?? ''];
$rows    = $inventory->reservations($filters);
$counts  = array_count_values(array_column($inventory->reservations([], 100000), 'reservation_status'));
$tabs    = ['' => 'All', 'active' => 'Active', 'fulfilled' => 'Fulfilled', 'released' => 'Released'];
?>
<?= page_head('Inventory workspace', 'Reservations',
    'Holds placed for order lines. Active becomes fulfilled when shipped, or released when cancelled or payment fails. Both are final.') ?>

<section class="panel">
    <div class="panel-head"><div><h2>Stock holds</h2><p>One hold per order line, drawn from one stock record.</p></div><span class="result-count"><?= plural(count($rows), 'result') ?></span></div>
    <div class="status-tabs">
        <?php foreach ($tabs as $st => $text): ?>
            <a class="status-tab<?= $filters['status'] === $st ? ' active' : '' ?>" href="?page=reservations&status=<?= $st ?><?= $filters['order_id'] ? '&order_id=' . h($filters['order_id']) : '' ?>">
                <?= $text ?> <span><?= $st === '' ? array_sum($counts) : ($counts[$st] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </div>
    <form class="toolbar" method="get">
        <input type="hidden" name="page" value="reservations">
        <input type="hidden" name="status" value="<?= h($filters['status']) ?>">
        <label>Order ID<input type="number" name="order_id" min="1" value="<?= h($filters['order_id']) ?>" placeholder="e.g. 1"></label>
        <button class="btn">Apply</button>
        <?php if ($filters['order_id']): ?><a class="btn btn-ghost" href="?page=reservations&status=<?= h($filters['status']) ?>">Reset</a><?php endif; ?>
    </form>
    <?php require __DIR__ . '/partials/reservation_table.php'; ?>
</section>
