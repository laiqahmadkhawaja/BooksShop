<?php
// suppliers.php - Suppliers, Publishers & Payables Management
require_once __DIR__ . '/config/app.php';
requireAuth();

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
        redirect("suppliers.php?supplier_id=" . ($id ?: $selectedSupplierId));
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
        redirect("suppliers.php?supplier_id=$supplierId");
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

$pageTitle = 'Suppliers & Publishers';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-truck-field text-primary me-2"></i> Supplier & Publisher Ledgers</h4>
        <small class="text-muted">Manage book publishers, stationery manufacturers, invoices & payment vouchers</small>
    </div>
    <div class="d-flex gap-2">
        <a href="supplier_payment.php<?= $selectedSupplierId ? '?supplier_id=' . $selectedSupplierId : '' ?>" class="btn btn-outline-danger btn-sm">
            <i class="fa-solid fa-money-bill-transfer me-1"></i> Pay Supplier Voucher
        </a>
        <a href="supplier_form.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fa-solid fa-plus-circle me-1"></i> Add Supplier / Publisher
        </a>
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
                        <a href="supplier_payment.php?supplier_id=<?= $currentSupplier['id'] ?>" class="btn btn-danger btn-sm">
                            <i class="fa-solid fa-money-bill-transfer me-1"></i> Make Payment to Supplier
                        </a>
                        <a href="purchases.php?action=new&supplier_id=<?= $currentSupplier['id'] ?>" class="btn btn-success btn-sm">
                            <i class="fa-solid fa-cart-plus me-1"></i> Record Inward Purchase
                        </a>
                        <a href="supplier_form.php?id=<?= $currentSupplier['id'] ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Supplier
                        </a>
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

<!-- Supplier Add/Edit and Payment Voucher are managed via supplier_form.php and supplier_payment.php -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
