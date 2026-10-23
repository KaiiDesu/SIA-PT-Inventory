<?php
$sellers    = $db->query('SELECT * FROM seller ORDER BY seller_id')->fetchAll();
$products   = $db->query('SELECT p.*, c.category_name FROM product p JOIN category c ON c.category_id = p.category_id ORDER BY p.product_id')->fetchAll();
$categories = $db->query('SELECT category_id, category_name FROM category ORDER BY category_name')->fetchAll();
$offers     = $db->query(
    'SELECT o.*, s.seller_name, p.product_name, i.inventory_id
       FROM seller_offer o
       JOIN seller s  ON s.seller_id = o.seller_id
       JOIN product p ON p.product_id = o.product_id
       LEFT JOIN inventory i ON i.offer_id = o.offer_id
      ORDER BY o.offer_id'
)->fetchAll();

$toggle = function (string $entity, int $id, string $status) {
    $text = $status === 'active' ? ($entity === 'product' ? 'Discontinue' : 'Deactivate') : 'Activate';
    return '<form method="post" class="inline">' . csrf_field()
        . '<input type="hidden" name="action" value="toggle_status">'
        . '<input type="hidden" name="entity" value="' . $entity . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="btn btn-sm btn-ghost">' . $text . '</button></form>';
};
?>
<?= page_head('Connected systems', 'Catalogue',
    "System 1's seller, product and offer tables. Inventory never copies them — it only checks their statuses before reserving. Toggle a status to test the rules.") ?>

<section class="panel">
    <div class="panel-head"><div><h2>Seller offers</h2><p>Each offer is one listing, and gets one stock record.</p></div><span class="result-count"><?= plural(count($offers), 'offer') ?></span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Offer</th><th>Product</th><th>Seller</th><th class="num">Selling price</th><th>Status</th><th>Stock record</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($offers as $o): ?>
                <tr>
                    <td class="muted">#<?= $o['offer_id'] ?></td>
                    <td><strong><?= h($o['product_name']) ?></strong></td>
                    <td><?= h($o['seller_name']) ?></td>
                    <td class="num"><?= peso($o['selling_price']) ?></td>
                    <td><?= badge($o['offer_status']) ?></td>
                    <td><?= $o['inventory_id'] ? '<a href="?page=inventory&id=' . $o['inventory_id'] . '">Record #' . $o['inventory_id'] . '</a>' : '<span class="muted">Not counted yet</span>' ?></td>
                    <td class="right"><?= $toggle('offer', (int) $o['offer_id'], $o['offer_status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form method="post" class="row-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_offer">
        <select name="seller_id"><?php foreach ($sellers as $s): ?><option value="<?= $s['seller_id'] ?>"><?= h($s['seller_name']) ?></option><?php endforeach; ?></select>
        <select name="product_id"><?php foreach ($products as $p): ?><option value="<?= $p['product_id'] ?>"><?= h($p['product_name']) ?></option><?php endforeach; ?></select>
        <input type="number" name="price" min="0" step="0.01" placeholder="Price (₱)" required style="max-width:140px">
        <button class="btn btn-sm"><?= icon('plus') ?>Add offer</button>
    </form>
</section>

<div class="grid-2">
    <section class="panel">
        <div class="panel-head"><h2>Sellers</h2><span class="result-count"><?= count($sellers) ?></span></div>
        <div class="table-wrap">
            <table>
                <tbody>
                <?php foreach ($sellers as $s): ?>
                    <tr><td><strong><?= h($s['seller_name']) ?></strong><small><?= h($s['contact_email']) ?></small></td>
                        <td><?= badge($s['seller_status']) ?></td>
                        <td class="right"><?= $toggle('seller', (int) $s['seller_id'], $s['seller_status']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="post" class="row-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="add_seller">
            <input name="seller_name" placeholder="Seller name" required maxlength="150">
            <input type="email" name="contact_email" placeholder="Contact email" required maxlength="255">
            <button class="btn btn-sm">Add</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Products</h2><span class="result-count"><?= count($products) ?></span></div>
        <div class="table-wrap">
            <table>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr><td><strong><?= h($p['product_name']) ?></strong><small><?= h($p['category_name']) ?><?= $p['description'] ? ' · ' . h($p['description']) : '' ?></small></td>
                        <td><?= badge($p['product_status']) ?></td>
                        <td class="right"><?= $toggle('product', (int) $p['product_id'], $p['product_status']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="post" class="row-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="add_product">
            <select name="category_id" style="max-width:170px"><?php foreach ($categories as $c): ?><option value="<?= $c['category_id'] ?>"><?= h($c['category_name']) ?></option><?php endforeach; ?></select>
            <input name="product_name" placeholder="Product name" required maxlength="150">
            <button class="btn btn-sm">Add</button>
        </form>
    </section>
</div>
