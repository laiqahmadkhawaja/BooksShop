<?php
// product_form.php - Add / Edit Product Dedicated Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();

$productId = (int)($_GET['id'] ?? 0);
$isEdit = ($productId > 0);
$product = null;

if ($isEdit) {
    $product = dbFetchOne("SELECT * FROM products WHERE id = ?", [$productId]);
    if (!$product) {
        setFlash('danger', 'Product not found.');
        header('Location: books.php');
        exit;
    }
    $pageTitle = 'Edit Product: ' . $product['title'];
} else {
    $pageTitle = 'Add New Product';
    $product = [
        'id' => 0,
        'product_code' => 'BK-' . rand(1000, 9999),
        'barcode' => '',
        'title' => '',
        'urdu_title' => '',
        'product_type' => 'book',
        'category_id' => 0,
        'isbn' => '',
        'author' => '',
        'publisher' => '',
        'edition' => '',
        'publication_year' => '',
        'class_grade' => '',
        'educational_board' => '',
        'subject' => '',
        'language' => 'English',
        'unit' => 'Piece',
        'purchase_price' => '0.00',
        'retail_price' => '0.00',
        'wholesale_price' => '0.00',
        'discount_percent' => '0.00',
        'current_stock' => 0,
        'min_stock_alert' => 5,
        'rack_location' => '',
        'shelf_location' => '',
        'description' => ''
    ];
}

$categories = dbFetchAll("SELECT * FROM categories ORDER BY name ASC");
$productTypes = getProductTypes();
$typeMap = array_column($productTypes, 'name', 'code');

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1">
            <i class="fa-solid <?= $isEdit ? 'fa-pen-to-square text-secondary' : 'fa-plus-circle text-primary' ?> me-2"></i>
            <?= $isEdit ? 'Edit Product' : 'Add New Product' ?>
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="books.php" class="text-decoration-none">Catalog</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Edit Product' : 'New Product' ?></li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="books.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Catalog
        </a>
    </div>
</div>

<form method="POST" action="books.php" id="productForm">
    <input type="hidden" name="save_product" value="1">
    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

    <!-- Section 1: Basic Information -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="fw-bold mb-0 text-primary">
                <i class="fa-solid fa-circle-info me-2"></i> 1. Basic Information
            </h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <!-- Product Type -->
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Product Type *</label>
                    <select name="product_type" id="prod_type" class="form-select" onchange="toggleProductTypeFields()">
                        <?php foreach ($productTypes as $pt): ?>
                            <option value="<?= htmlspecialchars($pt['code']) ?>" <?= ($product['product_type'] === $pt['code']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pt['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Product Code / SKU -->
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Product Code / SKU *</label>
                    <input type="text" name="product_code" id="prod_code" class="form-control" required
                           value="<?= htmlspecialchars($product['product_code']) ?>" placeholder="e.g. BK-1025 or ST-2030">
                </div>

                <!-- Barcode -->
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Barcode (EAN-13 / Code128)</label>
                    <div class="input-group">
                        <input type="text" name="barcode" id="prod_barcode" class="form-control"
                               value="<?= htmlspecialchars($product['barcode']) ?>" placeholder="Scan or leave blank to use SKU">
                        <button class="btn btn-outline-secondary" type="button" onclick="generateRandomBarcode()" title="Generate Barcode">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                    </div>
                </div>

                <!-- Title / English Name -->
                <div class="col-12 col-md-6">
                    <label class="form-label small fw-bold">Title / Item Name *</label>
                    <input type="text" name="title" id="prod_title" class="form-control" required
                           value="<?= htmlspecialchars($product['title']) ?>" placeholder="e.g. Wireless Mouse, Cotton Shirt, Standard Book">
                </div>

                <!-- Urdu Title -->
                <div class="col-12 col-md-6">
                    <label class="form-label small fw-bold">Urdu Title / نام (اردو)</label>
                    <input type="text" name="urdu_title" id="prod_urdu_title" class="form-control font-urdu" dir="rtl"
                           value="<?= htmlspecialchars($product['urdu_title'] ?? '') ?>" placeholder="اردو نام درج کریں">
                </div>

                <!-- Category -->
                <div class="col-12 col-md-6">
                    <label class="form-label small fw-bold">Category</label>
                    <select name="category_id" id="prod_category_id" class="form-select">
                        <option value="">-- No Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ((int)$product['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?> (<?= htmlspecialchars($typeMap[$cat['type']] ?? ucfirst($cat['type'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Unit -->
                <div class="col-12 col-md-6">
                    <label class="form-label small fw-bold">Measurement Unit</label>
                    <select name="unit" id="prod_unit" class="form-select">
                        <?php
                        $units = ['Piece', 'Set', 'Pack', 'Box', 'Dozen', 'Book', 'Meter', 'Roll'];
                        foreach ($units as $u):
                        ?>
                            <option value="<?= $u ?>" <?= ($product['unit'] === $u) ? 'selected' : '' ?>><?= $u ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Book / Educational Details (Conditional) -->
    <div class="card border-0 shadow-sm mb-4" id="bookSpecificFields">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="fw-bold mb-0 text-success">
                <i class="fa-solid fa-sliders me-2"></i> 2. Additional Specifications & Attributes (Optional)
            </h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Department / Subject / Tag</label>
                    <input type="text" name="subject" id="prod_subject" class="form-control"
                           value="<?= htmlspecialchars($product['subject'] ?? '') ?>" placeholder="e.g. General, Apparel, Electronics, Science">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Size / Model / Grade</label>
                    <input type="text" name="class_grade" id="prod_class_grade" class="form-control"
                           value="<?= htmlspecialchars($product['class_grade'] ?? '') ?>" placeholder="e.g. Standard, Medium, XL, Grade 5">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Brand / Series / Syllabus</label>
                    <input type="text" name="educational_board" id="prod_board" class="form-control"
                           value="<?= htmlspecialchars($product['educational_board'] ?? '') ?>" placeholder="e.g. Samsung, Oxford, National">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Manufacturer / Author</label>
                    <input type="text" name="author" id="prod_author" class="form-control"
                           value="<?= htmlspecialchars($product['author'] ?? '') ?>" placeholder="e.g. Dell, Sony, Author">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Publisher / Vendor</label>
                    <input type="text" name="publisher" id="prod_publisher" class="form-control"
                           value="<?= htmlspecialchars($product['publisher'] ?? '') ?>" placeholder="e.g. Vendor or Publisher">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">ISBN / Model / Part No</label>
                    <input type="text" name="isbn" id="prod_isbn" class="form-control"
                           value="<?= htmlspecialchars($product['isbn'] ?? '') ?>" placeholder="e.g. 978-0-19-940825-3, MOD-204">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Edition</label>
                    <input type="text" name="edition" id="prod_edition" class="form-control"
                           value="<?= htmlspecialchars($product['edition'] ?? '') ?>" placeholder="e.g. 3rd Edition (2024)">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Publication Year</label>
                    <input type="text" name="publication_year" id="prod_year" class="form-control"
                           value="<?= htmlspecialchars($product['publication_year'] ?? '') ?>" placeholder="e.g. 2024">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Medium / Language</label>
                    <select name="language" id="prod_language" class="form-select">
                        <option value="English" <?= ($product['language'] === 'English') ? 'selected' : '' ?>>English</option>
                        <option value="Urdu" <?= ($product['language'] === 'Urdu') ? 'selected' : '' ?>>Urdu</option>
                        <option value="Bilingual" <?= ($product['language'] === 'Bilingual') ? 'selected' : '' ?>>Bilingual (English + Urdu)</option>
                        <option value="Arabic" <?= ($product['language'] === 'Arabic') ? 'selected' : '' ?>>Arabic</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3: Pricing & Inventory Details -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-coins me-2 text-warning"></i> 3. Pricing & Stock Management
            </h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Purchase Cost Price (Rs.) *</label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" step="0.01" min="0" name="purchase_price" id="prod_cost" class="form-control" required
                               value="<?= htmlspecialchars($product['purchase_price']) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Retail / Selling Price (Rs.) *</label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" step="0.01" min="0" name="retail_price" id="prod_retail" class="form-control" required
                               value="<?= htmlspecialchars($product['retail_price']) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Wholesale Price (Rs.)</label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" step="0.01" min="0" name="wholesale_price" id="prod_wholesale" class="form-control"
                               value="<?= htmlspecialchars($product['wholesale_price']) ?>">
                    </div>
                </div>

                <?php if (!$isEdit): ?>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold text-success">Initial Opening Stock</label>
                    <input type="number" step="1" min="0" name="initial_stock" id="prod_stock" class="form-control"
                           value="0" placeholder="0">
                    <div class="form-text small">Sets current on-hand inventory quantity.</div>
                </div>
                <?php else: ?>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Current System Stock</label>
                    <input type="text" class="form-control bg-light fw-bold text-primary" readonly
                           value="<?= (float)($product['current_stock'] ?? 0) ?> <?= htmlspecialchars($product['unit']) ?>">
                    <div class="form-text small">Adjust stock via Stock Adjustment on catalog page.</div>
                </div>
                <?php endif; ?>

                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Low Stock Alert Threshold</label>
                    <input type="number" step="1" min="0" name="min_stock_alert" id="prod_min_stock" class="form-control"
                           value="<?= (int)$product['min_stock_alert'] ?>">
                    <div class="form-text small">Shows alert when stock drops below this.</div>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label small fw-bold">Rack Location</label>
                    <input type="text" name="rack_location" id="prod_rack" class="form-control"
                           value="<?= htmlspecialchars($product['rack_location'] ?? '') ?>" placeholder="e.g. Rack A-3">
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label small fw-bold">Shelf / Row</label>
                    <input type="text" name="shelf_location" id="prod_shelf" class="form-control"
                           value="<?= htmlspecialchars($product['shelf_location'] ?? '') ?>" placeholder="e.g. Shelf 2">
                </div>
            </div>
        </div>
    </div>

    <!-- Section 4: Description & Notes -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="fw-bold mb-0 text-secondary">
                <i class="fa-solid fa-align-left me-2"></i> 4. Notes & Description
            </h6>
        </div>
        <div class="card-body p-4">
            <textarea name="description" id="prod_desc" class="form-control" rows="3"
                      placeholder="Optional notes, edition notes, supplier information..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- Sticky Bottom Action Bar -->
    <div class="card border-0 shadow-sm mb-5 bg-light p-3 sticky-bottom" style="z-index: 10;">
        <div class="d-flex justify-content-between align-items-center">
            <a href="books.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-times me-1"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                <i class="fa-solid fa-save me-1"></i> <?= $isEdit ? 'Update Product' : 'Save Product' ?>
            </button>
        </div>
    </div>
</form>

<script>
function toggleProductTypeFields() {
    // Keep additional specifications available for all product types
    const bookFields = document.getElementById('bookSpecificFields');
    if (bookFields) {
        bookFields.style.display = 'block';
    }
}

function generateRandomBarcode() {
    const code = document.getElementById('prod_code').value;
    if (code) {
        document.getElementById('prod_barcode').value = code;
    } else {
        document.getElementById('prod_barcode').value = '89' + Math.floor(1000000000 + Math.random() * 9000000000);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleProductTypeFields();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
