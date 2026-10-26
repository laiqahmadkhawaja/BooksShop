<?php
// categories.php - Product Categories Manager Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();

// Load dynamic departments / product types
$productTypes = getProductTypes();
$typeNames = [];
foreach ($productTypes as $pt) {
    $typeNames[$pt['code']] = $pt['name'];
}

// Handle Save Category POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_category'])) {
        $cId = (int)($_POST['category_id'] ?? 0);
        $cName = trim($_POST['category_name'] ?? '');
        $cUrdu = trim($_POST['category_urdu_name'] ?? '');
        $cType = trim($_POST['category_type'] ?? 'book');
        $cDesc = trim($_POST['category_desc'] ?? '');

        if (!empty($cName)) {
            if ($cId > 0) {
                dbQuery("UPDATE categories SET name=?, urdu_name=?, type=?, description=? WHERE id=?", [$cName, $cUrdu, $cType, $cDesc, $cId]);
                setFlash('success', "Category '$cName' updated successfully.");
            } else {
                dbQuery("INSERT INTO categories (name, urdu_name, type, description) VALUES (?, ?, ?, ?)", [$cName, $cUrdu, $cType, $cDesc]);
                setFlash('success', "Category '$cName' created successfully.");
            }
        }
        $redirectUrl = 'categories.php' . (!empty($cType) && $cType !== 'book' ? '?dept=' . urlencode($cType) : '');
        header("Location: $redirectUrl");
        exit;
    }

    if (isset($_POST['delete_category'])) {
        $delId = (int)$_POST['category_id'];
        $count = dbFetchOne("SELECT COUNT(*) as cnt FROM products WHERE category_id = ?", [$delId])['cnt'] ?? 0;
        if ($count > 0) {
            setFlash('danger', "Cannot delete category: It contains $count active products.");
        } else {
            dbQuery("DELETE FROM categories WHERE id = ?", [$delId]);
            setFlash('success', "Category deleted successfully.");
        }
        header("Location: categories.php");
        exit;
    }
}

// Check edit mode
$editId = (int)($_GET['edit'] ?? 0);
$editCat = null;
if ($editId > 0) {
    $editCat = dbFetchOne("SELECT * FROM categories WHERE id = ?", [$editId]);
}

// Department filter
$deptFilter = trim($_GET['dept'] ?? 'all');
$where = '';
$params = [];
if ($deptFilter !== 'all' && !empty($deptFilter)) {
    $where = 'WHERE c.type = ?';
    $params = [$deptFilter];
}

$categories = dbFetchAll("SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    $where
    GROUP BY c.id 
    ORDER BY c.type ASC, c.name ASC", $params);

// Category count breakdown per department
$deptCountsRaw = dbFetchAll("SELECT type, COUNT(*) as cnt FROM categories GROUP BY type");
$deptCounts = [];
$totalAllCats = 0;
foreach ($deptCountsRaw as $dc) {
    $deptCounts[$dc['type']] = (int)$dc['cnt'];
    $totalAllCats += (int)$dc['cnt'];
}

$pageTitle = 'Product Categories Manager';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-tags text-primary me-2"></i> Product Categories</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="books.php" class="text-decoration-none">Catalog</a></li>
                <li class="breadcrumb-item active" aria-current="page">Categories</li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
        <a href="product_types.php" class="btn btn-outline-warning">
            <i class="fa-solid fa-layer-group me-1"></i> Manage Departments / Types
        </a>
        <a href="books.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Catalog
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Add / Edit Form -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm sticky-top" style="top: 20px; z-index: 5;">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-primary">
                    <i class="fa-solid <?= $editCat ? 'fa-pen-to-square' : 'fa-plus-circle' ?> me-2"></i>
                    <?= $editCat ? 'Edit Category: ' . htmlspecialchars($editCat['name']) : 'Add New Category' ?>
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="categories.php">
                    <input type="hidden" name="save_category" value="1">
                    <input type="hidden" name="category_id" value="<?= $editCat['id'] ?? 0 ?>">

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold mb-0">Department / Type *</label>
                            <a href="product_types.php" class="small text-decoration-none text-primary" title="Add or edit department types">
                                <i class="fa-solid fa-plus-circle me-1"></i>New Department
                            </a>
                        </div>
                        <select name="category_type" class="form-select" required>
                            <?php 
                            $selectedType = $editCat['type'] ?? ($deptFilter !== 'all' ? $deptFilter : 'book');
                            $foundSelected = false;
                            ?>
                            <?php foreach ($productTypes as $pt): ?>
                                <?php 
                                $isSel = ($selectedType === $pt['code']);
                                if ($isSel) $foundSelected = true;
                                ?>
                                <option value="<?= htmlspecialchars($pt['code']) ?>" <?= $isSel ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pt['name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if (!empty($selectedType) && !$foundSelected): ?>
                                <option value="<?= htmlspecialchars($selectedType) ?>" selected>
                                    <?= htmlspecialchars(ucfirst($selectedType)) ?> (Custom)
                                </option>
                            <?php endif; ?>
                        </select>
                        <div class="form-text small">Select the department this category belongs to.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Category Name (English) *</label>
                        <input type="text" name="category_name" class="form-control" required
                               value="<?= htmlspecialchars($editCat['name'] ?? '') ?>" placeholder="e.g. Mathematics, Notebooks, Action Figures">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Urdu Name / کیٹیگری نام (اردو)</label>
                        <input type="text" name="category_urdu_name" class="form-control font-urdu" dir="rtl"
                               value="<?= htmlspecialchars($editCat['urdu_name'] ?? '') ?>" placeholder="اردو نام">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Description (Optional)</label>
                        <textarea name="category_desc" class="form-control" rows="2"
                                  placeholder="Brief notes or remarks"><?= htmlspecialchars($editCat['description'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <?php if ($editCat): ?>
                            <a href="categories.php<?= $deptFilter !== 'all' ? '?dept=' . urlencode($deptFilter) : '' ?>" class="btn btn-outline-secondary">Cancel</a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="fa-solid fa-save me-1"></i> <?= $editCat ? 'Update Category' : 'Save Category' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Category Table -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h6 class="fw-bold mb-0">
                        <i class="fa-solid fa-list me-2 text-secondary"></i>
                        Categories (<?= count($categories) ?>)
                    </h6>
                    <span class="small text-muted">Showing <?= $deptFilter === 'all' ? 'All Departments' : htmlspecialchars($typeNames[$deptFilter] ?? ucfirst($deptFilter)) ?></span>
                </div>

                <!-- Department Filter Pills -->
                <div class="d-flex flex-wrap gap-1 pt-1">
                    <a href="categories.php" class="btn btn-sm <?= $deptFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        All (<?= $totalAllCats ?>)
                    </a>
                    <?php foreach ($productTypes as $pt): ?>
                        <?php 
                        $pCode = $pt['code'];
                        $cnt = $deptCounts[$pCode] ?? 0;
                        $isActive = ($deptFilter === $pCode);
                        ?>
                        <a href="categories.php?dept=<?= urlencode($pCode) ?>" class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-outline-secondary' ?>">
                            <?= htmlspecialchars($pt['name']) ?> (<?= $cnt ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Category</th>
                            <th>Department</th>
                            <th>Products</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-folder-open fs-3 d-block mb-2 text-black-50"></i>
                                    No categories found in this department.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($cat['name']) ?></strong>
                                        <?php if (!empty($cat['urdu_name'])): ?>
                                            <div class="font-urdu small text-muted" dir="rtl"><?= htmlspecialchars($cat['urdu_name']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($cat['description'])): ?>
                                            <div class="small text-muted text-truncate" style="max-width: 220px;"><?= htmlspecialchars($cat['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="categories.php?dept=<?= urlencode($cat['type']) ?>" class="badge bg-secondary-subtle text-secondary text-decoration-none fw-semibold">
                                            <?= htmlspecialchars($typeNames[$cat['type']] ?? ucfirst($cat['type'])) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="books.php?category=<?= $cat['id'] ?>" class="badge bg-primary-subtle text-primary fw-bold text-decoration-none" title="Filter catalog by this category">
                                            <?= (int)$cat['product_count'] ?> items <i class="fa-solid fa-arrow-up-right-from-square ms-1" style="font-size: 0.75rem;"></i>
                                        </a>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="categories.php?edit=<?= $cat['id'] ?><?= $deptFilter !== 'all' ? '&dept=' . urlencode($deptFilter) : '' ?>" class="btn btn-outline-secondary" title="Edit">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <form method="POST" action="categories.php" style="display:inline;" onsubmit="return confirm('Delete category \'<?= htmlspecialchars(addslashes($cat['name'])) ?>\'?')">
                                                <input type="hidden" name="delete_category" value="1">
                                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Delete" <?= $cat['product_count'] > 0 ? 'disabled' : '' ?>>
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
