<?php
// print_shift.php - Official Register Shift Reconciliation & Closing Slip
require_once __DIR__ . '/config/app.php';
requireAuth();
requirePagePermission('pos.php');

$shiftId = (int)($_GET['id'] ?? 0);
if (!$shiftId) {
    die("Shift ID is required.");
}

$shift = dbFetchOne("SELECT rs.*, u.full_name as cashier_name, u.username 
                     FROM register_shifts rs 
                     JOIN users u ON u.id = rs.user_id 
                     WHERE rs.id = ?", [$shiftId]);

if (!$shift) {
    die("Shift not found.");
}

// If shift is closed, use stored values; if open, calculate real-time
if ($shift['status'] === 'closed') {
    $summary = [
        'opening_cash' => (float)$shift['opening_cash'],
        'cash_sales' => (float)$shift['cash_sales'],
        'customer_receipts' => (float)$shift['customer_receipts_cash'],
        'other_inflows' => (float)$shift['other_cash_inflow'],
        'total_inflows' => (float)$shift['cash_sales'] + (float)$shift['customer_receipts_cash'] + (float)$shift['other_cash_inflow'],
        'total_outflows' => (float)$shift['cash_outflow'],
        'system_expected' => (float)$shift['system_expected_cash']
    ];
    $denoms = json_decode($shift['denominations'], true) ?: [];
    $totalPhysical = (float)$shift['physical_counted_cash'];
    $variance = (float)$shift['variance'];
} else {
    $summary = getShiftCashSummary($shift);
    $denoms = [];
    $totalPhysical = 0.00;
    $variance = 0.00;
}

$shopName = getSetting('shop_name', 'Kitab Ghar & Stationery');
$shopNameUrdu = getSetting('shop_name_urdu', 'کتاب گھر اینڈ اسٹیشنری مارٹ');
$shopTagline = getSetting('shop_tagline', 'Point of Sale & Inventory Management System');
$shopAddress = getSetting('shop_address', 'Shop # 14-18, Urdu Bazaar, Lahore, Pakistan');
$shopPhone = getSetting('shop_phone', '+92 300 1234567 / 042-37210000');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift_Slip_#<?= $shift['id'] ?>_<?= date('Ymd_Hi') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body {
            background-color: #f1f5f9;
            color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .action-toolbar {
            background: #1e293b;
            color: #fff;
            padding: 10px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .print-container {
            max-width: 800px;
            margin: 25px auto;
            background: #fff;
            padding: 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-radius: 8px;
        }
        .report-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .report-title-badge {
            display: inline-block;
            background: #0f172a;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .font-urdu {
            font-family: 'Noto Nastaliq Urdu', serif;
        }
        .table-print {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .table-print th, .table-print td {
            padding: 7px 10px;
            border: 1px solid #cbd5e1;
        }
        .table-print thead th {
            background-color: #f8fafc;
            color: #334155;
            font-weight: 700;
        }
        @media print {
            body { background: #fff !important; }
            .no-print { display: none !important; }
            .print-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
        }
    </style>
</head>
<body>

<!-- Standalone Action Toolbar (Hidden during print) -->
<div class="action-toolbar no-print d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
        <i class="fa-solid fa-receipt text-warning fs-5"></i>
        <span class="fw-bold">Register Shift #<?= $shift['id'] ?> Closing Slip</span>
        <span class="badge bg-secondary"><?= htmlspecialchars($shopName) ?></span>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-warning btn-sm fw-bold px-3 shadow-sm" onclick="triggerShareDialog()">
            <i class="fa-solid fa-print me-1"></i> Print / Thermal Share
        </button>
        <button type="button" class="btn btn-outline-light btn-sm fw-bold px-2 shadow-sm" onclick="triggerShareDialog()">
            <i class="fa-solid fa-share-nodes me-1"></i> Share
        </button>
        <a href="shift.php" class="btn btn-outline-light btn-sm px-3">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Shifts
        </a>
    </div>
</div>

<div class="print-container" id="printableShiftArea">
    <!-- Header Letterhead -->
    <div class="report-header">
        <div class="row align-items-center">
            <div class="col-7">
                <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($shopName) ?></h3>
                <div class="text-secondary small font-urdu mt-1"><?= htmlspecialchars($shopNameUrdu) ?></div>
                <div class="text-muted small mt-1"><?= htmlspecialchars($shopTagline) ?></div>
                <div class="small text-muted mt-1">
                    <i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($shopAddress) ?><br>
                    <i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($shopPhone) ?>
                </div>
            </div>
            <div class="col-5 text-end">
                <div class="report-title-badge mb-2">REGISTER SHIFT CLOSING SLIP</div>
                <div class="small text-muted fw-bold">Shift #<?= $shift['id'] ?> (<?= strtoupper($shift['status']) ?>)</div>
                <div class="small text-muted mt-1">
                    Cashier: <strong><?= htmlspecialchars($shift['cashier_name'] ?: $shift['username']) ?></strong><br>
                    Generated: <strong><?= date('d M, Y &bull; h:i A') ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Shift Meta Information Box -->
    <div class="row g-2 mb-3 p-3 bg-light rounded border text-muted small">
        <div class="col-6 col-sm-3">
            <div class="fw-bold text-dark">Shift #</div>
            <div><?= $shift['id'] ?></div>
        </div>
        <div class="col-6 col-sm-3">
            <div class="fw-bold text-dark">Started</div>
            <div><?= date('d M Y, h:i A', strtotime($shift['start_time'])) ?></div>
        </div>
        <div class="col-6 col-sm-3">
            <div class="fw-bold text-dark">Ended / Closed</div>
            <div><?= $shift['end_time'] ? date('d M Y, h:i A', strtotime($shift['end_time'])) : 'Currently Open' ?></div>
        </div>
        <div class="col-6 col-sm-3">
            <div class="fw-bold text-dark">Duration</div>
            <div>
                <?php
                    $startTs = strtotime($shift['start_time']);
                    $endTs = $shift['end_time'] ? strtotime($shift['end_time']) : time();
                    $diffSec = $endTs - $startTs;
                    $h = floor($diffSec / 3600);
                    $m = floor(($diffSec % 3600) / 60);
                    echo "$h hrs $m mins";
                ?>
            </div>
        </div>
    </div>

    <!-- 1. Cash Inflows & Outflows Summary -->
    <h6 class="fw-bold text-dark mb-2">1. Cash Drawer Movement Summary:</h6>
    <table class="table-print mb-4">
        <tbody>
            <tr class="table-light">
                <td class="fw-bold" style="width: 70%;">A. OPENING CASH / STARTING FLOAT</td>
                <td class="text-end font-monospace fw-bold" style="width: 30%;"><?= formatCurrency($summary['opening_cash']) ?></td>
            </tr>
            <tr class="table-light"><td colspan="2" class="fw-bold text-success">B. CASH INFLOWS COLLECTED IN SHIFT</td></tr>
            <tr><td class="ps-4">Counter POS Cash Sales</td><td class="text-end font-monospace text-success">+ <?= formatCurrency($summary['cash_sales']) ?></td></tr>
            <tr><td class="ps-4">Customer Khata Recoveries in Cash</td><td class="text-end font-monospace text-success">+ <?= formatCurrency($summary['customer_receipts']) ?></td></tr>
            <tr><td class="ps-4">Other Cash Inflows & Bank Transfers In</td><td class="text-end font-monospace text-success">+ <?= formatCurrency($summary['other_inflows']) ?></td></tr>
            <tr class="fw-bold"><td>Total Cash Inflows</td><td class="text-end font-monospace text-success">+ <?= formatCurrency($summary['total_inflows']) ?></td></tr>

            <tr class="table-light"><td colspan="2" class="fw-bold text-danger">C. CASH OUTFLOWS PAID IN SHIFT</td></tr>
            <tr><td class="ps-4">Cash Purchases, Supplier Payouts & Drawer Expenses</td><td class="text-end font-monospace text-danger">- <?= formatCurrency($summary['total_outflows']) ?></td></tr>
            <tr class="fw-bold"><td>Total Cash Outflows</td><td class="text-end font-monospace text-danger">- <?= formatCurrency($summary['total_outflows']) ?></td></tr>

            <tr style="border-top: 3px double #0f172a; font-size: 13px; font-weight: bold;" class="table-primary">
                <td>SYSTEM EXPECTED CLOSING CASH</td>
                <td class="text-end font-monospace text-primary"><?= formatCurrency($summary['system_expected']) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- 2. Physical Currency Denominations Verification -->
    <h6 class="fw-bold text-dark mb-2">2. Physical Currency Denominations Count:</h6>
    <table class="table-print mb-4" style="font-size: 11px;">
        <thead>
            <tr>
                <th>Denomination Note</th>
                <th class="text-center" style="width: 25%;">Count</th>
                <th class="text-end" style="width: 35%;">Subtotal (Rs.)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ([5000, 1000, 500, 100, 50, 20, 10] as $dn): 
                $cnt = isset($denoms[$dn]) ? (int)$denoms[$dn] : null;
                $sub = ($cnt !== null) ? ($dn * $cnt) : null;
            ?>
                <tr>
                    <td class="fw-bold">Rs. <?= number_format($dn) ?> Note</td>
                    <td class="text-center font-monospace">
                        <?= ($cnt !== null) ? number_format($cnt) : '&nbsp;' ?>
                    </td>
                    <td class="text-end font-monospace">
                        <?= ($sub !== null) ? formatCurrency($sub) : '&nbsp;' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td class="fw-bold">Coins & Loose Cash</td>
                <td class="text-center font-monospace">
                    <?= isset($denoms['coins']) ? '-' : '&nbsp;' ?>
                </td>
                <td class="text-end font-monospace">
                    <?= isset($denoms['coins']) ? formatCurrency($denoms['coins']) : '&nbsp;' ?>
                </td>
            </tr>
            <tr class="fw-bold table-light fs-6">
                <td colspan="2">TOTAL PHYSICAL COUNTED CASH:</td>
                <td class="text-end font-monospace text-primary">
                    <?= $shift['status'] === 'closed' ? formatCurrency($totalPhysical) : '&nbsp;' ?>
                </td>
            </tr>
            <tr class="fw-bold fs-6">
                <td colspan="2">VARIANCE (SHORTAGE / EXCESS):</td>
                <td class="text-end font-monospace">
                    <?php if ($shift['status'] === 'closed'): ?>
                        <?php if (abs($variance) < 0.01): ?>
                            <span class="text-success fw-bold">Rs. 0.00 (Balanced)</span>
                        <?php elseif ($variance > 0): ?>
                            <span class="text-primary fw-bold">+ <?= formatCurrency($variance) ?> (Excess)</span>
                        <?php else: ?>
                            <span class="text-danger fw-bold">- <?= formatCurrency(abs($variance)) ?> (Shortage)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        &nbsp;
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- 3. Variance Explanation & Cashier Notes -->
    <div class="p-3 bg-light rounded border mb-4">
        <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-note-sticky text-warning me-1"></i> Cashier Variance Explanation & Notes:</h6>
        <p class="mb-0 small text-dark fst-italic">
            <?= $shift['notes'] ? nl2br(htmlspecialchars($shift['notes'])) : 'No variance notes entered.' ?>
        </p>
    </div>

    <!-- Signatures -->
    <div class="row pt-4 mt-4" style="border-top: 1px dashed #94a3b8;">
        <div class="col-6 text-center">
            <div style="border-top: 1px solid #475569; width: 200px; margin: 40px auto 5px auto;"></div>
            <strong class="small text-muted">Cashier Signature</strong><br>
            <span class="small fw-semibold"><?= htmlspecialchars($shift['cashier_name'] ?: $shift['username']) ?></span>
        </div>
        <div class="col-6 text-center">
            <div style="border-top: 1px solid #475569; width: 200px; margin: 40px auto 5px auto;"></div>
            <strong class="small text-muted">Manager / Supervisor Signature</strong><br>
            <span class="small fw-semibold">Audit Verified</span>
        </div>
    </div>
    <!-- Company Promotion -->
    <div class="software-promo text-center mt-4 pt-3 border-top" style="font-size: 10px; line-height: 1.35; color: #64748b;">
        <div class="fw-bold text-dark" style="letter-spacing: 0.5px;">*** Software By: KH Soft ***</div>
        <div>0315-0709606 | 0305-6114800</div>
        <div>laiqahmadkhawaja1604@gmail.com</div>
    </div>
</div>

<script>
function triggerShareDialog() {
    var toolbars = document.querySelectorAll('.action-toolbar, .no-print');
    var captureTarget = document.getElementById('printableShiftArea') || document.body;
    var originalBg = captureTarget.style.backgroundColor;

    if (window.AndroidPrinter) {
        if (typeof html2canvas === 'function') {
            toolbars.forEach(function(el) { el.style.display = 'none'; });
            captureTarget.style.backgroundColor = '#ffffff';

            html2canvas(captureTarget, {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff'
            }).then(function(canvas) {
                toolbars.forEach(function(el) { el.style.display = ''; });
                captureTarget.style.backgroundColor = originalBg;

                var base64 = canvas.toDataURL('image/png');
                if (typeof window.AndroidPrinter.shareBase64Image === 'function') {
                    window.AndroidPrinter.shareBase64Image(base64, document.title || 'Shift_Slip');
                } else if (typeof window.AndroidPrinter.sharePageAsImage === 'function') {
                    window.AndroidPrinter.sharePageAsImage(document.title || 'Shift_Slip');
                }
            }).catch(function(err) {
                toolbars.forEach(function(el) { el.style.display = ''; });
                captureTarget.style.backgroundColor = originalBg;
                if (typeof window.AndroidPrinter.sharePageAsImage === 'function') {
                    window.AndroidPrinter.sharePageAsImage(document.title || 'Shift_Slip');
                }
            });
            return;
        } else if (typeof window.AndroidPrinter.sharePageAsImage === 'function') {
            toolbars.forEach(function(el) { el.style.display = 'none'; });
            window.AndroidPrinter.sharePageAsImage(document.title || 'Shift_Slip');
            return;
        }
    }

    toolbars.forEach(function(el) { el.style.display = ''; });
    captureTarget.style.backgroundColor = originalBg;
    if (navigator.share) {
        navigator.share({ title: document.title, url: window.location.href }).catch(function(){});
    } else {
        window.print();
    }
}

(function() {
    var originalWindowPrint = window.print;
    window.print = function() {
        if (typeof triggerShareDialog === 'function') {
            triggerShareDialog();
            return;
        }
        if (originalWindowPrint) originalWindowPrint.call(window);
    };
})();
</script>
</body>
</html>
