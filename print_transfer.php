<?php
// print_transfer.php - Stock Transfer Slip & Goods Delivery Challan
require_once __DIR__ . '/config/app.php';
requireAuth();
requirePagePermission('warehouses.php');

$transferId = (int)($_GET['id'] ?? 0);
if ($transferId <= 0) {
    die("Transfer ID is required.");
}

$transfer = dbFetchOne("SELECT wt.*, 
                               u.full_name as creator_name,
                               w_from.name as from_wh_name, w_from.warehouse_code as from_wh_code, w_from.address as from_wh_address, w_from.manager_name as from_wh_mgr, w_from.phone as from_wh_phone,
                               w_to.name as to_wh_name, w_to.warehouse_code as to_wh_code, w_to.address as to_wh_address, w_to.manager_name as to_wh_mgr, w_to.phone as to_wh_phone
                        FROM warehouse_transfers wt
                        LEFT JOIN users u ON u.id = wt.created_by
                        LEFT JOIN warehouses w_from ON w_from.id = wt.from_warehouse_id
                        LEFT JOIN warehouses w_to ON w_to.id = wt.to_warehouse_id
                        WHERE wt.id = ?", [$transferId]);

if (!$transfer) {
    die("Transfer record not found.");
}

$items = dbFetchAll("SELECT wti.*, p.title, p.product_code, p.barcode, p.unit, p.product_type
                     FROM warehouse_transfer_items wti
                     JOIN products p ON p.id = wti.product_id
                     WHERE wti.transfer_id = ?
                     ORDER BY wti.id ASC", [$transferId]);

$shopName = getSetting('shop_name', 'Retail & Wholesale Mart');
$shopNameUrdu = getSetting('shop_name_urdu', 'ریٹیل اینڈ ہول سیل مارٹ');
$shopAddress = getSetting('shop_address', 'Shop # 14-18, Urdu Bazaar, Lahore, Pakistan');
$shopPhone = getSetting('shop_phone', '+92 300 1234567 / 042-37210000');

$fromDisplay = ($transfer['from_location_type'] === 'shop') ? '🏬 Main Shop / Storefront Counter' : "🏭 {$transfer['from_wh_name']} ({$transfer['from_wh_code']})";
$toDisplay = ($transfer['to_location_type'] === 'shop') ? '🏬 Main Shop / Storefront Counter' : "🏭 {$transfer['to_wh_name']} ({$transfer['to_wh_code']})";

$totalValue = 0;
foreach ($items as $it) {
    $totalValue += (float)$it['total_cost'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Challan - <?= htmlspecialchars($transfer['transfer_no']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .challan-wrapper {
            max-width: 850px;
            margin: 20px auto;
            background: #fff;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-radius: 6px;
        }
        .challan-header {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .meta-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 12px 15px;
        }
        .table th {
            background-color: #f1f3f5;
            font-size: 0.82rem;
            text-transform: uppercase;
        }
        .table td {
            font-size: 0.88rem;
        }
        .sign-box {
            border-top: 1px dashed #6c757d;
            padding-top: 8px;
            text-align: center;
            font-size: 0.82rem;
            margin-top: 50px;
        }
        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .challan-wrapper {
                max-width: 100%;
                margin: 0;
                padding: 15px;
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>

<!-- Control Bar -->
<div class="container my-3 no-print">
    <div class="d-flex justify-content-between align-items-center">
        <a href="warehouse_transfers.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Transfers
        </a>
        <div class="d-flex gap-2">
            <a href="warehouse_transfer_form.php" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-plus me-1"></i> New Transfer
            </a>
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Print Delivery Challan (Ctrl+P)
            </button>
        </div>
    </div>
</div>

<div class="challan-wrapper">
    <!-- Header -->
    <div class="challan-header d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold mb-0 text-primary"><?= htmlspecialchars($shopName) ?></h3>
            <div class="text-muted small"><?= htmlspecialchars($shopAddress) ?> &bull; <?= htmlspecialchars($shopPhone) ?></div>
        </div>
        <div class="text-end">
            <span class="badge bg-primary fs-6 px-3 py-2 text-uppercase letter-spacing">Delivery Challan / Gate Pass</span>
            <div class="fw-bold text-dark font-monospace mt-1 fs-5"><?= htmlspecialchars($transfer['transfer_no']) ?></div>
            <div class="text-muted small">Date: <?= date('d M, Y', strtotime($transfer['transfer_date'])) ?></div>
        </div>
    </div>

    <!-- Routing / Meta -->
    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="meta-box h-100">
                <div class="text-muted small fw-bold text-uppercase mb-1"><i class="fa-solid fa-arrow-up-from-bracket text-danger me-1"></i> Dispatched From (Source):</div>
                <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($fromDisplay) ?></h6>
                <?php if ($transfer['from_location_type'] === 'warehouse' && $transfer['from_wh_address']): ?>
                    <div class="small text-muted"><?= htmlspecialchars($transfer['from_wh_address']) ?></div>
                <?php endif; ?>
                <?php if (!empty($transfer['from_wh_mgr'])): ?>
                    <div class="small text-muted">In-Charge: <?= htmlspecialchars($transfer['from_wh_mgr']) ?> (<?= htmlspecialchars($transfer['from_wh_phone'] ?: 'N/A') ?>)</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-6">
            <div class="meta-box h-100">
                <div class="text-muted small fw-bold text-uppercase mb-1"><i class="fa-solid fa-arrow-down-to-bracket text-success me-1"></i> Delivered To (Destination):</div>
                <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($toDisplay) ?></h6>
                <?php if ($transfer['to_location_type'] === 'warehouse' && $transfer['to_wh_address']): ?>
                    <div class="small text-muted"><?= htmlspecialchars($transfer['to_wh_address']) ?></div>
                <?php endif; ?>
                <?php if (!empty($transfer['to_wh_mgr'])): ?>
                    <div class="small text-muted">In-Charge: <?= htmlspecialchars($transfer['to_wh_mgr']) ?> (<?= htmlspecialchars($transfer['to_wh_phone'] ?: 'N/A') ?>)</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($transfer['notes'])): ?>
        <div class="alert alert-light border py-2 px-3 small mb-4">
            <strong><i class="fa-solid fa-truck me-1"></i> Transit & Vehicle Details:</strong> <?= htmlspecialchars($transfer['notes']) ?>
        </div>
    <?php endif; ?>

    <!-- Items Table -->
    <table class="table table-bordered align-middle mb-4">
        <thead>
            <tr>
                <th style="width: 5%;" class="text-center">#</th>
                <th style="width: 50%;">Product / Item Title</th>
                <th style="width: 15%;" class="text-center">Code</th>
                <th style="width: 15%;" class="text-center">Quantity</th>
                <th style="width: 15%;" class="text-end">Est. Value (Rs.)</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($items as $it): ?>
                <tr>
                    <td class="text-center text-muted"><?= $i++ ?></td>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($it['title']) ?></div>
                        <?php if ($it['barcode']): ?>
                            <small class="text-muted font-monospace"><i class="fa-solid fa-barcode me-1"></i><?= htmlspecialchars($it['barcode']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center font-monospace small"><?= htmlspecialchars($it['product_code']) ?></td>
                    <td class="text-center fw-bold fs-6 text-primary"><?= number_format((float)$it['quantity'], 0) ?></td>
                    <td class="text-end font-monospace"><?= formatCurrency((float)$it['total_cost']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="3" class="text-end text-uppercase">Total Physical Units:</td>
                <td class="text-center text-primary fs-6"><?= number_format((float)$transfer['total_qty'], 0) ?></td>
                <td class="text-end text-success font-monospace"><?= formatCurrency($totalValue) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="small text-muted mb-4">
        * Please count and verify all physical carton quantities before signing the delivery challan. Any discrepancies must be reported immediately.
    </div>

    <!-- Signatures -->
    <div class="row text-center mt-5">
        <div class="col-4">
            <div class="sign-box">
                <strong>Dispatched By</strong><br>
                <?= htmlspecialchars($transfer['creator_name'] ?: 'Store Staff') ?>
            </div>
        </div>
        <div class="col-4">
            <div class="sign-box">
                <strong>Transport / Driver</strong><br>
                Vehicle & Carrier Signature
            </div>
        </div>
        <div class="col-4">
            <div class="sign-box">
                <strong>Received By</strong><br>
                Receiver / Godown In-Charge
            </div>
        </div>
    </div>

    <!-- Company Promotion -->
    <div class="software-promo text-center mt-4 pt-3 border-top" style="font-size: 10px; line-height: 1.35; color: #64748b;">
        <div class="fw-bold text-dark" style="letter-spacing: 0.5px;">*** Software By: KH Soft ***</div>
        <div>0315-0709606 | 0305-6114800</div>
        <div>laiqahmadkhawaja1604@gmail.com</div>
    </div>
</div>

</body>
</html>
