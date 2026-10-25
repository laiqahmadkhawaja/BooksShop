<?php
// suppliers.php - Suppliers, Publishers & Payables Management
$pageTitle = 'Suppliers & Publishers';
require_once __DIR__ . '/includes/header.php';

$selectedSupplierId = (int)($_GET['supplier_id'] ?? 0);
$filterDueOnly = isset($_GET['filter']) && $_GET['filter'] === 'due';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Add / Edit Supplier
    if (isset($_POST['save_supplier'])) {
        $id = (int)($_POST['supplier_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $company = trim($_POST['company_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $openingBal = (float)($_POST['opening_balance'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($name) {
            if ($id > 0) {
                dbQuery("UPDATE suppliers SET name=?, company_name=?, phone=?, email=?, address=?, notes=? WHERE id=?",
                        [$name, $company, $phone, $email, $address, $notes, $id]);
                setFlash('success', "Supplier '$name' updated successfully.");
            } else {
                dbQuery("INSERT INTO suppliers (name, company_name, phone, email, address, opening_balance, current_balance, notes)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                         [$name, $company, $phone, $email, $address, $openingBal, $openingBal, $notes]);
                $newId = dbInsertId();
                if ($openingBal > 0) {
                    recordSupplierLedger($newId, 'opening', null, 'OPENING-BAL', 0, $openingBal, 'Opening Balance Entry');
                }
                setFlash('success', "Supplier '$name' created successfully.");
                $selectedSupplierId = $newId;
            }
        }
        header("Location: suppliers.php?supplier_id=" . ($id ?: $selectedSupplierId));
        exit;
    }

    // 2. Pay Supplier Voucher
    if (isset($_POST['pay_supplier'])) {
        $supplierId = (int)$_POST['supplier_id'];
        $accountId = (int)$_POST['account_id'];
        $amount = (float)$_POST['amount'];
        $refNo = trim($_POST['reference_no'] ?? 'PV-' . date('Ymd-His'));
        $notes = trim($_POST['notes'] ?? 'Supplier Bill Payment');

        if ($supplierId > 0 && $amount > 0 && $accountId > 0) {
            $supp = dbFetchOne("SELECT name FROM suppliers WHERE id = ?", [$supplierId]);
            // 1. Supplier Ledger debit (reduces payable)
            recordSupplierLedger($supplierId, 'payment', null, $refNo, $amount, 0, $notes);
            // 2. Cash Outflow from account
            recordCashTransaction('supplier_payment', $accountId, $amount, 'Supplier Payment', $refNo, "Payment to {$supp['name']} - $notes");
            setFlash('success', "Payment voucher of " . formatCurrency($amount) . " to {$supp['name']} saved successfully!");
        }
        header("Location: suppliers.php?supplier_id=$supplierId");
        exit;
    }
}

// Fetch suppliers list
$sWhere = ["status = 1"];
if ($filterDueOnly) {
    $sWhere[] = "current_balance > 0";
}
$suppliers = dbFetchAll("SELECT * FROM suppliers WHERE " . implode(" AND ", $sWhere) . " ORDER BY current_balance DESC, name ASC");

if (!$selectedSupplierId && !empty($suppliers)) {
    $selectedSupplierId = $suppliers[0]['id'];
}

// Selected supplier & ledgers
$currentSupplier = null;
$ledgers = [];
$totalPayables = dbFetchOne("SELECT SUM(current_balance) as tot FROM suppliers WHERE current_balance > 0")['tot'] ?? 0;

if ($selectedSupplierId) {
    $currentSupplier = dbFetchOne("SELECT * FROM suppliers WHERE id = ?", [$selectedSupplierId]);
    $ledgers = dbFetchAll("SELECT * FROM supplier_ledgers WHERE supplier_id = ? ORDER BY id DESC LIMIT 50", [$selectedSupplierId]);
}

$accounts = dbFetchAll("SELECT id, account_name, balance FROM accounts WHERE status = 1");
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-truck-field text-primary me-2"></i> Supplier & Publisher Ledgers</h4>
        <small class="text-muted">Manage book publishers, stationery manufacturers, invoices & payment vouchers</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#paySupplierModal">
            <i class="fa-solid fa-money-bill-transfer me-1"></i> Pay Supplier Voucher
        </button>
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="openNewSupplierModal()">
            <i class="fa-solid fa-plus-circle me-1"></i> Add Supplier / Publisher
        </button>
    </div>
</div>

<!-- Payables Banner -->
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card p-3 border-start border-danger border-4 shadow-sm bg-white">
            <div class="small text-muted fw-bold">TOTAL OUTSTANDING SUPPLIER PAYABLES</div>
            <h3 class="fw-bold text-danger mb-0 mt-1"><?= formatCurrency($totalPayables) ?></h3>
            <span class="small text-muted">Total amount to be paid to book publishers & distributors</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-3 border-start border-info border-4 shadow-sm bg-white">
            <div class="small text-muted fw-bold">REGISTERED PUBLISHERS & SUPPLIERS</div>
            <h3 class="fw-bold text-info mb-0 mt-1"><?= count($suppliers) ?> Accounts</h3>
            <span class="small text-muted">
                <a href="suppliers.php?filter=due" class="text-decoration-none">Filter Due Payables Only &rarr;</a>
            </span>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- LEFT: Suppliers List (35%) -->
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-building text-primary me-2"></i> Suppliers Directory</h6>
                <span class="badge bg-light text-secondary border"><?= count($suppliers) ?></span>
            </div>
            <div class="list-group list-group-flush directory-list-scroll">
                <?php if (empty($suppliers)): ?>
                    <div class="p-4 text-center text-muted">No suppliers found.</div>
                <?php else: ?>
                    <?php foreach ($suppliers as $s): ?>
                        <a href="suppliers.php?supplier_id=<?= $s['id'] ?><?= $filterDueOnly ? '&filter=due' : '' ?>" class="list-group-item list-group-item-action <?= $selectedSupplierId == $s['id'] ? 'active' : '' ?> py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge <?= $selectedSupplierId == $s['id'] ? 'bg-light text-dark' : 'bg-primary-subtle text-primary' ?>" style="font-size: 0.68rem;">
                                    <?= htmlspecialchars($s['company_name'] ?: 'Publisher') ?>
                                </span>
                                <?php if ($s['current_balance'] > 0): ?>
                                    <span class="badge <?= $selectedSupplierId == $s['id'] ? 'bg-danger text-white' : 'bg-danger-subtle text-danger' ?>" style="font-size: 0.72rem;">
                                        Payable: <?= formatCurrency($s['current_balance']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge <?= $selectedSupplierId == $s['id'] ? 'bg-light text-dark' : 'bg-light text-secondary border' ?>" style="font-size: 0.72rem;">
                                        Paid
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h6 class="mb-1 mt-1 fw-bold"><?= htmlspecialchars($s['name']) ?></h6>
                            <small class="<?= $selectedSupplierId == $s['id'] ? 'text-white-50' : 'text-muted' ?>">
                                <?= htmlspecialchars($s['phone'] ?: 'No Phone') ?>
                            </small>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Supplier Ledger (65%) -->
    <div class="col-lg-8">
        <?php if (!$currentSupplier): ?>
            <div class="card shadow-sm p-5 text-center text-muted">
                <i class="fa-solid fa-truck-field fa-3x mb-3 text-secondary opacity-50"></i>
                <h5>Select a supplier from the left to view their detailed payable account ledger.</h5>
            </div>
        <?php else: ?>
            <!-- Supplier Profile Card -->
            <div class="card shadow-sm mb-3">
                <div class="card-body p-4 bg-light border-bottom">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <span class="badge bg-secondary mb-1"><?= htmlspecialchars($currentSupplier['company_name']) ?></span>
                            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($currentSupplier['name']) ?></h4>
                            <div class="small text-muted">
                                <?php if ($currentSupplier['phone']): ?>
                                    <i class="fa-solid fa-phone me-1 text-success"></i> <?= htmlspecialchars($currentSupplier['phone']) ?> &bull;
                                <?php endif; ?>
                                <?php if ($currentSupplier['address']): ?>
                                    <i class="fa-solid fa-location-dot me-1 text-danger"></i> <?= htmlspecialchars($currentSupplier['address']) ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Current Balance Display -->
                        <div class="text-end">
                            <div class="small text-muted">Current Balance Owed:</div>
                            <h3 class="fw-bold <?= $currentSupplier['current_balance'] > 0 ? 'text-danger' : 'text-success' ?> mb-0">
                                <?= formatCurrency($currentSupplier['current_balance']) ?>
                            </h3>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                        <!-- Pay Supplier -->
                        <button type="button" class="btn btn-danger btn-sm" onclick="openPaySupplierModal(<?= $currentSupplier['id'] ?>, '<?= htmlspecialchars(addslashes($currentSupplier['name'])) ?>', <?= (float)$currentSupplier['current_balance'] ?>)">
                            <i class="fa-solid fa-money-bill-transfer me-1"></i> Make Payment to Supplier
                        </button>
                        <a href="purchases.php?action=new&supplier_id=<?= $currentSupplier['id'] ?>" class="btn btn-success btn-sm">
                            <i class="fa-solid fa-cart-plus me-1"></i> Record Inward Purchase
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick='openEditSupplierModal(<?= json_encode($currentSupplier) ?>)'>
                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Supplier
                        </button>
                        <a href="print_report.php?report=supplier_statement&supplier_id=<?= $selectedSupplierId ?>" target="_blank" class="btn btn-outline-dark btn-sm">
                            <i class="fa-solid fa-print me-1"></i> Print Supplier Ledger (New Page)
                        </a>
                    </div>
                </div>

                <!-- Ledger Table -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Invoice # / Ref</th>
                                    <th>Description</th>
                                    <th class="text-danger text-end">Credit (Bill Added)</th>
                                    <th class="text-success text-end">Debit (Paid)</th>
                                    <th class="text-end">Balance Owed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ledgers)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            No ledger transactions recorded for this supplier.
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
                                                <?= $l['credit'] > 0 ? formatCurrency($l['credit']) : '-' ?>
                                            </td>
                                            <td class="text-success text-end fw-semibold">
                                                <?= $l['debit'] > 0 ? formatCurrency($l['debit']) : '-' ?>
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

<!-- Modal: Pay Supplier Voucher -->
<div class="modal fade" id="paySupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="suppliers.php">
                <input type="hidden" name="pay_supplier" value="1">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-money-bill-transfer me-2"></i> Pay Supplier / Publisher Voucher</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Supplier *</label>
                        <select name="supplier_id" id="pay_supp_select" class="form-select" required>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $selectedSupplierId == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?> (Payable: <?= formatCurrency($s['current_balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Payment Amount (Rs.) *</label>
                        <input type="number" step="0.01" name="amount" id="pay_amount" class="form-control form-control-lg fw-bold text-danger" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pay From Account *</label>
                        <select name="account_id" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['account_name']) ?> (<?= formatCurrency($acc['balance']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Voucher / Cheque #</label>
                        <input type="text" name="reference_no" class="form-control form-control-sm" value="PV-<?= date('Ymd-His') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Notes</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Paid against OUP October textbooks invoice">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fa-solid fa-check me-1"></i> Confirm & Make Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add / Edit Supplier -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="suppliers.php">
                <input type="hidden" name="save_supplier" value="1">
                <input type="hidden" name="supplier_id" id="supp_id" value="0">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="supplierModalTitle"><i class="fa-solid fa-truck-field text-primary me-2"></i> Add Supplier / Publisher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Supplier / Contact Name *</label>
                        <input type="text" name="name" id="supp_name" class="form-control" required placeholder="e.g. Oxford University Press or Tariq Book Store">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Company / Publishing House</label>
                        <input type="text" name="company_name" id="supp_company" class="form-control" placeholder="e.g. OUP Pakistan or PCTB">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Phone</label>
                            <input type="text" name="phone" id="supp_phone" class="form-control" placeholder="042-35756611">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Email</label>
                            <input type="email" name="email" id="supp_email" class="form-control" placeholder="orders@publisher.com">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Address / Warehouse</label>
                        <textarea name="address" id="supp_address" class="form-control" rows="2" placeholder="Urdu Bazaar, Lahore"></textarea>
                    </div>
                    <div class="mb-3" id="suppOpeningDiv">
                        <label class="form-label small fw-bold">Opening Payable Balance (Rs.)</label>
                        <input type="number" step="0.01" name="opening_balance" id="supp_opening" class="form-control" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Notes</label>
                        <input type="text" name="notes" id="supp_notes" class="form-control" placeholder="Payment terms: Net 30 days">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openNewSupplierModal() {
    document.getElementById('supplierModalTitle').innerHTML = '<i class="fa-solid fa-truck-field text-primary me-2"></i> Add Supplier / Publisher';
    document.getElementById('supp_id').value = '0';
    document.getElementById('supp_name').value = '';
    document.getElementById('supp_company').value = '';
    document.getElementById('supp_phone').value = '';
    document.getElementById('supp_email').value = '';
    document.getElementById('supp_address').value = '';
    document.getElementById('supp_opening').value = '0';
    document.getElementById('suppOpeningDiv').style.display = 'block';
    document.getElementById('supp_notes').value = '';
}

function openEditSupplierModal(s) {
    document.getElementById('supplierModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-secondary me-2"></i> Edit Supplier / Publisher';
    document.getElementById('supp_id').value = s.id;
    document.getElementById('supp_name').value = s.name;
    document.getElementById('supp_company').value = s.company_name || '';
    document.getElementById('supp_phone').value = s.phone || '';
    document.getElementById('supp_email').value = s.email || '';
    document.getElementById('supp_address').value = s.address || '';
    document.getElementById('suppOpeningDiv').style.display = 'none';
    document.getElementById('supp_notes').value = s.notes || '';
    new bootstrap.Modal(document.getElementById('supplierModal')).show();
}

function openPaySupplierModal(suppId, suppName, due) {
    document.getElementById('pay_supp_select').value = suppId;
    document.getElementById('pay_amount').value = due > 0 ? due : '';
    new bootstrap.Modal(document.getElementById('paySupplierModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
