<?php
// purchases.php - Purchase Invoices & Goods Receiving
require_once __DIR__ . '/config/app.php';
requireAuth();

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
        redirect("purchases.php");
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

$pageTitle = 'Purchases & Inward';
require_once __DIR__ . '/includes/header.php';
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
                                    <div class="btn-group btn-group-sm shadow-sm">
                                        <a href="print_purchase.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-outline-dark" title="Print Purchase Invoice">
                                            <i class="fa-solid fa-print me-1"></i> Print
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary" onclick="viewPurchaseItems(<?= $p['id'] ?>)" title="View Order Details">
                                            <i class="fa-solid fa-list me-1"></i> View Items
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

<!-- Modal: Record New Purchase -->
<div class="modal fade" id="newPurchaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
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
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="small fw-bold">Amount Paid:</span>
                                        <button type="button" class="btn btn-outline-success py-0 px-1" style="font-size: 0.68rem;" onclick="setPaidInFull()" title="Match Grand Total">
                                            Full Paid
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary py-0 px-1" style="font-size: 0.68rem;" onclick="setPaidZero()" title="Set to 0 (Full Due)">
                                            Due
                                        </button>
                                    </div>
                                    <input type="number" step="0.01" name="paid_amount" id="purchasePaid" class="form-control form-control-sm text-end fw-bold text-success" style="width: 120px;" value="0.00" oninput="onPaidManualInput()">
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

<!-- Modal: View Purchase Order & Items -->
<div class="modal fade" id="viewPurchaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-file-invoice text-warning fs-5"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0">Purchase Order: <span id="vpModalPurchaseNo" class="text-warning"></span></h5>
                        <small class="text-white-50">Detailed Purchase Receipt & Inward Stock List</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span id="vpPaymentStatusBadge" class="badge"></span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <div class="modal-body p-4">
                <!-- Loading Spinner -->
                <div id="vpLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="text-muted small mt-2">Loading purchase details...</div>
                </div>

                <!-- Error Alert -->
                <div id="vpError" class="alert alert-danger d-none" role="alert"></div>

                <!-- Printable Content Area -->
                <div id="vpContent" class="d-none">
                    <div id="viewPurchaseModalPrintArea">
                        <!-- Header / Meta Info -->
                        <div class="row g-3 mb-3 p-3 bg-light rounded-3 border">
                            <div class="col-sm-6 col-md-4">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Supplier / Publisher</small>
                                <span class="fw-bold text-dark fs-6" id="vpSupplierName"></span>
                                <div class="small text-muted" id="vpSupplierCompany"></div>
                                <div class="small text-muted"><i class="fa-solid fa-phone me-1 small"></i><span id="vpSupplierPhone"></span></div>
                            </div>
                            <div class="col-sm-6 col-md-4 border-start-md">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Order & Invoice Info</small>
                                <div><strong class="text-secondary small">Inv / Bill #:</strong> <span class="fw-semibold text-dark" id="vpInvoiceNo"></span></div>
                                <div><strong class="text-secondary small">Date:</strong> <span class="fw-semibold text-dark" id="vpPurchaseDate"></span></div>
                                <div><strong class="text-secondary small">Recorded By:</strong> <span class="fw-semibold text-dark" id="vpCreatorName"></span></div>
                            </div>
                            <div class="col-sm-6 col-md-4 border-start-md">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Payment Account</small>
                                <div class="fw-semibold text-dark"><i class="fa-solid fa-building-columns text-primary me-1"></i><span id="vpAccountName"></span></div>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div class="table-responsive mb-3 border rounded-3">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light text-secondary small text-uppercase">
                                    <tr>
                                        <th class="text-center" style="width: 40px;">#</th>
                                        <th>Product / Item Description</th>
                                        <th class="text-center" style="width: 100px;">Type</th>
                                        <th class="text-end" style="width: 110px;">Unit Cost</th>
                                        <th class="text-center" style="width: 90px;">Qty</th>
                                        <th class="text-end" style="width: 120px;">Total Cost</th>
                                    </tr>
                                </thead>
                                <tbody id="vpItemsTableBody"></tbody>
                                <tfoot class="table-light fw-bold border-top">
                                    <tr>
                                        <td colspan="4" class="text-end">Total Items / Quantity:</td>
                                        <td class="text-center text-primary" id="vpTotalQty">0</td>
                                        <td class="text-end text-dark" id="vpItemsSubtotalSummary"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Financials & Notes -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div id="vpNotesContainer" class="p-3 bg-light rounded-3 border h-100 d-none">
                                    <small class="text-muted d-block fw-bold text-uppercase mb-1" style="font-size: 0.72rem;"><i class="fa-solid fa-note-sticky text-warning me-1"></i> Notes & Remarks</small>
                                    <p class="small text-dark mb-0 fst-italic" id="vpNotes"></p>
                                </div>
                            </div>
                            <div class="col-md-6 ms-auto">
                                <div class="p-3 bg-light rounded-3 border">
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span class="text-muted">Subtotal:</span>
                                        <span class="fw-semibold" id="vpSubtotal">Rs. 0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span class="text-muted">Discount:</span>
                                        <span class="text-success fw-semibold" id="vpDiscount">Rs. 0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span class="text-muted">Shipping / Freight:</span>
                                        <span class="fw-semibold" id="vpShipping">Rs. 0.00</span>
                                    </div>
                                    <hr class="my-2">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="fw-bold text-dark">Grand Total:</span>
                                        <span class="fw-bold text-dark fs-6" id="vpGrandTotal">Rs. 0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span class="text-success fw-bold">Amount Paid:</span>
                                        <span class="text-success fw-bold" id="vpPaidAmount">Rs. 0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold text-secondary">Balance Due:</span>
                                        <span id="vpDueAmount" class="fw-bold">Rs. 0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light d-flex justify-content-between">
                <div class="d-flex gap-2">
                    <a id="vpPrintFullBtn" href="#" target="_blank" class="btn btn-success btn-sm shadow-sm fw-bold">
                        <i class="fa-solid fa-print me-1"></i> Print Invoice (Software Format)
                    </a>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
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

let isPaidManuallyEdited = false;

function onPaidManualInput() {
    isPaidManuallyEdited = true;
}

function setPaidInFull() {
    isPaidManuallyEdited = false;
    calcPurchaseTotal();
}

function setPaidZero() {
    isPaidManuallyEdited = true;
    document.getElementById('purchasePaid').value = '0.00';
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

    const paidInput = document.getElementById('purchasePaid');
    if (!isPaidManuallyEdited) {
        paidInput.value = grand.toFixed(2);
    }
}

// Open modal on load if preselected product
document.addEventListener('DOMContentLoaded', function() {
    addPurchaseRow(<?= $preselectedProductId ?>);
    <?php if ($action === 'new'): ?>
        new bootstrap.Modal(document.getElementById('newPurchaseModal')).show();
    <?php endif; ?>

    const modalEl = document.getElementById('newPurchaseModal');
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function() {
            isPaidManuallyEdited = false;
            calcPurchaseTotal();
        });
    }
});

let viewPurchaseModalInstance = null;

function viewPurchaseItems(pId) {
    const modalEl = document.getElementById('viewPurchaseModal');
    if (!viewPurchaseModalInstance) {
        viewPurchaseModalInstance = new bootstrap.Modal(modalEl);
    }
    
    // Reset and show loading state
    document.getElementById('vpLoading').classList.remove('d-none');
    document.getElementById('vpContent').classList.add('d-none');
    document.getElementById('vpError').classList.add('d-none');
    viewPurchaseModalInstance.show();

    fetch('pos_ajax.php?action=get_purchase_details&purchase_id=' + encodeURIComponent(pId))
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            document.getElementById('vpLoading').classList.add('d-none');
            if (!data.success) {
                document.getElementById('vpError').textContent = data.message || 'Failed to load purchase details';
                document.getElementById('vpError').classList.remove('d-none');
                return;
            }

            const p = data.purchase;
            const items = data.items || [];

            // Populate header & info
            document.getElementById('vpPrintFullBtn').href = 'print_purchase.php?id=' + encodeURIComponent(pId);
            document.getElementById('vpModalPurchaseNo').textContent = p.purchase_no || ('#' + p.id);
            document.getElementById('vpInvoiceNo').textContent = p.invoice_no || p.purchase_no || '-';
            document.getElementById('vpPurchaseDate').textContent = p.purchase_date || '-';
            document.getElementById('vpSupplierName').textContent = p.supplier_name || '-';
            document.getElementById('vpSupplierCompany').textContent = p.company_name ? ('(' + p.company_name + ')') : '';
            document.getElementById('vpSupplierPhone').textContent = p.supplier_phone || '-';
            document.getElementById('vpCreatorName').textContent = p.creator_name || 'System';
            document.getElementById('vpAccountName').textContent = p.account_name || 'Counter Cash';

            // Payment status badge
            const statusBadge = document.getElementById('vpPaymentStatusBadge');
            if (p.payment_status === 'paid') {
                statusBadge.className = 'badge bg-success';
                statusBadge.textContent = 'PAID';
            } else if (p.payment_status === 'partial') {
                statusBadge.className = 'badge bg-warning text-dark';
                statusBadge.textContent = 'PARTIAL';
            } else {
                statusBadge.className = 'badge bg-danger';
                statusBadge.textContent = 'DUE';
            }

            // Populate items table
            const tbody = document.getElementById('vpItemsTableBody');
            tbody.innerHTML = '';
            let totalQty = 0;
            let itemsSubtotal = 0;

            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No items recorded in this purchase order.</td></tr>';
            } else {
                items.forEach((item, index) => {
                    const qty = parseFloat(item.quantity) || 0;
                    const cost = parseFloat(item.unit_cost) || 0;
                    const total = parseFloat(item.total_cost) || (qty * cost);
                    totalQty += qty;
                    itemsSubtotal += total;

                    let subtitle = [];
                    if (item.author) subtitle.push('Author: ' + item.author);
                    if (item.publisher) subtitle.push('Pub: ' + item.publisher);
                    if (item.class_grade) subtitle.push('Grade: ' + item.class_grade);

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="text-center text-muted small">${index + 1}</td>
                        <td>
                            <div class="fw-bold text-dark">${escapeVpHtml(item.title)}</div>
                            <div class="small text-muted">
                                <span class="badge bg-light text-secondary border me-1">${escapeVpHtml(item.product_code || '')}</span>
                                ${item.barcode ? `<span class="badge bg-light text-dark border me-1"><i class="fa fa-barcode me-1"></i>${escapeVpHtml(item.barcode)}</span>` : ''}
                                ${subtitle.length ? `<span class="fst-italic">${escapeVpHtml(subtitle.join(' | '))}</span>` : ''}
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info-subtle text-info border">${escapeVpHtml(item.product_type ? item.product_type.toUpperCase() : 'PRODUCT')}</span>
                        </td>
                        <td class="text-end fw-semibold">Rs. ${cost.toFixed(2)}</td>
                        <td class="text-center fw-bold">${qty} <span class="small text-muted">${escapeVpHtml(item.unit || '')}</span></td>
                        <td class="text-end fw-bold text-dark">Rs. ${total.toFixed(2)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            document.getElementById('vpTotalQty').textContent = totalQty;
            document.getElementById('vpItemsSubtotalSummary').textContent = 'Rs. ' + itemsSubtotal.toFixed(2);
            document.getElementById('vpSubtotal').textContent = 'Rs. ' + (parseFloat(p.subtotal) || itemsSubtotal).toFixed(2);
            document.getElementById('vpDiscount').textContent = 'Rs. ' + (parseFloat(p.discount_amount) || 0).toFixed(2);
            document.getElementById('vpShipping').textContent = 'Rs. ' + (parseFloat(p.shipping_cost) || 0).toFixed(2);
            document.getElementById('vpGrandTotal').textContent = 'Rs. ' + (parseFloat(p.grand_total) || itemsSubtotal).toFixed(2);
            document.getElementById('vpPaidAmount').textContent = 'Rs. ' + (parseFloat(p.paid_amount) || 0).toFixed(2);
            
            const due = parseFloat(p.due_amount) || 0;
            const dueEl = document.getElementById('vpDueAmount');
            dueEl.textContent = 'Rs. ' + due.toFixed(2);
            if (due > 0) {
                dueEl.className = 'text-danger fw-bold fs-6';
            } else {
                dueEl.className = 'text-success fw-bold fs-6';
            }

            // Notes
            const notesEl = document.getElementById('vpNotesContainer');
            if (p.notes && p.notes.trim()) {
                document.getElementById('vpNotes').textContent = p.notes;
                notesEl.classList.remove('d-none');
            } else {
                notesEl.classList.add('d-none');
            }

            document.getElementById('vpContent').classList.remove('d-none');
        })
        .catch(err => {
            console.error(err);
            document.getElementById('vpLoading').classList.add('d-none');
            document.getElementById('vpError').textContent = 'An error occurred while loading purchase items.';
            document.getElementById('vpError').classList.remove('d-none');
        });
}

function escapeVpHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function printPurchaseDetails() {
    const printContent = document.getElementById('viewPurchaseModalPrintArea').innerHTML;
    const printWin = window.open('', '_blank', 'width=850,height=750');
    printWin.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Purchase Order Receipt</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
            <style>
                body { padding: 30px; font-family: system-ui, -apple-system, sans-serif; background: #fff; color: #111; }
                .table { border: 1px solid #dee2e6; }
                @media print {
                    .no-print { display: none !important; }
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            <div class="d-flex justify-content-between align-items-center mb-4 no-print pb-2 border-bottom">
                <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa fa-print me-1"></i> Print Receipt</button>
                <button onclick="window.close()" class="btn btn-secondary btn-sm">Close Window</button>
            </div>
            ${printContent}
            <script>
                window.onload = function() {
                    window.focus();
                    window.print();
                };
            <\/script>
        </body>
        </html>
    `);
    printWin.document.close();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
