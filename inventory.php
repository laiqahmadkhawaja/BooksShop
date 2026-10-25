<?php
// inventory.php - Stock Management, Valuation & Movement Ledger
$pageTitle = 'Inventory & Stock';
require_once __DIR__ . '/includes/header.php';

$filter = $_GET['filter'] ?? 'all';
$category = (int)($_GET['category'] ?? 0);
$search = trim($_GET['search'] ?? '');
$activeTab = $_GET['tab'] ?? 'stock'; // 'stock' or 'movements'

// Handle Stock Adjustment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stock_adjustment'])) {
    $productId = (int)$_POST['product_id'];
    $adjType = $_POST['adj_type']; // 'add' or 'deduct'
    $qty = (float)$_POST['quantity'];
    $reason = trim($_POST['reason'] ?? 'Inventory Count Audit');
    $cost = (float)($_POST['cost_price'] ?? 0);

    if ($productId > 0 && $qty > 0) {
        $qtyChange = ($adjType === 'deduct') ? -$qty : $qty;
        $mType = ($adjType === 'deduct') ? 'adjustment_out' : 'adjustment_in';
        recordStockMovement($productId, $mType, null, $qtyChange, $reason, $cost);
        setFlash('success', "Stock adjustment successfully recorded.");
    }
    header("Location: inventory.php?tab=stock");
    exit;
}

// Valuation Summaries
$summary = dbFetchOne("SELECT 
    COUNT(*) as total_items,
    SUM(current_stock) as total_units,
    SUM(current_stock * purchase_price) as total_cost_value,
    SUM(current_stock * retail_price) as total_retail_value
    FROM products WHERE status = 1");

$totalUnits = (float)($summary['total_units'] ?? 0);
$totalCostVal = (float)($summary['total_cost_value'] ?? 0);
$totalRetailVal = (float)($summary['total_retail_value'] ?? 0);
$potentialProfit = $totalRetailVal - $totalCostVal;

// Low Stock count
$lowStockCount = dbFetchOne("SELECT COUNT(*) as cnt FROM products WHERE current_stock <= min_stock_alert AND status = 1")['cnt'] ?? 0;
$outStockCount = dbFetchOne("SELECT COUNT(*) as cnt FROM products WHERE current_stock <= 0 AND status = 1")['cnt'] ?? 0;

// Query Products
$where = ["p.status = 1"];
$params = [];

if ($filter === 'low_stock') {
    $where[] = "p.current_stock <= p.min_stock_alert AND p.current_stock > 0";
} elseif ($filter === 'out_of_stock') {
    $where[] = "p.current_stock <= 0";
} elseif ($filter === 'books') {
    $where[] = "p.product_type = 'book'";
} elseif ($filter === 'stationery') {
    $where[] = "p.product_type = 'stationery'";
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

$products = dbFetchAll("SELECT p.*, c.name as category_name 
                        FROM products p 
                        LEFT JOIN categories c ON c.id = p.category_id 
                        WHERE " . implode(" AND ", $where) . " 
                        ORDER BY p.current_stock ASC, p.title ASC", $params);

// Query Movements if on movements tab
$movements = [];
if ($activeTab === 'movements') {
    $movements = dbFetchAll("SELECT sm.*, p.title as product_title, p.product_code, p.unit, u.full_name as user_name
                             FROM stock_movements sm
                             JOIN products p ON p.id = sm.product_id
                             LEFT JOIN users u ON u.id = sm.created_by
                             ORDER BY sm.id DESC LIMIT 100");
}

$categories = dbFetchAll("SELECT * FROM categories ORDER BY name ASC");
$allProducts = dbFetchAll("SELECT id, title, product_code, current_stock, purchase_price FROM products WHERE status = 1 ORDER BY title ASC");
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i> Inventory & Stock Management</h4>
        <small class="text-muted">Real-time stock valuation, inventory adjustments, and stock movement audit trail</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-warning text-dark btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#stockAdjustmentModal">
            <i class="fa-solid fa-sliders me-1"></i> Stock Adjustment Voucher
        </button>
        <a href="books.php?action=new" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus-circle me-1"></i> Add New Product
        </a>
    </div>
</div>

<!-- Valuation KPI Metrics Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-primary border-4 shadow-sm bg-white">
            <div class="small text-muted fw-bold">TOTAL STOCK VALUATION (COST)</div>
            <h4 class="fw-bold text-primary mb-0 mt-1"><?= formatCurrency($totalCostVal) ?></h4>
            <span class="small text-muted">Purchase asset investment</span>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-success border-4 shadow-sm bg-white">
            <div class="small text-muted fw-bold">RETAIL SALES VALUATION</div>
            <h4 class="fw-bold text-success mb-0 mt-1"><?= formatCurrency($totalRetailVal) ?></h4>
            <span class="small text-muted">Total potential gross revenue</span>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-info border-4 shadow-sm bg-white">
            <div class="small text-muted fw-bold">POTENTIAL GROSS PROFIT</div>
            <h4 class="fw-bold text-info mb-0 mt-1"><?= formatCurrency($potentialProfit) ?></h4>
            <span class="small text-muted"><?= $totalCostVal > 0 ? number_format(($potentialProfit / $totalCostVal) * 100, 1) . '% profit margin' : '0%' ?></span>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-danger border-4 shadow-sm bg-white">
            <div class="small text-muted fw-bold">TOTAL UNITS IN WAREHOUSE</div>
            <h4 class="fw-bold text-danger mb-0 mt-1"><?= number_format($totalUnits) ?> Units</h4>
            <span class="small text-muted">Alerts: <?= $lowStockCount ?> Low, <?= $outStockCount ?> Out</span>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white p-0">
        <ul class="nav nav-tabs card-header-tabs m-0 flex-nowrap overflow-auto">
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $activeTab === 'stock' ? 'active fw-bold text-primary' : '' ?>" href="inventory.php?tab=stock">
                    <i class="fa-solid fa-list me-1"></i> Current Stock Balances
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $activeTab === 'movements' ? 'active fw-bold text-info' : '' ?>" href="inventory.php?tab=movements">
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
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search title, ISBN, barcode..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="filter" class="form-select form-select-sm">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Inventory</option>
                        <option value="low_stock" <?= $filter === 'low_stock' ? 'selected' : '' ?>>Low Stock Alert Only</option>
                        <option value="out_of_stock" <?= $filter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock (Zero)</option>
                        <option value="books" <?= $filter === 'books' ? 'selected' : '' ?>>Books Only</option>
                        <option value="stationery" <?= $filter === 'stationery' ? 'selected' : '' ?>>Stationery Only</option>
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
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Item / Book Title</th>
                            <th>Category & Location</th>
                            <th>Cost Price</th>
                            <th>Retail Price</th>
                            <th>Current Stock</th>
                            <th>Cost Value</th>
                            <th>Retail Value</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">No products found matching criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <?php 
                                    $costVal = $p['current_stock'] * $p['purchase_price'];
                                    $retailVal = $p['current_stock'] * $p['retail_price'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($p['title']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($p['product_code']) ?> &bull; Barcode: <?= htmlspecialchars($p['barcode'] ?: '-') ?></div>
                                    </td>
                                    <td>
                                        <div class="small"><?= htmlspecialchars($p['category_name'] ?? 'General') ?></div>
                                        <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">
                                            <?= htmlspecialchars($p['rack_location'] ?: 'Rack') ?>
                                        </span>
                                    </td>
                                    <td><?= formatCurrency($p['purchase_price']) ?></td>
                                    <td class="fw-semibold text-primary"><?= formatCurrency($p['retail_price']) ?></td>
                                    <td>
                                        <span class="fw-bold fs-6 <?= $p['current_stock'] <= 0 ? 'text-danger' : ($p['current_stock'] <= $p['min_stock_alert'] ? 'text-warning' : 'text-dark') ?>">
                                            <?= (float)$p['current_stock'] ?>
                                        </span>
                                        <small class="text-muted"><?= htmlspecialchars($p['unit']) ?></small>
                                    </td>
                                    <td class="small fw-semibold"><?= formatCurrency($costVal) ?></td>
                                    <td class="small fw-semibold text-success"><?= formatCurrency($retailVal) ?></td>
                                    <td>
                                        <?php if ($p['current_stock'] <= 0): ?>
                                            <span class="badge bg-danger">OUT OF STOCK</span>
                                        <?php elseif ($p['current_stock'] <= $p['min_stock_alert']): ?>
                                            <span class="badge bg-warning text-dark">LOW STOCK</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">HEALTHY</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="purchases.php?action=new&product_id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm py-0 px-2" title="Order Stock">
                                            <i class="fa-solid fa-cart-plus"></i> Reorder
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- Movements Audit Log Tab -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light text-uppercase">
                        <tr>
                            <th>Date & Time</th>
                            <th>Item Description</th>
                            <th>Movement Type</th>
                            <th>Previous</th>
                            <th>Change</th>
                            <th>New Stock</th>
                            <th>Unit Cost</th>
                            <th>Audit Notes</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movements)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">No stock movement logs recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($movements as $m): ?>
                                <tr>
                                    <td><?= date('d M, Y h:i A', strtotime($m['movement_date'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($m['product_title']) ?></strong>
                                        <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($m['product_code']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.7rem;">
                                            <?= htmlspecialchars($m['movement_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= (float)$m['previous_stock'] ?></td>
                                    <td>
                                        <strong class="<?= $m['quantity_change'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= $m['quantity_change'] > 0 ? '+' : '' ?><?= (float)$m['quantity_change'] ?>
                                        </strong>
                                    </td>
                                    <td class="fw-bold"><?= (float)$m['new_stock'] ?></td>
                                    <td><?= formatCurrency($m['unit_cost']) ?></td>
                                    <td><?= htmlspecialchars($m['notes']) ?></td>
                                    <td><?= htmlspecialchars($m['user_name'] ?: 'System') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Stock Adjustment Voucher -->
<div class="modal fade" id="stockAdjustmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="inventory.php">
                <input type="hidden" name="save_stock_adjustment" value="1">
                
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-sliders me-2"></i> Stock Adjustment Voucher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Product *</label>
                        <select name="product_id" id="modalAdjProdSelect" class="form-select" required onchange="onAdjProdChange(this)">
                            <option value="">-- Choose Book or Stationery Item --</option>
                            <?php foreach ($allProducts as $p): ?>
                                <option value="<?= $p['id'] ?>" data-stock="<?= (float)$p['current_stock'] ?>" data-cost="<?= (float)$p['purchase_price'] ?>">
                                    <?= htmlspecialchars($p['title']) ?> (Current Stock: <?= (float)$p['current_stock'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Adjustment Type *</label>
                            <select name="adj_type" class="form-select" required>
                                <option value="add">Add Stock (Found / Audit Surplus)</option>
                                <option value="deduct">Deduct Stock (Damage / Lost / Expired)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Quantity *</label>
                            <input type="number" step="0.01" name="quantity" class="form-control" required min="0.01" value="1">
                        </div>
                    </div>

                    <input type="hidden" name="cost_price" id="modalAdjCost" value="0.00">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Reason / Audit Trail Note *</label>
                        <input type="text" name="reason" class="form-control" required placeholder="e.g. Physical stock count discrepancy, water leakage damage">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold"><i class="fa-solid fa-check me-1"></i> Apply Stock Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function onAdjProdChange(select) {
    const opt = select.options[select.selectedIndex];
    const cost = opt.getAttribute('data-cost') || 0;
    document.getElementById('modalAdjCost').value = cost;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
