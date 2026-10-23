<?php
/** @var array $rows  Rows from InventoryService::reservations() */
$hideItem ??= false;
$holdBadge = fn (string $s) => $s === 'active'
    ? '<span class="badge active-hold"><span class="status-dot"></span>Waiting to ship</span>'
    : badge($s);
?>
<?php if (!$rows): ?>
    <div class="empty-state"><?= icon('clock') ?><p>No stock is reserved right now.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Order</th><?php if (!$hideItem): ?><th>Item</th><?php endif; ?><th class="num">Units</th><th>Status</th><th>Reserved on</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr data-search="<?= h(strtolower($r['order_number'] . ' ' . $r['product_name'] . ' ' . $r['seller_name'])) ?>">
                    <td><strong><?= h($r['order_number']) ?></strong></td>
                    <?php if (!$hideItem): ?><td><a href="?page=inventory&id=<?= $r['inventory_id'] ?>"><?= h($r['product_name']) ?></a><small><?= h($r['seller_name']) ?></small></td><?php endif; ?>
                    <td class="num"><?= qty($r['quantity']) ?></td>
                    <td><?= $holdBadge($r['reservation_status']) ?></td>
                    <td class="date"><small><?= when($r['created_at']) ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
