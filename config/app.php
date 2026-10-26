<?php
// config/app.php - Application core configurations, helpers, auth and localization

// Enable output buffering so headers can always be sent without 'headers already sent' errors
if (!ob_get_level()) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// Language / Localization
function getLanguage() {
    return $_SESSION['app_lang'] ?? 'en';
}

function setLanguage($lang) {
    if (in_array($lang, ['en', 'ur'])) {
        $_SESSION['app_lang'] = $lang;
    }
}

// Translations Dictionary (Active Navigation & KPI Terms)
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
        'today_sales' => "Today's Sales",
        'today_profit' => "Today's Profit",
        'customer_receivables' => 'Customer Receivables',
        'supplier_payables' => 'Supplier Payables',
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
        'today_sales' => 'آج کی کل فروخت',
        'today_profit' => 'آج کا خالص منافع',
        'customer_receivables' => 'گاہکوں کے واجبات (کھاتہ)',
        'supplier_payables' => 'سپلائرز کی ادائیگیاں',
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

// Safe Redirect Helper (Handles both HTTP header and JS/Meta fallback)
function redirect($url) {
    if (!headers_sent()) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Location: $url");
        exit;
    }
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
    exit;
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

// Product Types Helper Functions
function ensureProductSchema() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        dbQuery("CREATE TABLE IF NOT EXISTS `product_types` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(50) UNIQUE NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NULL,
            `is_system` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $cnt = dbFetchOne("SELECT COUNT(*) as c FROM product_types")['c'] ?? 0;
        if ($cnt == 0) {
            dbQuery("INSERT IGNORE INTO product_types (code, name, description, is_system) VALUES 
                ('book', 'Book / Textbook / Novel', 'Curriculum textbooks, literature, general books', 1),
                ('stationery', 'Stationery / Office Supply', 'Notebooks, pens, registers, office supplies', 1),
                ('toys', 'Toys & Games', 'Educational toys, board games, puzzles and creative play', 0)");
        } else {
            // Ensure default toys product type is available
            dbQuery("INSERT IGNORE INTO product_types (code, name, description, is_system) VALUES 
                ('toys', 'Toys & Games', 'Educational toys, board games, puzzles and creative play', 0)");
        }

        // Ensure products and categories tables support arbitrary custom types
        dbQuery("ALTER TABLE products MODIFY COLUMN product_type VARCHAR(50) NOT NULL DEFAULT 'book'");
        dbQuery("ALTER TABLE categories MODIFY COLUMN type VARCHAR(50) DEFAULT 'book'");
    } catch (Exception $e) {
        // Silently continue
    }
}

function getProductTypes() {
    ensureProductSchema();
    try {
        return dbFetchAll("SELECT * FROM product_types ORDER BY is_system DESC, name ASC");
    } catch (Exception $e) {
        return [
            ['id' => 1, 'code' => 'book', 'name' => 'Book / Textbook / Novel', 'is_system' => 1],
            ['id' => 2, 'code' => 'stationery', 'name' => 'Stationery / Office Supply', 'is_system' => 1]
        ];
    }
}

function saveProductType($name, $code = '', $description = '', $id = 0) {
    ensureProductSchema();
    $name = trim($name);
    if (empty($name)) return ['success' => false, 'message' => 'Product type name is required.'];

    $code = trim($code);
    if (empty($code)) {
        // Auto-generate slug from name
        $code = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($name)));
        $code = trim($code, '_');
    } else {
        $code = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '', $code));
    }

    if (empty($code)) $code = 'type_' . time();

    try {
        if ($id > 0) {
            $existing = dbFetchOne("SELECT * FROM product_types WHERE id = ?", [$id]);
            if (!$existing) return ['success' => false, 'message' => 'Product type not found.'];
            
            // Check if code taken by another
            $check = dbFetchOne("SELECT id FROM product_types WHERE code = ? AND id != ?", [$code, $id]);
            if ($check) return ['success' => false, 'message' => "Code '$code' already in use."];

            $oldCode = $existing['code'];
            dbQuery("UPDATE product_types SET name = ?, code = ?, description = ? WHERE id = ?", [$name, $code, $description, $id]);
            if ($oldCode !== $code) {
                dbQuery("UPDATE products SET product_type = ? WHERE product_type = ?", [$code, $oldCode]);
            }
            return ['success' => true, 'id' => $id, 'code' => $code, 'name' => $name, 'message' => "Product type '$name' updated."];
        } else {
            $check = dbFetchOne("SELECT id FROM product_types WHERE code = ?", [$code]);
            if ($check) {
                return ['success' => true, 'id' => $check['id'], 'code' => $code, 'name' => $name, 'message' => "Product type '$name' already exists."];
            }
            dbQuery("INSERT INTO product_types (name, code, description, is_system) VALUES (?, ?, ?, 0)", [$name, $code, $description]);
            $newId = dbInsertId();
            return ['success' => true, 'id' => $newId, 'code' => $code, 'name' => $name, 'message' => "Product type '$name' added successfully."];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function deleteProductType($id) {
    ensureProductSchema();
    $id = (int)$id;
    $type = dbFetchOne("SELECT * FROM product_types WHERE id = ?", [$id]);
    if (!$type) return ['success' => false, 'message' => 'Product type not found.'];
    if (!empty($type['is_system'])) return ['success' => false, 'message' => 'Built-in product types cannot be deleted.'];

    $code = $type['code'];
    $cnt = dbFetchOne("SELECT COUNT(*) as c FROM products WHERE product_type = ?", [$code])['c'] ?? 0;
    if ($cnt > 0) {
        return ['success' => false, 'message' => "Cannot delete type '{$type['name']}': $cnt products are assigned to it. Reassign or delete those products first."];
    }

    dbQuery("DELETE FROM product_types WHERE id = ?", [$id]);
    return ['success' => true, 'message' => "Product type '{$type['name']}' deleted successfully."];
}
