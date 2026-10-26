# Project Roadmap

## Active Milestone: Warehouse & Multi-Location Stock Management

### Phase 1: Warehouse & Multi-Location Inventory Integration
**Goal**: Integrate full multi-warehouse management into the system, allowing users to define multiple warehouses/godowns, transfer stock between Shop and Warehouses (with printable transfer slips), select receiving location (Shop vs. Warehouse) during supplier purchases, perform warehouse-specific stock adjustments, and generate comprehensive stock valuation for both Shop and all Warehouses.

- [ ] **Task 1: Schema & Data Migration** (`ensureWarehouseSchema()`, `warehouses`, `warehouse_stock`, `warehouse_transfers`, `warehouse_transfer_items`, `warehouse_stock_movements`, and `purchases` table extension)
- [ ] **Task 2: Warehouse CRUD & Profile Management** (`warehouses.php`, `warehouse_form.php`)
- [ ] **Task 3: Purchase Receiving Location Integration** (`purchase_form.php`, `purchases.php`, `print_purchase.php`)
- [ ] **Task 4: Inter-Location Stock Transfer Module** (`warehouse_transfers.php`, `warehouse_transfer_form.php`, `print_transfer.php`)
- [ ] **Task 5: Warehouse Inventory Audit & Stock Adjustment** (`warehouse_view.php`, `stock_adjust.php`)
- [ ] **Task 6: Comprehensive Stock Valuation & Reports** (Shop vs Warehouse valuation cards in `inventory.php`, `reports.php`, `print_report.php`)
- [ ] **Task 7: POS Stock Visibility & Navigation Updates** (`includes/sidebar.php`, `config/app.php` translations, `pos.php` stock indicators)
