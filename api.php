<?php
/**
 * JSON API for the other Mini-ERP systems (section 5 of the paper).
 * Call as api.php?action=<name>. POST bodies may be JSON or form-encoded.
 * Listings are identified by System 1's offer_id; orders by System 2's order_id / order_item_id.
 * Every response is {"success": bool, "data": ...} or {"success": false, "error": "...", "details": [...]}.
 */
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $body): never
{
    http_response_code($status);
    exit(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$json   = json_decode(file_get_contents('php://input') ?: 'null', true);
$in     = array_merge($_GET, $_POST, is_array($json) ? $json : []);

/** Accepts either inventory_id or offer_id. */
$shelf = function () use ($in, $inventory): int {
    if (!empty($in['inventory_id'])) {
        return (int) $in['inventory_id'];
    }
    return $inventory->shelfId(int_in($in, 'offer_id'))
        ?? throw new InventoryException('That offer_id has no inventory record.', [], 404);
};
$pick = fn (array $keys) => array_intersect_key($in, array_flip($keys));

try {
    $data = match ("$method $action") {
        // ---- What Inventory provides ----
        'GET available'    => isset($in['offer_id'])
                                ? $inventory->available(int_in($in, 'offer_id'))
                                : $inventory->stock($pick(['seller_id', 'product_id', 'status', 'q'])),
        'GET reservations' => isset($in['order_id'])
                                ? $inventory->orderStatus(int_in($in, 'order_id'))
                                : $inventory->reservations($pick(['status', 'inventory_id'])),
        'GET low-stock'    => $inventory->lowStock(),
        'GET movements'    => $inventory->movements($pick(['inventory_id', 'order_id', 'type', 'seller_id'])),

        // ---- System 2 – Sales/Order ----
        'POST reserve'     => $inventory->reserve(int_in($in, 'order_id'), isset($in['items']) ? (array) $in['items'] : null),
        'POST release'     => $inventory->release(int_in($in, 'order_id')),   // order cancelled, or System 4 payment failed/refunded

        // ---- System 5 – Delivery/Shipment ----
        'POST fulfill'     => $inventory->fulfill(int_in($in, 'order_id')),   // shipment dispatched
        'POST return'      => $inventory->returnOrder(int_in($in, 'order_id'), $in['remarks'] ?? null),

        // ---- Sellers / admins ----
        'POST inventory'   => ['inventory_id' => $inventory->createInventory(
                                int_in($in, 'offer_id'), int_in($in, 'reorder_level'), int_in($in, 'quantity'))],
        'POST restock'     => ['movement_id' => $inventory->restock($shelf(), int_in($in, 'quantity'), $in['remarks'] ?? null)],
        'POST adjust'      => ['movement_id' => $inventory->adjust($shelf(), (string) ($in['direction'] ?? ''),
                                int_in($in, 'quantity'), (string) ($in['remarks'] ?? ''))],

        default => throw new InventoryException("Unknown endpoint: $method $action", [], 404),
    };
    respond(200, ['success' => true, 'data' => $data]);
} catch (InventoryException $e) {
    $body = ['success' => false, 'error' => $e->getMessage()];
    if ($e->details) {
        $body[$action === 'reserve' ? 'short_items' : 'details'] = $e->details;
    }
    respond($e->status, $body);
} catch (PDOException $e) {
    respond(500, ['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
