<?php
// expense_categories.php - Manage Expense & Income Heads (Dedicated Zero-Modal Full Page)
require_once __DIR__ . '/config/app.php';
requireAuth();

$action = $_GET['action'] ?? '';
$editId = (int)($_GET['id'] ?? 0);
$typeFilter = $_GET['type'] ?? 'all';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_category'])) {
        $id = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['expense', 'income']) ? $_POST['type'] : 'expense';
        $desc = trim($_POST['description'] ?? '');

        if (empty($name)) {
            setFlash('danger', 'Category name is required.');
        } else {
            if ($id > 0) {
                dbQuery("UPDATE expense_categories SET name=?, type=?, description=? WHERE id=?", [$name, $type, $desc, $id]);
                setFlash('success', "Category '$name' updated successfully.");
            } else {
                $exists = dbFetchOne("SELECT id FROM expense_categories WHERE LOWER(name)=LOWER(?) AND type=?", [$name, $type]);
                if ($exists) {
                    setFlash('warning', "Category '$name' already exists.");
                } else {
                    dbQuery("INSERT INTO expense_categories (name, type, description) VALUES (?, ?, ?)", [$name, $type, $desc]);
                    setFlash('success', "Category '$name' added successfully.");
                }
            }
        }
        redirect('expense_categories.php?type=' . $type);
    }

    if (isset($_POST['delete_category'])) {
        $delId = (int)$_POST['category_id'];
        $cat = dbFetchOne("SELECT * FROM expense_categories WHERE id = ?", [$delId]);
        if ($cat) {
            dbQuery("DELETE FROM expense_categories WHERE id = ?", [$delId]);
            setFlash('success', "Category '{$cat['name']}' deleted.");
            redirect('expense_categories.php?type=' . $cat['type']);
        }
        redirect('expense_categories.php');
    }
}

// Fetch edit record if requested
$editCategory = null;
if ($editId > 0) {
    $editCategory = dbFetchOne("SELECT * FROM expense_categories WHERE id = ?", [$editId]);
}

// Build list query
$where = [];
$params = [];
if ($typeFilter === 'expense' || $typeFilter === 'income') {
    $where[] = "type = ?";
    $params[] = $typeFilter;
}
$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$categories = dbFetchAll("SELECT * FROM expense_categories $whereSql ORDER BY type ASC, name ASC", $params);

$pageTitle = 'Expense & Income Categories';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-tags text-primary me-2"></i> Expense & Income Categories</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="accounting.php" class="text-decoration-none">Accounting</a></li>
                <li class="breadcrumb-item active" aria-current="page">Categories</li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="expense_form.php" class="btn btn-danger btn-sm me-1 shadow-sm">
            <i class="fa-solid fa-arrow-trend-down me-1"></i> Add Expense
        </a>
        <a href="income_form.php" class="btn btn-success btn-sm me-1 shadow-sm">
            <i class="fa-solid fa-arrow-trend-up me-1"></i> Add Income
        </a>
        <a href="accounting.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Accounts
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- LEFT: Add / Edit Category Form (40%) -->
    <div class="col-lg-4 col-md-5">
        <div class="card shadow-sm border-0 sticky-top" style="top: 85px; z-index: 10;">
            <div class="card-header <?= $editCategory ? 'bg-warning-subtle text-warning-emphasis' : 'bg-primary text-white' ?> py-3">
                <h6 class="fw-bold mb-0">
                    <i class="fa-solid <?= $editCategory ? 'fa-pen-to-square' : 'fa-plus-circle' ?> me-2"></i>
                    <?= $editCategory ? 'Edit Category' : 'Create New Category' ?>
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="expense_categories.php">
                    <input type="hidden" name="save_category" value="1">
                    <input type="hidden" name="category_id" value="<?= $editCategory['id'] ?? 0 ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Category Type *</label>
                        <select name="type" class="form-select form-select-lg" required>
                            <option value="expense" <?= (($editCategory['type'] ?? $typeFilter) === 'expense') ? 'selected' : '' ?>>
                                🔴 Expense Head (Cash Outflow)
                            </option>
                            <option value="income" <?= (($editCategory['type'] ?? $typeFilter) === 'income') ? 'selected' : '' ?>>
                                🟢 Income Source (Cash Inflow)
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Category Name *</label>
                        <input type="text" name="name" class="form-control form-control-lg" required
                               value="<?= htmlspecialchars($editCategory['name'] ?? '') ?>"
                               placeholder="e.g. Generator Fuel, Marketing, Scrap Sale">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Description / Purpose (Optional)</label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Brief notes regarding this accounting head"><?= htmlspecialchars($editCategory['description'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <?php if ($editCategory): ?>
                            <a href="expense_categories.php?type=<?= urlencode($typeFilter) ?>" class="btn btn-outline-secondary btn-sm">Cancel Edit</a>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                        <button type="submit" class="btn <?= $editCategory ? 'btn-warning text-dark' : 'btn-primary' ?> px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-save me-1"></i> <?= $editCategory ? 'Update Category' : 'Save Category' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT: Categories Directory & Filter (60%) -->
    <div class="col-lg-8 col-md-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white p-0">
                <ul class="nav nav-tabs card-header-tabs m-0 flex-nowrap overflow-auto">
                    <li class="nav-item">
                        <a class="nav-link text-nowrap <?= $typeFilter === 'all' ? 'active fw-bold' : '' ?>" href="expense_categories.php?type=all">
                            <i class="fa-solid fa-list me-1"></i> All Categories (<?= count(dbFetchAll("SELECT id FROM expense_categories")) ?>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-nowrap <?= $typeFilter === 'expense' ? 'active fw-bold text-danger' : '' ?>" href="expense_categories.php?type=expense">
                            <i class="fa-solid fa-arrow-trend-down me-1 text-danger"></i> Expenses (<?= count(dbFetchAll("SELECT id FROM expense_categories WHERE type='expense'")) ?>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-nowrap <?= $typeFilter === 'income' ? 'active fw-bold text-success' : '' ?>" href="expense_categories.php?type=income">
                            <i class="fa-solid fa-arrow-trend-up me-1 text-success"></i> Income Sources (<?= count(dbFetchAll("SELECT id FROM expense_categories WHERE type='income'")) ?>)
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 35%;">Category Name</th>
                                <th style="width: 20%;">Type</th>
                                <th style="width: 25%;">Description</th>
                                <th style="width: 15%;" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-tags fa-2x mb-2 text-secondary opacity-50"></i>
                                        <p class="mb-0">No categories found in this section. Create one using the form on the left.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $idx => $c): ?>
                                    <tr class="<?= ($editId === (int)$c['id']) ? 'table-warning' : '' ?>">
                                        <td class="text-muted small"><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($c['name']) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($c['type'] === 'expense'): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                    <i class="fa-solid fa-arrow-down me-1"></i> Expense
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="fa-solid fa-arrow-up me-1"></i> Income
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small">
                                            <?= htmlspecialchars($c['description'] ?: '—') ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <a href="expense_categories.php?id=<?= $c['id'] ?>&type=<?= urlencode($typeFilter) ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Edit Category">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <form method="POST" action="expense_categories.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete category \'<?= htmlspecialchars(addslashes($c['name'])) ?>\'?');">
                                                <input type="hidden" name="delete_category" value="1">
                                                <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Category">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
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
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
