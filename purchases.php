<?php
// purchases.php - Purchase Invoices & Goods Receiving
$pageTitle = 'Purchases & Inward';
require_once __DIR__ . '/includes/header.php';

$action = $_GET['action'] ?? '';
$supplierFilter = (int)($_GET['supplier'] ?? 0);
$preselectedProductId = (int)($_GET['product_id'] ?? 0);

// Handle New Purchase POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_purchase'])) {
    $supplierId = (int)$_POST['supplier_id'];
    $purchaseDate = $_POST['purchase_date'] ?: date('Y-m-d');
    $invoiceNo = trim($_POST['invoice_no'] ?? '');
    $shippingCost = (float)($_POST['shipping_cost'] ?? 0);
    $discountAmount = (float)($_POST['discount_amount'] ?? 0);
    $paidAmount = (float)($_POST['paid_amount'] ?? 0);
    $paymentAccountId = (int)($_POST['payment_account_id'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');
    $productIds = $_POST['prod_id'] ?? [];
    $quantities = $_POST['prod_qty'] ?? [];
    $costs = $_POST['prod_cost'] ?? [];
    $userId = $_SESSION['user']['id'] ?? 1;

    if ($supplierId > 0 && !empty($productIds)) {
        $db = getDB();
        $db->beginTransaction();

        $subtotal = 0;
        $itemsToInsert = [];

        foreach ($productIds as $idx => $pId) {
            $pId = (int)$pId;
            $qty = (float)($quantities[$idx] ?? 0);
            $cost = (float)($costs[$idx] ?? 0);
            if ($pId > 0 && $qty > 0) {
                $lineTotal = $qty * $cost;
                $subtotal += $lineTotal;
                $itemsToInsert[] = [
                    'product_id' => $pId,
                    'qty' => $qty,
                    'cost' => $cost,
                    'total' => $lineTotal
                ];
            }
        }

        $grandTotal = max(0, ($subtotal - $discountAmount) + $shippingCost);
        $dueAmount = max(0, $grandTotal - $paidAmount);
        $paymentStatus = ($dueAmount <= 0.01) ? 'paid' : (($paidAmount > 0) ? 'partial' : 'due');

        $purchaseNo = 'PUR-' . date('Y') . '-' . str_pad(rand(100, 9999), 5, '0', STR_PAD_LEFT);
        if (!$invoiceNo) $invoiceNo = $purchaseNo;

        // Insert purchase
        $stmt = $db->prepare("INSERT INTO purchases (purchase_no, invoice_no, supplier_id, purchase_date, subtotal, 
                                discount_amount, shipping_cost, grand_total, paid_amount, due_amount, payment_status, 
                                payment_account_id, notes, created_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $purchaseNo, $invoiceNo, $supplierId, $purchaseDate, $subtotal, $discountAmount, 
            $shippingCost, $grandTotal, $paidAmount, $dueAmount, $paymentStatus, $paymentAccountId, $notes, $userId
        ]);
        $purchaseId = $db->lastInsertId();

        // Insert items & increase stock
        $itemStmt = $db->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?)");
        foreach ($itemsToInsert as $it) {
            $itemStmt->execute([$purchaseId, $it['product_id'], $it['qty'], $it['cost'], $it['total']]);
            
            // Update product purchase cost and increase stock
            dbQuery("UPDATE products SET purchase_price = ? WHERE id = ?", [$it['cost'], $it['product_id']]);
            recordStockMovement($it['product_id'], 'purchase', $purchaseId, $it['qty'], "Supplier Purchase #$purchaseNo", $it['cost']);
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
        setFlash('success', "Purchase invoice $purchaseNo recorded and inventory stock increased!");
        header("Location: purchases.php");
        exit;
    } else {
        setFlash('danger', "Please select a supplier and add at least one product.");
    }
}

// Fetch purchases
$pWhere = ["1=1"];
$pParams = [];
if ($supplierFilter > 0) {
    $pWhere[] = "p.supplier_id = ?";
    $pParams[] = $supplierFilter;
}
$purchases = dbFetchAll("SELECT p.*, s.name as supplier_name, s.company_name, u.full_name as creator_name
                         FROM purchases p
                         JOIN suppliers s ON s.id = p.supplier_id
                         LEFT JOIN users u ON u.id = p.created_by
                         WHERE " . implode(" AND ", $pWhere) . "
                         ORDER BY p.id DESC LIMIT 50", $pParams);

$suppliers = dbFetchAll("SELECT id, name, company_name, current_balance FROM suppliers WHERE status = 1 ORDER BY name ASC");
$products = dbFetchAll("SELECT id, title, product_code, purchase_price, current_stock FROM products WHERE status = 1 ORDER BY title ASC");
$accounts = dbFetchAll("SELECT id, account_name, balance FROM accounts WHERE status = 1");
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-cart-flatbed text-success me-2"></i> Purchases & Stock Receiving</h4>
        <small class="text-muted">Record inward shipments from publishers and manufacturers to replenish stock</small>
    </div>
    <div>
        <button type="button" class="btn btn-success btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#newPurchaseModal">
            <i class="fa-solid fa-plus-circle me-1"></i> Record New Purchase
        </button>
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
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="viewPurchaseItems(<?= $p['id'] ?>)">
                                        <i class="fa-solid fa-list me-1"></i> View Items
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Record New Purchase -->
<div class="modal fade" id="newPurchaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="purchases.php" id="purchaseForm">
                <input type="hidden" name="save_purchase" value="1">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-cart-plus me-2"></i> Record New Purchase & Stock Inward</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Supplier / Publisher *</label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">-- Choose Supplier --</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= $s['id'] ?>">
                                        <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['company_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Purchase Date *</label>
                            <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Supplier Invoice / Bilty #</label>
                            <input type="text" name="invoice_no" class="form-control" placeholder="e.g. OUP-INV-9823">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Payment Account</label>
                            <select name="payment_account_id" class="form-select">
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['account_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="card border mb-3">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                            <h6 class="mb-0 fw-bold small text-uppercase">Items & Stock Entry</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPurchaseRow()">
                                <i class="fa-solid fa-plus me-1"></i> Add Another Item
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-bordered mb-0 align-middle small" id="purchaseItemsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50%;">Product / Book Title</th>
                                        <th style="width: 18%;">Unit Purchase Cost (Rs.)</th>
                                        <th style="width: 15%;">Quantity</th>
                                        <th style="width: 12%;" class="text-end">Subtotal</th>
                                        <th style="width: 5%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="purchaseItemsBody">
                                    <!-- Dynamic rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Settlement & Totals -->
                    <div class="row g-3 justify-content-end">
                        <div class="col-md-4">
                            <div class="card p-3 bg-light border">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="small">Items Subtotal:</span>
                                    <strong id="purchaseSubtotalDisplay">Rs. 0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small">Supplier Discount:</span>
                                    <input type="number" step="0.01" name="discount_amount" id="purchaseDiscount" class="form-control form-control-sm text-end" style="width: 110px;" value="0.00" oninput="calcPurchaseTotal()">
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small">Shipping / Bilty Cost:</span>
                                    <input type="number" step="0.01" name="shipping_cost" id="purchaseShipping" class="form-control form-control-sm text-end" style="width: 110px;" value="0.00" oninput="calcPurchaseTotal()">
                                </div>
                                <div class="d-flex justify-content-between border-top pt-2 mb-2">
                                    <span class="fw-bold">Grand Total:</span>
                                    <strong class="text-primary fs-5" id="purchaseGrandTotalDisplay">Rs. 0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small fw-bold">Amount Paid:</span>
                                    <input type="number" step="0.01" name="paid_amount" id="purchasePaid" class="form-control form-control-sm text-end fw-bold text-success" style="width: 110px;" value="0.00">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-save me-1"></i> Save Purchase & Increase Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const productCatalog = <?= json_encode($products) ?>;
let rowCount = 0;

function addPurchaseRow(pId = 0) {
    rowCount++;
    const tbody = document.getElementById('purchaseItemsBody');
    const tr = document.createElement('tr');
    tr.id = 'prow_' + rowCount;

    let options = '<option value="">-- Choose Product --</option>';
    productCatalog.forEach(p => {
        const sel = p.id == pId ? 'selected' : '';
        options += `<option value="${p.id}" data-cost="${p.purchase_price}" ${sel}>${p.title} (${p.product_code})</option>`;
    });

    tr.innerHTML = `
        <td>
            <select name="prod_id[]" class="form-select form-select-sm" required onchange="onProdSelect(${rowCount}, this)">
                ${options}
            </select>
        </td>
        <td>
            <input type="number" step="0.01" name="prod_cost[]" id="cost_${rowCount}" class="form-control form-control-sm" value="0.00" required oninput="calcRow(${rowCount})">
        </td>
        <td>
            <input type="number" step="1" name="prod_qty[]" id="qty_${rowCount}" class="form-control form-control-sm" value="1" min="1" required oninput="calcRow(${rowCount})">
        </td>
        <td class="text-end fw-bold" id="rowtotal_${rowCount}">
            Rs. 0.00
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm py-0" onclick="removePurchaseRow(${rowCount})">&times;</button>
        </td>
    `;
    tbody.appendChild(tr);

    if (pId > 0) {
        const prod = productCatalog.find(x => x.id == pId);
        if (prod) {
            document.getElementById('cost_' + rowCount).value = prod.purchase_price;
            calcRow(rowCount);
        }
    }
}

function onProdSelect(id, select) {
    const opt = select.options[select.selectedIndex];
    const cost = opt.getAttribute('data-cost') || 0;
    document.getElementById('cost_' + id).value = parseFloat(cost).toFixed(2);
    calcRow(id);
}

function calcRow(id) {
    const cost = parseFloat(document.getElementById('cost_' + id)?.value) || 0;
    const qty = parseFloat(document.getElementById('qty_' + id)?.value) || 0;
    const total = cost * qty;
    const totalEl = document.getElementById('rowtotal_' + id);
    if (totalEl) totalEl.innerText = 'Rs. ' + total.toFixed(2);
    calcPurchaseTotal();
}

function removePurchaseRow(id) {
    document.getElementById('prow_' + id)?.remove();
    calcPurchaseTotal();
}

function calcPurchaseTotal() {
    let subtotal = 0;
    document.querySelectorAll('#purchaseItemsBody tr').forEach(tr => {
        const id = tr.id.replace('prow_', '');
        const cost = parseFloat(document.getElementById('cost_' + id)?.value) || 0;
        const qty = parseFloat(document.getElementById('qty_' + id)?.value) || 0;
        subtotal += (cost * qty);
    });

    const discount = parseFloat(document.getElementById('purchaseDiscount').value) || 0;
    const shipping = parseFloat(document.getElementById('purchaseShipping').value) || 0;
    const grand = Math.max(0, (subtotal - discount) + shipping);

    document.getElementById('purchaseSubtotalDisplay').innerText = 'Rs. ' + subtotal.toFixed(2);
    document.getElementById('purchaseGrandTotalDisplay').innerText = 'Rs. ' + grand.toFixed(2);

    if (parseFloat(document.getElementById('purchasePaid').value) === 0) {
        document.getElementById('purchasePaid').value = grand.toFixed(2);
    }
}

// Open modal on load if preselected product
document.addEventListener('DOMContentLoaded', function() {
    addPurchaseRow(<?= $preselectedProductId ?>);
    <?php if ($action === 'new'): ?>
        new bootstrap.Modal(document.getElementById('newPurchaseModal')).show();
    <?php endif; ?>
});

function viewPurchaseItems(pId) {
    alert('Purchase #' + pId + ' details view');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
