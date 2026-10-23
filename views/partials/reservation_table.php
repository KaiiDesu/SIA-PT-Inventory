<?php
/** @var array $rows  Rows from InventoryService::reservations(); $compact hides the timestamp columns */
$compact ??= false;
$holdBadge = fn (string $s) => $s === 'active'
    ? '<span class="badge active-hold"><span class="status-dot"></span>Active hold</span>'
    : badge($s);
?>
<?php if (!$rows): ?>
    <p class="empty">No reservations found.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <?php if (!$compact): ?><th>#</th><?php endif; ?>
                <th>Order</th><th>Listing</th><th class="num">Qty</th><th>Status</th><th>Placed</th>
                <?php if (!$compact): ?><th>Last change</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?php if (!$compact): ?><td class="muted"><?= $r['reservation_id'] ?></td><?php endif; ?>
                    <td><a href="?page=reservations&order_id=<?= $r['order_id'] ?>"><?= h($r['order_number']) ?></a><small>Line #<?= $r['order_item_id'] ?></small></td>
                    <td><a href="?page=inventory&id=<?= $r['inventory_id'] ?>"><?= h($r['product_name']) ?></a><small><?= h($r['seller_name']) ?></small></td>
                    <td class="num"><?= qty($r['quantity']) ?></td>
                    <td><?= $holdBadge($r['reservation_status']) ?></td>
                    <td class="date"><small><?= when($r['created_at']) ?></small></td>
                    <?php if (!$compact): ?><td class="date"><small><?= when($r['updated_at']) ?></small></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
