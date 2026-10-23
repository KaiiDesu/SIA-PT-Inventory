<?php
require_once __DIR__ . '/../src/actions.php';

$sellers   = $db->query('SELECT seller_id, seller_name, seller_status FROM seller ORDER BY seller_id')->fetchAll();
$sellerId  = int_in($_GET, 'seller') ?: (int) ($sellers[0]['seller_id'] ?? 0);
$listings  = $inventory->stock(['seller_id' => $sellerId]);
$customers = $db->query('SELECT customer_id, customer_name FROM customer ORDER BY customer_id')->fetchAll();
$orders    = $db->query(
    "SELECT o.order_id, o.order_number, o.order_status, o.order_total, o.created_at, o.cancellation_reason,
            c.customer_name, s.seller_name,
            (SELECT COUNT(*) FROM order_item i WHERE i.order_id = o.order_id) AS line_count,
            (SELECT GROUP_CONCAT(DISTINCT r.reservation_status) FROM stock_reservation r WHERE r.order_id = o.order_id) AS hold_statuses
       FROM sales_order o
       JOIN customer c ON c.customer_id = o.customer_id
       JOIN seller s   ON s.seller_id = o.seller_id
      ORDER BY o.order_id DESC
      LIMIT 50"
)->fetchAll();
?>
<?= page_head('Connected systems', 'Order simulator',
    'Stands in for Sales/Order, Billing and Delivery so the whole workflow can be tested. Orders are written to the shared System 2 tables.') ?>

<div class="grid-2 wide-left">
    <form class="panel" method="post">
        <div class="panel-head"><div><h2>Place an order</h2><p>One seller per order, as System 2 enforces.</p></div></div>
        <div class="panel-body">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="place_order">
            <input type="hidden" name="seller_id" value="<?= $sellerId ?>">
            <input type="hidden" name="back" value="?page=orders&seller=<?= $sellerId ?>">
            <div class="seller-pick">
                <?php foreach ($sellers as $s): ?>
                    <a class="<?= $s['seller_id'] == $sellerId ? 'active' : '' ?>" href="?page=orders&seller=<?= $s['seller_id'] ?>"><?= h($s['seller_name']) ?><?= $s['seller_status'] !== 'active' ? ' (inactive)' : '' ?></a>
                <?php endforeach; ?>
            </div>
            <div class="row">
                <label>Customer
                    <select name="customer_id"><?php foreach ($customers as $c): ?><option value="<?= $c['customer_id'] ?>"><?= h($c['customer_name']) ?></option><?php endforeach; ?></select>
                </label>
                <label>Delivery address<input name="address" placeholder="123 Test Street, Manila" maxlength="200"></label>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Listing</th><th class="num">Price</th><th class="num">Available</th><th class="num">Qty</th></tr></thead>
                <tbody>
                <?php foreach ($listings as $l): ?>
                    <tr class="<?= $l['sellable'] ? '' : 'dim' ?>">
                        <td><strong><?= h($l['product_name']) ?></strong><small>Offer #<?= $l['offer_id'] ?><?= $l['sellable'] ? '' : ' · not sellable' ?></small></td>
                        <td class="num"><?= peso($l['selling_price']) ?></td>
                        <td class="num"><?= qty($l['quantity_available']) ?></td>
                        <td class="num"><input class="qty" type="number" min="0" name="qty[<?= $l['offer_id'] ?>]" placeholder="0"></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$listings): ?><tr><td colspan="4" class="empty">This seller has no stock records.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="row-form">
            <button class="btn"><?= icon('check') ?>Submit &amp; reserve stock</button>
            <p class="hint" style="margin:0;align-self:center">Listings that aren't sellable stay enabled on purpose, so you can watch the order get rejected.</p>
        </div>
    </form>

    <section class="panel">
        <div class="panel-head"><h2>Orders</h2><span class="result-count"><?= plural(count($orders), 'order') ?></span></div>
        <?php if (!$orders): ?><p class="empty">No orders yet.</p><?php endif; ?>
        <div class="orders">
            <?php foreach ($orders as $o): ?>
                <div class="order">
                    <div class="order-head">
                        <div><b><?= h($o['order_number']) ?></b>
                            <small><?= h($o['customer_name']) ?> · <?= h($o['seller_name']) ?> · <?= $o['line_count'] ?> line(s) · <?= peso($o['order_total']) ?></small>
                            <?php if ($o['order_status'] === 'cancelled' && $o['cancellation_reason']): ?><small><?= h($o['cancellation_reason']) ?></small><?php endif; ?>
                        </div>
                        <?= badge($o['order_status']) ?>
                    </div>
                    <?php if ($events = order_events($o['order_status'])): ?>
                        <div class="order-actions">
                            <?php foreach ($events as $event => $text): ?>
                                <form method="post" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="order_event">
                                    <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                                    <input type="hidden" name="event" value="<?= $event ?>">
                                    <input type="hidden" name="back" value="?page=orders&seller=<?= $sellerId ?>">
                                    <button class="btn btn-sm <?= in_array($event, ['payment_failed', 'delivery_failed'], true) ? 'danger' : (in_array($event, ['cancel'], true) ? 'btn-ghost' : '') ?>"><?= h($text) ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($o['hold_statuses']): ?><small><a href="?page=reservations&order_id=<?= $o['order_id'] ?>">Stock holds: <?= h(str_replace(',', ', ', $o['hold_statuses'])) ?></a></small><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
