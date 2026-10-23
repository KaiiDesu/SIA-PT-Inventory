<?php
$groups = [
    ''         => ['All',            []],
    'added'    => ['Stock added',    ['restock']],
    'shipped'  => ['Shipped',        ['sale']],
    'returned' => ['Returned',       ['return']],
    'fixed'    => ['Count fixes',    ['adjustment_in', 'adjustment_out']],
];
$tab    = (string) ($_GET['type'] ?? '');
$tab    = isset($groups[$tab]) ? $tab : '';
$all    = $inventory->movements([], 1000);
$recent = $groups[$tab][1] ? array_values(array_filter($all, fn ($m) => in_array($m['movement_type'], $groups[$tab][1], true))) : $all;
?>
<?= page_head('', 'Stock history',
    'Every time stock went in or out, newest first. Entries can’t be edited — to correct a mistake, use “Fix the count” on the item.') ?>

<section class="panel">
    <div class="status-tabs">
        <?php foreach ($groups as $key => [$text, $types]): ?>
            <a class="status-tab<?= $tab === $key ? ' active' : '' ?>" href="?page=movements<?= $key !== '' ? "&type=$key" : '' ?>"><?= $text ?>
                <span><?= $types ? count(array_filter($all, fn ($m) => in_array($m['movement_type'], $types, true))) : count($all) ?></span></a>
        <?php endforeach; ?>
    </div>
    <div class="toolbar">
        <label class="grow search"><?= icon('search') ?><input type="search" data-filter="history-rows" placeholder="Search by item, seller, order or note" aria-label="Search stock history"></label>
    </div>
    <div id="history-rows"><?php require __DIR__ . '/partials/movement_table.php'; ?></div>
</section>
