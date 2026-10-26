<?php
// warehouses.php - Warehouses & Godowns Management
require_once __DIR__ . '/config/app.php';
requireAuth();

// Handle Actions (POST / GET toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_status'])) {
        $whId = (int)$_POST['warehouse_id'];
        $wh = dbFetchOne("SELECT * FROM warehouses WHERE id = ?", [$whId]);
        if ($wh) {
            $newStatus = $wh['status'] ? 0 : 1;
            dbQuery("UPDATE warehouses SET status = ? WHERE id = ?", [$newStatus, $whId]);
            setFlash('success', "Warehouse '{$wh['name']}' status updated.");
        }
        redirect('warehouses.php');
    }

    if (isset($_POST['set_primary'])) {
        $whId = (int)$_POST['warehouse_id'];
        dbQuery("UPDATE warehouses SET is_primary = 0");
        dbQuery("UPDATE warehouses SET is_primary = 1 WHERE id = ?", [$whId]);
        setFlash('success', "Primary warehouse updated.");
        redirect('warehouses.php');
    }

    if (isset($_POST['delete_warehouse'])) {
        $whId = (int)$_POST['warehouse_id'];
        $stockCnt = dbFetchOne("SELECT SUM(quantity) as total_qty FROM warehouse_stock WHERE warehouse_id = ?", [$whId])['total_qty'] ?? 0;
        if ($stockCnt > 0) {
            setFlash('danger', "Cannot delete warehouse: It still contains $stockCnt units of stock. Please transfer or adjust out all stock first.");
        } else {
            dbQuery("DELETE FROM warehouse_stock WHERE warehouse_id = ?", [$whId]);
            dbQuery("DELETE FROM warehouses WHERE id = ?", [$whId]);
            setFlash('success', "Warehouse deleted successfully.");
        }
        redirect('warehouses.php');
    }
}

// Fetch all warehouses with aggregates
$warehouses = getWarehouses(false);

// Calculate totals
$totalWarehouses = count($warehouses);
$activeWarehouses = 0;
$totalUnits = 0;
$totalCostVal = 0;
$totalRetailVal = 0;

foreach ($warehouses as $w) {
    if ($w['status']) $activeWarehouses++;
    $totalUnits += (float)$w['total_units'];
    $totalCostVal += (float)$w['total_cost_value'];
    $totalRetailVal += (float)$w['total_retail_value'];
}

$pageTitle = 'Warehouses & Godowns';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark">
            <i class="fa-solid fa-warehouse text-warning me-2"></i> Warehouses & Storage Godowns
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="inventory.php" class="text-decoration-none">Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Warehouses</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <a href="warehouse_transfer_form.php" class="btn btn-outline-primary">
            <i class="fa-solid fa-truck-ramp-box me-1"></i> New Stock Transfer
        </a>
        <a href="warehouse_form.php" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i> Add Warehouse
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 text-warning p-3 me-3">
                    <i class="fa-solid fa-warehouse fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Active Warehouses</div>
                    <h4 class="fw-bold mb-0 text-dark"><?= $activeWarehouses ?> <small class="text-muted fs-6">/ <?= $totalWarehouses ?></small></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 me-3">
                    <i class="fa-solid fa-boxes-stacked fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Stored Units</div>
                    <h4 class="fw-bold mb-0 text-dark"><?= number_format($totalUnits, 0) ?></h4>
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
                    <div class="text-muted small fw-semibold">Storage Valuation (Cost)</div>
                    <h4 class="fw-bold mb-0 text-success"><?= formatCurrency($totalCostVal) ?></h4>
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
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Warehouses Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-list me-2 text-primary"></i> Configured Storage Facilities
        </h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($warehouses)): ?>
            <div class="p-5 text-center text-muted">
                <i class="fa-solid fa-warehouse fa-3x mb-3 text-secondary opacity-50"></i>
                <h5>No Warehouses Found</h5>
                <p class="small">Add your first storage godown to start managing multi-location stock.</p>
                <a href="warehouse_form.php" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus me-1"></i> Add Warehouse
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Warehouse Info</th>
                            <th>Contact / Manager</th>
                            <th>Location / City</th>
                            <th class="text-center">Stored SKUs</th>
                            <th class="text-center">Total Units</th>
                            <th class="text-end">Cost Valuation</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($warehouses as $wh): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded p-2 bg-light text-primary me-2 border">
                                            <i class="fa-solid fa-warehouse"></i>
                                        </div>
                                        <div>
                                            <a href="warehouse_view.php?id=<?= $wh['id'] ?>" class="fw-bold text-decoration-none text-dark">
                                                <?= htmlspecialchars($wh['name']) ?>
                                            </a>
                                            <div class="text-muted font-monospace" style="font-size: 0.78rem;">
                                                <?= htmlspecialchars($wh['warehouse_code']) ?>
                                                <?php if ($wh['is_primary']): ?>
                                                    <span class="badge bg-warning text-dark ms-1">Primary Godown</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($wh['manager_name'] ?: 'Not assigned') ?></div>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($wh['phone'] ?: '') ?>
                                        <?= ($wh['phone'] && $wh['email']) ? ' &bull; ' : '' ?>
                                        <?= htmlspecialchars($wh['email'] ?: '') ?>
                                    </small>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($wh['address'] ?: '—') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($wh['city'] ?: '') ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= number_format($wh['total_skus']) ?> SKUs</span>
                                </td>
                                <td class="text-center">
                                    <strong class="text-primary fs-6"><?= number_format((float)$wh['total_units']) ?></strong>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success"><?= formatCurrency((float)$wh['total_cost_value']) ?></strong>
                                    <div class="text-muted font-monospace" style="font-size: 0.75rem;">Retail: <?= formatCurrency((float)$wh['total_retail_value']) ?></div>
                                </td>
                                <td class="text-center">
                                    <?php if ($wh['status']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="warehouse_view.php?id=<?= $wh['id'] ?>" class="btn btn-outline-primary" title="View Stock & Audit">
                                            <i class="fa-solid fa-boxes-stacked me-1"></i> Stock
                                        </a>
                                        <a href="warehouse_transfer_form.php?from_wh=<?= $wh['id'] ?>" class="btn btn-outline-secondary" title="Transfer Stock Out">
                                            <i class="fa-solid fa-truck-ramp-box"></i>
                                        </a>
                                        <a href="warehouse_form.php?id=<?= $wh['id'] ?>" class="btn btn-outline-secondary" title="Edit Warehouse">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false"></button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <?php if (!$wh['is_primary']): ?>
                                                <li>
                                                    <form method="POST">
                                                        <input type="hidden" name="warehouse_id" value="<?= $wh['id'] ?>">
                                                        <button type="submit" name="set_primary" class="dropdown-item">
                                                            <i class="fa-solid fa-star text-warning me-2"></i> Set as Primary Godown
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            <li>
                                                <form method="POST">
                                                    <input type="hidden" name="warehouse_id" value="<?= $wh['id'] ?>">
                                                    <button type="submit" name="toggle_status" class="dropdown-item">
                                                        <i class="fa-solid fa-power-off text-muted me-2"></i> <?= $wh['status'] ? 'Deactivate' : 'Activate' ?>
                                                    </button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this warehouse? All history in this godown must be empty.');">
                                                    <input type="hidden" name="warehouse_id" value="<?= $wh['id'] ?>">
                                                    <button type="submit" name="delete_warehouse" class="dropdown-item text-danger">
                                                        <i class="fa-solid fa-trash me-2"></i> Delete Warehouse
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
