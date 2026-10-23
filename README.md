# CampusERP · Inventory Management — Mini-ERP System 3

The stock ledger of the Mini-ERP, built from `DELIVERABLES-INVENTORY.docx`.
It answers one question for the other systems: **how many units of a given seller's product can still be sold right now?**

PHP 8.2 + MySQL 8 / MariaDB (XAMPP). No frameworks or Composer packages.

It is built to sit beside **System 2 (BSIT-44 Sales-Order Management)**:

- **Same ERD.** It runs on System 1 & 2's shared tables (`database/reference/System1_and_2.sql`, copied unchanged from System 2). A stock record is keyed by `offer_id`, and each hold belongs to one `order_item_id`, with the same composite foreign keys System 2 uses.
- **Same look.** It uses CampusERP's palette, type, sidebar, banner, panels, badges and icon set, taken from System 2's `public/style.css` and `public/app.js`.

## Setup (XAMPP)

1. Copy the `inventory-system` folder into `C:\xampp\htdocs\`.
2. Create `config.local.php` next to `config.php` with your MySQL settings. It is ignored by Git, so your password never gets uploaded:
   ```php
   <?php return ['db' => ['pass' => 'your-mysql-password']];
   ```
   You can also set `user`, `name` (database), `host` and `port` there. Leave out anything that matches the defaults in `config.php`.
3. Create the tables. Pick one:

| Situation | Command |
|---|---|
| **Standalone** (testing Inventory alone) | `C:\xampp\php\php.exe database\install.php`<br>Loads System 1 & 2's tables, Inventory's tables and sample data into the database set in `config.local.php` (default `sia_inventory`). Refuses to run if that database holds anything else. |
| **Shared** (System 2 already set up `mini_erp`) | Set `'name' => 'mini_erp'` in `config.local.php`, then run `C:\xampp\php\php.exe database\install.php --shared`<br>Adds only Inventory's 3 tables and view. Never touches Catalogue or Sales data. If they already exist it refuses, unless you add `--reset`. |

4. Start Apache and MySQL, then open <http://localhost/inventory-system/>.

## What's inside

| Path | Purpose |
|---|---|
| `database/reference/System1_and_2.sql` | System 1 & 2's shared schema, unchanged |
| `database/schema.sql` | Inventory's tables (`inventory`, `stock_reservation`, `stock_movement`), the ledger-protection triggers, and the `v_inventory_stock` view that calculates available stock |
| `database/seed.sql` | Sample data. Catalogue IDs 1–6 match System 2's demo data |
| `src/InventoryService.php` | Every business rule from section 4. The UI and the API both go through it |
| `api.php` | JSON endpoints for Systems 1, 2, 4 and 5 (listed below under *Calling it from System 2*) |
| `views/`, `assets/style.css` | The CampusERP-styled admin UI |
| Order simulator, Catalogue pages | Stand-ins for the other systems. They write the shared System 1 & 2 tables with System 2's real order statuses and `order_status_history` |

## How the rules are enforced

| Rule from the paper | Where |
|---|---|
| Available = on hand − reserved, never stored | `v_inventory_stock` view |
| No negative counts; reserved ≤ on hand | Table CHECK constraints |
| A stock record only exists for a real seller offer | FK `(offer_id, seller_id)` → `seller_offer`, one record per offer |
| A reservation is all-or-nothing and lists the short items | `InventoryService::reserve()` |
| Reserving needs the seller, product and offer all active | `reserve()`, using the same rule as System 1's `v_orderable_offer` |
| Two customers buying the last unit at once | `SELECT … FOR UPDATE` on the stock rows inside a transaction (tested: 10 parallel orders for 8 units → 8 succeed, 2 rejected) |
| on_hand only changes through a recorded movement | `recordMovement()` is the only code that writes it, in the same transaction |
| Movements are never edited or deleted | `BEFORE UPDATE` / `BEFORE DELETE` triggers |
| Only a shipped hold produces a sale line | CHECK `chk_movement_reservation` |
| Adjustments need remarks and can't drop below reserved | `recordMovement()` and a CHECK constraint |
| active → fulfilled / released only, both final | `fulfill()` / `release()` only act on `active` holds |
| Cancelled after dispatch: no release, return only | `release()` refuses shipped orders; `returnOrder()` |
| Records with history are never deleted | FKs from `stock_movement` (ON DELETE RESTRICT) |

## Calling it from System 2

```
POST api.php?action=reserve   {"order_id":12}     ← reserves every order_item line of the order
POST api.php?action=reserve   {"order_id":12,"items":[{"order_item_id":30,"offer_id":4,"quantity":2}]}
POST api.php?action=release   {"order_id":12}     ← order cancelled / payment failed
POST api.php?action=fulfill   {"order_id":12}     ← shipment dispatched
POST api.php?action=return    {"order_id":12}     ← package came back
GET  api.php?action=available&offer_id=4
GET  api.php?action=reservations&order_id=12
```

When an `items` list is sent, it must match the order's `order_item` lines and quantities exactly.

## Not yet agreed with System 2

System 2 proposed a stricter service contract (`docs/INTEGRATION.md`, `docs/system3-integration/System_1_2_3_Inventory_Proposed.sql`). It goes beyond what our paper specifies, so it needs a group decision before it's built:

- **Signed requests:** HMAC-SHA256 `x-sales-signature` headers, idempotency keys, and the `GET requests/<reference>` / `POST requests/<action>` routes.
- **Extra hold statuses:** `confirmed` and `expired`, with an `expires_at` time on unpaid holds. Our paper has only `active`, `fulfilled` and `released`.
- **Callbacks:** lifecycle events posted back to Sales (`POST /api/events/inventory/lifecycle`).

The API has no authentication yet. Add it before putting this on a shared network.
