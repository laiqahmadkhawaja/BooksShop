<?php
// customer_form.php - Add / Edit Customer Profile Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();

$customerId = (int)($_GET['id'] ?? 0);
$isEdit = ($customerId > 0);
$customer = null;

if ($isEdit) {
    $customer = dbFetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    if (!$customer) {
        setFlash('danger', 'Customer not found.');
        header('Location: customers.php');
        exit;
    }
    $pageTitle = 'Edit Customer: ' . $customer['name'];
} else {
    $pageTitle = 'Add New Customer';
    $customer = [
        'id' => 0,
        'name' => '',
        'customer_type' => 'regular',
        'phone' => '',
        'email' => '',
        'address' => '',
        'credit_limit' => 0,
        'opening_balance' => 0,
        'notes' => ''
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1">
            <i class="fa-solid <?= $isEdit ? 'fa-user-pen text-secondary' : 'fa-user-plus text-primary' ?> me-2"></i>
            <?= $isEdit ? 'Edit Customer' : 'Add New Customer' ?>
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="customers.php" class="text-decoration-none">Customers</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Edit' : 'New' ?></li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="customers.php<?= $isEdit ? '?customer_id=' . $customer['id'] : '' ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Customers
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-primary">
                    <i class="fa-solid fa-id-card me-2"></i> Customer Profile Information
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="customers.php">
                    <input type="hidden" name="save_customer" value="1">
                    <input type="hidden" name="customer_id" value="<?= (int)$customer['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Customer / Client Name *</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= htmlspecialchars($customer['name']) ?>" placeholder="e.g. Al-Rehman Traders, Muhammad Ali, Metro Corp">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Customer Type</label>
                            <select name="customer_type" class="form-select">
                                <option value="regular" <?= ($customer['customer_type'] === 'regular') ? 'selected' : '' ?>>Regular Customer</option>
                                <option value="walkin" <?= ($customer['customer_type'] === 'walkin') ? 'selected' : '' ?>>Walk-in</option>
                                <option value="wholesale" <?= ($customer['customer_type'] === 'wholesale') ? 'selected' : '' ?>>Wholesale Dealer</option>
                                <option value="school" <?= ($customer['customer_type'] === 'school') ? 'selected' : '' ?>>Corporate / Institutional</option>
                                <option value="teacher" <?= ($customer['customer_type'] === 'teacher') ? 'selected' : '' ?>>Teacher / Staff</option>
                                <option value="student" <?= ($customer['customer_type'] === 'student') ? 'selected' : '' ?>>Student</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Phone / WhatsApp</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?= htmlspecialchars($customer['phone']) ?>" placeholder="0300-1234567">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email Address (Optional)</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($customer['email']) ?>" placeholder="customer@example.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Address / Campus</label>
                        <textarea name="address" class="form-control" rows="2"
                                  placeholder="Complete address or branch"><?= htmlspecialchars($customer['address']) ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Credit Limit (Rs.)</label>
                            <input type="number" step="0.01" min="0" name="credit_limit" class="form-control"
                                   value="<?= (float)$customer['credit_limit'] ?>">
                            <div class="form-text small">Maximum allowed ledger balance (0 = no limit).</div>
                        </div>
                        <?php if (!$isEdit): ?>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold text-danger">Opening Balance (Receivable)</label>
                            <input type="number" step="0.01" min="0" name="opening_balance" class="form-control" value="0">
                            <div class="form-text small">Existing debt from previous manual records.</div>
                        </div>
                        <?php else: ?>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Current Ledger Balance</label>
                            <input type="text" class="form-control bg-light fw-bold text-danger" readonly
                                   value="<?= formatCurrency($customer['current_balance']) ?>">
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Terms, discount agreements, special instructions"><?= htmlspecialchars($customer['notes']) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="customers.php<?= $isEdit ? '?customer_id=' . $customer['id'] : '' ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-save me-1"></i> <?= $isEdit ? 'Update Customer' : 'Save Customer' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
