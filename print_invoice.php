<?php
// print_invoice.php - Dual Mode Invoice Printer (80mm Thermal & Professional A4)
require_once __DIR__ . '/config/app.php';
requireAuth();

$saleId = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'thermal'; // 'thermal' or 'a4'

if (!$saleId) {
    die("Invoice ID is missing.");
}

$sale = dbFetchOne("SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address, 
                           c.current_balance as customer_total_due, u.full_name as cashier_name, a.account_name
                    FROM sales s
                    LEFT JOIN customers c ON c.id = s.customer_id
                    LEFT JOIN users u ON u.id = s.created_by
                    LEFT JOIN accounts a ON a.id = s.payment_account_id
                    WHERE s.id = ?", [$saleId]);

if (!$sale) {
    die("Invoice not found.");
}

$items = dbFetchAll("SELECT si.*, p.title, p.product_code, p.barcode, p.unit, p.class_grade
                     FROM sale_items si
                     JOIN products p ON p.id = si.product_id
                     WHERE si.sale_id = ?", [$saleId]);

$shopName = getSetting('shop_name', 'Kitab Ghar & Stationery');
$shopNameUrdu = getSetting('shop_name_urdu', 'کتاب گھر اینڈ اسٹیشنری مارٹ');
$shopTagline = getSetting('shop_tagline', 'Complete Textbooks, General Books & Quality Stationery');
$shopAddress = getSetting('shop_address', 'Shop # 14-18, Urdu Bazaar, Lahore, Pakistan');
$shopPhone = getSetting('shop_phone', '+92 300 1234567 / 042-37210000');
$receiptFooter = getSetting('receipt_footer', 'Thank you for shopping with us! Books once sold cannot be returned without receipt.');
$receiptFooterUrdu = getSetting('receipt_footer_urdu', 'خریداری کا شکریہ! خریدی گئی کتب 7 دن کے اندر اصل رسید کے ہمراہ تبدیل کی جا سکتی ہیں۔');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($sale['invoice_no']) ?> - <?= htmlspecialchars($shopName) ?></title>
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
        #receiptBarcodeSvg {
            max-width: 100% !important;
            height: auto !important;
            margin: 0 auto;
            display: inline-block;
        }

        /* 80mm Thermal Receipt Styling */
        <?php if ($type === 'thermal'): ?>
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
        /* Professional A4 Invoice Styling */
        .a4-wrapper {
            max-width: 850px;
            margin: 30px auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .invoice-title {
            font-size: 28px;
            font-weight: 800;
            color: #1e3c72;
            letter-spacing: 1px;
        }
        .table-a4 th {
            background-color: #f8f9fa;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .a4-wrapper {
                max-width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
        <?php endif; ?>
    </style>
</head>
<body>

<!-- Floating Control Bar -->
<div class="no-print py-2 bg-dark text-white text-center sticky-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <strong><?= htmlspecialchars($sale['invoice_no']) ?></strong> &bull; 
            Mode: <?= strtoupper($type) ?>
        </div>
        <div class="btn-group btn-group-sm">
            <a href="print_invoice.php?id=<?= $saleId ?>&type=thermal" class="btn btn-<?= $type === 'thermal' ? 'primary' : 'outline-light' ?>">
                <i class="fa-solid fa-receipt me-1"></i> Thermal 80mm
            </a>
            <a href="print_invoice.php?id=<?= $saleId ?>&type=a4" class="btn btn-<?= $type === 'a4' ? 'primary' : 'outline-light' ?>">
                <i class="fa-solid fa-file-invoice me-1"></i> A4 Invoice
            </a>
            <button onclick="window.print()" class="btn btn-success fw-bold"><i class="fa-solid fa-print me-1"></i> Print / Share</button><button type="button" onclick="triggerShareDialog()" class="btn btn-outline-light fw-semibold ms-1"><i class="fa-solid fa-share-nodes me-1"></i> Share</button>
            <a href="pos.php" class="btn btn-outline-warning">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to POS
            </a>
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
    </div>

    <!-- Bill Metadata -->
    <div class="d-flex justify-content-between small">
        <span>Invoice #: <strong><?= htmlspecialchars($sale['invoice_no']) ?></strong></span>
        <span><?= date('d-m-Y h:i A', strtotime($sale['sale_date'])) ?></span>
    </div>
    <div class="d-flex justify-content-between small mb-2">
        <span>Customer: <strong><?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in') ?></strong></span>
        <span>Cashier: <?= htmlspecialchars($sale['cashier_name'] ?: 'Counter') ?></span>
    </div>

    <!-- Items Table -->
    <table class="receipt-table">
        <thead>
            <tr style="border-bottom: 1px dashed #333;">
                <th style="width: 50%;">Item</th>
                <th style="width: 15%; text-align: center;">Qty</th>
                <th style="width: 15%; text-align: right;">Rate</th>
                <th style="width: 20%; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($item['title']) ?></div>
                        <?php if ($item['class_grade']): ?>
                            <small>(<?= htmlspecialchars($item['class_grade']) ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;"><?= (float)$item['quantity'] ?></td>
                    <td style="text-align: right;"><?= number_format($item['unit_price'], 2) ?></td>
                    <td style="text-align: right; font-weight: bold;"><?= number_format($item['subtotal'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals Section -->
    <div class="border-dashed-y">
        <div class="d-flex justify-content-between">
            <span>Subtotal:</span>
            <span>Rs. <?= number_format($sale['subtotal'], 2) ?></span>
        </div>
        <?php if ($sale['discount_amount'] > 0): ?>
            <div class="d-flex justify-content-between text-danger">
                <span>Discount:</span>
                <span>- Rs. <?= number_format($sale['discount_amount'], 2) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($sale['tax_amount'] > 0): ?>
            <div class="d-flex justify-content-between">
                <span>Tax:</span>
                <span>Rs. <?= number_format($sale['tax_amount'], 2) ?></span>
            </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between fs-6 fw-bold mt-1 pt-1" style="border-top: 1px dashed #444;">
            <span>GRAND TOTAL:</span>
            <span>Rs. <?= number_format($sale['grand_total'], 2) ?></span>
        </div>
    </div>

    <!-- Payment Breakdown -->
    <div class="small">
        <div class="d-flex justify-content-between">
            <span>Payment Method:</span>
            <span class="text-uppercase fw-bold"><?= htmlspecialchars($sale['payment_method']) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>Paid Amount:</span>
            <span>Rs. <?= number_format($sale['paid_amount'], 2) ?></span>
        </div>
        <?php if ($sale['due_amount'] > 0): ?>
            <div class="d-flex justify-content-between fw-bold text-danger">
                <span>Due / Khata Balance:</span>
                <span>Rs. <?= number_format($sale['due_amount'], 2) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Barcode & Footer -->
    <div class="text-center mt-3 pt-2" style="border-top: 1px dashed #333;">
        <div class="d-flex justify-content-center my-2" style="overflow: hidden;">
            <svg id="receiptBarcodeSvg"></svg>
        </div>
        <div class="small mt-1 text-muted" style="font-size: 11px;"><?= htmlspecialchars($receiptFooter) ?></div>
        <div class="font-urdu mt-2 text-center" style="font-size: 11px; line-height: 2.3 !important; direction: rtl;"><?= htmlspecialchars($receiptFooterUrdu) ?></div>
        <div class="small text-muted mt-2" style="font-size: 9px; opacity: 0.7;">Software by Kitab Ghar POS System</div>
    </div>
</div>

<?php else: ?>
<!-- ================= PROFESSIONAL A4 INVOICE ================= -->
<div class="a4-wrapper">
    <!-- Header -->
    <div class="row align-items-center mb-4 pb-3 border-bottom">
        <div class="col-sm-7">
            <h3 class="fw-bold text-primary mb-1"><?= htmlspecialchars($shopName) ?></h3>
            <div class="font-urdu fs-6 text-secondary"><?= htmlspecialchars($shopNameUrdu) ?></div>
            <div class="text-muted small"><?= htmlspecialchars($shopTagline) ?></div>
            <div class="small mt-1"><i class="fa-solid fa-location-dot me-1 text-danger"></i> <?= htmlspecialchars($shopAddress) ?></div>
            <div class="small"><i class="fa-solid fa-phone me-1 text-success"></i> <?= htmlspecialchars($shopPhone) ?></div>
        </div>
        <div class="col-sm-5 text-sm-end mt-3 mt-sm-0">
            <div class="invoice-title">SALES INVOICE</div>
            <div class="fw-bold fs-5 text-dark"><?= htmlspecialchars($sale['invoice_no']) ?></div>
            <div class="small text-muted">Date: <?= date('d M, Y - h:i A', strtotime($sale['sale_date'])) ?></div>
            <div class="mt-2">
                <svg id="receiptBarcodeSvg"></svg>
            </div>
        </div>
    </div>

    <!-- Bill To / Customer Information -->
    <div class="row mb-4">
        <div class="col-sm-6">
            <h6 class="fw-bold text-uppercase small text-secondary">Billed To:</h6>
            <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in Customer (Cash)') ?></h5>
            <?php if ($sale['customer_phone']): ?>
                <div class="small"><i class="fa-solid fa-phone me-1 text-muted"></i> <?= htmlspecialchars($sale['customer_phone']) ?></div>
            <?php endif; ?>
            <?php if ($sale['customer_address']): ?>
                <div class="small"><i class="fa-solid fa-location-dot me-1 text-muted"></i> <?= htmlspecialchars($sale['customer_address']) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-sm-6 text-sm-end">
            <h6 class="fw-bold text-uppercase small text-secondary">Payment & Order Info:</h6>
            <div class="small"><strong>Payment Status:</strong> 
                <span class="badge bg-<?= $sale['payment_status'] === 'paid' ? 'success' : ($sale['payment_status'] === 'partial' ? 'warning text-dark' : 'danger') ?>">
                    <?= strtoupper($sale['payment_status']) ?>
                </span>
            </div>
            <div class="small"><strong>Payment Method:</strong> <?= strtoupper($sale['payment_method']) ?></div>
            <div class="small"><strong>Billed By:</strong> <?= htmlspecialchars($sale['cashier_name'] ?: 'Counter Staff') ?></div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-a4 align-middle">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 50%;">Item / Book Description</th>
                    <th style="width: 15%;" class="text-center">Rate</th>
                    <th style="width: 12%;" class="text-center">Qty</th>
                    <th style="width: 18%;" class="text-end">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($items as $item): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($item['title']) ?></div>
                            <div class="text-muted small">
                                Code: <?= htmlspecialchars($item['product_code']) ?>
                                <?php if ($item['class_grade']): ?>
                                    &bull; Class: <?= htmlspecialchars($item['class_grade']) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-center"><?= formatCurrency($item['unit_price']) ?></td>
                        <td class="text-center fw-semibold"><?= (float)$item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?></td>
                        <td class="text-end fw-bold"><?= formatCurrency($item['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary & Totals -->
    <div class="row align-items-start">
        <div class="col-sm-6">
            <div class="p-3 bg-light rounded border">
                <h6 class="fw-bold small mb-2"><i class="fa-solid fa-circle-info me-1 text-primary"></i> Terms & Return Policy:</h6>
                <p class="small text-muted mb-1"><?= htmlspecialchars($receiptFooter) ?></p>
                <p class="small text-muted font-urdu mb-0"><?= htmlspecialchars($receiptFooterUrdu) ?></p>
            </div>
        </div>
        <div class="col-sm-6">
            <table class="table table-sm border-0">
                <tr>
                    <td class="text-muted">Subtotal:</td>
                    <td class="text-end fw-semibold"><?= formatCurrency($sale['subtotal']) ?></td>
                </tr>
                <?php if ($sale['discount_amount'] > 0): ?>
                    <tr>
                        <td class="text-danger">Order Discount:</td>
                        <td class="text-end text-danger">- <?= formatCurrency($sale['discount_amount']) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($sale['tax_amount'] > 0): ?>
                    <tr>
                        <td class="text-muted">Tax:</td>
                        <td class="text-end"><?= formatCurrency($sale['tax_amount']) ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="table-primary border-top border-bottom border-2">
                    <td class="fw-bold fs-5">Grand Total:</td>
                    <td class="text-end fw-bold fs-5 text-primary"><?= formatCurrency($sale['grand_total']) ?></td>
                </tr>
                <tr>
                    <td class="text-success fw-semibold">Amount Paid:</td>
                    <td class="text-end fw-bold text-success"><?= formatCurrency($sale['paid_amount']) ?></td>
                </tr>
                <?php if ($sale['due_amount'] > 0): ?>
                    <tr class="table-warning">
                        <td class="text-danger fw-bold">Due Balance:</td>
                        <td class="text-end fw-bold text-danger"><?= formatCurrency($sale['due_amount']) ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Signatures -->
    <div class="row mt-5 pt-4 text-center">
        <div class="col-4">
            <div class="border-top pt-2 small text-muted">Customer Signature</div>
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
    JsBarcode("#receiptBarcodeSvg", "<?= htmlspecialchars($sale['invoice_no']) ?>", {
        format: "CODE128",
        width: <?= $type === 'thermal' ? '1.15' : '1.5' ?>,
        height: <?= $type === 'thermal' ? '30' : '40' ?>,
        fontSize: <?= $type === 'thermal' ? '11' : '13' ?>,
        margin: 0,
        displayValue: true
    });
});
</script>
<script>
function triggerShareDialog() {
    if (window.AndroidPrinter && typeof window.AndroidPrinter.openShareDialog === 'function') {
        window.AndroidPrinter.openShareDialog(window.location.href, document.title, "");
    } else if (navigator.share) {
        navigator.share({ title: document.title, url: window.location.href }).catch(function(){});
    } else {
        window.print();
    }
}
</script>
</body>
</html>
