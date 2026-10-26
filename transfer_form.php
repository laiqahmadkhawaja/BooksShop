<?php
// transfer_form.php - Internal Account Transfer Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();

$accounts = dbFetchAll("SELECT * FROM accounts WHERE status = 1 ORDER BY id ASC");

$pageTitle = 'Internal Account Transfer';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-right-left text-primary me-2"></i> Account Funds Transfer</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="accounting.php" class="text-decoration-none">Accounting</a></li>
                <li class="breadcrumb-item active" aria-current="page">Transfer</li>
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
            <div class="card-header bg-primary text-white py-3">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-money-bill-transfer me-2"></i> Transfer Funds Between Accounts</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="accounting.php">
                    <input type="hidden" name="save_transfer" value="1">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Source Account (Debit / From) *</label>
                        <select name="from_account_id" class="form-select form-select-lg" required>
                            <option value="">-- Choose From Account --</option>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['account_name']) ?> (Bal: <?= formatCurrency($acc['balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 text-center">
                        <i class="fa-solid fa-arrow-down fs-4 text-muted"></i>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Destination Account (Credit / To) *</label>
                        <select name="to_account_id" class="form-select form-select-lg" required>
                            <option value="">-- Choose Destination Account --</option>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['account_name']) ?> (Bal: <?= formatCurrency($acc['balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Transfer Amount (Rs.) *</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" step="0.01" min="1" name="amount" class="form-control form-control-lg fw-bold text-primary" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Transfer Description / Reason</label>
                        <input type="text" name="description" class="form-control" value="Cash deposit to bank or cash drawer rebalance">
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="accounting.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-check me-1"></i> Complete Transfer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
