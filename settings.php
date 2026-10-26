<?php
// settings.php - System Configuration & Store Settings
require_once __DIR__ . '/config/app.php';
requireAuth();
requireRole('admin');

// Handle POST Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        $keys = [
            'shop_name', 'shop_name_urdu', 'shop_tagline', 'shop_address',
            'shop_phone', 'shop_email', 'currency_symbol', 'currency_code',
            'tax_percent', 'receipt_footer', 'receipt_footer_urdu'
        ];
        foreach ($keys as $k) {
            if (isset($_POST[$k])) {
                setSetting($k, trim($_POST[$k]));
            }
        }
        setFlash('success', 'System settings saved successfully.');
        redirect("settings.php");
    }

    // Reset Sample Data
    if (isset($_POST['reseed_data'])) {
        require_once __DIR__ . '/install.php';
        runInstallation();
        setFlash('success', 'Database sample data successfully restored to defaults.');
        redirect("settings.php");
    }
}

// Current settings values
$shopName = getSetting('shop_name', 'Kitab Ghar & Stationery');
$shopNameUrdu = getSetting('shop_name_urdu', 'کتاب گھر اینڈ اسٹیشنری مارٹ');
$shopTagline = getSetting('shop_tagline', 'Complete Textbooks, General Books & Quality Stationery');
$shopAddress = getSetting('shop_address', 'Shop # 14-18, Urdu Bazaar, Lahore, Pakistan');
$shopPhone = getSetting('shop_phone', '+92 300 1234567 / 042-37210000');
$shopEmail = getSetting('shop_email', 'sales@kitabghar.pk');
$currSymbol = getSetting('currency_symbol', 'Rs. ');
$currCode = getSetting('currency_code', 'PKR');
$taxRate = getSetting('tax_percent', '0.00');
$footerNote = getSetting('receipt_footer', 'Thank you for shopping with us! Books once sold cannot be returned without receipt.');
$footerNoteUrdu = getSetting('receipt_footer_urdu', 'خریداری کا شکریہ! خریدی گئی کتب 7 دن کے اندر اصل رسید کے ہمراہ تبدیل کی جا سکتی ہیں۔');

$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-sliders text-primary me-2"></i> System Configuration & Store Settings</h4>
        <small class="text-muted">Configure store identity, thermal receipts, tax, and currency</small>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-store text-primary me-2"></i> Bookstore Business Profile</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="settings.php">
                    <input type="hidden" name="save_settings" value="1">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Bookstore Name (English) *</label>
                            <input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($shopName) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Bookstore Name (اردو نام)</label>
                            <input type="text" name="shop_name_urdu" class="form-control" value="<?= htmlspecialchars($shopNameUrdu) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tagline / Motto</label>
                        <input type="text" name="shop_tagline" class="form-control" value="<?= htmlspecialchars($shopTagline) ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Contact Phone / Landline</label>
                            <input type="text" name="shop_phone" class="form-control" value="<?= htmlspecialchars($shopPhone) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Official Email</label>
                            <input type="email" name="shop_email" class="form-control" value="<?= htmlspecialchars($shopEmail) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Store Physical Address</label>
                        <textarea name="shop_address" class="form-control" rows="2"><?= htmlspecialchars($shopAddress) ?></textarea>
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-coins me-2"></i> Currency & Financials</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($currSymbol) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Currency Code</label>
                            <input type="text" name="currency_code" class="form-control" value="<?= htmlspecialchars($currCode) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Sales Tax (%)</label>
                            <input type="number" step="0.01" name="tax_percent" class="form-control" value="<?= htmlspecialchars($taxRate) ?>">
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-receipt me-2"></i> Thermal Receipt & Invoice Customization</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Receipt Footer Note (English)</label>
                        <input type="text" name="receipt_footer" class="form-control" value="<?= htmlspecialchars($footerNote) ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Receipt Footer Note (اردو واپسی پالیسی)</label>
                        <input type="text" name="receipt_footer_urdu" class="form-control" value="<?= htmlspecialchars($footerNoteUrdu) ?>">
                    </div>

                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="fa-solid fa-save me-1"></i> Save All Settings
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- System Tools & Information -->
    <div class="col-lg-4">
        <!-- System Info Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-server text-info me-2"></i> Server & Database Info</h6>
            </div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Environment:</span>
                    <strong>WAMP (Windows)</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">PHP Version:</span>
                    <strong><?= phpversion() ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Database Engine:</span>
                    <strong>MySQL 8 (InnoDB utf8mb4)</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">POS Barcode Driver:</span>
                    <strong>Code128 / EAN-13 JS</strong>
                </div>
            </div>
        </div>

        <!-- Sample Data Reset Card -->
        <div class="card shadow-sm border-warning">
            <div class="card-header bg-warning-subtle py-3">
                <h6 class="mb-0 fw-bold text-warning-emphasis"><i class="fa-solid fa-rotate-right me-2"></i> Restore Sample Data</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">Re-initializes sample Oxford syllabus textbooks, Punjab Board matric books, Urdu literature, stationery items, and sample schools.</p>
                <form method="POST" action="settings.php" onsubmit="return confirm('Restore all default sample data? Existing customized tables will be updated.')">
                    <input type="hidden" name="reseed_data" value="1">
                    <button type="submit" class="btn btn-outline-warning text-dark w-100 fw-semibold btn-sm">
                        <i class="fa-solid fa-arrows-rotate me-1"></i> Re-seed Sample Database
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
