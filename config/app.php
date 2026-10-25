<?php
// config/app.php - Application core configurations, helpers, auth and localization

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// Base URL helper
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    return rtrim($protocol . $hostName . $scriptDir, '/');
}

// Language / Localization
function getLanguage() {
    return $_SESSION['app_lang'] ?? 'en';
}

function setLanguage($lang) {
    if (in_array($lang, ['en', 'ur'])) {
        $_SESSION['app_lang'] = $lang;
    }
}

// Translations Dictionary
$TRANSLATIONS = [
    'en' => [
        'app_name' => 'Book Shop Management System',
        'dashboard' => 'Dashboard',
        'pos' => 'POS Billing',
        'books_catalog' => 'Books & Products',
        'inventory' => 'Inventory & Stock',
        'sales_history' => 'Sales & Invoices',
        'purchases' => 'Purchases & Inward',
        'suppliers' => 'Suppliers',
        'customers' => 'Customers & Khata',
        'schools' => 'School Book Lists',
        'accounting' => 'Accounts & Cashbook',
        'reports' => 'Reports & Analytics',
        'users' => 'Users & Roles',
        'settings' => 'Settings',
        'logout' => 'Logout',
        'total_books' => 'Total Books',
        'total_stationery' => 'Stationery Items',
        'today_sales' => "Today's Sales",
        'today_profit' => "Today's Profit",
        'customer_receivables' => 'Customer Receivables',
        'supplier_payables' => 'Supplier Payables',
        'cash_in_hand' => 'Cash in Hand',
        'low_stock_alerts' => 'Low Stock Alerts',
        'search_placeholder' => 'Scan Barcode or Search Title, ISBN, Author (F2)...',
        'walkin_customer' => 'Walk-in Customer (Cash)',
        'grand_total' => 'Grand Total',
        'subtotal' => 'Subtotal',
        'discount' => 'Discount',
        'tax' => 'Tax',
        'paid' => 'Paid',
        'due' => 'Due',
        'change' => 'Change',
        'checkout' => 'Checkout (F8)',
        'hold_bill' => 'Hold Bill (F4)',
        'print_receipt' => 'Print Receipt (F9)',
        'qty' => 'Qty',
        'price' => 'Price',
        'action' => 'Action',
        'currency' => 'Rs.',
    ],
    'ur' => [
        'app_name' => 'بک شاپ مینجمنٹ سسٹم',
        'dashboard' => 'ڈیش بورڈ',
        'pos' => 'پی او ایس بلنگ',
        'books_catalog' => 'کتب اور اشیاء',
        'inventory' => 'اسٹاک اور انوینٹری',
        'sales_history' => 'فروخت اور رسیدات',
        'purchases' => 'خریداری و سپلائرز',
        'suppliers' => 'سپلائرز (ڈیلرز)',
        'customers' => 'گاہک اور کھاتہ',
        'schools' => 'سکول نصاب و پیکجز',
        'accounting' => 'اکاؤنٹس و کیش بک',
        'reports' => 'رپورٹس اور تجزیات',
        'users' => 'صارفین اور اختیارات',
        'settings' => 'ترتیبات',
        'logout' => 'لاگ آؤٹ',
        'total_books' => 'کل کتب',
        'total_stationery' => 'اسٹیشنری آئٹمز',
        'today_sales' => 'آج کی کل فروخت',
        'today_profit' => 'آج کا خالص منافع',
        'customer_receivables' => 'گاہکوں کے واجبات (کھاتہ)',
        'supplier_payables' => 'سپلائرز کی ادائیگیاں',
        'cash_in_hand' => 'موجودہ کیش (ہاتھ میں)',
        'low_stock_alerts' => 'کم اسٹاک الرٹس',
        'search_placeholder' => 'بارکوڈ اسکین کریں یا نام، آئی ایس بی این تلاش کریں...',
        'walkin_customer' => 'عام گاہک (نقد)',
        'grand_total' => 'کل رقم',
        'subtotal' => 'سب ٹوٹل',
        'discount' => 'رعایت',
        'tax' => 'ٹیکس',
        'paid' => 'وصول شدہ',
        'due' => 'باقی رقم',
        'change' => 'بقایا رقم',
        'checkout' => 'بل مکمل کریں (F8)',
        'hold_bill' => 'بل ہولڈ کریں (F4)',
        'print_receipt' => 'رسید پرنٹ کریں (F9)',
        'qty' => 'تعداد',
        'price' => 'قیمت',
        'action' => 'عمل',
        'currency' => 'روپے',
    ]
];

function __t($key) {
    global $TRANSLATIONS;
    $lang = getLanguage();
    return $TRANSLATIONS[$lang][$key] ?? $TRANSLATIONS['en'][$key] ?? $key;
}

// Get System Setting
function getSetting($key, $default = '') {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    try {
        $row = dbFetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        $val = $row ? $row['setting_value'] : $default;
        $cache[$key] = $val;
        return $val;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting($key, $val) {
    dbQuery("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE setting_value = ?", [$key, $val, $val]);
}

// Format Currency
function formatCurrency($amount) {
    $symbol = getSetting('currency_symbol', 'Rs. ');
    return $symbol . number_format((float)$amount, 2);
}

// Flash Messages
function setFlash($type, $message) {
    $_SESSION['flash_msg'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash_msg'])) {
        $msg = $_SESSION['flash_msg'];
        unset($_SESSION['flash_msg']);
        return $msg;
    }
    return null;
}

// Authentication Helpers
function authUser() {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn() {
    return !empty($_SESSION['user']['id']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        $loginUrl = 'login.php';
        header("Location: $loginUrl");
        exit;
    }
}

function requireRole($roles = []) {
    requireAuth();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    if (!in_array($_SESSION['user']['role'], $roles)) {
        setFlash('danger', 'Access denied. You do not have permission for this section.');
        header("Location: index.php");
        exit;
    }
}

// Stock Movement Logger Helper
function recordStockMovement($productId, $movementType, $refId, $qtyChange, $notes = '', $unitCost = 0) {
    $db = getDB();
    $prod = dbFetchOne("SELECT current_stock, purchase_price FROM products WHERE id = ?", [$productId]);
    if (!$prod) return false;
    
    $prevStock = (float)$prod['current_stock'];
    $newStock = $prevStock + (float)$qtyChange;
    $cost = $unitCost > 0 ? $unitCost : (float)$prod['purchase_price'];
    $userId = $_SESSION['user']['id'] ?? 1;

    // Update product stock
    dbQuery("UPDATE products SET current_stock = ? WHERE id = ?", [$newStock, $productId]);

    // Record movement
    dbQuery("INSERT INTO stock_movements (product_id, movement_date, movement_type, reference_id, quantity_change, previous_stock, new_stock, unit_cost, notes, created_by)
             VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)",
             [$productId, $movementType, $refId, $qtyChange, $prevStock, $newStock, $cost, $notes, $userId]);
    return true;
}

// Customer Ledger Helper
function recordCustomerLedger($customerId, $txType, $refId, $invoiceNo, $debit, $credit, $description) {
    if (!$customerId) return;
    $cust = dbFetchOne("SELECT current_balance FROM customers WHERE id = ?", [$customerId]);
    $prevBal = $cust ? (float)$cust['current_balance'] : 0;
    // Debit increases receivable (+), Credit decreases receivable (-)
    $newBal = $prevBal + $debit - $credit;

    dbQuery("UPDATE customers SET current_balance = ? WHERE id = ?", [$newBal, $customerId]);

    dbQuery("INSERT INTO customer_ledgers (customer_id, transaction_date, transaction_type, reference_id, invoice_no, debit, credit, balance, description)
             VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)",
             [$customerId, $txType, $refId, $invoiceNo, $debit, $credit, $newBal, $description]);
}

// Supplier Ledger Helper
function recordSupplierLedger($supplierId, $txType, $refId, $invoiceNo, $debit, $credit, $description) {
    if (!$supplierId) return;
    $supp = dbFetchOne("SELECT current_balance FROM suppliers WHERE id = ?", [$supplierId]);
    $prevBal = $supp ? (float)$supp['current_balance'] : 0;
    // Credit increases payable (+), Debit reduces payable (-)
    $newBal = $prevBal + $credit - $debit;

    dbQuery("UPDATE suppliers SET current_balance = ? WHERE id = ?", [$newBal, $supplierId]);

    dbQuery("INSERT INTO supplier_ledgers (supplier_id, transaction_date, transaction_type, reference_id, invoice_no, debit, credit, balance, description)
             VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)",
             [$supplierId, $txType, $refId, $invoiceNo, $debit, $credit, $newBal, $description]);
}

// Account Balance & Cash Transaction Helper
function recordCashTransaction($type, $accountId, $amount, $category, $refNo, $desc, $toAccountId = null) {
    $userId = $_SESSION['user']['id'] ?? 1;
    $amount = abs((float)$amount);
    
    // Inflow types: income, customer_payment, sale
    // Outflow types: expense, supplier_payment, purchase
    if (in_array($type, ['income', 'customer_payment', 'sale'])) {
        dbQuery("UPDATE accounts SET balance = balance + ? WHERE id = ?", [$amount, $accountId]);
    } elseif (in_array($type, ['expense', 'supplier_payment', 'purchase'])) {
        dbQuery("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$amount, $accountId]);
    } elseif ($type === 'transfer' && $toAccountId) {
        dbQuery("UPDATE accounts SET balance = balance - ? WHERE id = ?", [$amount, $accountId]);
        dbQuery("UPDATE accounts SET balance = balance + ? WHERE id = ?", [$amount, $toAccountId]);
    }

    dbQuery("INSERT INTO cash_transactions (transaction_date, transaction_type, account_id, to_account_id, category_name, amount, reference_no, description, created_by)
             VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?)",
             [$type, $accountId, $toAccountId, $category, $amount, $refNo, $desc, $userId]);
}
