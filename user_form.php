<?php
// user_form.php - Add / Edit System User Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();
requireRole('admin');

$userId = (int)($_GET['id'] ?? 0);
$isEdit = ($userId > 0);
$user = null;

if ($isEdit) {
    $user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        setFlash('danger', 'User not found.');
        header('Location: users.php');
        exit;
    }
    foreach ($user as $k => $v) {
        $user[$k] = $v ?? '';
    }
    $pageTitle = 'Edit User: ' . $user['name'];
} else {
    $pageTitle = 'Add New System User';
    $user = [
        'id' => 0,
        'name' => '',
        'username' => '',
        'email' => '',
        'role' => 'cashier',
        'is_active' => 1
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1">
            <i class="fa-solid <?= $isEdit ? 'fa-user-pen text-secondary' : 'fa-user-plus text-primary' ?> me-2"></i>
            <?= $isEdit ? 'Edit System User' : 'Add New System User' ?>
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="users.php" class="text-decoration-none">Users</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Edit User' : 'New User' ?></li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="users.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Users
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-primary">
                    <i class="fa-solid fa-shield-halved me-2"></i> User Account Details & Role
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="users.php">
                    <input type="hidden" name="save_user" value="1">
                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name *</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= htmlspecialchars($user['name']) ?>" placeholder="e.g. Ahmad Ali">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Username *</label>
                        <input type="text" name="username" class="form-control" required
                               value="<?= htmlspecialchars($user['username']) ?>" placeholder="e.g. ahmad_pos">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email Address</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="user@bookshop.com">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">System Role *</label>
                            <select name="role" class="form-select" required>
                                <option value="cashier" <?= ($user['role'] === 'cashier') ? 'selected' : '' ?>>Cashier (POS Only)</option>
                                <option value="manager" <?= ($user['role'] === 'manager') ? 'selected' : '' ?>>Manager (Catalog, Stock & Sales)</option>
                                <option value="admin" <?= ($user['role'] === 'admin') ? 'selected' : '' ?>>Administrator (Full Access)</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Account Status</label>
                            <select name="is_active" class="form-select">
                                <option value="1" <?= (!empty($user['is_active'])) ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= (empty($user['is_active'])) ? 'selected' : '' ?>>Suspended / Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">
                            <?= $isEdit ? 'Change Password (Leave blank to keep current)' : 'Password *' ?>
                        </label>
                        <input type="password" name="password" class="form-control" <?= $isEdit ? '' : 'required' ?> placeholder="<?= $isEdit ? '••••••••' : 'Enter strong password' ?>">
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-save me-1"></i> <?= $isEdit ? 'Update User' : 'Save User' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
