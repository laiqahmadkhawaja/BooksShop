<?php
// print_purchase.php - Official Purchase Order & Stock Inward Receipt Printer
require_once __DIR__ . '/config/app.php';
requireAuth();
requirePagePermission('purchases.php');

$purchaseId = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'a4'; // 'a4' (default) or 'thermal'

if (!$purchaseId) {
    die("Purchase Order ID is missing.");
}

$purchase = dbFetchOne("SELECT p.*, s.name as supplier_name, s.company_name, s.phone as supplier_phone, 
                               s.address as supplier_address, s.current_balance as supplier_balance,
                               u.full_name as creator_name, a.account_name, w.name as warehouse_name, w.warehouse_code
                        FROM purchases p
                        JOIN suppliers s ON s.id = p.supplier_id
                        LEFT JOIN users u ON u.id = p.created_by
                        LEFT JOIN accounts a ON a.id = p.payment_account_id
                        LEFT JOIN warehouses w ON w.id = p.destination_warehouse_id
                        WHERE p.id = ?", [$purchaseId]);

if (!$purchase) {
    die("Purchase order not found.");
}

$items = dbFetchAll("SELECT pi.*, p.title, p.product_code, p.barcode, p.unit, p.product_type,
                            p.author, p.publisher, p.class_grade
                     FROM purchase_items pi
                     JOIN products p ON p.id = pi.product_id
                     WHERE pi.purchase_id = ?
                     ORDER BY pi.id ASC", [$purchaseId]);

$shopName = getSetting('shop_name', 'Retail & Wholesale Mart');
$shopNameUrdu = getSetting('shop_name_urdu', 'ریٹیل اینڈ ہول سیل مارٹ');
$shopTagline = getSetting('shop_tagline', 'Complete Retail & Wholesale Inventory POS Solution');
$shopAddress = getSetting('shop_address', 'Shop # 14-18, Urdu Bazaar, Lahore, Pakistan');
$shopPhone = getSetting('shop_phone', '+92 300 1234567 / 042-37210000');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Invoice <?= htmlspecialchars($purchase['purchase_no']) ?> - <?= htmlspecialchars($shopName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <style>
        body {
            background-color: #f0f2f5;
            color: #111;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }
        .font-urdu {
            font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', serif;
            line-height: 2.2 !important;
            direction: rtl;
        }
        #purchaseBarcodeSvg {
            max-width: 100% !important;
            height: auto !important;
            margin: 0 auto;
            display: inline-block;
        }

        /* Top Action Bar */
        .print-actions-bar {
            background: #fff;
            padding: 12px 20px;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }

        <?php if ($type === 'thermal'): ?>
        /* 80mm Thermal Receipt Styling */
        .thermal-wrapper {
            width: 80mm;
            max-width: 100%;
            margin: 20px auto;
            background: #fff;
            padding: 5mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.35;
        }
        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #333;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .receipt-table {
            width: 100%;
            margin-bottom: 8px;
        }
        .receipt-table th, .receipt-table td {
            padding: 3px 0;
        }
        .border-dashed-y {
            border-top: 1px dashed #333;
            border-bottom: 1px dashed #333;
            padding: 6px 0;
            margin: 6px 0;
        }
        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .thermal-wrapper {
                width: 80mm !important;
                margin: 0 !important;
                padding: 3mm !important;
                box-shadow: none !important;
            }
        }
        <?php else: ?>
        /* Professional A4 Purchase Voucher Styling */
        .a4-wrapper {
            max-width: 850px;
            margin: 25px auto 40px auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .voucher-title {
            font-size: 24px;
            font-weight: 800;
            color: #157347;
            letter-spacing: 0.5px;
        }
        .table-a4 th {
            background-color: #f8f9fa;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .a4-wrapper {
                max-width: 100%;
                margin: 0;
                padding: 15px;
                box-shadow: none;
            }
        }
        <?php endif; ?>
    </style>
</head>
<body>

<!-- Top Navigation & Action Buttons (Hidden on Print) -->
<div class="print-actions-bar no-print">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="purchases.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Purchases
            </a>
            <div class="btn-group btn-group-sm">
                <a href="print_purchase.php?id=<?= $purchaseId ?>&type=a4" class="btn <?= $type === 'a4' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fa-solid fa-file-lines me-1"></i> A4 Voucher
                </a>
                <a href="print_purchase.php?id=<?= $purchaseId ?>&type=thermal" class="btn <?= $type === 'thermal' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="fa-solid fa-receipt me-1"></i> 80mm Slip
                </a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" onclick="triggerShareDialog()" class="btn btn-success btn-sm fw-bold shadow-sm px-3">
                <i class="fa-solid fa-print me-1"></i> Print / Share
            </button>
            <button type="button" onclick="triggerShareDialog()" class="btn btn-outline-success btn-sm fw-bold shadow-sm px-2">
                <i class="fa-solid fa-share-nodes me-1"></i> Share
            </button>
            <button onclick="window.close()" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-xmark me-1"></i> Close
            </button>
        </div>
    </div>
</div>

<?php if ($type === 'thermal'): ?>
<!-- ================= 80mm THERMAL RECEIPT ================= -->
<div class="thermal-wrapper">
    <div class="receipt-header">
        <h5 class="fw-bold mb-0"><?= htmlspecialchars($shopName) ?></h5>
        <div class="font-urdu small"><?= htmlspecialchars($shopNameUrdu) ?></div>
        <div class="small text-muted"><?= htmlspecialchars($shopTagline) ?></div>
        <div class="small"><?= htmlspecialchars($shopAddress) ?></div>
        <div class="small fw-bold">Tel: <?= htmlspecialchars($shopPhone) ?></div>
        <div class="fw-bold mt-2" style="font-size: 13px;">*** PURCHASE ORDER / GOODS INWARD ***</div>
    </div>

    <!-- Voucher Metadata -->
    <div class="d-flex justify-content-between small">
        <span>Order #: <strong><?= htmlspecialchars($purchase['purchase_no']) ?></strong></span>
        <span><?= date('d-m-Y', strtotime($purchase['purchase_date'])) ?></span>
    </div>
    <div class="d-flex justify-content-between small">
        <span>Supplier: <strong><?= htmlspecialchars($purchase['supplier_name']) ?></strong></span>
        <span>Status: <strong><?= strtoupper($purchase['payment_status']) ?></strong></span>
    </div>
    <?php if (!empty($purchase['invoice_no']) && $purchase['invoice_no'] !== $purchase['purchase_no']): ?>
        <div class="small text-muted">Bill/Bilty #: <?= htmlspecialchars($purchase['invoice_no']) ?></div>
    <?php endif; ?>

    <!-- Items Table -->
    <table class="receipt-table mt-2">
        <thead>
            <tr style="border-bottom: 1px dashed #333;">
                <th style="width: 50%;">Item</th>
                <th style="width: 15%; text-align: center;">Qty</th>
                <th style="width: 15%; text-align: right;">Cost</th>
                <th style="width: 20%; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['title']) ?><br>
                        <small class="text-muted"><?= htmlspecialchars($item['product_code']) ?></small>
                    </td>
                    <td style="text-align: center;"><?= (float)$item['quantity'] ?></td>
                    <td style="text-align: right;"><?= formatCurrency($item['unit_cost']) ?></td>
                    <td style="text-align: right;"><?= formatCurrency($item['total_cost']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="border-dashed-y">
        <div class="d-flex justify-content-between">
            <span>Items Subtotal:</span>
            <span><?= formatCurrency($purchase['subtotal']) ?></span>
        </div>
        <?php if ($purchase['discount_amount'] > 0): ?>
            <div class="d-flex justify-content-between">
                <span>Discount:</span>
                <span>- <?= formatCurrency($purchase['discount_amount']) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($purchase['shipping_cost'] > 0): ?>
            <div class="d-flex justify-content-between">
                <span>Shipping / Freight:</span>
                <span>+ <?= formatCurrency($purchase['shipping_cost']) ?></span>
            </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between fw-bold" style="font-size: 14px;">
            <span>GRAND TOTAL:</span>
            <span><?= formatCurrency($purchase['grand_total']) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>Amount Paid:</span>
            <span><?= formatCurrency($purchase['paid_amount']) ?></span>
        </div>
        <?php if ($purchase['due_amount'] > 0): ?>
            <div class="d-flex justify-content-between fw-bold">
                <span>Balance Due:</span>
                <span><?= formatCurrency($purchase['due_amount']) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="text-center mt-3">
        <svg id="purchaseBarcodeSvg"></svg>
    </div>
    <div class="text-center small mt-2">
        Stock Inward Verified by Staff<br>
        Recorded: <?= date('d M, Y - h:i A', strtotime($purchase['created_at'])) ?>
    </div>
</div>

<?php else: ?>
<!-- ================= PROFESSIONAL A4 PURCHASE INVOICE ================= -->
<div class="a4-wrapper">
    <!-- Header -->
    <div class="row align-items-center mb-4 pb-3 border-bottom">
        <div class="col-sm-7">
            <h3 class="fw-bold text-success mb-1"><?= htmlspecialchars($shopName) ?></h3>
            <div class="font-urdu fs-6 text-secondary"><?= htmlspecialchars($shopNameUrdu) ?></div>
            <div class="text-muted small"><?= htmlspecialchars($shopTagline) ?></div>
            <div class="small mt-1"><i class="fa-solid fa-location-dot me-1 text-danger"></i> <?= htmlspecialchars($shopAddress) ?></div>
            <div class="small"><i class="fa-solid fa-phone me-1 text-success"></i> <?= htmlspecialchars($shopPhone) ?></div>
        </div>
        <div class="col-sm-5 text-sm-end mt-3 mt-sm-0">
            <div class="voucher-title">PURCHASE ORDER</div>
            <div class="text-muted small fw-bold text-uppercase">Goods Inward & Stock Receipt</div>
            <div class="fw-bold fs-5 text-dark mt-1"><?= htmlspecialchars($purchase['purchase_no']) ?></div>
            <div class="small text-muted">Date: <strong><?= date('d M, Y', strtotime($purchase['purchase_date'])) ?></strong></div>
            <div class="mt-2">
                <svg id="purchaseBarcodeSvg"></svg>
            </div>
        </div>
    </div>

    <!-- Supplier & Payment Meta -->
    <div class="row mb-4">
        <div class="col-sm-6">
            <h6 class="fw-bold text-uppercase small text-secondary">Supplier / Publisher:</h6>
            <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($purchase['supplier_name']) ?></h5>
            <?php if ($purchase['company_name']): ?>
                <div class="fw-semibold text-secondary small"><?= htmlspecialchars($purchase['company_name']) ?></div>
            <?php endif; ?>
            <?php if ($purchase['supplier_phone']): ?>
                <div class="small"><i class="fa-solid fa-phone me-1 text-muted"></i> <?= htmlspecialchars($purchase['supplier_phone']) ?></div>
            <?php endif; ?>
            <?php if ($purchase['supplier_address']): ?>
                <div class="small"><i class="fa-solid fa-location-dot me-1 text-muted"></i> <?= htmlspecialchars($purchase['supplier_address']) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-sm-6 text-sm-end">
            <h6 class="fw-bold text-uppercase small text-secondary">Order & Payment Details:</h6>
            <div class="small mb-1">
                <strong>Supplier Bill / Invoice #:</strong> 
                <span class="fw-bold text-dark"><?= htmlspecialchars($purchase['invoice_no'] ?: $purchase['purchase_no']) ?></span>
            </div>
            <div class="small mb-1">
                <strong>Payment Status:</strong> 
                <span class="badge bg-<?= $purchase['payment_status'] === 'paid' ? 'success' : ($purchase['payment_status'] === 'partial' ? 'warning text-dark' : 'danger') ?>">
                    <?= strtoupper($purchase['payment_status']) ?>
                </span>
            </div>
            <div class="small mb-1">
                <strong>Payment Account:</strong> <?= htmlspecialchars($purchase['account_name'] ?: 'Cash Account') ?>
            </div>
            <div class="small">
                <strong>Recorded By:</strong> <?= htmlspecialchars($purchase['creator_name'] ?: 'Staff') ?>
            </div>
            <div class="small">
                <strong>Delivered To:</strong> 
                <?php if ($purchase['destination_type'] === 'warehouse' && !empty($purchase['warehouse_name'])): ?>
                    <span class="badge bg-warning text-dark border"><?= htmlspecialchars($purchase['warehouse_name']) ?></span>
                <?php else: ?>
                    <span class="badge bg-light text-dark border">Main Shop / Counter</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-a4 align-middle">
            <thead>
                <tr>
                    <th style="width: 5%;" class="text-center">#</th>
                    <th style="width: 48%;">Product / Item Description</th>
                    <th style="width: 12%;" class="text-center">Type</th>
                    <th style="width: 15%;" class="text-end">Unit Cost</th>
                    <th style="width: 10%;" class="text-center">Qty</th>
                    <th style="width: 15%;" class="text-end">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($items as $item): ?>
                    <tr>
                        <td class="text-center text-muted"><?= $i++ ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($item['title']) ?></div>
                            <div class="text-muted small">
                                <span class="badge bg-light text-dark border me-1">Code: <?= htmlspecialchars($item['product_code']) ?></span>
                                <?php if ($item['barcode']): ?>
                                    <span class="badge bg-light text-secondary border me-1"><i class="fa-solid fa-barcode me-1"></i><?= htmlspecialchars($item['barcode']) ?></span>
                                <?php endif; ?>
                                <?php if ($item['author']): ?>
                                    <span>Author: <?= htmlspecialchars($item['author']) ?></span> &bull; 
                                <?php endif; ?>
                                <?php if ($item['publisher']): ?>
                                    <span>Pub: <?= htmlspecialchars($item['publisher']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.7rem;">
                                <?= htmlspecialchars($item['product_type']) ?>
                            </span>
                        </td>
                        <td class="text-end fw-semibold"><?= formatCurrency($item['unit_cost']) ?></td>
                        <td class="text-center fw-bold"><?= (float)$item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?></td>
                        <td class="text-end fw-bold"><?= formatCurrency($item['total_cost']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary & Totals -->
    <div class="row align-items-start">
        <div class="col-sm-6">
            <div class="p-3 bg-light rounded border h-100">
                <h6 class="fw-bold small mb-2"><i class="fa-solid fa-note-sticky me-1 text-success"></i> Stock Receiving Notes:</h6>
                <?php if (!empty($purchase['notes'])): ?>
                    <p class="small text-dark mb-2"><?= nl2br(htmlspecialchars($purchase['notes'])) ?></p>
                <?php else: ?>
                    <p class="small text-muted fst-italic mb-2">No special notes recorded for this inward consignment.</p>
                <?php endif; ?>
                <div class="small text-muted border-top pt-2 mt-2">
                    Goods verified and taken into system inventory. Supplier ledger has been updated.
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <table class="table table-sm border-0">
                <tr>
                    <td class="text-muted">Items Subtotal:</td>
                    <td class="text-end fw-semibold"><?= formatCurrency($purchase['subtotal']) ?></td>
                </tr>
                <?php if ($purchase['discount_amount'] > 0): ?>
                    <tr>
                        <td class="text-danger">Supplier Discount:</td>
                        <td class="text-end text-danger">- <?= formatCurrency($purchase['discount_amount']) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($purchase['shipping_cost'] > 0): ?>
                    <tr>
                        <td class="text-muted">Shipping / Bilty Cost:</td>
                        <td class="text-end">+ <?= formatCurrency($purchase['shipping_cost']) ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="table-success border-top border-bottom border-2">
                    <td class="fw-bold fs-5">Grand Total:</td>
                    <td class="text-end fw-bold fs-5 text-success"><?= formatCurrency($purchase['grand_total']) ?></td>
                </tr>
                <tr>
                    <td class="text-success fw-semibold">Amount Paid:</td>
                    <td class="text-end fw-bold text-success"><?= formatCurrency($purchase['paid_amount']) ?></td>
                </tr>
                <?php if ($purchase['due_amount'] > 0): ?>
                    <tr class="table-warning">
                        <td class="text-danger fw-bold">Balance Payable (Due):</td>
                        <td class="text-end fw-bold text-danger"><?= formatCurrency($purchase['due_amount']) ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Signatures -->
    <div class="row mt-5 pt-4 text-center">
        <div class="col-4">
            <div class="border-top pt-2 small text-muted">Stock Receiver / Storekeeper</div>
        </div>
        <div class="col-4"></div>
        <div class="col-4">
            <div class="border-top pt-2 small text-muted">Authorized Signature & Stamp</div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    JsBarcode("#purchaseBarcodeSvg", "<?= htmlspecialchars($purchase['purchase_no']) ?>", {
        format: "CODE128",
        width: <?= $type === 'thermal' ? '1.15' : '1.5' ?>,
        height: <?= $type === 'thermal' ? '30' : '40' ?>,
        fontSize: <?= $type === 'thermal' ? '11' : '13' ?>,
        margin: 0,
        displayValue: true
    });
});
</script>
<script src="assets/js/html2canvas.min.js"></script>
<script>
function triggerShareDialog() {
    var captureTarget = document.querySelector('.thermal-wrapper') || 
                        document.querySelector('.a4-wrapper') || 
                        document.querySelector('.print-container') || 
                        document.querySelector('.report-container') || 
                        document.body;

    // Hide any toolbar or action buttons so they never appear in the receipt image
    var toolbars = document.querySelectorAll('.print-actions, .action-toolbar, .no-print');
    toolbars.forEach(function(el) { el.style.display = 'none'; });

    var originalBg = captureTarget.style.backgroundColor;
    captureTarget.style.backgroundColor = '#ffffff';

    if (window.AndroidPrinter) {
        if (typeof html2canvas !== 'undefined') {
            window.scrollTo(0, 0);
            html2canvas(captureTarget, {
                scale: 2,
                backgroundColor: '#ffffff',
                useCORS: true,
                logging: false,
                windowWidth: captureTarget.scrollWidth,
                windowHeight: captureTarget.scrollHeight,
                scrollX: 0,
                scrollY: 0
            }).then(function(canvas) {
                toolbars.forEach(function(el) { el.style.display = ''; });
                captureTarget.style.backgroundColor = originalBg;

                var base64 = canvas.toDataURL('image/png');
                if (typeof window.AndroidPrinter.shareBase64Image === 'function') {
                    window.AndroidPrinter.shareBase64Image(base64, document.title || 'Receipt');
                } else if (typeof window.AndroidPrinter.sharePageAsImage === 'function') {
                    window.AndroidPrinter.sharePageAsImage(document.title || 'Receipt');
                }
            }).catch(function(err) {
                toolbars.forEach(function(el) { el.style.display = ''; });
                captureTarget.style.backgroundColor = originalBg;
                if (typeof window.AndroidPrinter.sharePageAsImage === 'function') {
                    window.AndroidPrinter.sharePageAsImage(document.title || 'Receipt');
                }
            });
            return;
        } else if (typeof window.AndroidPrinter.sharePageAsImage === 'function') {
            toolbars.forEach(function(el) { el.style.display = ''; });
            window.AndroidPrinter.sharePageAsImage(document.title || 'Receipt');
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
