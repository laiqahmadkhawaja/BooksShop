<?php
// print_report.php - Dedicated Clean A4 Printable View for All Reports
require_once __DIR__ . '/config/app.php';
requireAuth();

$reportType = $_GET['report'] ?? 'pnl';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedAccountId = (int)($_GET['account_id'] ?? 1);
$selectedClassId = (int)($_GET['class_id'] ?? 0);

$user = authUser();
$shopName = getSetting('shop_name', 'Kitab Ghar & Stationery');
$shopNameUrdu = getSetting('shop_name_urdu', 'کتاب گھر اینڈ اسٹیشنری مارٹ');
$shopTagline = getSetting('shop_tagline', 'Complete Textbooks, General Books & Quality Stationery');
$shopAddress = getSetting('shop_address', 'Shop # 14-18, Urdu Bazaar, Lahore, Pakistan');
$shopPhone = getSetting('shop_phone', '+92 300 1234567 / 042-37210000');

$reportTitle = 'Report';
$reportSubtitle = '';

// ================= 1. PROFIT & LOSS =================
if ($reportType === 'pnl') {
    $reportTitle = 'PROFIT & LOSS STATEMENT';
    $reportSubtitle = "Reporting Period: " . date('d M, Y', strtotime($startDate)) . " to " . date('d M, Y', strtotime($endDate));

    $salesData = dbFetchOne("SELECT COUNT(*) as orders_count, SUM(subtotal) as gross_subtotal, 
                                    SUM(discount_amount) as total_discount, SUM(grand_total) as net_sales 
                             FROM sales 
                             WHERE DATE(sale_date) BETWEEN ? AND ? AND hold_status = 0", [$startDate, $endDate]);
    $netSales = (float)($salesData['net_sales'] ?? 0);
    $totalDiscount = (float)($salesData['total_discount'] ?? 0);

    $cogsData = dbFetchOne("SELECT SUM(si.quantity * si.cost_price) as total_cogs
                            FROM sale_items si
                            JOIN sales s ON s.id = si.sale_id
                            WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.hold_status = 0", [$startDate, $endDate]);
    $totalCogs = (float)($cogsData['total_cogs'] ?? 0);
    $grossProfit = $netSales - $totalCogs;

    $expensesList = dbFetchAll("SELECT category_name, SUM(amount) as cat_amount 
                                FROM cash_transactions 
                                WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'expense'
                                GROUP BY category_name", [$startDate, $endDate]);
    $totalExpenses = 0;
    foreach ($expensesList as $exp) $totalExpenses += (float)$exp['cat_amount'];

    $incomeData = dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'income'", [$startDate, $endDate]);
    $otherIncome = (float)($incomeData['tot'] ?? 0);
    $netProfit = ($grossProfit + $otherIncome) - $totalExpenses;
}

// ================= 2. TRIAL BALANCE =================
elseif ($reportType === 'trial_balance') {
    $reportTitle = 'COMPREHENSIVE TRIAL BALANCE';
    $reportSubtitle = "As of " . date('d M, Y', strtotime($endDate)) . " (Period: " . date('d M, Y', strtotime($startDate)) . " to " . date('d M, Y', strtotime($endDate)) . ")";

    $stockValRow = dbFetchOne("SELECT SUM(current_stock * purchase_price) as tot_cost, SUM(current_stock * retail_price) as tot_retail FROM products WHERE status = 1");
    $inventoryValuation = (float)($stockValRow['tot_cost'] ?? 0);
    $totReceivables = (float)(dbFetchOne("SELECT SUM(current_balance) as tot FROM customers WHERE current_balance > 0")['tot'] ?? 0);
    $totPayables = (float)(dbFetchOne("SELECT SUM(current_balance) as tot FROM suppliers WHERE current_balance > 0")['tot'] ?? 0);

    $cashAccounts = dbFetchAll("SELECT * FROM accounts WHERE status = 1 ORDER BY account_type ASC, id ASC");
    $trialSales = (float)(dbFetchOne("SELECT SUM(grand_total) as tot FROM sales WHERE DATE(sale_date) BETWEEN ? AND ? AND hold_status = 0", [$startDate, $endDate])['tot'] ?? 0);
    $trialCogs = (float)(dbFetchOne("SELECT SUM(si.quantity * si.cost_price) as tot FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.hold_status = 0", [$startDate, $endDate])['tot'] ?? 0);
    $trialExpenses = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'expense'", [$startDate, $endDate])['tot'] ?? 0);
    $trialIncome = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'income'", [$startDate, $endDate])['tot'] ?? 0);
}

// ================= 3. ACCOUNT STATEMENT / BANK LEDGER =================
elseif ($reportType === 'account_statement') {
    $currentAccount = dbFetchOne("SELECT * FROM accounts WHERE id = ?", [$selectedAccountId]) ?: dbFetchOne("SELECT * FROM accounts WHERE status = 1 LIMIT 1");
    $reportTitle = 'ACCOUNT LEDGER STATEMENT';
    $reportSubtitle = ($currentAccount['account_name'] ?? 'Account') . " &bull; Period: " . date('d M, Y', strtotime($startDate)) . " to " . date('d M, Y', strtotime($endDate));

    $priorInflows = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) < ? AND transaction_type IN ('income', 'customer_payment', 'sale')", [$currentAccount['id'], $startDate])['tot'] ?? 0);
    $priorTransfersIn = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE to_account_id = ? AND DATE(transaction_date) < ? AND transaction_type = 'transfer'", [$currentAccount['id'], $startDate])['tot'] ?? 0);
    $priorOutflows = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) < ? AND transaction_type IN ('expense', 'purchase', 'supplier_payment', 'transfer')", [$currentAccount['id'], $startDate])['tot'] ?? 0);

    $accountOpeningBal = (float)($currentAccount['balance'] ?? 0);
    $statementTransactions = dbFetchAll("SELECT ct.*, a.account_name, ta.account_name as to_account_name, u.full_name as user_name
                                          FROM cash_transactions ct
                                          JOIN accounts a ON a.id = ct.account_id
                                          LEFT JOIN accounts ta ON ta.id = ct.to_account_id
                                          LEFT JOIN users u ON u.id = ct.created_by
                                          WHERE (ct.account_id = ? OR ct.to_account_id = ?) 
                                          AND DATE(ct.transaction_date) BETWEEN ? AND ?
                                          ORDER BY ct.transaction_date ASC, ct.id ASC", [$currentAccount['id'], $currentAccount['id'], $startDate, $endDate]);
}

// ================= 4. EXPENSE ANALYTICS =================
elseif ($reportType === 'expense_report') {
    $reportTitle = 'OPERATING EXPENSES REPORT & LEDGER';
    $reportSubtitle = "Reporting Period: " . date('d M, Y', strtotime($startDate)) . " to " . date('d M, Y', strtotime($endDate));

    $expenseStats = dbFetchOne("SELECT COUNT(*) as vouchers_count, SUM(amount) as total_expenses, MAX(amount) as max_expense, AVG(amount) as avg_expense 
                                FROM cash_transactions 
                                WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'expense'", [$startDate, $endDate]);
    $totalExpAmount = (float)($expenseStats['total_expenses'] ?? 0);

    $expenseCategoriesBreakdown = dbFetchAll("SELECT category_name, COUNT(*) as cnt, SUM(amount) as total_cat 
                                              FROM cash_transactions 
                                              WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'expense'
                                              GROUP BY category_name 
                                              ORDER BY total_cat DESC", [$startDate, $endDate]);

    $expenseListDetailed = dbFetchAll("SELECT ct.*, a.account_name, u.full_name as user_name
                                       FROM cash_transactions ct
                                       JOIN accounts a ON a.id = ct.account_id
                                       LEFT JOIN users u ON u.id = ct.created_by
                                       WHERE DATE(ct.transaction_date) BETWEEN ? AND ? AND ct.transaction_type = 'expense'
                                       ORDER BY ct.transaction_date ASC, ct.id ASC", [$startDate, $endDate]);
}

// ================= 5. DAILY CASH CLOSING SLIP =================
elseif ($reportType === 'cash_closing') {
    $drawerAcc = dbFetchOne("SELECT * FROM accounts WHERE id = 1") ?: dbFetchOne("SELECT * FROM accounts WHERE status = 1 LIMIT 1");
    $drawerId = $drawerAcc['id'] ?? 1;

    $reportTitle = 'DAILY CASH DRAWER RECONCILIATION SLIP';
    $reportSubtitle = "Closing Date: " . date('l, d F Y', strtotime($selectedDate));

    $priorIn = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) < ? AND transaction_type IN ('income', 'sale', 'customer_payment')", [$drawerId, $selectedDate])['tot'] ?? 0);
    $priorTransIn = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE to_account_id = ? AND DATE(transaction_date) < ? AND transaction_type = 'transfer'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $priorOut = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) < ? AND transaction_type IN ('expense', 'purchase', 'supplier_payment', 'transfer')", [$drawerId, $selectedDate])['tot'] ?? 0);
    $dayOpeningCash = max(0, ($priorIn + $priorTransIn) - $priorOut);

    $daySalesCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'sale'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $dayCustReceiptsCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'customer_payment'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $dayIncomeCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'income'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $dayTransferInCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE to_account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'transfer'", [$drawerId, $selectedDate])['tot'] ?? 0);

    $dayPurchasesCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'purchase'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $daySuppPaymentsCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'supplier_payment'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $dayExpensesCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'expense'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $dayTransfersOutCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'transfer'", [$drawerId, $selectedDate])['tot'] ?? 0);

    $totalDayInflows = $daySalesCash + $dayCustReceiptsCash + $dayIncomeCash + $dayTransferInCash;
    $totalDayOutflows = $dayPurchasesCash + $daySuppPaymentsCash + $dayExpensesCash + $dayTransfersOutCash;
    $systemClosingCash = ($dayOpeningCash + $totalDayInflows) - $totalDayOutflows;
}

// ================= 6. PRODUCT SALES =================
elseif ($reportType === 'product_sales') {
    $reportTitle = 'PRODUCT-WISE SALES & PROFITABILITY';
    $reportSubtitle = "Period: " . date('d M, Y', strtotime($startDate)) . " to " . date('d M, Y', strtotime($endDate));

    $productSales = dbFetchAll("SELECT p.id, p.title, p.product_code, p.product_type, p.class_grade, 
                                       SUM(si.quantity) as sold_qty, 
                                       SUM(si.subtotal) as total_revenue, 
                                       SUM(si.quantity * si.cost_price) as total_cost,
                                       (SUM(si.subtotal) - SUM(si.quantity * si.cost_price)) as profit
                                FROM sale_items si
                                JOIN products p ON p.id = si.product_id
                                JOIN sales s ON s.id = si.sale_id
                                WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.hold_status = 0
                                GROUP BY p.id
                                ORDER BY total_revenue DESC", [$startDate, $endDate]);
}

// ================= 7. CATEGORY SALES =================
elseif ($reportType === 'category_sales') {
    $reportTitle = 'CATEGORY-WISE SALES BREAKDOWN';
    $reportSubtitle = "Period: " . date('d M, Y', strtotime($startDate)) . " to " . date('d M, Y', strtotime($endDate));

    $categorySales = dbFetchAll("SELECT c.name as category_name, c.type, 
                                        COUNT(DISTINCT si.product_id) as distinct_products,
                                        SUM(si.quantity) as sold_qty, 
                                        SUM(si.subtotal) as total_revenue
                                 FROM sale_items si
                                 JOIN products p ON p.id = si.product_id
                                 JOIN categories c ON c.id = p.category_id
                                 JOIN sales s ON s.id = si.sale_id
                                 WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.hold_status = 0
                                 GROUP BY c.id
                                 ORDER BY total_revenue DESC", [$startDate, $endDate]);
}

// ================= 8. RECEIVABLES & PAYABLES =================
elseif ($reportType === 'balances') {
    $reportTitle = 'RECEIVABLES & PAYABLES AGING STATEMENT';
    $reportSubtitle = "As of " . date('d M, Y');

    $custReceivables = dbFetchAll("SELECT name, phone, customer_type, current_balance, credit_limit FROM customers WHERE current_balance > 0 ORDER BY current_balance DESC");
    $suppPayables = dbFetchAll("SELECT name, company_name, phone, current_balance FROM suppliers WHERE current_balance > 0 ORDER BY current_balance DESC");
}

// ================= 9. SCHOOL CLASS SYLLABUS BOOKLIST =================
elseif ($reportType === 'school_syllabus') {
    $currentClass = dbFetchOne("SELECT sc.*, s.name as school_name, s.phone as school_phone, s.address as school_address 
                                FROM school_classes sc 
                                JOIN schools s ON s.id = sc.school_id 
                                WHERE sc.id = ?", [$selectedClassId]);
    if ($currentClass) {
        $reportTitle = 'CLASS SYLLABUS & BOOKLIST PACKAGE';
        $reportSubtitle = htmlspecialchars($currentClass['school_name']) . " &mdash; " . htmlspecialchars($currentClass['class_name']) . " (Session: " . htmlspecialchars($currentClass['academic_session']) . ")";
        $packageItems = dbFetchAll("SELECT sci.*, p.title, p.product_code, p.barcode, p.product_type, p.unit, 
                                           p.purchase_price, p.retail_price, p.current_stock, p.publisher, p.author
                                    FROM school_class_items sci
                                    JOIN products p ON p.id = sci.product_id
                                    WHERE sci.school_class_id = ?
                                    ORDER BY p.product_type ASC, p.title ASC", [$selectedClassId]);
    }
}

// ================= 10. CUSTOMER KHATA STATEMENT =================
elseif ($reportType === 'customer_statement') {
    $selectedCustomerId = (int)($_GET['customer_id'] ?? 0);
    $currentCustomer = dbFetchOne("SELECT * FROM customers WHERE id = ?", [$selectedCustomerId]);
    if ($currentCustomer) {
        $reportTitle = 'CUSTOMER KHATA ACCOUNT STATEMENT';
        $reportSubtitle = htmlspecialchars($currentCustomer['name']) . " &bull; Phone: " . htmlspecialchars($currentCustomer['phone'] ?: 'N/A') . " &bull; Current Due: " . formatCurrency($currentCustomer['current_balance']);
        $custLedger = dbFetchAll("SELECT * FROM customer_ledgers WHERE customer_id = ? ORDER BY transaction_date ASC, id ASC", [$selectedCustomerId]);
    }
}

// ================= 11. SUPPLIER LEDGER STATEMENT =================
elseif ($reportType === 'supplier_statement') {
    $selectedSupplierId = (int)($_GET['supplier_id'] ?? 0);
    $currentSupplier = dbFetchOne("SELECT * FROM suppliers WHERE id = ?", [$selectedSupplierId]);
    if ($currentSupplier) {
        $reportTitle = 'SUPPLIER BILLING & PAYMENT LEDGER';
        $reportSubtitle = htmlspecialchars($currentSupplier['name']) . ($currentSupplier['company_name'] ? " (".htmlspecialchars($currentSupplier['company_name']).")" : "") . " &bull; Payable: " . formatCurrency($currentSupplier['current_balance']);
        $suppLedger = dbFetchAll("SELECT * FROM supplier_ledgers WHERE supplier_id = ? ORDER BY transaction_date ASC, id ASC", [$selectedSupplierId]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($reportTitle) ?> - <?= htmlspecialchars($shopName) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f8fafc;
            color: #1e293b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 13px;
        }
        .print-container {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #fff;
            padding: 15mm;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-radius: 4px;
        }
        .font-urdu {
            font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', serif;
        }
        .report-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .report-title-badge {
            display: inline-block;
            background: #0f172a;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 4px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .table-print {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .table-print th {
            background-color: #f1f5f9 !important;
            color: #0f172a;
            font-weight: 700;
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
            text-transform: uppercase;
            font-size: 11px;
        }
        .table-print td {
            border: 1px solid #e2e8f0;
            padding: 6px 10px;
            vertical-align: middle;
        }
        .table-print tfoot td {
            font-weight: 700;
            background-color: #f8fafc;
            border-top: 2px solid #0f172a;
        }
        .meta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 16px;
        }
        .action-toolbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #0f172a;
            color: #fff;
            padding: 10px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .signature-section {
            margin-top: 50px;
            padding-top: 15px;
        }
        .sig-line {
            border-top: 1px dashed #64748b;
            padding-top: 5px;
            font-weight: 600;
            color: #475569;
            font-size: 11px;
            text-align: center;
        }
        @media print {
            body {
                background: #fff;
                color: #000;
                margin: 0;
                padding: 0;
            }
            .no-print, .action-toolbar {
                display: none !important;
            }
            .print-container {
                width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 5mm 8mm !important;
                box-shadow: none !important;
                border: none !important;
            }
            .table-print th {
                background-color: #e2e8f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .report-title-badge {
                background: #000 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<!-- Standalone Action Toolbar (Hidden during print) -->
<div class="action-toolbar no-print d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
        <i class="fa-solid fa-file-invoice text-warning fs-5"></i>
        <span class="fw-bold"><?= htmlspecialchars($reportTitle) ?></span>
        <span class="badge bg-secondary"><?= htmlspecialchars($shopName) ?></span>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-warning btn-sm fw-bold px-3 shadow-sm" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print Report (Ctrl + P)
        </button>
        <button type="button" class="btn btn-outline-light btn-sm px-3" onclick="window.close()">
            <i class="fa-solid fa-xmark me-1"></i> Close Window
        </button>
    </div>
</div>

<div class="print-container">
    <!-- Header Letterhead -->
    <div class="report-header">
        <div class="row align-items-center">
            <div class="col-7">
                <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($shopName) ?></h3>
                <div class="text-secondary small font-urdu mt-1"><?= htmlspecialchars($shopNameUrdu) ?></div>
                <div class="text-muted small mt-1"><?= htmlspecialchars($shopTagline) ?></div>
                <div class="small text-muted mt-1">
                    <i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($shopAddress) ?><br>
                    <i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($shopPhone) ?>
                </div>
            </div>
            <div class="col-5 text-end">
                <div class="report-title-badge mb-2"><?= htmlspecialchars($reportTitle) ?></div>
                <div class="small text-muted fw-bold"><?= htmlspecialchars($reportSubtitle) ?></div>
                <div class="small text-muted mt-2">
                    Date Generated: <strong><?= date('d M, Y &bull; h:i A') ?></strong><br>
                    Generated By: <strong><?= htmlspecialchars($user['full_name'] ?? 'Staff') ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- REPORT BODY ACCORDING TO TYPE -->
    <?php if ($reportType === 'pnl'): ?>
        <!-- ================= PROFIT & LOSS STATEMENT ================= -->
        <table class="table-print mb-4">
            <thead>
                <tr>
                    <th style="width: 70%;">Particulars / Head of Account</th>
                    <th style="width: 30%;" class="text-end">Amount (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <tr class="table-light"><td colspan="2" class="fw-bold text-primary">1. REVENUE FROM TRADING & SALES</td></tr>
                <tr><td class="ps-4">Gross Sales & Syllabi Invoices</td><td class="text-end"><?= formatCurrency($salesData['gross_subtotal'] ?? 0) ?></td></tr>
                <?php if ($totalDiscount > 0): ?>
                    <tr><td class="ps-4 text-danger">Less: Customer Discounts Allowed</td><td class="text-end text-danger">- <?= formatCurrency($totalDiscount) ?></td></tr>
                <?php endif; ?>
                <tr class="fw-bold"><td class="ps-4">Net Sales Turnover</td><td class="text-end text-primary"><?= formatCurrency($netSales) ?></td></tr>

                <tr class="table-light"><td colspan="2" class="fw-bold text-danger">2. COST OF GOODS SOLD (COGS)</td></tr>
                <tr><td class="ps-4">Cost of Books, Textbooks & Stationery Sold</td><td class="text-end text-danger">- <?= formatCurrency($totalCogs) ?></td></tr>
                <tr class="fw-bold table-light"><td>GROSS TRADING PROFIT</td><td class="text-end text-primary"><?= formatCurrency($grossProfit) ?></td></tr>

                <tr class="table-light"><td colspan="2" class="fw-bold text-danger">3. OPERATING EXPENSES</td></tr>
                <?php if (empty($expensesList)): ?>
                    <tr><td class="ps-4 text-muted">No operating expenses recorded.</td><td class="text-end">Rs. 0.00</td></tr>
                <?php else: ?>
                    <?php foreach ($expensesList as $exp): ?>
                        <tr><td class="ps-4"><?= htmlspecialchars($exp['category_name']) ?></td><td class="text-end text-danger">- <?= formatCurrency($exp['cat_amount']) ?></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr class="fw-bold"><td class="ps-4">Total Operating Expenses</td><td class="text-end text-danger">- <?= formatCurrency($totalExpenses) ?></td></tr>

                <?php if ($otherIncome > 0): ?>
                    <tr class="table-light"><td colspan="2" class="fw-bold text-success">4. OTHER DIRECT INCOMES</td></tr>
                    <tr><td class="ps-4">Photocopy, Services & Other Incomes</td><td class="text-end text-success">+ <?= formatCurrency($otherIncome) ?></td></tr>
                <?php endif; ?>

                <tr style="border-top: 3px double #0f172a; font-size: 14px; font-weight: bold;">
                    <td>NET OPERATING PROFIT / (LOSS)</td>
                    <td class="text-end <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($netProfit) ?></td>
                </tr>
            </tbody>
        </table>

    <?php elseif ($reportType === 'trial_balance'): ?>
        <!-- ================= TRIAL BALANCE ================= -->
        <?php $totD = 0; $totC = 0; ?>
        <table class="table-print mb-4">
            <thead>
                <tr>
                    <th style="width: 50%;">Head of Account / Ledger Head</th>
                    <th style="width: 25%;" class="text-end">Debit (Rs.)</th>
                    <th style="width: 25%;" class="text-end">Credit (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <tr class="table-light"><td colspan="3" class="fw-bold">CURRENT ASSETS & CASH BALANCES</td></tr>
                <?php foreach ($cashAccounts as $ca): ?>
                    <?php 
                        $bal = (float)$ca['balance'];
                        if ($bal >= 0) $totD += $bal; else $totC += abs($bal);
                    ?>
                    <tr>
                        <td class="ps-4"><?= htmlspecialchars($ca['account_name']) ?> <?= $ca['bank_name'] ? "(".htmlspecialchars($ca['bank_name']).")" : "" ?></td>
                        <td class="text-end font-monospace"><?= $bal >= 0 ? formatCurrency($bal) : '-' ?></td>
                        <td class="text-end font-monospace"><?= $bal < 0 ? formatCurrency(abs($bal)) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php $totD += $totReceivables; ?>
                <tr>
                    <td class="ps-4">Accounts Receivable (Customer & School Khata)</td>
                    <td class="text-end font-monospace"><?= formatCurrency($totReceivables) ?></td>
                    <td class="text-end font-monospace">-</td>
                </tr>
                <?php $totD += $inventoryValuation; ?>
                <tr>
                    <td class="ps-4">Merchandise Inventory Valuation (at Purchase Cost)</td>
                    <td class="text-end font-monospace"><?= formatCurrency($inventoryValuation) ?></td>
                    <td class="text-end font-monospace">-</td>
                </tr>

                <tr class="table-light"><td colspan="3" class="fw-bold">CURRENT LIABILITIES</td></tr>
                <?php $totC += $totPayables; ?>
                <tr>
                    <td class="ps-4">Accounts Payable (Publishers & Vendors Khata)</td>
                    <td class="text-end font-monospace">-</td>
                    <td class="text-end font-monospace"><?= formatCurrency($totPayables) ?></td>
                </tr>

                <tr class="table-light"><td colspan="3" class="fw-bold">PERIOD REVENUE & COSTS</td></tr>
                <?php $totC += $trialSales; ?>
                <tr><td class="ps-4">Sales Turnover</td><td class="text-end font-monospace">-</td><td class="text-end font-monospace"><?= formatCurrency($trialSales) ?></td></tr>
                <?php $totD += $trialCogs; ?>
                <tr><td class="ps-4">Cost of Goods Sold (COGS)</td><td class="text-end font-monospace"><?= formatCurrency($trialCogs) ?></td><td class="text-end font-monospace">-</td></tr>
                <?php $totD += $trialExpenses; ?>
                <tr><td class="ps-4">Operating Overhead Expenses</td><td class="text-end font-monospace"><?= formatCurrency($trialExpenses) ?></td><td class="text-end font-monospace">-</td></tr>
                <?php if ($trialIncome > 0): $totC += $trialIncome; ?>
                    <tr><td class="ps-4">Other Incomes</td><td class="text-end font-monospace">-</td><td class="text-end font-monospace"><?= formatCurrency($trialIncome) ?></td></tr>
                <?php endif; ?>

                <?php 
                    $diff = $totD - $totC;
                    if ($diff > 0) { $eqC = $diff; $totC += $eqC; } else { $eqD = abs($diff); $totD += $eqD; }
                ?>
                <tr class="fw-semibold">
                    <td class="ps-4">Owner's Equity & Retained Store Capital</td>
                    <td class="text-end font-monospace"><?= isset($eqD) ? formatCurrency($eqD) : '-' ?></td>
                    <td class="text-end font-monospace"><?= isset($eqC) ? formatCurrency($eqC) : '-' ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="border-top: 3px double #0f172a; font-size: 13px;">
                    <td class="text-uppercase">Total Trial Balance</td>
                    <td class="text-end font-monospace"><?= formatCurrency($totD) ?></td>
                    <td class="text-end font-monospace"><?= formatCurrency($totC) ?></td>
                </tr>
            </tfoot>
        </table>

    <?php elseif ($reportType === 'account_statement'): ?>
        <!-- ================= ACCOUNT STATEMENT ================= -->
        <div class="meta-box d-flex justify-content-between align-items-center">
            <div>
                <strong>Account:</strong> <?= htmlspecialchars($currentAccount['account_name']) ?> &bull; 
                <strong>Type:</strong> <?= strtoupper($currentAccount['account_type']) ?> &bull;
                <strong>A/C #:</strong> <?= htmlspecialchars($currentAccount['account_number'] ?: 'N/A') ?>
            </div>
            <div>
                <strong>Current Ledger Balance:</strong> <?= formatCurrency($currentAccount['balance']) ?>
            </div>
        </div>
        <table class="table-print mb-4">
            <thead>
                <tr>
                    <th style="width: 15%;">Date & Time</th>
                    <th style="width: 15%;">Voucher / Ref</th>
                    <th style="width: 18%;">Category / Type</th>
                    <th style="width: 22%;">Description</th>
                    <th style="width: 10%;" class="text-end">Deposit (+)</th>
                    <th style="width: 10%;" class="text-end">Outflow (-)</th>
                    <th style="width: 10%;" class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    $running = $accountOpeningBal; 
                    $tIn = 0; $tOut = 0;
                ?>
                <tr class="fw-bold table-light">
                    <td colspan="4">Opening Balance prior to <?= date('d M, Y', strtotime($startDate)) ?></td>
                    <td class="text-end">-</td><td class="text-end">-</td>
                    <td class="text-end font-monospace"><?= formatCurrency($running) ?></td>
                </tr>
                <?php foreach ($statementTransactions as $st): ?>
                    <?php 
                        $isRec = ($st['to_account_id'] == $currentAccount['id']);
                        if ($isRec) {
                            $inflow = (float)$st['amount']; $outflow = 0;
                            $running += $inflow; $tIn += $inflow;
                        } else {
                            if (in_array($st['transaction_type'], ['income', 'customer_payment', 'sale'])) {
                                $inflow = (float)$st['amount']; $outflow = 0;
                                $running += $inflow; $tIn += $inflow;
                            } else {
                                $inflow = 0; $outflow = (float)$st['amount'];
                                $running -= $outflow; $tOut += $outflow;
                            }
                        }
                    ?>
                    <tr>
                        <td><?= date('d M Y, h:i A', strtotime($st['transaction_date'])) ?></td>
                        <td class="font-monospace"><?= htmlspecialchars($st['reference_no'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($st['category_name'] ?: $st['transaction_type']) ?></td>
                        <td><?= htmlspecialchars($st['description'] ?: '-') ?></td>
                        <td class="text-end font-monospace text-success"><?= $inflow > 0 ? formatCurrency($inflow) : '-' ?></td>
                        <td class="text-end font-monospace text-danger"><?= $outflow > 0 ? formatCurrency($outflow) : '-' ?></td>
                        <td class="text-end font-monospace fw-bold"><?= formatCurrency($running) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end text-uppercase">Totals:</td>
                    <td class="text-end font-monospace text-success">+ <?= formatCurrency($tIn) ?></td>
                    <td class="text-end font-monospace text-danger">- <?= formatCurrency($tOut) ?></td>
                    <td class="text-end font-monospace text-primary"><?= formatCurrency($running) ?></td>
                </tr>
            </tfoot>
        </table>

    <?php elseif ($reportType === 'expense_report'): ?>
        <!-- ================= EXPENSE REPORT ================= -->
        <div class="row g-2 mb-3">
            <div class="col-3"><div class="meta-box text-center py-2"><small class="text-muted">Total Expenses</small><div class="fw-bold text-danger fs-6"><?= formatCurrency($totalExpAmount) ?></div></div></div>
            <div class="col-3"><div class="meta-box text-center py-2"><small class="text-muted">Vouchers Count</small><div class="fw-bold fs-6"><?= (int)($expenseStats['vouchers_count'] ?? 0) ?></div></div></div>
            <div class="col-3"><div class="meta-box text-center py-2"><small class="text-muted">Largest Voucher</small><div class="fw-bold fs-6"><?= formatCurrency($expenseStats['max_expense'] ?? 0) ?></div></div></div>
            <div class="col-3"><div class="meta-box text-center py-2"><small class="text-muted">Average Voucher</small><div class="fw-bold fs-6"><?= formatCurrency($expenseStats['avg_expense'] ?? 0) ?></div></div></div>
        </div>

        <h6 class="fw-bold text-dark mb-2">1. Category-Wise Summary</h6>
        <table class="table-print mb-4">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th class="text-center">Count</th>
                    <th class="text-end">Percentage</th>
                    <th class="text-end">Total Amount (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenseCategoriesBreakdown as $ecb): ?>
                    <?php $pct = $totalExpAmount > 0 ? (($ecb['total_cat'] / $totalExpAmount) * 100) : 0; ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($ecb['category_name']) ?></td>
                        <td class="text-center"><?= $ecb['cnt'] ?></td>
                        <td class="text-end"><?= number_format($pct, 1) ?>%</td>
                        <td class="text-end font-monospace fw-bold text-danger"><?= formatCurrency($ecb['total_cat']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h6 class="fw-bold text-dark mb-2">2. Itemized Expense Vouchers Ledger</h6>
        <table class="table-print mb-4">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Account Paid From</th>
                    <th>Voucher #</th>
                    <th>Description</th>
                    <th class="text-end">Amount (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenseListDetailed as $eld): ?>
                    <tr>
                        <td><?= date('d M, Y', strtotime($eld['transaction_date'])) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($eld['category_name']) ?></td>
                        <td><?= htmlspecialchars($eld['account_name']) ?></td>
                        <td class="font-monospace"><?= htmlspecialchars($eld['reference_no'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($eld['description'] ?: '-') ?></td>
                        <td class="text-end font-monospace fw-bold text-danger"><?= formatCurrency($eld['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-end text-uppercase">Total Operating Expenses:</td>
                    <td class="text-end font-monospace text-danger"><?= formatCurrency($totalExpAmount) ?></td>
                </tr>
            </tfoot>
        </table>

    <?php elseif ($reportType === 'cash_closing'): ?>
        <!-- ================= DAILY CASH CLOSING SLIP ================= -->
        <table class="table-print mb-4">
            <tbody>
                <tr class="table-light">
                    <td class="fw-bold" style="width: 70%;">1. OPENING CASH BALANCE IN DRAWER</td>
                    <td class="text-end font-monospace fw-bold" style="width: 30%;"><?= formatCurrency($dayOpeningCash) ?></td>
                </tr>
                <tr class="table-light"><td colspan="2" class="fw-bold text-success">2. CASH INFLOWS COLLECTED TODAY</td></tr>
                <tr><td class="ps-4">Counter POS Cash Sales</td><td class="text-end font-monospace text-success">+ <?= formatCurrency($daySalesCash) ?></td></tr>
                <tr><td class="ps-4">Customer Khata / Receivables Paid in Cash</td><td class="text-end font-monospace text-success">+ <?= formatCurrency($dayCustReceiptsCash) ?></td></tr>
                <tr><td class="ps-4">Other Cash Income / Capital Injections</td><td class="text-end font-monospace text-success">+ <?= formatCurrency($dayIncomeCash) ?></td></tr>
                <tr><td class="ps-4">Cash Received from Bank Account</td><td class="text-end font-monospace text-success">+ <?= formatCurrency($dayTransferInCash) ?></td></tr>
                <tr class="fw-bold"><td>Total Cash Inflows</td><td class="text-end font-monospace text-success">+ <?= formatCurrency($totalDayInflows) ?></td></tr>

                <tr class="table-light"><td colspan="2" class="fw-bold text-danger">3. CASH OUTFLOWS PAID TODAY</td></tr>
                <tr><td class="ps-4">Cash Purchases Paid Directly</td><td class="text-end font-monospace text-danger">- <?= formatCurrency($dayPurchasesCash) ?></td></tr>
                <tr><td class="ps-4">Supplier / Publisher Khata Paid in Cash</td><td class="text-end font-monospace text-danger">- <?= formatCurrency($daySuppPaymentsCash) ?></td></tr>
                <tr><td class="ps-4">Operating Expenses Paid from Drawer</td><td class="text-end font-monospace text-danger">- <?= formatCurrency($dayExpensesCash) ?></td></tr>
                <tr><td class="ps-4">Cash Deposited to Bank</td><td class="text-end font-monospace text-danger">- <?= formatCurrency($dayTransfersOutCash) ?></td></tr>
                <tr class="fw-bold"><td>Total Cash Outflows</td><td class="text-end font-monospace text-danger">- <?= formatCurrency($totalDayOutflows) ?></td></tr>

                <tr style="border-top: 3px double #0f172a; font-size: 14px; font-weight: bold;">
                    <td>SYSTEM EXPECTED CLOSING CASH</td>
                    <td class="text-end font-monospace text-primary"><?= formatCurrency($systemClosingCash) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Denomination table for physical count -->
        <h6 class="fw-bold text-dark mb-2">Physical Currency Denominations Verification:</h6>
        <table class="table-print mb-4" style="font-size: 11px;">
            <thead>
                <tr>
                    <th>Denomination</th>
                    <th class="text-center" style="width: 25%;">Count</th>
                    <th class="text-end" style="width: 35%;">Subtotal (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ([5000, 1000, 500, 100, 50, 20, 10] as $dn): ?>
                    <tr>
                        <td class="fw-bold">Rs. <?= $dn ?> Note</td>
                        <td class="text-center" style="border-bottom: 1px dotted #94a3b8;">&nbsp;</td>
                        <td class="text-end" style="border-bottom: 1px dotted #94a3b8;">&nbsp;</td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td class="fw-bold">Coins & Loose Cash</td>
                    <td class="text-center" style="border-bottom: 1px dotted #94a3b8;">&nbsp;</td>
                    <td class="text-end" style="border-bottom: 1px dotted #94a3b8;">&nbsp;</td>
                </tr>
                <tr class="fw-bold table-light">
                    <td colspan="2">TOTAL PHYSICAL COUNTED CASH:</td>
                    <td class="text-end">&nbsp;</td>
                </tr>
                <tr class="fw-bold">
                    <td colspan="2">VARIANCE (SHORTAGE / EXCESS):</td>
                    <td class="text-end">&nbsp;</td>
                </tr>
            </tbody>
        </table>

    <?php elseif ($reportType === 'product_sales'): ?>
        <!-- ================= PRODUCT SALES ================= -->
        <table class="table-print mb-4">
            <thead>
                <tr>
                    <th>Product / Title</th>
                    <th>Type</th>
                    <th class="text-center">Units Sold</th>
                    <th class="text-end">Revenue (Rs.)</th>
                    <th class="text-end">COGS Cost (Rs.)</th>
                    <th class="text-end">Gross Profit</th>
                    <th class="text-end">Margin %</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    $tSold = 0; $tRev = 0; $tC = 0; $tProf = 0;
                    foreach ($productSales as $ps): 
                        $tSold += $ps['sold_qty']; $tRev += $ps['total_revenue'];
                        $tC += $ps['total_cost']; $tProf += $ps['profit'];
                        $m = $ps['total_revenue'] > 0 ? ($ps['profit'] / $ps['total_revenue']) * 100 : 0;
                ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($ps['title']) ?></strong>
                            <div class="text-muted" style="font-size: 10px;"><?= htmlspecialchars($ps['product_code']) ?></div>
                        </td>
                        <td><?= ucfirst($ps['product_type']) ?></td>
                        <td class="text-center fw-bold"><?= (float)$ps['sold_qty'] ?></td>
                        <td class="text-end font-monospace"><?= formatCurrency($ps['total_revenue']) ?></td>
                        <td class="text-end font-monospace"><?= formatCurrency($ps['total_cost']) ?></td>
                        <td class="text-end font-monospace fw-bold text-success"><?= formatCurrency($ps['profit']) ?></td>
                        <td class="text-end font-monospace"><?= number_format($m, 1) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="text-uppercase">Total:</td>
                    <td class="text-center"><?= $tSold ?></td>
                    <td class="text-end font-monospace"><?= formatCurrency($tRev) ?></td>
                    <td class="text-end font-monospace"><?= formatCurrency($tC) ?></td>
                    <td class="text-end font-monospace text-success"><?= formatCurrency($tProf) ?></td>
                    <td class="text-end font-monospace"><?= $tRev > 0 ? number_format(($tProf / $tRev) * 100, 1) : 0 ?>%</td>
                </tr>
            </tfoot>
        </table>

    <?php elseif ($reportType === 'category_sales'): ?>
        <!-- ================= CATEGORY SALES ================= -->
        <table class="table-print mb-4">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Type</th>
                    <th class="text-center">Distinct Titles</th>
                    <th class="text-center">Total Units Sold</th>
                    <th class="text-end">Gross Revenue (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    $tUnits = 0; $tRev = 0;
                    foreach ($categorySales as $cs): 
                        $tUnits += $cs['sold_qty']; $tRev += $cs['total_revenue'];
                ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($cs['category_name']) ?></td>
                        <td><?= ucfirst($cs['type']) ?></td>
                        <td class="text-center"><?= $cs['distinct_products'] ?></td>
                        <td class="text-center fw-bold"><?= (float)$cs['sold_qty'] ?></td>
                        <td class="text-end font-monospace fw-bold"><?= formatCurrency($cs['total_revenue']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-uppercase">Total Category Sales:</td>
                    <td class="text-center"><?= $tUnits ?></td>
                    <td class="text-end font-monospace"><?= formatCurrency($tRev) ?></td>
                </tr>
            </tfoot>
        </table>

    <?php elseif ($reportType === 'balances'): ?>
        <!-- ================= RECEIVABLES & PAYABLES ================= -->
        <h6 class="fw-bold text-dark mb-2">1. Outstanding Customer Receivables (Khata)</h6>
        <table class="table-print mb-4">
            <thead>
                <tr>
                    <th>Customer Name / Institution</th>
                    <th>Phone #</th>
                    <th>Category</th>
                    <th class="text-end">Outstanding Due Balance (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php $tRec = 0; foreach ($custReceivables as $cr): $tRec += $cr['current_balance']; ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($cr['name']) ?></td>
                        <td><?= htmlspecialchars($cr['phone']) ?></td>
                        <td><?= ucfirst($cr['customer_type']) ?></td>
                        <td class="text-end font-monospace fw-bold text-danger"><?= formatCurrency($cr['current_balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end text-uppercase">Total Customer Receivables:</td>
                    <td class="text-end font-monospace text-danger"><?= formatCurrency($tRec) ?></td>
                </tr>
            </tfoot>
        </table>

        <h6 class="fw-bold text-dark mb-2">2. Outstanding Supplier & Publisher Payables</h6>
        <table class="table-print mb-4">
            <thead>
                <tr>
                    <th>Supplier / Publisher Name</th>
                    <th>Company / Business</th>
                    <th>Phone #</th>
                    <th class="text-end">Payable Balance (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php $tPay = 0; foreach ($suppPayables as $sp): $tPay += $sp['current_balance']; ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($sp['name']) ?></td>
                        <td><?= htmlspecialchars($sp['company_name'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($sp['phone']) ?></td>
                        <td class="text-end font-monospace fw-bold text-danger"><?= formatCurrency($sp['current_balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end text-uppercase">Total Supplier Payables:</td>
                    <td class="text-end font-monospace text-danger"><?= formatCurrency($tPay) ?></td>
                </tr>
            </tfoot>
        </table>

    <?php elseif ($reportType === 'school_syllabus'): ?>
        <!-- ================= SCHOOL CLASS SYLLABUS ================= -->
        <table class="table-print mb-4">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 45%;">Book / Stationery Item</th>
                    <th style="width: 15%;">Publisher / Brand</th>
                    <th style="width: 10%;" class="text-center">Qty</th>
                    <th style="width: 10%;">Requirement</th>
                    <th style="width: 15%;" class="text-end">Price (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    $sn = 1; $totalPkg = 0;
                    foreach ($packageItems as $pi):
                        $price = $pi['custom_price'] !== null ? (float)$pi['custom_price'] : (float)$pi['retail_price'];
                        $lineTot = $price * (float)$pi['quantity_needed'];
                        $totalPkg += $lineTot;
                ?>
                    <tr>
                        <td class="text-center"><?= $sn++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($pi['title']) ?></strong>
                            <div class="text-muted" style="font-size: 10px;"><?= htmlspecialchars($pi['product_code']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($pi['publisher'] ?: '-') ?></td>
                        <td class="text-center fw-bold"><?= (float)$pi['quantity_needed'] ?></td>
                        <td><?= $pi['is_mandatory'] ? 'Mandatory' : 'Optional' ?></td>
                        <td class="text-end font-monospace fw-bold"><?= formatCurrency($lineTot) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-size: 14px;">
                    <td colspan="5" class="text-end text-uppercase">Complete Package Price per Student:</td>
                    <td class="text-end font-monospace text-primary"><?= formatCurrency($totalPkg) ?></td>
                </tr>
            </tfoot>
        </table>

    <?php elseif ($reportType === 'customer_statement' && !empty($currentCustomer)): ?>
        <!-- ================= CUSTOMER KHATA STATEMENT ================= -->
        <div class="meta-box mb-3 d-flex justify-content-between align-items-center">
            <div>
                <strong>Customer:</strong> <?= htmlspecialchars($currentCustomer['name']) ?> &bull;
                <strong>Type:</strong> <?= strtoupper($currentCustomer['customer_type']) ?> &bull;
                <strong>Phone:</strong> <?= htmlspecialchars($currentCustomer['phone'] ?: 'N/A') ?>
                <?php if ($currentCustomer['address']): ?>
                    <br><small class="text-muted"><strong>Address:</strong> <?= htmlspecialchars($currentCustomer['address']) ?></small>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <small class="text-muted">Current Outstanding Due</small>
                <h4 class="fw-bold text-danger mb-0"><?= formatCurrency($currentCustomer['current_balance']) ?></h4>
            </div>
        </div>
        <table class="table-print mb-4">
            <thead>
                <tr>
                    <th style="width: 15%;">Date & Time</th>
                    <th style="width: 15%;">Tx Type</th>
                    <th style="width: 15%;">Invoice #</th>
                    <th style="width: 25%;">Description / Particulars</th>
                    <th style="width: 10%;" class="text-end">Billed / Debit (+)</th>
                    <th style="width: 10%;" class="text-end">Paid / Credit (-)</th>
                    <th style="width: 10%;" class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($custLedger)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No transaction entries found for this customer.</td></tr>
                <?php else: ?>
                    <?php 
                        $tDeb = 0; $tCred = 0;
                        foreach ($custLedger as $cl): 
                            $tDeb += $cl['debit']; $tCred += $cl['credit'];
                    ?>
                        <tr>
                            <td><?= date('d M Y, h:i A', strtotime($cl['transaction_date'])) ?></td>
                            <td class="text-uppercase fw-semibold"><?= htmlspecialchars($cl['transaction_type']) ?></td>
                            <td class="font-monospace text-primary"><?= htmlspecialchars($cl['invoice_no'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($cl['description'] ?: '-') ?></td>
                            <td class="text-end font-monospace text-danger"><?= $cl['debit'] > 0 ? formatCurrency($cl['debit']) : '-' ?></td>
                            <td class="text-end font-monospace text-success"><?= $cl['credit'] > 0 ? formatCurrency($cl['credit']) : '-' ?></td>
                            <td class="text-end font-monospace fw-bold"><?= formatCurrency($cl['balance']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end text-uppercase">Ledger Totals:</td>
                    <td class="text-end font-monospace text-danger">+ <?= formatCurrency($tDeb ?? 0) ?></td>
                    <td class="text-end font-monospace text-success">- <?= formatCurrency($tCred ?? 0) ?></td>
                    <td class="text-end font-monospace text-primary"><?= formatCurrency($currentCustomer['current_balance']) ?></td>
                </tr>
            </tfoot>
        </table>

    <?php elseif ($reportType === 'supplier_statement' && !empty($currentSupplier)): ?>
        <!-- ================= SUPPLIER LEDGER STATEMENT ================= -->
        <div class="meta-box mb-3 d-flex justify-content-between align-items-center">
            <div>
                <strong>Supplier:</strong> <?= htmlspecialchars($currentSupplier['name']) ?>
                <?= $currentSupplier['company_name'] ? " &bull; <strong>Company:</strong> " . htmlspecialchars($currentSupplier['company_name']) : "" ?> &bull;
                <strong>Phone:</strong> <?= htmlspecialchars($currentSupplier['phone'] ?: 'N/A') ?>
                <?php if ($currentSupplier['address']): ?>
                    <br><small class="text-muted"><strong>Address:</strong> <?= htmlspecialchars($currentSupplier['address']) ?></small>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <small class="text-muted">Current Payable Balance</small>
                <h4 class="fw-bold text-danger mb-0"><?= formatCurrency($currentSupplier['current_balance']) ?></h4>
            </div>
        </div>
        <table class="table-print mb-4">
            <thead>
                <tr>
                    <th style="width: 15%;">Date & Time</th>
                    <th style="width: 15%;">Tx Type</th>
                    <th style="width: 15%;">Ref / Bill #</th>
                    <th style="width: 25%;">Description / Particulars</th>
                    <th style="width: 10%;" class="text-end">Paid / Debit (-)</th>
                    <th style="width: 10%;" class="text-end">Purchased / Credit (+)</th>
                    <th style="width: 10%;" class="text-end">Payable Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppLedger)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No transaction entries found for this supplier.</td></tr>
                <?php else: ?>
                    <?php 
                        $sDeb = 0; $sCred = 0;
                        foreach ($suppLedger as $sl): 
                            $sDeb += $sl['debit']; $sCred += $sl['credit'];
                    ?>
                        <tr>
                            <td><?= date('d M Y, h:i A', strtotime($sl['transaction_date'])) ?></td>
                            <td class="text-uppercase fw-semibold"><?= htmlspecialchars($sl['transaction_type']) ?></td>
                            <td class="font-monospace text-primary"><?= htmlspecialchars($sl['invoice_no'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($sl['description'] ?: '-') ?></td>
                            <td class="text-end font-monospace text-success"><?= $sl['debit'] > 0 ? formatCurrency($sl['debit']) : '-' ?></td>
                            <td class="text-end font-monospace text-danger"><?= $sl['credit'] > 0 ? formatCurrency($sl['credit']) : '-' ?></td>
                            <td class="text-end font-monospace fw-bold"><?= formatCurrency($sl['balance']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end text-uppercase">Ledger Totals:</td>
                    <td class="text-end font-monospace text-success">- <?= formatCurrency($sDeb ?? 0) ?></td>
                    <td class="text-end font-monospace text-danger">+ <?= formatCurrency($sCred ?? 0) ?></td>
                    <td class="text-end font-monospace text-primary"><?= formatCurrency($currentSupplier['current_balance']) ?></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <!-- Authorized Signature Sign-off Section -->
    <div class="signature-section">
        <div class="row">
            <div class="col-4">
                <div class="sig-line">Prepared By (Cashier / Operator)</div>
            </div>
            <div class="col-4">
                <div class="sig-line">Audited & Verified By</div>
            </div>
            <div class="col-4">
                <div class="sig-line">Authorized Signature (Manager / Owner)</div>
            </div>
        </div>
    </div>
</div>

<script>
window.onload = function() {
    // Smooth auto print prompt
    setTimeout(function() {
        window.print();
    }, 350);
};
</script>

</body>
</html>
