<?php
// print_invoice.php - Dual Mode Invoice Printer (80mm Thermal & Professional A4)
require_once __DIR__ . '/config/app.php';
requireAuth();
requirePagePermission('sales.php');

$saleId = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'thermal'; // 'thermal' or 'a4'
$thermalSize = $_GET['size'] ?? ($_GET['paper'] ?? getSetting('thermal_paper_size', '80'));
$thermalSize = ($thermalSize === '58') ? '58' : '80';
$printableWidth = ($thermalSize === '58') ? '48mm' : '72mm';
$rollWidth = ($thermalSize === '58') ? '58mm' : '80mm';

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

$shopName = getSetting('shop_name', 'Retail & Wholesale Mart');
$shopNameUrdu = getSetting('shop_name_urdu', 'ریٹیل اینڈ ہول سیل مارٹ');
$shopTagline = getSetting('shop_tagline', 'Complete Retail & Wholesale Inventory POS Solution');
$shopAddress = getSetting('shop_address', 'Shop # 14-18, Urdu Bazaar, Lahore, Pakistan');
$shopPhone = getSetting('shop_phone', '+92 300 1234567 / 042-37210000');
$receiptFooter = getSetting('receipt_footer', 'Thank you for shopping with us! Items once sold cannot be returned without receipt.');
$receiptFooterUrdu = getSetting('receipt_footer_urdu', 'خریداری کا شکریہ! اشیاء 7 دن کے اندر اصل رسید کے ساتھ تبدیل کی جا سکتی ہیں۔');
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

        /* ========================================================
           THERMAL PRINT OPTIMIZATION RULES (58mm & 80mm)
           1. Monoline Sans-Serif Fonts: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, sans-serif
           2. Uniform Bold Weight: font-weight: 700 !important for solid 2-3px burn thickness
           3. Hierarchy via Font Size Only
           4. Pure #000000 Black: no dithering/grays/reds
           5. Disabled Gray Fringe Anti-Aliasing
           6. Exact Roll Widths: 58mm (48mm printable) & 80mm (72mm printable)
           ======================================================== */
        <?php if ($type === 'thermal'): ?>
        @page {
            size: <?= $rollWidth ?> auto;
            margin: 0;
        }
        html, body {
            background-color: #ffffff !important;
            color: #000000 !important;
            margin: 0 !important;
            padding: 0 !important;
            font-family: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, sans-serif !important;
            font-weight: 700 !important;
            -webkit-font-smoothing: antialiased !important;
            -moz-osx-font-smoothing: grayscale !important;
            text-rendering: geometricPrecision !important;
        }
        .thermal-wrapper {
            width: <?= $printableWidth ?> !important;
            max-width: <?= $printableWidth ?> !important;
            margin: 10px auto;
            background: #ffffff !important;
            padding: <?= ($thermalSize === '58') ? '2mm 1mm' : '4mm 2mm' ?>;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            font-family: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, sans-serif !important;
            font-size: <?= ($thermalSize === '58') ? '10px' : '12px' ?>;
            line-height: 1.3;
            color: #000000 !important;
            font-weight: 700 !important;
            -webkit-font-smoothing: antialiased !important;
            -moz-osx-font-smoothing: grayscale !important;
            text-rendering: geometricPrecision !important;
        }
        .thermal-wrapper * {
            font-family: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, sans-serif !important;
            font-weight: 700 !important;
            color: #000000 !important;
            border-color: #000000 !important;
            background-color: transparent !important;
            -webkit-font-smoothing: antialiased !important;
            -moz-osx-font-smoothing: grayscale !important;
            text-rendering: geometricPrecision !important;
            box-shadow: none !important;
            text-shadow: none !important;
        }
        .thermal-wrapper .font-urdu {
            font-family: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, 'Noto Nastaliq Urdu', sans-serif !important;
            font-weight: 700 !important;
            line-height: 1.6 !important;
            direction: rtl;
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #000000 !important;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }
        .receipt-header .shop-title {
            font-size: <?= ($thermalSize === '58') ? '15px' : '19px' ?>;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
        }
        .receipt-header .shop-urdu {
            font-size: <?= ($thermalSize === '58') ? '12px' : '14px' ?>;
            margin-bottom: 2px;
        }
        .receipt-header .shop-meta {
            font-size: <?= ($thermalSize === '58') ? '9px' : '11px' ?>;
        }
        .bill-info {
            font-size: <?= ($thermalSize === '58') ? '9.5px' : '11.5px' ?>;
            margin-bottom: 4px;
        }
        .receipt-table {
            width: 100%;
            margin-bottom: 4px;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .receipt-table th, .receipt-table td {
            padding: 2px 0;
            vertical-align: top;
            font-size: <?= ($thermalSize === '58') ? '9.5px' : '11.5px' ?>;
            font-weight: 700 !important;
            word-break: break-word;
        }
        .receipt-table thead tr {
            border-bottom: 1px dashed #000000 !important;
            border-top: 1px dashed #000000 !important;
        }
        .receipt-table th {
            font-size: <?= ($thermalSize === '58') ? '10px' : '12px' ?>;
            padding: 3px 0;
        }
        .totals-section {
            border-top: 1px dashed #000000 !important;
            border-bottom: 1px dashed #000000 !important;
            padding: 4px 0;
            margin: 4px 0;
            font-size: <?= ($thermalSize === '58') ? '10.5px' : '12.5px' ?>;
        }
        .grand-total-row {
            font-size: <?= ($thermalSize === '58') ? '14px' : '18px' ?> !important;
            border-top: 2px solid #000000 !important;
            border-bottom: 2px solid #000000 !important;
            padding: 3px 0;
            margin-top: 3px;
        }
        .payment-section {
            font-size: <?= ($thermalSize === '58') ? '9.5px' : '11.5px' ?>;
            margin-top: 4px;
            padding-bottom: 4px;
            border-bottom: 1px dashed #000000 !important;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 5px;
            padding-top: 2px;
        }
        .receipt-footer .footer-note {
            font-size: <?= ($thermalSize === '58') ? '8.5px' : '10.5px' ?>;
            margin-top: 3px;
            line-height: 1.25;
        }
        .receipt-footer .footer-urdu {
            font-size: <?= ($thermalSize === '58') ? '9px' : '11px' ?>;
            margin-top: 3px;
        }
        .receipt-footer .footer-system {
            font-size: <?= ($thermalSize === '58') ? '7.5px' : '9px' ?>;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }
        @media print {
            body { 
                background: #ffffff !important; 
                margin: 0 !important; 
                padding: 0 !important; 
            }
            .no-print { 
                display: none !important; 
            }
            .thermal-wrapper {
                width: <?= $printableWidth ?> !important;
                max-width: <?= $printableWidth ?> !important;
                margin: 0 auto !important;
                padding: <?= ($thermalSize === '58') ? '1mm 0' : '2mm 0' ?> !important;
                box-shadow: none !important;
                border: none !important;
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
            <a href="print_invoice.php?id=<?= $saleId ?>&type=thermal&size=80" class="btn btn-<?= ($type === 'thermal' && $thermalSize === '80') ? 'primary fw-bold' : 'outline-light' ?>" title="80mm Roll (Printable width: 72mm)">
                <i class="fa-solid fa-receipt me-1"></i> Thermal 80mm (72mm)
            </a>
            <a href="print_invoice.php?id=<?= $saleId ?>&type=thermal&size=58" class="btn btn-<?= ($type === 'thermal' && $thermalSize === '58') ? 'primary fw-bold' : 'outline-light' ?>" title="58mm Roll (Printable width: 48mm)">
                <i class="fa-solid fa-receipt me-1"></i> Thermal 58mm (48mm)
            </a>
            <a href="print_invoice.php?id=<?= $saleId ?>&type=a4" class="btn btn-<?= $type === 'a4' ? 'primary fw-bold' : 'outline-light' ?>">
                <i class="fa-solid fa-file-invoice me-1"></i> A4 Invoice
            </a>
            <button type="button" onclick="triggerShareDialog()" class="btn btn-success fw-bold"><i class="fa-solid fa-print me-1"></i> Print / Share</button><button type="button" onclick="triggerShareDialog()" class="btn btn-outline-light fw-semibold ms-1"><i class="fa-solid fa-share-nodes me-1"></i> Share</button>
            <a href="pos.php" class="btn btn-outline-warning">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to POS
            </a>
        </div>
    </div>
</div>

<?php if ($type === 'thermal'): ?>
<!-- ================= THERMAL RECEIPT (<?= $thermalSize ?>mm / Printable: <?= $printableWidth ?>) ================= -->
<div class="thermal-wrapper">
    <div class="receipt-header">
        <div class="shop-title"><?= htmlspecialchars($shopName) ?></div>
        <?php if (!empty($shopNameUrdu)): ?>
            <div class="font-urdu shop-urdu"><?= htmlspecialchars($shopNameUrdu) ?></div>
        <?php endif; ?>
        <?php if (!empty($shopTagline)): ?>
            <div class="shop-meta"><?= htmlspecialchars($shopTagline) ?></div>
        <?php endif; ?>
        <div class="shop-meta"><?= htmlspecialchars($shopAddress) ?></div>
        <div class="shop-meta">TEL: <?= htmlspecialchars($shopPhone) ?></div>
    </div>

    <!-- Bill Metadata -->
    <div class="bill-info">
        <div class="d-flex justify-content-between">
            <span>INV: <?= htmlspecialchars($sale['invoice_no']) ?></span>
            <span><?= date('d/m/Y h:i A', strtotime($sale['sale_date'])) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>CUST: <?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in') ?></span>
            <span>OP: <?= htmlspecialchars($sale['cashier_name'] ?: 'Counter') ?></span>
        </div>
    </div>

    <!-- Items Table -->
    <table class="receipt-table">
        <thead>
            <tr>
                <th style="width: 48%; text-align: left;">ITEM</th>
                <th style="width: 14%; text-align: center;">QTY</th>
                <th style="width: 18%; text-align: right;">RATE</th>
                <th style="width: 20%; text-align: right;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <div><?= htmlspecialchars($item['title']) ?></div>
                        <?php if ($item['class_grade']): ?>
                            <div style="font-size: 0.85em;">(<?= htmlspecialchars($item['class_grade']) ?>)</div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;"><?= (float)$item['quantity'] ?></td>
                    <td style="text-align: right;"><?= number_format($item['unit_price'], 2) ?></td>
                    <td style="text-align: right;"><?= number_format($item['subtotal'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals Section -->
    <div class="totals-section">
        <div class="d-flex justify-content-between">
            <span>SUBTOTAL:</span>
            <span>Rs. <?= number_format($sale['subtotal'], 2) ?></span>
        </div>
        <?php if ($sale['discount_amount'] > 0): ?>
            <div class="d-flex justify-content-between">
                <span>DISCOUNT:</span>
                <span>- Rs. <?= number_format($sale['discount_amount'], 2) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($sale['tax_amount'] > 0): ?>
            <div class="d-flex justify-content-between">
                <span>TAX:</span>
                <span>Rs. <?= number_format($sale['tax_amount'], 2) ?></span>
            </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between grand-total-row">
            <span>NET TOTAL:</span>
            <span>Rs. <?= number_format($sale['grand_total'], 2) ?></span>
        </div>
    </div>

    <!-- Payment Breakdown -->
    <div class="payment-section">
        <div class="d-flex justify-content-between">
            <span>PAYMENT (<?= strtoupper($sale['payment_method']) ?>):</span>
            <span>Rs. <?= number_format($sale['paid_amount'], 2) ?></span>
        </div>
        <?php if ($sale['due_amount'] > 0): ?>
            <div class="d-flex justify-content-between">
                <span>DUE / KHATA:</span>
                <span>Rs. <?= number_format($sale['due_amount'], 2) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Barcode & Footer -->
    <div class="receipt-footer">
        <div class="d-flex justify-content-center my-1" style="overflow: hidden;">
            <svg id="receiptBarcodeSvg"></svg>
        </div>
        <div class="footer-note"><?= htmlspecialchars($receiptFooter) ?></div>
        <?php if (!empty($receiptFooterUrdu)): ?>
            <div class="font-urdu footer-urdu"><?= htmlspecialchars($receiptFooterUrdu) ?></div>
        <?php endif; ?>
        <div class="footer-system">*** THANK YOU FOR YOUR BUSINESS ***</div>
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
                    <th style="width: 50%;">Product / Item Description</th>
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
        width: <?= ($type === 'thermal' && $thermalSize === '58') ? '1.0' : ($type === 'thermal' ? '1.25' : '1.5') ?>,
        height: <?= ($type === 'thermal' && $thermalSize === '58') ? '24' : ($type === 'thermal' ? '32' : '40') ?>,
        fontSize: <?= ($type === 'thermal' && $thermalSize === '58') ? '9' : ($type === 'thermal' ? '11' : '13') ?>,
        lineColor: "#000000",
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
