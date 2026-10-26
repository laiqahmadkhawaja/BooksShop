<?php
// purchase_form.php - Dedicated page for Recording New Purchase & Inward Stock
require_once __DIR__ . '/config/app.php';
requireAuth();

$suppliers = dbFetchAll("SELECT * FROM suppliers WHERE status = 1 ORDER BY name ASC");
$accounts = dbFetchAll("SELECT * FROM accounts WHERE status = 1 ORDER BY account_name ASC");
$warehouses = getWarehouses(true);
$products = dbFetchAll("SELECT id, title, product_code, purchase_price, current_stock FROM products WHERE status = 1 ORDER BY title ASC");

$preselectedProductId = (int)($_GET['product_id'] ?? 0);
$preselectedSupplierId = (int)($_GET['supplier_id'] ?? 0);

$pageTitle = 'Record New Purchase';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-11 col-xl-10">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <a href="purchases.php" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Purchases
                </a>
                <h4 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-cart-plus text-success me-2"></i> Record New Purchase & Stock Inward
                </h4>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="purchases.php" id="purchaseForm">
                    <input type="hidden" name="save_purchase" value="1">

                    <div class="row g-3 mb-4">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Supplier / Publisher <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">-- Choose Supplier --</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $preselectedSupplierId === (int)$s['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['name']) ?> <?= $s['company_name'] ? '(' . htmlspecialchars($s['company_name']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Purchase Date <span class="text-danger">*</span></label>
                            <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-primary"><i class="fa-solid fa-boxes-packing me-1"></i> Stock Destination <span class="text-danger">*</span></label>
                            <select name="stock_destination" class="form-select border-primary" required>
                                <option value="shop:0">🏬 Main Shop / Storefront Counter</option>
                                <?php if (!empty($warehouses)): ?>
                                    <optgroup label="Warehouses & Godowns">
                                        <?php foreach ($warehouses as $wh): ?>
                                            <option value="warehouse:<?= $wh['id'] ?>">🏭 <?= htmlspecialchars($wh['name']) ?> (<?= htmlspecialchars($wh['warehouse_code']) ?>)</option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
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
                    <div class="card border mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                            <h6 class="mb-0 fw-bold small text-uppercase"><i class="fa-solid fa-boxes-stacked me-1"></i> Items & Stock Entry</h6>
                            <button type="button" class="btn btn-primary btn-sm" onclick="addPurchaseRow()">
                                <i class="fa-solid fa-plus me-1"></i> Add Another Item
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0 align-middle small" id="purchaseItemsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 48%;">Product / Book Title</th>
                                            <th style="width: 18%;">Unit Purchase Cost (Rs.)</th>
                                            <th style="width: 14%;">Quantity</th>
                                            <th style="width: 15%;" class="text-end">Subtotal</th>
                                            <th style="width: 5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="purchaseItemsBody">
                                        <!-- Dynamic rows will be inserted here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Settlement & Totals -->
                    <div class="row g-3 justify-content-between mb-4">
                        <div class="col-md-6 col-lg-7">
                            <div class="card p-3 bg-light border h-100">
                                <label class="form-label small fw-bold text-dark"><i class="fa-solid fa-note-sticky text-secondary me-1"></i> Purchase Notes / Bilty Remarks</label>
                                <textarea name="notes" class="form-control" rows="4" placeholder="Optional notes, bilty/tracking number, parcel condition, payment terms..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-5 col-xl-4">
                            <div class="card p-3 bg-light border">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="small">Items Subtotal:</span>
                                    <strong id="purchaseSubtotalDisplay">Rs. 0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small">Supplier Discount:</span>
                                    <input type="number" step="0.01" name="discount_amount" id="purchaseDiscount" class="form-control form-control-sm text-end" style="width: 120px;" value="0.00" oninput="calcPurchaseTotal()">
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small">Shipping / Bilty Cost:</span>
                                    <input type="number" step="0.01" name="shipping_cost" id="purchaseShipping" class="form-control form-control-sm text-end" style="width: 120px;" value="0.00" oninput="calcPurchaseTotal()">
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

                    <div class="d-flex justify-content-end gap-2">
                        <a href="purchases.php" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-success px-4 fw-bold">
                            <i class="fa-solid fa-save me-1"></i> Save Purchase & Increase Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const productsCatalog = <?= json_encode($products) ?>;
const preselectedProductId = <?= (int)$preselectedProductId ?>;
let purchaseRowIndex = 0;
let userManuallySetPaid = false;

function buildProductSelectHtml(idx, selectedId = 0) {
    let html = `<select name="items[${idx}][product_id]" class="form-select form-select-sm" required onchange="onPurchaseProductSelect(this, ${idx})">
        <option value="">-- Select Product --</option>`;
    productsCatalog.forEach(p => {
        const sel = (selectedId && selectedId === parseInt(p.id)) ? 'selected' : '';
        html += `<option value="${p.id}" data-cost="${p.purchase_price}" ${sel}>${escapeHtml(p.title)} (${escapeHtml(p.product_code)}) - Stock: ${parseFloat(p.current_stock)}</option>`;
    });
    html += `</select>`;
    return html;
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function addPurchaseRow(preselectId = 0, defaultCost = 0) {
    const tbody = document.getElementById('purchaseItemsBody');
    const row = document.createElement('tr');
    row.id = `purchase_row_${purchaseRowIndex}`;

    row.innerHTML = `
        <td>${buildProductSelectHtml(purchaseRowIndex, preselectId)}</td>
        <td>
            <input type="number" step="0.01" min="0" name="items[${purchaseRowIndex}][purchase_price]" class="form-control form-control-sm text-end" id="cost_${purchaseRowIndex}" value="${defaultCost ? parseFloat(defaultCost).toFixed(2) : '0.00'}" required oninput="calcPurchaseRow(${purchaseRowIndex})">
        </td>
        <td>
            <input type="number" step="1" min="1" name="items[${purchaseRowIndex}][quantity]" class="form-control form-control-sm text-center" id="qty_${purchaseRowIndex}" value="1" required oninput="calcPurchaseRow(${purchaseRowIndex})">
        </td>
        <td class="text-end fw-bold" id="subtotal_${purchaseRowIndex}">
            Rs. 0.00
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="removePurchaseRow(${purchaseRowIndex})">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </td>
    `;
    tbody.appendChild(row);
    calcPurchaseRow(purchaseRowIndex);
    purchaseRowIndex++;
}

function removePurchaseRow(idx) {
    const row = document.getElementById(`purchase_row_${idx}`);
    if (row) {
        row.remove();
        calcPurchaseTotal();
    }
}

function onPurchaseProductSelect(selectElem, idx) {
    const opt = selectElem.options[selectElem.selectedIndex];
    const cost = opt.getAttribute('data-cost');
    const costInput = document.getElementById(`cost_${idx}`);
    if (cost && costInput) {
        costInput.value = parseFloat(cost).toFixed(2);
    }
    calcPurchaseRow(idx);
}

function calcPurchaseRow(idx) {
    const cost = parseFloat(document.getElementById(`cost_${idx}`)?.value) || 0;
    const qty = parseFloat(document.getElementById(`qty_${idx}`)?.value) || 0;
    const subtotal = cost * qty;
    const subtotalElem = document.getElementById(`subtotal_${idx}`);
    if (subtotalElem) {
        subtotalElem.innerText = `Rs. ${subtotal.toFixed(2)}`;
    }
    calcPurchaseTotal();
}

function calcPurchaseTotal() {
    let itemsSubtotal = 0;
    const tbody = document.getElementById('purchaseItemsBody');
    const rows = tbody.querySelectorAll('tr');

    rows.forEach(r => {
        const idParts = r.id.split('_');
        const idx = idParts[2];
        const cost = parseFloat(document.getElementById(`cost_${idx}`)?.value) || 0;
        const qty = parseFloat(document.getElementById(`qty_${idx}`)?.value) || 0;
        itemsSubtotal += (cost * qty);
    });

    const discount = parseFloat(document.getElementById('purchaseDiscount')?.value) || 0;
    const shipping = parseFloat(document.getElementById('purchaseShipping')?.value) || 0;
    const grandTotal = Math.max(0, itemsSubtotal - discount + shipping);

    const subtotalDisplay = document.getElementById('purchaseSubtotalDisplay');
    const grandTotalDisplay = document.getElementById('purchaseGrandTotalDisplay');
    const paidInput = document.getElementById('purchasePaid');

    if (subtotalDisplay) subtotalDisplay.innerText = `Rs. ${itemsSubtotal.toFixed(2)}`;
    if (grandTotalDisplay) grandTotalDisplay.innerText = `Rs. ${grandTotal.toFixed(2)}`;

    if (paidInput && !userManuallySetPaid) {
        paidInput.value = grandTotal.toFixed(2);
    }
}

function onPaidManualInput() {
    userManuallySetPaid = true;
}

function setPaidInFull() {
    userManuallySetPaid = false;
    calcPurchaseTotal();
}

function setPaidZero() {
    userManuallySetPaid = true;
    const paidInput = document.getElementById('purchasePaid');
    if (paidInput) {
        paidInput.value = "0.00";
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (preselectedProductId > 0) {
        const match = productsCatalog.find(p => parseInt(p.id) === preselectedProductId);
        if (match) {
            addPurchaseRow(match.id, match.purchase_price);
        } else {
            addPurchaseRow();
        }
    } else {
        addPurchaseRow();
    }

    const form = document.getElementById('purchaseForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const rows = document.querySelectorAll('#purchaseItemsBody tr');
            if (rows.length === 0) {
                e.preventDefault();
                alert('Please add at least one product item to the purchase invoice.');
                return false;
            }
            let hasValidItem = false;
            rows.forEach(r => {
                const sel = r.querySelector('select');
                const qty = r.querySelector('input[name*="[quantity]"]');
                if (sel && sel.value && qty && parseFloat(qty.value) > 0) {
                    hasValidItem = true;
                }
            });
            if (!hasValidItem) {
                e.preventDefault();
                alert('Please select a valid product and set quantity greater than 0.');
                return false;
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
