<?php
// accounting.php - Cash & Bank Accounts, Daily Cashbook, Expenses, and Cash Drawer Closing
$pageTitle = 'Accounts & Cashbook';
require_once __DIR__ . '/includes/header.php';

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
        header("Location: accounting.php");
        exit;
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
        header("Location: accounting.php");
        exit;
    }

    // Delete Expense Category
    if (isset($_POST['delete_expense_cat'])) {
        $delId = (int)$_POST['cat_id'];
        dbQuery("DELETE FROM expense_categories WHERE id = ?", [$delId]);
        setFlash('success', "Category removed successfully.");
        header("Location: accounting.php");
        exit;
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
        header("Location: accounting.php?date=$selectedDate");
        exit;
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
        header("Location: accounting.php?date=$selectedDate");
        exit;
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
        header("Location: accounting.php");
        exit;
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
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-wallet text-primary me-2"></i> Cash & Bank Accounts & Daily Cashbook</h4>
        <small class="text-muted">Manage register drawer, bank deposits, expenses, and daily cash reconciliation</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-danger btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#expenseModal">
            <i class="fa-solid fa-receipt me-1"></i> Add Expense
        </button>
        <button type="button" class="btn btn-success btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#incomeModal">
            <i class="fa-solid fa-circle-dollar-to-slot me-1"></i> Add Income
        </button>
        <button type="button" class="btn btn-info text-white btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#transferModal">
            <i class="fa-solid fa-money-bill-transfer me-1"></i> Transfer Funds
        </button>
        <button type="button" class="btn btn-outline-dark btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#manageExpenseCatModal">
            <i class="fa-solid fa-tags me-1"></i> Expense Categories
        </button>
        <button type="button" class="btn btn-primary btn-sm shadow-sm" onclick="openNewAccountModal()">
            <i class="fa-solid fa-plus me-1"></i> Add Account
        </button>
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

<!-- Modal: Add Expense Voucher -->
<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="accounting.php?date=<?= $selectedDate ?>">
                <input type="hidden" name="save_expense" value="1">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-receipt me-2"></i> Add Expense Voucher</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold mb-0">Expense Category *</label>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#manageExpenseCatModal" class="small text-primary text-decoration-none">+ Manage Categories</a>
                        </div>
                        <select name="category_name" class="form-select" required>
                            <?php if (!empty($expenseCategories)): ?>
                                <?php foreach ($expenseCategories as $ec): ?>
                                    <option value="<?= htmlspecialchars($ec['name']) ?>"><?= htmlspecialchars($ec['name']) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="Shop Rent">Shop Rent</option>
                                <option value="Staff Salaries & Wages">Staff Salaries & Wages</option>
                                <option value="Electricity & Utilities">Electricity & Utilities</option>
                                <option value="Tea & Refreshments">Tea & Refreshments</option>
                                <option value="Cartage & Transport">Cartage & Transport</option>
                                <option value="Miscellaneous Operating Expense">Miscellaneous Operating Expense</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Amount (Rs.) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-danger" required placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Paid From Account *</label>
                        <select name="account_id" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['account_name']) ?> (<?= formatCurrency($acc['balance']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Bill / Voucher Reference #</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. LESCO-OCT-2026 or EXP-101">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Details / Notes</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Description of expense or payee details"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fa-solid fa-save me-1"></i> Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Income Voucher -->
<div class="modal fade" id="incomeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="accounting.php?date=<?= $selectedDate ?>">
                <input type="hidden" name="save_income" value="1">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-circle-dollar-to-slot me-2"></i> Add Income / Capital Voucher</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold mb-0">Income Category *</label>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#manageExpenseCatModal" class="small text-success text-decoration-none">+ Manage Categories</a>
                        </div>
                        <select name="category_name" class="form-select" required>
                            <?php if (!empty($incomeCategories)): ?>
                                <?php foreach ($incomeCategories as $ic): ?>
                                    <option value="<?= htmlspecialchars($ic['name']) ?>"><?= htmlspecialchars($ic['name']) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="Owner Capital Deposit">Owner Capital Deposit</option>
                                <option value="Photocopy & Printing Services">Photocopy & Printing Services</option>
                                <option value="Used Book Commission / Profit">Used Book Commission</option>
                                <option value="School Syllabus Royalty / Rebate">School Syllabus Rebate</option>
                                <option value="Other Miscellaneous Income">Other Miscellaneous Income</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Amount (Rs.) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-success" required placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Deposit to Account *</label>
                        <select name="account_id" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['account_name']) ?> (<?= formatCurrency($acc['balance']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Details</label>
                        <input type="text" name="description" class="form-control" placeholder="Source, person name, or notes">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-save me-1"></i> Save Income</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Funds Transfer -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="accounting.php">
                <input type="hidden" name="save_transfer" value="1">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-money-bill-transfer me-2"></i> Internal Account Transfer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Transfer From (Deduct) *</label>
                        <select name="from_account_id" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['account_name']) ?> (<?= formatCurrency($acc['balance']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Transfer To (Deposit) *</label>
                        <select name="to_account_id" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['account_name']) ?> (<?= formatCurrency($acc['balance']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Amount to Transfer (Rs.) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold" required placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Transfer Memo / Reference</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Deposited cash in Meezan Bank branch deposit slip #9821">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white"><i class="fa-solid fa-check me-1"></i> Confirm Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add / Edit Account -->
<div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="accounting.php">
                <input type="hidden" name="save_account" value="1">
                <input type="hidden" name="account_id" id="modalAccountId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalAccountTitle"><i class="fa-solid fa-wallet text-primary me-2"></i> Add Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Account Name / Title *</label>
                        <input type="text" name="account_name" id="modalAccountName" class="form-control" required placeholder="e.g. Meezan Bank Current A/C or Cash Counter 2">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Account Type *</label>
                            <select name="account_type" id="modalAccountType" class="form-select">
                                <option value="bank">Bank Account</option>
                                <option value="cash">Cash in Hand / Drawer</option>
                                <option value="wallet">Mobile Wallet (EasyPaisa / JazzCash)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Bank Name</label>
                            <input type="text" name="bank_name" id="modalBankName" class="form-control" placeholder="e.g. Meezan Bank, HBL, Allied Bank">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Account Number / IBAN</label>
                            <input type="text" name="account_number" id="modalAccountNumber" class="form-control font-monospace" placeholder="e.g. PK00MEZN0000123456789">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Branch Name / Code</label>
                            <input type="text" name="branch_name" id="modalBranchName" class="form-control" placeholder="e.g. Main Market Gulberg (0123)">
                        </div>
                    </div>
                    <div class="mb-3" id="openingBalGroup">
                        <label class="form-label small fw-bold">Opening Balance (Rs.)</label>
                        <input type="number" step="0.01" name="opening_balance" id="modalOpeningBal" class="form-control" value="0.00">
                        <small class="text-muted">Opening balance is recorded once when adding a new account.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Notes / Description</label>
                        <textarea name="notes" id="modalAccountNotes" class="form-control" rows="2" placeholder="Signatory details, branch contact, or notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Manage Expense & Income Categories -->
<div class="modal fade" id="manageExpenseCatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-tags text-warning me-2"></i> Manage Expense & Income Categories</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Add New Category Form -->
                <div class="card bg-light border p-3 mb-4">
                    <h6 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-plus-circle text-success me-1"></i> Add / Edit Category</h6>
                    <form method="POST" action="accounting.php" class="row g-2 align-items-end">
                        <input type="hidden" name="save_expense_cat" value="1">
                        <input type="hidden" name="cat_id" id="editCatId" value="0">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Category Name *</label>
                            <input type="text" name="cat_name" id="editCatName" class="form-control form-control-sm" required placeholder="e.g. Generator Fuel, Marketing, Photocopy">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Type *</label>
                            <select name="cat_type" id="editCatType" class="form-select form-select-sm">
                                <option value="expense">Expense (Outflow)</option>
                                <option value="income">Income (Inflow)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Description</label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="cat_desc" id="editCatDesc" class="form-control" placeholder="Optional notes">
                                <button type="submit" class="btn btn-success" id="btnSaveCat">Save</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Existing Categories Table -->
                <h6 class="fw-bold text-dark mb-2">Existing Categories (<?= count($allExpenseCats) ?>)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Category Name</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allExpenseCats)): ?>
                                <tr><td colspan="4" class="text-center py-3 text-muted">No custom categories found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($allExpenseCats as $ec): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($ec['name']) ?></td>
                                        <td>
                                            <span class="badge <?= $ec['type'] === 'expense' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?> text-uppercase">
                                                <?= htmlspecialchars($ec['type']) ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small"><?= htmlspecialchars($ec['description'] ?: '-') ?></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" onclick='editExpenseCat(<?= json_encode($ec, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Category">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <form method="POST" action="accounting.php" class="d-inline" onsubmit="return confirm('Remove category <?= htmlspecialchars(addslashes($ec['name'])) ?>?')">
                                                <input type="hidden" name="delete_expense_cat" value="1">
                                                <input type="hidden" name="cat_id" value="<?= $ec['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete Category">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openNewAccountModal() {
    document.getElementById('modalAccountId').value = '0';
    document.getElementById('modalAccountTitle').innerHTML = '<i class="fa-solid fa-wallet text-primary me-2"></i> Add Account';
    document.getElementById('modalAccountName').value = '';
    document.getElementById('modalBankName').value = '';
    document.getElementById('modalAccountType').value = 'bank';
    document.getElementById('modalAccountNumber').value = '';
    document.getElementById('modalBranchName').value = '';
    document.getElementById('modalOpeningBal').value = '0.00';
    document.getElementById('modalAccountNotes').value = '';
    document.getElementById('openingBalGroup').style.display = 'block';
    new bootstrap.Modal(document.getElementById('accountModal')).show();
}

function editAccount(acc) {
    document.getElementById('modalAccountId').value = acc.id;
    document.getElementById('modalAccountTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-primary me-2"></i> Edit Account Details';
    document.getElementById('modalAccountName').value = acc.account_name || '';
    document.getElementById('modalBankName').value = acc.bank_name || '';
    document.getElementById('modalAccountType').value = acc.account_type || 'bank';
    document.getElementById('modalAccountNumber').value = acc.account_number || '';
    document.getElementById('modalBranchName').value = acc.branch_name || '';
    document.getElementById('modalOpeningBal').value = acc.balance || '0.00';
    document.getElementById('modalAccountNotes').value = acc.notes || '';
    // Hide opening balance when editing to prevent ledger corruption
    document.getElementById('openingBalGroup').style.display = 'none';
    new bootstrap.Modal(document.getElementById('accountModal')).show();
}

function editExpenseCat(cat) {
    document.getElementById('editCatId').value = cat.id;
    document.getElementById('editCatName').value = cat.name;
    document.getElementById('editCatType').value = cat.type;
    document.getElementById('editCatDesc').value = cat.description || '';
    document.getElementById('btnSaveCat').textContent = 'Update';
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($action === 'expense'): ?>
        new bootstrap.Modal(document.getElementById('expenseModal')).show();
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
