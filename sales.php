<?php
// sales.php - Sales Invoices & Sales Returns Management
$pageTitle = 'Sales & Invoices';
require_once __DIR__ . '/includes/header.php';

$search = trim($_GET['search'] ?? '');
$customerFilter = (int)($_GET['customer'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Handle Sales Return POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_sales_return'])) {
    $saleId = (int)$_POST['sale_id'];
    $returnItems = $_POST['return_qty'] ?? [];
    $returnCondition = $_POST['condition'] ?? [];
    $reason = trim($_POST['return_reason'] ?? 'Customer Return / Exchange');
    $refundMethod = $_POST['refund_method'] ?? 'cash';
    $refundAccountId = (int)($_POST['refund_account_id'] ?? 1);
    $userId = $_SESSION['user']['id'] ?? 1;

    $sale = dbFetchOne("SELECT * FROM sales WHERE id = ?", [$saleId]);
    if ($sale) {
        $totalReturnAmount = 0;
        $returnItemsData = [];

        foreach ($returnItems as $prodId => $qty) {
            $qty = (float)$qty;
            if ($qty > 0) {
                $saleItem = dbFetchOne("SELECT * FROM sale_items WHERE sale_id = ? AND product_id = ?", [$saleId, $prodId]);
                if ($saleItem) {
                    $itemTotal = $qty * (float)$saleItem['unit_price'];
                    $totalReturnAmount += $itemTotal;
                    $returnItemsData[] = [
                        'product_id' => $prodId,
                        'qty' => $qty,
                        'unit_price' => (float)$saleItem['unit_price'],
                        'total' => $itemTotal,
                        'condition' => $returnCondition[$prodId] ?? 'restockable'
                    ];
                }
            }
        }

        if ($totalReturnAmount > 0) {
            $db = getDB();
            $db->beginTransaction();

            $returnNo = 'RET-' . date('Ymd-His');
            $stmt = $db->prepare("INSERT INTO returns (return_no, return_type, reference_id, entity_id, return_date, total_amount, refund_method, refund_account_id, reason, created_by)
                                  VALUES (?, 'sale_return', ?, ?, NOW(), ?, ?, ?, ?, ?)");
            $stmt->execute([$returnNo, $saleId, $sale['customer_id'], $totalReturnAmount, $refundMethod, $refundAccountId, $reason, $userId]);
            $returnId = $db->lastInsertId();

            $rItemStmt = $db->prepare("INSERT INTO return_items (return_id, product_id, quantity, unit_price, total_amount, condition_status)
                                       VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($returnItemsData as $ri) {
                $rItemStmt->execute([$returnId, $ri['product_id'], $ri['qty'], $ri['unit_price'], $ri['total'], $ri['condition']]);
                
                // If restockable, add back to inventory
                if ($ri['condition'] === 'restockable') {
                    recordStockMovement($ri['product_id'], 'return_in', $returnId, $ri['qty'], "Sales Return #$returnNo (Invoice #{$sale['invoice_no']})");
                }
            }

            // Adjust Customer Khata or Cash Refund
            if ($refundMethod === 'credit' && $sale['customer_id'] > 1) {
                // Credit customer khata
                recordCustomerLedger($sale['customer_id'], 'return', $returnId, $sale['invoice_no'], 0, $totalReturnAmount, "Sales Return #$returnNo");
            } elseif ($refundAccountId > 0) {
                // Cash or bank payout
                recordCashTransaction('expense', $refundAccountId, $totalReturnAmount, 'Sales Refund', $returnNo, "Sales Return Refund for #{$sale['invoice_no']}");
            }

            $db->commit();
            setFlash('success', "Sales return processed successfully! (Ref: $returnNo, Refund: " . formatCurrency($totalReturnAmount) . ")");
        } else {
            setFlash('warning', "No valid items selected for return.");
        }
    }
    header("Location: sales.php");
    exit;
}

// Build query
$where = ["s.hold_status = 0"];
$params = [];

if ($search) {
    $where[] = "(s.invoice_no LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
    $w = "%$search%";
    $params = array_merge($params, [$w, $w, $w]);
}
if ($customerFilter > 0) {
    $where[] = "s.customer_id = ?";
    $params[] = $customerFilter;
}
if ($statusFilter) {
    $where[] = "s.payment_status = ?";
    $params[] = $statusFilter;
}
if ($dateFrom) {
    $where[] = "DATE(s.sale_date) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = "DATE(s.sale_date) <= ?";
    $params[] = $dateTo;
}

$whereSql = implode(" AND ", $where);

// Aggregates
$totals = dbFetchOne("SELECT SUM(s.grand_total) as grand_total, SUM(s.paid_amount) as paid_amount, SUM(s.due_amount) as due_amount 
                      FROM sales s 
                      LEFT JOIN customers c ON c.id = s.customer_id 
                      WHERE $whereSql", $params);

// Sales list
$sales = dbFetchAll("SELECT s.*, c.name as customer_name, c.phone as customer_phone, u.full_name as cashier_name
                     FROM sales s
                     LEFT JOIN customers c ON c.id = s.customer_id
                     LEFT JOIN users u ON u.id = s.created_by
                     WHERE $whereSql
                     ORDER BY s.id DESC LIMIT 100", $params);

$customers = dbFetchAll("SELECT id, name FROM customers ORDER BY name ASC");
$accounts = dbFetchAll("SELECT id, account_name, balance FROM accounts WHERE status = 1");
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i> Sales History & Invoices</h4>
        <small class="text-muted">Browse invoices, reprint thermal & A4 receipts, and manage sales returns</small>
    </div>
    <div>
        <a href="pos.php" class="btn btn-pos btn-sm shadow-sm">
            <i class="fa-solid fa-cash-register me-1"></i> New POS Sale (F2)
        </a>
    </div>
</div>

<!-- Aggregates Bar -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card p-3 bg-white border-start border-primary border-4 shadow-sm">
            <div class="small text-muted fw-bold">TOTAL SALES INVOICED</div>
            <h4 class="fw-bold text-primary mb-0 mt-1"><?= formatCurrency($totals['grand_total'] ?? 0) ?></h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 bg-white border-start border-success border-4 shadow-sm">
            <div class="small text-muted fw-bold">TOTAL CASH/BANK RECEIVED</div>
            <h4 class="fw-bold text-success mb-0 mt-1"><?= formatCurrency($totals['paid_amount'] ?? 0) ?></h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 bg-white border-start border-danger border-4 shadow-sm">
            <div class="small text-muted fw-bold">TOTAL DUE / UNPAID (KHATA)</div>
            <h4 class="fw-bold text-danger mb-0 mt-1"><?= formatCurrency($totals['due_amount'] ?? 0) ?></h4>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Search Invoice # or Customer</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="e.g. INV-2026-0001" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Customer</label>
                <select name="customer" class="form-select form-select-sm">
                    <option value="">-- All Customers --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $customerFilter == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Payment Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- All Statuses --</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="partial" <?= $statusFilter === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="due" <?= $statusFilter === 'due' ? 'selected' : '' ?>>Due (Credit)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">From Date</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">To Date</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-filter"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- Sales Invoices Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th>Invoice #</th>
                        <th>Date & Time</th>
                        <th>Customer</th>
                        <th>Grand Total</th>
                        <th>Paid Amount</th>
                        <th>Due Amount</th>
                        <th>Payment Mode</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="fa-solid fa-receipt fa-3x mb-2 text-secondary opacity-25"></i>
                                <div>No sales invoices found matching your criteria.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales as $s): ?>
                            <tr>
                                <td>
                                    <strong class="text-primary"><?= htmlspecialchars($s['invoice_no']) ?></strong>
                                    <div class="small text-muted"><?= htmlspecialchars($s['cashier_name'] ?: 'Counter') ?></div>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= date('d M, Y', strtotime($s['sale_date'])) ?></div>
                                    <div class="small text-muted"><?= date('h:i A', strtotime($s['sale_date'])) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($s['customer_name'] ?: 'Walk-in Customer') ?></div>
                                    <?php if ($s['customer_phone']): ?>
                                        <div class="small text-muted"><?= htmlspecialchars($s['customer_phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-dark"><?= formatCurrency($s['grand_total']) ?></td>
                                <td class="text-success fw-semibold"><?= formatCurrency($s['paid_amount']) ?></td>
                                <td class="text-danger fw-semibold"><?= $s['due_amount'] > 0 ? formatCurrency($s['due_amount']) : '-' ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.72rem;">
                                        <?= htmlspecialchars($s['payment_method']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($s['payment_status'] === 'paid'): ?>
                                        <span class="badge bg-success">PAID</span>
                                    <?php elseif ($s['payment_status'] === 'partial'): ?>
                                        <span class="badge bg-warning text-dark">PARTIAL</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">DUE / KHATA</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="print_invoice.php?id=<?= $s['id'] ?>&type=thermal" target="_blank" class="btn btn-outline-secondary" title="Thermal 80mm Receipt">
                                            <i class="fa-solid fa-receipt"></i>
                                        </a>
                                        <a href="print_invoice.php?id=<?= $s['id'] ?>&type=a4" target="_blank" class="btn btn-outline-primary" title="A4 Invoice">
                                            <i class="fa-solid fa-file-invoice"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" onclick="openReturnModal(<?= $s['id'] ?>, '<?= $s['invoice_no'] ?>')" title="Process Sales Return / Exchange">
                                            <i class="fa-solid fa-rotate-left"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Sales Return -->
<div class="modal fade" id="returnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="sales.php">
                <input type="hidden" name="process_sales_return" value="1">
                <input type="hidden" name="sale_id" id="return_sale_id">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-rotate-left me-2"></i> Process Sales Return / Book Exchange</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3">
                        Invoice: <strong id="return_invoice_label" class="text-primary"></strong>
                    </div>

                    <div id="returnItemsContainer" class="table-responsive mb-3">
                        <div class="text-center py-3"><i class="fa-solid fa-spinner fa-spin"></i> Loading invoice items...</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Refund Method</label>
                            <select name="refund_method" class="form-select" id="return_refund_method">
                                <option value="cash">Cash Refund to Customer</option>
                                <option value="credit">Credit / Adjust Customer Khata</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Refund From Account</label>
                            <select name="refund_account_id" class="form-select">
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['account_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Reason for Return</label>
                            <input type="text" name="return_reason" class="form-control" placeholder="e.g. Wrong class syllabus purchased, defective binding" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fa-solid fa-check me-1"></i> Confirm & Process Return</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReturnModal(saleId, invoiceNo) {
    document.getElementById('return_sale_id').value = saleId;
    document.getElementById('return_invoice_label').innerText = invoiceNo;
    const container = document.getElementById('returnItemsContainer');
    container.innerHTML = '<div class="text-center py-3"><i class="fa-solid fa-spinner fa-spin"></i> Loading invoice items...</div>';
    
    fetch(`get_sale_items.php?sale_id=${saleId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.items.length > 0) {
                let html = `
                    <table class="table table-bordered align-middle small">
                        <thead class="table-light">
                            <tr>
                                <th>Item / Title</th>
                                <th>Sold Qty</th>
                                <th>Unit Price</th>
                                <th>Return Qty</th>
                                <th>Condition</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                data.items.forEach(it => {
                    html += `
                        <tr>
                            <td>
                                <strong>${it.title}</strong>
                                <div class="text-muted">${it.product_code}</div>
                            </td>
                            <td>${parseFloat(it.quantity)}</td>
                            <td>Rs. ${parseFloat(it.unit_price).toFixed(2)}</td>
                            <td>
                                <input type="number" step="1" name="return_qty[${it.product_id}]" class="form-control form-control-sm" value="0" min="0" max="${it.quantity}">
                            </td>
                            <td>
                                <select name="condition[${it.product_id}]" class="form-select form-select-sm">
                                    <option value="restockable">Good / Restockable</option>
                                    <option value="damaged">Damaged / Defective</option>
                                </select>
                            </td>
                        </tr>
                    `;
                });
                html += '</tbody></table>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="alert alert-warning">No items found for this invoice.</div>';
            }
        });

    new bootstrap.Modal(document.getElementById('returnModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
