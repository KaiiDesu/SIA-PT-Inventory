-- =============================================================
--  Mini-ERP · System 3: Inventory Management
--  Universidad de Manila · College of Computing Studies
--
--  Requires the shared System 1 & 2 tables (reference/System1_and_2.sql):
--  seller, product, seller_offer, sales_order, order_item.
--  This file only creates and drops Inventory's own objects, so it is
--  safe to run inside the shared mini_erp database.
-- =============================================================

DROP VIEW  IF EXISTS v_inventory_stock;
DROP TABLE IF EXISTS stock_movement;
DROP TABLE IF EXISTS stock_reservation;
DROP TABLE IF EXISTS inventory;

-- A. Inventory: the current count for one listing (the "shelf").
-- A listing is one System 1 seller_offer, i.e. one seller + product pair.
CREATE TABLE inventory (
    inventory_id      INT      NOT NULL AUTO_INCREMENT,
    offer_id          INT      NOT NULL,
    seller_id         INT      NOT NULL,
    quantity_on_hand  INT      NOT NULL DEFAULT 0,
    quantity_reserved INT      NOT NULL DEFAULT 0,
    reorder_level     INT      NOT NULL DEFAULT 0,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (inventory_id),
    UNIQUE KEY uq_inventory_offer (offer_id),
    -- Same composite key System 2's order_item uses, so the seller always matches the offer.
    CONSTRAINT fk_inventory_offer FOREIGN KEY (offer_id, seller_id)
        REFERENCES seller_offer (offer_id, seller_id),
    CONSTRAINT chk_inventory_on_hand  CHECK (quantity_on_hand >= 0),
    CONSTRAINT chk_inventory_reserved CHECK (quantity_reserved >= 0 AND quantity_reserved <= quantity_on_hand),
    CONSTRAINT chk_inventory_reorder  CHECK (reorder_level >= 0)
) ENGINE = InnoDB;

-- B. StockReservation: a hold placed on a shelf for one order line.
CREATE TABLE stock_reservation (
    reservation_id     INT         NOT NULL AUTO_INCREMENT,
    inventory_id       INT         NOT NULL,
    order_id           INT         NOT NULL,
    order_item_id      INT         NOT NULL,
    quantity           INT         NOT NULL,
    reservation_status VARCHAR(10) NOT NULL DEFAULT 'active',
    created_at         DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (reservation_id),
    UNIQUE KEY uq_reservation_order_shelf (order_id, inventory_id),
    UNIQUE KEY uq_reservation_item (order_item_id),
    KEY idx_reservation_status (reservation_status),
    CONSTRAINT fk_reservation_inventory FOREIGN KEY (inventory_id)  REFERENCES inventory (inventory_id),
    CONSTRAINT fk_reservation_order     FOREIGN KEY (order_id)      REFERENCES sales_order (order_id),
    CONSTRAINT fk_reservation_item      FOREIGN KEY (order_item_id) REFERENCES order_item (order_item_id),
    CONSTRAINT chk_reservation_qty    CHECK (quantity > 0),
    CONSTRAINT chk_reservation_status CHECK (reservation_status IN ('active', 'fulfilled', 'released'))
) ENGINE = InnoDB;

-- C. StockMovement: the append-only ledger behind quantity_on_hand.
CREATE TABLE stock_movement (
    movement_id    INT          NOT NULL AUTO_INCREMENT,
    inventory_id   INT          NOT NULL,
    reservation_id INT          NULL,
    movement_type  VARCHAR(15)  NOT NULL,
    quantity       INT          NOT NULL,
    on_hand_after  INT          NOT NULL,
    order_id       INT          NULL,
    remarks        VARCHAR(255) NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (movement_id),
    KEY idx_movement_type (movement_type),
    CONSTRAINT fk_movement_inventory   FOREIGN KEY (inventory_id)   REFERENCES inventory (inventory_id),
    CONSTRAINT fk_movement_reservation FOREIGN KEY (reservation_id) REFERENCES stock_reservation (reservation_id),
    CONSTRAINT fk_movement_order       FOREIGN KEY (order_id)       REFERENCES sales_order (order_id),
    CONSTRAINT chk_movement_qty     CHECK (quantity > 0),
    CONSTRAINT chk_movement_balance CHECK (on_hand_after >= 0),
    CONSTRAINT chk_movement_type    CHECK (movement_type IN ('restock', 'sale', 'return', 'adjustment_in', 'adjustment_out')),
    -- Only a shipped hold produces a sale line, and every sale line comes from one.
    CONSTRAINT chk_movement_reservation CHECK (
        (movement_type = 'sale' AND reservation_id IS NOT NULL AND order_id IS NOT NULL)
        OR (movement_type <> 'sale' AND reservation_id IS NULL)
    ),
    CONSTRAINT chk_movement_remarks CHECK (
        movement_type NOT IN ('adjustment_in', 'adjustment_out')
        OR (remarks IS NOT NULL AND TRIM(remarks) <> '')
    )
) ENGINE = InnoDB;

-- Movements are never edited or deleted; mistakes are corrected with a new adjustment.
DROP TRIGGER IF EXISTS trg_movement_no_update;
DROP TRIGGER IF EXISTS trg_movement_no_delete;
DELIMITER $$
CREATE TRIGGER trg_movement_no_update BEFORE UPDATE ON stock_movement
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Stock movements are permanent. Record an adjustment instead.';
END$$

CREATE TRIGGER trg_movement_no_delete BEFORE DELETE ON stock_movement
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Stock movements are permanent. Record an adjustment instead.';
END$$
DELIMITER ;

-- Available stock is always calculated (on hand − reserved), never stored.
-- sellable mirrors System 1's v_orderable_offer rule: offer, product and seller all active.
CREATE VIEW v_inventory_stock AS
SELECT
    i.inventory_id,
    i.offer_id,
    i.seller_id,
    s.seller_name,
    o.product_id,
    p.product_name,
    c.category_name,
    o.selling_price,
    i.quantity_on_hand,
    i.quantity_reserved,
    i.quantity_on_hand - i.quantity_reserved AS quantity_available,
    i.reorder_level,
    CASE
        WHEN i.quantity_on_hand - i.quantity_reserved = 0               THEN 'out_of_stock'
        WHEN i.quantity_on_hand - i.quantity_reserved <= i.reorder_level THEN 'low_stock'
        ELSE 'in_stock'
    END AS stock_status,
    s.seller_status,
    p.product_status,
    o.offer_status,
    (o.offer_status = 'active' AND p.product_status = 'active' AND s.seller_status = 'active') AS sellable,
    i.updated_at
FROM inventory i
JOIN seller_offer o ON o.offer_id = i.offer_id
JOIN seller s       ON s.seller_id = i.seller_id
JOIN product p      ON p.product_id = o.product_id
JOIN category c     ON c.category_id = p.category_id;
