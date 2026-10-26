<?php
// purchases.php - Purchase Invoices & Goods Receiving
require_once __DIR__ . '/config/app.php';
requireAuth();

$action = $_GET['action'] ?? '';
$preselectedProductId = (int)($_GET['product_id'] ?? 0);
$preselectedSupplierId = (int)($_GET['supplier_id'] ?? 0);
if ($action === 'new' || $preselectedProductId > 0 || $preselectedSupplierId > 0) {
    $redirectParams = [];
    if ($preselectedProductId > 0) $redirectParams['product_id'] = $preselectedProductId;
    if ($preselectedSupplierId > 0) $redirectParams['supplier_id'] = $preselectedSupplierId;
    redirect('purchase_form.php' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : ''));
}

$supplierFilter = (int)($_GET['supplier'] ?? 0);

// Handle New Purchase POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_purchase'])) {
    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $purchaseDate = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : date('Y-m-d');
    $invoiceNo = trim($_POST['invoice_no'] ?? '');
    $shippingCost = (float)($_POST['shipping_cost'] ?? 0);
    $discountAmount = (float)($_POST['discount_amount'] ?? 0);
    $paidAmount = (float)($_POST['paid_amount'] ?? 0);
    $paymentAccountId = (int)($_POST['payment_account_id'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');
    $userId = $_SESSION['user']['id'] ?? 1;

    $destRaw = $_POST['stock_destination'] ?? 'shop:0';
    $destParts = explode(':', $destRaw);
    $destType = ($destParts[0] === 'warehouse') ? 'warehouse' : 'shop';
    $destWhId = ($destType === 'warehouse') ? (int)($destParts[1] ?? 0) : null;

    $itemsToInsert = [];
    $subtotal = 0;

    // 1. Process items from purchase_form.php (items[idx][product_id], quantity, purchase_price)
    if (!empty($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $it) {
            $pId = (int)($it['product_id'] ?? 0);
            $qty = (float)($it['quantity'] ?? ($it['qty'] ?? 0));
            $cost = (float)($it['purchase_price'] ?? ($it['unit_cost'] ?? ($it['cost'] ?? 0)));
            if ($pId > 0 && $qty > 0) {
                $lineTotal = round($qty * $cost, 2);
                $subtotal += $lineTotal;
                $itemsToInsert[] = [
                    'product_id' => $pId,
                    'qty' => $qty,
                    'cost' => $cost,
                    'total' => $lineTotal
                ];
            }
        }
    }
    // 2. Fallback for legacy parallel arrays (prod_id[], prod_qty[], prod_cost[])
    elseif (!empty($_POST['prod_id']) && is_array($_POST['prod_id'])) {
        $productIds = $_POST['prod_id'];
        $quantities = $_POST['prod_qty'] ?? [];
        $costs = $_POST['prod_cost'] ?? [];
        foreach ($productIds as $idx => $pId) {
            $pId = (int)$pId;
            $qty = (float)($quantities[$idx] ?? 0);
            $cost = (float)($costs[$idx] ?? 0);
            if ($pId > 0 && $qty > 0) {
                $lineTotal = round($qty * $cost, 2);
                $subtotal += $lineTotal;
                $itemsToInsert[] = [
                    'product_id' => $pId,
                    'qty' => $qty,
                    'cost' => $cost,
                    'total' => $lineTotal
                ];
            }
        }
    }

    if ($supplierId > 0 && !empty($itemsToInsert)) {
        try {
            $db = getDB();
            $db->beginTransaction();

            $grandTotal = max(0, ($subtotal - $discountAmount) + $shippingCost);
            $dueAmount = max(0, $grandTotal - $paidAmount);
            $paymentStatus = ($dueAmount <= 0.01) ? 'paid' : (($paidAmount > 0) ? 'partial' : 'due');

            $purchaseNo = 'PUR-' . date('Y') . '-' . str_pad(rand(100, 9999), 5, '0', STR_PAD_LEFT);
            if (!$invoiceNo) $invoiceNo = $purchaseNo;

            // Insert purchase
            $stmt = $db->prepare("INSERT INTO purchases (purchase_no, invoice_no, supplier_id, destination_type, destination_warehouse_id, purchase_date, subtotal, 
                                    discount_amount, shipping_cost, grand_total, paid_amount, due_amount, payment_status, 
                                    payment_account_id, notes, created_by)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $purchaseNo, $invoiceNo, $supplierId, $destType, $destWhId, $purchaseDate, $subtotal, $discountAmount, 
                $shippingCost, $grandTotal, $paidAmount, $dueAmount, $paymentStatus, $paymentAccountId, $notes, $userId
            ]);
            $purchaseId = $db->lastInsertId();

            // Insert items & increase stock
            $itemStmt = $db->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?)");
            foreach ($itemsToInsert as $it) {
                $itemStmt->execute([$purchaseId, $it['product_id'], $it['qty'], $it['cost'], $it['total']]);
                
                // Update product purchase cost and increase stock
                dbQuery("UPDATE products SET purchase_price = ? WHERE id = ?", [$it['cost'], $it['product_id']]);
                if ($destType === 'warehouse' && $destWhId > 0) {
                    recordWarehouseStockMovement($destWhId, $it['product_id'], 'purchase_in', $purchaseId, $purchaseNo, $it['qty'], "Supplier Purchase #$purchaseNo", $it['cost']);
                } else {
                    recordStockMovement($it['product_id'], 'purchase', $purchaseId, $it['qty'], "Supplier Purchase #$purchaseNo", $it['cost']);
                }
            }

            // Supplier Ledger (Credit total invoice)
            recordSupplierLedger($supplierId, 'purchase', $purchaseId, $purchaseNo, 0, $grandTotal, "Stock Purchase Invoice #$purchaseNo");
            
            // If payment made, record Supplier Ledger debit and Cash Outflow
            if ($paidAmount > 0) {
                recordSupplierLedger($supplierId, 'payment', $purchaseId, $purchaseNo, $paidAmount, 0, "Payment for Purchase #$purchaseNo");
                if ($paymentAccountId > 0) {
                    recordCashTransaction('purchase', $paymentAccountId, $paidAmount, 'Supplier Purchase', $purchaseNo, "Payment to supplier for $purchaseNo");
                }
            }

            $db->commit();
            setFlash('success', "Purchase invoice $purchaseNo recorded successfully and stock updated!");
            redirect("purchases.php");
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            setFlash('danger', "Failed to record purchase invoice: " . $e->getMessage());
            redirect("purchase_form.php");
        }
    } else {
        if ($supplierId <= 0) {
            setFlash('danger', "Please select a supplier / publisher.");
        } else {
            setFlash('danger', "Please add at least one product with a valid quantity.");
        }
        redirect("purchase_form.php");
    }
}

// Fetch purchases
$pWhere = ["1=1"];
$pParams = [];
if ($supplierFilter > 0) {
    $pWhere[] = "p.supplier_id = ?";
    $pParams[] = $supplierFilter;
}
$purchases = dbFetchAll("SELECT p.*, s.name as supplier_name, s.company_name, u.full_name as creator_name, w.name as warehouse_name, w.warehouse_code
                         FROM purchases p
                         JOIN suppliers s ON s.id = p.supplier_id
                         LEFT JOIN users u ON u.id = p.created_by
                         LEFT JOIN warehouses w ON w.id = p.destination_warehouse_id
                         WHERE " . implode(" AND ", $pWhere) . "
                         ORDER BY p.id DESC LIMIT 50", $pParams);

$suppliers = dbFetchAll("SELECT id, name, company_name, current_balance FROM suppliers WHERE status = 1 ORDER BY name ASC");
$products = dbFetchAll("SELECT id, title, product_code, purchase_price, current_stock FROM products WHERE status = 1 ORDER BY title ASC");
$accounts = dbFetchAll("SELECT id, account_name, balance FROM accounts WHERE status = 1");

$pageTitle = 'Purchases & Inward';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-cart-flatbed text-success me-2"></i> Purchases & Stock Receiving</h4>
        <small class="text-muted">Record inward shipments from publishers and manufacturers to replenish stock</small>
    </div>
    <div>
        <a href="purchase_form.php" class="btn btn-success btn-sm shadow-sm">
            <i class="fa-solid fa-cart-plus me-1"></i> Record New Purchase
        </a>
    </div>
</div>

<!-- Purchases History Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Inward Purchase Invoices</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th>Purchase #</th>
                        <th>Date</th>
                        <th>Supplier / Publisher</th>
                        <th>Bill Total</th>
                        <th>Amount Paid</th>
                        <th>Due (Payable)</th>
                        <th>Status</th>
                        <th class="text-end">Items</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-cart-arrow-down fa-3x mb-2 text-secondary opacity-25"></i>
                                <div>No purchase invoices recorded yet.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $p): ?>
                            <tr>
                                <td>
                                    <strong class="text-success"><?= htmlspecialchars($p['purchase_no']) ?></strong>
                                    <?php if ($p['invoice_no'] !== $p['purchase_no']): ?>
                                        <div class="small text-muted">Ref: <?= htmlspecialchars($p['invoice_no']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d M, Y', strtotime($p['purchase_date'])) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($p['supplier_name']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($p['company_name'] ?: '') ?></div>
                                    <div class="mt-1">
                                        <?php if ($p['destination_type'] === 'warehouse' && !empty($p['warehouse_name'])): ?>
                                            <span class="badge bg-warning bg-opacity-25 text-dark border border-warning" style="font-size: 0.72rem;">
                                                <i class="fa-solid fa-warehouse me-1"></i> <?= htmlspecialchars($p['warehouse_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-secondary border" style="font-size: 0.72rem;">
                                                <i class="fa-solid fa-store me-1"></i> Shop Counter
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="fw-bold text-dark"><?= formatCurrency($p['grand_total']) ?></td>
                                <td class="text-success fw-semibold"><?= formatCurrency($p['paid_amount']) ?></td>
                                <td class="text-danger fw-semibold"><?= $p['due_amount'] > 0 ? formatCurrency($p['due_amount']) : '-' ?></td>
                                <td>
                                    <?php if ($p['payment_status'] === 'paid'): ?>
                                        <span class="badge bg-success">PAID</span>
                                    <?php elseif ($p['payment_status'] === 'partial'): ?>
                                        <span class="badge bg-warning text-dark">PARTIAL</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">DUE</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm shadow-sm">
                                        <a href="print_purchase.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-outline-dark" title="Print Purchase Invoice">
                                            <i class="fa-solid fa-print me-1"></i> Print
                                        </a>
                                        <a href="purchase_view.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary" title="View Order Details">
                                            <i class="fa-solid fa-eye me-1"></i> View Items
                                        </a>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
