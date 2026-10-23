<?php /** @var array $recent  Rows from InventoryService::movements() */ ?>
<?php if (!$recent): ?>
    <p class="empty">No movements recorded yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>When</th><th>Listing</th><th>Type</th><th class="num">Change</th><th class="num">On hand after</th><th>Order</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $m): ?>
                <tr>
                    <td class="muted"><?= $m['movement_id'] ?></td>
                    <td class="date"><small><?= when($m['created_at']) ?></small></td>
                    <td><a href="?page=inventory&id=<?= $m['inventory_id'] ?>"><?= h($m['product_name']) ?></a><small><?= h($m['seller_name']) ?></small></td>
                    <td><?= badge($m['movement_type']) ?></td>
                    <td class="num <?= $m['signed_quantity'] > 0 ? 'pos' : 'neg' ?>"><?= ($m['signed_quantity'] > 0 ? '+' : '−') . qty(abs((int) $m['signed_quantity'])) ?></td>
                    <td class="num"><?= qty($m['on_hand_after']) ?></td>
                    <td><?= $m['order_id'] ? '<a href="?page=reservations&order_id=' . $m['order_id'] . '">' . h($m['order_number']) . '</a>' : '<span class="muted">—</span>' ?></td>
                    <td><?= $m['remarks'] !== null ? h($m['remarks']) : '<span class="muted">—</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
