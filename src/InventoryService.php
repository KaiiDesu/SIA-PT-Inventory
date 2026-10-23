<?php
declare(strict_types=1);

/**
 * A business-rule violation. $details carries structured data for the caller,
 * e.g. the short items of a failed reservation. $status is the HTTP code the API returns.
 */
class InventoryException extends RuntimeException
{
    public function __construct(string $message, public array $details = [], public int $status = 422)
    {
        parent::__construct($message);
    }
}

/**
 * System 3 – Inventory Management.
 *
 * Owns three tables: inventory (the shelf), stock_reservation (holds for order lines)
 * and stock_movement (the ledger). It reads System 1's seller_offer / product / seller
 * and System 2's sales_order / order_item by ID, and never writes them.
 * Every rule from section 4 of the paper lives here, so the web UI and the JSON API
 * behave identically.
 */
class InventoryService
{
    public const MOVEMENT_TYPES = ['restock', 'sale', 'return', 'adjustment_in', 'adjustment_out'];
    public const INCREASING     = ['restock', 'return', 'adjustment_in'];

    public function __construct(private PDO $db) {}

    // ---------------------------------------------------------------
    //  Shelves
    // ---------------------------------------------------------------

    /** Creates the inventory record for an existing System 1 seller offer. */
    public function createInventory(int $offerId, int $reorderLevel = 0, int $initialQty = 0): int
    {
        if ($reorderLevel < 0 || $initialQty < 0) {
            throw new InventoryException('Reorder level and initial quantity cannot be negative.');
        }

        return $this->transaction(function () use ($offerId, $reorderLevel, $initialQty) {
            $offer = $this->fetch('SELECT seller_id FROM seller_offer WHERE offer_id = ?', [$offerId]);
            if (!$offer) {
                throw new InventoryException("Offer #$offerId does not exist in the Catalogue.", [], 404);
            }
            if ($this->shelfId($offerId) !== null) {
                throw new InventoryException("Offer #$offerId already has an inventory record.", [], 409);
            }

            $this->run('INSERT INTO inventory (offer_id, seller_id, reorder_level) VALUES (?, ?, ?)',
                [$offerId, $offer['seller_id'], $reorderLevel]);
            $id = (int) $this->db->lastInsertId();

            if ($initialQty > 0) {
                $this->recordMovement($id, 'restock', $initialQty, remarks: 'Initial stock count');
            }
            return $id;
        });
    }

    public function restock(int $inventoryId, int $qty, ?string $remarks = null): int
    {
        return $this->transaction(fn () => $this->recordMovement($inventoryId, 'restock', $qty, remarks: $remarks));
    }

    /** Damaged, lost or miscounted stock. $direction is 'in' or 'out'; remarks are required. */
    public function adjust(int $inventoryId, string $direction, int $qty, string $remarks): int
    {
        if (!in_array($direction, ['in', 'out'], true)) {
            throw new InventoryException("Adjustment direction must be 'in' or 'out'.");
        }
        return $this->transaction(
            fn () => $this->recordMovement($inventoryId, "adjustment_$direction", $qty, remarks: $remarks)
        );
    }

    public function setReorderLevel(int $inventoryId, int $level): void
    {
        if ($level < 0) {
            throw new InventoryException('Reorder level cannot be negative.');
        }
        $this->requireShelf($inventoryId);
        $this->run('UPDATE inventory SET reorder_level = ? WHERE inventory_id = ?', [$level, $inventoryId]);
    }

    // ---------------------------------------------------------------
    //  Reservations (order lifecycle)
    // ---------------------------------------------------------------

    /**
     * All-or-nothing hold for every line of a System 2 order.
     *
     * $items is optional. When given (System 2's reserve payload), each entry needs
     * order_item_id or offer_id plus quantity, and together they must match the order's
     * lines exactly. When omitted, the order's lines are read from order_item.
     * On failure nothing is reserved and the exception details list the short items.
     */
    public function reserve(int $orderId, ?array $items = null): array
    {
        $order = $this->fetch('SELECT order_id, seller_id FROM sales_order WHERE order_id = ?', [$orderId]);
        if (!$order) {
            throw new InventoryException("Order #$orderId does not exist.", [], 404);
        }
        $lines = $this->fetchAll(
            'SELECT order_item_id, offer_id, product_name_at_order, quantity FROM order_item WHERE order_id = ? ORDER BY order_item_id',
            [$orderId]
        );
        if (!$lines) {
            throw new InventoryException("Order #$orderId has no items to reserve.");
        }
        if ($items) {
            $this->matchRequestToLines($orderId, $items, $lines);
        }

        return $this->transaction(function () use ($orderId, $lines) {
            // Lock every requested shelf (in id order, to avoid deadlocks) so two
            // customers racing for the last unit cannot both succeed.
            $offerIds = array_column($lines, 'offer_id');
            $rows = $this->fetchAll(
                'SELECT i.inventory_id, i.offer_id, i.quantity_on_hand, i.quantity_reserved,
                        o.offer_status, p.product_status, s.seller_status
                   FROM inventory i
                   JOIN seller_offer o ON o.offer_id = i.offer_id
                   JOIN product p      ON p.product_id = o.product_id
                   JOIN seller s       ON s.seller_id = i.seller_id
                  WHERE i.offer_id IN (' . $this->placeholders($offerIds) . ')
                  ORDER BY i.inventory_id
                    FOR UPDATE',
                $offerIds
            );
            $shelves = array_column($rows, null, 'offer_id');
            $held = array_flip(array_column(
                $this->fetchAll('SELECT order_item_id FROM stock_reservation WHERE order_id = ?', [$orderId]),
                'order_item_id'
            ));

            $short = [];
            foreach ($lines as $line) {
                $shelf     = $shelves[$line['offer_id']] ?? null;
                $available = $shelf ? $shelf['quantity_on_hand'] - $shelf['quantity_reserved'] : 0;
                $reason    = match (true) {
                    $shelf === null                         => 'No inventory record for this offer',
                    $shelf['seller_status'] !== 'active'    => 'Seller is inactive',
                    $shelf['product_status'] !== 'active'   => 'Product is discontinued',
                    $shelf['offer_status'] !== 'active'     => 'Seller offer is inactive',
                    isset($held[$line['order_item_id']])    => 'This order line already has a hold',
                    $available < $line['quantity']          => "Only $available available",
                    default                                 => null,
                };
                if ($reason !== null) {
                    $short[] = [
                        'order_item_id' => (int) $line['order_item_id'],
                        'offer_id'      => (int) $line['offer_id'],
                        'product_name'  => $line['product_name_at_order'],
                        'quantity'      => (int) $line['quantity'],
                        'available'     => $available,
                        'reason'        => $reason,
                    ];
                }
            }
            if ($short) {
                throw new InventoryException(
                    'Reservation failed: not every item could be reserved, so nothing was held.', $short, 409
                );
            }

            $created = [];
            foreach ($lines as $line) {
                $id = (int) $shelves[$line['offer_id']]['inventory_id'];
                $this->run('UPDATE inventory SET quantity_reserved = quantity_reserved + ? WHERE inventory_id = ?',
                    [$line['quantity'], $id]);
                $this->run('INSERT INTO stock_reservation (inventory_id, order_id, order_item_id, quantity) VALUES (?, ?, ?, ?)',
                    [$id, $orderId, $line['order_item_id'], $line['quantity']]);
                $created[] = [
                    'reservation_id' => (int) $this->db->lastInsertId(),
                    'order_item_id'  => (int) $line['order_item_id'],
                    'offer_id'       => (int) $line['offer_id'],
                    'inventory_id'   => $id,
                    'quantity'       => (int) $line['quantity'],
                ];
            }
            return ['order_id' => $orderId, 'status' => 'reserved', 'reservations' => $created];
        });
    }

    /** Order cancelled, or payment failed/refunded before dispatch: frees the held units. */
    public function release(int $orderId): array
    {
        return $this->transaction(function () use ($orderId) {
            $holds = $this->lockHolds($orderId, 'active');
            if (!$holds) {
                throw $this->noActiveHolds($orderId);
            }
            foreach ($holds as $h) {
                $this->run('UPDATE inventory SET quantity_reserved = quantity_reserved - ? WHERE inventory_id = ?',
                    [$h['quantity'], $h['inventory_id']]);
                $this->run("UPDATE stock_reservation SET reservation_status = 'released' WHERE reservation_id = ?",
                    [$h['reservation_id']]);
            }
            return ['order_id' => $orderId, 'status' => 'released', 'released' => count($holds),
                    'units' => array_sum(array_column($holds, 'quantity'))];
        });
    }

    /** Shipment dispatched: deducts on-hand stock and writes a sale line per hold. */
    public function fulfill(int $orderId): array
    {
        return $this->transaction(function () use ($orderId) {
            $holds = $this->lockHolds($orderId, 'active');
            if (!$holds) {
                throw $this->noActiveHolds($orderId);
            }
            $movements = [];
            foreach ($holds as $h) {
                $movements[] = $this->recordMovement((int) $h['inventory_id'], 'sale', (int) $h['quantity'],
                    reservationId: (int) $h['reservation_id'], orderId: $orderId);
                $this->run("UPDATE stock_reservation SET reservation_status = 'fulfilled' WHERE reservation_id = ?",
                    [$h['reservation_id']]);
            }
            return ['order_id' => $orderId, 'status' => 'fulfilled', 'fulfilled' => count($holds), 'movement_ids' => $movements];
        });
    }

    /** Package came back (failed delivery, or cancelled after dispatch): returns shipped units to on hand. */
    public function returnOrder(int $orderId, ?string $remarks = null): array
    {
        return $this->transaction(function () use ($orderId, $remarks) {
            $holds = $this->lockHolds($orderId, 'fulfilled');
            if (!$holds) {
                throw new InventoryException("Order #$orderId has no shipped items to return.", [], 409);
            }
            $movements = [];
            foreach ($holds as $h) {
                $returned = (int) $this->fetch(
                    "SELECT COALESCE(SUM(quantity), 0) AS n FROM stock_movement
                      WHERE movement_type = 'return' AND order_id = ? AND inventory_id = ?",
                    [$orderId, $h['inventory_id']]
                )['n'];
                $remaining = (int) $h['quantity'] - $returned;
                if ($remaining > 0) {
                    $movements[] = $this->recordMovement((int) $h['inventory_id'], 'return', $remaining,
                        orderId: $orderId, remarks: $remarks ?: 'Package returned');
                }
            }
            if (!$movements) {
                throw new InventoryException("Order #$orderId has already been returned.", [], 409);
            }
            return ['order_id' => $orderId, 'status' => 'returned', 'movement_ids' => $movements];
        });
    }

    // ---------------------------------------------------------------
    //  Read side
    // ---------------------------------------------------------------

    /** Rows from v_inventory_stock. Filters: inventory_id, offer_id, seller_id, product_id, status, q, alerts. */
    public function stock(array $f = []): array
    {
        [$where, $params] = $this->filters($f, [
            'inventory_id' => 'inventory_id = ?',
            'offer_id'     => 'offer_id = ?',
            'seller_id'    => 'seller_id = ?',
            'product_id'   => 'product_id = ?',
            'status'       => 'stock_status = ?',
        ]);
        if (!empty($f['q'])) {
            $where[] = '(product_name LIKE ? OR seller_name LIKE ? OR category_name LIKE ?)';
            $like    = '%' . $f['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($f['alerts'])) {
            $where[] = "stock_status IN ('low_stock', 'out_of_stock')";
        }
        $sql = 'SELECT * FROM v_inventory_stock' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY ' . (!empty($f['alerts']) ? 'quantity_available, ' : '') . 'product_name, seller_name';
        return $this->fetchAll($sql, $params);
    }

    public function find(int $inventoryId): ?array
    {
        return $this->stock(['inventory_id' => $inventoryId])[0] ?? null;
    }

    public function available(int $offerId): array
    {
        $row = $this->stock(['offer_id' => $offerId])[0] ?? null;
        if (!$row) {
            throw new InventoryException("Offer #$offerId has no inventory record.", [], 404);
        }
        return $row;
    }

    /** Listings where available ≤ reorder level (low) or available = 0 (out). */
    public function lowStock(): array
    {
        return $this->stock(['alerts' => true]);
    }

    /** Filters: order_id, inventory_id, status. */
    public function reservations(array $f = [], int $limit = 300): array
    {
        [$where, $params] = $this->filters($f, [
            'order_id'     => 'r.order_id = ?',
            'inventory_id' => 'r.inventory_id = ?',
            'status'       => 'r.reservation_status = ?',
        ]);
        return $this->fetchAll(
            'SELECT r.*, so.order_number, v.offer_id, v.seller_name, v.product_name
               FROM stock_reservation r
               JOIN sales_order so       ON so.order_id = r.order_id
               JOIN v_inventory_stock v  ON v.inventory_id = r.inventory_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY r.reservation_id DESC LIMIT ' . $limit,
            $params
        );
    }

    /** Reservation status per order, for Billing and Delivery. */
    public function orderStatus(int $orderId): array
    {
        $rows     = $this->reservations(['order_id' => $orderId]);
        $statuses = array_unique(array_column($rows, 'reservation_status'));
        $overall  = match (count($statuses)) {
            0       => 'none',
            1       => $statuses[array_key_first($statuses)],
            default => 'mixed',
        };
        return [
            'order_id'       => $orderId,
            'status'         => $overall,
            'stock_reserved' => $overall === 'active',
            'reservations'   => $rows,
        ];
    }

    /** Filters: inventory_id, order_id, type, seller_id. */
    public function movements(array $f = [], int $limit = 300): array
    {
        [$where, $params] = $this->filters($f, [
            'inventory_id' => 'm.inventory_id = ?',
            'order_id'     => 'm.order_id = ?',
            'type'         => 'm.movement_type = ?',
            'seller_id'    => 'v.seller_id = ?',
        ]);
        return $this->fetchAll(
            "SELECT m.*, so.order_number, v.offer_id, v.seller_id, v.seller_name, v.product_name,
                    CASE WHEN m.movement_type IN ('restock', 'return', 'adjustment_in') THEN m.quantity ELSE -m.quantity END AS signed_quantity
               FROM stock_movement m
               JOIN v_inventory_stock v ON v.inventory_id = m.inventory_id
               LEFT JOIN sales_order so ON so.order_id = m.order_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY m.movement_id DESC LIMIT ' . $limit,
            $params
        );
    }

    public function summary(): array
    {
        $s = $this->fetch(
            "SELECT COUNT(*) AS listings,
                    COALESCE(SUM(quantity_on_hand), 0)   AS on_hand,
                    COALESCE(SUM(quantity_reserved), 0)  AS reserved,
                    COALESCE(SUM(quantity_available), 0) AS available,
                    COALESCE(SUM(stock_status = 'low_stock'), 0)    AS low_stock,
                    COALESCE(SUM(stock_status = 'out_of_stock'), 0) AS out_of_stock,
                    COALESCE(SUM(quantity_available * selling_price), 0) AS available_value
               FROM v_inventory_stock"
        );
        $s['active_holds'] = $this->fetch(
            "SELECT COUNT(*) AS n FROM stock_reservation WHERE reservation_status = 'active'"
        )['n'];
        return array_map('floatval', $s);
    }

    /** System 1 seller offers that have no inventory record yet. */
    public function unstockedOffers(): array
    {
        return $this->fetchAll(
            'SELECT o.offer_id, s.seller_name, p.product_name, o.offer_status
               FROM seller_offer o
               JOIN seller s  ON s.seller_id = o.seller_id
               JOIN product p ON p.product_id = o.product_id
               LEFT JOIN inventory i ON i.offer_id = o.offer_id
              WHERE i.inventory_id IS NULL
              ORDER BY p.product_name, s.seller_name'
        );
    }

    public function shelfId(int $offerId): ?int
    {
        $row = $this->fetch('SELECT inventory_id FROM inventory WHERE offer_id = ?', [$offerId]);
        return $row ? (int) $row['inventory_id'] : null;
    }

    // ---------------------------------------------------------------
    //  Internals
    // ---------------------------------------------------------------

    /**
     * The only path that changes quantity_on_hand: updates the shelf and writes the
     * ledger line in the caller's transaction. A sale also consumes its reserved units.
     */
    private function recordMovement(
        int $inventoryId,
        string $type,
        int $qty,
        ?int $reservationId = null,
        ?int $orderId = null,
        ?string $remarks = null
    ): int {
        if ($qty <= 0) {
            throw new InventoryException('Enter a number of units greater than 0.');
        }
        $remarks = $remarks !== null && trim($remarks) !== '' ? trim($remarks) : null;
        if (str_starts_with($type, 'adjustment_') && $remarks === null) {
            throw new InventoryException('Please give a reason for fixing the count, e.g. damaged, lost or miscounted.');
        }

        $shelf    = $this->requireShelf($inventoryId, lock: true);
        $onHand   = $shelf['quantity_on_hand'] + (in_array($type, self::INCREASING, true) ? $qty : -$qty);
        $reserved = $shelf['quantity_reserved'] - ($type === 'sale' ? $qty : 0);

        if ($onHand < 0) {
            throw new InventoryException("There are only {$shelf['quantity_on_hand']} in the warehouse, so $qty can't be removed.");
        }
        if ($onHand < $reserved) {
            $free = $shelf['quantity_on_hand'] - $shelf['quantity_reserved'];
            throw new InventoryException(
                "You can remove at most $free: the other $reserved units are reserved for customers' orders that haven't shipped yet."
            );
        }

        $this->run('UPDATE inventory SET quantity_on_hand = ?, quantity_reserved = ? WHERE inventory_id = ?',
            [$onHand, $reserved, $inventoryId]);
        $this->run(
            'INSERT INTO stock_movement (inventory_id, reservation_id, movement_type, quantity, on_hand_after, order_id, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$inventoryId, $reservationId, $type, $qty, $onHand, $orderId, $remarks]
        );
        return (int) $this->db->lastInsertId();
    }

    /** A caller-supplied item list must name exactly the order's lines and Sales' accepted quantities. */
    private function matchRequestToLines(int $orderId, array $items, array $lines): void
    {
        $byItem  = array_column($lines, null, 'order_item_id');
        $byOffer = array_column($lines, null, 'offer_id');
        $seen    = [];
        foreach ($items as $item) {
            $line = isset($item['order_item_id']) ? ($byItem[(int) $item['order_item_id']] ?? null)
                                                  : ($byOffer[(int) ($item['offer_id'] ?? 0)] ?? null);
            if (!$line || (isset($item['offer_id']) && (int) $item['offer_id'] !== (int) $line['offer_id'])) {
                throw new InventoryException("An item in the request is not a line of order #$orderId.");
            }
            if ((int) ($item['quantity'] ?? 0) !== (int) $line['quantity']) {
                throw new InventoryException("Quantity for order line #{$line['order_item_id']} does not match the order ({$line['quantity']}).");
            }
            $seen[$line['order_item_id']] = true;
        }
        if (count($seen) !== count($lines) || count($items) !== count($lines)) {
            throw new InventoryException("The request must list every line of order #$orderId exactly once.");
        }
    }

    private function requireShelf(int $inventoryId, bool $lock = false): array
    {
        $row = $this->fetch('SELECT * FROM inventory WHERE inventory_id = ?' . ($lock ? ' FOR UPDATE' : ''), [$inventoryId]);
        if (!$row) {
            throw new InventoryException("Inventory record #$inventoryId does not exist.", [], 404);
        }
        return $row;
    }

    /** Locks an order's holds of one status together with their shelves, in shelf order. */
    private function lockHolds(int $orderId, string $status): array
    {
        return $this->fetchAll(
            'SELECT r.reservation_id, r.inventory_id, r.quantity
               FROM stock_reservation r
               JOIN inventory i ON i.inventory_id = r.inventory_id
              WHERE r.order_id = ? AND r.reservation_status = ?
              ORDER BY r.inventory_id
                FOR UPDATE',
            [$orderId, $status]
        );
    }

    private function noActiveHolds(int $orderId): InventoryException
    {
        $shipped = $this->fetch(
            "SELECT 1 FROM stock_reservation WHERE order_id = ? AND reservation_status = 'fulfilled' LIMIT 1", [$orderId]
        );
        return new InventoryException(
            $shipped
                ? "Order #$orderId was already dispatched. Stock comes back only through a return."
                : "Order #$orderId has no active reservations.",
            [], 409
        );
    }

    private function filters(array $f, array $map): array
    {
        $where = $params = [];
        foreach ($map as $key => $clause) {
            if (isset($f[$key]) && $f[$key] !== '') {
                $where[]  = $clause;
                $params[] = $f[$key];
            }
        }
        return [$where, $params];
    }

    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    private function transaction(callable $fn): mixed
    {
        $this->db->beginTransaction();
        try {
            $result = $fn();
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function run(string $sql, array $params = []): PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st;
    }

    private function fetch(string $sql, array $params = []): ?array
    {
        return $this->run($sql, $params)->fetch() ?: null;
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }
}
