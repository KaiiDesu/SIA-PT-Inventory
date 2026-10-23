<?php
$tabs   = ['active' => 'Waiting to ship', 'fulfilled' => 'Shipped', 'released' => 'Released', 'all' => 'All'];
$tab    = (string) ($_GET['status'] ?? 'active');
$tab    = isset($tabs[$tab]) ? $tab : 'active';
$rows   = $inventory->reservations($tab === 'all' ? [] : ['status' => $tab]);
$counts = array_count_values(array_column($inventory->reservations([], 100000), 'reservation_status'));
?>
<?= page_head('', 'Reserved stock',
    "Units set aside for customers' orders. They leave the warehouse when the order ships, or go back to available if the order is cancelled.") ?>

<section class="panel">
    <div class="status-tabs">
        <?php foreach ($tabs as $key => $text): ?>
            <a class="status-tab<?= $tab === $key ? ' active' : '' ?>" href="?page=reservations&status=<?= $key ?>"><?= $text ?> <span><?= $key === 'all' ? array_sum($counts) : ($counts[$key] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </div>
    <div class="toolbar">
        <label class="grow search"><?= icon('search') ?><input type="search" data-filter="reserved-rows" placeholder="Search by order number or item" aria-label="Search reserved stock"></label>
    </div>
    <div id="reserved-rows"><?php require __DIR__ . '/partials/reservation_table.php'; ?></div>
</section>
