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

// Track previously returned items for this invoice
$alreadyReturned = dbFetchAll("SELECT ri.product_id, SUM(ri.quantity) as ret_qty 
                               FROM return_items ri 
                               JOIN returns r ON r.id = ri.return_id 
                               WHERE r.return_type = 'sale_return' AND r.reference_id = ? 
                               GROUP BY ri.product_id", [$saleId]);
$returnedMap = [];
foreach ($alreadyReturned as $ar) {
    $returnedMap[$ar['product_id']] = (float)$ar['ret_qty'];
}

$previousReturns = dbFetchAll("SELECT * FROM returns WHERE return_type = 'sale_return' AND reference_id = ? ORDER BY id DESC", [$saleId]);

$totalReturnableQty = 0;
foreach ($saleItems as $it) {
    $alreadyRet = $returnedMap[$it['product_id']] ?? 0;
    $availRet = max(0, (float)$it['quantity'] - $alreadyRet);
    $totalReturnableQty += $availRet;
}
$isFullyReturned = !empty($saleItems) && ($totalReturnableQty <= 0);

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

        <?php if (!empty($previousReturns)): ?>
            <div class="alert <?= $isFullyReturned ? 'alert-danger' : 'alert-warning' ?> border d-flex align-items-start gap-3 mb-4">
                <i class="fa-solid <?= $isFullyReturned ? 'fa-circle-check text-danger' : 'fa-rotate-left text-warning' ?> fs-3 mt-1"></i>
                <div class="flex-grow-1">
                    <h6 class="fw-bold mb-1"><?= $isFullyReturned ? 'Invoice Already Fully Returned' : 'Invoice Partially Returned' ?></h6>
                    <div class="small mb-2">
                        <?= $isFullyReturned 
                            ? 'All items from this invoice have already been returned and refunded. No additional items remain eligible for return.' 
                            : 'Some items on this invoice were previously returned. You can only return the remaining eligible quantities below.' ?>
                    </div>
                    <?php foreach ($previousReturns as $pr): ?>
                        <div class="small text-muted">&bull; Return <strong>#<?= htmlspecialchars($pr['return_no']) ?></strong> on <?= date('d M Y, h:i A', strtotime($pr['return_date'])) ?> &bull; Refunded: <strong><?= formatCurrency($pr['total_amount']) ?></strong> via <strong><?= strtoupper($pr['refund_method']) ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="sales.php">
<?php if (function_exists('hasActiveShift') && !hasActiveShift()): ?>
<div class="alert alert-warning border-warning shadow-sm d-flex align-items-center justify-content-between mb-3 py-2 px-3 rounded">
    <div><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> <strong>Notice:</strong> No active shift is open. Cash/bank refunds cannot be paid without an active shift.</div>
    <a href="shift.php" class="btn btn-warning btn-sm fw-bold">Start Shift</a>
</div>
<?php endif; ?>

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
                                    <th class="text-center" style="width: 10%;">Sold Qty</th>
                                    <th class="text-center" style="width: 13%;">Prev. Returned</th>
                                    <th class="text-end" style="width: 13%;">Unit Price</th>
                                    <th class="text-center" style="width: 15%;">Return Qty</th>
                                    <th style="width: 24%;">Item Condition</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($saleItems)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No items found for this invoice.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($saleItems as $it): 
                                        $alreadyRet = $returnedMap[$it['product_id']] ?? 0;
                                        $availRet = max(0, (float)$it['quantity'] - $alreadyRet);
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($it['title']) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($it['product_code']) ?></div>
                                            </td>
                                            <td class="text-center fw-bold text-secondary">
                                                <?= (float)$it['quantity'] ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($alreadyRet > 0): ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?= (float)$alreadyRet ?> returned</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end fw-bold text-primary">
                                                <?= formatCurrency($it['unit_price']) ?>
                                            </td>
                                            <td>
                                                <?php if ($availRet > 0): ?>
                                                    <input type="number" step="1" name="return_qty[<?= $it['product_id'] ?>]" 
                                                           class="form-control text-center fw-bold" value="0" min="0" max="<?= $availRet ?>">
                                                    <div class="text-center small text-muted" style="font-size: 0.7rem;">Max: <?= $availRet ?></div>
                                                <?php else: ?>
                                                    <input type="text" class="form-control text-center text-muted" value="0" disabled>
                                                    <div class="text-center small text-danger fw-semibold" style="font-size: 0.7rem;">Fully Returned</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <select name="condition[<?= $it['product_id'] ?>]" class="form-select form-select-sm" <?= $availRet <= 0 ? 'disabled' : '' ?>>
                                                    <option value="restockable">Good / Restockable (Restore stock & recalculate WAC)</option>
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
                                <?php if ($sale['customer_id'] > 1): ?>
                                <option value="credit">Credit / Adjust Customer Khata (Store Balance)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Refund From Account (If Cash/Bank)</label>
                            <select name="refund_account_id" class="form-select form-select-lg">
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['account_name']) ?> (<?= formatCurrency($acc['balance']) ?>)</option>
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
                    <?php if ($isFullyReturned): ?>
                        <button type="button" class="btn btn-secondary px-4 fw-bold" disabled>
                            <i class="fa-solid fa-ban me-1"></i> Already Fully Returned
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-danger px-4 fw-bold">
                            <i class="fa-solid fa-check me-1"></i> Confirm & Process Return
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
