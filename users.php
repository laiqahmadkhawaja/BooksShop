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
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openNewUserModal()">
            <i class="fa-solid fa-user-plus me-1"></i> Add New User
        </button>
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

<!-- Modal: Add / Edit User -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="users.php">
                <input type="hidden" name="save_user" value="1">
                <input type="hidden" name="user_id" id="modal_user_id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="userModalTitle"><i class="fa-solid fa-user-plus text-primary me-2"></i> Add Staff Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name *</label>
                        <input type="text" name="full_name" id="modal_fullname" class="form-control" required placeholder="e.g. Asim Raza">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Username *</label>
                            <input type="text" name="username" id="modal_username" class="form-control" required placeholder="asim.cashier">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Role *</label>
                            <select name="role" id="modal_role" class="form-select">
                                <option value="cashier">Cashier (POS & Sales)</option>
                                <option value="accountant">Accountant (Ledgers & Finance)</option>
                                <option value="manager">Manager (Stock & Purchases)</option>
                                <option value="admin">Administrator (Full Control)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small">Email Address</label>
                            <input type="email" name="email" id="modal_email" class="form-control" placeholder="user@kitabghar.pk">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Phone #</label>
                            <input type="text" name="phone" id="modal_phone" class="form-control" placeholder="03001234567">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Password</label>
                        <input type="password" name="password" id="modal_password" class="form-control" placeholder="Enter password (leave blank to keep unchanged)">
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="modal_is_active" checked>
                        <label class="form-check-label small" for="modal_is_active">
                            Active Account (Allow logging in)
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openNewUserModal() {
    document.getElementById('userModalTitle').innerHTML = '<i class="fa-solid fa-user-plus text-primary me-2"></i> Add Staff Account';
    document.getElementById('modal_user_id').value = '0';
    document.getElementById('modal_fullname').value = '';
    document.getElementById('modal_username').value = '';
    document.getElementById('modal_role').value = 'cashier';
    document.getElementById('modal_email').value = '';
    document.getElementById('modal_phone').value = '';
    document.getElementById('modal_password').value = '';
    document.getElementById('modal_password').required = true;
    document.getElementById('modal_is_active').checked = true;
}

function openEditUserModal(u) {
    document.getElementById('userModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-secondary me-2"></i> Edit Staff Account';
    document.getElementById('modal_user_id').value = u.id;
    document.getElementById('modal_fullname').value = u.full_name;
    document.getElementById('modal_username').value = u.username;
    document.getElementById('modal_role').value = u.role;
    document.getElementById('modal_email').value = u.email || '';
    document.getElementById('modal_phone').value = u.phone || '';
    document.getElementById('modal_password').value = '';
    document.getElementById('modal_password').required = false;
    document.getElementById('modal_is_active').checked = u.is_active == 1;
    new bootstrap.Modal(document.getElementById('userModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
