<?php
// pos_ajax.php - High-speed AJAX endpoints for POS Terminal
require_once __DIR__ . '/config/app.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if (!userHasPermission('pos')) {
    echo json_encode(['success' => false, 'message' => 'Access denied. You do not have permission for POS.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// 1. Search Products
if ($action === 'search_products') {
    $q = trim($_REQUEST['q'] ?? '');
    $category = (int)($_REQUEST['category'] ?? 0);
    $type = trim($_REQUEST['type'] ?? '');

    $where = ["status = 1"];
    $params = [];

    if ($category > 0) {
        $where[] = "category_id = ?";
        $params[] = $category;
    }
    if ($type) {
        $where[] = "product_type = ?";
        $params[] = $type;
    }
    if ($q !== '') {
        $where[] = "(title LIKE ? OR urdu_title LIKE ? OR barcode LIKE ? OR product_code LIKE ? OR isbn LIKE ? OR author LIKE ? OR publisher LIKE ?)";
        $wild = "%$q%";
        $params = array_merge($params, [$wild, $wild, $wild, $wild, $wild, $wild, $wild]);
    }

    $whereSql = implode(" AND ", $where);
    $products = dbFetchAll("SELECT id, product_code, barcode, title, urdu_title, product_type, unit, 
                                   purchase_price, retail_price, wholesale_price, discount_percent, 
                                   current_stock, min_stock_alert, rack_location, class_grade, author, publisher,
                                   COALESCE((SELECT SUM(quantity) FROM warehouse_stock WHERE product_id = products.id), 0) as wh_stock
                            FROM products 
                            WHERE $whereSql 
                            ORDER BY (barcode = '$q' OR product_code = '$q') DESC, title ASC LIMIT 30", $params);

    echo json_encode(['success' => true, 'products' => $products]);
    exit;
}

// 2. Barcode lookup (Exact match first, then prefix/like match)
if ($action === 'barcode_lookup') {
    $barcode = trim($_REQUEST['barcode'] ?? $_POST['barcode'] ?? $_GET['barcode'] ?? '');
    if (!$barcode) {
        echo json_encode(['success' => false, 'message' => 'Barcode required']);
        exit;
    }
    
    // 1. Try exact match
    $product = dbFetchOne("SELECT * FROM products WHERE (barcode = ? OR product_code = ? OR isbn = ?) AND status = 1 LIMIT 1", [$barcode, $barcode, $barcode]);
    
    // 2. Fallback to prefix / contains match
    if (!$product) {
        $wild = "%$barcode%";
        $product = dbFetchOne("SELECT * FROM products WHERE (barcode LIKE ? OR product_code LIKE ? OR isbn LIKE ? OR title LIKE ?) AND status = 1 LIMIT 1", [$wild, $wild, $wild, $wild]);
    }

    if ($product) {
        echo json_encode(['success' => true, 'product' => $product]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found with barcode/code: ' . $barcode]);
    }
    exit;
}

// 3. Load School Package Items into POS Cart
if ($action === 'load_school_package') {
    $classId = (int)($_GET['class_id'] ?? 0);
    if (!$classId) {
        echo json_encode(['success' => false, 'message' => 'Class ID required']);
        exit;
    }

    $classInfo = dbFetchOne("SELECT sc.*, s.name as school_name 
                             FROM school_classes sc 
                             JOIN schools s ON s.id = sc.school_id 
                             WHERE sc.id = ?", [$classId]);

    $items = dbFetchAll("SELECT sci.*, p.product_code, p.barcode, p.title, p.retail_price, p.purchase_price, p.current_stock, p.unit
                         FROM school_class_items sci
                         JOIN products p ON p.id = sci.product_id
                         WHERE sci.school_class_id = ?", [$classId]);

    echo json_encode([
        'success' => true,
        'class_info' => $classInfo,
        'items' => $items
    ]);
    exit;
}

// 4. Search Customers
if ($action === 'search_customers') {
    $q = trim($_GET['q'] ?? '');
    $where = ["status = 1"];
    $params = [];
    if ($q) {
        $where[] = "(name LIKE ? OR phone LIKE ?)";
        $wild = "%$q%";
        $params = [$wild, $wild];
    }
    $customers = dbFetchAll("SELECT id, name, customer_type, phone, current_balance, credit_limit 
                             FROM customers 
                             WHERE " . implode(" AND ", $where) . " 
                             ORDER BY id ASC LIMIT 20", $params);
    echo json_encode(['success' => true, 'customers' => $customers]);
    exit;
}

// 5. Checkout / Process Sale
if ($action === 'checkout') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['items'])) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
        exit;
    }

    $customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : 1; // 1 = Walk-in
    $items = $data['items'];
    $subtotal = (float)($data['subtotal'] ?? 0);
    $discountAmount = (float)($data['discount_amount'] ?? 0);
    $taxAmount = (float)($data['tax_amount'] ?? 0);
    $grandTotal = (float)($data['grand_total'] ?? 0);
    $paidAmount = (float)($data['paid_amount'] ?? 0);
    $dueAmount = max(0, $grandTotal - $paidAmount);
    $paymentMethod = $data['payment_method'] ?? 'cash';
    $paymentAccountId = !empty($data['account_id']) ? (int)$data['account_id'] : 1; // Default Cash Account
    $saleType = $data['sale_type'] ?? 'pos';
    $notes = trim($data['notes'] ?? '');
    $userId = $_SESSION['user']['id'] ?? 1;

    // Strict Validation: Walk-in customer sale CANNOT be saved as credit or with an unpaid balance
    if ($customerId <= 1) {
        if ($paymentMethod === 'credit' || $dueAmount > 0.01 || $paidAmount < ($grandTotal - 0.01)) {
            echo json_encode([
                'success' => false,
                'message' => 'Walk-in customer sale cannot be saved as credit or with an unpaid balance. Please receive full payment of Rs. ' . number_format($grandTotal, 2) . ' or select a registered customer for Khata.'
            ]);
            exit;
        }
    }

    // Determine payment status
    if ($dueAmount <= 0.01) {
        $paymentStatus = 'paid';
    } elseif ($paidAmount > 0) {
        $paymentStatus = 'partial';
    } else {
        $paymentStatus = 'due';
    }

    try {
        // Enforce Negative Stock Selling Policy
        $allowNegativeStock = getSetting('allow_negative_stock', '0') === '1';
        if (!$allowNegativeStock) {
            foreach ($items as $it) {
                $checkProdId = (int)$it['product_id'];
                $checkQty = (float)$it['qty'];
                $chk = dbFetchOne("SELECT title, current_stock FROM products WHERE id = ?", [$checkProdId]);
                if (!$chk) {
                    throw new Exception("Product #$checkProdId not found in catalog.");
                }
                $avail = (float)$chk['current_stock'];
                if ($avail < $checkQty) {
                    throw new Exception("Negative stock selling is disabled! '{$chk['title']}' only has {$avail} in stock, but {$checkQty} was requested.");
                }
            }
        }

        $db = getDB();
        $db->beginTransaction();

        // Generate unique invoice number: INV-YEAR-0000X
        $lastSale = dbFetchOne("SELECT id FROM sales ORDER BY id DESC LIMIT 1");
        $nextNum = ($lastSale['id'] ?? 0) + 1;
        $invoiceNo = 'INV-' . date('Y') . '-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        // Insert into sales
        $stmt = $db->prepare("INSERT INTO sales (invoice_no, customer_id, sale_date, subtotal, discount_amount, 
                                tax_amount, grand_total, paid_amount, due_amount, payment_method, payment_account_id, 
                                payment_status, sale_type, notes, created_by)
                                VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $invoiceNo, $customerId, $subtotal, $discountAmount, $taxAmount, $grandTotal, 
            $paidAmount, $dueAmount, $paymentMethod, $paymentAccountId, $paymentStatus, $saleType, $notes, $userId
        ]);
        $saleId = $db->lastInsertId();

        // Process Sale Items & Stock Deductions
        $insertItemStmt = $db->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, cost_price, discount, subtotal)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($items as $item) {
            $pId = (int)$item['product_id'];
            $qty = (float)$item['qty'];
            $price = (float)$item['price'];
            $itemDiscount = (float)($item['discount'] ?? 0);
            $lineTotal = (float)$item['line_total'];

            $prod = dbFetchOne("SELECT purchase_price, current_stock FROM products WHERE id = ?", [$pId]);
            $costPrice = $prod ? (float)$prod['purchase_price'] : 0.00;

            $insertItemStmt->execute([$saleId, $pId, $qty, $price, $costPrice, $itemDiscount, $lineTotal]);

            // Deduct stock and record stock movement
            recordStockMovement($pId, 'sale', $saleId, -$qty, "POS Sale Invoice #$invoiceNo", $costPrice);
        }

        // Handle Customer Khata Ledger
        if ($customerId > 1) { // If not walk-in
            // Record full sale debit
            recordCustomerLedger($customerId, 'sale', $saleId, $invoiceNo, $grandTotal, 0, "Sale Invoice #$invoiceNo");
            // If payment made, record credit
            if ($paidAmount > 0) {
                recordCustomerLedger($customerId, 'payment', $saleId, $invoiceNo, 0, $paidAmount, "Payment for Invoice #$invoiceNo");
            }
        }

        // Handle Cash / Bank Inflow
        if ($paidAmount > 0 && $paymentAccountId > 0) {
            recordCashTransaction('sale', $paymentAccountId, $paidAmount, 'POS Counter Sale', $invoiceNo, "Cash received for invoice #$invoiceNo");
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'sale_id' => $saleId,
            'invoice_no' => $invoiceNo,
            'grand_total' => $grandTotal,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
            'change' => max(0, (float)($data['tendered_amount'] ?? $paidAmount) - $paidAmount)
        ]);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Checkout error: ' . $e->getMessage()]);
    }
    exit;
}

// 6. Hold / Resume Bill
if ($action === 'hold_bill') {
    $cartData = json_decode(file_get_contents('php://input'), true);
    if (!$cartData || empty($cartData['items'])) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
        exit;
    }
    if (!isset($_SESSION['held_bills'])) {
        $_SESSION['held_bills'] = [];
    }
    $holdRef = 'HOLD-' . date('His');
    $_SESSION['held_bills'][$holdRef] = [
        'hold_ref' => $holdRef,
        'time' => date('h:i A'),
        'customer_id' => $cartData['customer_id'] ?? 1,
        'customer_name' => $cartData['customer_name'] ?? 'Walk-in',
        'items' => $cartData['items'],
        'subtotal' => $cartData['subtotal'] ?? 0,
        'discount' => $cartData['discount_amount'] ?? 0,
        'grand_total' => $cartData['grand_total'] ?? 0,
    ];
    echo json_encode(['success' => true, 'hold_ref' => $holdRef, 'held_bills' => array_values($_SESSION['held_bills'])]);
    exit;
}

if ($action === 'get_held_bills') {
    $held = isset($_SESSION['held_bills']) ? array_values($_SESSION['held_bills']) : [];
    echo json_encode(['success' => true, 'held_bills' => $held]);
    exit;
}

if ($action === 'resume_bill') {
    $holdRef = trim($_GET['hold_ref'] ?? '');
    if (isset($_SESSION['held_bills'][$holdRef])) {
        $bill = $_SESSION['held_bills'][$holdRef];
        unset($_SESSION['held_bills'][$holdRef]);
        echo json_encode(['success' => true, 'bill' => $bill]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Held bill not found']);
    }
    exit;
}

// 7. Get Sale Items for Return / Inspection
if ($action === 'get_sale_items') {
    $saleId = (int)($_REQUEST['sale_id'] ?? 0);
    if (!$saleId) {
        echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
        exit;
    }
    $items = dbFetchAll("SELECT si.*, p.title, p.product_code, p.unit
                         FROM sale_items si
                         JOIN products p ON p.id = si.product_id
                         WHERE si.sale_id = ?", [$saleId]);
    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}

// 8. Get Purchase Details & Items
if ($action === 'get_purchase_details') {
    $purchaseId = (int)($_REQUEST['purchase_id'] ?? 0);
    if (!$purchaseId) {
        echo json_encode(['success' => false, 'message' => 'Invalid purchase ID']);
        exit;
    }

    $purchase = dbFetchOne("SELECT p.*, s.name as supplier_name, s.company_name, s.phone as supplier_phone,
                                   s.current_balance as supplier_balance,
                                   u.full_name as creator_name,
                                   a.account_name
                            FROM purchases p
                            JOIN suppliers s ON s.id = p.supplier_id
                            LEFT JOIN users u ON u.id = p.created_by
                            LEFT JOIN accounts a ON a.id = p.payment_account_id
                            WHERE p.id = ?", [$purchaseId]);

    if (!$purchase) {
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        exit;
    }

    $items = dbFetchAll("SELECT pi.*, p.title, p.product_code, p.barcode, p.unit, p.product_type,
                                p.author, p.publisher, p.class_grade
                         FROM purchase_items pi
                         JOIN products p ON p.id = pi.product_id
                         WHERE pi.purchase_id = ?
                         ORDER BY pi.id ASC", [$purchaseId]);

    echo json_encode([
        'success' => true,
        'purchase' => $purchase,
        'items' => $items
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
