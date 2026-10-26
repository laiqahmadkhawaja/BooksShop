<?php
// income_form.php - Record Non-Sale Income Voucher Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();

$accounts = dbFetchAll("SELECT * FROM accounts WHERE status = 1 ORDER BY id ASC");
$categories = dbFetchAll("SELECT * FROM expense_categories WHERE type = 'income' ORDER BY name ASC");

$pageTitle = 'Record Income Voucher';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-arrow-trend-up text-success me-2"></i> Record Income Voucher</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="accounting.php" class="text-decoration-none">Accounting</a></li>
                <li class="breadcrumb-item active" aria-current="page">Income</li>
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
            <div class="card-header bg-success text-white py-3">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-hand-holding-dollar me-2"></i> Cash / Bank Inflow Voucher</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="accounting.php">
                    <input type="hidden" name="save_income" value="1">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Deposit To Account *</label>
                        <select name="account_id" class="form-select form-select-lg" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['account_name']) ?> (Bal: <?= formatCurrency($acc['balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Income Category / Source *</label>
                        <select name="category_name" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="Owner Capital Deposit">Owner Capital Deposit</option>
                            <option value="Photocopy & Printing Profit">Photocopy & Printing Profit</option>
                            <option value="Commission Income">Commission Income</option>
                            <option value="Old Waste Paper / Scrap Sale">Old Waste Paper / Scrap Sale</option>
                            <option value="Other Income">Other Income</option>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Income Amount (Rs.) *</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" step="0.01" min="1" name="amount" class="form-control form-control-lg fw-bold text-success" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Voucher / Receipt #</label>
                            <input type="text" name="reference_no" class="form-control" value="INC-<?= date('Ymd-His') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Description / Notes</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Details of income source"></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="accounting.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-check me-1"></i> Post Income
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
