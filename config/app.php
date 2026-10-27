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
        'app_name' => 'Inventory & POS Management System',
        'dashboard' => 'Dashboard',
        'pos' => 'POS Billing',
        'books_catalog' => 'Products',
        'inventory' => 'Inventory & Stock',
        'sales_history' => 'Sales & Invoices',
        'purchases' => 'Purchases & Inward',
        'suppliers' => 'Suppliers',
        'customers' => 'Customers & Khata',
        'schools' => 'Packages & Bundles',
        'accounting' => 'Accounts & Cashbook',
        'reports' => 'Reports & Analytics',
        'users' => 'Users & Roles',
        'settings' => 'Settings',
        'logout' => 'Logout',
        'today_sales' => "Today's Sales",
        'today_profit' => "Today's Profit",
        'customer_receivables' => 'Customer Receivables',
        'supplier_payables' => 'Supplier Payables',
        'warehouses' => 'Warehouses',
        'transfers' => 'Stock Transfers',
    ],
    'ur' => [
        'app_name' => 'انوینٹری اور پی او ایس مینجمنٹ سسٹم',
        'dashboard' => 'ڈیش بورڈ',
        'pos' => 'پی او ایس بلنگ',
        'books_catalog' => 'پروڈکٹس اور اشیاء',
        'inventory' => 'اسٹاک اور انوینٹری',
        'sales_history' => 'فروخت اور رسیدات',
        'purchases' => 'خریداری و سپلائرز',
        'suppliers' => 'سپلائرز (ڈیلرز)',
        'customers' => 'گاہک اور کھاتہ',
        'schools' => 'پیکجز اور بنڈلز',
        'accounting' => 'اکاؤنٹس و کیش بک',
        'reports' => 'رپورٹس اور تجزیات',
        'users' => 'صارفین اور اختیارات',
        'settings' => 'ترتیبات',
        'logout' => 'لاگ آؤٹ',
        'today_sales' => 'آج کی کل فروخت',
        'today_profit' => 'آج کا خالص منافع',
        'customer_receivables' => 'گاہکوں کے واجبات (کھاتہ)',
        'supplier_payables' => 'سپلائرز کی ادائیگیاں',
        'warehouses' => 'گودام و ویئر ہاؤس',
        'transfers' => 'اسٹاک منتقلی (ٹرانسفر)',
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
        $target = getFirstAllowedPage();
        redirect($target);
    }
}

// =========================================================================
// User Permissions & Customizable Access Control Helpers
// =========================================================================

function ensureUserSchema() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = getDB();
        $cols = $db->query("SHOW COLUMNS FROM `users` LIKE 'permissions'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `permissions` TEXT NULL AFTER `role`");
        }
    } catch (Exception $e) {
        // Silently continue
    }
}
ensureUserSchema();

function getAvailableModules() {
    return [
        'dashboard' => [
            'label' => 'Dashboard Overview',
            'desc' => 'Executive analytics, KPI counters, sales metrics and charts',
            'icon' => 'fa-chart-pie',
            'pages' => ['index.php']
        ],
        'pos' => [
            'label' => 'Point of Sale (POS)',
            'desc' => 'Fast barcode billing, receipts, cart discounts, customer khata at counter',
            'icon' => 'fa-cash-register',
            'pages' => ['pos.php', 'pos_ajax.php']
        ],
        'books' => [
            'label' => 'Products & Catalog',
            'desc' => 'Manage inventory items, pricing, categories, product types and barcodes',
            'icon' => 'fa-box-open',
            'pages' => ['books.php', 'product_form.php', 'categories.php', 'product_types.php', 'print_barcode.php']
        ],
        'sales' => [
            'label' => 'Sales Invoices & Returns',
            'desc' => 'View invoice history, reprint thermal/A4 bills, customer returns & exchanges',
            'icon' => 'fa-file-invoice-dollar',
            'pages' => ['sales.php', 'sale_return.php', 'print_invoice.php']
        ],
        'schools' => [
            'label' => 'Schools & Syllabus Bundles',
            'desc' => 'School profiles, grade syllabus lists, course curriculum bundles',
            'icon' => 'fa-boxes-packing',
            'pages' => ['schools.php', 'school_form.php', 'school_class_form.php', 'school_item_form.php']
        ],
        'inventory' => [
            'label' => 'Inventory & Stock Adjustments',
            'desc' => 'Shop stock levels, low-stock warnings, physical stock audits & write-offs',
            'icon' => 'fa-boxes-stacked',
            'pages' => ['inventory.php', 'stock_adjust.php']
        ],
        'warehouses' => [
            'label' => 'Warehouses & Godowns',
            'desc' => 'Multi-warehouse godown tracking, inter-warehouse transfers & delivery notes',
            'icon' => 'fa-warehouse',
            'pages' => ['warehouses.php', 'warehouse_form.php', 'warehouse_view.php', 'warehouse_transfers.php', 'warehouse_transfer_form.php', 'transfer_form.php', 'print_transfer.php']
        ],
        'purchases' => [
            'label' => 'Purchases & Inward Stock',
            'desc' => 'Supplier purchase orders, receiving inward stock, Weighted Average Cost',
            'icon' => 'fa-cart-flatbed',
            'pages' => ['purchases.php', 'purchase_form.php', 'purchase_view.php', 'print_purchase.php']
        ],
        'suppliers' => [
            'label' => 'Suppliers & Publisher Khata',
            'desc' => 'Supplier directory, payable balances, transaction ledger & payments',
            'icon' => 'fa-truck-field',
            'pages' => ['suppliers.php', 'supplier_form.php', 'supplier_payment.php']
        ],
        'customers' => [
            'label' => 'Customers & Receivables Khata',
            'desc' => 'Customer khata accounts, credit limits, recovery receipts & ledger statements',
            'icon' => 'fa-users',
            'pages' => ['customers.php', 'customer_form.php', 'customer_payment.php']
        ],
        'accounting' => [
            'label' => 'Accounting, Incomes & Expenses',
            'desc' => 'Bank/cash accounts, daily expense logs, miscellaneous income, fund transfers',
            'icon' => 'fa-wallet',
            'pages' => ['accounting.php', 'account_form.php', 'expense_form.php', 'income_form.php', 'expense_categories.php']
        ],
        'reports' => [
            'label' => 'Financial Reports & Analytics',
            'desc' => 'Profit & Loss (P&L), Trial Balance, Cash Closing, Stock Valuation',
            'icon' => 'fa-chart-line',
            'pages' => ['reports.php', 'print_report.php']
        ],
        'users' => [
            'label' => 'User Management & Permissions',
            'desc' => 'Create staff logins, set passwords, customize page-by-page access rights',
            'icon' => 'fa-user-shield',
            'pages' => ['users.php', 'user_form.php']
        ],
        'settings' => [
            'label' => 'Store & System Settings',
            'desc' => 'Store business details, receipt footer, tax rates, database backups',
            'icon' => 'fa-sliders',
            'pages' => ['settings.php']
        ]
    ];
}

function getRoleDefaultPermissions($role) {
    $role = strtolower(trim($role));
    $defaults = [
        'admin' => array_keys(getAvailableModules()),
        'manager' => ['dashboard', 'pos', 'books', 'sales', 'schools', 'inventory', 'warehouses', 'purchases', 'suppliers', 'customers'],
        'cashier' => ['pos', 'sales', 'customers'],
        'accountant' => ['dashboard', 'sales', 'purchases', 'suppliers', 'customers', 'accounting', 'reports']
    ];
    return $defaults[$role] ?? ['pos', 'sales'];
}

function userHasPermission($moduleOrPage, $user = null) {
    if (!$user) {
        $user = authUser();
    }
    if (!$user) return false;

    // Superadmin / Admin has unconditional full access to everything
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }

    // Fetch fresh permissions and role from DB for active user in request lifecycle
    $userId = (int)($user['id'] ?? 0);
    $userPermissions = null;
    $userRole = $user['role'] ?? 'cashier';

    if ($userId > 0) {
        static $userCache = [];
        if (!isset($userCache[$userId])) {
            $fresh = dbFetchOne("SELECT role, permissions FROM users WHERE id = ?", [$userId]);
            if ($fresh) {
                $userCache[$userId] = $fresh;
            } else {
                $userCache[$userId] = [
                    'role' => $userRole,
                    'permissions' => $user['permissions'] ?? null
                ];
            }
        }
        $userRole = $userCache[$userId]['role'] ?? $userRole;
        if ($userRole === 'admin') return true;
        $userPermissions = $userCache[$userId]['permissions'] ?? null;
    } else {
        $userPermissions = $user['permissions'] ?? null;
    }

    // Resolve target module if a page filename like 'pos.php' was passed
    $moduleKey = $moduleOrPage;
    $modules = getAvailableModules();

    if (str_ends_with($moduleOrPage, '.php')) {
        $cleanPage = basename($moduleOrPage);
        $found = false;
        foreach ($modules as $k => $info) {
            if (in_array($cleanPage, $info['pages'])) {
                $moduleKey = $k;
                $found = true;
                break;
            }
        }
        if (!$found) {
            return false;
        }
    }

    // Check custom permissions first if configured
    if ($userPermissions !== null && $userPermissions !== '') {
        $permList = is_array($userPermissions) ? $userPermissions : json_decode($userPermissions, true);
        if (is_array($permList)) {
            if (in_array('all', $permList)) return true;
            return in_array($moduleKey, $permList);
        }
    }

    // Otherwise fallback to role-based defaults
    $defaults = getRoleDefaultPermissions($userRole);
    return in_array($moduleKey, $defaults);
}

function getFirstAllowedPage($user = null) {
    if (!$user) $user = authUser();
    if (!$user) return 'login.php';
    if (($user['role'] ?? '') === 'admin') return 'index.php';

    $order = ['dashboard', 'pos', 'books', 'sales', 'inventory', 'warehouses', 'purchases', 'suppliers', 'customers', 'accounting', 'reports', 'users', 'settings'];
    $modules = getAvailableModules();

    foreach ($order as $m) {
        if (userHasPermission($m, $user) && !empty($modules[$m]['pages'][0])) {
            return $modules[$m]['pages'][0];
        }
    }

    return 'login.php';
}

function requirePagePermission($moduleOrPage = null) {
    requireAuth();
    if ($moduleOrPage === null) {
        $moduleOrPage = basename($_SERVER['PHP_SELF']);
    }
    if (!userHasPermission($moduleOrPage)) {
        setFlash('danger', 'Access denied. You do not have permission to access that page.');
        $target = getFirstAllowedPage();
        redirect($target);
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

/**
 * Moving Weighted Average Cost (AVCO) Calculator & Updater
 * Formula: New WAC = ((Existing Total Stock * Current WAC) + (Incoming Qty * Incoming Unit Cost)) / (Existing Total Stock + Incoming Qty)
 * Evaluates current inventory across shop and all warehouses before this incoming movement.
 *
 * @param int $productId
 * @param float $incomingQty
 * @param float $incomingUnitCost
 * @return float Newly computed moving weighted average cost
 */
function updateProductWeightedAverageCost($productId, $incomingQty, $incomingUnitCost) {
    $productId = (int)$productId;
    $incomingQty = (float)$incomingQty;
    $incomingUnitCost = (float)$incomingUnitCost;

    if ($productId <= 0 || $incomingQty <= 0) {
        return 0.0;
    }

    $prod = dbFetchOne("SELECT current_stock, purchase_price FROM products WHERE id = ?", [$productId]);
    if (!$prod) return 0.0;

    $shopStock = (float)$prod['current_stock'];
    $currentCost = (float)$prod['purchase_price'];

    // Total stock in warehouses
    $wh = dbFetchOne("SELECT COALESCE(SUM(quantity), 0) as wh_qty FROM warehouse_stock WHERE product_id = ?", [$productId]);
    $whStock = $wh ? (float)$wh['wh_qty'] : 0.0;

    $existingTotalStock = $shopStock + $whStock;

    if ($existingTotalStock <= 0 || $currentCost <= 0) {
        // If prior stock is 0 or negative, incoming unit cost becomes the base weighted average cost
        $newCost = $incomingUnitCost;
    } else {
        $currentValuation = $existingTotalStock * $currentCost;
        $incomingValuation = $incomingQty * $incomingUnitCost;
        $totalUnits = $existingTotalStock + $incomingQty;

        if ($totalUnits > 0) {
            $newCost = round(($currentValuation + $incomingValuation) / $totalUnits, 2);
        } else {
            $newCost = $incomingUnitCost;
        }
    }

    dbQuery("UPDATE products SET purchase_price = ? WHERE id = ?", [$newCost, $productId]);
    return $newCost;
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

        // Initial one-time seed only if not already seeded
        $isSeeded = getSetting('product_types_seeded', '0');
        if ($isSeeded === '0') {
            $cnt = dbFetchOne("SELECT COUNT(*) as c FROM product_types")['c'] ?? 0;
            if ($cnt == 0) {
                $defaults = [
                    ['code' => 'book', 'name' => 'Standard Products / Items', 'description' => 'Standard inventory items and retail products', 'is_system' => 1],
                    ['code' => 'stationery', 'name' => 'Supplies & Stationery', 'description' => 'Consumables, packaging, stationery, office supplies', 'is_system' => 1],
                    ['code' => 'toys', 'name' => 'Toys & Games', 'description' => 'Educational toys, board games, puzzles and creative play', 'is_system' => 0]
                ];
                foreach ($defaults as $d) {
                    dbQuery("INSERT INTO product_types (code, name, description, is_system) VALUES (?, ?, ?, ?)", [
                        $d['code'], $d['name'], $d['description'], $d['is_system']
                    ]);
                }
            }
            setSetting('product_types_seeded', '1');
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
            ['id' => 1, 'code' => 'book', 'name' => 'Standard Products / Items', 'is_system' => 1],
            ['id' => 2, 'code' => 'stationery', 'name' => 'Supplies & Stationery', 'is_system' => 1]
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


// =========================================================================
// Multi-Warehouse & Multi-Location Stock Management Helpers
// =========================================================================

function ensureWarehouseSchema() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = getDB();

        // 1. Warehouses Master Table
        $db->exec("CREATE TABLE IF NOT EXISTS `warehouses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `warehouse_code` VARCHAR(50) UNIQUE NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `phone` VARCHAR(50) NULL,
            `email` VARCHAR(100) NULL,
            `address` TEXT NULL,
            `city` VARCHAR(100) NULL,
            `manager_name` VARCHAR(100) NULL,
            `is_primary` TINYINT(1) DEFAULT 0,
            `status` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. Warehouse Stock Table
        $db->exec("CREATE TABLE IF NOT EXISTS `warehouse_stock` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `warehouse_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `quantity` DECIMAL(12,2) DEFAULT 0.00,
            `rack_shelf` VARCHAR(100) NULL,
            `min_stock_alert` DECIMAL(12,2) DEFAULT 5.00,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_wh_prod` (`warehouse_id`, `product_id`),
            INDEX `idx_wh` (`warehouse_id`),
            INDEX `idx_prod` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 3. Warehouse Transfers Table
        $db->exec("CREATE TABLE IF NOT EXISTS `warehouse_transfers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `transfer_no` VARCHAR(50) UNIQUE NOT NULL,
            `transfer_date` DATE NOT NULL,
            `from_location_type` ENUM('shop', 'warehouse') NOT NULL,
            `from_warehouse_id` INT NULL,
            `to_location_type` ENUM('shop', 'warehouse') NOT NULL,
            `to_warehouse_id` INT NULL,
            `total_items` INT DEFAULT 0,
            `total_qty` DECIMAL(12,2) DEFAULT 0.00,
            `status` ENUM('completed', 'draft', 'cancelled') DEFAULT 'completed',
            `notes` TEXT NULL,
            `created_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 4. Warehouse Transfer Items
        $db->exec("CREATE TABLE IF NOT EXISTS `warehouse_transfer_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `transfer_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `quantity` DECIMAL(12,2) NOT NULL,
            `unit_cost` DECIMAL(12,2) DEFAULT 0.00,
            `total_cost` DECIMAL(12,2) DEFAULT 0.00,
            INDEX `idx_transfer` (`transfer_id`),
            INDEX `idx_product` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 5. Warehouse Stock Movements Audit Ledger
        $db->exec("CREATE TABLE IF NOT EXISTS `warehouse_stock_movements` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `warehouse_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `movement_date` DATETIME NOT NULL,
            `movement_type` VARCHAR(50) NOT NULL,
            `reference_id` INT NULL,
            `reference_no` VARCHAR(50) NULL,
            `quantity_change` DECIMAL(12,2) NOT NULL,
            `previous_stock` DECIMAL(12,2) NOT NULL,
            `new_stock` DECIMAL(12,2) NOT NULL,
            `unit_cost` DECIMAL(12,2) DEFAULT 0.00,
            `notes` TEXT NULL,
            `created_by` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_wh_mov` (`warehouse_id`, `product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 6. Ensure purchases table has destination columns
        $cols = $db->query("SHOW COLUMNS FROM `purchases` LIKE 'destination_type'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE `purchases` 
                ADD COLUMN `destination_type` ENUM('shop', 'warehouse') DEFAULT 'shop' AFTER `supplier_id`,
                ADD COLUMN `destination_warehouse_id` INT NULL AFTER `destination_type`");
        }

        // 7. Insert default warehouse if empty
        $whCount = dbFetchOne("SELECT COUNT(*) as c FROM warehouses")['c'] ?? 0;
        if ($whCount == 0) {
            $db->exec("INSERT INTO warehouses (warehouse_code, name, phone, email, address, city, manager_name, is_primary, status)
                VALUES ('WH-01', 'Main Central Godown', '', '', 'Main Storage Facility', '', 'Store Incharge', 1, 1)");
        }
    } catch (Exception $e) {
        // Silently continue if already exists
    }
}

// Auto-run schema check
ensureWarehouseSchema();

function getWarehouses($activeOnly = false) {
    ensureWarehouseSchema();
    $sql = "SELECT w.*, 
            COALESCE(SUM(ws.quantity), 0) as total_units,
            COUNT(DISTINCT ws.product_id) as total_skus,
            COALESCE(SUM(ws.quantity * p.purchase_price), 0) as total_cost_value,
            COALESCE(SUM(ws.quantity * p.retail_price), 0) as total_retail_value
            FROM warehouses w
            LEFT JOIN warehouse_stock ws ON ws.warehouse_id = w.id
            LEFT JOIN products p ON p.id = ws.product_id AND p.status = 1";
    if ($activeOnly) {
        $sql .= " WHERE w.status = 1";
    }
    $sql .= " GROUP BY w.id ORDER BY w.is_primary DESC, w.name ASC";
    try {
        return dbFetchAll($sql);
    } catch (Exception $e) {
        return [];
    }
}

function getWarehouseStock($warehouseId, $productId) {
    ensureWarehouseSchema();
    $row = dbFetchOne("SELECT quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ?", [(int)$warehouseId, (int)$productId]);
    return $row ? (float)$row['quantity'] : 0.0;
}

function recordWarehouseStockMovement($warehouseId, $productId, $movementType, $refId, $refNo, $qtyChange, $notes = '', $unitCost = 0) {
    ensureWarehouseSchema();
    $db = getDB();
    $warehouseId = (int)$warehouseId;
    $productId = (int)$productId;
    $qtyChange = (float)$qtyChange;
    $userId = $_SESSION['user']['id'] ?? 1;

    $prod = dbFetchOne("SELECT purchase_price FROM products WHERE id = ?", [$productId]);
    if (!$prod) return false;
    $cost = $unitCost > 0 ? $unitCost : (float)$prod['purchase_price'];

    // Current warehouse stock
    $whStock = dbFetchOne("SELECT quantity FROM warehouse_stock WHERE warehouse_id = ? AND product_id = ?", [$warehouseId, $productId]);
    $prevStock = $whStock ? (float)$whStock['quantity'] : 0.0;
    $newStock = $prevStock + $qtyChange;

    if ($whStock) {
        dbQuery("UPDATE warehouse_stock SET quantity = ? WHERE warehouse_id = ? AND product_id = ?", [$newStock, $warehouseId, $productId]);
    } else {
        dbQuery("INSERT INTO warehouse_stock (warehouse_id, product_id, quantity) VALUES (?, ?, ?)", [$warehouseId, $productId, $newStock]);
    }

    // Insert movement log
    dbQuery("INSERT INTO warehouse_stock_movements (warehouse_id, product_id, movement_date, movement_type, reference_id, reference_no, quantity_change, previous_stock, new_stock, unit_cost, notes, created_by)
             VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)",
             [$warehouseId, $productId, $movementType, $refId, $refNo, $qtyChange, $prevStock, $newStock, $cost, $notes, $userId]);

    return true;
}

// Ensure return_items schema supports cost_price for accurate AVCO restoration & P&L accounting
function ensureReturnSchema() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = getDB();
        $cols = $db->query("SHOW COLUMNS FROM `return_items` LIKE 'cost_price'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE `return_items` ADD COLUMN `cost_price` DECIMAL(12,2) DEFAULT 0.00 AFTER `unit_price`");
        }
    } catch (Exception $e) {
        // Silently continue
    }
}
ensureReturnSchema();
