<?php
// customer_payment.php - Receive Customer Payment Voucher Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();

$selectedCustomerId = (int)($_GET['customer_id'] ?? 0);
$customers = dbFetchAll("SELECT id, name, phone, current_balance FROM customers ORDER BY name ASC");
$accounts = dbFetchAll("SELECT id, account_name, current_balance FROM financial_accounts WHERE is_active = 1 ORDER BY account_name ASC");

$pageTitle = 'Receive Customer Payment';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-hand-holding-dollar text-success me-2"></i> Receive Customer Payment</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="customers.php" class="text-decoration-none">Customers</a></li>
                <li class="breadcrumb-item active" aria-current="page">Payment Receipt</li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="customers.php<?= $selectedCustomerId ? '?customer_id=' . $selectedCustomerId : '' ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Ledger
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white py-3">
                <h6 class="fw-bold mb-0">
                    <i class="fa-solid fa-receipt me-2"></i> Payment Receipt Voucher
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="customers.php">
                    <input type="hidden" name="receive_payment" value="1">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Customer *</label>
                        <select name="customer_id" id="rcpt_cust_select" class="form-select form-select-lg" required onchange="onCustSelect(this)">
                            <option value="">-- Choose Customer --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"
                                        data-balance="<?= (float)$c['current_balance'] ?>"
                                        <?= ($selectedCustomerId == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?> (Due: <?= formatCurrency($c['current_balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="alert alert-light border py-2 mb-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Current Outstanding Receivable:</span>
                        <span class="fw-bold fs-6 text-danger" id="disp_cust_due">
                            <?php
                            $found = null;
                            foreach ($customers as $c) {
                                if ($c['id'] == $selectedCustomerId) { $found = $c; break; }
                            }
                            echo $found ? formatCurrency($found['current_balance']) : 'Select a customer';
                            ?>
                        </span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Deposit Into Account / Cash Drawer *</label>
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
                            <label class="form-label small fw-bold">Amount Received (Rs.) *</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" step="0.01" min="1" name="amount" id="rcpt_amount" class="form-control form-control-lg fw-bold text-success" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Reference / Cheque / Slip #</label>
                            <input type="text" name="reference_no" class="form-control" value="RCPT-<?= date('Ymd-His') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Payment Description / Notes</label>
                        <input type="text" name="notes" class="form-control" value="Customer Khata Balance Payment Received">
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="customers.php<?= $selectedCustomerId ? '?customer_id=' . $selectedCustomerId : '' ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-check-circle me-1"></i> Post Payment Voucher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function onCustSelect(sel) {
    const opt = sel.options[sel.selectedIndex];
    const disp = document.getElementById('disp_cust_due');
    if (opt && opt.value) {
        const bal = parseFloat(opt.getAttribute('data-balance')) || 0;
        disp.textContent = 'Rs. ' + bal.toFixed(2);
        const amtInput = document.getElementById('rcpt_amount');
        if (bal > 0 && (!amtInput.value || amtInput.value == '0')) {
            amtInput.value = bal.toFixed(2);
        }
    } else {
        disp.textContent = 'Select a customer';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
