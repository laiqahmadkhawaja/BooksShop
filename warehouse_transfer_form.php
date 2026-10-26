<?php
// warehouse_transfer_form.php - Create Inter-Location Stock Transfer
require_once __DIR__ . '/config/app.php';
requireAuth();

$warehouses = getWarehouses(true);
$preselectedFromWh = (int)($_GET['from_wh'] ?? 0);

// Handle Transfer POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_transfer'])) {
    $fromLoc = $_POST['from_location'] ?? '';
    $toLoc = $_POST['to_location'] ?? '';
    $transferDate = $_POST['transfer_date'] ?: date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    $prodIds = $_POST['prod_id'] ?? [];
    $quantities = $_POST['prod_qty'] ?? [];
    $userId = $_SESSION['user']['id'] ?? 1;

    // Parse Locations
    $fromParts = explode(':', $fromLoc);
    $fromType = ($fromParts[0] === 'warehouse') ? 'warehouse' : 'shop';
    $fromWhId = ($fromType === 'warehouse') ? (int)($fromParts[1] ?? 0) : null;

    $toParts = explode(':', $toLoc);
    $toType = ($toParts[0] === 'warehouse') ? 'warehouse' : 'shop';
    $toWhId = ($toType === 'warehouse') ? (int)($toParts[1] ?? 0) : null;

    // Validation 1: Locations cannot be identical
    if ($fromLoc === $toLoc) {
        setFlash('danger', 'Source and Destination locations cannot be the same.');
        redirect('warehouse_transfer_form.php');
    }

    // Validation 2: At least one item
    $itemsToTransfer = [];
    $totalQty = 0;
    foreach ($prodIds as $idx => $pId) {
        $pId = (int)$pId;
        $qty = (float)($quantities[$idx] ?? 0);
        if ($pId > 0 && $qty > 0) {
            $itemsToTransfer[] = ['product_id' => $pId, 'qty' => $qty];
            $totalQty += $qty;
        }
    }

    if (empty($itemsToTransfer)) {
        setFlash('danger', 'Please select at least one item with quantity greater than zero.');
        redirect('warehouse_transfer_form.php');
    }

    // Process Transfer in Transaction
    $db = getDB();
    $db->beginTransaction();

    try {
        // Generate Transfer Number: TRF-YEAR-XXXXX
        $lastTrf = dbFetchOne("SELECT id FROM warehouse_transfers ORDER BY id DESC LIMIT 1");
        $nextNum = ($lastTrf['id'] ?? 0) + 1;
        $transferNo = 'TRF-' . date('Y') . '-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        // Location Display Names for Notes
        $fromName = ($fromType === 'shop') ? 'Shop Counter' : (dbFetchOne("SELECT name FROM warehouses WHERE id = ?", [$fromWhId])['name'] ?? 'Godown');
        $toName = ($toType === 'shop') ? 'Shop Counter' : (dbFetchOne("SELECT name FROM warehouses WHERE id = ?", [$toWhId])['name'] ?? 'Godown');

        // Insert Master Transfer
        $stmt = $db->prepare("INSERT INTO warehouse_transfers 
            (transfer_no, transfer_date, from_location_type, from_warehouse_id, to_location_type, to_warehouse_id, total_items, total_qty, status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?)");
        $stmt->execute([
            $transferNo, $transferDate, $fromType, $fromWhId, $toType, $toWhId, 
            count($itemsToTransfer), $totalQty, $notes, $userId
        ]);
        $transferId = $db->lastInsertId();

        $itemStmt = $db->prepare("INSERT INTO warehouse_transfer_items (transfer_id, product_id, quantity, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?)");

        foreach ($itemsToTransfer as $it) {
            $pId = $it['product_id'];
            $qty = $it['qty'];

            $prod = dbFetchOne("SELECT title, purchase_price, current_stock FROM products WHERE id = ?", [$pId]);
            $unitCost = $prod ? (float)$prod['purchase_price'] : 0.0;
            $lineTotal = $qty * $unitCost;

            // Check Source Stock Availability
            if ($fromType === 'shop') {
                $avail = (float)$prod['current_stock'];
            } else {
                $whs = dbFetchOne("SELECT quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ?", [$fromWhId, $pId]);
                $avail = $whs ? (float)$whs['quantity'] : 0.0;
            }

            if ($qty > ($avail + 0.001)) {
                throw new Exception("Insufficient stock for '{$prod['title']}' at $fromName. Available: $avail, Requested: $qty");
            }

            // Record Item
            $itemStmt->execute([$transferId, $pId, $qty, $unitCost, $lineTotal]);

            // 1. Deduct from Source
            if ($fromType === 'shop') {
                recordStockMovement($pId, 'transfer_out', $transferId, -$qty, "Stock Transfer #$transferNo to $toName", $unitCost);
            } else {
                recordWarehouseStockMovement($fromWhId, $pId, 'transfer_out', $transferId, $transferNo, -$qty, "Stock Transfer #$transferNo to $toName", $unitCost);
            }

            // 2. Add to Destination
            if ($toType === 'shop') {
                recordStockMovement($pId, 'transfer_in', $transferId, $qty, "Stock Transfer #$transferNo from $fromName", $unitCost);
            } else {
                recordWarehouseStockMovement($toWhId, $pId, 'transfer_in', $transferId, $transferNo, $qty, "Stock Transfer #$transferNo from $fromName", $unitCost);
            }
        }

        $db->commit();
        setFlash('success', "Stock Transfer $transferNo ($totalQty units) completed successfully!");
        redirect("print_transfer.php?id=$transferId");
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlash('danger', 'Transfer Error: ' . $e->getMessage());
        redirect('warehouse_transfer_form.php');
    }
}

// Preload Catalog Products & Stock Map for instant client-side updates
$products = dbFetchAll("SELECT id, title, product_code, barcode, purchase_price, current_stock FROM products WHERE status = 1 ORDER BY title ASC");
$allWhStockRaw = dbFetchAll("SELECT warehouse_id, product_id, quantity FROM warehouse_stock");

$whStockMap = [];
foreach ($allWhStockRaw as $ws) {
    $whStockMap[$ws['warehouse_id']][$ws['product_id']] = (float)$ws['quantity'];
}

$pageTitle = 'New Stock Transfer';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-11 col-xl-10">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <a href="warehouse_transfers.php" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Transfers
                </a>
                <h4 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-truck-ramp-box text-primary me-2"></i> Create Inter-Location Stock Transfer
                </h4>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" id="transferForm" onsubmit="return validateTransferForm()">
                    <input type="hidden" name="save_transfer" value="1">

                    <!-- Location Routing Box -->
                    <div class="card bg-light border-primary border-opacity-25 mb-4">
                        <div class="card-body p-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-danger">
                                        <i class="fa-solid fa-arrow-up-from-bracket me-1"></i> Source Location (From) <span class="text-danger">*</span>
                                    </label>
                                    <select name="from_location" id="fromLocationSelect" class="form-select form-select-lg" required onchange="onSourceLocationChange()">
                                        <option value="shop:0" <?= ($preselectedFromWh <= 0) ? 'selected' : '' ?>>🏬 Main Shop / Storefront Counter</option>
                                        <?php if (!empty($warehouses)): ?>
                                            <optgroup label="Warehouses & Godowns">
                                                <?php foreach ($warehouses as $wh): ?>
                                                    <option value="warehouse:<?= $wh['id'] ?>" <?= ($preselectedFromWh === (int)$wh['id']) ? 'selected' : '' ?>>
                                                        🏭 <?= htmlspecialchars($wh['name']) ?> (<?= htmlspecialchars($wh['warehouse_code']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                    <div class="form-text small">Location where stock will be deducted.</div>
                                </div>

                                <div class="col-md-2 text-center d-none d-md-block">
                                    <div class="rounded-circle bg-white border shadow-sm p-3 d-inline-block text-primary">
                                        <i class="fa-solid fa-arrow-right-arrow-left fs-4"></i>
                                    </div>
                                </div>

                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-success">
                                        <i class="fa-solid fa-arrow-down-to-bracket me-1"></i> Destination Location (To) <span class="text-danger">*</span>
                                    </label>
                                    <select name="to_location" id="toLocationSelect" class="form-select form-select-lg" required>
                                        <?php if (!empty($warehouses)): ?>
                                            <optgroup label="Warehouses & Godowns">
                                                <?php foreach ($warehouses as $wh): ?>
                                                    <option value="warehouse:<?= $wh['id'] ?>" <?= ($preselectedFromWh <= 0 && $wh['is_primary']) ? 'selected' : '' ?>>
                                                        🏭 <?= htmlspecialchars($wh['name']) ?> (<?= htmlspecialchars($wh['warehouse_code']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <option value="shop:0" <?= ($preselectedFromWh > 0) ? 'selected' : '' ?>>🏬 Main Shop / Storefront Counter</option>
                                    </select>
                                    <div class="form-text small">Location where stock will be added.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Transfer Date <span class="text-danger">*</span></label>
                            <input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Transfer Notes / Vehicle / Bilty Details</label>
                            <input type="text" name="notes" class="form-control" placeholder="e.g. Dispatched via Suzuki Carry # LEB-4521, Driver Rashid">
                        </div>
                    </div>

                    <!-- Items Selection Table -->
                    <div class="card border mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2 border-bottom">
                            <h6 class="mb-0 fw-bold small text-uppercase">
                                <i class="fa-solid fa-boxes-stacked text-primary me-1"></i> Items to Transfer
                            </h6>
                            <button type="button" class="btn btn-primary btn-sm" onclick="addTransferRow()">
                                <i class="fa-solid fa-plus me-1"></i> Add Another Item
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0 align-middle small" id="transferItemsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 45%;">Product / Book Title</th>
                                            <th style="width: 18%;" class="text-center">Available at Source</th>
                                            <th style="width: 18%;">Transfer Quantity</th>
                                            <th style="width: 14%;" class="text-end">Est. Value (Rs.)</th>
                                            <th style="width: 5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="transferItemsBody">
                                        <!-- Dynamic Rows -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-light py-2 d-flex justify-content-between align-items-center">
                            <span class="small text-muted" id="totalItemsSummary">0 items selected</span>
                            <div class="fw-bold">
                                Total Units: <span class="text-primary fs-6 me-3" id="displayTotalUnits">0</span>
                                Total Value: <span class="text-success fs-6" id="displayTotalValue">Rs. 0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Form Buttons -->
                    <div class="text-end pt-3 border-top">
                        <a href="warehouse_transfers.php" class="btn btn-secondary px-4 me-2">Cancel</a>
                        <button type="submit" class="btn btn-success btn-lg px-4 fw-bold">
                            <i class="fa-solid fa-check-circle me-1"></i> Complete Transfer & Print Slip
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const CATALOG_PRODUCTS = <?= json_encode($products) ?>;
const WAREHOUSE_STOCKS = <?= json_encode($whStockMap) ?>;

// Products map for O(1) lookups
const productsById = {};
CATALOG_PRODUCTS.forEach(p => {
    productsById[p.id] = p;
});

function getSourceStock(productId) {
    const fromVal = document.getElementById('fromLocationSelect').value;
    const parts = fromVal.split(':');
    const locType = parts[0];
    const whId = parseInt(parts[1] || 0);

    if (locType === 'shop') {
        const prod = productsById[productId];
        return prod ? parseFloat(prod.current_stock || 0) : 0;
    } else {
        if (WAREHOUSE_STOCKS[whId] && WAREHOUSE_STOCKS[whId][productId] !== undefined) {
            return parseFloat(WAREHOUSE_STOCKS[whId][productId] || 0);
        }
        return 0;
    }
}

let rowCount = 0;

function addTransferRow(preselectedProdId = 0) {
    rowCount++;
    const tbody = document.getElementById('transferItemsBody');
    const tr = document.createElement('tr');
    tr.id = 'row_' + rowCount;

    let options = '<option value="">-- Choose Book or Product --</option>';
    CATALOG_PRODUCTS.forEach(p => {
        const sel = (preselectedProdId === parseInt(p.id)) ? 'selected' : '';
        options += `<option value="${p.id}" ${sel}>${escapeHtml(p.title)} (${escapeHtml(p.product_code || 'N/A')})</option>`;
    });

    tr.innerHTML = `
        <td>
            <select name="prod_id[]" class="form-select form-select-sm prod-select" onchange="onProductSelect(${rowCount})" required>
                ${options}
            </select>
        </td>
        <td class="text-center">
            <span class="badge bg-light text-dark border fs-6 avail-badge" id="avail_${rowCount}">0</span>
        </td>
        <td>
            <input type="number" step="1" min="1" name="prod_qty[]" class="form-control form-control-sm text-center fw-bold qty-input"
                   id="qty_${rowCount}" value="1" oninput="calculateTotals()" required>
        </td>
        <td class="text-end font-monospace">
            <span id="lineVal_${rowCount}" class="fw-semibold">0.00</span>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="removeRow(${rowCount})">
                <i class="fa-solid fa-times"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);

    if (preselectedProdId > 0) {
        onProductSelect(rowCount);
    }
    calculateTotals();
}

function removeRow(idx) {
    const r = document.getElementById('row_' + idx);
    if (r) r.remove();
    calculateTotals();
}

function onProductSelect(idx) {
    const row = document.getElementById('row_' + idx);
    if (!row) return;
    const select = row.querySelector('.prod-select');
    const prodId = parseInt(select.value || 0);
    const availBadge = document.getElementById('avail_' + idx);
    const qtyInput = document.getElementById('qty_' + idx);

    if (prodId > 0) {
        const avail = getSourceStock(prodId);
        availBadge.innerText = avail.toLocaleString();
        availBadge.className = (avail > 0) ? 'badge bg-success-subtle text-success border border-success-subtle fs-6' : 'badge bg-danger-subtle text-danger border border-danger-subtle fs-6';
        qtyInput.max = avail;
        if (parseFloat(qtyInput.value) > avail && avail > 0) {
            qtyInput.value = avail;
        }
    } else {
        availBadge.innerText = '0';
        availBadge.className = 'badge bg-light text-dark border fs-6';
    }
    calculateTotals();
}

function onSourceLocationChange() {
    // Refresh all rows with updated available source stock
    const rows = document.querySelectorAll('#transferItemsBody tr');
    rows.forEach(r => {
        const idParts = r.id.split('_');
        const idx = idParts[1];
        onProductSelect(idx);
    });
}

function calculateTotals() {
    let totalUnits = 0;
    let totalValue = 0;
    let validItems = 0;

    const rows = document.querySelectorAll('#transferItemsBody tr');
    rows.forEach(r => {
        const pSelect = r.querySelector('.prod-select');
        const qInput = r.querySelector('.qty-input');
        const pId = parseInt(pSelect ? pSelect.value : 0);
        const qty = parseFloat(qInput ? qInput.value : 0) || 0;

        if (pId > 0 && qty > 0) {
            validItems++;
            totalUnits += qty;
            const cost = productsById[pId] ? parseFloat(productsById[pId].purchase_price || 0) : 0;
            const line = qty * cost;
            totalValue += line;

            const lineSpan = r.querySelector('[id^="lineVal_"]');
            if (lineSpan) lineSpan.innerText = line.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    });

    document.getElementById('totalItemsSummary').innerText = `${validItems} item(s) selected`;
    document.getElementById('displayTotalUnits').innerText = totalUnits.toLocaleString();
    document.getElementById('displayTotalValue').innerText = 'Rs. ' + totalValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function validateTransferForm() {
    const fromVal = document.getElementById('fromLocationSelect').value;
    const toVal = document.getElementById('toLocationSelect').value;

    if (fromVal === toVal) {
        alert("Validation Error:\nSource and Destination locations cannot be the same.");
        return false;
    }

    const rows = document.querySelectorAll('#transferItemsBody tr');
    if (rows.length === 0) {
        alert("Please add at least one item to transfer.");
        return false;
    }

    for (let r of rows) {
        const select = r.querySelector('.prod-select');
        const qInput = r.querySelector('.qty-input');
        const pId = parseInt(select ? select.value : 0);
        const qty = parseFloat(qInput ? qInput.value : 0) || 0;
        if (pId > 0) {
            const avail = getSourceStock(pId);
            if (qty > avail) {
                const prod = productsById[pId];
                alert(`Insufficient Stock:\n\nItem: ${prod.title}\nRequested: ${qty}\nAvailable at Source: ${avail}\n\nPlease adjust the quantity.`);
                qInput.focus();
                return false;
            }
        }
    }
    return true;
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

// Add initial row on load
document.addEventListener('DOMContentLoaded', () => {
    addTransferRow();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
