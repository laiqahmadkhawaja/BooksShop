<?php
// stock_adjust.php - Quick Stock Adjustment Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();

$warehouses = getWarehouses(true);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (isset($_POST['adjust_stock_quick'])) {
        $pId = (int)$_POST['product_id'];
        $adjType = $_POST['adjustment_type']; // 'add' or 'subtract'
        $qty = (float)$_POST['quantity'];
        $reason = trim($_POST['reason'] ?? 'Manual Stock Adjustment');
        $targetLoc = $_POST['target_location'] ?? 'shop:0';

        $locParts = explode(':', $targetLoc);
        $locType = ($locParts[0] === 'warehouse') ? 'warehouse' : 'shop';
        $whId = ($locType === 'warehouse') ? (int)($locParts[1] ?? 0) : 0;

        if ($pId > 0 && $qty > 0) {
            $product = dbFetchOne("SELECT * FROM products WHERE id = ?", [$pId]);
            if ($product) {
                $changeQty = ($adjType === 'add') ? $qty : -$qty;

                if ($locType === 'warehouse' && $whId > 0) {
                    $wh = dbFetchOne("SELECT name FROM warehouses WHERE id = ?", [$whId]);
                    $whName = $wh ? $wh['name'] : 'Warehouse';
                    $mType = ($adjType === 'add') ? 'adjustment_add' : 'adjustment_deduct';
                    recordWarehouseStockMovement($whId, $pId, $mType, null, 'ADJUST-' . date('Ymd'), $changeQty, $reason, $product['purchase_price']);
                    setFlash('success', "Stock for '{$product['title']}' at $whName adjusted successfully.");
                } else {
                    $newStock = $product['current_stock'] + $changeQty;
                    recordStockMovement($pId, 'adjustment_' . ($adjType === 'add' ? 'in' : 'out'), null, $changeQty, $reason, $product['purchase_price']);
                    setFlash('success', "Shop Counter stock for '{$product['title']}' updated from {$product['current_stock']} to $newStock.");
                }
            }
        }
        if ($locType === 'warehouse' && $whId > 0) {
            header("Location: warehouse_view.php?id=$whId&tab=stock");
        } else {
            header("Location: inventory.php");
        }
        exit;
    }
}

$productId = (int)($_GET['id'] ?? ($_GET['product_id'] ?? 0));
$warehouseId = (int)($_GET['warehouse_id'] ?? 0);
$selectedLocation = $_GET['location'] ?? ($warehouseId > 0 ? "warehouse:$warehouseId" : 'shop:0');

$selectedProduct = null;
if ($productId > 0) {
    $selectedProduct = dbFetchOne("SELECT * FROM products WHERE id = ?", [$productId]);
}

$backUrl = ($warehouseId > 0) ? "warehouse_view.php?id=$warehouseId&tab=stock" : "inventory.php";
$backTitle = ($warehouseId > 0) ? "Back to Warehouse" : "Back to Inventory";

$allProducts = dbFetchAll("SELECT id, title, product_code, current_stock, unit, purchase_price, retail_price FROM products WHERE status = 1 ORDER BY title ASC");
$allWhStockRaw = dbFetchAll("SELECT warehouse_id, product_id, quantity FROM warehouse_stock");

$whStockMap = [];
foreach ($allWhStockRaw as $ws) {
    $whStockMap[$ws['warehouse_id']][$ws['product_id']] = (float)$ws['quantity'];
}

$pageTitle = 'Stock Adjustment';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-sliders text-info me-2"></i> Stock Adjustment</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="inventory.php" class="text-decoration-none">Inventory</a></li>
                <li class="breadcrumb-item active" aria-current="page">Stock Adjustment</li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> <?= htmlspecialchars($backTitle) ?>
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-info">
                    <i class="fa-solid fa-arrow-right-arrow-left me-2"></i> Adjust Physical Inventory
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="stock_adjust.php">
                    <input type="hidden" name="adjust_stock_quick" value="1">

                    <!-- Target Location -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Location to Adjust *</label>
                        <select name="target_location" id="target_location" class="form-select form-select-lg" required onchange="updateStockDisplay()">
                            <option value="shop:0" <?= $selectedLocation === 'shop:0' ? 'selected' : '' ?>>🏬 Main Shop / Storefront Counter</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="warehouse:<?= $wh['id'] ?>" <?= $selectedLocation === "warehouse:{$wh['id']}" ? 'selected' : '' ?>>🏭 <?= htmlspecialchars($wh['name']) ?> (<?= htmlspecialchars($wh['warehouse_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Product Select -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Product *</label>
                        <select name="product_id" id="product_id" class="form-select form-select-lg" required onchange="updateStockDisplay()">
                            <option value="">-- Choose Product --</option>
                            <?php foreach ($allProducts as $p): ?>
                                <option value="<?= $p['id'] ?>"
                                        data-unit="<?= htmlspecialchars($p['unit'] ?: 'Piece') ?>"
                                        data-shop-stock="<?= (float)$p['current_stock'] ?>"
                                        <?= ($selectedProduct && $selectedProduct['id'] == $p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['title']) ?> (Code: <?= htmlspecialchars($p['product_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Current Stock Info Badge -->
                    <div class="alert alert-light border py-2 mb-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Current On-Hand Stock:</span>
                        <span class="fw-bold fs-6 text-primary" id="disp_current_stock">
                            <?= $selectedProduct ? (float)$selectedProduct['current_stock'] . ' ' . htmlspecialchars($selectedProduct['unit'] ?: 'Piece') : 'Select product' ?>
                        </span>
                    </div>

                    <!-- Adjustment Type -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Adjustment Action *</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="adjustment_type" id="adj_add" value="add" checked>
                                <label class="form-check-label fw-bold text-success" for="adj_add">
                                    <i class="fa-solid fa-plus-circle me-1"></i> Add Stock (Surplus / Found / Gift)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="adjustment_type" id="adj_sub" value="subtract">
                                <label class="form-check-label fw-bold text-danger" for="adj_sub">
                                    <i class="fa-solid fa-minus-circle me-1"></i> Reduce Stock (Damaged / Missing / Audit)
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
                        <label class="form-label small fw-bold">Adjustment Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g. Broken packaging, Water damage, Physical count discrepancy">
                    </div>

                    <!-- Buttons -->
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="fa-solid fa-check me-1"></i> Apply Adjustment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const WAREHOUSE_STOCKS = <?= json_encode($whStockMap) ?>;

function updateStockDisplay() {
    const pSelect = document.getElementById('product_id');
    const locSelect = document.getElementById('target_location');
    const disp = document.getElementById('disp_current_stock');

    const opt = pSelect.options[pSelect.selectedIndex];
    if (!opt || !opt.value) {
        disp.innerText = 'Select product';
        return;
    }

    const prodId = parseInt(opt.value);
    const unit = opt.getAttribute('data-unit') || 'Piece';
    const locVal = locSelect.value;
    const parts = locVal.split(':');
    const locType = parts[0];
    const whId = parseInt(parts[1] || 0);

    let stock = 0;
    if (locType === 'shop') {
        stock = parseFloat(opt.getAttribute('data-shop-stock') || 0);
    } else {
        if (WAREHOUSE_STOCKS[whId] && WAREHOUSE_STOCKS[whId][prodId] !== undefined) {
            stock = parseFloat(WAREHOUSE_STOCKS[whId][prodId]);
        }
    }

    disp.innerText = stock.toLocaleString() + ' ' + unit;
}

document.addEventListener('DOMContentLoaded', () => {
    updateStockDisplay();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
