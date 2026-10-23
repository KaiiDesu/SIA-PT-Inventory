<?php
$filters = [
    'type'         => $_GET['type'] ?? '',
    'order_id'     => $_GET['order_id'] ?? '',
    'inventory_id' => $_GET['inventory_id'] ?? '',
];
$recent   = $inventory->movements($filters);
$listings = $inventory->stock();
?>
<?= page_head('Inventory workspace', 'Stock ledger',
    'Every change to on-hand stock, newest first. Lines are permanent — mistakes are fixed with a new adjustment.') ?>

<section class="panel">
    <div class="panel-head"><div><h2>Movements</h2><p>Restocks, sales, returns and adjustments.</p></div><span class="result-count"><?= plural(count($recent), 'result') ?></span></div>
    <form class="toolbar" method="get">
        <input type="hidden" name="page" value="movements">
        <label class="grow">Listing
            <select name="inventory_id">
                <option value="">All listings</option>
                <?php foreach ($listings as $l): ?>
                    <option value="<?= $l['inventory_id'] ?>"<?= selected($filters['inventory_id'], $l['inventory_id']) ?>><?= h($l['product_name']) ?> — <?= h($l['seller_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Type
            <select name="type">
                <option value="">All types</option>
                <?php foreach (InventoryService::MOVEMENT_TYPES as $t): ?>
                    <option value="<?= $t ?>"<?= selected($filters['type'], $t) ?>><?= label($t) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Order ID<input type="number" name="order_id" min="1" value="<?= h($filters['order_id']) ?>" placeholder="Any"></label>
        <button class="btn">Apply</button>
        <?php if (array_filter($filters)): ?><a class="btn btn-ghost" href="?page=movements">Reset</a><?php endif; ?>
    </form>
    <?php require __DIR__ . '/partials/movement_table.php'; ?>
</section>
