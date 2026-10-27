<?php
// user_form.php - Add / Edit System User with Custom Page Permissions
require_once __DIR__ . '/config/app.php';
requireAuth();
requireRole('admin');

$userId = (int)($_GET['id'] ?? 0);
$isEdit = ($userId > 0);
$user = null;

if ($isEdit) {
    $user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        setFlash('danger', 'User account not found.');
        redirect('users.php');
    }
    foreach ($user as $k => $v) {
        $user[$k] = $v ?? '';
    }
    $pageTitle = 'Edit User: ' . ($user['full_name'] ?: $user['username']);
} else {
    $pageTitle = 'Add New System User';
    $user = [
        'id' => 0,
        'full_name' => '',
        'username' => '',
        'email' => '',
        'phone' => '',
        'role' => 'cashier',
        'permissions' => '',
        'is_active' => 1
    ];
}

$availableModules = getAvailableModules();

// Determine initially checked permissions
$activePerms = [];
if (!empty($user['permissions'])) {
    $decoded = json_decode($user['permissions'], true);
    if (is_array($decoded)) {
        $activePerms = $decoded;
    }
} elseif ($isEdit && empty($user['permissions'])) {
    $activePerms = getRoleDefaultPermissions($user['role'] ?? 'cashier');
} elseif (!$isEdit) {
    $activePerms = ['pos', 'sales', 'customers'];
}

$isAdmin = ($user['role'] === 'admin');

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1">
            <i class="fa-solid <?= $isEdit ? 'fa-user-pen text-secondary' : 'fa-user-plus text-primary' ?> me-2"></i>
            <?= $isEdit ? 'Edit System User & Permissions' : 'Create System User & Custom Permissions' ?>
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
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Users List
        </a>
    </div>
</div>

<form method="POST" action="users.php" id="userForm">
    <input type="hidden" name="save_user" value="1">
    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">

    <div class="row g-4">
        <!-- Column 1: Account Credentials & Role -->
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-primary">
                        <i class="fa-solid fa-id-card me-2"></i> User Account & Credentials
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required
                               value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" placeholder="e.g. Muhammad Ali">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Login Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                            <input type="text" name="username" class="form-control" required
                                   value="<?= htmlspecialchars($user['username'] ?? '') ?>" placeholder="e.g. ali_pos">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="ali@bookshop.com">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                            <input type="text" name="phone" class="form-control"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="0300-1234567">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Base Role <span class="text-danger">*</span></label>
                            <select name="role" id="userRoleSelect" class="form-select" required onchange="handleRoleChange(this.value)">
                                <option value="cashier" <?= ($user['role'] === 'cashier') ? 'selected' : '' ?>>Cashier (Counter POS)</option>
                                <option value="manager" <?= ($user['role'] === 'manager') ? 'selected' : '' ?>>Manager (Operations)</option>
                                <option value="accountant" <?= ($user['role'] === 'accountant') ? 'selected' : '' ?>>Accountant (Khata & Books)</option>
                                <option value="admin" <?= ($user['role'] === 'admin') ? 'selected' : '' ?>>Administrator (Superadmin)</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Login Status</label>
                            <select name="is_active" class="form-select">
                                <option value="1" <?= (!empty($user['is_active'])) ? 'selected' : '' ?>>Active (Can Login)</option>
                                <option value="0" <?= (empty($user['is_active'])) ? 'selected' : '' ?>>Suspended / Blocked</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">
                            <?= $isEdit ? 'Change Password (Leave blank to keep current)' : 'Login Password *' ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                            <input type="password" name="password" class="form-control" <?= $isEdit ? '' : 'required' ?> 
                                   placeholder="<?= $isEdit ? '••••••••' : 'Enter login password' ?>">
                        </div>
                    </div>

                    <div class="alert alert-info border-0 p-3 mb-0 small">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        <strong>Superadmin Notice:</strong> You can grant or restrict access to any page on the right side. Selected permissions will apply immediately to this staff member upon login.
                    </div>
                </div>
            </div>
        </div>

        <!-- Column 2: Custom Page & Module Permissions -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm h-100" id="permissionsSection">
                <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="fw-bold mb-0 text-primary">
                            <i class="fa-solid fa-user-lock me-2"></i> Custom Page Access & ERP Permissions
                        </h6>
                        <small class="text-muted">Check or uncheck individual pages to customize this user's manual access differently.</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" onclick="selectAllPerms(true)">
                            <i class="fa-solid fa-check-double me-1"></i> Check All
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="selectAllPerms(false)">
                            <i class="fa-solid fa-xmark me-1"></i> Uncheck All
                        </button>
                    </div>
                </div>
                <div class="card-body p-4">
                    <!-- Quick Presets -->
                    <div class="p-2 mb-3 bg-light rounded-3 d-flex flex-wrap align-items-center gap-2">
                        <span class="small fw-bold text-secondary me-1">Apply Quick Presets:</span>
                        <button type="button" class="btn btn-xs btn-outline-success" onclick="applyPreset('cashier')">
                            <i class="fa-solid fa-cash-register me-1"></i> Cashier (POS + Sales)
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-warning text-dark" onclick="applyPreset('manager')">
                            <i class="fa-solid fa-boxes-stacked me-1"></i> Inventory Manager
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-info text-dark" onclick="applyPreset('accountant')">
                            <i class="fa-solid fa-wallet me-1"></i> Accountant
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="applyPreset('admin')">
                            <i class="fa-solid fa-shield me-1"></i> Full Access
                        </button>
                    </div>

                    <div id="adminNotice" class="alert alert-warning border-0 p-3 mb-3 small <?= $isAdmin ? '' : 'd-none' ?>">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        <strong>Administrator role selected:</strong> Users with Administrator role have full unrestricted access to all ERP pages, users management, and store settings automatically.
                    </div>

                    <!-- Modules Grid -->
                    <div class="row g-3">
                        <?php foreach ($availableModules as $modKey => $mod): ?>
                            <?php 
                                $isChecked = in_array($modKey, $activePerms) || in_array('all', $activePerms) || $isAdmin;
                            ?>
                            <div class="col-12 col-sm-6">
                                <div class="p-3 border rounded-3 h-100 bg-white perm-card shadow-sm" style="transition: all 0.2s ease;">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input perm-checkbox" type="checkbox" name="permissions[]" 
                                               value="<?= $modKey ?>" id="perm_<?= $modKey ?>" <?= $isChecked ? 'checked' : '' ?>
                                               onchange="highlightCard(this)">
                                        <label class="form-check-label fw-bold text-dark d-flex align-items-center gap-2" for="perm_<?= $modKey ?>">
                                            <i class="fa-solid <?= $mod['icon'] ?> text-primary"></i>
                                            <?= htmlspecialchars($mod['label']) ?>
                                        </label>
                                    </div>
                                    <div class="text-muted small ps-4">
                                        <?= htmlspecialchars($mod['desc']) ?>
                                        <div class="mt-1">
                                            <code class="text-secondary" style="font-size: 0.72rem;"><?= implode(', ', array_slice($mod['pages'], 0, 3)) ?><?= count($mod['pages']) > 3 ? '...' : '' ?></code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                    <a href="users.php" class="btn btn-light px-3">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-save me-1"></i> <?= $isEdit ? 'Save Changes & Permissions' : 'Create User Account' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const rolePresets = {
    cashier: ['pos', 'sales', 'customers'],
    manager: ['dashboard', 'pos', 'books', 'sales', 'schools', 'inventory', 'warehouses', 'purchases', 'suppliers', 'customers'],
    accountant: ['dashboard', 'sales', 'purchases', 'suppliers', 'customers', 'accounting', 'reports'],
    admin: <?= json_encode(array_keys($availableModules)) ?>
};

function selectAllPerms(check) {
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = check;
        highlightCard(cb);
    });
}

function applyPreset(role) {
    const list = rolePresets[role] || [];
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = list.includes(cb.value);
        highlightCard(cb);
    });
}

function handleRoleChange(role) {
    const adminNotice = document.getElementById('adminNotice');
    if (role === 'admin') {
        adminNotice.classList.remove('d-none');
        selectAllPerms(true);
    } else {
        adminNotice.classList.add('d-none');
        applyPreset(role);
    }
}

function highlightCard(cb) {
    const card = cb.closest('.perm-card');
    if (card) {
        if (cb.checked) {
            card.classList.remove('bg-white');
            card.style.backgroundColor = '#f0fdf4';
            card.style.borderColor = '#86efac';
        } else {
            card.style.backgroundColor = '#ffffff';
            card.style.borderColor = '#e2e8f0';
        }
    }
}

// Initial highlight on load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.perm-checkbox').forEach(highlightCard);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
