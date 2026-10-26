<?php
// account_form.php - Add / Edit Account Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();

$accId = (int)($_GET['id'] ?? 0);
$isEdit = ($accId > 0);
$account = null;

if ($isEdit) {
    $account = dbFetchOne("SELECT * FROM accounts WHERE id = ?", [$accId]);
    if (!$account) {
        setFlash('danger', 'Account not found.');
        header('Location: accounting.php');
        exit;
    }
    $account['account_name'] = $account['account_name'] ?? '';
    $account['bank_name'] = $account['bank_name'] ?? '';
    $account['account_type'] = $account['account_type'] ?? 'cash';
    $account['account_number'] = $account['account_number'] ?? '';
    $account['branch_name'] = $account['branch_name'] ?? '';
    $account['notes'] = $account['notes'] ?? '';
    $pageTitle = 'Edit Account: ' . $account['account_name'];
} else {
    $pageTitle = 'Add New Account';
    $account = [
        'id' => 0,
        'account_name' => '',
        'bank_name' => '',
        'account_type' => 'cash',
        'account_number' => '',
        'branch_name' => '',
        'balance' => 0,
        'notes' => ''
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1">
            <i class="fa-solid <?= $isEdit ? 'fa-pen-to-square text-secondary' : 'fa-plus-circle text-primary' ?> me-2"></i>
            <?= $isEdit ? 'Edit Account' : 'Add New Account' ?>
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="accounting.php" class="text-decoration-none">Accounting</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Edit Account' : 'New Account' ?></li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="accounting.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Accounts
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-primary">
                    <i class="fa-solid fa-landmark me-2"></i> Account Details
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="accounting.php">
                    <input type="hidden" name="save_account" value="1">
                    <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Account Name *</label>
                        <input type="text" name="account_name" class="form-control" required
                               value="<?= htmlspecialchars($account['account_name'] ?? '') ?>" placeholder="e.g. Main Cash Drawer, Meezan Bank, HBL Current">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Account Type</label>
                            <select name="account_type" class="form-select">
                                <option value="cash" <?= ($account['account_type'] === 'cash') ? 'selected' : '' ?>>Cash / Counter</option>
                                <option value="bank" <?= ($account['account_type'] === 'bank') ? 'selected' : '' ?>>Bank Account</option>
                                <option value="wallet" <?= ($account['account_type'] === 'wallet') ? 'selected' : '' ?>>Mobile Wallet (EasyPaisa/JazzCash)</option>
                                <option value="other" <?= ($account['account_type'] === 'other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Bank Name (if applicable)</label>
                            <input type="text" name="bank_name" class="form-control"
                                   value="<?= htmlspecialchars($account['bank_name'] ?? '') ?>" placeholder="e.g. Meezan Bank">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Account / IBAN Number</label>
                            <input type="text" name="account_number" class="form-control"
                                   value="<?= htmlspecialchars($account['account_number'] ?? '') ?>" placeholder="e.g. 0101-0102030405">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Branch Name / Code</label>
                            <input type="text" name="branch_name" class="form-control"
                                   value="<?= htmlspecialchars($account['branch_name'] ?? '') ?>" placeholder="e.g. Main Market Branch">
                        </div>
                    </div>

                    <div class="mb-3">
                        <?php if (!$isEdit): ?>
                            <label class="form-label small fw-bold text-success">Opening Balance (Rs.)</label>
                            <input type="number" step="0.01" name="opening_balance" class="form-control" value="0">
                            <div class="form-text small">Initial funds available in this account.</div>
                        <?php else: ?>
                            <label class="form-label small fw-bold">Current Balance</label>
                            <input type="text" class="form-control bg-light fw-bold text-success" readonly
                                   value="<?= formatCurrency($account['balance']) ?>">
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Signatory information, online banking credentials notes"><?= htmlspecialchars($account['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="accounting.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-save me-1"></i> <?= $isEdit ? 'Update Account' : 'Save Account' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
