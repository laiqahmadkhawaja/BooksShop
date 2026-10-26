<?php
// users.php - User Accounts & Role Permissions Management
require_once __DIR__ . '/config/app.php';
requireAuth();
requireRole('admin');

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_user'])) {
        $id = (int)($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'cashier';
        $password = $_POST['password'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($username && $fullname) {
            if ($id > 0) {
                if ($password) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    dbQuery("UPDATE users SET username=?, full_name=?, email=?, phone=?, role=?, is_active=?, password_hash=? WHERE id=?",
                            [$username, $fullname, $email, $phone, $role, $isActive, $hash, $id]);
                } else {
                    dbQuery("UPDATE users SET username=?, full_name=?, email=?, phone=?, role=?, is_active=? WHERE id=?",
                            [$username, $fullname, $email, $phone, $role, $isActive, $id]);
                }
                setFlash('success', "User '$fullname' updated successfully.");
            } else {
                if (!$password) $password = '123456';
                $hash = password_hash($password, PASSWORD_DEFAULT);
                dbQuery("INSERT INTO users (username, password_hash, full_name, email, phone, role, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?)",
                         [$username, $hash, $fullname, $email, $phone, $role, $isActive]);
                setFlash('success', "User '$fullname' created successfully.");
            }
        }
        redirect("users.php");
    }
}

$users = dbFetchAll("SELECT * FROM users ORDER BY id ASC");

$pageTitle = 'Users & Roles';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-shield text-primary me-2"></i> User Accounts & Role Permissions</h4>
        <small class="text-muted">Manage system logins for Cashiers, Inventory Managers, Accountants and Administrators</small>
    </div>
    <div>
        <a href="user_form.php" class="btn btn-primary btn-sm shadow-sm"><i class="fa-solid fa-user-plus me-1"></i> Add New User</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th>Staff Member</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($u['full_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($u['email'] ?: 'No Email') ?></div>
                            </td>
                            <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                            <td>
                                <?php 
                                    $badgeClass = 'bg-secondary';
                                    if ($u['role'] === 'admin') $badgeClass = 'bg-danger';
                                    elseif ($u['role'] === 'cashier') $badgeClass = 'bg-success';
                                    elseif ($u['role'] === 'accountant') $badgeClass = 'bg-primary';
                                    elseif ($u['role'] === 'manager') $badgeClass = 'bg-warning text-dark';
                                ?>
                                <span class="badge <?= $badgeClass ?> text-uppercase" style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($u['phone'] ?: '-') ?></td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="badge bg-success">ACTIVE</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">INACTIVE</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d M, Y', strtotime($u['created_at'])) ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick='openEditUserModal(<?= json_encode($u) ?>)'>
                                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User Add/Edit is managed via dedicated user_form.php -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
