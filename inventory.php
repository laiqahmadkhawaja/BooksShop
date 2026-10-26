<?php
// inventory.php - Stock Management, Multi-Location Valuation & Movement Ledger
require_once __DIR__ . '/config/app.php';
requireAuth();

$location = $_GET['location'] ?? 'all'; // 'all', 'shop', or 'wh_X'
$filter = $_GET['filter'] ?? 'all';
$category = (int)($_GET['category'] ?? 0);
$search = trim($_GET['search'] ?? '');
$activeTab = $_GET['tab'] ?? 'stock'; // 'stock', 'movements', or 'valuation'

$warehouses = getWarehouses(false);

// Handle Stock Adjustment POST (Shop or Warehouse)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stock_adjustment'])) {
    $productId = (int)$_POST['product_id'];
    $adjType = $_POST['adj_type']; // 'add' or 'deduct'
    $qty = (float)$_POST['quantity'];
    $reason = trim($_POST['reason'] ?? 'Inventory Count Audit');
    $cost = (float)($_POST['cost_price'] ?? 0);
    $adjTarget = $_POST['adj_target'] ?? 'shop:0';

    $tParts = explode(':', $adjTarget);
    $tType = $tParts[0];
    $tWhId = (int)($tParts[1] ?? 0);

    if ($productId > 0 && $qty > 0) {
        $qtyChange = ($adjType === 'deduct') ? -$qty : $qty;
        if ($tType === 'warehouse' && $tWhId > 0) {
            $mType = ($adjType === 'deduct') ? 'adjustment_deduct' : 'adjustment_add';
            recordWarehouseStockMovement($tWhId, $productId, $mType, null, 'ADJUST-' . date('Ymd'), $qtyChange, $reason, $cost);
        } else {
            $mType = ($adjType === 'deduct') ? 'adjustment_out' : 'adjustment_in';
            recordStockMovement($productId, $mType, null, $qtyChange, $reason, $cost);
        }
        setFlash('success', "Stock adjustment successfully recorded.");
    }
    redirect("inventory.php?tab=stock&location=" . urlencode($location));
}

// 1. Valuation Calculations by Location
// A. Shop Totals
$shopSummary = dbFetchOne("SELECT 
    COUNT(*) as total_items,
    COALESCE(SUM(current_stock), 0) as total_units,
    COALESCE(SUM(current_stock * purchase_price), 0) as total_cost_value,
    COALESCE(SUM(current_stock * retail_price), 0) as total_retail_value
    FROM products WHERE status = 1");

// B. Warehouses Totals
$whSummary = dbFetchOne("SELECT 
    COUNT(DISTINCT ws.product_id) as total_items,
    COALESCE(SUM(ws.quantity), 0) as total_units,
    COALESCE(SUM(ws.quantity * p.purchase_price), 0) as total_cost_value,
    COALESCE(SUM(ws.quantity * p.retail_price), 0) as total_retail_value
    FROM warehouse_stock ws
    JOIN products p ON p.id = ws.product_id AND p.status = 1
    WHERE ws.quantity > 0");

// C. Specific Warehouse if selected
$specificWhId = 0;
$specificWhSummary = null;
if (strpos($location, 'wh_') === 0) {
    $specificWhId = (int)substr($location, 3);
    $specificWhSummary = dbFetchOne("SELECT 
        COUNT(DISTINCT ws.product_id) as total_items,
        COALESCE(SUM(ws.quantity), 0) as total_units,
        COALESCE(SUM(ws.quantity * p.purchase_price), 0) as total_cost_value,
        COALESCE(SUM(ws.quantity * p.retail_price), 0) as total_retail_value
        FROM warehouse_stock ws
        JOIN products p ON p.id = ws.product_id AND p.status = 1
        WHERE ws.warehouse_id = ? AND ws.quantity > 0", [$specificWhId]);
}

// Active KPI Scope
if ($location === 'shop') {
    $activeUnits = (float)($shopSummary['total_units'] ?? 0);
    $activeCostVal = (float)($shopSummary['total_cost_value'] ?? 0);
    $activeRetailVal = (float)($shopSummary['total_retail_value'] ?? 0);
} elseif ($specificWhId > 0 && $specificWhSummary) {
    $activeUnits = (float)($specificWhSummary['total_units'] ?? 0);
    $activeCostVal = (float)($specificWhSummary['total_cost_value'] ?? 0);
    $activeRetailVal = (float)($specificWhSummary['total_retail_value'] ?? 0);
} else {
    // Consolidated (Shop + All Warehouses)
    $activeUnits = (float)($shopSummary['total_units'] ?? 0) + (float)($whSummary['total_units'] ?? 0);
    $activeCostVal = (float)($shopSummary['total_cost_value'] ?? 0) + (float)($whSummary['total_cost_value'] ?? 0);
    $activeRetailVal = (float)($shopSummary['total_retail_value'] ?? 0) + (float)($whSummary['total_retail_value'] ?? 0);
}
$activeProfit = $activeRetailVal - $activeCostVal;

// Low Stock count for Shop
$lowStockCount = dbFetchOne("SELECT COUNT(*) as cnt FROM products WHERE current_stock <= min_stock_alert AND status = 1")['cnt'] ?? 0;
$outStockCount = dbFetchOne("SELECT COUNT(*) as cnt FROM products WHERE current_stock <= 0 AND status = 1")['cnt'] ?? 0;

// 2. Query Products with Location Stock Subqueries
$where = ["p.status = 1"];
$params = [];

if ($filter === 'low_stock') {
    $where[] = "p.current_stock <= p.min_stock_alert AND p.current_stock > 0";
} elseif ($filter === 'out_of_stock') {
    $where[] = "p.current_stock <= 0";
} elseif ($filter === 'books' || $filter === 'book') {
    $where[] = "p.product_type = 'book'";
} elseif ($filter === 'stationery') {
    $where[] = "p.product_type = 'stationery'";
} elseif (!empty($filter) && $filter !== 'all') {
    $where[] = "p.product_type = ?";
    $params[] = $filter;
}

if ($category > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category;
}

if ($search) {
    $where[] = "(p.title LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ? OR p.isbn LIKE ?)";
    $w = "%$search%";
    $params = array_merge($params, [$w, $w, $w, $w]);
}

$products = dbFetchAll("SELECT p.*, c.name as category_name,
                        COALESCE((SELECT SUM(quantity) FROM warehouse_stock WHERE product_id = p.id), 0) as total_wh_stock
                        FROM products p 
                        LEFT JOIN categories c ON c.id = p.category_id 
                        WHERE " . implode(" AND ", $where) . " 
                        ORDER BY p.current_stock ASC, p.title ASC", $params);

// 3. Query Movements (Shop movements + Warehouse movements)
$movements = [];
if ($activeTab === 'movements') {
    $movements = dbFetchAll("SELECT sm.*, p.title as product_title, p.product_code, p.unit, u.full_name as user_name
                             FROM stock_movements sm
                             JOIN products p ON p.id = sm.product_id
                             LEFT JOIN users u ON u.id = sm.created_by
                             ORDER BY sm.id DESC LIMIT 100");
}

$categories = dbFetchAll("SELECT * FROM categories ORDER BY name ASC");
$productTypes = getProductTypes();
$allProducts = dbFetchAll("SELECT id, title, product_code, current_stock, purchase_price FROM products WHERE status = 1 ORDER BY title ASC");

$pageTitle = 'Inventory & Multi-Location Stock';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-boxes-stacked text-primary me-2"></i> Inventory & Stock Valuation
        </h4>
        <small class="text-muted">Real-time stock valuation across shop counters and storage godowns</small>
    </div>
    <div class="d-flex gap-2">
        <a href="warehouse_transfer_form.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-truck-ramp-box me-1"></i> Stock Transfer
        </a>
        <a href="stock_adjust.php" class="btn btn-warning text-dark btn-sm fw-bold shadow-sm">
            <i class="fa-solid fa-sliders me-1"></i> Stock Adjustment
        </a>
        <a href="product_form.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus-circle me-1"></i> Add Product
        </a>
    </div>
</div>

<!-- Location Valuation Scope Bar -->
<div class="card border-0 shadow-sm mb-4 bg-light">
    <div class="card-body p-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="small fw-bold text-dark text-uppercase ms-2"><i class="fa-solid fa-location-dot text-danger me-1"></i> Valuation Scope:</span>
            <div class="btn-group btn-group-sm" role="group">
                <a href="inventory.php?tab=<?= $activeTab ?>&location=all&filter=<?= $filter ?>&category=<?= $category ?>"
                   class="btn <?= ($location === 'all') ? 'btn-primary fw-bold' : 'btn-outline-secondary bg-white' ?>">
                    🌐 All Locations (Consolidated)
                </a>
                <a href="inventory.php?tab=<?= $activeTab ?>&location=shop&filter=<?= $filter ?>&category=<?= $category ?>"
                   class="btn <?= ($location === 'shop') ? 'btn-primary fw-bold' : 'btn-outline-secondary bg-white' ?>">
                    🏬 Main Shop / Storefront
                </a>
                <?php foreach ($warehouses as $wh): ?>
                    <a href="inventory.php?tab=<?= $activeTab ?>&location=wh_<?= $wh['id'] ?>&filter=<?= $filter ?>&category=<?= $category ?>"
                       class="btn <?= ($location === 'wh_' . $wh['id']) ? 'btn-primary fw-bold' : 'btn-outline-secondary bg-white' ?>">
                        🏭 <?= htmlspecialchars($wh['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3 pe-2">
            <span class="small text-muted">Shop Asset: <strong class="text-dark"><?= formatCurrency((float)($shopSummary['total_cost_value'] ?? 0)) ?></strong></span>
            <span class="small text-muted">&bull;</span>
            <span class="small text-muted">Godown Asset: <strong class="text-dark"><?= formatCurrency((float)($whSummary['total_cost_value'] ?? 0)) ?></strong></span>
            <span class="small text-muted">&bull;</span>
            <span class="small fw-bold text-success">Total Company Asset: <?= formatCurrency((float)($shopSummary['total_cost_value'] ?? 0) + (float)($whSummary['total_cost_value'] ?? 0)) ?></span>
        </div>
    </div>
</div>

<!-- Valuation KPI Metrics Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-primary border-4 shadow-sm bg-white h-100">
            <div class="small text-muted fw-bold">TOTAL STOCK VALUATION (COST)</div>
            <h4 class="fw-bold text-primary mb-0 mt-1"><?= formatCurrency($activeCostVal) ?></h4>
            <span class="small text-muted">Capital asset in <?= ($location === 'shop') ? 'Shop Counter' : (($specificWhId > 0) ? 'this godown' : 'entire business') ?></span>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-success border-4 shadow-sm bg-white h-100">
            <div class="small text-muted fw-bold">RETAIL SALES VALUATION</div>
            <h4 class="fw-bold text-success mb-0 mt-1"><?= formatCurrency($activeRetailVal) ?></h4>
            <span class="small text-muted">Potential shelf sale value</span>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-info border-4 shadow-sm bg-white h-100">
            <div class="small text-muted fw-bold">POTENTIAL GROSS PROFIT</div>
            <h4 class="fw-bold text-info mb-0 mt-1"><?= formatCurrency($activeProfit) ?></h4>
            <span class="small text-muted"><?= $activeCostVal > 0 ? number_format(($activeProfit / $activeCostVal) * 100, 1) . '% profit margin' : '0%' ?></span>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-warning border-4 shadow-sm bg-white h-100">
            <div class="small text-muted fw-bold">TOTAL PHYSICAL UNITS</div>
            <h4 class="fw-bold text-dark mb-0 mt-1"><?= number_format($activeUnits, 0) ?> Units</h4>
            <span class="small text-muted"><?= ($location === 'all') ? "Shop: " . number_format((float)$shopSummary['total_units']) . " | Godowns: " . number_format((float)$whSummary['total_units']) : "Active Scope Inventory" ?></span>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white p-0">
        <ul class="nav nav-tabs card-header-tabs m-0 flex-nowrap overflow-auto">
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $activeTab === 'stock' ? 'active fw-bold text-primary' : '' ?>" href="inventory.php?tab=stock&location=<?= urlencode($location) ?>">
                    <i class="fa-solid fa-list me-1"></i> Current Stock Balances & Locations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $activeTab === 'movements' ? 'active fw-bold text-info' : '' ?>" href="inventory.php?tab=movements&location=<?= urlencode($location) ?>">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Stock Movement Audit Log
                </a>
            </li>
        </ul>
    </div>

    <?php if ($activeTab === 'stock'): ?>
        <!-- Filter Bar -->
        <div class="card-body border-bottom bg-light">
            <form method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="stock">
                <input type="hidden" name="location" value="<?= htmlspecialchars($location) ?>">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search title, ISBN, barcode..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="filter" class="form-select form-select-sm">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Inventory</option>
                        <option value="low_stock" <?= $filter === 'low_stock' ? 'selected' : '' ?>>Low Stock Alert Only</option>
                        <option value="out_of_stock" <?= $filter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock (Zero)</option>
                        <?php foreach ($productTypes as $pt): ?>
                            <option value="<?= htmlspecialchars($pt['code']) ?>" <?= ($filter === $pt['code'] || ($filter === 'books' && $pt['code'] === 'book')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pt['name']) ?> Only
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select form-select-sm">
                        <option value="0">-- All Categories --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                </div>
            </form>
        </div>

        <!-- Stock Table -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light text-uppercase">
                        <tr>
                            <th>Item / Book Title</th>
                            <th>Category</th>
                            <th class="text-end">Cost Price</th>
                            <th class="text-end">Retail Price</th>
                            <th class="text-center">Shop Stock</th>
                            <th class="text-center">Godown Stock</th>
                            <th class="text-center">Total Stock</th>
                            <th class="text-end">Total Valuation (Cost)</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">No products found matching criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): 
                                $shopQty = (float)$p['current_stock'];
                                $whQty = (float)$p['total_wh_stock'];
                                $totalQty = $shopQty + $whQty;
                                $costVal = $totalQty * (float)$p['purchase_price'];
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($p['title']) ?></div>
                                        <div class="text-muted font-monospace" style="font-size: 0.75rem;">
                                            Code: <?= htmlspecialchars($p['product_code']) ?>
                                            <?php if ($p['barcode']): ?>
                                                &bull; <i class="fa-solid fa-barcode ms-1"></i> <?= htmlspecialchars($p['barcode']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['category_name'] ?? 'General') ?></span>
                                    </td>
                                    <td class="text-end font-monospace"><?= formatCurrency($p['purchase_price']) ?></td>
                                    <td class="text-end font-monospace fw-semibold text-primary"><?= formatCurrency($p['retail_price']) ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $shopQty <= 0 ? 'bg-danger-subtle text-danger' : ($shopQty <= $p['min_stock_alert'] ? 'bg-warning-subtle text-warning-emphasis' : 'bg-light text-dark border') ?> fs-6">
                                            <?= number_format($shopQty, 0) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($whQty > 0): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle fs-6">
                                                <i class="fa-solid fa-warehouse me-1"></i> <?= number_format($whQty, 0) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <strong class="fs-6 text-dark"><?= number_format($totalQty, 0) ?></strong>
                                        <small class="text-muted d-block"><?= htmlspecialchars($p['unit'] ?: 'Piece') ?></small>
                                    </td>
                                    <td class="text-end font-monospace fw-bold text-success">
                                        <?= formatCurrency($costVal) ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="warehouse_transfer_form.php" class="btn btn-outline-secondary" title="Transfer Stock">
                                                <i class="fa-solid fa-truck-ramp-box"></i>
                                            </a>
                                            <a href="stock_adjust.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary" title="Adjust Stock">
                                                <i class="fa-solid fa-sliders"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <!-- Movements Audit Tab -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light text-uppercase">
                        <tr>
                            <th>Date & Time</th>
                            <th>Item Title</th>
                            <th>Movement Type</th>
                            <th>Ref #</th>
                            <th class="text-center">Qty Change</th>
                            <th class="text-center">Prev Stock</th>
                            <th class="text-center">New Stock</th>
                            <th>Description</th>
                            <th>Staff</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $m): 
                            $isPos = (float)$m['quantity_change'] > 0;
                        ?>
                            <tr>
                                <td><?= date('d M, Y - h:i A', strtotime($m['movement_date'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($m['product_title']) ?></strong>
                                    <small class="text-muted d-block"><?= htmlspecialchars($m['product_code']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($m['movement_type']) ?></span>
                                </td>
                                <td class="font-monospace text-muted"><?= htmlspecialchars($m['reference_id'] ?: '—') ?></td>
                                <td class="text-center fw-bold <?= $isPos ? 'text-success' : 'text-danger' ?>">
                                    <?= ($isPos ? '+' : '') . (float)$m['quantity_change'] ?>
                                </td>
                                <td class="text-center text-muted"><?= (float)$m['previous_stock'] ?></td>
                                <td class="text-center fw-bold"><?= (float)$m['new_stock'] ?></td>
                                <td class="text-muted"><?= htmlspecialchars($m['notes'] ?: '—') ?></td>
                                <td class="text-muted"><?= htmlspecialchars($m['user_name'] ?: 'Staff') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
