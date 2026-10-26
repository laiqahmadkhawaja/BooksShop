<?php
// sale_return.php - Dedicated page for processing sales returns / book exchanges
require_once __DIR__ . '/config/app.php';
requireAuth();

$saleId = (int)($_GET['sale_id'] ?? 0);
if ($saleId <= 0) {
    setFlash('danger', 'Invalid sale selected.');
    redirect('sales.php');
}

$sale = dbFetchOne("SELECT s.*, c.name as customer_name 
                    FROM sales s 
                    LEFT JOIN customers c ON c.id = s.customer_id 
                    WHERE s.id = ?", [$saleId]);

if (!$sale) {
    setFlash('danger', 'Sale invoice not found.');
    redirect('sales.php');
}

$saleItems = dbFetchAll("SELECT si.*, p.title, p.product_code 
                        FROM sale_items si 
                        JOIN products p ON p.id = si.product_id 
                        WHERE si.sale_id = ?", [$saleId]);

$accounts = dbFetchAll("SELECT * FROM accounts WHERE status = 1 ORDER BY account_name ASC");

$pageTitle = 'Process Sales Return - ' . htmlspecialchars($sale['invoice_no']);
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <a href="sales.php" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Sales History
                </a>
                <h4 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-rotate-left text-danger me-2"></i> Process Sales Return / Book Exchange
                </h4>
                <div class="text-muted small">
                    Invoice: <strong class="text-primary"><?= htmlspecialchars($sale['invoice_no']) ?></strong> &bull;
                    Customer: <strong><?= htmlspecialchars($sale['customer_name'] ?: 'Walking Customer') ?></strong> &bull;
                    Date: <?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?>
                </div>
            </div>
        </div>

        <form method="POST" action="sales.php">
            <input type="hidden" name="process_sales_return" value="1">
            <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-list-check text-primary me-2"></i> Select Items & Quantities to Return
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th>Product Title</th>
                                    <th class="text-center" style="width: 12%;">Sold Qty</th>
                                    <th class="text-end" style="width: 15%;">Unit Price</th>
                                    <th class="text-center" style="width: 18%;">Return Qty</th>
                                    <th style="width: 25%;">Item Condition</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($saleItems)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No items found for this invoice.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($saleItems as $it): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($it['title']) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($it['product_code']) ?></div>
                                            </td>
                                            <td class="text-center fw-bold">
                                                <?= (float)$it['quantity'] ?>
                                            </td>
                                            <td class="text-end fw-bold text-primary">
                                                <?= formatCurrency($it['unit_price']) ?>
                                            </td>
                                            <td>
                                                <input type="number" step="1" name="return_qty[<?= $it['product_id'] ?>]" 
                                                       class="form-control text-center fw-bold" value="0" min="0" max="<?= (float)$it['quantity'] ?>">
                                            </td>
                                            <td>
                                                <select name="condition[<?= $it['product_id'] ?>]" class="form-select form-select-sm">
                                                    <option value="restockable">Good / Restockable (Add to inventory)</option>
                                                    <option value="damaged">Damaged / Defective (Do not restock)</option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-money-bill-transfer text-danger me-2"></i> Refund Details
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Refund Method <span class="text-danger">*</span></label>
                            <select name="refund_method" class="form-select form-select-lg" required>
                                <option value="cash">Cash / Bank Refund to Customer</option>
                                <option value="credit">Credit / Adjust Customer Khata</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Refund From Account (If Cash/Bank)</label>
                            <select name="refund_account_id" class="form-select form-select-lg">
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['account_name']) ?> (<?= formatCurrency($acc['current_balance']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Reason for Return <span class="text-danger">*</span></label>
                            <input type="text" name="return_reason" class="form-control" required
                                   placeholder="e.g. Defective item, customer exchange, wrong size or model">
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light p-3 d-flex justify-content-end gap-2">
                    <a href="sales.php" class="btn btn-light px-4">Cancel</a>
                    <button type="submit" class="btn btn-danger px-4 fw-bold">
                        <i class="fa-solid fa-check me-1"></i> Confirm & Process Return
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
