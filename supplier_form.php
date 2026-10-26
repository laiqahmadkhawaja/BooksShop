<?php
// supplier_form.php - Add / Edit Supplier / Publisher Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();

$supplierId = (int)($_GET['id'] ?? 0);
$isEdit = ($supplierId > 0);
$supplier = null;

if ($isEdit) {
    $supplier = dbFetchOne("SELECT * FROM suppliers WHERE id = ?", [$supplierId]);
    if (!$supplier) {
        setFlash('danger', 'Supplier not found.');
        header('Location: suppliers.php');
        exit;
    }
    $pageTitle = 'Edit Supplier: ' . $supplier['name'];
} else {
    $pageTitle = 'Add New Supplier / Publisher';
    $supplier = [
        'id' => 0,
        'name' => '',
        'company_name' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'opening_balance' => 0,
        'notes' => ''
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1">
            <i class="fa-solid <?= $isEdit ? 'fa-pen-to-square text-secondary' : 'fa-truck-field text-primary' ?> me-2"></i>
            <?= $isEdit ? 'Edit Supplier / Publisher' : 'Add New Supplier / Publisher' ?>
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="suppliers.php" class="text-decoration-none">Suppliers</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Edit' : 'New' ?></li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="suppliers.php<?= $isEdit ? '?supplier_id=' . $supplier['id'] : '' ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Suppliers
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-primary">
                    <i class="fa-solid fa-building me-2"></i> Supplier / Publisher Details
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="suppliers.php">
                    <input type="hidden" name="save_supplier" value="1">
                    <input type="hidden" name="supplier_id" value="<?= (int)$supplier['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Supplier / Contact Name *</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= htmlspecialchars($supplier['name']) ?>" placeholder="e.g. Oxford University Press, PCTB, Tariq Book Store">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Company / Publishing House</label>
                        <input type="text" name="company_name" class="form-control"
                               value="<?= htmlspecialchars($supplier['company_name'] ?? '') ?>" placeholder="e.g. OUP Pakistan or Paramount Books">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Phone</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?= htmlspecialchars($supplier['phone']) ?>" placeholder="042-35756611">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($supplier['email']) ?>" placeholder="orders@publisher.com">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Office Address / Warehouse</label>
                        <textarea name="address" class="form-control" rows="2"
                                  placeholder="Complete address or book market location"><?= htmlspecialchars($supplier['address']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <?php if (!$isEdit): ?>
                            <label class="form-label small fw-bold text-danger">Opening Balance (Our Payable Debt)</label>
                            <input type="number" step="0.01" min="0" name="opening_balance" class="form-control" value="0">
                            <div class="form-text small">Amount currently owed to this supplier from past purchases.</div>
                        <?php else: ?>
                            <label class="form-label small fw-bold">Current Ledger Balance</label>
                            <input type="text" class="form-control bg-light fw-bold text-danger" readonly
                                   value="<?= formatCurrency($supplier['current_balance']) ?>">
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Discount agreements, return policy, delivery days"><?= htmlspecialchars($supplier['notes']) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="suppliers.php<?= $isEdit ? '?supplier_id=' . $supplier['id'] : '' ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-save me-1"></i> <?= $isEdit ? 'Update Supplier' : 'Save Supplier' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
