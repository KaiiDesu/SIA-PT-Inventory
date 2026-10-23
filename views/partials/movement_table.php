<?php
/** @var array $recent  Rows from InventoryService::movements(); $compact shows only the key columns */
$compact  ??= false;
$hideItem ??= false;   // on an item's own page the Item column would repeat itself
?>
<?php if (!$recent): ?>
    <div class="empty-state"><?= icon('ledger') ?><p>Nothing has happened to this stock yet.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>When</th><?php if (!$hideItem): ?><th>Item</th><?php endif; ?><th>What happened</th><th class="num">Change</th>
                <?php if (!$compact): ?><th class="num" title="Units in the warehouse right after this change">Left in warehouse</th><th>Order</th><th>Note</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($recent as $m): ?>
                <tr data-search="<?= h(strtolower($m['product_name'] . ' ' . $m['seller_name'] . ' ' . $m['order_number'] . ' ' . $m['remarks'] . ' ' . label($m['movement_type']))) ?>">
                    <td class="date"><small><?= when($m['created_at']) ?></small></td>
                    <?php if (!$hideItem): ?><td><a href="?page=inventory&id=<?= $m['inventory_id'] ?>"><?= h($m['product_name']) ?></a><small><?= h($m['seller_name']) ?></small></td><?php endif; ?>
                    <td><?= badge($m['movement_type']) ?></td>
                    <td class="num <?= $m['signed_quantity'] > 0 ? 'pos' : 'neg' ?>"><?= ($m['signed_quantity'] > 0 ? '+' : '−') . qty(abs((int) $m['signed_quantity'])) ?></td>
                    <?php if (!$compact): ?>
                        <td class="num"><?= qty($m['on_hand_after']) ?></td>
                        <td><?= $m['order_id'] ? h($m['order_number']) : '<span class="muted">—</span>' ?></td>
                        <td><?= $m['remarks'] !== null ? h($m['remarks']) : '<span class="muted">—</span>' ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
