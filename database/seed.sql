-- Sample data for a standalone install.
-- Catalogue rows 1–6 match System 2's demo data (src/db.mjs),
-- so offer and product IDs mean the same thing in both apps. Rows 7+ exist to
-- exercise Inventory rules: discontinued product, inactive offer, inactive seller,
-- and the paper's "two sellers, same product" scenario.

-- ---------- System 1: Catalogue ----------
INSERT INTO category (category_id, category_name, description) VALUES
    (1, 'Campus essentials', 'Local demonstration catalogue');

INSERT INTO seller (seller_id, seller_name, contact_email, seller_status) VALUES
    (1, 'Campus Supply Co.', 'supply@example.test', 'active'),
    (2, 'Studio Paper',      'paper@example.test',  'active'),
    (3, 'Old Stock Trading', 'oldstock@example.test', 'inactive');

INSERT INTO product (product_id, category_id, product_name, description, product_status) VALUES
    (1, 1, 'Everyday notebook',   'A5 ruled · 80 pages',             'active'),
    (2, 1, 'Canvas tote bag',     'Natural cotton · campus edition', 'active'),
    (3, 1, 'Stainless tumbler',   '500 ml · midnight blue',          'active'),
    (4, 1, 'Gel pen set',         'Black ink · pack of 3',           'active'),
    (5, 1, 'Weekly desk planner', 'Undated · 52 sheets',             'active'),
    (6, 1, 'Index cards',         'Pastel · pack of 100',            'active'),
    (7, 1, 'Highlighter set',     'Assorted · pack of 5',            'discontinued'),
    (8, 1, 'Sticky notes',        '3x3 in · 12 pads',                'active');

INSERT INTO seller_offer (offer_id, seller_id, product_id, selling_price, offer_status) VALUES
    (1,  1, 1,  89.50, 'active'),
    (2,  1, 2, 249.00, 'active'),
    (3,  1, 3, 495.00, 'active'),
    (4,  1, 4,  75.25, 'active'),
    (5,  2, 5, 159.00, 'active'),
    (6,  2, 6,  65.00, 'active'),
    (7,  1, 7, 120.00, 'active'),
    (8,  2, 8,  55.00, 'inactive'),
    (9,  3, 6,  60.00, 'active'),
    (10, 2, 1,  85.00, 'active'),     -- Studio Paper also sells the notebook: a separate shelf
    (11, 2, 2, 239.00, 'active');     -- live listing with no stock counted yet

-- ---------- System 2: Sales / Order ----------
INSERT INTO customer (customer_id, customer_name, email, contact_number) VALUES
    (1, 'Klloyd Robert Advincula', 'klloyd@example.test', '09171234567'),
    (2, 'Lance Perez',             'lance@example.test',  '09181234567'),
    (3, 'Roi Perez',               'roi@example.test',    '09191234567'),
    (4, 'Ken Oconer',              'ken@example.test',    '09201234567');

-- The paper's scenario: Seller B has 15 of a product, a customer checks out with 3,
-- so 12 stay sellable until the box ships.
INSERT INTO sales_order (order_id, order_number, customer_id, seller_id, order_date, order_status, order_total,
                         recipient_name, recipient_contact, requested_delivery_address) VALUES
    (1, 'SO-2026-INV00001', 2, 2, NOW() - INTERVAL 1 HOUR, 'awaiting_payment', 255.00,
     'Lance Perez', '09181234567', '45 Taft Avenue, Manila');

INSERT INTO order_item (order_item_id, order_id, seller_id, offer_id, product_name_at_order, quantity, unit_price_at_order) VALUES
    (1, 1, 2, 10, 'Everyday notebook', 3, 85.00);

INSERT INTO order_status_history (order_id, previous_status, new_status, change_source, reason, changed_at) VALUES
    (1, NULL,            'pending_stock',    'sales',     'Order submitted; waiting for all-item stock reservation', NOW() - INTERVAL 1 HOUR),
    (1, 'pending_stock', 'awaiting_payment', 'inventory', 'All items reserved',                                       NOW() - INTERVAL 1 HOUR);

-- ---------- System 3: Inventory ----------
INSERT INTO inventory (inventory_id, offer_id, seller_id, quantity_on_hand, quantity_reserved, reorder_level) VALUES
    (1,  1,  1, 120, 0, 20),
    (2,  2,  1,  18, 0,  5),
    (3,  3,  1,   8, 0, 10),
    (4,  4,  1,  60, 0, 15),
    (5,  5,  2,   0, 0,  5),
    (6,  6,  2, 200, 0, 30),
    (7,  7,  1,  12, 0,  0),
    (8,  8,  2,  25, 0,  5),
    (9,  9,  3,  10, 0,  2),
    (10, 10, 2,  15, 3,  5);

INSERT INTO stock_movement (inventory_id, movement_type, quantity, on_hand_after, remarks, created_at) VALUES
    (1,  'restock',        120, 120, 'Initial stock count',                 NOW() - INTERVAL 14 DAY),
    (2,  'restock',         18,  18, 'Initial stock count',                 NOW() - INTERVAL 14 DAY),
    (3,  'restock',         10,  10, 'Initial stock count',                 NOW() - INTERVAL 14 DAY),
    (4,  'restock',         60,  60, 'Initial stock count',                 NOW() - INTERVAL 14 DAY),
    (5,  'restock',          4,   4, 'Initial stock count',                 NOW() - INTERVAL 14 DAY),
    (6,  'restock',        200, 200, 'Initial stock count',                 NOW() - INTERVAL 14 DAY),
    (7,  'restock',         12,  12, 'Initial stock count',                 NOW() - INTERVAL 14 DAY),
    (8,  'restock',         25,  25, 'Initial stock count',                 NOW() - INTERVAL 14 DAY),
    (9,  'restock',         10,  10, 'Initial stock count',                 NOW() - INTERVAL 14 DAY),
    (10, 'restock',         15,  15, 'Initial stock count',                 NOW() - INTERVAL 14 DAY),
    (3,  'adjustment_out',   2,   8, 'Damaged in storage (water leak)',     NOW() - INTERVAL 6 DAY),
    (5,  'adjustment_out',   4,   0, 'Lost in transit to seller warehouse', NOW() - INTERVAL 3 DAY);

INSERT INTO stock_reservation (inventory_id, order_id, order_item_id, quantity, reservation_status, created_at) VALUES
    (10, 1, 1, 3, 'active', NOW() - INTERVAL 1 HOUR);
