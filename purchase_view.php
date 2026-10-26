<?php
// purchase_view.php - Dedicated page for viewing purchase order details
require_once __DIR__ . '/config/app.php';
requireAuth();

$purchaseId = (int)($_GET['id'] ?? 0);
if ($purchaseId <= 0) {
    setFlash('danger', 'Invalid purchase order.');
    redirect('purchases.php');
}

$purchase = dbFetchOne("SELECT p.*, s.name as supplier_name, s.company_name, s.phone as supplier_phone,
                               u.name as creator_name, a.account_name
                        FROM purchases p
                        LEFT JOIN suppliers s ON s.id = p.supplier_id
                        LEFT JOIN users u ON u.id = p.created_by
                        LEFT JOIN accounts a ON a.id = p.payment_account_id
                        WHERE p.id = ?", [$purchaseId]);

if (!$purchase) {
    setFlash('danger', 'Purchase order not found.');
    redirect('purchases.php');
}

$items = dbFetchAll("SELECT pi.*, pr.title, pr.product_code, pr.product_type
                     FROM purchase_items pi
                     JOIN products pr ON pr.id = pi.product_id
                     WHERE pi.purchase_id = ?
                     ORDER BY pi.id ASC", [$purchaseId]);

$pageTitle = 'Purchase Order #' . ($purchase['purchase_no'] ?: $purchase['id']);
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <div>
                <a href="purchases.php" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Purchases
                </a>
                <h4 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-file-invoice text-success me-2"></i> Purchase Order: <?= htmlspecialchars($purchase['purchase_no'] ?: '#' . $purchase['id']) ?>
                </h4>
            </div>
            <div class="d-flex gap-2">
                <a href="print_purchase.php?id=<?= $purchase['id'] ?>&type=a4" target="_blank" class="btn btn-success btn-sm shadow-sm fw-bold">
                    <i class="fa-solid fa-print me-1"></i> Print Invoice (A4)
                </a>
                <a href="print_purchase.php?id=<?= $purchase['id'] ?>&type=thermal" target="_blank" class="btn btn-outline-dark btn-sm shadow-sm">
                    <i class="fa-solid fa-receipt me-1"></i> Thermal Receipt
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <!-- Meta Info -->
                <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                    <div class="col-sm-6 col-md-4">
                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Supplier / Publisher</small>
                        <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($purchase['supplier_name']) ?></span>
                        <?php if ($purchase['company_name']): ?>
                            <div class="small text-muted"><?= htmlspecialchars($purchase['company_name']) ?></div>
                        <?php endif; ?>
                        <?php if ($purchase['supplier_phone']): ?>
                            <div class="small text-muted"><i class="fa-solid fa-phone me-1 small"></i><?= htmlspecialchars($purchase['supplier_phone']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6 col-md-4 border-start-md">
                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Order & Invoice Info</small>
                        <div><strong class="text-secondary small">Inv / Bill #:</strong> <span class="fw-semibold text-dark"><?= htmlspecialchars($purchase['invoice_no'] ?: 'N/A') ?></span></div>
                        <div><strong class="text-secondary small">Date:</strong> <span class="fw-semibold text-dark"><?= date('d M Y', strtotime($purchase['purchase_date'])) ?></span></div>
                        <div><strong class="text-secondary small">Recorded By:</strong> <span class="fw-semibold text-dark"><?= htmlspecialchars($purchase['creator_name'] ?: 'Admin') ?></span></div>
                    </div>
                    <div class="col-sm-6 col-md-4 border-start-md">
                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Payment & Account</small>
                        <div class="mb-1">
                            <?php if ($purchase['payment_status'] === 'paid'): ?>
                                <span class="badge bg-success">PAID</span>
                            <?php elseif ($purchase['payment_status'] === 'partial'): ?>
                                <span class="badge bg-warning text-dark">PARTIAL</span>
                            <?php else: ?>
                                <span class="badge bg-danger">DUE</span>
                            <?php endif; ?>
                        </div>
                        <div class="fw-semibold text-dark"><i class="fa-solid fa-building-columns text-primary me-1"></i><?= htmlspecialchars($purchase['account_name'] ?: 'Default Account') ?></div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="table-responsive mb-4 border rounded-3">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-secondary small text-uppercase">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Product / Item Description</th>
                                <th class="text-center" style="width: 100px;">Type</th>
                                <th class="text-end" style="width: 120px;">Unit Cost</th>
                                <th class="text-center" style="width: 100px;">Qty</th>
                                <th class="text-end" style="width: 130px;">Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $idx = 1;
                            $totalQty = 0;
                            foreach ($items as $it): 
                                $totalQty += (float)$it['quantity'];
                            ?>
                                <tr>
                                    <td class="text-center text-muted"><?= $idx++ ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($it['title']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($it['product_code']) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-secondary border"><?= htmlspecialchars($it['product_type']) ?></span>
                                    </td>
                                    <td class="text-end fw-semibold"><?= formatCurrency($it['purchase_price']) ?></td>
                                    <td class="text-center fw-bold"><?= (float)$it['quantity'] ?></td>
                                    <td class="text-end fw-bold text-dark"><?= formatCurrency($it['subtotal']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold border-top">
                            <tr>
                                <td colspan="4" class="text-end">Total Quantity:</td>
                                <td class="text-center text-primary"><?= $totalQty ?></td>
                                <td class="text-end text-dark"><?= formatCurrency($purchase['subtotal']) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Financial Summary -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <?php if ($purchase['notes']): ?>
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <small class="text-muted d-block fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">
                                    <i class="fa-solid fa-note-sticky text-warning me-1"></i> Notes & Remarks
                                </small>
                                <p class="small text-dark mb-0 fst-italic"><?= nl2br(htmlspecialchars($purchase['notes'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 ms-auto">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="d-flex justify-content-between mb-1 small">
                                <span class="text-muted">Subtotal:</span>
                                <span class="fw-semibold"><?= formatCurrency($purchase['subtotal']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1 small">
                                <span class="text-muted">Supplier Discount:</span>
                                <span class="text-success fw-semibold">- <?= formatCurrency($purchase['discount_amount']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1 small">
                                <span class="text-muted">Shipping / Freight:</span>
                                <span class="fw-semibold">+ <?= formatCurrency($purchase['shipping_cost']) ?></span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-bold text-dark">Grand Total:</span>
                                <span class="fw-bold text-dark fs-5"><?= formatCurrency($purchase['grand_total']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1 small">
                                <span class="text-success fw-bold">Amount Paid:</span>
                                <span class="text-success fw-bold"><?= formatCurrency($purchase['paid_amount']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold text-secondary">Balance Due:</span>
                                <span class="fw-bold text-danger"><?= formatCurrency($purchase['due_amount']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light d-flex justify-content-between p-3">
                <a href="purchases.php" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Purchases List
                </a>
                <a href="print_purchase.php?id=<?= $purchase['id'] ?>&type=a4" target="_blank" class="btn btn-success btn-sm fw-bold">
                    <i class="fa-solid fa-print me-1"></i> Print Invoice
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
