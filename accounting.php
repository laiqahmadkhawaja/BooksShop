<?php
// accounting.php - Cash & Bank Accounts, Daily Cashbook, Expenses, and Cash Drawer Closing
require_once __DIR__ . '/config/app.php';
requireAuth();

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$action = $_GET['action'] ?? '';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // 1. Add / Edit Account
    if (isset($_POST['save_account'])) {
        $id = (int)($_POST['account_id'] ?? 0);
        $name = trim($_POST['account_name'] ?? '');
        $bankName = trim($_POST['bank_name'] ?? '');
        $type = $_POST['account_type'] ?? 'cash';
        $number = trim($_POST['account_number'] ?? '');
        $branch = trim($_POST['branch_name'] ?? '');
        $bal = (float)($_POST['opening_balance'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($name) {
            if ($id > 0) {
                dbQuery("UPDATE accounts SET account_name=?, bank_name=?, account_type=?, account_number=?, branch_name=?, notes=? WHERE id=?",
                        [$name, $bankName, $type, $number, $branch, $notes, $id]);
                setFlash('success', "Account '$name' updated successfully.");
            } else {
                dbQuery("INSERT INTO accounts (account_name, bank_name, account_type, account_number, branch_name, balance, notes) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [$name, $bankName, $type, $number, $branch, $bal, $notes]);
                setFlash('success', "Account '$name' created successfully.");
            }
        }
        redirect("accounting.php");
    }

    // 2. Add / Edit Expense & Income Category
    if (isset($_POST['save_expense_cat'])) {
        $cId = (int)($_POST['cat_id'] ?? 0);
        $cName = trim($_POST['cat_name'] ?? '');
        $cType = $_POST['cat_type'] ?? 'expense';
        $cDesc = trim($_POST['cat_desc'] ?? '');

        if ($cName) {
            if ($cId > 0) {
                dbQuery("UPDATE expense_categories SET name=?, type=?, description=? WHERE id=?", [$cName, $cType, $cDesc, $cId]);
                setFlash('success', "Category '$cName' updated.");
            } else {
                dbQuery("INSERT INTO expense_categories (name, type, description) VALUES (?, ?, ?)", [$cName, $cType, $cDesc]);
                setFlash('success', "Category '$cName' added.");
            }
        }
        redirect("accounting.php");
    }

    // Delete Expense Category
    if (isset($_POST['delete_expense_cat'])) {
        $delId = (int)$_POST['cat_id'];
        dbQuery("DELETE FROM expense_categories WHERE id = ?", [$delId]);
        setFlash('success', "Category removed successfully.");
        redirect("accounting.php");
    }

    // 3. Add Expense Voucher
    if (isset($_POST['save_expense'])) {
        $accountId = (int)$_POST['account_id'];
        $category = trim($_POST['category_name'] ?? 'General Expense');
        $amount = (float)$_POST['amount'];
        $refNo = trim($_POST['reference_no'] ?? 'EXP-' . date('Ymd-His'));
        $desc = trim($_POST['description'] ?? '');

        if ($accountId > 0 && $amount > 0) {
            recordCashTransaction('expense', $accountId, $amount, $category, $refNo, $desc);
            setFlash('success', "Expense voucher of " . formatCurrency($amount) . " ($category) recorded.");
        }
        redirect("accounting.php?date=$selectedDate");
    }

    // 4. Add Income / Capital Voucher
    if (isset($_POST['save_income'])) {
        $accountId = (int)$_POST['account_id'];
        $category = trim($_POST['category_name'] ?? 'Other Income');
        $amount = (float)$_POST['amount'];
        $refNo = trim($_POST['reference_no'] ?? 'INC-' . date('Ymd-His'));
        $desc = trim($_POST['description'] ?? '');

        if ($accountId > 0 && $amount > 0) {
            recordCashTransaction('income', $accountId, $amount, $category, $refNo, $desc);
            setFlash('success', "Income voucher of " . formatCurrency($amount) . " recorded.");
        }
        redirect("accounting.php?date=$selectedDate");
    }

    // 5. Internal Account Transfer
    if (isset($_POST['save_transfer'])) {
        $fromId = (int)$_POST['from_account_id'];
        $toId = (int)$_POST['to_account_id'];
        $amount = (float)$_POST['amount'];
        $desc = trim($_POST['description'] ?? 'Internal Transfer');
        $refNo = 'TRF-' . date('Ymd-His');

        if ($fromId > 0 && $toId > 0 && $fromId !== $toId && $amount > 0) {
            recordCashTransaction('transfer', $fromId, $amount, 'Internal Transfer', $refNo, $desc, $toId);
            setFlash('success', "Successfully transferred " . formatCurrency($amount) . " between accounts.");
        } else {
            setFlash('danger', "Please select two different accounts and valid transfer amount.");
        }
        redirect("accounting.php");
    }
}

// Fetch Accounts & Categories
$accounts = dbFetchAll("SELECT * FROM accounts WHERE status = 1 ORDER BY id ASC");
$expenseCategories = dbFetchAll("SELECT * FROM expense_categories WHERE type = 'expense' ORDER BY name ASC");
$incomeCategories = dbFetchAll("SELECT * FROM expense_categories WHERE type = 'income' ORDER BY name ASC");
$allExpenseCats = dbFetchAll("SELECT * FROM expense_categories ORDER BY type ASC, name ASC");
$totalLiquidity = 0;
foreach ($accounts as $a) $totalLiquidity += (float)$a['balance'];

// Daily Transactions
$dayTransactions = dbFetchAll("SELECT ct.*, a.account_name, ta.account_name as to_account_name, u.full_name as user_name
                               FROM cash_transactions ct
                               JOIN accounts a ON a.id = ct.account_id
                               LEFT JOIN accounts ta ON ta.id = ct.to_account_id
                               LEFT JOIN users u ON u.id = ct.created_by
                               WHERE DATE(ct.transaction_date) = ?
                               ORDER BY ct.id DESC", [$selectedDate]);

// Daily Cash Closing Calculation for Register Drawer (Account ID 1)
$drawerInflows = dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = 1 AND DATE(transaction_date) = ? AND transaction_type IN ('sale', 'income', 'customer_payment')", [$selectedDate])['tot'] ?? 0;
$drawerOutflows = dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = 1 AND DATE(transaction_date) = ? AND transaction_type IN ('expense', 'purchase', 'supplier_payment', 'transfer')", [$selectedDate])['tot'] ?? 0;

$pageTitle = 'Accounts & Cashbook';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-wallet text-primary me-2"></i> Cash & Bank Accounts & Daily Cashbook</h4>
        <small class="text-muted">Manage register drawer, bank deposits, expenses, and daily cash reconciliation</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="expense_form.php" class="btn btn-danger btn-sm shadow-sm">
            <i class="fa-solid fa-receipt me-1"></i> Add Expense
        </a>
        <a href="income_form.php" class="btn btn-success btn-sm shadow-sm">
            <i class="fa-solid fa-circle-dollar-to-slot me-1"></i> Add Income
        </a>
        <a href="transfer_form.php" class="btn btn-info text-white btn-sm shadow-sm">
            <i class="fa-solid fa-money-bill-transfer me-1"></i> Transfer Funds
        </a>
        <a href="account_form.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fa-solid fa-plus-circle me-1"></i> Add Account
        </a>
    </div>
</div>

<!-- Account Balances Row -->
<div class="row g-3 mb-4">
    <?php foreach ($accounts as $acc): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card p-3 border-start <?= $acc['account_type'] === 'cash' ? 'border-success' : ($acc['account_type'] === 'bank' ? 'border-primary' : 'border-warning') ?> border-4 shadow-sm bg-white h-100 position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="badge <?= $acc['account_type'] === 'cash' ? 'bg-success-subtle text-success' : ($acc['account_type'] === 'bank' ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning-emphasis') ?> mb-1 text-uppercase" style="font-size: 0.65rem;">
                            <?= htmlspecialchars($acc['account_type']) ?>
                        </span>
                        <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($acc['account_name']) ?></h6>
                        <?php if (!empty($acc['bank_name'])): ?>
                            <div class="text-primary small fw-semibold mt-1">
                                <i class="fa-solid fa-building-columns me-1"></i><?= htmlspecialchars($acc['bank_name']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($acc['branch_name'])): ?>
                            <div class="text-muted small" style="font-size: 0.72rem;">
                                <i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($acc['branch_name']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="text-muted small mt-1 font-monospace" style="font-size: 0.72rem;">
                            <?= htmlspecialchars($acc['account_number'] ?: 'Main Register Drawer') ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 border-0 mb-2" onclick='editAccount(<?= json_encode($acc, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Account Details">
                            <i class="fa-solid fa-pen-to-square text-secondary"></i>
                        </button>
                        <h5 class="fw-bold <?= $acc['balance'] >= 0 ? 'text-success' : 'text-danger' ?> mb-0">
                            <?= formatCurrency($acc['balance']) ?>
                        </h5>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Daily Cash Reconciliation & Cashbook -->
<div class="row g-3">
    <!-- LEFT: Daily Cash Register Drawer Closing (35%) -->
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-cash-register text-success me-2"></i> Register Drawer Daily Closing</h6>
            </div>
            <div class="card-body">
                <div class="text-center p-3 bg-light rounded border mb-3">
                    <span class="small text-muted text-uppercase fw-semibold">Current Drawer Cash</span>
                    <h3 class="fw-bold text-success mb-0"><?= formatCurrency($accounts[0]['balance'] ?? 0) ?></h3>
                </div>

                <div class="list-group list-group-flush small mb-3">
                    <div class="list-group-item d-flex justify-content-between px-0">
                        <span>Selected Closing Date:</span>
                        <strong><?= date('d M, Y', strtotime($selectedDate)) ?></strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0 text-success">
                        <span>Cash Inflows (Sales & Receipts):</span>
                        <strong>+ <?= formatCurrency($drawerInflows) ?></strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0 text-danger">
                        <span>Cash Outflows (Expenses & Payouts):</span>
                        <strong>- <?= formatCurrency($drawerOutflows) ?></strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0 border-top fw-bold">
                        <span>Net Daily Drawer Movement:</span>
                        <span class="<?= ($drawerInflows - $drawerOutflows) >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= formatCurrency($drawerInflows - $drawerOutflows) ?>
                        </span>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <a href="reports.php?report=cash_closing&date=<?= $selectedDate ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-calculator me-1"></i> Full Cash Closing & Denominations
                    </a>
                    <a href="print_report.php?report=cash_closing&date=<?= urlencode($selectedDate) ?>" target="_blank" class="btn btn-outline-dark btn-sm">
                        <i class="fa-solid fa-print me-1"></i> Print Daily Closing Slip (New Page)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Daily Cashbook Log (65%) -->
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-book-journal-whills text-primary me-2"></i> Daily Cashbook & Vouchers</h6>
                <form method="GET" class="d-flex align-items-center gap-2">
                    <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light text-uppercase">
                            <tr>
                                <th>Time</th>
                                <th>Type / Category</th>
                                <th>Account</th>
                                <th>Ref / Voucher</th>
                                <th>Description</th>
                                <th class="text-end">Inflow (+)</th>
                                <th class="text-end">Outflow (-)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dayTransactions)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        No cashbook transactions recorded for <?= date('d M, Y', strtotime($selectedDate)) ?>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dayTransactions as $t): ?>
                                    <?php 
                                        $isInflow = in_array($t['transaction_type'], ['income', 'customer_payment', 'sale']);
                                    ?>
                                    <tr>
                                        <td><?= date('h:i A', strtotime($t['transaction_date'])) ?></td>
                                        <td>
                                            <span class="badge <?= $isInflow ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> text-uppercase" style="font-size: 0.68rem;">
                                                <?= htmlspecialchars($t['transaction_type']) ?>
                                            </span>
                                            <div class="fw-semibold text-dark mt-1"><?= htmlspecialchars($t['category_name'] ?: '-') ?></div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($t['account_name']) ?></div>
                                            <?php if ($t['to_account_name']): ?>
                                                <small class="text-muted">&rarr; <?= htmlspecialchars($t['to_account_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="font-monospace text-primary"><?= htmlspecialchars($t['reference_no'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($t['description'] ?: '-') ?></td>
                                        <td class="text-end fw-bold text-success">
                                            <?= $isInflow ? formatCurrency($t['amount']) : '-' ?>
                                        </td>
                                        <td class="text-end fw-bold text-danger">
                                            <?= !$isInflow ? formatCurrency($t['amount']) : '-' ?>
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

<!-- Accounting vouchers and accounts are managed via dedicated full pages (expense_form.php, income_form.php, transfer_form.php, account_form.php) -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
