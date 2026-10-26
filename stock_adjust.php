<?php
// stock_adjust.php - Quick Stock Adjustment Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['adjust_stock_quick'])) {
        $pId = (int)$_POST['product_id'];
        $adjType = $_POST['adjustment_type']; // 'add' or 'subtract'
        $qty = (float)$_POST['quantity'];
        $reason = trim($_POST['reason'] ?? 'Manual Stock Adjustment');

        if ($pId > 0 && $qty > 0) {
            $product = dbFetchOne("SELECT * FROM products WHERE id = ?", [$pId]);
            if ($product) {
                $changeQty = ($adjType === 'add') ? $qty : -$qty;
                $newStock = $product['stock'] + $changeQty;
                dbQuery("UPDATE products SET stock = ? WHERE id = ?", [$newStock, $pId]);
                recordStockMovement($pId, 'adjustment_' . ($adjType === 'add' ? 'in' : 'out'), null, $qty, $reason, $product['purchase_price']);
                setFlash('success', "Stock for '{$product['title']}' updated from {$product['stock']} to $newStock.");
            }
        }
        header("Location: books.php");
        exit;
    }
}

$productId = (int)($_GET['id'] ?? 0);
$selectedProduct = null;
if ($productId > 0) {
    $selectedProduct = dbFetchOne("SELECT * FROM products WHERE id = ?", [$productId]);
}

$allProducts = dbFetchAll("SELECT id, title, product_code, stock, unit, purchase_price, retail_price FROM products ORDER BY title ASC");

$pageTitle = 'Stock Adjustment';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-sliders text-info me-2"></i> Stock Adjustment</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="books.php" class="text-decoration-none">Catalog</a></li>
                <li class="breadcrumb-item active" aria-current="page">Stock Adjustment</li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="books.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Catalog
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-info">
                    <i class="fa-solid fa-arrow-right-arrow-left me-2"></i> Adjust Product Inventory
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="stock_adjust.php">
                    <input type="hidden" name="adjust_stock_quick" value="1">

                    <!-- Product Select -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Product / Book *</label>
                        <select name="product_id" id="product_id" class="form-select form-select-lg" required onchange="onProductSelect(this)">
                            <option value="">-- Choose Product --</option>
                            <?php foreach ($allProducts as $p): ?>
                                <option value="<?= $p['id'] ?>"
                                        data-stock="<?= (float)$p['stock'] ?>"
                                        data-unit="<?= htmlspecialchars($p['unit'] ?: 'Piece') ?>"
                                        <?= ($selectedProduct && $selectedProduct['id'] == $p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['title']) ?> (Code: <?= htmlspecialchars($p['product_code']) ?> | Stock: <?= (float)$p['stock'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Current Stock Info Badge -->
                    <div class="alert alert-light border py-2 mb-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Current System Stock:</span>
                        <span class="fw-bold fs-6 text-primary" id="disp_current_stock">
                            <?= $selectedProduct ? (float)$selectedProduct['stock'] . ' ' . htmlspecialchars($selectedProduct['unit'] ?: 'Piece') : 'Select product' ?>
                        </span>
                    </div>

                    <!-- Adjustment Type -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Adjustment Action *</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="adjustment_type" id="adj_add" value="add" checked>
                                <label class="form-check-label fw-bold text-success" for="adj_add">
                                    <i class="fa-solid fa-plus-circle me-1"></i> Add Stock (Restock / Found / Gift)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="adjustment_type" id="adj_sub" value="subtract">
                                <label class="form-check-label fw-bold text-danger" for="adj_sub">
                                    <i class="fa-solid fa-minus-circle me-1"></i> Reduce Stock (Damaged / Lost)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Quantity -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Adjustment Quantity *</label>
                        <input type="number" step="1" min="1" name="quantity" id="quantity" class="form-control form-control-lg" required placeholder="Enter quantity (e.g. 10)">
                    </div>

                    <!-- Reason -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Reason / Audit Remark *</label>
                        <select name="reason" class="form-select mb-2" onchange="if(this.value==='Other'){document.getElementById('custom_reason').classList.remove('d-none');}else{document.getElementById('custom_reason').classList.add('d-none');}">
                            <option value="Physical Audit Discrepancy Correction">Physical Audit Discrepancy Correction</option>
                            <option value="Damaged / Shelf Wear Books Removed">Damaged / Shelf Wear Books Removed</option>
                            <option value="Initial Opening Stock Correction">Initial Opening Stock Correction</option>
                            <option value="Sample / Display Copy">Sample / Display Copy</option>
                            <option value="Returned from School Syllabus">Returned from School Syllabus</option>
                            <option value="Other">Other (Type below)...</option>
                        </select>
                        <input type="text" id="custom_reason" class="form-control d-none" placeholder="Enter custom reason...">
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="books.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-info text-white px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-check me-1"></i> Apply Adjustment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function onProductSelect(sel) {
    const opt = sel.options[sel.selectedIndex];
    const disp = document.getElementById('disp_current_stock');
    if (opt && opt.value) {
        disp.textContent = opt.getAttribute('data-stock') + ' ' + opt.getAttribute('data-unit');
    } else {
        disp.textContent = 'Select product';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
