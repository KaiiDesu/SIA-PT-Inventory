<?php
/** @var array $pages @var string $page */
$alerts = count(array_filter($inventory->lowStock(), fn ($r) => $r['sellable']));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pages[$page][0]) ?> · CampusERP Inventory Management</title>
    <meta name="description" content="CampusERP Inventory Management · Mini-ERP System 3">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<aside class="sidebar">
    <a class="brand" href="?page=dashboard">
        <span class="brand-mark"><?= icon('box') ?></span>
        <span class="brand-copy">Campus<span class="brand-light">ERP</span><small>INVENTORY MANAGEMENT</small></span>
    </a>

    <div class="nav-caption">INVENTORY WORKSPACE</div>
    <nav>
        <?php foreach ($pages as $key => [$title, $icon, $group]): if ($group !== 'inventory') continue; ?>
            <a href="?page=<?= $key ?>" class="nav-link<?= $key === $page ? ' active' : '' ?>">
                <?= icon($icon) ?><span><?= h($title) ?></span>
                <?php if ($key === 'inventory' && $alerts): ?><em class="nav-count" title="<?= plural($alerts, 'item') ?> need restocking"><?= $alerts ?></em><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="nav-caption second">OTHER SYSTEMS</div>
    <nav>
        <?php foreach ($pages as $key => [$title, $icon, $group]): if ($group !== 'demo') continue; ?>
            <a href="?page=<?= $key ?>" class="nav-link<?= $key === $page ? ' active' : '' ?>"><?= icon($icon) ?><span><?= h($title) ?></span></a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-help"><?= icon('alert') ?><p><b>Available to sell</b> =<br>In warehouse − Reserved for orders</p></div>
    <div class="sidebar-foot">
        <div class="account">
            <span class="avatar">IM</span>
            <div><strong>Inventory team</strong><small>System 3 · Mini-ERP</small></div>
        </div>
    </div>
</aside>

<div class="workspace">
    <header class="topbar">
        <span>CampusERP<span class="breadcrumb">/</span><strong><?= h($pages[$page][0]) ?></strong></span>
    </header>

    <main class="content">
        <?php foreach (take_flashes() as $f): ?>
            <div class="notice-bar <?= h($f['type']) ?>" role="status">
                <?= icon($f['type'] === 'success' ? 'check' : 'alert') ?>
                <div>
                    <strong><?= h($f['message']) ?></strong>
                    <?php if ($f['details']): ?>
                        <ul class="short-list">
                            <?php foreach ($f['details'] as $d): ?>
                                <li><b><?= h($d['product_name'] ?? 'Offer #' . ($d['offer_id'] ?? '?')) ?></b>
                                    — wanted <?= h($d['quantity'] ?? '') ?>, <?= h($d['reason'] ?? '') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php require __DIR__ . "/$page.php"; ?>
    </main>

    <footer class="app-footer">
        <span>CampusERP · Inventory Management</span>
        <span>Universidad de Manila · College of Computing Studies</span>
    </footer>
</div>
<?php require __DIR__ . '/partials/add_stock_dialog.php'; ?>
<script src="assets/app.js"></script>
</body>
</html>
