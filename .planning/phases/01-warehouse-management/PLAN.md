# Phase 01: Warehouse & Multi-Location Inventory Integration

## Objective
Enable bookshop owners and managers to manage inventory across multiple physical locations:
1. Main Shop / Retail Counter (Storefront)
2. Multiple Godowns / Warehouses (Central Warehouse, Secondary Godown, Basement Storage, etc.)

Users will be able to:
- Add, update, and manage multiple warehouses.
- Receive purchases directly into either the Main Shop or any designated Warehouse.
- Transfer stock seamlessly between Shop and Warehouses (Shop -> Warehouse, Warehouse -> Shop, Warehouse -> Warehouse) with full transfer audit slips.
- View and audit real-time stock and ledger movements for each individual warehouse.
- Calculate and report accurate stock valuation (Units, Cost Value, Retail Value, Potential Margin) broken down by Shop, individual Warehouses, and Combined Total.

---

## Architectural Principles & Non-Breaking Design
- **Non-breaking shop stock**: `products.current_stock` remains the active Shop / Counter inventory. All POS queries, quick barcode lookups, and sales checkout remain lightning fast and 100% backward-compatible.
- **Dedicated Warehouse Stock Table**: `warehouse_stock` tracks `(warehouse_id, product_id, quantity, rack_shelf)`.
- **Atomic Database Transactions**: Transfers and purchases wrap inventory modifications across tables in PDO transactions (`beginTransaction`, `commit`, `rollBack`).
- **Audit Trails**: Every movement is recorded in `stock_movements` (for shop changes) and `warehouse_stock_movements` (for warehouse changes).
- **Ponytail Simplicity**: Procedural PHP, direct PDO prepared statements, vanilla JS, Bootstrap 5 UI, no unnecessary bloated npm packages.

---

## Tasks Breakdown

### Task 1: Database Schema & Core Helper Functions
- **Files**: `config/app.php`, `install.php`
- Add `ensureWarehouseSchema()` in `config/app.php` running automatically.
- Create tables:
  - `warehouses` (id, warehouse_code, name, phone, email, address, city, manager_name, is_primary, status, created_at)
  - `warehouse_stock` (id, warehouse_id, product_id, quantity, rack_shelf, min_stock_alert, updated_at)
  - `warehouse_transfers` (id, transfer_no, transfer_date, from_location_type, from_warehouse_id, to_location_type, to_warehouse_id, total_items, total_qty, status, notes, created_by, created_at)
  - `warehouse_transfer_items` (id, transfer_id, product_id, quantity, unit_cost, total_cost)
  - `warehouse_stock_movements` (id, warehouse_id, product_id, movement_date, movement_type, reference_id, reference_no, quantity_change, previous_stock, new_stock, unit_cost, notes, created_by, created_at)
- Alter `purchases` table to add `destination_type` ('shop', 'warehouse') and `destination_warehouse_id`.
- Add helper functions:
  - `getWarehouses($activeOnly = true)`
  - `recordWarehouseStockMovement($warehouseId, $productId, $movementType, $refId, $refNo, $qtyChange, $notes, $unitCost)`
  - `getWarehouseStock($warehouseId, $productId)`

### Task 2: Warehouse CRUD & Management Interface
- **Files**: `warehouses.php`, `warehouse_form.php`
- `warehouses.php`:
  - List all warehouses with badges for Active/Inactive, Primary warehouse.
  - KPI cards: Total Warehouses, Total Stored Units across Warehouses, Total Warehouse Inventory Valuation (Cost & Retail).
  - Quick action buttons: "View Stock", "Transfer Stock", "Edit Details", "Toggle Status".
- `warehouse_form.php`:
  - Create new warehouse or edit existing.
  - Fields: Warehouse Name, Unique Code, Manager Contact, Phone, Full Address, City, Set as Primary, Active toggle.

### Task 3: Inward Purchase Receiving Destination
- **Files**: `purchase_form.php`, `purchases.php`, `print_purchase.php`, `purchase_view.php`
- In `purchase_form.php`:
  - Add "Receive Goods Into / Destination" selector dropdown:
    - `[Shop] Main Retail Counter / Storefront` (Default)
    - `[Warehouse] <Warehouse Name> (<Warehouse Code>)`
- In `purchases.php`:
  - If destination is `shop`, credit `products.current_stock` and log in `stock_movements` (existing behavior).
  - If destination is `warehouse`, credit `warehouse_stock` for that `warehouse_id` and log in `warehouse_stock_movements` with type `'purchase_in'`.
  - Always update `products.purchase_price` to the latest purchase cost.
- Update `print_purchase.php` and `purchase_view.php` to display destination location.

### Task 4: Inter-Location Stock Transfer Module
- **Files**: `warehouse_transfers.php`, `warehouse_transfer_form.php`, `print_transfer.php`
- `warehouse_transfer_form.php`:
  - Select Source Location (`Shop` or a specific `Warehouse`).
  - Select Destination Location (cannot be the same as source).
  - Search & add products with live stock availability display for the chosen Source location.
  - Quantity validation (prevents transferring more stock than available at source).
  - Input notes, reference/vehicle/bilty number, transfer date.
- Processing in `warehouse_transfers.php`:
  - Deducts from Source location.
  - Adds to Destination location.
  - Logs corresponding audit records.
  - Generates unique Transfer Number (`TRF-2026-0001`).
- `print_transfer.php`:
  - Clean printable Delivery Challan / Gate Pass with sender & receiver signatures, transfer date, item list, quantities, and transfer notes.

### Task 5: Warehouse Stock Audit & Adjustments
- **Files**: `warehouse_view.php`, `stock_adjust.php`
- `warehouse_view.php`:
  - Browse inventory in a specific warehouse with search, category filter, and low stock highlights.
  - Stock movement audit tab for this specific warehouse.
  - "Adjust Stock" button per item to record physical count corrections, damage, or missing items.
- `stock_adjust.php`:
  - Add location dropdown: `Shop` or specific `Warehouse`.
  - Adjusts target location stock and logs appropriate audit ledger.

### Task 6: Stock Valuation for Shop & Warehouses
- **Files**: `inventory.php`, `reports.php`, `print_report.php`
- In `inventory.php`:
  - Add Location Filter tab/dropdown: `All Locations (Consolidated)`, `Main Shop`, `Warehouse: X`.
  - Display updated Valuation Metrics for selected scope:
    - Total Units
    - Total Cost Value (Rs.)
    - Total Retail Value (Rs.)
    - Potential Gross Profit (Rs. and Margin %)
  - Table showing per-location quantity and valuation.
- In `reports.php`:
  - Add `report=stock_valuation` with downloadable/printable view for accounting audits.

### Task 7: Navigation, POS Visibility & UI Polish
- **Files**: `includes/sidebar.php`, `config/app.php`, `pos.php`
- Update `includes/sidebar.php`:
  - Add "Warehouses" and "Stock Transfers" links under "Inventory & Purchase".
- Add Urdu & English translations in `config/app.php` (`$TRANSLATIONS`).
- In `pos.php`:
  - Display a subtle badge/tooltip showing additional stock in godowns (e.g., `Shop: 3 | Godown: 20`) so cashiers know more stock is available in storage if shop shelf is running low.

---

## Verification Criteria
1. Warehouse CRUD works (add warehouse, edit, deactivate).
2. Purchase receiving into warehouse increases `warehouse_stock` and leaves `products.current_stock` unchanged.
3. Purchase receiving into shop increases `products.current_stock` (standard flow).
4. Stock transfer from Shop to Warehouse decreases `products.current_stock` and increases `warehouse_stock`.
5. Stock transfer from Warehouse to Shop decreases `warehouse_stock` and increases `products.current_stock`.
6. Transfer slip prints correctly with all items, quantities, and signatures.
7. Valuation correctly calculates for Shop alone, Warehouses alone, and Combined.
8. No broken links or database errors.
