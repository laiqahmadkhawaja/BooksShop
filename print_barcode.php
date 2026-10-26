<?php
// print_barcode.php - Barcode Label Sticker Print Page
require_once __DIR__ . '/config/app.php';
requireAuth();

$productId = (int)($_GET['id'] ?? 0);
$product = null;
if ($productId > 0) {
    $product = dbFetchOne("SELECT * FROM products WHERE id = ?", [$productId]);
}

if (!$product) {
    setFlash('danger', 'Product not found.');
    header('Location: books.php');
    exit;
}

$shopName = getSetting('shop_name', 'Kitab Ghar');
$barcodeValue = $product['barcode'] ?: $product['product_code'];

$pageTitle = 'Print Barcode Sticker: ' . $product['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center no-print">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-barcode text-primary me-2"></i> Print Barcode Sticker</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="books.php" class="text-decoration-none">Catalog</a></li>
                <li class="breadcrumb-item active" aria-current="page">Barcode</li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end d-flex justify-content-md-end gap-2">
        <a href="books.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Catalog
        </a>
        <button type="button" class="btn btn-primary px-4 fw-bold shadow-sm" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print Sticker
        </button>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-6 text-center">
        <div class="card border-0 shadow-sm p-4">
            <h6 class="text-muted small mb-3 no-print">Sticker Preview:</h6>
            
            <div id="barcodePrintArea" class="border p-3 rounded bg-white shadow-sm d-inline-block text-center" style="min-width: 260px;">
                <div class="small fw-bold text-uppercase"><?= htmlspecialchars($shopName) ?></div>
                <div class="small fw-semibold text-truncate my-1" style="max-width: 250px;"><?= htmlspecialchars($product['title']) ?></div>
                <svg id="barcodeCanvas"></svg>
                <div class="fw-bold fs-6 text-primary mt-1">Rs. <?= number_format($product['retail_price'], 2) ?></div>
            </div>

            <div class="mt-4 no-print d-flex justify-content-center gap-3">
                <button type="button" class="btn btn-dark btn-lg px-4" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Print Sticker (Thermal / Laser)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JsBarcode for Barcode Label Generation -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    try {
        JsBarcode("#barcodeCanvas", "<?= htmlspecialchars($barcodeValue) ?>", {
            format: "CODE128",
            lineColor: "#000",
            width: 2,
            height: 48,
            displayValue: true,
            fontSize: 12
        });
    } catch(e) {
        console.error("Barcode generation error:", e);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
