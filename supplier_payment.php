<?php
// supplier_payment.php - Pay Supplier Voucher Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();

$selectedSupplierId = (int)($_GET['supplier_id'] ?? 0);
$suppliers = dbFetchAll("SELECT id, name, company_name, current_balance FROM suppliers ORDER BY name ASC");
$accounts = dbFetchAll("SELECT id, account_name, current_balance FROM financial_accounts WHERE is_active = 1 ORDER BY account_name ASC");

$pageTitle = 'Pay Supplier Voucher';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-money-bill-transfer text-danger me-2"></i> Pay Supplier Voucher</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="suppliers.php" class="text-decoration-none">Suppliers</a></li>
                <li class="breadcrumb-item active" aria-current="page">Payment Voucher</li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="suppliers.php<?= $selectedSupplierId ? '?supplier_id=' . $selectedSupplierId : '' ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Ledger
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white py-3">
                <h6 class="fw-bold mb-0">
                    <i class="fa-solid fa-receipt me-2"></i> Supplier Payment Voucher
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="suppliers.php">
                    <input type="hidden" name="pay_supplier" value="1">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Supplier / Publisher *</label>
                        <select name="supplier_id" id="pay_supp_select" class="form-select form-select-lg" required onchange="onSuppSelect(this)">
                            <option value="">-- Choose Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"
                                        data-balance="<?= (float)$s['current_balance'] ?>"
                                        <?= ($selectedSupplierId == $s['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?> (Payable: <?= formatCurrency($s['current_balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="alert alert-light border py-2 mb-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Current Outstanding Payable:</span>
                        <span class="fw-bold fs-6 text-danger" id="disp_supp_due">
                            <?php
                            $found = null;
                            foreach ($suppliers as $s) {
                                if ($s['id'] == $selectedSupplierId) { $found = $s; break; }
                            }
                            echo $found ? formatCurrency($found['current_balance']) : 'Select a supplier';
                            ?>
                        </span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pay From Account / Cash Drawer *</label>
                        <select name="account_id" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['account_name']) ?> (Bal: <?= formatCurrency($acc['current_balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Amount Paid (Rs.) *</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" step="0.01" min="1" name="amount" id="pay_amount" class="form-control form-control-lg fw-bold text-danger" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer / Online</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Slip">Deposit Slip</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Reference / Cheque #</label>
                        <input type="text" name="reference_no" class="form-control" value="SPAY-<?= date('Ymd-His') ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Payment Description / Remarks</label>
                        <input type="text" name="notes" class="form-control" value="Payment made to publisher for books invoice">
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="suppliers.php<?= $selectedSupplierId ? '?supplier_id=' . $selectedSupplierId : '' ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-danger px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-paper-plane me-1"></i> Post Payment Voucher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function onSuppSelect(sel) {
    const opt = sel.options[sel.selectedIndex];
    const disp = document.getElementById('disp_supp_due');
    if (opt && opt.value) {
        const bal = parseFloat(opt.getAttribute('data-balance')) || 0;
        disp.textContent = 'Rs. ' + bal.toFixed(2);
        const amtInput = document.getElementById('pay_amount');
        if (bal > 0 && (!amtInput.value || amtInput.value == '0')) {
            amtInput.value = bal.toFixed(2);
        }
    } else {
        disp.textContent = 'Select a supplier';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
