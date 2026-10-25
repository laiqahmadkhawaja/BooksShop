<?php
// pos.php - High-Speed POS Billing Terminal
$pageTitle = 'POS Billing Terminal';
require_once __DIR__ . '/includes/header.php';

$customers = dbFetchAll("SELECT id, name, phone, current_balance, credit_limit, customer_type FROM customers WHERE status = 1 ORDER BY id ASC");
$accounts = dbFetchAll("SELECT id, account_name, account_type, balance FROM accounts WHERE status = 1 ORDER BY id ASC");
$categories = dbFetchAll("SELECT id, name, type FROM categories ORDER BY name ASC");
$schoolClasses = dbFetchAll("SELECT sc.id, sc.class_name, sc.academic_session, s.name as school_name 
                             FROM school_classes sc 
                             JOIN schools s ON s.id = sc.school_id 
                             ORDER BY s.name ASC, sc.class_name ASC");
?>

<div class="row g-2 mb-2">
    <!-- Top POS Controls Bar -->
    <div class="col-12">
        <div class="card p-2 bg-white shadow-sm border-0">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <!-- Customer Selection -->
                <div class="d-flex align-items-center gap-2" style="min-width: 320px;">
                    <span class="text-secondary small fw-bold"><i class="fa-solid fa-user me-1 text-primary"></i> Customer:</span>
                    <select id="posCustomerSelect" class="form-select form-select-sm" onchange="onCustomerChange()">
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" 
                                    data-balance="<?= (float)$c['current_balance'] ?>" 
                                    data-limit="<?= (float)$c['credit_limit'] ?>"
                                    data-name="<?= htmlspecialchars($c['name']) ?>"
                                    data-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>">
                                <?= htmlspecialchars($c['name']) ?> 
                                <?= $c['current_balance'] > 0 ? '(Due: ' . formatCurrency($c['current_balance']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Customer Balance Info Pill -->
                <div id="customerBalPill" class="d-none small badge bg-light text-dark border p-2">
                    <span id="custBalLabel">Balance: </span> <strong id="custBalVal" class="text-danger ms-1">Rs. 0.00</strong>
                </div>

                <!-- School Package 1-Click Bundle Loader -->
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#schoolPackageModal">
                        <i class="fa-solid fa-graduation-cap me-1"></i> <strong>Load School Book Bundle</strong>
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="openHeldBillsModal()">
                        <i class="fa-solid fa-pause me-1"></i> Held Bills <span id="heldBillsBadge" class="badge bg-warning text-dark ms-1">0</span>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearCart()">
                        <i class="fa-solid fa-trash-can me-1"></i> Clear Cart
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main POS Split View -->
<div class="row g-3">
    <!-- LEFT PANEL: Cart & Billing Table (60%) -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100 d-flex flex-column">
            <!-- Fast Barcode / Search Input -->
            <div class="p-3 bg-light border-bottom position-relative">
                <div class="input-group">
                    <span class="input-group-text bg-primary text-white"><i class="fa-solid fa-barcode"></i></span>
                    <input type="text" id="barcodeSearchInput" class="form-control form-control-lg" placeholder="Scan Barcode or Search Book Title, ISBN, Author (Press Enter)..." autofocus autocomplete="off">
                    <button class="btn btn-warning text-dark fw-bold px-3 d-flex align-items-center gap-1" type="button" onclick="openCameraScannerModal()" title="Scan Barcode via Camera (Alt+S or F3)">
                        <i class="fa-solid fa-camera"></i> <span class="d-none d-sm-inline">Scan</span>
                    </button>
                    <button class="btn btn-primary px-3" type="button" onclick="triggerSearchOrScan()">
                        <i class="fa-solid fa-plus-circle me-1"></i> Add
                    </button>
                </div>
                <!-- Live Search Suggestions Dropdown List -->
                <div id="liveSearchResults" class="list-group shadow position-absolute w-100 start-0 mt-1 d-none" style="z-index: 1050; max-height: 380px; overflow-y: auto; padding: 0 15px;"></div>
            </div>

            <!-- Cart Table Items -->
            <div class="card-body p-0 flex-grow-1 table-responsive pos-cart-scroll">
                <table class="table table-hover align-middle mb-0" id="cartTable">
                    <thead class="table-light sticky-top small text-uppercase">
                        <tr>
                            <th style="min-width: 140px; width: 40%;">Item / Book</th>
                            <th style="min-width: 85px; width: 20%;">Price</th>
                            <th style="min-width: 95px; width: 18%;" class="text-center">Qty</th>
                            <th style="min-width: 85px; width: 15%;" class="text-end">Total</th>
                            <th style="min-width: 35px; width: 7%;" class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="cartTableBody">
                        <tr id="emptyCartRow">
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-cart-shopping fa-3x mb-2 text-secondary opacity-25"></i>
                                <div>Your POS Cart is empty.</div>
                                <small class="text-muted d-block">Scan a barcode, use your camera scanner, or click any product from the catalog.</small>
                                <button type="button" class="btn btn-sm btn-outline-warning mt-2 fw-semibold" onclick="openCameraScannerModal()">
                                    <i class="fa-solid fa-camera me-1"></i> Scan with Camera
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Cart Totals & Checkout Actions Footer -->
            <div class="card-footer bg-white border-top p-3">
                <div class="row g-2 align-items-center mb-3">
                    <div class="col-6 col-md-4">
                        <span class="small text-muted">Subtotal:</span>
                        <div class="fs-6 fw-bold text-dark font-monospace" id="displaySubtotal">Rs. 0.00</div>
                    </div>
                    <div class="col-6 col-md-4">
                        <span class="small text-muted">Discount (Rs.):</span>
                        <input type="number" step="0.01" id="orderDiscountInput" class="form-control form-control-sm font-monospace" value="0.00" oninput="recalculateTotals()">
                    </div>
                    <div class="col-12 col-md-4 text-md-end text-center mt-2 mt-md-0 pt-2 pt-md-0 border-top border-md-0">
                        <span class="small text-muted text-uppercase fw-semibold">Grand Total:</span>
                        <div class="fs-3 fw-bold text-primary font-monospace" id="displayGrandTotal">Rs. 0.00</div>
                    </div>
                </div>

                <div class="d-flex flex-column flex-sm-row gap-2">
                    <button type="button" class="btn btn-outline-secondary order-2 order-sm-1 py-2 flex-grow-1" onclick="holdCurrentBill()">
                        <i class="fa-solid fa-pause me-1"></i> Hold Bill (F4)
                    </button>
                    <button type="button" class="btn btn-pos btn-lg order-1 order-sm-2 shadow-sm px-4 fw-bold py-2 flex-grow-2" onclick="openCheckoutModal()">
                        <i class="fa-solid fa-credit-card me-2"></i> PAY & CHECKOUT (F8)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL: Visual Catalog & Quick Selection (40%) -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100 d-flex flex-column">
            <!-- Filter Bar -->
            <div class="p-3 border-bottom bg-light">
                <div class="row g-2">
                    <div class="col-7">
                        <input type="text" id="catalogSearchInput" class="form-control form-control-sm" placeholder="Filter items..." oninput="filterCatalogItems()">
                    </div>
                    <div class="col-5">
                        <select id="catalogCategorySelect" class="form-select form-select-sm" onchange="filterCatalogItems()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Product Cards Grid -->
            <div class="card-body p-2 flex-grow-1 pos-catalog-scroll" id="productGrid">
                <div class="row g-2" id="catalogCardsContainer">
                    <!-- Loaded dynamically via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: School Book Package / Syllabus Bundle -->
<div class="modal fade" id="schoolPackageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info-subtle">
                <h5 class="modal-title fw-bold text-info-emphasis">
                    <i class="fa-solid fa-graduation-cap me-2"></i> Select Class Syllabus Book Package
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small">Choose an educational institution and class level to load the entire approved textbook & stationery set directly into this POS cart.</p>
                <div class="row g-3">
                    <?php foreach ($schoolClasses as $sc): ?>
                        <div class="col-md-6">
                            <div class="card border p-3 h-100 shadow-sm hover-shadow">
                                <div class="badge bg-primary mb-2 align-self-start"><?= htmlspecialchars($sc['school_name']) ?></div>
                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($sc['class_name']) ?></h6>
                                <div class="small text-muted mb-3">Academic Session: <?= htmlspecialchars($sc['academic_session']) ?></div>
                                <button type="button" class="btn btn-outline-primary btn-sm mt-auto" onclick="loadSchoolPackage(<?= $sc['id'] ?>)">
                                    <i class="fa-solid fa-cart-arrow-down me-1"></i> Load Complete Package into Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Checkout & Payment Settlement -->
<div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-cash-register me-2"></i> Bill Payment & Checkout</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Bill Amount Summary -->
                <div class="bg-light p-3 rounded mb-3 text-center border">
                    <div class="small text-muted text-uppercase fw-semibold">Net Payable Amount</div>
                    <div class="display-6 fw-bold text-primary" id="modalPayableAmount">Rs. 0.00</div>
                </div>

                <!-- Payment Method Selection -->
                <div class="mb-3">
                    <label class="form-label small fw-bold">Payment Method</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="payMethod" id="pay_cash" value="cash" checked onchange="onPaymentMethodChange()">
                        <label class="btn btn-outline-primary" for="pay_cash"><i class="fa-solid fa-money-bill-wave me-1"></i> Cash</label>

                        <input type="radio" class="btn-check" name="payMethod" id="pay_bank" value="bank" onchange="onPaymentMethodChange()">
                        <label class="btn btn-outline-primary" for="pay_bank"><i class="fa-solid fa-building-columns me-1"></i> Bank</label>

                        <input type="radio" class="btn-check" name="payMethod" id="pay_wallet" value="wallet" onchange="onPaymentMethodChange()">
                        <label class="btn btn-outline-primary" for="pay_wallet"><i class="fa-solid fa-mobile-screen-button me-1"></i> Wallet</label>

                        <input type="radio" class="btn-check" name="payMethod" id="pay_credit" value="credit" onchange="onPaymentMethodChange()">
                        <label class="btn btn-outline-primary" for="pay_credit"><i class="fa-solid fa-hand-holding-dollar me-1"></i> Khata (Credit)</label>
                    </div>
                </div>

                <!-- Receiving Account -->
                <div class="mb-3" id="depositAccountDiv">
                    <label class="form-label small fw-bold">Deposit Into Account</label>
                    <select id="paymentAccountId" class="form-select">
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['account_name']) ?> (<?= formatCurrency($acc['balance']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Cash Tendered & Quick Fill Buttons -->
                <div id="cashTenderedDiv">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold mb-0">Cash Received (Tendered) *</label>
                        <small class="text-muted">Smart note shortcuts:</small>
                    </div>
                    <input type="number" step="1" id="tenderedAmountInput" class="form-control form-control-lg fw-bold text-success font-monospace" oninput="calculateChange()">
                    <!-- Dynamic Closer Currency Shortcuts Container -->
                    <div class="d-flex flex-wrap gap-1 mt-2" id="smartShortcutsContainer"></div>
                    <div id="checkoutWarningBox"></div>
                </div>

                <!-- Return Change Display -->
                <div class="mt-3 p-3 bg-light rounded border d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">Change to Return:</div>
                        <h4 class="fw-bold mb-0 text-success" id="displayChangeAmount">Rs. 0.00</h4>
                    </div>
                    <div>
                        <div class="small text-muted text-end">Remaining Due:</div>
                        <h5 class="fw-bold mb-0 text-danger text-end" id="displayDueAmount">Rs. 0.00</h5>
                    </div>
                </div>

                <div class="mt-3">
                    <input type="text" id="saleNotesInput" class="form-control form-control-sm" placeholder="Order notes or customer reference (optional)">
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-lg px-4 fw-bold" id="confirmCheckoutBtn" onclick="processCheckout()">
                    <i class="fa-solid fa-check-circle me-1"></i> Complete & Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Held Bills Management -->
<div class="modal fade" id="heldBillsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title fw-bold text-warning-emphasis"><i class="fa-solid fa-pause me-2"></i> Held Bills</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div id="heldBillsList" class="list-group">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Post-Checkout Success & Receipt Printing -->
<div class="modal fade" id="receiptSuccessModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-circle-check me-2"></i> Sale Completed Successfully!</h5>
            </div>
            <div class="modal-body text-center p-4">
                <div class="text-success mb-2"><i class="fa-solid fa-check-double fa-3x"></i></div>
                <h4 class="fw-bold mb-1" id="successInvoiceNo">INV-2026-0001</h4>
                <p class="text-muted small mb-3">Total: <strong id="successTotal">Rs. 0.00</strong> &bull; Change: <strong id="successChange" class="text-success">Rs. 0.00</strong></p>

                <!-- Receipt Preview Frame / Action Buttons -->
                <div class="d-grid gap-2">
                    <a href="#" id="thermalPrintLink" target="_blank" class="btn btn-dark btn-lg">
                        <i class="fa-solid fa-receipt me-2"></i> Print 80mm Thermal Receipt (F9)
                    </a>
                    <a href="#" id="a4PrintLink" target="_blank" class="btn btn-outline-primary">
                        <i class="fa-solid fa-file-invoice me-2"></i> Print Standard A4 Invoice
                    </a>
                    <button type="button" class="btn btn-outline-secondary" onclick="startNewSale()">
                        <i class="fa-solid fa-plus-circle me-1"></i> New Bill / Next Customer (Enter)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Interactive Camera Barcode Scanner with Auto-Alignment & Zoom -->
<div class="modal fade" id="cameraScannerModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border-secondary shadow-lg">
            <div class="modal-header border-secondary py-2 px-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-camera"></i> LIVE SCAN</span>
                    <h5 class="modal-title fw-bold mb-0 text-white fs-6">
                        <i class="fa-solid fa-barcode text-primary me-1"></i> Barcode & Book Scanner
                    </h5>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <!-- Torch Button (if supported by mobile device) -->
                    <button type="button" class="btn btn-sm btn-outline-light d-none" id="scannerTorchBtn" onclick="toggleScannerTorch()" title="Toggle Flashlight">
                        <i class="fa-solid fa-bolt"></i>
                    </button>
                    <!-- Close button -->
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="stopCameraScanner()"></button>
                </div>
            </div>
            
            <div class="modal-body p-0 position-relative bg-black" style="min-height: 380px;">
                <!-- Video Viewport Container -->
                <div class="scanner-viewport-wrapper" id="cameraScannerViewport">
                    <div id="interactiveScannerReader"></div>
                    
                    <!-- HUD Alignment Overlay Layer -->
                    <div class="scanner-hud-overlay" id="scannerHudOverlay">
                        <!-- Targeting reticle frame for 1D/2D Barcodes -->
                        <div class="scanner-target-box" id="scannerTargetBox">
                            <!-- Corner HUD brackets -->
                            <div class="corner corner-tl"></div>
                            <div class="corner corner-tr"></div>
                            <div class="corner corner-bl"></div>
                            <div class="corner corner-br"></div>
                            
                            <!-- Sweeping Red Laser Bar -->
                            <div class="scanner-laser-line"></div>
                            
                            <!-- Status Label -->
                            <div class="scanner-target-label" id="scannerTargetLabel">
                                <i class="fa-solid fa-arrows-to-dot me-1 text-info"></i> Center Barcode Inside Box
                            </div>
                        </div>
                    </div>

                    <!-- Floating Feedback Toast (appears on scan) -->
                    <div id="scannerFeedbackToast" class="scanner-feedback-toast d-none">
                        <i class="fa-solid fa-circle-check text-success fs-5"></i>
                        <div class="text-truncate">
                            <strong id="toastScannedTitle" class="text-white d-block small"></strong>
                            <span id="toastScannedDetails" class="text-warning" style="font-size: 11px;"></span>
                        </div>
                    </div>

                    <!-- Floating Error Toast (if code not found) -->
                    <div id="scannerErrorToast" class="scanner-error-toast d-none">
                        <i class="fa-solid fa-triangle-exclamation text-danger fs-5"></i>
                        <div class="text-truncate">
                            <strong id="toastErrorTitle" class="text-white d-block small">Barcode Not Found</strong>
                            <span id="toastErrorDetails" class="text-white-50" style="font-size: 11px;"></span>
                        </div>
                    </div>
                </div>

                <!-- Camera Error / Permission Notice if needed -->
                <div id="scannerErrorNotice" class="alert alert-danger m-3 d-none"></div>
            </div>

            <!-- Controls Footer -->
            <div class="modal-footer border-secondary py-2 px-3 justify-content-between flex-wrap bg-dark">
                <!-- Zoom Controls -->
                <div class="d-flex align-items-center gap-2 my-1">
                    <span class="small text-white-50"><i class="fa-solid fa-magnifying-glass-plus me-1"></i> Zoom:</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary text-white zoom-btn" data-zoom="1.0" onclick="applyScannerZoom(1.0)">1x</button>
                        <button type="button" class="btn btn-primary text-white zoom-btn active" data-zoom="1.5" onclick="applyScannerZoom(1.5)">1.5x</button>
                        <button type="button" class="btn btn-outline-secondary text-white zoom-btn" data-zoom="2.0" onclick="applyScannerZoom(2.0)">2x</button>
                        <button type="button" class="btn btn-outline-secondary text-white zoom-btn" data-zoom="3.0" onclick="applyScannerZoom(3.0)">3x</button>
                    </div>
                    <input type="range" class="form-range d-none d-md-inline-block" id="scannerZoomSlider" min="1.0" max="3.5" step="0.1" value="1.5" style="width: 80px;" oninput="applyScannerZoom(parseFloat(this.value))">
                </div>

                <!-- Camera Selector & Continuous Scan Toggle -->
                <div class="d-flex align-items-center gap-3 flex-wrap my-1">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="continuousScanToggle" checked>
                        <label class="form-check-label small text-white" for="continuousScanToggle">Continuous Scan</label>
                    </div>

                    <!-- Camera Select Dropdown (if multiple cameras exist) -->
                    <select id="cameraSourceSelect" class="form-select form-select-sm bg-dark text-white border-secondary" style="max-width: 140px;" onchange="switchCameraSource(this.value)">
                        <option value="">Detecting...</option>
                    </select>

                    <button type="button" class="btn btn-sm btn-light fw-bold px-3" data-bs-dismiss="modal" onclick="stopCameraScanner()">
                        <i class="fa-solid fa-check me-1"></i> Done
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Local Html5Qrcode Barcode Scanning Library -->
<script src="assets/js/html5-qrcode.min.js"></script>

<script>
// POS State
let cartItems = [];
let allCatalogProducts = [];
let productsMap = {};
let searchDebounceTimer = null;
let activeDropdownIndex = -1;

// Synthesize pleasant checkout / add beep using Web Audio API
function playBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.frequency.setValueAtTime(880, audioCtx.currentTime); // A5 note
        gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.12);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.12);
    } catch(e) {}
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadCatalog();
    checkHeldBills();

    const searchInput = document.getElementById('barcodeSearchInput');

    // Live search on typing
    searchInput.addEventListener('input', function(e) {
        const term = this.value.trim();
        handleLiveSearch(term);
    });

    // Keyboard navigation in search (Enter, Arrows, Escape)
    searchInput.addEventListener('keydown', function(e) {
        const dropdown = document.getElementById('liveSearchResults');
        const items = dropdown.querySelectorAll('.live-search-item');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (items.length > 0) {
                activeDropdownIndex = (activeDropdownIndex + 1) % items.length;
                updateDropdownSelection(items);
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (items.length > 0) {
                activeDropdownIndex = (activeDropdownIndex - 1 + items.length) % items.length;
                updateDropdownSelection(items);
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeDropdownIndex >= 0 && items[activeDropdownIndex]) {
                items[activeDropdownIndex].click();
            } else {
                triggerSearchOrScan();
            }
        } else if (e.key === 'Escape') {
            hideLiveSearchDropdown();
        }
    });

    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#barcodeSearchInput') && !e.target.closest('#liveSearchResults')) {
            hideLiveSearchDropdown();
        }
    });

    // Ensure camera stops if user dismisses scanner modal
    const scannerModalEl = document.getElementById('cameraScannerModal');
    if (scannerModalEl) {
        scannerModalEl.addEventListener('hidden.bs.modal', function () {
            stopCameraScanner();
        });
    }

    // POS Hotkeys (F3 = Camera Scan, F4 = Hold, F8 = Checkout, F7 = Held Bills)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F3' || (e.altKey && (e.key === 's' || e.key === 'S'))) {
            e.preventDefault();
            openCameraScannerModal();
        } else if (e.key === 'F4') {
            e.preventDefault();
            holdCurrentBill();
        } else if (e.key === 'F8') {
            e.preventDefault();
            openCheckoutModal();
        } else if (e.key === 'F7') {
            e.preventDefault();
            openHeldBillsModal();
        }
    });
});

// Load products for the visual catalog
function loadCatalog() {
    fetch('pos_ajax.php?action=search_products')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.products) {
                allCatalogProducts = data.products;
                productsMap = {};
                allCatalogProducts.forEach(p => {
                    productsMap[p.id] = p;
                });
                renderCatalog(allCatalogProducts);
            }
        })
        .catch(err => {
            console.error('Error loading catalog:', err);
        });
}

// Live Search logic
function handleLiveSearch(term) {
    clearTimeout(searchDebounceTimer);
    activeDropdownIndex = -1;

    // Simultaneously filter right-side visual catalog
    filterCatalogItemsWithQuery(term);

    const dropdown = document.getElementById('liveSearchResults');
    if (!term || term.length < 1) {
        hideLiveSearchDropdown();
        return;
    }

    // First: Instant filter against in-memory catalog (0ms latency!)
    const localMatches = allCatalogProducts.filter(p => {
        const q = term.toLowerCase();
        return (p.title && p.title.toLowerCase().includes(q)) ||
               (p.urdu_title && p.urdu_title.toLowerCase().includes(q)) ||
               (p.barcode && p.barcode.toLowerCase().includes(q)) ||
               (p.isbn && p.isbn.toLowerCase().includes(q)) ||
               (p.product_code && p.product_code.toLowerCase().includes(q)) ||
               (p.author && p.author.toLowerCase().includes(q));
    });

    if (localMatches.length > 0) {
        renderLiveSearchDropdown(localMatches.slice(0, 8), term);
    } else {
        dropdown.innerHTML = `<div class="list-group-item text-center py-3 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Searching database for "${term}"...</div>`;
        dropdown.classList.remove('d-none');
    }

    // Debounced AJAX search for fresh results
    searchDebounceTimer = setTimeout(() => {
        fetch(`pos_ajax.php?action=search_products&q=${encodeURIComponent(term)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.products && data.products.length > 0) {
                    data.products.forEach(p => { productsMap[p.id] = p; });
                    renderLiveSearchDropdown(data.products.slice(0, 10), term);
                } else if (localMatches.length === 0) {
                    dropdown.innerHTML = `<div class="list-group-item text-center py-3 text-muted">No products found matching "<strong>${term}</strong>". Press Enter to scan.</div>`;
                    dropdown.classList.remove('d-none');
                }
            })
            .catch(() => {});
    }, 180);
}

function renderLiveSearchDropdown(products, term) {
    const dropdown = document.getElementById('liveSearchResults');
    if (!products.length) {
        dropdown.innerHTML = `<div class="list-group-item text-center py-3 text-muted">No products found for "${term}".</div>`;
        dropdown.classList.remove('d-none');
        return;
    }

    let html = '';
    products.forEach((p, idx) => {
        const isLow = parseFloat(p.current_stock) <= parseFloat(p.min_stock_alert);
        const isOut = parseFloat(p.current_stock) <= 0;
        const stockBadge = isOut ? '<span class="badge bg-danger">Out of Stock</span>' : 
                          (isLow ? `<span class="badge bg-warning text-dark">Stock: ${p.current_stock}</span>` : 
                                   `<span class="badge bg-success">In Stock (${p.current_stock})</span>`);

        html += `
            <a href="javascript:void(0)" class="list-group-item list-group-item-action live-search-item py-2 px-3 border-bottom d-flex justify-content-between align-items-center" data-id="${p.id}" onclick="addProductToCartById(${p.id})">
                <div style="max-width: 65%;">
                    <div class="fw-bold text-dark text-truncate">${p.title}</div>
                    <div class="small text-muted">
                        <span class="badge bg-light text-secondary border font-monospace">${p.barcode || p.product_code}</span>
                        ${p.author ? `&bull; <span>${p.author}</span>` : ''}
                        ${p.class_grade ? `&bull; <span class="text-primary">${p.class_grade}</span>` : ''}
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-primary fs-6">Rs. ${parseFloat(p.retail_price).toFixed(2)}</div>
                    <div>${stockBadge}</div>
                </div>
            </a>
        `;
    });
    dropdown.innerHTML = html;
    dropdown.classList.remove('d-none');
    activeDropdownIndex = -1;
}

function updateDropdownSelection(items) {
    items.forEach((it, idx) => {
        if (idx === activeDropdownIndex) {
            it.classList.add('active');
            it.scrollIntoView({ block: 'nearest' });
        } else {
            it.classList.remove('active');
        }
    });
}

function hideLiveSearchDropdown() {
    const dropdown = document.getElementById('liveSearchResults');
    dropdown.classList.add('d-none');
    dropdown.innerHTML = '';
    activeDropdownIndex = -1;
}

function renderCatalog(products) {
    const container = document.getElementById('catalogCardsContainer');
    if (!products || !products.length) {
        container.innerHTML = '<div class="col-12 text-center text-muted py-5"><i class="fa-solid fa-box-open fa-2x mb-2 text-secondary opacity-50"></i><br>No matching products found.</div>';
        return;
    }
    let html = '';
    products.forEach(p => {
        productsMap[p.id] = p;
        const isLow = parseFloat(p.current_stock) <= parseFloat(p.min_stock_alert);
        const isOut = parseFloat(p.current_stock) <= 0;
        html += `
            <div class="col-sm-6 col-12">
                <div class="pos-product-item p-2 h-100 d-flex flex-column justify-content-between shadow-xs" onclick="addProductToCartById(${p.id})">
                    <div>
                        <div class="d-flex justify-content-between align-items-start gap-1">
                            <span class="badge ${p.product_type === 'book' ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-dark'}" style="font-size: 0.68rem;">
                                ${(p.product_type || 'BOOK').toUpperCase()}
                            </span>
                            <span class="badge ${isOut ? 'bg-danger' : (isLow ? 'bg-warning text-dark' : 'bg-light text-secondary border')}" style="font-size: 0.68rem;">
                                Stock: ${p.current_stock}
                            </span>
                        </div>
                        <div class="fw-bold small text-truncate mt-1" title="${p.title}">${p.title}</div>
                        ${p.author ? `<div class="text-muted text-truncate" style="font-size: 0.7rem;">${p.author}</div>` : ''}
                        <div class="text-secondary font-monospace" style="font-size: 0.68rem;">${p.barcode || p.product_code}</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top">
                        <span class="fw-bold text-success small">Rs. ${parseFloat(p.retail_price).toFixed(2)}</span>
                        <span class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.72rem;"><i class="fa-solid fa-plus"></i></span>
                    </div>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

function filterCatalogItemsWithQuery(q) {
    q = (q || '').toLowerCase().trim();
    const cat = document.getElementById('catalogCategorySelect')?.value || '';

    const filtered = allCatalogProducts.filter(p => {
        const matchQ = !q || (p.title && p.title.toLowerCase().includes(q)) || 
                       (p.urdu_title && p.urdu_title.toLowerCase().includes(q)) || 
                       (p.barcode && p.barcode.toLowerCase().includes(q)) || 
                       (p.isbn && p.isbn.toLowerCase().includes(q)) || 
                       (p.product_code && p.product_code.toLowerCase().includes(q)) ||
                       (p.author && p.author.toLowerCase().includes(q));
        const matchCat = !cat || p.category_id == cat;
        return matchQ && matchCat;
    });
    renderCatalog(filtered);
}

function filterCatalogItems() {
    const q = document.getElementById('catalogSearchInput')?.value || '';
    filterCatalogItemsWithQuery(q);
}

// Add Product to Cart by ID (Safe against string quotes/escapes)
function addProductToCartById(productId, qty = 1) {
    let product = productsMap[productId];
    if (!product) {
        product = allCatalogProducts.find(x => x.id == productId);
    }
    if (product) {
        addProductToCart(product, qty);
    } else {
        // Fetch from server if not found locally
        fetch(`pos_ajax.php?action=search_products&q=${productId}`)
            .then(r => r.json())
            .then(d => {
                if (d.success && d.products && d.products.length > 0) {
                    addProductToCart(d.products[0], qty);
                }
            });
    }
}

// Add Product to Cart
function addProductToCart(product, qty = 1) {
    playBeep();
    hideLiveSearchDropdown();

    const existingIndex = cartItems.findIndex(item => item.product_id == product.id);
    if (existingIndex > -1) {
        cartItems[existingIndex].qty += qty;
        cartItems[existingIndex].line_total = (cartItems[existingIndex].qty * cartItems[existingIndex].price) - cartItems[existingIndex].discount;
    } else {
        const price = parseFloat(product.retail_price) || 0;
        cartItems.push({
            product_id: product.id,
            product_code: product.product_code,
            barcode: product.barcode,
            title: product.title,
            urdu_title: product.urdu_title,
            unit: product.unit || 'Piece',
            price: price,
            qty: qty,
            discount: 0,
            line_total: price * qty
        });
    }
    renderCart();

    const input = document.getElementById('barcodeSearchInput');
    input.value = '';
    input.focus();
}

// Barcode Lookup / Enter trigger
function triggerSearchOrScan() {
    const input = document.getElementById('barcodeSearchInput');
    const term = input.value.trim();
    if (!term) return;

    // 1. Try local exact or best match first
    const exactLocal = allCatalogProducts.find(p => 
        (p.barcode && p.barcode.toLowerCase() === term.toLowerCase()) || 
        (p.product_code && p.product_code.toLowerCase() === term.toLowerCase()) ||
        (p.isbn && p.isbn.toLowerCase() === term.toLowerCase())
    );
    if (exactLocal) {
        addProductToCart(exactLocal, 1);
        return;
    }

    // 2. Try server barcode lookup
    fetch(`pos_ajax.php?action=barcode_lookup&barcode=${encodeURIComponent(term)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.product) {
                addProductToCart(data.product, 1);
            } else {
                // If not found by exact barcode, try search endpoint
                fetch(`pos_ajax.php?action=search_products&q=${encodeURIComponent(term)}`)
                    .then(r => r.json())
                    .then(sData => {
                        if (sData.success && sData.products && sData.products.length > 0) {
                            addProductToCart(sData.products[0], 1);
                        } else {
                            // Show floating tooltip or highlight input
                            input.classList.add('is-invalid');
                            setTimeout(() => input.classList.remove('is-invalid'), 1500);
                            const dropdown = document.getElementById('liveSearchResults');
                            dropdown.innerHTML = `<div class="list-group-item list-group-item-danger py-2 px-3 small"><i class="fa-solid fa-triangle-exclamation me-1"></i> No product found for "<strong>${term}</strong>"</div>`;
                            dropdown.classList.remove('d-none');
                        }
                    });
            }
        })
        .catch(err => {
            console.error('POS Scan Error:', err);
        });
}

// Render Cart Table
function renderCart() {
    const tbody = document.getElementById('cartTableBody');
    if (cartItems.length === 0) {
        tbody.innerHTML = `
            <tr id="emptyCartRow">
                <td colspan="5" class="text-center py-5 text-muted">
                    <i class="fa-solid fa-cart-shopping fa-3x mb-2 text-secondary opacity-25"></i>
                    <div>Your POS Cart is empty.</div>
                    <small class="text-muted">Scan a barcode or click any product from the catalog on the right.</small>
                </td>
            </tr>
        `;
        recalculateTotals();
        return;
    }

    let html = '';
    cartItems.forEach((item, index) => {
        html += `
            <tr>
                <td>
                    <div class="fw-bold small text-dark">${item.title}</div>
                    <div class="text-muted" style="font-size: 0.7rem;">${item.product_code} &bull; ${item.unit}</div>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control form-control-sm" value="${item.price.toFixed(2)}" onchange="updateItemPrice(${index}, this.value)">
                </td>
                <td class="text-center">
                    <div class="input-group input-group-sm justify-content-center" style="width: 100px; margin: 0 auto;">
                        <button class="btn btn-outline-secondary" type="button" onclick="changeQty(${index}, -1)">-</button>
                        <input type="number" step="1" class="form-control text-center p-0" value="${item.qty}" min="1" onchange="updateItemQty(${index}, this.value)">
                        <button class="btn btn-outline-secondary" type="button" onclick="changeQty(${index}, 1)">+</button>
                    </div>
                </td>
                <td class="text-end fw-bold text-dark">
                    Rs. ${item.line_total.toFixed(2)}
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="removeCartItem(${index})" title="Remove">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
    recalculateTotals();
}

function changeQty(index, delta) {
    if (cartItems[index]) {
        const newQty = cartItems[index].qty + delta;
        if (newQty > 0) {
            cartItems[index].qty = newQty;
            cartItems[index].line_total = (cartItems[index].qty * cartItems[index].price) - cartItems[index].discount;
            renderCart();
        } else {
            removeCartItem(index);
        }
    }
}

function updateItemQty(index, val) {
    const q = parseFloat(val) || 1;
    if (cartItems[index]) {
        cartItems[index].qty = Math.max(1, q);
        cartItems[index].line_total = (cartItems[index].qty * cartItems[index].price) - cartItems[index].discount;
        renderCart();
    }
}

function updateItemPrice(index, val) {
    const p = parseFloat(val) || 0;
    if (cartItems[index]) {
        cartItems[index].price = p;
        cartItems[index].line_total = (cartItems[index].qty * p) - cartItems[index].discount;
        renderCart();
    }
}

function removeCartItem(index) {
    cartItems.splice(index, 1);
    renderCart();
}

function clearCart() {
    if (cartItems.length > 0 && confirm('Are you sure you want to clear the entire cart?')) {
        cartItems = [];
        renderCart();
    }
}

function recalculateTotals() {
    let subtotal = 0;
    cartItems.forEach(item => {
        subtotal += item.line_total;
    });

    const discount = parseFloat(document.getElementById('orderDiscountInput').value) || 0;
    const grandTotal = Math.max(0, subtotal - discount);

    document.getElementById('displaySubtotal').innerText = 'Rs. ' + subtotal.toFixed(2);
    document.getElementById('displayGrandTotal').innerText = 'Rs. ' + grandTotal.toFixed(2);
}

// Customer change handler
function onCustomerChange() {
    const sel = document.getElementById('posCustomerSelect');
    const opt = sel.options[sel.selectedIndex];
    const bal = parseFloat(opt.getAttribute('data-balance')) || 0;
    const pill = document.getElementById('customerBalPill');
    const val = document.getElementById('custBalVal');

    if (bal > 0) {
        pill.classList.remove('d-none');
        val.innerText = 'Rs. ' + bal.toFixed(2);
    } else {
        pill.classList.add('d-none');
    }
}

// Load complete school package into cart
function loadSchoolPackage(classId) {
    fetch(`pos_ajax.php?action=load_school_package&class_id=${classId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.items.length > 0) {
                data.items.forEach(item => {
                    addProductToCart({
                        id: item.product_id,
                        product_code: item.product_code,
                        barcode: item.barcode,
                        title: item.title,
                        unit: item.unit,
                        retail_price: item.custom_price || item.retail_price
                    }, parseFloat(item.quantity_needed));
                });
                bootstrap.Modal.getInstance(document.getElementById('schoolPackageModal')).hide();
                alert(`Loaded ${data.items.length} items from ${data.class_info.class_name}!`);
            } else {
                alert('No items found in this school package.');
            }
        });
}

// Checkout Modal
function openCheckoutModal() {
    if (cartItems.length === 0) {
        alert('Please add at least one book or product to the cart before checkout.');
        return;
    }
    let subtotal = 0;
    cartItems.forEach(item => subtotal += item.line_total);
    const discount = parseFloat(document.getElementById('orderDiscountInput').value) || 0;
    const grandTotal = Math.max(0, subtotal - discount);

    document.getElementById('modalPayableAmount').innerText = 'Rs. ' + grandTotal.toFixed(2);
    document.getElementById('tenderedAmountInput').value = grandTotal;
    document.getElementById('tenderedAmountInput').classList.remove('is-invalid');

    // Generate and render dynamic closer currency shortcuts for this specific bill
    renderSmartCashShortcuts(grandTotal);
    calculateChange();

    new bootstrap.Modal(document.getElementById('checkoutModal')).show();
}

// Generate smart cash shortcuts tailored closely to the actual bill
function generateSmartCashShortcuts(grandTotal) {
    if (grandTotal <= 0) return [];
    const suggestions = new Set();

    // 1. Exact amount
    suggestions.add(grandTotal);

    // 2. Next nearest 10, 50, 100, 500, 1000 multiples
    const next10 = Math.ceil(grandTotal / 10) * 10;
    if (next10 > grandTotal) suggestions.add(next10);

    const next50 = Math.ceil(grandTotal / 50) * 50;
    if (next50 > grandTotal) suggestions.add(next50);

    const next100 = Math.ceil(grandTotal / 100) * 100;
    if (next100 > grandTotal) suggestions.add(next100);

    const next500 = Math.ceil(grandTotal / 500) * 500;
    if (next500 > grandTotal) suggestions.add(next500);

    const next1000 = Math.ceil(grandTotal / 1000) * 1000;
    if (next1000 > grandTotal) suggestions.add(next1000);

    // 3. Circulating Pakistani currency notes (500, 1000, 2000, 5000) greater than total
    [500, 1000, 2000, 5000].forEach(denom => {
        if (denom > grandTotal) {
            suggestions.add(denom);
        }
    });

    if (grandTotal >= 5000) {
        const next5000 = Math.ceil(grandTotal / 5000) * 5000;
        if (next5000 > grandTotal) suggestions.add(next5000);
        suggestions.add(next5000 + 1000);
    }

    const sorted = Array.from(suggestions).sort((a, b) => a - b);
    return sorted.slice(0, 6);
}

function renderSmartCashShortcuts(grandTotal) {
    const container = document.getElementById('smartShortcutsContainer');
    if (!container) return;
    const shortcuts = generateSmartCashShortcuts(grandTotal);
    let html = '';
    shortcuts.forEach(amt => {
        const isExact = (Math.abs(amt - grandTotal) < 0.01);
        if (isExact) {
            html += `<button type="button" class="btn btn-sm btn-primary fw-bold px-2 py-1 flex-grow-1" onclick="setTenderedAmount(${amt})"><i class="fa-solid fa-check me-1"></i> Exact (${amt.toLocaleString()})</button>`;
        } else {
            html += `<button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1 flex-grow-1" onclick="setTenderedAmount(${amt})">${amt.toLocaleString()}</button>`;
        }
    });
    container.innerHTML = html;
}

function setTenderedAmount(amt) {
    document.getElementById('tenderedAmountInput').value = amt;
    document.getElementById('tenderedAmountInput').classList.remove('is-invalid');
    calculateChange();
}

function onPaymentMethodChange() {
    const method = document.querySelector('input[name="payMethod"]:checked').value;
    const cashDiv = document.getElementById('cashTenderedDiv');
    const depositDiv = document.getElementById('depositAccountDiv');

    if (method === 'credit') {
        cashDiv.style.display = 'none';
        depositDiv.style.display = 'none';
        document.getElementById('tenderedAmountInput').value = 0;
        calculateChange();
    } else {
        cashDiv.style.display = 'block';
        depositDiv.style.display = 'block';
        if (parseFloat(document.getElementById('tenderedAmountInput').value) <= 0) {
            exactCashTendered();
        }
    }
}

function exactCashTendered() {
    let subtotal = 0;
    cartItems.forEach(item => subtotal += item.line_total);
    const discount = parseFloat(document.getElementById('orderDiscountInput').value) || 0;
    const grandTotal = Math.max(0, subtotal - discount);
    document.getElementById('tenderedAmountInput').value = grandTotal;
    document.getElementById('tenderedAmountInput').classList.remove('is-invalid');
    calculateChange();
}

function calculateChange() {
    let subtotal = 0;
    cartItems.forEach(item => subtotal += item.line_total);
    const discount = parseFloat(document.getElementById('orderDiscountInput').value) || 0;
    const grandTotal = Math.max(0, subtotal - discount);

    const tenderedInput = document.getElementById('tenderedAmountInput');
    const tendered = parseFloat(tenderedInput.value) || 0;
    const method = document.querySelector('input[name="payMethod"]:checked').value;
    const customerId = document.getElementById('posCustomerSelect').value;
    const warningBox = document.getElementById('checkoutWarningBox');

    let change = 0;
    let due = 0;

    if (method === 'credit') {
        due = grandTotal;
        change = 0;
        if (warningBox) {
            if (customerId == 1) {
                warningBox.innerHTML = '<div class="alert alert-danger py-1 px-2 small mb-0 mt-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> Walk-in cash customer cannot purchase on credit. Select a customer for Khata.</div>';
            } else {
                warningBox.innerHTML = '<div class="alert alert-info py-1 px-2 small mb-0 mt-2"><i class="fa-solid fa-circle-info me-1"></i> Full bill of Rs. ' + grandTotal.toFixed(2) + ' will be charged to customer Khata.</div>';
            }
        }
    } else {
        if (tendered >= (grandTotal - 0.01)) {
            change = Math.max(0, tendered - grandTotal);
            due = 0;
            tenderedInput.classList.remove('is-invalid');
            if (warningBox) warningBox.innerHTML = '';
        } else {
            change = 0;
            due = grandTotal - tendered;
            if (customerId == 1) {
                tenderedInput.classList.add('is-invalid');
                if (warningBox) {
                    warningBox.innerHTML = '<div class="alert alert-danger py-1 px-2 small mb-0 mt-2"><i class="fa-solid fa-circle-xmark me-1"></i> <strong>Underpaid:</strong> Received amount (Rs. ' + tendered.toFixed(2) + ') is less than total bill (Rs. ' + grandTotal.toFixed(2) + '). Walk-in customers cannot have an unpaid balance.</div>';
                }
            } else {
                tenderedInput.classList.remove('is-invalid');
                if (warningBox) {
                    warningBox.innerHTML = '<div class="alert alert-warning py-1 px-2 small mb-0 mt-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> Remaining balance of <strong>Rs. ' + due.toFixed(2) + '</strong> will be debited to customer Khata.</div>';
                }
            }
        }
    }

    document.getElementById('displayChangeAmount').innerText = 'Rs. ' + change.toFixed(2);
    document.getElementById('displayDueAmount').innerText = 'Rs. ' + due.toFixed(2);
}

// Process Sale Checkout with strict validation
function processCheckout() {
    const btn = document.getElementById('confirmCheckoutBtn');

    let subtotal = 0;
    cartItems.forEach(item => subtotal += item.line_total);
    const discount = parseFloat(document.getElementById('orderDiscountInput').value) || 0;
    const grandTotal = Math.max(0, subtotal - discount);

    const method = document.querySelector('input[name="payMethod"]:checked').value;
    const tenderedInput = document.getElementById('tenderedAmountInput');
    const tendered = parseFloat(tenderedInput.value) || 0;
    const accountId = document.getElementById('paymentAccountId').value;
    const customerSelect = document.getElementById('posCustomerSelect');
    const customerId = customerSelect.value;
    const customerName = customerSelect.options[customerSelect.selectedIndex]?.text || 'Walk-in Customer';
    const notes = document.getElementById('saleNotesInput').value;

    // VALIDATION 1: Walk-in customers cannot purchase on Khata/Credit
    if (method === 'credit' && customerId == 1) {
        alert("Validation Error: Walk-in customers cannot purchase on Credit / Khata.\n\nPlease select a registered customer profile from the customer list, or switch to Cash / Bank / Wallet payment.");
        customerSelect.focus();
        return;
    }

    // VALIDATION 2: Checking paid amount is not less than total bill
    if (method !== 'credit' && tendered < (grandTotal - 0.01)) {
        if (customerId == 1) {
            alert("Validation Error: Received amount (Rs. " + tendered.toFixed(2) + ") is less than the total bill (Rs. " + grandTotal.toFixed(2) + ").\n\nWalk-in customers cannot have an unpaid balance.\nPlease receive the full bill amount or select a registered customer to record in Khata.");
            tenderedInput.focus();
            tenderedInput.classList.add('is-invalid');
            return;
        } else {
            const shortAmt = (grandTotal - tendered).toFixed(2);
            const confirmed = confirm("Partial Payment Notice:\n\nTotal Bill: Rs. " + grandTotal.toFixed(2) + "\nReceived: Rs. " + tendered.toFixed(2) + "\nRemaining Due: Rs. " + shortAmt + "\n\nThis remaining balance of Rs. " + shortAmt + " will be added to " + customerName + "'s Khata.\n\nDo you want to proceed?");
            if (!confirmed) {
                tenderedInput.focus();
                return;
            }
        }
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Processing...';

    const paidAmount = (method === 'credit') ? 0 : Math.min(grandTotal, tendered);

    const payload = {
        customer_id: customerId,
        items: cartItems,
        subtotal: subtotal,
        discount_amount: discount,
        tax_amount: 0,
        grand_total: grandTotal,
        paid_amount: paidAmount,
        tendered_amount: tendered,
        payment_method: method,
        account_id: accountId,
        notes: notes,
        sale_type: 'pos'
    };

    fetch('pos_ajax.php?action=checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i> Complete & Print';

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('checkoutModal')).hide();

            // Populate success modal
            document.getElementById('successInvoiceNo').innerText = data.invoice_no;
            document.getElementById('successTotal').innerText = 'Rs. ' + data.grand_total.toFixed(2);
            document.getElementById('successChange').innerText = 'Rs. ' + data.change.toFixed(2);
            document.getElementById('thermalPrintLink').href = 'print_invoice.php?id=' + data.sale_id + '&type=thermal';
            document.getElementById('a4PrintLink').href = 'print_invoice.php?id=' + data.sale_id + '&type=a4';

            new bootstrap.Modal(document.getElementById('receiptSuccessModal')).show();

            // Auto-trigger thermal receipt in background or new window
            // window.open('print_invoice.php?id=' + data.sale_id + '&type=thermal', '_blank');
        } else {
            alert('Error processing checkout: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i> Complete & Print';
        alert('Network / Server Error: ' + err);
    });
}

function startNewSale() {
    cartItems = [];
    document.getElementById('orderDiscountInput').value = '0.00';
    document.getElementById('posCustomerSelect').value = '1';
    renderCart();
    bootstrap.Modal.getInstance(document.getElementById('receiptSuccessModal')).hide();
    document.getElementById('barcodeSearchInput').focus();
    loadCatalog(); // Refresh stock counts in background
}

// Hold & Resume
function holdCurrentBill() {
    if (cartItems.length === 0) {
        alert('Cart is empty.');
        return;
    }
    let subtotal = 0;
    cartItems.forEach(item => subtotal += item.line_total);
    const discount = parseFloat(document.getElementById('orderDiscountInput').value) || 0;
    const grandTotal = Math.max(0, subtotal - discount);

    const sel = document.getElementById('posCustomerSelect');
    const custName = sel.options[sel.selectedIndex].text;

    fetch('pos_ajax.php?action=hold_bill', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            customer_id: sel.value,
            customer_name: custName,
            items: cartItems,
            subtotal: subtotal,
            discount_amount: discount,
            grand_total: grandTotal
        })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('Bill held successfully with reference: ' + d.hold_ref);
            cartItems = [];
            renderCart();
            checkHeldBills();
        }
    });
}

function checkHeldBills() {
    fetch('pos_ajax.php?action=get_held_bills')
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const count = d.held_bills.length;
                document.getElementById('heldBillsBadge').innerText = count;
            }
        });
}

function openHeldBillsModal() {
    fetch('pos_ajax.php?action=get_held_bills')
        .then(r => r.json())
        .then(d => {
            const list = document.getElementById('heldBillsList');
            if (d.success && d.held_bills.length > 0) {
                let html = '';
                d.held_bills.forEach(b => {
                    html += `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold">${b.hold_ref} &bull; ${b.customer_name}</h6>
                                <small class="text-muted">Held at ${b.time} &bull; ${b.items.length} items &bull; <strong>Rs. ${parseFloat(b.grand_total).toFixed(2)}</strong></small>
                            </div>
                            <button type="button" class="btn btn-sm btn-success" onclick="resumeHeldBill('${b.hold_ref}')">
                                <i class="fa-solid fa-play me-1"></i> Resume Bill
                            </button>
                        </div>
                    `;
                });
                list.innerHTML = html;
            } else {
                list.innerHTML = '<div class="text-center py-4 text-muted">No held bills found.</div>';
            }
            new bootstrap.Modal(document.getElementById('heldBillsModal')).show();
        });
}

function resumeHeldBill(ref) {
    fetch(`pos_ajax.php?action=resume_bill&hold_ref=${encodeURIComponent(ref)}`)
        .then(r => r.json())
        .then(d => {
            if (d.success && d.bill) {
                cartItems = d.bill.items;
                document.getElementById('orderDiscountInput').value = d.bill.discount || 0;
                if (d.bill.customer_id) {
                    document.getElementById('posCustomerSelect').value = d.bill.customer_id;
                }
                renderCart();
                bootstrap.Modal.getInstance(document.getElementById('heldBillsModal')).hide();
                checkHeldBills();
            } else {
                alert('Could not resume held bill.');
            }
        });
}

// ========================================================
// CAMERA BARCODE SCANNER WITH AUTO-ALIGNMENT & AUTO-ZOOM
// ========================================================
let html5QrScanner = null;
let activeCameraTrack = null;
let currentScannerZoom = 1.5;
let scannerTorchActive = false;
let lastScannedText = '';
let lastScannedTime = 0;
let scannerTargetFieldId = null;
let scannerToastTimer = null;
let availableCameras = [];
let currentCameraDeviceId = null;

function openCameraScannerModal(targetFieldId = null) {
    scannerTargetFieldId = targetFieldId;
    
    // Clear feedback notifications & reset target frame
    hideScannerToasts();
    const errorNotice = document.getElementById('scannerErrorNotice');
    if (errorNotice) {
        errorNotice.classList.add('d-none');
        errorNotice.innerHTML = '';
    }
    const targetBox = document.getElementById('scannerTargetBox');
    if (targetBox) targetBox.classList.remove('detected');

    // Show modal
    const modalEl = document.getElementById('cameraScannerModal');
    const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
    modalInstance.show();

    // Start camera stream after modal is visible
    setTimeout(() => {
        initCameraScanner();
    }, 350);
}

function initCameraScanner() {
    if (typeof Html5Qrcode === 'undefined') {
        showScannerErrorNotice('Scanner library not ready. Please ensure assets/js/html5-qrcode.min.js is present.');
        return;
    }

    if (html5QrScanner) {
        try {
            if (html5QrScanner.isScanning) {
                html5QrScanner.stop().then(() => startScannerWithCamera()).catch(() => startScannerWithCamera());
                return;
            }
        } catch(e) {}
    }

    startScannerWithCamera();
}

function startScannerWithCamera() {
    html5QrScanner = new Html5Qrcode("interactiveScannerReader");

    // Barcode scanning configuration optimized for 1D ISBN, EAN-13, and Code128
    const config = {
        fps: 24,
        qrbox: function(viewfinderWidth, viewfinderHeight) {
            // Precise rectangular alignment viewport
            const boxWidth = Math.min(Math.floor(viewfinderWidth * 0.84), 340);
            const boxHeight = Math.min(Math.floor(boxWidth * 0.48), 155);
            return { width: boxWidth, height: boxHeight };
        },
        aspectRatio: 1.777778,
        formatsToSupport: [
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E,
            Html5QrcodeSupportedFormats.QR_CODE
        ],
        experimentalFeatures: {
            useBarCodeDetectorIfSupported: true
        }
    };

    Html5Qrcode.getCameras().then(devices => {
        availableCameras = devices || [];
        populateCameraSelect(availableCameras);

        let cameraIdOrConfig = { facingMode: "environment" };
        if (currentCameraDeviceId) {
            cameraIdOrConfig = currentCameraDeviceId;
        } else if (availableCameras.length > 0) {
            // Auto-prefer rear/environment/back camera for physical book barcode scanning
            const backCam = availableCameras.find(c => /back|rear|environment/i.test(c.label));
            if (backCam) {
                cameraIdOrConfig = backCam.id;
                currentCameraDeviceId = backCam.id;
            } else {
                cameraIdOrConfig = availableCameras[availableCameras.length - 1].id;
                currentCameraDeviceId = cameraIdOrConfig;
            }
            const camSelect = document.getElementById('cameraSourceSelect');
            if (camSelect) camSelect.value = currentCameraDeviceId;
        }

        html5QrScanner.start(
            cameraIdOrConfig,
            config,
            onBarcodeSuccess,
            (errorMessage) => {
                // Ignore transient frame decode misses
            }
        ).then(() => {
            // Camera stream active
            attachCameraCapabilities();
            // Automatically align & zoom (1.5x preset) for high-accuracy barcode detection
            applyScannerZoom(1.5);
        }).catch(err => {
            console.error("Camera start error:", err);
            showScannerErrorNotice('Could not start camera stream: ' + (err.message || err) + '. Please allow camera access in browser permissions.');
        });
    }).catch(err => {
        console.error("GetCameras error:", err);
        showScannerErrorNotice('No cameras detected: ' + (err.message || err));
    });
}

function populateCameraSelect(cameras) {
    const select = document.getElementById('cameraSourceSelect');
    if (!select) return;
    select.innerHTML = '';

    if (!cameras || cameras.length === 0) {
        select.innerHTML = '<option value="">No Camera Found</option>';
        return;
    }

    cameras.forEach((cam, idx) => {
        const opt = document.createElement('option');
        opt.value = cam.id;
        opt.text = cam.label || `Camera ${idx + 1}`;
        select.appendChild(opt);
    });
}

function switchCameraSource(newDeviceId) {
    if (!newDeviceId || newDeviceId === currentCameraDeviceId) return;
    currentCameraDeviceId = newDeviceId;
    if (html5QrScanner && html5QrScanner.isScanning) {
        html5QrScanner.stop().then(() => {
            startScannerWithCamera();
        }).catch(err => {
            console.error('Switch camera stop error:', err);
            startScannerWithCamera();
        });
    } else {
        startScannerWithCamera();
    }
}

function attachCameraCapabilities() {
    activeCameraTrack = null;
    const videoEl = document.querySelector('#interactiveScannerReader video');
    if (videoEl && videoEl.srcObject) {
        const tracks = videoEl.srcObject.getVideoTracks();
        if (tracks && tracks.length > 0) {
            activeCameraTrack = tracks[0];
            
            if (typeof activeCameraTrack.getCapabilities === 'function') {
                const caps = activeCameraTrack.getCapabilities();
                
                // Hardware Torch / Flashlight support
                const torchBtn = document.getElementById('scannerTorchBtn');
                if (torchBtn) {
                    if (caps.torch) {
                        torchBtn.classList.remove('d-none');
                    } else {
                        torchBtn.classList.add('d-none');
                    }
                }

                // Hardware Zoom slider calibration
                const slider = document.getElementById('scannerZoomSlider');
                if (slider && caps.zoom) {
                    slider.min = caps.zoom.min || 1.0;
                    slider.max = caps.zoom.max || 4.0;
                    slider.step = caps.zoom.step || 0.1;
                }
            }
        }
    }
}

function applyScannerZoom(zoomVal) {
    currentScannerZoom = parseFloat(zoomVal) || 1.0;

    // 1. Hardware Optical / Digital Track Zoom (Phones & HD webcams)
    if (activeCameraTrack && typeof activeCameraTrack.getCapabilities === 'function') {
        try {
            const caps = activeCameraTrack.getCapabilities();
            if (caps.zoom) {
                const targetZ = Math.min(caps.zoom.max || 4.0, Math.max(caps.zoom.min || 1.0, currentScannerZoom));
                activeCameraTrack.applyConstraints({
                    advanced: [{ zoom: targetZ }]
                }).catch(() => {});
            }
        } catch(e) {}
    }

    // 2. CSS Centered Viewport Zoom (Guarantees crisp zoom and alignment on ALL browsers & laptops)
    const videoEl = document.querySelector('#interactiveScannerReader video');
    if (videoEl) {
        videoEl.style.transform = `scale(${currentScannerZoom})`;
        videoEl.style.transformOrigin = 'center center';
    }

    // 3. Update Zoom UI buttons and slider
    document.querySelectorAll('.zoom-btn').forEach(btn => {
        const btnZ = parseFloat(btn.dataset.zoom);
        if (Math.abs(btnZ - currentScannerZoom) < 0.1) {
            btn.classList.add('btn-primary', 'active');
            btn.classList.remove('btn-outline-secondary');
        } else {
            btn.classList.remove('btn-primary', 'active');
            btn.classList.add('btn-outline-secondary');
        }
    });

    const slider = document.getElementById('scannerZoomSlider');
    if (slider) slider.value = currentScannerZoom;
}

function toggleScannerTorch() {
    if (!activeCameraTrack || typeof activeCameraTrack.applyConstraints !== 'function') return;
    scannerTorchActive = !scannerTorchActive;
    activeCameraTrack.applyConstraints({
        advanced: [{ torch: scannerTorchActive }]
    }).then(() => {
        const btn = document.getElementById('scannerTorchBtn');
        if (btn) {
            if (scannerTorchActive) {
                btn.classList.add('btn-warning', 'text-dark');
                btn.classList.remove('btn-outline-light');
            } else {
                btn.classList.remove('btn-warning', 'text-dark');
                btn.classList.add('btn-outline-light');
            }
        }
    }).catch(err => {
        console.warn('Torch constraint error:', err);
    });
}

function stopCameraScanner() {
    if (html5QrScanner) {
        try {
            if (html5QrScanner.isScanning) {
                html5QrScanner.stop().then(() => {
                    html5QrScanner.clear();
                }).catch(() => {});
            }
        } catch(e) {}
    }
    activeCameraTrack = null;
    scannerTorchActive = false;
    const torchBtn = document.getElementById('scannerTorchBtn');
    if (torchBtn) {
        torchBtn.classList.remove('btn-warning', 'text-dark');
        torchBtn.classList.add('btn-outline-light');
    }
    const input = document.getElementById('barcodeSearchInput');
    if (input) input.focus();
}

function onBarcodeSuccess(decodedText, decodedResult) {
    if (!decodedText) return;
    const code = decodedText.trim();
    const now = Date.now();

    // Prevent immediate duplicate scans of the exact same code within 1.8s
    if (code === lastScannedText && (now - lastScannedTime) < 1800) {
        return;
    }
    lastScannedText = code;
    lastScannedTime = now;

    // Positive audio & haptic feedback
    playBeep();
    if (navigator.vibrate) navigator.vibrate([70]);

    // Visual HUD pulse
    const targetBox = document.getElementById('scannerTargetBox');
    if (targetBox) {
        targetBox.classList.add('detected');
        setTimeout(() => targetBox.classList.remove('detected'), 650);
    }

    // If filling a specific form field (e.g. from product catalog)
    if (scannerTargetFieldId) {
        const targetInput = document.getElementById(scannerTargetFieldId);
        if (targetInput) {
            targetInput.value = code;
            targetInput.dispatchEvent(new Event('input', { bubbles: true }));
            targetInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        bootstrap.Modal.getInstance(document.getElementById('cameraScannerModal')).hide();
        stopCameraScanner();
        return;
    }

    // Default: Add item directly to POS Cart
    handleCameraScannedCode(code);

    // If Continuous Scan is toggled OFF, close modal
    const isContinuous = document.getElementById('continuousScanToggle')?.checked ?? true;
    if (!isContinuous) {
        bootstrap.Modal.getInstance(document.getElementById('cameraScannerModal')).hide();
        stopCameraScanner();
    }
}

function handleCameraScannedCode(code) {
    // 1. Check local catalog
    const exactLocal = allCatalogProducts.find(p => 
        (p.barcode && p.barcode.toLowerCase() === code.toLowerCase()) || 
        (p.product_code && p.product_code.toLowerCase() === code.toLowerCase()) ||
        (p.isbn && p.isbn.toLowerCase() === code.toLowerCase())
    );

    if (exactLocal) {
        addProductToCart(exactLocal, 1);
        showScannerFeedbackToast(exactLocal);
        return;
    }

    // 2. Query server for barcode / ISBN
    fetch(`pos_ajax.php?action=barcode_lookup&barcode=${encodeURIComponent(code)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.product) {
                addProductToCart(data.product, 1);
                showScannerFeedbackToast(data.product);
            } else {
                fetch(`pos_ajax.php?action=search_products&q=${encodeURIComponent(code)}`)
                    .then(r => r.json())
                    .then(sData => {
                        if (sData.success && sData.products && sData.products.length > 0) {
                            addProductToCart(sData.products[0], 1);
                            showScannerFeedbackToast(sData.products[0]);
                        } else {
                            showScannerErrorToast(`Code "${code}" is not in catalog`);
                        }
                    });
            }
        })
        .catch(err => {
            console.error('Camera scan fetch error:', err);
            showScannerErrorToast(`Lookup error for: ${code}`);
        });
}

function showScannerFeedbackToast(product) {
    hideScannerToasts();
    const toast = document.getElementById('scannerFeedbackToast');
    const titleEl = document.getElementById('toastScannedTitle');
    const detailsEl = document.getElementById('toastScannedDetails');

    if (toast && titleEl && detailsEl) {
        titleEl.textContent = product.title;
        const totalItems = cartItems.reduce((sum, item) => sum + item.quantity, 0);
        detailsEl.innerHTML = `Rs. ${parseFloat(product.retail_price).toFixed(2)} &bull; Added! Cart: ${totalItems} items`;
        toast.classList.remove('d-none');

        clearTimeout(scannerToastTimer);
        scannerToastTimer = setTimeout(() => {
            toast.classList.add('d-none');
        }, 2400);
    }
}

function showScannerErrorToast(msg) {
    hideScannerToasts();
    const toast = document.getElementById('scannerErrorToast');
    const detailsEl = document.getElementById('toastErrorDetails');

    if (toast && detailsEl) {
        detailsEl.textContent = msg;
        toast.classList.remove('d-none');

        clearTimeout(scannerToastTimer);
        scannerToastTimer = setTimeout(() => {
            toast.classList.add('d-none');
        }, 2600);
    }
}

function hideScannerToasts() {
    clearTimeout(scannerToastTimer);
    document.getElementById('scannerFeedbackToast')?.classList.add('d-none');
    document.getElementById('scannerErrorToast')?.classList.add('d-none');
}

function showScannerErrorNotice(msg) {
    const notice = document.getElementById('scannerErrorNotice');
    if (notice) {
        notice.innerHTML = `<i class="fa-solid fa-triangle-exclamation me-1"></i> ${msg}`;
        notice.classList.remove('d-none');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
