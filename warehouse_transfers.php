<?php
// warehouse_transfers.php - Inter-Location Stock Transfers History & Management
require_once __DIR__ . '/config/app.php';
requireAuth();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$fromLocation = $_GET['from_location'] ?? '';
$toLocation = $_GET['to_location'] ?? '';

// Build Query Filter
$where = ["DATE(wt.transfer_date) BETWEEN ? AND ?"];
$params = [$startDate, $endDate];

if (!empty($fromLocation)) {
    $parts = explode(':', $fromLocation);
    $where[] = "wt.from_location_type = ?";
    $params[] = $parts[0];
    if ($parts[0] === 'warehouse' && !empty($parts[1])) {
        $where[] = "wt.from_warehouse_id = ?";
        $params[] = (int)$parts[1];
    }
}

if (!empty($toLocation)) {
    $parts = explode(':', $toLocation);
    $where[] = "wt.to_location_type = ?";
    $params[] = $parts[0];
    if ($parts[0] === 'warehouse' && !empty($parts[1])) {
        $where[] = "wt.to_warehouse_id = ?";
        $params[] = (int)$parts[1];
    }
}

$transfers = dbFetchAll("SELECT wt.*, 
                                u.full_name as creator_name,
                                w_from.name as from_wh_name, w_from.warehouse_code as from_wh_code,
                                w_to.name as to_wh_name, w_to.warehouse_code as to_wh_code,
                                (SELECT SUM(total_cost) FROM warehouse_transfer_items WHERE transfer_id = wt.id) as total_value
                         FROM warehouse_transfers wt
                         LEFT JOIN users u ON u.id = wt.created_by
                         LEFT JOIN warehouses w_from ON w_from.id = wt.from_warehouse_id
                         LEFT JOIN warehouses w_to ON w_to.id = wt.to_warehouse_id
                         WHERE " . implode(" AND ", $where) . "
                         ORDER BY wt.id DESC LIMIT 100", $params);

$warehouses = getWarehouses(false);

$pageTitle = 'Stock Transfers';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark">
            <i class="fa-solid fa-truck-ramp-box text-info me-2"></i> Inter-Location Stock Transfers
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="warehouses.php" class="text-decoration-none">Warehouses</a></li>
                <li class="breadcrumb-item active" aria-current="page">Transfers</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <a href="warehouses.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-warehouse me-1"></i> Manage Godowns
        </a>
        <a href="warehouse_transfer_form.php" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i> New Stock Transfer
        </a>
    </div>
</div>

<!-- Filters Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold mb-1">From Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold mb-1">To Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold mb-1">Source (From)</label>
                <select name="from_location" class="form-select form-select-sm">
                    <option value="">All Sources</option>
                    <option value="shop:0" <?= $fromLocation === 'shop:0' ? 'selected' : '' ?>>🏬 Shop Counter</option>
                    <?php foreach ($warehouses as $wh): ?>
                        <option value="warehouse:<?= $wh['id'] ?>" <?= $fromLocation === "warehouse:{$wh['id']}" ? 'selected' : '' ?>>
                            🏭 <?= htmlspecialchars($wh['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold mb-1">Destination (To)</label>
                <select name="to_location" class="form-select form-select-sm">
                    <option value="">All Destinations</option>
                    <option value="shop:0" <?= $toLocation === 'shop:0' ? 'selected' : '' ?>>🏬 Shop Counter</option>
                    <?php foreach ($warehouses as $wh): ?>
                        <option value="warehouse:<?= $wh['id'] ?>" <?= $toLocation === "warehouse:{$wh['id']}" ? 'selected' : '' ?>>
                            🏭 <?= htmlspecialchars($wh['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <a href="warehouse_transfers.php" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Transfers Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Transfer Challans & Gate Passes
        </h6>
        <span class="badge bg-light text-dark border"><?= count($transfers) ?> records</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($transfers)): ?>
            <div class="p-5 text-center text-muted">
                <i class="fa-solid fa-truck-ramp-box fa-3x mb-3 text-secondary opacity-50"></i>
                <h5>No Stock Transfers Recorded</h5>
                <p class="small">Move stock between your shop display and storage godowns anytime.</p>
                <a href="warehouse_transfer_form.php" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus me-1"></i> Create First Stock Transfer
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light text-uppercase">
                        <tr>
                            <th>Transfer #</th>
                            <th>Date</th>
                            <th>Source (From)</th>
                            <th class="text-center"><i class="fa-solid fa-arrow-right text-muted"></i></th>
                            <th>Destination (To)</th>
                            <th class="text-center">SKUs</th>
                            <th class="text-center">Total Units</th>
                            <th class="text-end">Transfer Value</th>
                            <th>Status</th>
                            <th>Staff</th>
                            <th class="text-end">Challan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfers as $trf): ?>
                            <tr>
                                <td>
                                    <strong class="text-primary font-monospace"><?= htmlspecialchars($trf['transfer_no']) ?></strong>
                                </td>
                                <td><?= date('d M, Y', strtotime($trf['transfer_date'])) ?></td>
                                <td>
                                    <?php if ($trf['from_location_type'] === 'shop'): ?>
                                        <span class="badge bg-light text-dark border"><i class="fa-solid fa-store me-1"></i> Shop Counter</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-25 text-dark border border-warning">
                                            <i class="fa-solid fa-warehouse me-1"></i> <?= htmlspecialchars($trf['from_wh_name'] ?: 'Warehouse') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-muted">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </td>
                                <td>
                                    <?php if ($trf['to_location_type'] === 'shop'): ?>
                                        <span class="badge bg-light text-dark border"><i class="fa-solid fa-store me-1"></i> Shop Counter</span>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-25 text-dark border border-success">
                                            <i class="fa-solid fa-warehouse me-1"></i> <?= htmlspecialchars($trf['to_wh_name'] ?: 'Warehouse') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= (int)$trf['total_items'] ?></span>
                                </td>
                                <td class="text-center">
                                    <strong class="text-primary fs-6"><?= number_format((float)$trf['total_qty'], 0) ?></strong>
                                </td>
                                <td class="text-end font-monospace">
                                    <?= formatCurrency((float)($trf['total_value'] ?? 0)) ?>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <?= strtoupper($trf['status']) ?>
                                    </span>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($trf['creator_name'] ?: 'Admin') ?></td>
                                <td class="text-end">
                                    <a href="print_transfer.php?id=<?= $trf['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Print Delivery Challan">
                                        <i class="fa-solid fa-print me-1"></i> Print
                                    </a>
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
