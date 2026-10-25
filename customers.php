<?php
// customers.php - Customer Profiles & Khata (Ledger) Management
$pageTitle = 'Customers & Khata';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? '';
$selectedCustomerId = (int)($_GET['customer_id'] ?? 0);
$filterDueOnly = isset($_GET['filter']) && $_GET['filter'] === 'due';

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Add / Edit Customer
    if (isset($_POST['save_customer'])) {
        $id = (int)($_POST['customer_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['customer_type'] ?? 'regular';
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $creditLimit = (float)($_POST['credit_limit'] ?? 0);
        $openingBalance = (float)($_POST['opening_balance'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($name) {
            if ($id > 0) {
                dbQuery("UPDATE customers SET name=?, customer_type=?, phone=?, email=?, address=?, credit_limit=?, notes=? WHERE id=?",
                        [$name, $type, $phone, $email, $address, $creditLimit, $notes, $id]);
                setFlash('success', "Customer '$name' updated successfully.");
            } else {
                dbQuery("INSERT INTO customers (name, customer_type, phone, email, address, credit_limit, opening_balance, current_balance, notes)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                         [$name, $type, $phone, $email, $address, $creditLimit, $openingBalance, $openingBalance, $notes]);
                $newId = dbInsertId();
                if ($openingBalance > 0) {
                    recordCustomerLedger($newId, 'opening', null, 'OPENING-BAL', $openingBalance, 0, 'Opening Balance Entry');
                }
                setFlash('success', "Customer '$name' created successfully.");
                $selectedCustomerId = $newId;
            }
        }
        header("Location: customers.php?customer_id=" . ($id ?: $selectedCustomerId));
        exit;
    }

    // 2. Receive Customer Payment Voucher
    if (isset($_POST['receive_payment'])) {
        $customerId = (int)$_POST['customer_id'];
        $accountId = (int)$_POST['account_id'];
        $amount = (float)$_POST['amount'];
        $refNo = trim($_POST['reference_no'] ?? 'RCPT-' . date('Ymd-His'));
        $notes = trim($_POST['notes'] ?? 'Customer Khata Payment Received');

        if ($customerId > 0 && $amount > 0 && $accountId > 0) {
            $cust = dbFetchOne("SELECT name FROM customers WHERE id = ?", [$customerId]);
            // 1. Update customer ledger
            recordCustomerLedger($customerId, 'payment', null, $refNo, 0, $amount, $notes);
            // 2. Inflow into cash/bank account
            recordCashTransaction('customer_payment', $accountId, $amount, 'Customer Payment', $refNo, "Payment from {$cust['name']} - $notes");
            setFlash('success', "Payment of " . formatCurrency($amount) . " received successfully from {$cust['name']}!");
        }
        header("Location: customers.php?customer_id=$customerId");
        exit;
    }
}

// Fetch customers list
$cWhere = ["status = 1"];
if ($filterDueOnly) {
    $cWhere[] = "current_balance > 0";
}
$customers = dbFetchAll("SELECT * FROM customers WHERE " . implode(" AND ", $cWhere) . " ORDER BY current_balance DESC, name ASC");

if (!$selectedCustomerId && !empty($customers)) {
    $selectedCustomerId = $customers[0]['id'];
}

// Selected customer details & ledger
$currentCustomer = null;
$ledgers = [];
$totalReceivables = dbFetchOne("SELECT SUM(current_balance) as tot FROM customers WHERE current_balance > 0")['tot'] ?? 0;

if ($selectedCustomerId) {
    $currentCustomer = dbFetchOne("SELECT * FROM customers WHERE id = ?", [$selectedCustomerId]);
    $ledgers = dbFetchAll("SELECT * FROM customer_ledgers WHERE customer_id = ? ORDER BY id DESC LIMIT 50", [$selectedCustomerId]);
}

$accounts = dbFetchAll("SELECT id, account_name, balance FROM accounts WHERE status = 1");
$shopName = getSetting('shop_name', 'Kitab Ghar');
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-users text-primary me-2"></i> Customer Management & Khata Ledger</h4>
        <small class="text-muted">Manage regular clients, schools, credit balances & WhatsApp payment reminders</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#receivePaymentModal">
            <i class="fa-solid fa-hand-holding-dollar me-1"></i> Receive Payment Voucher
        </button>
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="openNewCustomerModal()">
            <i class="fa-solid fa-user-plus me-1"></i> Add New Customer
        </button>
    </div>
</div>

<!-- Receivables Banner -->
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card p-3 border-start border-danger border-4 shadow-sm bg-white">
            <div class="small text-muted fw-bold">TOTAL OUTSTANDING CUSTOMER RECEIVABLES (KHATA)</div>
            <h3 class="fw-bold text-danger mb-0 mt-1"><?= formatCurrency($totalReceivables) ?></h3>
            <span class="small text-muted">Total amount to be collected from customers & schools</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-3 border-start border-info border-4 shadow-sm bg-white">
            <div class="small text-muted fw-bold">REGISTERED KHATA ACCOUNTS</div>
            <h3 class="fw-bold text-info mb-0 mt-1"><?= count($customers) ?> Customers</h3>
            <span class="small text-muted">
                <a href="customers.php?filter=due" class="text-decoration-none">Filter Due Balances Only &rarr;</a>
            </span>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- LEFT COLUMN: Customer Directory (35%) -->
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-address-book text-primary me-2"></i> Customer Directory</h6>
                <span class="badge bg-light text-secondary border"><?= count($customers) ?></span>
            </div>
            <div class="list-group list-group-flush directory-list-scroll">
                <?php if (empty($customers)): ?>
                    <div class="p-4 text-center text-muted">No customers found.</div>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <a href="customers.php?customer_id=<?= $c['id'] ?><?= $filterDueOnly ? '&filter=due' : '' ?>" class="list-group-item list-group-item-action <?= $selectedCustomerId == $c['id'] ? 'active' : '' ?> py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge <?= $selectedCustomerId == $c['id'] ? 'bg-light text-dark' : 'bg-primary-subtle text-primary' ?>" style="font-size: 0.68rem;">
                                    <?= strtoupper($c['customer_type']) ?>
                                </span>
                                <?php if ($c['current_balance'] > 0): ?>
                                    <span class="badge <?= $selectedCustomerId == $c['id'] ? 'bg-danger text-white' : 'bg-danger-subtle text-danger' ?>" style="font-size: 0.72rem;">
                                        Due: <?= formatCurrency($c['current_balance']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge <?= $selectedCustomerId == $c['id'] ? 'bg-light text-dark' : 'bg-light text-secondary border' ?>" style="font-size: 0.72rem;">
                                        Clear
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h6 class="mb-1 mt-1 fw-bold"><?= htmlspecialchars($c['name']) ?></h6>
                            <small class="<?= $selectedCustomerId == $c['id'] ? 'text-white-50' : 'text-muted' ?>">
                                <?= htmlspecialchars($c['phone'] ?: 'No Phone') ?>
                            </small>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Customer Khata Ledger (65%) -->
    <div class="col-lg-8">
        <?php if (!$currentCustomer): ?>
            <div class="card shadow-sm p-5 text-center text-muted">
                <i class="fa-solid fa-users fa-3x mb-3 text-secondary opacity-50"></i>
                <h5>Select a customer from the left to view their detailed Khata statement.</h5>
            </div>
        <?php else: ?>
            <!-- Customer Profile Card -->
            <div class="card shadow-sm mb-3">
                <div class="card-body p-4 bg-light border-bottom">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <span class="badge bg-primary text-uppercase mb-1"><?= htmlspecialchars($currentCustomer['customer_type']) ?></span>
                            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($currentCustomer['name']) ?></h4>
                            <div class="small text-muted">
                                <?php if ($currentCustomer['phone']): ?>
                                    <i class="fa-solid fa-phone me-1 text-success"></i> <?= htmlspecialchars($currentCustomer['phone']) ?> &bull;
                                <?php endif; ?>
                                <?php if ($currentCustomer['address']): ?>
                                    <i class="fa-solid fa-location-dot me-1 text-danger"></i> <?= htmlspecialchars($currentCustomer['address']) ?> &bull;
                                <?php endif; ?>
                                Credit Limit: <strong><?= formatCurrency($currentCustomer['credit_limit']) ?></strong>
                            </div>
                        </div>

                        <!-- Outstanding Balance Display -->
                        <div class="text-end">
                            <div class="small text-muted">Current Khata Due Balance:</div>
                            <h3 class="fw-bold <?= $currentCustomer['current_balance'] > 0 ? 'text-danger' : 'text-success' ?> mb-0">
                                <?= formatCurrency($currentCustomer['current_balance']) ?>
                            </h3>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                        <!-- Receive Payment -->
                        <button type="button" class="btn btn-success btn-sm" onclick="openReceivePaymentModal(<?= $currentCustomer['id'] ?>, '<?= htmlspecialchars(addslashes($currentCustomer['name'])) ?>', <?= (float)$currentCustomer['current_balance'] ?>)">
                            <i class="fa-solid fa-hand-holding-dollar me-1"></i> Receive Payment
                        </button>

                        <!-- WhatsApp Reminder Button -->
                        <?php if ($currentCustomer['phone'] && $currentCustomer['current_balance'] > 0): ?>
                            <?php
                                $cleanPhone = preg_replace('/[^0-9]/', '', $currentCustomer['phone']);
                                if (str_starts_with($cleanPhone, '0')) {
                                    $cleanPhone = '92' . substr($cleanPhone, 1);
                                }
                                $waMsg = urlencode("Respected " . $currentCustomer['name'] . ",\n\nThis is a polite reminder from " . $shopName . " regarding your outstanding bookshop ledger balance of " . formatCurrency($currentCustomer['current_balance']) . ".\nKindly arrange payment at your earliest convenience.\n\nمحترم صارف، آپ کا کتاب گھر کا بقایا کھاتہ " . formatCurrency($currentCustomer['current_balance']) . " ہے۔ برائے مہربانی جلد از جلد ادائیگی فرمائیں۔ شکریہ!");
                                $waUrl = "https://wa.me/{$cleanPhone}?text={$waMsg}";
                            ?>
                            <a href="<?= $waUrl ?>" target="_blank" class="btn btn-outline-success btn-sm">
                                <i class="fa-brands fa-whatsapp me-1 fs-6"></i> Send WhatsApp Reminder
                            </a>
                        <?php endif; ?>

                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick='openEditCustomerModal(<?= json_encode($currentCustomer) ?>)'>
                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Profile
                        </button>
                        <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print()">
                            <i class="fa-solid fa-print me-1"></i> Print Khata Statement
                        </button>
                    </div>
                </div>

                <!-- Khata Ledger Table -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Ref / Invoice #</th>
                                    <th>Description</th>
                                    <th class="text-danger text-end">Debit (Added Due)</th>
                                    <th class="text-success text-end">Credit (Paid)</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ledgers)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            No transaction records found for this customer.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ledgers as $l): ?>
                                        <tr>
                                            <td class="small">
                                                <div><?= date('d M, Y', strtotime($l['transaction_date'])) ?></div>
                                                <div class="text-muted" style="font-size: 0.7rem;"><?= date('h:i A', strtotime($l['transaction_date'])) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.68rem;">
                                                    <?= htmlspecialchars($l['transaction_type']) ?>
                                                </span>
                                            </td>
                                            <td class="font-monospace small text-primary">
                                                <strong><?= htmlspecialchars($l['invoice_no'] ?: '-') ?></strong>
                                            </td>
                                            <td class="small"><?= htmlspecialchars($l['description']) ?></td>
                                            <td class="text-danger text-end fw-semibold">
                                                <?= $l['debit'] > 0 ? formatCurrency($l['debit']) : '-' ?>
                                            </td>
                                            <td class="text-success text-end fw-semibold">
                                                <?= $l['credit'] > 0 ? formatCurrency($l['credit']) : '-' ?>
                                            </td>
                                            <td class="text-end fw-bold">
                                                <?= formatCurrency($l['balance']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Receive Payment Voucher -->
<div class="modal fade" id="receivePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="customers.php">
                <input type="hidden" name="receive_payment" value="1">
                <input type="hidden" name="customer_id" id="rcpt_customer_id" value="<?= $selectedCustomerId ?>">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-hand-holding-dollar me-2"></i> Receive Customer Payment Voucher</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Customer *</label>
                        <select name="customer_id" id="rcpt_cust_select" class="form-select" required>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $selectedCustomerId == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?> (Due: <?= formatCurrency($c['current_balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Amount Received (Rs.) *</label>
                        <input type="number" step="0.01" name="amount" id="rcpt_amount" class="form-control form-control-lg fw-bold text-success" required>
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
                        <label class="form-label small fw-bold">Receipt / Voucher #</label>
                        <input type="text" name="reference_no" class="form-control form-control-sm" value="RCPT-<?= date('Ymd-His') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Payment Note</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Cleared October booklist installment">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-check me-1"></i> Confirm & Save Voucher</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add / Edit Customer -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="customers.php">
                <input type="hidden" name="save_customer" value="1">
                <input type="hidden" name="customer_id" id="cust_id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="customerModalTitle"><i class="fa-solid fa-user-plus text-primary me-2"></i> Add Customer Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Customer / School Name *</label>
                        <input type="text" name="name" id="cust_name" class="form-control" required placeholder="e.g. Beaconhouse or Tariq Ahmed">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Customer Type</label>
                            <select name="customer_type" id="cust_type" class="form-select">
                                <option value="regular">Regular Customer</option>
                                <option value="walkin">Walk-in</option>
                                <option value="wholesale">Wholesale Dealer</option>
                                <option value="institution">School / Institution</option>
                                <option value="school_parent">Student Parent</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Phone (WhatsApp) *</label>
                            <input type="text" name="phone" id="cust_phone" class="form-control" placeholder="03001234567">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Email Address</label>
                        <input type="email" name="email" id="cust_email" class="form-control" placeholder="customer@example.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Physical Address / Campus</label>
                        <textarea name="address" id="cust_address" class="form-control" rows="2" placeholder="Full address"></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Credit Limit (Rs.)</label>
                            <input type="number" step="0.01" name="credit_limit" id="cust_limit" class="form-control" value="0">
                        </div>
                        <div class="col-6" id="openingBalDiv">
                            <label class="form-label small fw-bold">Opening Due Balance</label>
                            <input type="number" step="0.01" name="opening_balance" id="cust_opening" class="form-control" value="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Notes</label>
                        <input type="text" name="notes" id="cust_notes" class="form-control" placeholder="e.g. 10% discount on syllabus sets">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openNewCustomerModal() {
    document.getElementById('customerModalTitle').innerHTML = '<i class="fa-solid fa-user-plus text-primary me-2"></i> Add Customer Profile';
    document.getElementById('cust_id').value = '0';
    document.getElementById('cust_name').value = '';
    document.getElementById('cust_type').value = 'regular';
    document.getElementById('cust_phone').value = '';
    document.getElementById('cust_email').value = '';
    document.getElementById('cust_address').value = '';
    document.getElementById('cust_limit').value = '0';
    document.getElementById('cust_opening').value = '0';
    document.getElementById('openingBalDiv').style.display = 'block';
    document.getElementById('cust_notes').value = '';
}

function openEditCustomerModal(c) {
    document.getElementById('customerModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-secondary me-2"></i> Edit Customer Profile';
    document.getElementById('cust_id').value = c.id;
    document.getElementById('cust_name').value = c.name;
    document.getElementById('cust_type').value = c.customer_type;
    document.getElementById('cust_phone').value = c.phone || '';
    document.getElementById('cust_email').value = c.email || '';
    document.getElementById('cust_address').value = c.address || '';
    document.getElementById('cust_limit').value = c.credit_limit || '0';
    document.getElementById('openingBalDiv').style.display = 'none'; // only on create
    document.getElementById('cust_notes').value = c.notes || '';
    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

function openReceivePaymentModal(custId, custName, due) {
    document.getElementById('rcpt_cust_select').value = custId;
    document.getElementById('rcpt_amount').value = due > 0 ? due : '';
    new bootstrap.Modal(document.getElementById('receivePaymentModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
