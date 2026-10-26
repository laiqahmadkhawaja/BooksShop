<?php
// product_types.php - Departments & Product Types Manager Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_product_type'])) {
        $tId = (int)($_POST['type_id'] ?? 0);
        $tName = trim($_POST['type_name'] ?? '');
        $tCode = trim($_POST['type_code'] ?? '');
        $tDesc = trim($_POST['type_description'] ?? '');

        if (!empty($tName) && !empty($tCode)) {
            $tCode = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $tCode));

            if ($tId > 0) {
                // Update
                $existing = dbFetchOne("SELECT * FROM product_types WHERE id = ?", [$tId]);
                if ($existing && !empty($existing['is_system'])) {
                    dbQuery("UPDATE product_types SET name = ?, description = ? WHERE id = ?", [$tName, $tDesc, $tId]);
                } else {
                    $oldCode = $existing['code'] ?? '';
                    dbQuery("UPDATE product_types SET name = ?, code = ?, description = ? WHERE id = ?", [$tName, $tCode, $tDesc, $tId]);
                    if ($oldCode && $oldCode !== $tCode) {
                        dbQuery("UPDATE products SET product_type = ? WHERE product_type = ?", [$tCode, $oldCode]);
                        dbQuery("UPDATE categories SET type = ? WHERE type = ?", [$tCode, $oldCode]);
                    }
                }
                setFlash('success', "Department / Product type '$tName' updated successfully.");
            } else {
                // Check duplicate code
                $dup = dbFetchOne("SELECT id FROM product_types WHERE code = ?", [$tCode]);
                if ($dup) {
                    setFlash('danger', "Department code '$tCode' already exists. Please choose a different code.");
                } else {
                    dbQuery("INSERT INTO product_types (code, name, description, is_system) VALUES (?, ?, ?, 0)", [$tCode, $tName, $tDesc]);
                    setFlash('success', "Department / Product type '$tName' created successfully.");
                }
            }
        }
        header("Location: product_types.php");
        exit;
    }

    if (isset($_POST['delete_product_type'])) {
        $delId = (int)$_POST['type_id'];
        $typeRow = dbFetchOne("SELECT * FROM product_types WHERE id = ?", [$delId]);
        if ($typeRow) {
            if (!empty($typeRow['is_system'])) {
                setFlash('danger', "Cannot delete system protected department.");
            } else {
                $count = dbFetchOne("SELECT COUNT(*) as cnt FROM products WHERE product_type = ?", [$typeRow['code']])['cnt'] ?? 0;
                $catCount = dbFetchOne("SELECT COUNT(*) as cnt FROM categories WHERE type = ?", [$typeRow['code']])['cnt'] ?? 0;
                if ($count > 0) {
                    setFlash('danger', "Cannot delete '{$typeRow['name']}': It is used by $count active products.");
                } elseif ($catCount > 0) {
                    setFlash('danger', "Cannot delete '{$typeRow['name']}': It is used by $catCount categories. Reassign or delete those categories first.");
                } else {
                    dbQuery("DELETE FROM product_types WHERE id = ?", [$delId]);
                    setFlash('success', "Department / Product type deleted successfully.");
                }
            }
        }
        header("Location: product_types.php");
        exit;
    }
}

// Check edit mode
$editId = (int)($_GET['edit'] ?? 0);
$editType = null;
if ($editId > 0) {
    $editType = dbFetchOne("SELECT * FROM product_types WHERE id = ?", [$editId]);
}

$productTypes = dbFetchAll("SELECT pt.*, 
    (SELECT COUNT(*) FROM products p WHERE p.product_type = pt.code) as product_count,
    (SELECT COUNT(*) FROM categories c WHERE c.type = pt.code) as category_count 
    FROM product_types pt 
    ORDER BY pt.is_system DESC, pt.name ASC");

$pageTitle = 'Departments / Product Types Manager';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-layer-group text-warning me-2"></i> Departments / Product Types</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="books.php" class="text-decoration-none">Catalog</a></li>
                <li class="breadcrumb-item active" aria-current="page">Departments & Types</li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
        <a href="categories.php" class="btn btn-outline-primary">
            <i class="fa-solid fa-tags me-1"></i> Manage Categories
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
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid <?= $editType ? 'fa-pen-to-square' : 'fa-plus-circle text-primary' ?> me-2"></i>
                    <?= $editType ? 'Edit Department: ' . htmlspecialchars($editType['name']) : 'Add New Department / Type' ?>
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="product_types.php">
                    <input type="hidden" name="save_product_type" value="1">
                    <input type="hidden" name="type_id" value="<?= $editType['id'] ?? 0 ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Department / Type Name *</label>
                        <input type="text" name="type_name" id="type_name" class="form-control" required
                               value="<?= htmlspecialchars($editType['name'] ?? '') ?>" placeholder="e.g. Electronics, Apparel, General Merchandise, Supplies"
                               oninput="autoGenerateCode(this.value)">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">System Identifier / Code (Slug) *</label>
                        <input type="text" name="type_code" id="type_code" class="form-control font-monospace" required
                               value="<?= htmlspecialchars($editType['code'] ?? '') ?>" placeholder="e.g. toys"
                               <?= (!empty($editType['is_system'])) ? 'readonly' : '' ?>>
                        <div class="form-text small">Unique slug (letters, numbers, underscores).</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Description (Optional)</label>
                        <textarea name="type_description" class="form-control" rows="2"
                                  placeholder="Brief description or purpose"><?= htmlspecialchars($editType['description'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <?php if ($editType): ?>
                            <a href="product_types.php" class="btn btn-outline-secondary">Cancel</a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="fa-solid fa-save me-1"></i> <?= $editType ? 'Update Department' : 'Save Department' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Types Table -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">
                    <i class="fa-solid fa-list me-2 text-secondary"></i> All Departments (<?= count($productTypes) ?>)
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Department / Type</th>
                            <th>Code</th>
                            <th>Categories</th>
                            <th>Products</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productTypes as $pt): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($pt['name']) ?></strong>
                                    <?php if (!empty($pt['is_system'])): ?>
                                        <span class="badge bg-secondary-subtle text-secondary ms-1">System Default</span>
                                    <?php endif; ?>
                                    <?php if (!empty($pt['description'])): ?>
                                        <div class="small text-muted text-truncate" style="max-width: 200px;"><?= htmlspecialchars($pt['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($pt['code']) ?></code>
                                </td>
                                <td>
                                    <a href="categories.php?dept=<?= urlencode($pt['code']) ?>" class="badge bg-secondary-subtle text-secondary text-decoration-none fw-bold" title="View categories in this department">
                                        <?= (int)$pt['category_count'] ?> categories <i class="fa-solid fa-arrow-up-right-from-square ms-1" style="font-size: 0.75rem;"></i>
                                    </a>
                                </td>
                                <td>
                                    <a href="books.php?tab=<?= urlencode($pt['code']) ?>" class="badge bg-primary-subtle text-primary fw-bold text-decoration-none" title="View products in this department">
                                        <?= (int)$pt['product_count'] ?> items <i class="fa-solid fa-arrow-up-right-from-square ms-1" style="font-size: 0.75rem;"></i>
                                    </a>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="product_types.php?edit=<?= $pt['id'] ?>" class="btn btn-outline-secondary" title="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <?php if (empty($pt['is_system'])): ?>
                                            <form method="POST" action="product_types.php" style="display:inline;" onsubmit="return confirm('Delete department \'<?= htmlspecialchars(addslashes($pt['name'])) ?>\'?')">
                                                <input type="hidden" name="delete_product_type" value="1">
                                                <input type="hidden" name="type_id" value="<?= $pt['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Delete" <?= ($pt['product_count'] > 0 || $pt['category_count'] > 0) ? 'disabled' : '' ?>>
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="btn btn-sm btn-light disabled text-muted"><i class="fa-solid fa-lock"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function autoGenerateCode(val) {
    const codeInput = document.getElementById('type_code');
    // Only auto-generate if not in edit mode or code is empty/not touched
    <?php if (!$editType): ?>
    if (codeInput) {
        codeInput.value = val.toLowerCase().replace(/[^a-z0-9]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
    }
    <?php endif; ?>
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
