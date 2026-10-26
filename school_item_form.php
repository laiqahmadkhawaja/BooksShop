<?php
// school_item_form.php - Dedicated page for Adding an Item to Class Syllabus Package
require_once __DIR__ . '/config/app.php';
requireAuth();

$classId = (int)($_GET['class_id'] ?? 0);
if ($classId <= 0) {
    setFlash('danger', 'Please select a class first.');
    redirect('schools.php');
}

$currentClass = dbFetchOne("SELECT sc.*, s.name as school_name 
                            FROM school_classes sc 
                            JOIN schools s ON s.id = sc.school_id 
                            WHERE sc.id = ?", [$classId]);

if (!$currentClass) {
    setFlash('danger', 'Class package not found.');
    redirect('schools.php');
}

// Fetch all active products
$allProducts = dbFetchAll("SELECT id, title, product_code, barcode, product_type, retail_price, current_stock 
                           FROM products 
                           WHERE status = 1 
                           ORDER BY title ASC");

$pageTitle = 'Add Item to Package - ' . htmlspecialchars($currentClass['class_name']);
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-md-9">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <a href="schools.php?class_id=<?= $classId ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to <?= htmlspecialchars($currentClass['class_name']) ?>
                </a>
                <h4 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-plus-circle text-primary me-2"></i> Add Item to Package
                </h4>
                <div class="text-muted small">
                    <?= htmlspecialchars($currentClass['school_name']) ?> &bull; <strong><?= htmlspecialchars($currentClass['class_name']) ?></strong> (<?= htmlspecialchars($currentClass['academic_session']) ?>)
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="schools.php">
                    <input type="hidden" name="add_class_item" value="1">
                    <input type="hidden" name="school_class_id" value="<?= $classId ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="syllabusProductSelect" class="form-select form-select-lg" required>
                            <option value="">-- Choose from Inventory --</option>
                            <?php foreach ($allProducts as $p): ?>
                                <option value="<?= $p['id'] ?>" data-price="<?= $p['retail_price'] ?>">
                                    <?= htmlspecialchars($p['title']) ?> (<?= htmlspecialchars($p['product_code']) ?>) - Rs. <?= number_format($p['retail_price'], 2) ?> [Stock: <?= (float)$p['current_stock'] ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Item not in list? <a href="product_form.php" target="_blank">Add new product first</a></small>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Quantity Needed / Student <span class="text-danger">*</span></label>
                            <input type="number" step="1" min="1" name="quantity_needed" class="form-control form-control-lg" value="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Special Package Price (Optional)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" step="0.01" min="0" name="custom_price" id="customPriceInput" class="form-control" placeholder="Default retail price">
                            </div>
                            <small class="text-muted">Leave empty to use normal retail price</small>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="is_mandatory" id="isMandatoryCheck" value="1" checked>
                        <label class="form-check-label fw-bold" for="isMandatoryCheck">
                            Mandatory item in package (auto-checked at POS)
                        </label>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="schools.php?class_id=<?= $classId ?>" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fa-solid fa-plus me-1"></i> Add to Package
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pSelect = document.getElementById('syllabusProductSelect');
    const pCustom = document.getElementById('customPriceInput');
    if (pSelect && pCustom) {
        pSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const defaultPrice = opt.getAttribute('data-price');
            if (defaultPrice) {
                pCustom.placeholder = parseFloat(defaultPrice).toFixed(2);
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
