<?php
declare(strict_types=1);

/**
 * Form handlers for the web UI. Inventory actions go through InventoryService.
 * The order and catalogue actions stand in for Systems 1, 2, 4 and 5 so the whole
 * workflow from section 5 can be demonstrated. They write the shared System 1 & 2
 * tables the same way those systems would, using System 2's order statuses.
 */
function handle_action(InventoryService $inv, PDO $db, string $action, array $p): void
{
    switch ($action) {
        // ---- Inventory ----
        case 'create_inventory':
            $id   = $inv->createInventory(int_in($p, 'offer_id'), int_in($p, 'reorder_level'), int_in($p, 'initial_qty'));
            $item = $inv->find($id);
            flash('success', "Now tracking {$item['product_name']} from {$item['seller_name']}.");
            break;

        case 'restock':
            $n = int_in($p, 'quantity');
            $inv->restock(int_in($p, 'inventory_id'), $n, $p['remarks'] ?? null);
            $item = $inv->find(int_in($p, 'inventory_id'));
            flash('success', 'Added ' . plural($n, 'unit') . " of {$item['product_name']}. {$item['quantity_available']} now available to sell.");
            break;

        case 'adjust':
            $n = int_in($p, 'quantity');
            $inv->adjust(int_in($p, 'inventory_id'), (string) ($p['direction'] ?? ''), $n, (string) ($p['remarks'] ?? ''));
            $item = $inv->find(int_in($p, 'inventory_id'));
            flash('success', 'Count fixed: ' . (($p['direction'] ?? '') === 'in' ? 'added ' : 'removed ') . plural($n, 'unit')
                . ". {$item['quantity_on_hand']} now in the warehouse.");
            break;

        case 'set_reorder':
            $inv->setReorderLevel(int_in($p, 'inventory_id'), int_in($p, 'reorder_level'));
            flash('success', "You'll be warned when available stock drops to " . int_in($p, 'reorder_level') . '.');
            break;

        // ---- Order simulator (System 2 → 4 → 5 workflow) ----
        case 'place_order':
            place_order($inv, $db, $p);
            break;

        case 'order_event':
            order_event($inv, $db, int_in($p, 'order_id'), (string) ($p['event'] ?? ''));
            break;

        // ---- Catalogue stand-in (System 1) ----
        case 'toggle_status':
            $toggles = [
                'seller'  => ['seller', 'seller_status', 'seller_id', 'inactive'],
                'product' => ['product', 'product_status', 'product_id', 'discontinued'],
                'offer'   => ['seller_offer', 'offer_status', 'offer_id', 'inactive'],
            ];
            [$table, $col, $key, $off] = $toggles[$p['entity'] ?? ''] ?? throw new InventoryException('Unknown catalogue entity.');
            $db->prepare("UPDATE $table SET $col = IF($col = 'active', ?, 'active') WHERE $key = ?")->execute([$off, int_in($p, 'id')]);
            flash('success', 'Status updated.');
            break;

        case 'add_offer':
            $db->prepare('INSERT INTO seller_offer (seller_id, product_id, selling_price) VALUES (?, ?, ?)')
               ->execute([int_in($p, 'seller_id'), int_in($p, 'product_id'), (float) ($p['price'] ?? 0)]);
            flash('success', 'Seller offer #' . $db->lastInsertId() . ' added. You can now create its inventory record.');
            break;

        case 'add_product':
            $db->prepare('INSERT INTO product (category_id, product_name, description) VALUES (?, ?, ?)')
               ->execute([int_in($p, 'category_id'), trim((string) $p['product_name']), trim((string) ($p['description'] ?? '')) ?: null]);
            flash('success', 'Product added.');
            break;

        case 'add_seller':
            $db->prepare('INSERT INTO seller (seller_name, contact_email) VALUES (?, ?)')
               ->execute([trim((string) $p['seller_name']), strtolower(trim((string) $p['contact_email']))]);
            flash('success', 'Seller added.');
            break;

        default:
            throw new InventoryException('Unknown action.');
    }
}

/** Creates a submitted System 2 order (one seller, like System 2 enforces), then asks Inventory to reserve it. */
function place_order(InventoryService $inv, PDO $db, array $p): void
{
    $sellerId   = int_in($p, 'seller_id');
    $customerId = int_in($p, 'customer_id');
    $address    = trim((string) ($p['address'] ?? '')) ?: '123 Test Street, Manila';

    $wanted = array_filter(array_map('intval', (array) ($p['qty'] ?? [])), fn ($q) => $q > 0);
    if (!$wanted) {
        throw new InventoryException('Enter a quantity for at least one listing.');
    }
    $st = $db->prepare('SELECT o.offer_id, o.selling_price, p.product_name FROM seller_offer o
                          JOIN product p ON p.product_id = o.product_id
                         WHERE o.seller_id = ? AND o.offer_id IN (' . implode(',', array_fill(0, count($wanted), '?')) . ')');
    $st->execute([$sellerId, ...array_keys($wanted)]);
    $offers = $st->fetchAll();
    if (count($offers) !== count($wanted)) {
        throw new InventoryException('Every item in an order must come from the selected seller.');
    }
    $st = $db->prepare('SELECT customer_name, contact_number FROM customer WHERE customer_id = ?');
    $st->execute([$customerId]);
    $customer = $st->fetch() ?: throw new InventoryException('Choose a customer.');

    $total = 0.0;
    foreach ($offers as $o) {
        $total += $wanted[$o['offer_id']] * (float) $o['selling_price'];
    }

    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO sales_order (order_number, customer_id, seller_id, order_date, order_status, order_total,
                                               recipient_name, recipient_contact, requested_delivery_address)
                      VALUES (?, ?, ?, NOW(), 'pending_stock', ?, ?, ?, ?)")
           ->execute(['SO-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4))), $customerId, $sellerId,
                      number_format($total, 2, '.', ''), $customer['customer_name'], $customer['contact_number'] ?: 'n/a', $address]);
        $orderId = (int) $db->lastInsertId();
        $item = $db->prepare('INSERT INTO order_item (order_id, seller_id, offer_id, product_name_at_order, quantity, unit_price_at_order)
                              VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($offers as $o) {
            $item->execute([$orderId, $sellerId, $o['offer_id'], $o['product_name'], $wanted[$o['offer_id']], $o['selling_price']]);
        }
        log_status($db, $orderId, null, 'pending_stock', 'sales', 'Order submitted; waiting for all-item stock reservation');
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    try {
        $inv->reserve($orderId);
        set_order_status($db, $orderId, 'awaiting_payment', 'inventory', 'All items reserved');
        flash('success', "Order #$orderId placed — every item is reserved. Waiting for payment.");
    } catch (InventoryException $e) {
        set_order_status($db, $orderId, 'cancelled', 'inventory', 'Stock unavailable: ' . implode('; ', array_column($e->details, 'reason')));
        throw new InventoryException("Order #$orderId was rejected. " . $e->getMessage(), $e->details);
    }
}

function log_status(PDO $db, int $orderId, ?string $from, string $to, string $source, string $reason): void
{
    $db->prepare('INSERT INTO order_status_history (order_id, previous_status, new_status, change_source, reason) VALUES (?, ?, ?, ?, ?)')
       ->execute([$orderId, $from, $to, $source, $reason]);
}

function set_order_status(PDO $db, int $orderId, string $status, string $source, string $reason): void
{
    $st = $db->prepare('SELECT order_status FROM sales_order WHERE order_id = ?');
    $st->execute([$orderId]);
    $from = $st->fetchColumn() ?: null;
    $db->prepare('UPDATE sales_order SET order_status = ?, cancellation_reason = IF(? = \'cancelled\', ?, cancellation_reason) WHERE order_id = ?')
       ->execute([$status, $status, $reason, $orderId]);
    log_status($db, $orderId, $from, $status, $source, $reason);
}

/** Which simulator steps each System 2 order status offers. */
function order_events(string $status): array
{
    return match ($status) {
        'awaiting_payment'     => ['payment_success' => 'Payment verified', 'payment_failed' => 'Payment failed', 'cancel' => 'Cancel'],
        'confirmed'            => ['dispatch' => 'Dispatch shipment', 'cancel' => 'Cancel'],
        'fulfilling'           => ['delivered' => 'Delivered', 'delivery_failed' => 'Delivery failed', 'cancel' => 'Cancel'],
        'cancellation_pending' => ['receive_return' => 'Goods received back'],
        default                => [],
    };
}

function order_event(InventoryService $inv, PDO $db, int $orderId, string $event): void
{
    $st = $db->prepare('SELECT order_status FROM sales_order WHERE order_id = ?');
    $st->execute([$orderId]);
    $status = $st->fetchColumn();
    if ($status === false || !isset(order_events($status)[$event])) {
        throw new InventoryException("That step is not available for order #$orderId right now.");
    }

    switch ($event) {
        case 'payment_success':   // System 4
            set_order_status($db, $orderId, 'confirmed', 'billing', 'Payment verified');
            flash('success', "Order #$orderId paid. Delivery can now create the shipment.");
            break;
        case 'payment_failed':    // System 4 → Inventory releases
            $r = $inv->release($orderId);
            set_order_status($db, $orderId, 'cancelled', 'billing', 'Payment failed');
            flash('success', "Payment failed for order #$orderId — released {$r['units']} held units.");
            break;
        case 'cancel':            // System 2
            if ($status === 'fulfilling') {
                set_order_status($db, $orderId, 'cancellation_pending', 'sales', 'Cancelled after dispatch; waiting for returned goods');
                flash('info', "Order #$orderId cancelled after dispatch. Nothing is released; stock comes back when the goods are received.");
            } else {
                $r = $inv->release($orderId);
                set_order_status($db, $orderId, 'cancelled', 'sales', 'Cancelled before dispatch');
                flash('success', "Order #$orderId cancelled — released {$r['units']} held units.");
            }
            break;
        case 'dispatch':          // System 5 → Inventory fulfills
            $inv->fulfill($orderId);
            set_order_status($db, $orderId, 'fulfilling', 'delivery', 'Delivery accepted shipment; stock deducted');
            flash('success', "Order #$orderId dispatched — stock deducted and sale lines recorded.");
            break;
        case 'delivered':         // System 5
            set_order_status($db, $orderId, 'completed', 'delivery', 'Delivery confirmed');
            flash('success', "Order #$orderId delivered.");
            break;
        case 'delivery_failed':   // System 5 → Inventory records return
        case 'receive_return':
            $reason = $event === 'delivery_failed' ? 'Delivery failed, package returned' : 'Cancelled after dispatch, goods received';
            $inv->returnOrder($orderId, $reason);
            set_order_status($db, $orderId, 'cancelled', $event === 'delivery_failed' ? 'delivery' : 'inventory', $reason);
            flash('success', "Order #$orderId returned — units added back to on hand.");
            break;
    }
}
