<?php
// accounting.php - Cash & Bank Accounts, Daily Cashbook, Expenses, and Cash Drawer Closing
$pageTitle = 'Accounts & Cashbook';
require_once __DIR__ . '/includes/header.php';

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$action = $_GET['action'] ?? '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Add / Edit Account
    if (isset($_POST['save_account'])) {
        $id = (int)($_POST['account_id'] ?? 0);
        $name = trim($_POST['account_name'] ?? '');
        $type = $_POST['account_type'] ?? 'cash';
        $number = trim($_POST['account_number'] ?? '');
        $bal = (float)($_POST['opening_balance'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($name) {
            if ($id > 0) {
                dbQuery("UPDATE accounts SET account_name=?, account_type=?, account_number=?, notes=? WHERE id=?",
                        [$name, $type, $number, $notes, $id]);
                setFlash('success', "Account updated.");
            } else {
                dbQuery("INSERT INTO accounts (account_name, account_type, account_number, balance, notes) VALUES (?, ?, ?, ?, ?)",
                        [$name, $type, $number, $bal, $notes]);
                setFlash('success', "Account created.");
            }
        }
        header("Location: accounting.php");
        exit;
    }

    // 2. Add Expense Voucher
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

    // 3. Add Income / Capital Voucher
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

    // 4. Internal Account Transfer
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

// Fetch Accounts
$accounts = dbFetchAll("SELECT * FROM accounts WHERE status = 1 ORDER BY id ASC");
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
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-danger btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#expenseModal">
            <i class="fa-solid fa-receipt me-1"></i> Add Expense Voucher
        </button>
        <button type="button" class="btn btn-success btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#incomeModal">
            <i class="fa-solid fa-circle-dollar-to-slot me-1"></i> Add Income Voucher
        </button>
        <button type="button" class="btn btn-info text-white btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#transferModal">
            <i class="fa-solid fa-money-bill-transfer me-1"></i> Transfer Funds
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#accountModal">
            <i class="fa-solid fa-plus me-1"></i> Add Account
        </button>
    </div>
</div>

<!-- Account Balances Row -->
<div class="row g-3 mb-4">
    <?php foreach ($accounts as $acc): ?>
        <div class="col-xl-3 col-md-6">
            <div class="card p-3 border-start <?= $acc['account_type'] === 'cash' ? 'border-success' : ($acc['account_type'] === 'bank' ? 'border-primary' : 'border-warning') ?> border-4 shadow-sm bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="badge <?= $acc['account_type'] === 'cash' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' ?> mb-1 text-uppercase" style="font-size: 0.65rem;">
                            <?= htmlspecialchars($acc['account_type']) ?>
                        </span>
                        <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($acc['account_name']) ?></h6>
                        <small class="text-muted"><?= htmlspecialchars($acc['account_number'] ?: 'Main Register') ?></small>
                    </div>
                    <div class="text-end">
                        <h5 class="fw-bold <?= $acc['balance'] >= 0 ? 'text-success' : 'text-danger' ?> mb-0 mt-2">
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

                <button type="button" class="btn btn-outline-dark btn-sm w-100" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Print Cash Closing Slip
                </button>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="accounting.php?date=<?= $selectedDate ?>">
                <input type="hidden" name="save_expense" value="1">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-receipt me-2"></i> Add Expense Voucher</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Expense Category *</label>
                        <select name="category_name" class="form-select" required>
                            <option value="Shop Rent">Shop Rent</option>
                            <option value="Staff Salaries & Wages">Staff Salaries & Wages</option>
                            <option value="Electricity & Utilities">Electricity & Utilities</option>
                            <option value="Tea & Refreshments">Tea & Refreshments</option>
                            <option value="Cartage & Transport">Cartage & Transport</option>
                            <option value="Stationery Packaging Material">Stationery Packaging & Shoppers</option>
                            <option value="Shop Maintenance & Repairs">Shop Maintenance & Repairs</option>
                            <option value="Miscellaneous Operating Expense">Miscellaneous Operating Expense</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Amount (Rs.) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-danger" required>
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
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. LESCO-OCT-2026">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Details / Notes</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Description of expense"></textarea>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="accounting.php?date=<?= $selectedDate ?>">
                <input type="hidden" name="save_income" value="1">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-circle-dollar-to-slot me-2"></i> Add Income / Capital Voucher</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Income Category *</label>
                        <select name="category_name" class="form-select" required>
                            <option value="Owner Capital Deposit">Owner Capital Deposit</option>
                            <option value="Photocopy & Printing Services">Photocopy & Printing Services</option>
                            <option value="Used Book Commission / Profit">Used Book Commission</option>
                            <option value="School Syllabus Royalty / Rebate">School Syllabus Rebate</option>
                            <option value="Other Miscellaneous Income">Other Miscellaneous Income</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Amount (Rs.) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-success" required>
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
                        <input type="text" name="description" class="form-control" placeholder="Source and notes">
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
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="accounting.php">
                <input type="hidden" name="save_transfer" value="1">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-money-bill-transfer me-2"></i> Internal Account Transfer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Transfer From *</label>
                        <select name="from_account_id" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['account_name']) ?> (<?= formatCurrency($acc['balance']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Transfer To *</label>
                        <select name="to_account_id" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['account_name']) ?> (<?= formatCurrency($acc['balance']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Amount to Transfer (Rs.) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Transfer Memo</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Deposited cash to Meezan Bank branch">
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

<!-- Modal: Add New Account -->
<div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="accounting.php">
                <input type="hidden" name="save_account" value="1">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-wallet text-primary me-2"></i> Add Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Account Name *</label>
                        <input type="text" name="account_name" class="form-control" required placeholder="e.g. Allied Bank Ltd or Branch Drawer 2">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Account Type *</label>
                        <select name="account_type" class="form-select">
                            <option value="bank">Bank Account</option>
                            <option value="cash">Cash in Hand / Drawer</option>
                            <option value="wallet">Mobile Wallet (EasyPaisa / JazzCash)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Account / IBAN Number</label>
                        <input type="text" name="account_number" class="form-control" placeholder="PK... or mobile #">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Opening Balance (Rs.)</label>
                        <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($action === 'expense'): ?>
        new bootstrap.Modal(document.getElementById('expenseModal')).show();
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
