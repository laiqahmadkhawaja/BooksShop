<?php
// warehouse_view.php - Dedicated Warehouse Stock & Audit Ledger Viewer
require_once __DIR__ . '/config/app.php';
requireAuth();

$warehouseId = (int)($_GET['id'] ?? 0);
if ($warehouseId <= 0) {
    setFlash('danger', 'Invalid warehouse ID.');
    redirect('warehouses.php');
}

$warehouse = dbFetchOne("SELECT * FROM warehouses WHERE id = ?", [$warehouseId]);
if (!$warehouse) {
    setFlash('danger', 'Warehouse not found.');
    redirect('warehouses.php');
}

$activeTab = $_GET['tab'] ?? 'stock'; // 'stock' or 'movements'
$search = trim($_GET['search'] ?? '');
$filterCategory = (int)($_GET['category'] ?? 0);



// 1. Warehouse Stock Query
$stockWhere = ["p.status = 1"];
$stockParams = [$warehouseId];

if ($search) {
    $stockWhere[] = "(p.title LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ? OR p.isbn LIKE ?)";
    $w = "%$search%";
    $stockParams[] = $w; $stockParams[] = $w; $stockParams[] = $w; $stockParams[] = $w;
}
if ($filterCategory > 0) {
    $stockWhere[] = "p.category_id = ?";
    $stockParams[] = $filterCategory;
}

$warehouseItems = dbFetchAll("SELECT p.id as product_id, p.title, p.product_code, p.barcode, p.unit, p.product_type,
                                     p.purchase_price, p.retail_price, c.name as category_name,
                                     COALESCE(ws.quantity, 0) as wh_quantity, ws.rack_shelf
                              FROM products p
                              LEFT JOIN warehouse_stock ws ON ws.product_id = p.id AND ws.warehouse_id = ?
                              LEFT JOIN categories c ON c.id = p.category_id
                              WHERE " . implode(" AND ", $stockWhere) . "
                              HAVING wh_quantity > 0
                              ORDER BY wh_quantity DESC, p.title ASC", $stockParams);

// 2. Warehouse Stock Movements Query
$movements = [];
if ($activeTab === 'movements') {
    $movements = dbFetchAll("SELECT wsm.*, p.title as product_title, p.product_code, p.unit, u.full_name as user_name
                             FROM warehouse_stock_movements wsm
                             JOIN products p ON p.id = wsm.product_id
                             LEFT JOIN users u ON u.id = wsm.created_by
                             WHERE wsm.warehouse_id = ?
                             ORDER BY wsm.id DESC LIMIT 150", [$warehouseId]);
}

// 3. Summaries for this warehouse
$summary = dbFetchOne("SELECT 
    COUNT(DISTINCT ws.product_id) as total_skus,
    COALESCE(SUM(ws.quantity), 0) as total_units,
    COALESCE(SUM(ws.quantity * p.purchase_price), 0) as total_cost_value,
    COALESCE(SUM(ws.quantity * p.retail_price), 0) as total_retail_value
    FROM warehouse_stock ws
    JOIN products p ON p.id = ws.product_id AND p.status = 1
    WHERE ws.warehouse_id = ? AND ws.quantity > 0", [$warehouseId]);

$totalSkus = (int)($summary['total_skus'] ?? 0);
$totalUnits = (float)($summary['total_units'] ?? 0);
$totalCostVal = (float)($summary['total_cost_value'] ?? 0);
$totalRetailVal = (float)($summary['total_retail_value'] ?? 0);
$potentialProfit = $totalRetailVal - $totalCostVal;

$categories = dbFetchAll("SELECT * FROM categories ORDER BY name ASC");
$allProducts = dbFetchAll("SELECT id, title, product_code, purchase_price, current_stock FROM products WHERE status = 1 ORDER BY title ASC");

$pageTitle = htmlspecialchars($warehouse['name']) . ' - Inventory & Audit';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <a href="warehouses.php" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Warehouses
        </a>
        <h4 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-warehouse text-warning me-2"></i> <?= htmlspecialchars($warehouse['name']) ?>
            <span class="badge bg-light text-muted border font-monospace fs-6 ms-2"><?= htmlspecialchars($warehouse['warehouse_code']) ?></span>
            <?php if ($warehouse['is_primary']): ?>
                <span class="badge bg-warning text-dark fs-6 ms-1">Primary Godown</span>
            <?php endif; ?>
        </h4>
        <small class="text-muted">
            <?= htmlspecialchars($warehouse['address'] ?: 'No address specified') ?>
            <?= $warehouse['city'] ? ' &bull; ' . htmlspecialchars($warehouse['city']) : '' ?>
            <?= $warehouse['manager_name'] ? ' &bull; In-Charge: ' . htmlspecialchars($warehouse['manager_name']) : '' ?>
            <?= $warehouse['phone'] ? ' (' . htmlspecialchars($warehouse['phone']) . ')' : '' ?>
        </small>
    </div>
    <div class="d-flex gap-2">
        <a href="warehouse_transfer_form.php?from_wh=<?= $warehouse['id'] ?>" class="btn btn-outline-primary">
            <i class="fa-solid fa-truck-ramp-box me-1"></i> Transfer Out
        </a>
        <a href="stock_adjust.php?warehouse_id=<?= $warehouse['id'] ?>" class="btn btn-warning text-dark fw-bold">
            <i class="fa-solid fa-sliders me-1"></i> Stock Adjustment
        </a>
        <a href="warehouse_form.php?id=<?= $warehouse['id'] ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-pen me-1"></i> Edit Facility
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 me-3">
                    <i class="fa-solid fa-boxes-stacked fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Stored Units</div>
                    <h4 class="fw-bold mb-0 text-dark"><?= number_format($totalUnits, 0) ?></h4>
                    <small class="text-muted"><?= number_format($totalSkus) ?> distinct products</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 text-success p-3 me-3">
                    <i class="fa-solid fa-coins fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Inventory Valuation (Cost)</div>
                    <h4 class="fw-bold mb-0 text-success"><?= formatCurrency($totalCostVal) ?></h4>
                    <small class="text-muted">Capital asset in godown</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 text-info p-3 me-3">
                    <i class="fa-solid fa-tags fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Retail Valuation</div>
                    <h4 class="fw-bold mb-0 text-info"><?= formatCurrency($totalRetailVal) ?></h4>
                    <small class="text-muted">Expected shelf revenue</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 text-warning p-3 me-3">
                    <i class="fa-solid fa-chart-line fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Potential Gross Profit</div>
                    <h4 class="fw-bold mb-0 text-warning-emphasis"><?= formatCurrency($potentialProfit) ?></h4>
                    <small class="text-muted">Margin: <?= $totalRetailVal > 0 ? round(($potentialProfit / $totalRetailVal) * 100, 1) : 0 ?>%</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $activeTab === 'stock' ? 'active fw-bold' : '' ?>" href="warehouse_view.php?id=<?= $warehouseId ?>&tab=stock">
            <i class="fa-solid fa-boxes-stacked me-1"></i> Stock On Hand (<?= count($warehouseItems) ?> items)
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $activeTab === 'movements' ? 'active fw-bold' : '' ?>" href="warehouse_view.php?id=<?= $warehouseId ?>&tab=movements">
            <i class="fa-solid fa-clock-rotate-left me-1"></i> Inward / Outward Movement Audit Trail
        </a>
    </li>
</ul>

<?php if ($activeTab === 'stock'): ?>
    <!-- Search / Filter Form -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" class="row g-2">
                <input type="hidden" name="id" value="<?= $warehouseId ?>">
                <input type="hidden" name="tab" value="stock">
                <div class="col-12 col-md-6">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by title, code, barcode, or ISBN..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-8 col-md-4">
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-4 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fa-solid fa-search"></i> Search</button>
                    <?php if ($search || $filterCategory): ?>
                        <a href="warehouse_view.php?id=<?= $warehouseId ?>&tab=stock" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($warehouseItems)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="fa-solid fa-box-open fa-3x mb-3 text-secondary opacity-50"></i>
                    <h5>No Inventory In This Godown</h5>
                    <p class="small">Transfer stock from your shop counter or record a purchase delivered to this warehouse.</p>
                    <a href="warehouse_transfer_form.php" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-truck-ramp-box me-1"></i> Transfer Stock In
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light text-uppercase">
                            <tr>
                                <th>Product Title</th>
                                <th>Category / Type</th>
                                <th class="text-center">Stored Qty</th>
                                <th class="text-end">Cost Price</th>
                                <th class="text-end">Total Cost</th>
                                <th class="text-end">Retail Price</th>
                                <th class="text-end">Total Retail</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($warehouseItems as $it): 
                                $costTotal = (float)$it['wh_quantity'] * (float)$it['purchase_price'];
                                $retailTotal = (float)$it['wh_quantity'] * (float)$it['retail_price'];
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($it['title']) ?></div>
                                        <small class="text-muted font-monospace">
                                            Code: <?= htmlspecialchars($it['product_code']) ?>
                                            <?php if ($it['barcode']): ?>
                                                &bull; <i class="fa-solid fa-barcode ms-1"></i> <?= htmlspecialchars($it['barcode']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($it['category_name'] ?: 'General') ?></span>
                                    </td>
                                    <td class="text-center">
                                        <strong class="text-primary fs-6"><?= number_format((float)$it['wh_quantity'], 0) ?></strong>
                                        <small class="text-muted d-block"><?= htmlspecialchars($it['unit']) ?></small>
                                    </td>
                                    <td class="text-end font-monospace"><?= formatCurrency((float)$it['purchase_price']) ?></td>
                                    <td class="text-end font-monospace fw-bold text-success"><?= formatCurrency($costTotal) ?></td>
                                    <td class="text-end font-monospace"><?= formatCurrency((float)$it['retail_price']) ?></td>
                                    <td class="text-end font-monospace text-muted"><?= formatCurrency($retailTotal) ?></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="stock_adjust.php?product_id=<?= $it['product_id'] ?>&warehouse_id=<?= $warehouseId ?>" class="btn btn-outline-secondary" title="Adjust Stock (Physical Audit)">
                                                <i class="fa-solid fa-sliders me-1"></i> Adjust
                                            </a>
                                            <a href="warehouse_transfer_form.php?from_wh=<?= $warehouseId ?>" class="btn btn-outline-primary" title="Transfer Out">
                                                <i class="fa-solid fa-truck-ramp-box"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>



<?php else: ?>
    <!-- Movements Tab -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Stock Inward & Outward Ledger
            </h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($movements)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="fa-solid fa-clock-rotate-left fa-3x mb-3 text-secondary opacity-50"></i>
                    <h5>No Movements Recorded Yet</h5>
                    <p class="small">Purchases and stock transfers into or out of this facility will be logged here.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light text-uppercase">
                            <tr>
                                <th>Date & Time</th>
                                <th>Product Title</th>
                                <th>Movement Type</th>
                                <th>Reference #</th>
                                <th class="text-center">Qty Change</th>
                                <th class="text-center">Prev Stock</th>
                                <th class="text-center">New Stock</th>
                                <th>Notes / Reason</th>
                                <th>Staff</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $m): 
                                $isPositive = (float)$m['quantity_change'] > 0;
                            ?>
                                <tr>
                                    <td><?= date('d M, Y - h:i A', strtotime($m['movement_date'])) ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($m['product_title']) ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($m['product_code']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($m['movement_type'] === 'purchase_in'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Purchase In</span>
                                        <?php elseif ($m['movement_type'] === 'transfer_in'): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Transfer In</span>
                                        <?php elseif ($m['movement_type'] === 'transfer_out'): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Transfer Out</span>
                                        <?php elseif ($m['movement_type'] === 'adjustment_add'): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle">Audit +</span>
                                        <?php elseif ($m['movement_type'] === 'adjustment_deduct'): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Audit -</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border"><?= htmlspecialchars($m['movement_type']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-monospace text-muted"><?= htmlspecialchars($m['reference_no'] ?: '—') ?></td>
                                    <td class="text-center fw-bold <?= $isPositive ? 'text-success' : 'text-danger' ?>">
                                        <?= ($isPositive ? '+' : '') . number_format((float)$m['quantity_change'], 0) ?>
                                    </td>
                                    <td class="text-center text-muted"><?= number_format((float)$m['previous_stock'], 0) ?></td>
                                    <td class="text-center fw-bold text-dark"><?= number_format((float)$m['new_stock'], 0) ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($m['notes'] ?: '—') ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($m['user_name'] ?: 'Staff') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
