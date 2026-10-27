<?php
// users.php - User Accounts & Customized Role Permissions Management
require_once __DIR__ . '/config/app.php';
requireAuth();
requireRole('admin');

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_user'])) {
        $id = (int)($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['full_name'] ?? ($_POST['name'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'cashier';
        $password = $_POST['password'] ?? '';
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        // Custom module permissions
        $permissions = null;
        if ($role === 'admin') {
            $permissions = json_encode(['all']);
        } elseif (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
            $permissions = json_encode(array_values($_POST['permissions']));
        } else {
            $permissions = json_encode([]);
        }

        if ($username && $fullname) {
            if ($id > 0) {
                if ($password) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    dbQuery("UPDATE users SET username=?, full_name=?, email=?, phone=?, role=?, permissions=?, is_active=?, password_hash=? WHERE id=?",
                            [$username, $fullname, $email, $phone, $role, $permissions, $isActive, $hash, $id]);
                } else {
                    dbQuery("UPDATE users SET username=?, full_name=?, email=?, phone=?, role=?, permissions=?, is_active=? WHERE id=?",
                            [$username, $fullname, $email, $phone, $role, $permissions, $isActive, $id]);
                }
                setFlash('success', "User account '$fullname' and custom page permissions updated successfully.");
            } else {
                if (!$password) $password = '123456';
                $hash = password_hash($password, PASSWORD_DEFAULT);
                dbQuery("INSERT INTO users (username, password_hash, full_name, email, phone, role, permissions, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                         [$username, $hash, $fullname, $email, $phone, $role, $permissions, $isActive]);
                setFlash('success', "User '$fullname' created with customized permissions.");
            }
        } else {
            setFlash('danger', 'Both Full Name and Login Username are required.');
        }
        redirect("users.php");
    }

    if (isset($_POST['delete_user'])) {
        $id = (int)($_POST['delete_user_id'] ?? 0);
        $currentUserId = (int)($_SESSION['user']['id'] ?? 0);
        if ($id === $currentUserId) {
            setFlash('danger', 'You cannot delete your own logged-in user account.');
        } elseif ($id === 1) {
            setFlash('danger', 'The primary system administrator account cannot be deleted.');
        } else {
            dbQuery("DELETE FROM users WHERE id = ?", [$id]);
            setFlash('success', 'User account deleted successfully.');
        }
        redirect("users.php");
    }
}

$users = dbFetchAll("SELECT * FROM users ORDER BY id ASC");
$allModules = getAvailableModules();

$pageTitle = 'Users & Customized Permissions';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-user-shield text-primary me-2"></i> User Accounts & Page Permissions
        </h4>
        <small class="text-muted">Superadmin can customize each staff member's page access and permissions manually and differently</small>
    </div>
    <div>
        <a href="user_form.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fa-solid fa-user-plus me-1"></i> Add New User & Set Permissions
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th style="width: 20%;">Staff Member</th>
                        <th style="width: 12%;">Username</th>
                        <th style="width: 12%;">Role</th>
                        <th style="width: 32%;">Allowed Pages & ERP Permissions</th>
                        <th style="width: 8%;">Status</th>
                        <th style="width: 16%;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <?php 
                                $badgeClass = 'bg-secondary';
                                if ($u['role'] === 'admin') $badgeClass = 'bg-danger';
                                elseif ($u['role'] === 'cashier') $badgeClass = 'bg-success';
                                elseif ($u['role'] === 'accountant') $badgeClass = 'bg-primary';
                                elseif ($u['role'] === 'manager') $badgeClass = 'bg-warning text-dark';

                                // Parse permissions
                                $permBadges = [];
                                $isAll = ($u['role'] === 'admin');
                                $perms = [];
                                if (!$isAll && !empty($u['permissions'])) {
                                    $decoded = json_decode($u['permissions'], true);
                                    if (is_array($decoded)) {
                                        if (in_array('all', $decoded)) {
                                            $isAll = true;
                                        } else {
                                            $perms = $decoded;
                                        }
                                    }
                                } elseif (!$isAll) {
                                    $perms = getRoleDefaultPermissions($u['role']);
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($u['full_name']) ?></div>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars($u['email'] ?: ($u['phone'] ?: 'No contact')) ?>
                                    </div>
                                </td>
                                <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                                <td>
                                    <span class="badge <?= $badgeClass ?> text-uppercase px-2 py-1" style="font-size: 0.72rem;">
                                        <?= htmlspecialchars($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($isAll): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                            <i class="fa-solid fa-lock-open me-1"></i> Full Access (All 14 Modules)
                                        </span>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                            <span class="badge bg-primary px-2 py-1" style="font-size: 0.72rem;">
                                                <i class="fa-solid fa-check me-1"></i> <?= count($perms) ?> Modules
                                            </span>
                                            <?php 
                                                $displayed = array_slice($perms, 0, 4);
                                                foreach ($displayed as $pKey): 
                                                    $pLabel = $allModules[$pKey]['label'] ?? ucfirst($pKey);
                                                    $pIcon = $allModules[$pKey]['icon'] ?? 'fa-circle';
                                            ?>
                                                <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.7rem;">
                                                    <i class="fa-solid <?= $pIcon ?> text-primary me-1"></i> <?= htmlspecialchars($pLabel) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (count($perms) > 4): ?>
                                                <span class="badge bg-secondary-subtle text-secondary border px-1" title="More modules enabled" style="font-size: 0.7rem;">
                                                    +<?= count($perms) - 4 ?> more
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="user_form.php?id=<?= $u['id'] ?>" class="btn btn-outline-primary" title="Customize Page Access & Edit User">
                                            <i class="fa-solid fa-user-gear me-1"></i> Permissions
                                        </a>
                                        <?php if ($u['id'] > 1 && $u['id'] != ($_SESSION['user']['id'] ?? 0)): ?>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="confirmDeleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>')" title="Delete User">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
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

<!-- Inline non-modal delete confirmation form -->
<div id="deleteConfirmBox" class="card border-danger shadow-sm mt-3 d-none">
    <div class="card-body p-3">
        <form method="POST" action="users.php" class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <input type="hidden" name="delete_user" value="1">
            <input type="hidden" name="delete_user_id" id="deleteUserIdInput" value="0">
            <div class="text-danger fw-bold">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                Are you sure you want to permanently delete user: <span id="deleteUserNameSpan" class="text-dark"></span>?
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light btn-sm px-3" onclick="cancelDeleteUser()">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm px-3 fw-bold">Confirm Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmDeleteUser(id, name) {
    document.getElementById('deleteUserIdInput').value = id;
    document.getElementById('deleteUserNameSpan').textContent = name;
    const box = document.getElementById('deleteConfirmBox');
    box.classList.remove('d-none');
    box.scrollIntoView({ behavior: 'smooth' });
}

function cancelDeleteUser() {
    document.getElementById('deleteConfirmBox').classList.add('d-none');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
