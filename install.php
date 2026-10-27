<?php
// install.php - Database schema creation and rich initial seed data

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';

$message = '';
$error = '';

function runInstallation() {
    $db = getDB();

    // 1. Settings Table
    $db->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) UNIQUE NOT NULL,
        `setting_value` TEXT,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 2. Users Table
    $db->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) UNIQUE NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `full_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100),
        `phone` VARCHAR(30),
        `role` ENUM('admin', 'manager', 'cashier', 'accountant') NOT NULL DEFAULT 'cashier',
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 3. Categories Table
    $db->exec("CREATE TABLE IF NOT EXISTS `categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `urdu_name` VARCHAR(100),
        `type` VARCHAR(50) DEFAULT 'book',
        `description` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 3b. Product Types Table
    $db->exec("CREATE TABLE IF NOT EXISTS `product_types` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(50) UNIQUE NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `description` VARCHAR(255),
        `is_system` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("INSERT IGNORE INTO `product_types` (`code`, `name`, `is_system`) VALUES 
        ('book', 'Book / Textbook / Novel', 1),
        ('stationery', 'Stationery / Office Supply', 1)");

    // 4. Products Table
    $db->exec("CREATE TABLE IF NOT EXISTS `products` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_code` VARCHAR(50) UNIQUE NOT NULL,
        `barcode` VARCHAR(50) UNIQUE,
        `title` VARCHAR(200) NOT NULL,
        `urdu_title` VARCHAR(200),
        `product_type` VARCHAR(50) DEFAULT 'book',
        `category_id` INT,
        `isbn` VARCHAR(30),
        `author` VARCHAR(150),
        `publisher` VARCHAR(150),
        `edition` VARCHAR(50),
        `publication_year` VARCHAR(10),
        `class_grade` VARCHAR(50),
        `educational_board` VARCHAR(100),
        `subject` VARCHAR(100),
        `language` VARCHAR(50) DEFAULT 'English',
        `unit` VARCHAR(30) DEFAULT 'Piece',
        `purchase_price` DECIMAL(12, 2) DEFAULT 0.00,
        `retail_price` DECIMAL(12, 2) DEFAULT 0.00,
        `wholesale_price` DECIMAL(12, 2) DEFAULT 0.00,
        `discount_percent` DECIMAL(5, 2) DEFAULT 0.00,
        `current_stock` DECIMAL(12, 2) DEFAULT 0.00,
        `min_stock_alert` DECIMAL(12, 2) DEFAULT 5.00,
        `reorder_quantity` DECIMAL(12, 2) DEFAULT 20.00,
        `rack_location` VARCHAR(50),
        `shelf_location` VARCHAR(50),
        `is_used` TINYINT(1) DEFAULT 0,
        `status` TINYINT(1) DEFAULT 1,
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`barcode`),
        INDEX (`isbn`),
        INDEX (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 5. Suppliers Table
    $db->exec("CREATE TABLE IF NOT EXISTS `suppliers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `company_name` VARCHAR(150),
        `phone` VARCHAR(50),
        `email` VARCHAR(100),
        `address` TEXT,
        `opening_balance` DECIMAL(12, 2) DEFAULT 0.00,
        `current_balance` DECIMAL(12, 2) DEFAULT 0.00,
        `notes` TEXT,
        `status` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 6. Customers Table
    $db->exec("CREATE TABLE IF NOT EXISTS `customers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `customer_type` ENUM('walkin', 'regular', 'wholesale', 'school_parent', 'institution') DEFAULT 'walkin',
        `phone` VARCHAR(50),
        `email` VARCHAR(100),
        `address` TEXT,
        `credit_limit` DECIMAL(12, 2) DEFAULT 0.00,
        `opening_balance` DECIMAL(12, 2) DEFAULT 0.00,
        `current_balance` DECIMAL(12, 2) DEFAULT 0.00,
        `notes` TEXT,
        `status` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 7. Accounts Table
    $db->exec("CREATE TABLE IF NOT EXISTS `accounts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `account_name` VARCHAR(100) NOT NULL,
        `account_type` ENUM('cash', 'bank', 'wallet') DEFAULT 'cash',
        `account_number` VARCHAR(100),
        `balance` DECIMAL(14, 2) DEFAULT 0.00,
        `notes` TEXT,
        `status` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 8. Purchases Table
    $db->exec("CREATE TABLE IF NOT EXISTS `purchases` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `purchase_no` VARCHAR(50) UNIQUE NOT NULL,
        `invoice_no` VARCHAR(50),
        `supplier_id` INT NOT NULL,
        `purchase_date` DATE NOT NULL,
        `subtotal` DECIMAL(12, 2) DEFAULT 0.00,
        `tax_amount` DECIMAL(12, 2) DEFAULT 0.00,
        `discount_amount` DECIMAL(12, 2) DEFAULT 0.00,
        `shipping_cost` DECIMAL(12, 2) DEFAULT 0.00,
        `grand_total` DECIMAL(12, 2) DEFAULT 0.00,
        `paid_amount` DECIMAL(12, 2) DEFAULT 0.00,
        `due_amount` DECIMAL(12, 2) DEFAULT 0.00,
        `payment_status` ENUM('paid', 'partial', 'due') DEFAULT 'paid',
        `payment_account_id` INT,
        `notes` TEXT,
        `created_by` INT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 9. Purchase Items Table
    $db->exec("CREATE TABLE IF NOT EXISTS `purchase_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `purchase_id` INT NOT NULL,
        `product_id` INT NOT NULL,
        `quantity` DECIMAL(12, 2) NOT NULL,
        `unit_cost` DECIMAL(12, 2) NOT NULL,
        `discount` DECIMAL(12, 2) DEFAULT 0.00,
        `tax` DECIMAL(12, 2) DEFAULT 0.00,
        `total_cost` DECIMAL(12, 2) NOT NULL,
        INDEX (`purchase_id`),
        INDEX (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 10. Sales Table
    $db->exec("CREATE TABLE IF NOT EXISTS `sales` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `invoice_no` VARCHAR(50) UNIQUE NOT NULL,
        `customer_id` INT DEFAULT NULL,
        `sale_date` DATETIME NOT NULL,
        `subtotal` DECIMAL(12, 2) DEFAULT 0.00,
        `discount_percent` DECIMAL(5, 2) DEFAULT 0.00,
        `discount_amount` DECIMAL(12, 2) DEFAULT 0.00,
        `tax_amount` DECIMAL(12, 2) DEFAULT 0.00,
        `grand_total` DECIMAL(12, 2) DEFAULT 0.00,
        `paid_amount` DECIMAL(12, 2) DEFAULT 0.00,
        `due_amount` DECIMAL(12, 2) DEFAULT 0.00,
        `payment_method` ENUM('cash', 'bank', 'card', 'wallet', 'credit', 'split') DEFAULT 'cash',
        `payment_account_id` INT DEFAULT NULL,
        `payment_status` ENUM('paid', 'partial', 'due') DEFAULT 'paid',
        `sale_type` VARCHAR(50) DEFAULT 'pos',
        `hold_status` TINYINT(1) DEFAULT 0,
        `notes` TEXT,
        `created_by` INT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`invoice_no`),
        INDEX (`customer_id`),
        INDEX (`sale_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 11. Sale Items Table
    $db->exec("CREATE TABLE IF NOT EXISTS `sale_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `sale_id` INT NOT NULL,
        `product_id` INT NOT NULL,
        `quantity` DECIMAL(12, 2) NOT NULL,
        `unit_price` DECIMAL(12, 2) NOT NULL,
        `cost_price` DECIMAL(12, 2) DEFAULT 0.00,
        `discount` DECIMAL(12, 2) DEFAULT 0.00,
        `tax` DECIMAL(12, 2) DEFAULT 0.00,
        `subtotal` DECIMAL(12, 2) NOT NULL,
        INDEX (`sale_id`),
        INDEX (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 12. Sale Payments Table (for split payments)
    $db->exec("CREATE TABLE IF NOT EXISTS `sale_payments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `sale_id` INT NOT NULL,
        `payment_method` VARCHAR(50) NOT NULL,
        `account_id` INT,
        `amount` DECIMAL(12, 2) NOT NULL,
        `reference_no` VARCHAR(100),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`sale_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 13. Returns Table
    $db->exec("CREATE TABLE IF NOT EXISTS `returns` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `return_no` VARCHAR(50) UNIQUE NOT NULL,
        `return_type` ENUM('sale_return', 'purchase_return') NOT NULL,
        `reference_id` INT NOT NULL,
        `entity_id` INT,
        `return_date` DATETIME NOT NULL,
        `total_amount` DECIMAL(12, 2) NOT NULL,
        `refund_method` VARCHAR(50) DEFAULT 'cash',
        `refund_account_id` INT,
        `reason` TEXT,
        `status` VARCHAR(50) DEFAULT 'completed',
        `created_by` INT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 14. Return Items Table
    $db->exec("CREATE TABLE IF NOT EXISTS `return_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `return_id` INT NOT NULL,
        `product_id` INT NOT NULL,
        `quantity` DECIMAL(12, 2) NOT NULL,
        `unit_price` DECIMAL(12, 2) NOT NULL,
        cost_price DECIMAL(12, 2) DEFAULT 0.00,
        `total_amount` DECIMAL(12, 2) NOT NULL,
        `condition_status` ENUM('restockable', 'damaged') DEFAULT 'restockable'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 15. Customer Ledgers Table
    $db->exec("CREATE TABLE IF NOT EXISTS `customer_ledgers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `customer_id` INT NOT NULL,
        `transaction_date` DATETIME NOT NULL,
        `transaction_type` VARCHAR(50) NOT NULL,
        `reference_id` INT DEFAULT NULL,
        `invoice_no` VARCHAR(50) DEFAULT NULL,
        `debit` DECIMAL(12, 2) DEFAULT 0.00,
        `credit` DECIMAL(12, 2) DEFAULT 0.00,
        `balance` DECIMAL(12, 2) NOT NULL,
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`customer_id`),
        INDEX (`transaction_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 16. Supplier Ledgers Table
    $db->exec("CREATE TABLE IF NOT EXISTS `supplier_ledgers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `supplier_id` INT NOT NULL,
        `transaction_date` DATETIME NOT NULL,
        `transaction_type` VARCHAR(50) NOT NULL,
        `reference_id` INT DEFAULT NULL,
        `invoice_no` VARCHAR(50) DEFAULT NULL,
        `debit` DECIMAL(12, 2) DEFAULT 0.00,
        `credit` DECIMAL(12, 2) DEFAULT 0.00,
        `balance` DECIMAL(12, 2) NOT NULL,
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`supplier_id`),
        INDEX (`transaction_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 17. Cash Transactions / Daily Cashbook Table
    $db->exec("CREATE TABLE IF NOT EXISTS `cash_transactions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `transaction_date` DATETIME NOT NULL,
        `transaction_type` ENUM('income', 'expense', 'transfer', 'customer_payment', 'supplier_payment', 'sale', 'purchase') NOT NULL,
        `account_id` INT NOT NULL,
        `to_account_id` INT DEFAULT NULL,
        `category_name` VARCHAR(100),
        `amount` DECIMAL(12, 2) NOT NULL,
        `reference_no` VARCHAR(100),
        `description` TEXT,
        `created_by` INT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`account_id`),
        INDEX (`transaction_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 18. Stock Movements Table
    $db->exec("CREATE TABLE IF NOT EXISTS `stock_movements` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_id` INT NOT NULL,
        `movement_date` DATETIME NOT NULL,
        `movement_type` VARCHAR(50) NOT NULL,
        `reference_id` INT DEFAULT NULL,
        `quantity_change` DECIMAL(12, 2) NOT NULL,
        `previous_stock` DECIMAL(12, 2) NOT NULL,
        `new_stock` DECIMAL(12, 2) NOT NULL,
        `unit_cost` DECIMAL(12, 2) DEFAULT 0.00,
        `notes` TEXT,
        `created_by` INT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`product_id`),
        INDEX (`movement_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 19. Schools Table
    $db->exec("CREATE TABLE IF NOT EXISTS `schools` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `contact_person` VARCHAR(100),
        `phone` VARCHAR(50),
        `email` VARCHAR(100),
        `address` TEXT,
        `notes` TEXT,
        `status` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 20. School Classes Table
    $db->exec("CREATE TABLE IF NOT EXISTS `school_classes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `school_id` INT NOT NULL,
        `class_name` VARCHAR(100) NOT NULL,
        `academic_session` VARCHAR(50) DEFAULT '2026-2027',
        `notes` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`school_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 21. School Class Items (Book packages & stationery lists)
    $db->exec("CREATE TABLE IF NOT EXISTS `school_class_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `school_class_id` INT NOT NULL,
        `product_id` INT NOT NULL,
        `is_mandatory` TINYINT(1) DEFAULT 1,
        `quantity_needed` DECIMAL(10, 2) DEFAULT 1.00,
        `custom_price` DECIMAL(12, 2) DEFAULT NULL,
        `notes` VARCHAR(255),
        INDEX (`school_class_id`),
        INDEX (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 22. Warehouses Master Table
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

    // 23. Warehouse Stock
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

    // 24. Warehouse Transfers
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

    // 25. Warehouse Transfer Items
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

    // 26. Warehouse Stock Movements
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

    // 27. Seed Settings
    $defaultSettings = [
        'shop_name' => 'Kitab Ghar & Stationery',
        'shop_name_urdu' => 'کتاب گھر اینڈ اسٹیشنری مارٹ',
        'shop_tagline' => 'Complete Textbooks, General Books & Quality Stationery',
        'shop_address' => 'Shop # 14-18, Urdu Bazaar, Lahore, Pakistan',
        'shop_phone' => '+92 300 1234567 / 042-37210000',
        'shop_email' => 'sales@kitabghar.pk',
        'currency_symbol' => 'Rs. ',
        'currency_code' => 'PKR',
        'receipt_footer' => 'Thank you for shopping with us! Books once sold cannot be returned without original receipt. Exchange within 7 days.',
        'receipt_footer_urdu' => 'خریداری کا شکریہ! خریدی گئی کتب 7 دن کے اندر اصل رسید کے ہمراہ تبدیل کی جا سکتی ہیں۔',
        'tax_percent' => '0.00',
        'enable_barcode_audio' => '1',
    ];
    foreach ($defaultSettings as $k => $v) {
        dbQuery("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)", [$k, $v]);
    }

    // 23. Seed Users (admin, cashier, accountant)
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $cashierPass = password_hash('cashier123', PASSWORD_DEFAULT);
    $accPass = password_hash('acc123', PASSWORD_DEFAULT);
    
    dbQuery("INSERT IGNORE INTO users (id, username, password_hash, full_name, email, phone, role) VALUES 
        (1, 'admin', ?, 'Super Administrator', 'admin@kitabghar.pk', '03001234567', 'admin'),
        (2, 'cashier', ?, 'Main Counter Cashier', 'cashier@kitabghar.pk', '03129876543', 'cashier'),
        (3, 'accountant', ?, 'Senior Accountant', 'accounts@kitabghar.pk', '03335554433', 'accountant')
    ", [$adminPass, $cashierPass, $accPass]);

    // 24. Seed Accounts
    dbQuery("INSERT IGNORE INTO accounts (id, account_name, account_type, account_number, balance, notes) VALUES
        (1, 'Cash in Hand (Counter Drawer)', 'cash', 'CASH-MAIN', 25000.00, 'Main POS Register Drawer'),
        (2, 'Meezan Bank Limited', 'bank', 'PK90MEZN00012345678901', 185000.00, 'Urdu Bazaar Branch'),
        (3, 'Habib Bank Limited (HBL)', 'bank', 'PK36HABB00098765432101', 94000.00, 'Commercial Current Account'),
        (4, 'JazzCash / EasyPaisa Merchant', 'wallet', '03001234567', 15400.00, 'Mobile QR and digital payments')
    ");

    // 25. Seed Categories
    dbQuery("INSERT IGNORE INTO categories (id, name, urdu_name, type, description) VALUES
        (1, 'School Textbooks', 'اسکول درسی کتب', 'book', 'Federal, Punjab Board, Oxford, Cambridge syllabus books'),
        (2, 'College & University Books', 'کالج و یونیورسٹی کتب', 'book', 'FSc, ICS, BCom, Medical, Engineering and Law'),
        (3, 'Urdu Literature & Novels', 'اردو ادب و ناول', 'book', 'Classical poetry, contemporary fiction and history'),
        (4, 'Islamic & Religious Books', 'اسلامی کتب و تفاسیر', 'book', 'Quran, Hadith, Seerat-un-Nabi, Islamic law'),
        (5, 'Children Story & Activity', 'بچوں کی کہانیاں و سرگرمیاں', 'book', 'Coloring, rhymes, bedtime tales, phonics'),
        (6, 'Stationery - Writing Instruments', 'قلم و پینسلز', 'stationery', 'Ball pens, ink pens, gel pens, markers, pencils'),
        (7, 'Stationery - Notebooks & Registers', 'رجسٹرز و کاپیاں', 'stationery', 'Ruled notebooks, practical journals, binding registers'),
        (8, 'Stationery - Office & Art Supplies', 'دفتری و آرٹ سامان', 'stationery', 'Files, staplers, scissors, colors, geometry boxes')
    ");

    // 26. Seed Suppliers
    dbQuery("INSERT IGNORE INTO suppliers (id, name, company_name, phone, email, address, opening_balance, current_balance) VALUES
        (1, 'Oxford University Press', 'OUP Pakistan', '042-35756611', 'order@oup.com.pk', 'Gulberg III, Lahore', 0.00, 32000.00),
        (2, 'Punjab Curriculum & Textbook Board', 'PCTB Distribution Center', '042-99230671', 'sales@pctb.gov.pk', 'Wahdat Road, Lahore', 0.00, 18500.00),
        (3, 'National Book Foundation', 'NBF Islamabad / Lahore', '042-37324567', 'nbf_lhr@nbf.org.pk', 'Urdu Bazaar, Lahore', 0.00, 0.00),
        (4, 'Dollar Industries Pvt Ltd', 'Dollar Stationery', '021-32567890', 'info@dollar-stationery.com', 'SITE Karachi', 0.00, 14200.00),
        (5, 'Sang-e-Meel Publications', 'Sang-e-Meel Books', '042-37220100', 'contact@sang-e-meel.com', 'Lower Mall, Lahore', 0.00, 8500.00)
    ");

    // 27. Seed Customers
    dbQuery("INSERT IGNORE INTO customers (id, name, customer_type, phone, email, address, credit_limit, opening_balance, current_balance) VALUES
        (1, 'Walk-in Customer (Cash)', 'walkin', '03000000000', '', 'Counter Walk-in', 0.00, 0.00, 0.00),
        (2, 'Beaconhouse School System (Main Branch)', 'institution', '042-35889900', 'admin@bss.edu.pk', 'Canal Road Campus, Lahore', 150000.00, 0.00, 42500.00),
        (3, 'The City School (Gulberg)', 'institution', '042-35711234', 'accounts@tcs.edu.pk', 'Gulberg Campus, Lahore', 100000.00, 0.00, 19800.00),
        (4, 'Tariq Mehmood (Wholesale Buyer)', 'wholesale', '03214567891', 'tariq.traders@gmail.com', 'Railway Road, Gujranwala', 50000.00, 0.00, 12000.00),
        (5, 'Muhammad Usman (Parent Khata)', 'school_parent', '03334455667', 'usman.parent@gmail.com', 'Johar Town, Lahore', 15000.00, 0.00, 3200.00)
    ");

    // 28. Seed Products (Books & Stationery)
    $products = [
        // Books
        [
            'code' => 'BK-1001', 'barcode' => '9780199408221', 'title' => 'Oxford Progressive English Book 5',
            'urdu_title' => 'آکسفورڈ پروگریسو انگلش کتاب 5', 'type' => 'book', 'cat' => 1,
            'isbn' => '978-0199408221', 'author' => 'Rachel Redford', 'publisher' => 'Oxford University Press',
            'edition' => '3rd Edition', 'year' => '2025', 'class' => 'Class 5', 'board' => 'Cambridge / Private',
            'subject' => 'English', 'lang' => 'English', 'unit' => 'Piece',
            'cost' => 650.00, 'retail' => 895.00, 'wholesale' => 740.00, 'stock' => 45.00, 'min_stock' => 10.00,
            'rack' => 'Rack A', 'shelf' => 'Shelf 2', 'desc' => 'Standard textbook for Class 5 Oxford syllabus.'
        ],
        [
            'code' => 'BK-1002', 'barcode' => '9780199408238', 'title' => 'Oxford Progressive English Book 4',
            'urdu_title' => 'آکسفورڈ پروگریسو انگلش کتاب 4', 'type' => 'book', 'cat' => 1,
            'isbn' => '978-0199408238', 'author' => 'Rachel Redford', 'publisher' => 'Oxford University Press',
            'edition' => '3rd Edition', 'year' => '2025', 'class' => 'Class 4', 'board' => 'Cambridge / Private',
            'subject' => 'English', 'lang' => 'English', 'unit' => 'Piece',
            'cost' => 610.00, 'retail' => 850.00, 'wholesale' => 700.00, 'stock' => 38.00, 'min_stock' => 10.00,
            'rack' => 'Rack A', 'shelf' => 'Shelf 2', 'desc' => 'Standard textbook for Class 4 Oxford syllabus.'
        ],
        [
            'code' => 'BK-1003', 'barcode' => '9789694480911', 'title' => 'Punjab Board Mathematics Class 9 (Science)',
            'urdu_title' => 'ریاضی نہم سائنس گروپ (پنجاب ٹیکسٹ بک بورڈ)', 'type' => 'book', 'cat' => 1,
            'isbn' => '978-9694480911', 'author' => 'Dr. Karamat H. Dar', 'publisher' => 'Punjab Curriculum & Textbook Board',
            'edition' => 'New SNC Edition', 'year' => '2026', 'class' => 'Class 9', 'board' => 'Punjab Board (BISE)',
            'subject' => 'Mathematics', 'lang' => 'English', 'unit' => 'Piece',
            'cost' => 180.00, 'retail' => 240.00, 'wholesale' => 200.00, 'stock' => 85.00, 'min_stock' => 15.00,
            'rack' => 'Rack B', 'shelf' => 'Shelf 1', 'desc' => 'Official PTB Math text for Class 9 SSC Part 1.'
        ],
        [
            'code' => 'BK-1004', 'barcode' => '9789694480928', 'title' => 'Punjab Board Physics Class 9',
            'urdu_title' => 'طبیعیات فزکس نہم (پنجاب ٹیکسٹ بک بورڈ)', 'type' => 'book', 'cat' => 1,
            'isbn' => '978-9694480928', 'author' => 'Prof. Muhammad Ali', 'publisher' => 'Punjab Curriculum & Textbook Board',
            'edition' => 'New SNC Edition', 'year' => '2026', 'class' => 'Class 9', 'board' => 'Punjab Board (BISE)',
            'subject' => 'Physics', 'lang' => 'English', 'unit' => 'Piece',
            'cost' => 165.00, 'retail' => 220.00, 'wholesale' => 185.00, 'stock' => 70.00, 'min_stock' => 15.00,
            'rack' => 'Rack B', 'shelf' => 'Shelf 1', 'desc' => 'Official PTB Physics text for Class 9.'
        ],
        [
            'code' => 'BK-1005', 'barcode' => '9789694480935', 'title' => 'Punjab Board Chemistry Class 9',
            'urdu_title' => 'کیمسٹری نہم (پنجاب ٹیکسٹ بک بورڈ)', 'type' => 'book', 'cat' => 1,
            'isbn' => '978-9694480935', 'author' => 'Dr. Jalil Ahmad', 'publisher' => 'Punjab Curriculum & Textbook Board',
            'edition' => 'New SNC Edition', 'year' => '2026', 'class' => 'Class 9', 'board' => 'Punjab Board (BISE)',
            'subject' => 'Chemistry', 'lang' => 'English', 'unit' => 'Piece',
            'cost' => 170.00, 'retail' => 230.00, 'wholesale' => 190.00, 'stock' => 60.00, 'min_stock' => 15.00,
            'rack' => 'Rack B', 'shelf' => 'Shelf 1', 'desc' => 'Official PTB Chemistry text for Class 9.'
        ],
        [
            'code' => 'BK-1006', 'barcode' => '9789694480942', 'title' => 'Punjab Board Biology Class 9',
            'urdu_title' => 'حیاتیات بیالوجی نہم (پنجاب ٹیکسٹ بک بورڈ)', 'type' => 'book', 'cat' => 1,
            'isbn' => '978-9694480942', 'author' => 'Dr. Zahid Baig', 'publisher' => 'Punjab Curriculum & Textbook Board',
            'edition' => 'New SNC Edition', 'year' => '2026', 'class' => 'Class 9', 'board' => 'Punjab Board (BISE)',
            'subject' => 'Biology', 'lang' => 'English', 'unit' => 'Piece',
            'cost' => 175.00, 'retail' => 235.00, 'wholesale' => 195.00, 'stock' => 55.00, 'min_stock' => 15.00,
            'rack' => 'Rack B', 'shelf' => 'Shelf 1', 'desc' => 'Official PTB Biology text for Class 9.'
        ],
        [
            'code' => 'BK-1007', 'barcode' => '9789693520123', 'title' => 'Kulliyat-e-Iqbal (Urdu) - Allama Iqbal',
            'urdu_title' => 'کلیات اقبال (مکمل اردو کلام)', 'type' => 'book', 'cat' => 3,
            'isbn' => '978-9693520123', 'author' => 'Allama Muhammad Iqbal', 'publisher' => 'Sang-e-Meel Publications',
            'edition' => 'Deluxe Hardcover', 'year' => '2024', 'class' => 'General / Literature', 'board' => 'N/A',
            'subject' => 'Urdu Poetry', 'lang' => 'Urdu', 'unit' => 'Piece',
            'cost' => 1200.00, 'retail' => 1800.00, 'wholesale' => 1450.00, 'stock' => 18.00, 'min_stock' => 3.00,
            'rack' => 'Rack C', 'shelf' => 'Shelf 3', 'desc' => 'Complete poetry collection: Bang-e-Dra, Bal-e-Jibreel, Zarb-e-Kaleem, Armaghan-e-Hijaz.'
        ],
        [
            'code' => 'BK-1008', 'barcode' => '9789694165504', 'title' => 'Seerat-un-Nabi (PBUH) Vol 1-2 Complete',
            'urdu_title' => 'سیرت النبی ﷺ (مکمل دو جلدیں)', 'type' => 'book', 'cat' => 4,
            'isbn' => '978-9694165504', 'author' => 'Allama Shibli Nomani & Syed Sulaiman Nadvi', 'publisher' => 'Darussalam / NBF',
            'edition' => 'Revised', 'year' => '2023', 'class' => 'Islamic Studies', 'board' => 'N/A',
            'subject' => 'Seerat', 'lang' => 'Urdu', 'unit' => 'Set',
            'cost' => 1600.00, 'retail' => 2400.00, 'wholesale' => 1950.00, 'stock' => 12.00, 'min_stock' => 2.00,
            'rack' => 'Rack D', 'shelf' => 'Shelf 1', 'desc' => 'Masterpiece on the life and biography of Prophet Muhammad (PBUH).'
        ],
        [
            'code' => 'BK-1009', 'barcode' => '9789693500219', 'title' => 'Shahab Nama - Qudrat Ullah Shahab',
            'urdu_title' => 'شہاب نامہ - قدرت اللہ شہاب', 'type' => 'book', 'cat' => 3,
            'isbn' => '978-9693500219', 'author' => 'Qudrat Ullah Shahab', 'publisher' => 'Sang-e-Meel Publications',
            'edition' => 'Hardcover 52nd Edition', 'year' => '2025', 'class' => 'Autobiography', 'board' => 'N/A',
            'subject' => 'Biography / History', 'lang' => 'Urdu', 'unit' => 'Piece',
            'cost' => 1100.00, 'retail' => 1650.00, 'wholesale' => 1350.00, 'stock' => 22.00, 'min_stock' => 5.00,
            'rack' => 'Rack C', 'shelf' => 'Shelf 2', 'desc' => 'Legendary Urdu autobiography covering Pakistan civil service history.'
        ],
        [
            'code' => 'BK-1010', 'barcode' => '9780199409901', 'title' => 'Oxford Secondary Social Studies Book 2',
            'urdu_title' => 'آکسفورڈ سیکنڈری سوشل اسٹڈیز کتاب 2', 'type' => 'book', 'cat' => 1,
            'isbn' => '978-0199409901', 'author' => 'Peter Moss', 'publisher' => 'Oxford University Press',
            'edition' => 'Revised Edition', 'year' => '2024', 'class' => 'Class 7', 'board' => 'Cambridge / OUP',
            'subject' => 'Social Studies', 'lang' => 'English', 'unit' => 'Piece',
            'cost' => 720.00, 'retail' => 995.00, 'wholesale' => 820.00, 'stock' => 4.00, 'min_stock' => 8.00,
            'rack' => 'Rack A', 'shelf' => 'Shelf 3', 'desc' => 'Comprehensive social studies for Grade 7 (Low Stock Sample!).'
        ],

        // Stationery Items
        [
            'code' => 'ST-2001', 'barcode' => '8964000100124', 'title' => 'Dollar Clipper Ball Pen Blue (Pack of 10)',
            'urdu_title' => 'ڈالر کلپر بال پوائنٹ نیلا (10 عدد پیکٹ)', 'type' => 'stationery', 'cat' => 6,
            'isbn' => '', 'author' => '', 'publisher' => 'Dollar Stationery',
            'edition' => '', 'year' => '', 'class' => '', 'board' => '',
            'subject' => 'Stationery', 'lang' => 'N/A', 'unit' => 'Pack',
            'cost' => 180.00, 'retail' => 250.00, 'wholesale' => 205.00, 'stock' => 95.00, 'min_stock' => 20.00,
            'rack' => 'Stationery Rack', 'shelf' => 'Bin 1', 'desc' => 'Smooth writing ball pens pack of 10.'
        ],
        [
            'code' => 'ST-2002', 'barcode' => '8964000100254', 'title' => 'Dollar SP-10 Fountain Ink Pen Blue',
            'urdu_title' => 'ڈالر ایس پی 10 سیاہی والا فاؤنٹین پین', 'type' => 'stationery', 'cat' => 6,
            'isbn' => '', 'author' => '', 'publisher' => 'Dollar Stationery',
            'edition' => '', 'year' => '', 'class' => '', 'board' => '',
            'subject' => 'Stationery', 'lang' => 'N/A', 'unit' => 'Piece',
            'cost' => 65.00, 'retail' => 100.00, 'wholesale' => 80.00, 'stock' => 140.00, 'min_stock' => 30.00,
            'rack' => 'Stationery Rack', 'shelf' => 'Bin 2', 'desc' => 'Classic student fountain pen with fine nib.'
        ],
        [
            'code' => 'ST-2003', 'barcode' => '8964000100384', 'title' => 'Standard Four-Line English Notebook (120 Pgs)',
            'urdu_title' => 'چار لائن انگلش کاپی (120 صفحات)', 'type' => 'stationery', 'cat' => 7,
            'isbn' => '', 'author' => '', 'publisher' => 'Premium Paper Mills',
            'edition' => '', 'year' => '', 'class' => 'Junior Classes', 'board' => '',
            'subject' => 'Notebook', 'lang' => 'N/A', 'unit' => 'Piece',
            'cost' => 75.00, 'retail' => 120.00, 'wholesale' => 90.00, 'stock' => 210.00, 'min_stock' => 50.00,
            'rack' => 'Notebook Shelf', 'shelf' => 'Row 1', 'desc' => 'High quality 70gsm white paper English 4-line notebook.'
        ],
        [
            'code' => 'ST-2004', 'barcode' => '8964000100490', 'title' => 'Standard Single-Line Urdu Notebook (120 Pgs)',
            'urdu_title' => 'سنگل لائن اردو کاپی (120 صفحات)', 'type' => 'stationery', 'cat' => 7,
            'isbn' => '', 'author' => '', 'publisher' => 'Premium Paper Mills',
            'edition' => '', 'year' => '', 'class' => 'All Classes', 'board' => '',
            'subject' => 'Notebook', 'lang' => 'N/A', 'unit' => 'Piece',
            'cost' => 75.00, 'retail' => 120.00, 'wholesale' => 90.00, 'stock' => 230.00, 'min_stock' => 50.00,
            'rack' => 'Notebook Shelf', 'shelf' => 'Row 1', 'desc' => 'Fine ruling Urdu single-line exercise book.'
        ],
        [
            'code' => 'ST-2005', 'barcode' => '8964000100520', 'title' => 'Standard Small-Square Math Notebook (120 Pgs)',
            'urdu_title' => 'ریاضی خانے والی کاپی (120 صفحات)', 'type' => 'stationery', 'cat' => 7,
            'isbn' => '', 'author' => '', 'publisher' => 'Premium Paper Mills',
            'edition' => '', 'year' => '', 'class' => 'All Classes', 'board' => '',
            'subject' => 'Notebook', 'lang' => 'N/A', 'unit' => 'Piece',
            'cost' => 75.00, 'retail' => 120.00, 'wholesale' => 90.00, 'stock' => 180.00, 'min_stock' => 40.00,
            'rack' => 'Notebook Shelf', 'shelf' => 'Row 2', 'desc' => 'Maths check square notebook.'
        ],
        [
            'code' => 'ST-2006', 'barcode' => '4005401124128', 'title' => 'Faber-Castell Classic Color Pencils (24 Shades)',
            'urdu_title' => 'فیبر کاسٹل رنگین پنسلیں (24 رنگ باکس)', 'type' => 'stationery', 'cat' => 8,
            'isbn' => '', 'author' => '', 'publisher' => 'Faber-Castell',
            'edition' => '', 'year' => '', 'class' => 'Art', 'board' => '',
            'subject' => 'Art Supplies', 'lang' => 'N/A', 'unit' => 'Box',
            'cost' => 550.00, 'retail' => 780.00, 'wholesale' => 640.00, 'stock' => 32.00, 'min_stock' => 10.00,
            'rack' => 'Art Rack', 'shelf' => 'Shelf 1', 'desc' => 'High quality hexagonal color pencils with break-resistant leads.'
        ],
        [
            'code' => 'ST-2007', 'barcode' => '8964000100674', 'title' => 'Student Mathematical Geometry Box Complete',
            'urdu_title' => 'طلباء جیومیٹری بکس مکمل', 'type' => 'stationery', 'cat' => 8,
            'isbn' => '', 'author' => '', 'publisher' => 'Deer Stationery',
            'edition' => '', 'year' => '', 'class' => 'Middle / High School', 'board' => '',
            'subject' => 'Geometry', 'lang' => 'N/A', 'unit' => 'Box',
            'cost' => 220.00, 'retail' => 350.00, 'wholesale' => 270.00, 'stock' => 45.00, 'min_stock' => 15.00,
            'rack' => 'Art Rack', 'shelf' => 'Shelf 2', 'desc' => 'Metal compass, divider, protractor, set squares and ruler set.'
        ],
        [
            'code' => 'ST-2008', 'barcode' => '8964000100780', 'title' => 'Pelikan / Dollar 60ml Fountain Pen Ink Royal Blue',
            'urdu_title' => 'ڈالر فاؤنٹین پین سیاہی رائل بلیو (60 ملی لیٹر)', 'type' => 'stationery', 'cat' => 6,
            'isbn' => '', 'author' => '', 'publisher' => 'Dollar Stationery',
            'edition' => '', 'year' => '', 'class' => '', 'board' => '',
            'subject' => 'Ink', 'lang' => 'N/A', 'unit' => 'Piece',
            'cost' => 70.00, 'retail' => 110.00, 'wholesale' => 85.00, 'stock' => 3.00, 'min_stock' => 15.00,
            'rack' => 'Stationery Rack', 'shelf' => 'Bin 3', 'desc' => 'Brilliant royal blue fountain pen ink (Low Stock Alert!).'
        ]
    ];

    foreach ($products as $p) {
        $stmt = $db->prepare("INSERT INTO products 
            (product_code, barcode, title, urdu_title, product_type, category_id, isbn, author, publisher, 
             edition, publication_year, class_grade, educational_board, subject, language, unit, 
             purchase_price, retail_price, wholesale_price, current_stock, min_stock_alert, rack_location, shelf_location, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE title = VALUES(title), retail_price = VALUES(retail_price), current_stock = VALUES(current_stock)");
        
        $stmt->execute([
            $p['code'], $p['barcode'], $p['title'], $p['urdu_title'], $p['type'], $p['cat'],
            $p['isbn'], $p['author'], $p['publisher'], $p['edition'], $p['year'], $p['class'],
            $p['board'], $p['subject'], $p['lang'], $p['unit'], $p['cost'], $p['retail'],
            $p['wholesale'], $p['stock'], $p['min_stock'], $p['rack'], $p['shelf'], $p['desc']
        ]);
    }

    // 29. Seed School Syllabus Packages
    dbQuery("INSERT IGNORE INTO schools (id, name, contact_person, phone, email, address, notes) VALUES
        (1, 'Beaconhouse School System', 'Mr. Asad Khan (Vice Principal)', '042-35889900', 'admin@bss.edu.pk', 'Canal Road Campus, Lahore', 'Official authorized booklist vendor partner'),
        (2, 'The City School', 'Ms. Farzana Tariq (Academic Head)', '042-35711234', 'info@tcs.edu.pk', 'Gulberg III, Lahore', 'Annual curriculum syllabus partner'),
        (3, 'Govt. Central Model High School', 'Sir Muhammad Rafiq (Headmaster)', '042-37320099', 'model_school@schools.punjab.gov.pk', 'Lower Mall, Lahore', 'Punjab Textbook Board curriculum')
    ");

    dbQuery("INSERT IGNORE INTO school_classes (id, school_id, class_name, academic_session, notes) VALUES
        (1, 1, 'Class 5 (Syllabus Package)', '2026-2027', 'Beaconhouse Grade 5 complete course syllabus'),
        (2, 2, 'Class 4 (Syllabus Package)', '2026-2027', 'The City School Grade 4 complete book set'),
        (3, 3, 'Matric Class 9 (Science Group)', '2026-2027', 'Punjab Board complete science book set')
    ");

    // Package Items for Class 5 Beaconhouse
    dbQuery("INSERT IGNORE INTO school_class_items (school_class_id, product_id, is_mandatory, quantity_needed, custom_price) VALUES
        (1, 1, 1, 1.00, 895.00), -- Oxford Progressive English 5
        (1, 11, 1, 1.00, 250.00), -- Dollar Ball Pen Pack
        (1, 13, 1, 4.00, 120.00), -- 4 English Notebooks
        (1, 14, 1, 3.00, 120.00), -- 3 Urdu Notebooks
        (1, 15, 1, 3.00, 120.00), -- 3 Math Notebooks
        (1, 16, 1, 1.00, 780.00), -- Faber-Castell 24 Colors
        (1, 17, 1, 1.00, 350.00)  -- Geometry Box
    ");

    // Package Items for Matric Class 9 Punjab Board
    dbQuery("INSERT IGNORE INTO school_class_items (school_class_id, product_id, is_mandatory, quantity_needed, custom_price) VALUES
        (3, 3, 1, 1.00, 240.00), -- PTB Math 9
        (3, 4, 1, 1.00, 220.00), -- PTB Physics 9
        (3, 5, 1, 1.00, 230.00), -- PTB Chemistry 9
        (3, 6, 1, 1.00, 235.00), -- PTB Biology 9
        (3, 12, 1, 2.00, 100.00), -- 2 Fountain Pens
        (3, 18, 1, 1.00, 110.00), -- Royal Blue Ink
        (3, 14, 1, 4.00, 120.00), -- 4 Urdu/Single Line Registers
        (3, 17, 1, 1.00, 350.00)  -- Geometry Box
    ");

    // 30. Seed a couple of recent sales for immediate dashboard analytics!
    $checkSales = dbFetchOne("SELECT COUNT(*) as cnt FROM sales");
    if ($checkSales && $checkSales['cnt'] == 0) {
        $today = date('Y-m-d H:i:s');
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));

        // Sale 1: Completed cash sale
        dbQuery("INSERT INTO sales (id, invoice_no, customer_id, sale_date, subtotal, discount_amount, tax_amount, grand_total, paid_amount, due_amount, payment_method, payment_account_id, payment_status, sale_type, created_by)
                 VALUES (1, 'INV-2026-0001', 1, ?, 1890.00, 90.00, 0.00, 1800.00, 1800.00, 0.00, 'cash', 1, 'paid', 'pos', 1)", [$today]);
        dbQuery("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, cost_price, subtotal) VALUES
                 (1, 1, 1, 895.00, 650.00, 895.00),
                 (1, 11, 1, 250.00, 180.00, 250.00),
                 (1, 16, 1, 780.00, 550.00, 780.00)");
        
        // Sale 2: Credit sale to school with balance
        dbQuery("INSERT INTO sales (id, invoice_no, customer_id, sale_date, subtotal, discount_amount, tax_amount, grand_total, paid_amount, due_amount, payment_method, payment_account_id, payment_status, sale_type, created_by)
                 VALUES (2, 'INV-2026-0002', 2, ?, 42500.00, 0.00, 0.00, 42500.00, 0.00, 42500.00, 'credit', NULL, 'due', 'school_package', 1)", [$yesterday]);
        dbQuery("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, cost_price, subtotal) VALUES
                 (2, 1, 30, 895.00, 650.00, 26850.00),
                 (2, 16, 20, 780.00, 550.00, 15650.00)");
        
        // Customer ledger entry for Sale 2
        recordCustomerLedger(2, 'sale', 2, 'INV-2026-0002', 42500.00, 0.00, 'Beaconhouse Grade 5 Advance Syllabus Sets');

        // Sample Expense in Cash Transactions
        dbQuery("INSERT INTO cash_transactions (transaction_date, transaction_type, account_id, category_name, amount, reference_no, description, created_by)
                 VALUES (?, 'expense', 1, 'Electricity & Utilities', 4500.00, 'LESCO-OCT26', 'October Electricity Bill Paid', 1)", [$today]);
        dbQuery("INSERT INTO cash_transactions (transaction_date, transaction_type, account_id, category_name, amount, reference_no, description, created_by)
                 VALUES (?, 'expense', 1, 'Tea & Refreshment', 650.00, 'TEA-01', 'Staff tea and refreshments', 1)", [$today]);
    }

    return true;
}

if (isset($_POST['install']) || php_sapi_name() === 'cli') {
    try {
        runInstallation();
        $message = "Database & schema initialized successfully with complete seed data! You can log in with: <strong>admin</strong> / <strong>admin123</strong> or <strong>cashier</strong> / <strong>cashier123</strong>.";
    } catch (Exception $e) {
        $error = "Installation Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Shop Management System - Setup & Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .setup-card { background: #fff; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.25); max-width: 580px; width: 100%; overflow: hidden; }
        .setup-header { background: #0f2027; background: linear-gradient(to right, #2c3e50, #3498db); color: white; padding: 30px; text-align: center; }
        .btn-install { background: #27ae60; border: none; padding: 12px 24px; font-weight: 600; font-size: 1.1rem; border-radius: 8px; }
        .btn-install:hover { background: #219150; }
    </style>
</head>
<body>
<div class="container p-3">
    <div class="setup-card mx-auto">
        <div class="setup-header">
            <i class="fa-solid fa-book-open-reader fa-3x mb-2 text-warning"></i>
            <h3 class="mb-1">Book Shop Management System</h3>
            <p class="mb-0 text-white-50">One-Click Database Setup & Sample Data Generator</p>
        </div>
        <div class="p-4">
            <?php if ($message): ?>
                <div class="alert alert-success d-flex align-items-center">
                    <i class="fa-solid fa-circle-check fa-2x me-3"></i>
                    <div>
                        <?= $message ?><br>
                        <a href="login.php" class="btn btn-primary mt-3"><i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Proceed to Login</a>
                    </div>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <h5 class="fw-bold text-dark"><i class="fa-solid fa-cubes text-primary me-2"></i>What will be installed:</h5>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item"><i class="fa-solid fa-check text-success me-2"></i> Complete 21 MySQL relational tables with foreign indexes</li>
                    <li class="list-group-item"><i class="fa-solid fa-check text-success me-2"></i> Books (Oxford, Punjab Board, Urdu Literature, Islamic) & Stationery items</li>
                    <li class="list-group-item"><i class="fa-solid fa-check text-success me-2"></i> Barcodes (EAN-13, Code128 ready) and Rack/Shelf tracking</li>
                    <li class="list-group-item"><i class="fa-solid fa-check text-success me-2"></i> School book lists, class syllabus bundles & 1-click POS packages</li>
                    <li class="list-group-item"><i class="fa-solid fa-check text-success me-2"></i> Customer Khata ledger & Supplier payable accounts</li>
                    <li class="list-group-item"><i class="fa-solid fa-check text-success me-2"></i> Cash & Bank Accounts (Meezan, HBL, Cash in Hand, JazzCash)</li>
                    <li class="list-group-item"><i class="fa-solid fa-check text-success me-2"></i> Dual-Language (English & اردو RTL) support</li>
                </ul>
            </div>

            <div class="bg-light p-3 rounded mb-4 border">
                <div class="fw-bold mb-1"><i class="fa-solid fa-key me-2 text-warning"></i>Default System Logins:</div>
                <div class="small">
                    <strong>Admin:</strong> <code>admin</code> / <code>admin123</code><br>
                    <strong>Cashier:</strong> <code>cashier</code> / <code>cashier123</code><br>
                    <strong>Accountant:</strong> <code>accountant</code> / <code>acc123</code>
                </div>
            </div>

            <form method="POST">
                <div class="d-grid gap-2">
                    <button type="submit" name="install" value="1" class="btn btn-success btn-install shadow-sm text-white">
                        <i class="fa-solid fa-bolt me-2"></i> Initialize Database Now
                    </button>
                    <a href="login.php" class="btn btn-outline-secondary">Already Installed? Go to Login</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
