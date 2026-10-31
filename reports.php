<?php
// reports.php - Financial, Accounting & Operational Reports & Analytics
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/includes/header.php';

$reportType = $_GET['report'] ?? 'pnl';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedAccountId = (int)($_GET['account_id'] ?? 1);

// All accounts for statement filter
$allAccounts = dbFetchAll("SELECT * FROM accounts WHERE status = 1 ORDER BY id ASC");

// 1. Profit & Loss Report Data
if ($reportType === 'pnl') {
    // Gross Sales Revenue
    $salesData = dbFetchOne("SELECT COUNT(*) as orders_count, SUM(subtotal) as gross_subtotal, 
                                    SUM(discount_amount) as total_discount, SUM(grand_total) as invoiced_sales 
                             FROM sales 
                             WHERE DATE(sale_date) BETWEEN ? AND ? AND hold_status = 0", [$startDate, $endDate]);
    $grossSubtotal = (float)($salesData['gross_subtotal'] ?? 0);
    $totalDiscount = (float)($salesData['total_discount'] ?? 0);
    $invoicedSales = (float)($salesData['invoiced_sales'] ?? 0);

    // Sales Returns & Exchanges in this period
    $returnsData = dbFetchOne("SELECT COALESCE(SUM(total_amount), 0) as total_returns, COUNT(*) as returns_count 
                               FROM returns 
                               WHERE return_type = 'sale_return' AND DATE(return_date) BETWEEN ? AND ?", [$startDate, $endDate]);
    $totalSalesReturns = (float)($returnsData['total_returns'] ?? 0);
    $returnsCount = (int)($returnsData['returns_count'] ?? 0);

    // Net Sales Revenue after Discounts & Returns
    $netSales = max(0, $invoicedSales - $totalSalesReturns);

    // Gross Cost of Goods Sold (COGS based on moving weighted average cost)
    $cogsData = dbFetchOne("SELECT SUM(si.quantity * si.cost_price) as gross_cogs
                            FROM sale_items si
                            JOIN sales s ON s.id = si.sale_id
                            WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.hold_status = 0", [$startDate, $endDate]);
    $grossCogs = (float)($cogsData['gross_cogs'] ?? 0);

    // Restocked Returns COGS (Restored into inventory at AVCO, reversing sold COGS)
    $returnCogsData = dbFetchOne("SELECT SUM(ri.quantity * ri.cost_price) as return_cogs
                                  FROM return_items ri
                                  JOIN returns r ON r.id = ri.return_id
                                  WHERE r.return_type = 'sale_return' 
                                    AND ri.condition_status = 'restockable'
                                    AND DATE(r.return_date) BETWEEN ? AND ?", [$startDate, $endDate]);
    $restockedReturnCogs = (float)($returnCogsData['return_cogs'] ?? 0);

    // Net COGS (Gross COGS minus Restocked returned inventory)
    $totalCogs = max(0, $grossCogs - $restockedReturnCogs);
    $grossProfit = $netSales - $totalCogs;

    // Operating Expenses by Category (excluding 'Sales Refund' cash transaction to prevent double deduction)
    $expensesList = dbFetchAll("SELECT category_name, SUM(amount) as cat_amount 
                                FROM cash_transactions 
                                WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'expense'
                                  AND category_name != 'Sales Refund'
                                GROUP BY category_name", [$startDate, $endDate]);
    $totalExpenses = 0;
    foreach ($expensesList as $exp) $totalExpenses += (float)$exp['cat_amount'];

    // Other Income
    $incomeData = dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'income'", [$startDate, $endDate]);
    $otherIncome = (float)($incomeData['tot'] ?? 0);

    // Net Profit
    $netProfit = ($grossProfit + $otherIncome) - $totalExpenses;
}

// 2. Product-Wise Sales Report Data
if ($reportType === 'product_sales') {
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

// 3. Category-Wise Sales
if ($reportType === 'category_sales') {
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

// 4. Receivables & Payables Aging
if ($reportType === 'balances') {
    $custReceivables = dbFetchAll("SELECT name, phone, customer_type, current_balance, credit_limit FROM customers WHERE current_balance > 0 ORDER BY current_balance DESC");
    $suppPayables = dbFetchAll("SELECT name, company_name, phone, current_balance FROM suppliers WHERE current_balance > 0 ORDER BY current_balance DESC");
}

// 4.5. Stock Valuation Report (Shop + Warehouses)
if ($reportType === 'stock_valuation') {
    $shopVal = dbFetchOne("SELECT 
        COUNT(*) as skus,
        COALESCE(SUM(current_stock), 0) as units,
        COALESCE(SUM(current_stock * purchase_price), 0) as cost_val,
        COALESCE(SUM(current_stock * retail_price), 0) as retail_val
        FROM products WHERE status = 1");

    $warehousesVal = getWarehouses(false);

    $totalCompanyUnits = (float)($shopVal['units'] ?? 0);
    $totalCompanyCost = (float)($shopVal['cost_val'] ?? 0);
    $totalCompanyRetail = (float)($shopVal['retail_val'] ?? 0);

    foreach ($warehousesVal as $w) {
        $totalCompanyUnits += (float)$w['total_units'];
        $totalCompanyCost += (float)$w['total_cost_value'];
        $totalCompanyRetail += (float)$w['total_retail_value'];
    }

    $topStockItems = dbFetchAll("SELECT p.*, c.name as category_name,
        COALESCE((SELECT SUM(quantity) FROM warehouse_stock WHERE product_id = p.id), 0) as wh_stock,
        (p.current_stock + COALESCE((SELECT SUM(quantity) FROM warehouse_stock WHERE product_id = p.id), 0)) as total_stock,
        ((p.current_stock + COALESCE((SELECT SUM(quantity) FROM warehouse_stock WHERE product_id = p.id), 0)) * p.purchase_price) as total_cost_valuation
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status = 1
        ORDER BY total_cost_valuation DESC LIMIT 30");
}

// 5. NEW: Trial Balance Report
if ($reportType === 'trial_balance') {
    // Current Inventory Valuation at Cost
    $stockValRow = dbFetchOne("SELECT SUM(current_stock * purchase_price) as tot_cost, SUM(current_stock * retail_price) as tot_retail FROM products WHERE status = 1");
    $inventoryValuation = (float)($stockValRow['tot_cost'] ?? 0);

    // Customer Receivables (Total Asset)
    $totReceivables = (float)(dbFetchOne("SELECT SUM(current_balance) as tot FROM customers WHERE current_balance > 0")['tot'] ?? 0);

    // Supplier Payables (Total Liability)
    $totPayables = (float)(dbFetchOne("SELECT SUM(current_balance) as tot FROM suppliers WHERE current_balance > 0")['tot'] ?? 0);

    // Liquid Accounts
    $cashAccounts = dbFetchAll("SELECT * FROM accounts WHERE status = 1 ORDER BY account_type ASC, id ASC");

    // Period Revenue & Expenses
    $trialSales = (float)(dbFetchOne("SELECT SUM(grand_total) as tot FROM sales WHERE DATE(sale_date) BETWEEN ? AND ? AND hold_status = 0", [$startDate, $endDate])['tot'] ?? 0);
    $trialCogs = (float)(dbFetchOne("SELECT SUM(si.quantity * si.cost_price) as tot FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.hold_status = 0", [$startDate, $endDate])['tot'] ?? 0);
    $trialExpenses = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'expense'", [$startDate, $endDate])['tot'] ?? 0);
    $trialIncome = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'income'", [$startDate, $endDate])['tot'] ?? 0);
}

// 6. NEW: Account Statement / Bank & Cash Ledger
if ($reportType === 'account_statement') {
    $currentAccount = dbFetchOne("SELECT * FROM accounts WHERE id = ?", [$selectedAccountId]) ?: ($allAccounts[0] ?? null);

    // Calculate opening balance before start date
    // Account opening balance + prior inflows - prior outflows
    $priorInflows = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions 
                                       WHERE account_id = ? AND DATE(transaction_date) < ? 
                                       AND transaction_type IN ('income', 'customer_payment', 'sale')", [$selectedAccountId, $startDate])['tot'] ?? 0);
    // Transferred in as recipient
    $priorTransfersIn = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions 
                                            WHERE to_account_id = ? AND DATE(transaction_date) < ? 
                                            AND transaction_type = 'transfer'", [$selectedAccountId, $startDate])['tot'] ?? 0);
    $priorOutflows = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions 
                                        WHERE account_id = ? AND DATE(transaction_date) < ? 
                                        AND transaction_type IN ('expense', 'purchase', 'supplier_payment', 'transfer')", [$selectedAccountId, $startDate])['tot'] ?? 0);

    // Current account base
    $accountOpeningBal = (float)($currentAccount['balance'] ?? 0);
    // Period transactions
    $statementTransactions = dbFetchAll("SELECT ct.*, a.account_name, ta.account_name as to_account_name, u.full_name as user_name
                                          FROM cash_transactions ct
                                          JOIN accounts a ON a.id = ct.account_id
                                          LEFT JOIN accounts ta ON ta.id = ct.to_account_id
                                          LEFT JOIN users u ON u.id = ct.created_by
                                          WHERE (ct.account_id = ? OR ct.to_account_id = ?) 
                                          AND DATE(ct.transaction_date) BETWEEN ? AND ?
                                          ORDER BY ct.transaction_date ASC, ct.id ASC", [$selectedAccountId, $selectedAccountId, $startDate, $endDate]);
}

// 7. NEW: Expense Breakdown & Analytics
if ($reportType === 'expense_report') {
    // Total expenses & category breakdown
    $expenseStats = dbFetchOne("SELECT COUNT(*) as vouchers_count, SUM(amount) as total_expenses, MAX(amount) as max_expense, AVG(amount) as avg_expense 
                                FROM cash_transactions 
                                WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'expense'", [$startDate, $endDate]);
    $totalExpAmount = (float)($expenseStats['total_expenses'] ?? 0);

    $expenseCategoriesBreakdown = dbFetchAll("SELECT category_name, COUNT(*) as cnt, SUM(amount) as total_cat 
                                              FROM cash_transactions 
                                              WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'expense'
                                              GROUP BY category_name 
                                              ORDER BY total_cat DESC", [$startDate, $endDate]);

    // Detailed transactions
    $expenseListDetailed = dbFetchAll("SELECT ct.*, a.account_name, u.full_name as user_name
                                       FROM cash_transactions ct
                                       JOIN accounts a ON a.id = ct.account_id
                                       LEFT JOIN users u ON u.id = ct.created_by
                                       WHERE DATE(ct.transaction_date) BETWEEN ? AND ? AND ct.transaction_type = 'expense'
                                       ORDER BY ct.transaction_date DESC, ct.id DESC", [$startDate, $endDate]);
}

// 8. NEW: Daily Cash Closing & Denominations
if ($reportType === 'cash_closing') {
    // Register Drawer is Account ID 1
    $drawerAcc = dbFetchOne("SELECT * FROM accounts WHERE id = 1") ?: ($allAccounts[0] ?? null);
    $drawerId = $drawerAcc['id'] ?? 1;

    // Prior Cash Inflows & Outflows before selectedDate to compute opening of the day
    $priorIn = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) < ? AND transaction_type IN ('income', 'sale', 'customer_payment')", [$drawerId, $selectedDate])['tot'] ?? 0);
    $priorTransIn = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE to_account_id = ? AND DATE(transaction_date) < ? AND transaction_type = 'transfer'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $priorOut = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) < ? AND transaction_type IN ('expense', 'purchase', 'supplier_payment', 'transfer')", [$drawerId, $selectedDate])['tot'] ?? 0);
    $dayOpeningCash = max(0, ($priorIn + $priorTransIn) - $priorOut);

    // Day Cash Inflows broken down
    $daySalesCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'sale'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $dayCustReceiptsCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'customer_payment'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $dayIncomeCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'income'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $dayTransferInCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE to_account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'transfer'", [$drawerId, $selectedDate])['tot'] ?? 0);

    // Day Cash Outflows broken down
    $dayPurchasesCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'purchase'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $daySuppPaymentsCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'supplier_payment'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $dayExpensesCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'expense'", [$drawerId, $selectedDate])['tot'] ?? 0);
    $dayTransfersOutCash = (float)(dbFetchOne("SELECT SUM(amount) as tot FROM cash_transactions WHERE account_id = ? AND DATE(transaction_date) = ? AND transaction_type = 'transfer'", [$drawerId, $selectedDate])['tot'] ?? 0);

    $totalDayInflows = $daySalesCash + $dayCustReceiptsCash + $dayIncomeCash + $dayTransferInCash;
    $totalDayOutflows = $dayPurchasesCash + $daySuppPaymentsCash + $dayExpensesCash + $dayTransfersOutCash;
    $systemClosingCash = ($dayOpeningCash + $totalDayInflows) - $totalDayOutflows;
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-chart-line text-primary me-2"></i> Financial, Accounting & Operational Reports</h4>
        <small class="text-muted">Generate Profit & Loss, Trial Balance, Bank Statements, Expense Analytics, and Daily Cash Closing</small>
    </div>
    <div class="d-flex gap-2">
        <a href="print_report.php?report=<?= urlencode($reportType) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&date=<?= urlencode($selectedDate) ?>&account_id=<?= $selectedAccountId ?>" target="_blank" class="btn btn-outline-dark btn-sm shadow-sm">
            <i class="fa-solid fa-print me-1"></i> Print Report (New Page)
        </a>
    </div>
</div>

<!-- Report Navigation Tabs -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white p-0">
        <ul class="nav nav-tabs card-header-tabs m-0 flex-nowrap overflow-auto">
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $reportType === 'pnl' ? 'active fw-bold text-success' : '' ?>" href="reports.php?report=pnl&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                    <i class="fa-solid fa-scale-balanced me-1"></i> Profit & Loss
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $reportType === 'trial_balance' ? 'active fw-bold text-primary' : '' ?>" href="reports.php?report=trial_balance&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                    <i class="fa-solid fa-book-bookmark me-1"></i> Trial Balance
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $reportType === 'account_statement' ? 'active fw-bold text-info' : '' ?>" href="reports.php?report=account_statement&account_id=<?= $selectedAccountId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                    <i class="fa-solid fa-building-columns me-1"></i> Bank/Cash Statement
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $reportType === 'expense_report' ? 'active fw-bold text-danger' : '' ?>" href="reports.php?report=expense_report&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                    <i class="fa-solid fa-receipt me-1"></i> Expense Analytics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $reportType === 'cash_closing' ? 'active fw-bold text-dark' : '' ?>" href="reports.php?report=cash_closing&date=<?= $selectedDate ?>">
                    <i class="fa-solid fa-calculator me-1"></i> Cash Closing Slip
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $reportType === 'product_sales' ? 'active fw-bold text-primary' : '' ?>" href="reports.php?report=product_sales&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                    <i class="fa-solid fa-book me-1"></i> Product Sales
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $reportType === 'category_sales' ? 'active fw-bold text-warning' : '' ?>" href="reports.php?report=category_sales&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                    <i class="fa-solid fa-chart-pie me-1"></i> Category Sales
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $reportType === 'balances' ? 'active fw-bold text-danger' : '' ?>" href="reports.php?report=balances">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Receivables & Payables
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $reportType === 'stock_valuation' ? 'active fw-bold text-success' : '' ?>" href="reports.php?report=stock_valuation">
                    <i class="fa-solid fa-boxes-stacked me-1"></i> Stock Valuation (Shop & Godowns)
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body bg-light py-2">
        <?php if ($reportType === 'cash_closing'): ?>
            <!-- Single Date Filter for Cash Closing -->
            <form method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="report" value="cash_closing">
                <div class="col-md-6 col-lg-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fa-solid fa-calendar me-1"></i> Closing Date</span>
                        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-dark btn-sm w-100"><i class="fa-solid fa-check me-1"></i> Load Closing Report</button>
                </div>
            </form>
        <?php elseif ($reportType === 'account_statement'): ?>
            <!-- Account & Date Range Filter for Statement -->
            <form method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="report" value="account_statement">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fa-solid fa-wallet me-1"></i> Account</span>
                        <select name="account_id" class="form-select">
                            <?php foreach ($allAccounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>" <?= $acc['id'] === $selectedAccountId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($acc['account_name']) ?> (<?= formatCurrency($acc['balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">From</span>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">To</span>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                </div>
            </form>
        <?php elseif ($reportType !== 'balances'): ?>
            <!-- Standard Date Range Filter -->
            <form method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="report" value="<?= htmlspecialchars($reportType) ?>">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">From</span>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">To</span>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-filter me-1"></i> Apply Filter</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($reportType === 'pnl'): ?>
<!-- ================= PROFIT & LOSS REPORT ================= -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-scale-balanced text-success me-2"></i> Profit & Loss Statement</h5>
            <small class="text-muted">Period: <?= date('d M, Y', strtotime($startDate)) ?> to <?= date('d M, Y', strtotime($endDate)) ?></small>
        </div>
        <div class="text-end">
            <span class="small text-muted">Net Profit for Period:</span>
            <h4 class="fw-bold <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?> mb-0"><?= formatCurrency($netProfit) ?></h4>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 70%;">Particulars / Account Head</th>
                    <th style="width: 30%;" class="text-end">Amount (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <!-- Trading Account -->
                <tr class="table-light">
                    <td colspan="2" class="fw-bold text-primary"><i class="fa-solid fa-cart-shopping me-1"></i> 1. REVENUE FROM SALES</td>
                </tr>
                <tr>
                    <td class="ps-4">Gross Sales Invoiced</td>
                    <td class="text-end fw-semibold"><?= formatCurrency($grossSubtotal) ?></td>
                </tr>
                <?php if ($totalDiscount > 0): ?>
                    <tr>
                        <td class="ps-4 text-danger">Less: Customer Discounts Allowed</td>
                        <td class="text-end text-danger">- <?= formatCurrency($totalDiscount) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($totalSalesReturns > 0): ?>
                    <tr>
                        <td class="ps-4 text-danger">Less: Sales Returns & Exchanges (<?= $returnsCount ?> returned)</td>
                        <td class="text-end text-danger">- <?= formatCurrency($totalSalesReturns) ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="fw-bold">
                    <td class="ps-4">Net Sales Revenue</td>
                    <td class="text-end text-primary"><?= formatCurrency($netSales) ?></td>
                </tr>

                <tr class="table-light">
                    <td colspan="2" class="fw-bold text-danger"><i class="fa-solid fa-boxes-stacked me-1"></i> 2. COST OF GOODS SOLD (COGS - AVCO)</td>
                </tr>
                <tr>
                    <td class="ps-4">Gross Cost of Sold Goods (AVCO)</td>
                    <td class="text-end text-danger">- <?= formatCurrency($grossCogs) ?></td>
                </tr>
                <?php if ($restockedReturnCogs > 0): ?>
                    <tr>
                        <td class="ps-4 text-success">Less: Restocked Returns Inventory Cost (Restored to stock)</td>
                        <td class="text-end text-success">+ <?= formatCurrency($restockedReturnCogs) ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="fw-bold">
                    <td class="ps-4">Net Cost of Goods Sold</td>
                    <td class="text-end text-danger">- <?= formatCurrency($totalCogs) ?></td>
                </tr>
                <tr class="table-primary fw-bold">
                    <td>GROSS PROFIT (Net Sales - COGS)</td>
                    <td class="text-end fs-6 text-primary"><?= formatCurrency($grossProfit) ?></td>
                </tr>

                <!-- Operating Expenses -->
                <tr class="table-light">
                    <td colspan="2" class="fw-bold text-danger"><i class="fa-solid fa-file-invoice-dollar me-1"></i> 3. OPERATING EXPENSES</td>
                </tr>
                <?php if (empty($expensesList)): ?>
                    <tr>
                        <td class="ps-4 text-muted">No operating expenses recorded in this period.</td>
                        <td class="text-end">Rs. 0.00</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expensesList as $exp): ?>
                        <tr>
                            <td class="ps-4"><?= htmlspecialchars($exp['category_name']) ?></td>
                            <td class="text-end text-danger">- <?= formatCurrency($exp['cat_amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr class="fw-bold">
                    <td class="ps-4">Total Operating Expenses</td>
                    <td class="text-end text-danger">- <?= formatCurrency($totalExpenses) ?></td>
                </tr>

                <!-- Other Income -->
                <?php if ($otherIncome > 0): ?>
                    <tr class="table-light">
                        <td colspan="2" class="fw-bold text-success"><i class="fa-solid fa-plus me-1"></i> 4. OTHER INCOME</td>
                    </tr>
                    <tr>
                        <td class="ps-4">Photocopy, Capital & Other Incomes</td>
                        <td class="text-end text-success">+ <?= formatCurrency($otherIncome) ?></td>
                    </tr>
                <?php endif; ?>

                <!-- Final Net Profit -->
                <tr class="table-success fw-bold border-top border-3">
                    <td class="fs-5">NET PROFIT / (LOSS)</td>
                    <td class="text-end fs-4 <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($netProfit) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($reportType === 'trial_balance'): ?>
<!-- ================= TRIAL BALANCE REPORT ================= -->
<?php
    $totalDebits = 0;
    $totalCredits = 0;
?>
<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-book-bookmark text-primary me-2"></i> Comprehensive Trial Balance</h5>
            <small class="text-muted">As of <?= date('d M, Y', strtotime($endDate)) ?> (Activity Period: <?= date('d M, Y', strtotime($startDate)) ?> to <?= date('d M, Y', strtotime($endDate)) ?>)</small>
        </div>
        <div>
            <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
                <i class="fa-solid fa-check-circle me-1"></i> Double-Entry Verification
            </span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light text-uppercase small">
                    <tr>
                        <th style="width: 50%;">Account Title / Head of Account</th>
                        <th style="width: 25%;" class="text-end text-primary">Debit (Rs.)</th>
                        <th style="width: 25%;" class="text-end text-success">Credit (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- ASSETS (Debits) -->
                    <tr class="table-light"><td colspan="3" class="fw-bold text-dark"><i class="fa-solid fa-vault me-1"></i> Current Assets & Cash</td></tr>
                    <?php foreach ($cashAccounts as $ca): ?>
                        <?php 
                            $bal = (float)$ca['balance'];
                            if ($bal >= 0) {
                                $totalDebits += $bal;
                            } else {
                                $totalCredits += abs($bal);
                            }
                        ?>
                        <tr>
                            <td class="ps-4">
                                <strong><?= htmlspecialchars($ca['account_name']) ?></strong>
                                <?php if (!empty($ca['bank_name'])): ?>
                                    <span class="text-muted small ms-1">(<?= htmlspecialchars($ca['bank_name']) ?> <?= htmlspecialchars($ca['account_number']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end font-monospace"><?= $bal >= 0 ? formatCurrency($bal) : '-' ?></td>
                            <td class="text-end font-monospace text-danger"><?= $bal < 0 ? formatCurrency(abs($bal)) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Receivables -->
                    <?php $totalDebits += $totReceivables; ?>
                    <tr>
                        <td class="ps-4">
                            <strong>Accounts Receivable (Customer Accounts)</strong>
                            <div class="text-muted small">Total outstanding balance due from credit buyers</div>
                        </td>
                        <td class="text-end font-monospace"><?= formatCurrency($totReceivables) ?></td>
                        <td class="text-end font-monospace">-</td>
                    </tr>

                    <!-- Inventory Valuation at Cost -->
                    <?php $totalDebits += $inventoryValuation; ?>
                    <tr>
                        <td class="ps-4">
                            <strong>Merchandise Inventory at Purchase Cost</strong>
                            <div class="text-muted small">Current physical inventory valuation</div>
                        </td>
                        <td class="text-end font-monospace"><?= formatCurrency($inventoryValuation) ?></td>
                        <td class="text-end font-monospace">-</td>
                    </tr>

                    <!-- LIABILITIES (Credits) -->
                    <tr class="table-light"><td colspan="3" class="fw-bold text-dark"><i class="fa-solid fa-hand-holding-dollar me-1"></i> Current Liabilities</td></tr>
                    <?php $totalCredits += $totPayables; ?>
                    <tr>
                        <td class="ps-4">
                            <strong>Accounts Payable (Suppliers & Vendors Khata)</strong>
                            <div class="text-muted small">Outstanding bills payable to suppliers and vendors</div>
                        </td>
                        <td class="text-end font-monospace">-</td>
                        <td class="text-end font-monospace"><?= formatCurrency($totPayables) ?></td>
                    </tr>

                    <!-- REVENUE & COST OF SALES -->
                    <tr class="table-light"><td colspan="3" class="fw-bold text-dark"><i class="fa-solid fa-chart-pie me-1"></i> Period Trading & Operations</td></tr>
                    <?php $totalCredits += $trialSales; ?>
                    <tr>
                        <td class="ps-4"><strong>Sales Revenue (Turnover)</strong></td>
                        <td class="text-end font-monospace">-</td>
                        <td class="text-end font-monospace"><?= formatCurrency($trialSales) ?></td>
                    </tr>
                    <?php $totalDebits += $trialCogs; ?>
                    <tr>
                        <td class="ps-4"><strong>Cost of Goods Sold (COGS)</strong></td>
                        <td class="text-end font-monospace"><?= formatCurrency($trialCogs) ?></td>
                        <td class="text-end font-monospace">-</td>
                    </tr>
                    <?php $totalDebits += $trialExpenses; ?>
                    <tr>
                        <td class="ps-4"><strong>Operating Expenses (Rent, Salaries, Utilities, Tea)</strong></td>
                        <td class="text-end font-monospace"><?= formatCurrency($trialExpenses) ?></td>
                        <td class="text-end font-monospace">-</td>
                    </tr>
                    <?php if ($trialIncome > 0): ?>
                        <?php $totalCredits += $trialIncome; ?>
                        <tr>
                            <td class="ps-4"><strong>Other Direct Incomes & Service Fees</strong></td>
                            <td class="text-end font-monospace">-</td>
                            <td class="text-end font-monospace"><?= formatCurrency($trialIncome) ?></td>
                        </tr>
                    <?php endif; ?>

                    <!-- Balancing / Equity Line -->
                    <?php 
                        $diff = $totalDebits - $totalCredits;
                        if ($diff > 0) {
                            $equityCredit = $diff;
                            $totalCredits += $equityCredit;
                        } else {
                            $equityDebit = abs($diff);
                            $totalDebits += $equityDebit;
                        }
                    ?>
                    <tr class="table-warning">
                        <td class="ps-4">
                            <strong>Owner's Equity & Retained Earnings / Balancing Head</strong>
                            <div class="text-muted small">Balancing proprietor capital and retained store net worth</div>
                        </td>
                        <td class="text-end font-monospace"><?= isset($equityDebit) ? formatCurrency($equityDebit) : '-' ?></td>
                        <td class="text-end font-monospace"><?= isset($equityCredit) ? formatCurrency($equityCredit) : '-' ?></td>
                    </tr>

                    <!-- Totals Row -->
                    <tr class="table-dark fs-6 fw-bold">
                        <td class="text-uppercase ps-3">Total Trial Balance</td>
                        <td class="text-end text-primary-subtle font-monospace"><?= formatCurrency($totalDebits) ?></td>
                        <td class="text-end text-success-subtle font-monospace"><?= formatCurrency($totalCredits) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'account_statement'): ?>
<!-- ================= ACCOUNT STATEMENT / BANK LEDGER ================= -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <div class="row align-items-center">
            <div class="col-md-7">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge <?= $currentAccount['account_type'] === 'cash' ? 'bg-success' : 'bg-primary' ?> text-uppercase">
                        <?= htmlspecialchars($currentAccount['account_type']) ?>
                    </span>
                    <h5 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($currentAccount['account_name']) ?></h5>
                </div>
                <div class="text-muted small mt-1">
                    <?php if (!empty($currentAccount['bank_name'])): ?>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($currentAccount['bank_name']) ?></span> &bull; 
                    <?php endif; ?>
                    Account #: <span class="font-monospace fw-semibold"><?= htmlspecialchars($currentAccount['account_number'] ?: 'N/A') ?></span>
                    <?php if (!empty($currentAccount['branch_name'])): ?>
                        &bull; Branch: <?= htmlspecialchars($currentAccount['branch_name']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-5 text-md-end mt-2 mt-md-0">
                <small class="text-muted">Current Ledger Balance</small>
                <h4 class="fw-bold <?= $currentAccount['balance'] >= 0 ? 'text-success' : 'text-danger' ?> mb-0">
                    <?= formatCurrency($currentAccount['balance']) ?>
                </h4>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-uppercase">
                    <tr>
                        <th>Date & Time</th>
                        <th>Reference / Voucher #</th>
                        <th>Type / Category</th>
                        <th>Description / Payee</th>
                        <th class="text-end text-success">Deposit / Inflow (+)</th>
                        <th class="text-end text-danger">Withdrawal / Outflow (-)</th>
                        <th class="text-end">Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        $running = $accountOpeningBal;
                        $totInflowPeriod = 0;
                        $totOutflowPeriod = 0;
                    ?>
                    <tr class="table-light fw-bold">
                        <td colspan="4" class="ps-3"><i class="fa-solid fa-flag me-1"></i> Opening Balance prior to <?= date('d M, Y', strtotime($startDate)) ?></td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end font-monospace"><?= formatCurrency($running) ?></td>
                    </tr>
                    <?php if (empty($statementTransactions)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No transactions found for this account in the selected date range.</td></tr>
                    <?php else: ?>
                        <?php foreach ($statementTransactions as $st): ?>
                            <?php
                                // Check if this account was sender or receiver
                                $isReceiver = ($st['to_account_id'] == $selectedAccountId);
                                $isSender = ($st['account_id'] == $selectedAccountId);

                                if ($isReceiver) {
                                    $inflow = (float)$st['amount'];
                                    $outflow = 0;
                                    $running += $inflow;
                                    $totInflowPeriod += $inflow;
                                } else {
                                    $isInflowType = in_array($st['transaction_type'], ['income', 'customer_payment', 'sale']);
                                    if ($isInflowType) {
                                        $inflow = (float)$st['amount'];
                                        $outflow = 0;
                                        $running += $inflow;
                                        $totInflowPeriod += $inflow;
                                    } else {
                                        $inflow = 0;
                                        $outflow = (float)$st['amount'];
                                        $running -= $outflow;
                                        $totOutflowPeriod += $outflow;
                                    }
                                }
                            ?>
                            <tr>
                                <td><?= date('d M Y, h:i A', strtotime($st['transaction_date'])) ?></td>
                                <td class="font-monospace text-primary fw-semibold"><?= htmlspecialchars($st['reference_no'] ?: '-') ?></td>
                                <td>
                                    <span class="badge <?= $inflow > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> text-uppercase">
                                        <?= htmlspecialchars($st['transaction_type']) ?>
                                    </span>
                                    <div class="fw-semibold text-dark mt-1"><?= htmlspecialchars($st['category_name'] ?: '-') ?></div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($st['description'] ?: '-') ?>
                                    <?php if ($st['to_account_name'] && $isSender): ?>
                                        <div class="text-muted small">&rarr; Transferred to <?= htmlspecialchars($st['to_account_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-success font-monospace">
                                    <?= $inflow > 0 ? formatCurrency($inflow) : '-' ?>
                                </td>
                                <td class="text-end fw-bold text-danger font-monospace">
                                    <?= $outflow > 0 ? formatCurrency($outflow) : '-' ?>
                                </td>
                                <td class="text-end fw-bold font-monospace <?= $running >= 0 ? 'text-dark' : 'text-danger' ?>">
                                    <?= formatCurrency($running) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end text-uppercase">Period Totals:</td>
                        <td class="text-end text-success font-monospace">+ <?= formatCurrency($totInflowPeriod) ?></td>
                        <td class="text-end text-danger font-monospace">- <?= formatCurrency($totOutflowPeriod) ?></td>
                        <td class="text-end text-primary font-monospace"><?= formatCurrency($running) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'expense_report'): ?>
<!-- ================= EXPENSE ANALYTICS REPORT ================= -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card p-3 shadow-sm bg-white border-start border-danger border-4">
            <small class="text-muted text-uppercase fw-semibold">Total Expenses</small>
            <h4 class="fw-bold text-danger mb-0 mt-1"><?= formatCurrency($totalExpAmount) ?></h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 shadow-sm bg-white border-start border-warning border-4">
            <small class="text-muted text-uppercase fw-semibold">Expense Vouchers</small>
            <h4 class="fw-bold text-warning-emphasis mb-0 mt-1"><?= (int)($expenseStats['vouchers_count'] ?? 0) ?></h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 shadow-sm bg-white border-start border-primary border-4">
            <small class="text-muted text-uppercase fw-semibold">Largest Single Voucher</small>
            <h4 class="fw-bold text-primary mb-0 mt-1"><?= formatCurrency($expenseStats['max_expense'] ?? 0) ?></h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 shadow-sm bg-white border-start border-info border-4">
            <small class="text-muted text-uppercase fw-semibold">Average Voucher</small>
            <h4 class="fw-bold text-info mb-0 mt-1"><?= formatCurrency($expenseStats['avg_expense'] ?? 0) ?></h4>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Category Breakdown (40%) -->
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-chart-pie text-danger me-2"></i> Expenses by Category</h6>
            </div>
            <div class="card-body">
                <?php if (empty($expenseCategoriesBreakdown)): ?>
                    <p class="text-muted text-center py-4">No expenses found for this period.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($expenseCategoriesBreakdown as $ecb): ?>
                            <?php 
                                $pct = $totalExpAmount > 0 ? (($ecb['total_cat'] / $totalExpAmount) * 100) : 0;
                            ?>
                            <div class="list-group-item px-0 py-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-dark small"><?= htmlspecialchars($ecb['category_name']) ?></strong>
                                    <span class="fw-bold text-danger small"><?= formatCurrency($ecb['total_cat']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center small text-muted">
                                    <span><?= $ecb['cnt'] ?> voucher(s)</span>
                                    <span><?= number_format($pct, 1) ?>%</span>
                                </div>
                                <div class="progress mt-1" style="height: 6px;">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $pct ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Detailed Ledger (60%) -->
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i> Detailed Expense Vouchers Ledger</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light text-uppercase">
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Account</th>
                                <th>Ref #</th>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expenseListDetailed)): ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">No expense vouchers in selected date range.</td></tr>
                            <?php else: ?>
                                <?php foreach ($expenseListDetailed as $eld): ?>
                                    <tr>
                                        <td><?= date('d M, Y', strtotime($eld['transaction_date'])) ?></td>
                                        <td><span class="badge bg-danger-subtle text-danger"><?= htmlspecialchars($eld['category_name']) ?></span></td>
                                        <td><?= htmlspecialchars($eld['account_name']) ?></td>
                                        <td class="font-monospace text-primary"><?= htmlspecialchars($eld['reference_no'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($eld['description'] ?: '-') ?></td>
                                        <td class="text-end fw-bold text-danger"><?= formatCurrency($eld['amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'cash_closing'): ?>
<!-- ================= DAILY CASH CLOSING & DENOMINATIONS ================= -->
<div class="row g-3">
    <!-- LEFT: System Ledger Cash Flow Reconciliation -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-calculator text-primary me-2"></i> Register Drawer Daily Closing Slip</h6>
                <span class="badge bg-light text-dark border"><?= date('l, d F Y', strtotime($selectedDate)) ?></span>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0 align-middle">
                    <tbody>
                        <tr class="table-light">
                            <td class="fw-bold text-dark ps-3">1. OPENING CASH IN DRAWER</td>
                            <td class="text-end fw-bold font-monospace pe-3"><?= formatCurrency($dayOpeningCash) ?></td>
                        </tr>

                        <!-- INFLOWS -->
                        <tr class="table-success-subtle">
                            <td colspan="2" class="fw-bold text-success ps-3"><i class="fa-solid fa-plus-circle me-1"></i> CASH INFLOWS RECEIVED TODAY</td>
                        </tr>
                        <tr>
                            <td class="ps-4">Cash Sales Collected (Counter POS)</td>
                            <td class="text-end font-monospace text-success pe-3">+ <?= formatCurrency($daySalesCash) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-4">Customer Khata / Credit Collections in Cash</td>
                            <td class="text-end font-monospace text-success pe-3">+ <?= formatCurrency($dayCustReceiptsCash) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-4">Other Cash Income / Capital Injections</td>
                            <td class="text-end font-monospace text-success pe-3">+ <?= formatCurrency($dayIncomeCash) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-4">Cash Transferred into Drawer from Bank</td>
                            <td class="text-end font-monospace text-success pe-3">+ <?= formatCurrency($dayTransferInCash) ?></td>
                        </tr>
                        <tr class="fw-bold table-light">
                            <td class="ps-3">Total Cash Inflows</td>
                            <td class="text-end font-monospace text-success pe-3">+ <?= formatCurrency($totalDayInflows) ?></td>
                        </tr>

                        <!-- OUTFLOWS -->
                        <tr class="table-danger-subtle">
                            <td colspan="2" class="fw-bold text-danger ps-3"><i class="fa-solid fa-minus-circle me-1"></i> CASH OUTFLOWS PAID TODAY</td>
                        </tr>
                        <tr>
                            <td class="ps-4">Cash Purchases Paid Directly</td>
                            <td class="text-end font-monospace text-danger pe-3">- <?= formatCurrency($dayPurchasesCash) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-4">Supplier / Publisher Khata Paid in Cash</td>
                            <td class="text-end font-monospace text-danger pe-3">- <?= formatCurrency($daySuppPaymentsCash) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-4">Operating Expenses Paid from Drawer</td>
                            <td class="text-end font-monospace text-danger pe-3">- <?= formatCurrency($dayExpensesCash) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-4">Cash Transferred / Deposited to Bank</td>
                            <td class="text-end font-monospace text-danger pe-3">- <?= formatCurrency($dayTransfersOutCash) ?></td>
                        </tr>
                        <tr class="fw-bold table-light">
                            <td class="ps-3">Total Cash Outflows</td>
                            <td class="text-end font-monospace text-danger pe-3">- <?= formatCurrency($totalDayOutflows) ?></td>
                        </tr>

                        <!-- NET CLOSING -->
                        <tr class="table-primary fw-bold fs-6">
                            <td class="ps-3">EXPECTED SYSTEM CLOSING CASH</td>
                            <td class="text-end font-monospace pe-3" id="expectedClosingDisplay"><?= formatCurrency($systemClosingCash) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT: Physical Denomination Counter -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white py-3">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-money-bill-wave text-warning me-2"></i> Physical Currency Denominations</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Count physical notes and coins in the cash drawer to verify reconciliation.</p>

                <?php 
                    $denoms = [5000, 1000, 500, 100, 50, 20, 10];
                ?>
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th>Note</th>
                                <th style="width: 100px;">Count</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($denoms as $d): ?>
                                <tr>
                                    <td class="fw-bold text-dark">Rs. <?= $d ?></td>
                                    <td>
                                        <input type="number" min="0" class="form-control form-control-sm text-center denom-input" data-value="<?= $d ?>" value="0" oninput="calculatePhysicalCash()">
                                    </td>
                                    <td class="text-end font-monospace text-muted denom-subtotal" id="sub_<?= $d ?>">Rs. 0</td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td class="fw-bold text-dark">Coins & Loose</td>
                                <td>
                                    <input type="number" min="0" step="1" id="coinsInput" class="form-control form-control-sm text-center" value="0" oninput="calculatePhysicalCash()">
                                </td>
                                <td class="text-end font-monospace text-muted" id="sub_coins">Rs. 0</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Reconciliation Summary Box -->
                <div class="p-3 bg-light rounded border">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-muted text-uppercase fw-semibold">Physical Counted Cash:</span>
                        <h5 class="fw-bold text-primary mb-0 font-monospace" id="totalPhysicalCount">Rs. 0.00</h5>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 border-top pt-2">
                        <span class="small text-muted text-uppercase fw-semibold">System Expected:</span>
                        <span class="fw-bold text-dark font-monospace"><?= formatCurrency($systemClosingCash) ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-2">
                        <span class="small fw-bold">Difference (Shortage / Excess):</span>
                        <h6 class="fw-bold mb-0 font-monospace" id="cashDifference">Rs. 0.00</h6>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="print_report.php?report=cash_closing&date=<?= urlencode($selectedDate) ?>" id="printClosingBtn" target="_blank" class="btn btn-outline-dark btn-sm w-100" onclick="updatePrintClosingUrl()">
                        <i class="fa-solid fa-print me-1"></i> Print Daily Closing Slip (New Page)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const systemExpectedCash = <?= (float)$systemClosingCash ?>;

function calculatePhysicalCash() {
    let total = 0;
    document.querySelectorAll('.denom-input').forEach(input => {
        const noteVal = parseFloat(input.dataset.value) || 0;
        const count = parseInt(input.value) || 0;
        const sub = noteVal * count;
        total += sub;
        const subEl = document.getElementById('sub_' + noteVal);
        if (subEl) subEl.textContent = 'Rs. ' + sub.toLocaleString();
    });

    const coins = parseFloat(document.getElementById('coinsInput').value) || 0;
    total += coins;
    document.getElementById('sub_coins').textContent = 'Rs. ' + coins.toLocaleString();

    document.getElementById('totalPhysicalCount').textContent = 'Rs. ' + total.toLocaleString(undefined, {minimumFractionDigits: 2});

    const diff = total - systemExpectedCash;
    const diffEl = document.getElementById('cashDifference');
    if (Math.abs(diff) < 0.01) {
        diffEl.textContent = 'Rs. 0.00 (Balanced)';
        diffEl.className = 'fw-bold mb-0 font-monospace text-success';
    } else if (diff > 0) {
        diffEl.textContent = '+ Rs. ' + diff.toLocaleString(undefined, {minimumFractionDigits: 2}) + ' (Excess)';
        diffEl.className = 'fw-bold mb-0 font-monospace text-primary';
    } else {
        diffEl.textContent = '- Rs. ' + Math.abs(diff).toLocaleString(undefined, {minimumFractionDigits: 2}) + ' (Shortage)';
        diffEl.className = 'fw-bold mb-0 font-monospace text-danger';
    }
    updatePrintClosingUrl();
}

function updatePrintClosingUrl() {
    const btn = document.getElementById('printClosingBtn');
    if (!btn) return;
    const params = new URLSearchParams();
    params.set('report', 'cash_closing');
    params.set('date', '<?= urlencode($selectedDate) ?>');
    
    document.querySelectorAll('.denom-input').forEach(input => {
        const noteVal = input.dataset.value;
        const count = parseInt(input.value) || 0;
        params.set('d_' + noteVal, count);
    });
    const coins = parseFloat(document.getElementById('coinsInput')?.value) || 0;
    params.set('d_coins', coins);
    params.set('counted', '1');
    
    btn.href = 'print_report.php?' + params.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    updatePrintClosingUrl();
});
</script>

<?php elseif ($reportType === 'product_sales'): ?>
<!-- ================= PRODUCT-WISE SALES ================= -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-book text-primary me-2"></i> Product-Wise Sales & Profitability Analysis</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-uppercase">
                    <tr>
                        <th>Product / Title</th>
                        <th>Type</th>
                        <th class="text-center">Units Sold</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">COGS (Cost)</th>
                        <th class="text-end">Gross Profit</th>
                        <th class="text-end">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productSales)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No product sales in selected date range.</td></tr>
                    <?php else: ?>
                        <?php foreach ($productSales as $ps): ?>
                            <?php 
                                $margin = $ps['total_revenue'] > 0 ? ($ps['profit'] / $ps['total_revenue']) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong class="text-dark"><?= htmlspecialchars($ps['title']) ?></strong>
                                    <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($ps['product_code']) ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= ucfirst($ps['product_type']) ?></span></td>
                                <td class="text-center fw-bold"><?= (float)$ps['sold_qty'] ?></td>
                                <td class="text-end fw-semibold"><?= formatCurrency($ps['total_revenue']) ?></td>
                                <td class="text-end text-muted"><?= formatCurrency($ps['total_cost']) ?></td>
                                <td class="text-end fw-bold text-success"><?= formatCurrency($ps['profit']) ?></td>
                                <td class="text-end fw-bold text-primary"><?= number_format($margin, 1) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'category_sales'): ?>
<!-- ================= CATEGORY SALES ================= -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-chart-pie text-warning me-2"></i> Category-Wise Sales Breakdown</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th>Category Name</th>
                        <th>Type</th>
                        <th class="text-center">Distinct Titles</th>
                        <th class="text-center">Total Units Sold</th>
                        <th class="text-end">Gross Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categorySales)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No sales in this date range.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categorySales as $cs): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($cs['category_name']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= ucfirst($cs['type']) ?></span></td>
                                <td class="text-center"><?= $cs['distinct_products'] ?></td>
                                <td class="text-center fw-bold"><?= (float)$cs['sold_qty'] ?></td>
                                <td class="text-end fw-bold text-primary"><?= formatCurrency($cs['total_revenue']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'balances'): ?>
<!-- ================= RECEIVABLES & PAYABLES ================= -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 border-start border-danger border-4">
                <h6 class="mb-0 fw-bold text-danger"><i class="fa-solid fa-users me-2"></i> Outstanding Customer Receivables (Khata)</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Customer / School</th>
                            <th>Phone</th>
                            <th class="text-end">Due Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($custReceivables)): ?>
                            <tr><td colspan="3" class="text-center py-3 text-muted">All customer balances cleared!</td></tr>
                        <?php else: ?>
                            <?php foreach ($custReceivables as $cr): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($cr['name']) ?></strong>
                                        <div class="text-muted" style="font-size: 0.7rem;"><?= ucfirst($cr['customer_type']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($cr['phone']) ?></td>
                                    <td class="text-end fw-bold text-danger"><?= formatCurrency($cr['current_balance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 border-start border-warning border-4">
                <h6 class="mb-0 fw-bold text-warning-emphasis"><i class="fa-solid fa-truck-field me-2"></i> Outstanding Supplier Payables</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Supplier / Publisher</th>
                            <th>Phone</th>
                            <th class="text-end">Payable Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppPayables)): ?>
                            <tr><td colspan="3" class="text-center py-3 text-muted">All supplier bills cleared!</td></tr>
                        <?php else: ?>
                            <?php foreach ($suppPayables as $sp): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($sp['name']) ?></strong>
                                        <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($sp['company_name'] ?: '') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($sp['phone']) ?></td>
                                    <td class="text-end fw-bold text-danger"><?= formatCurrency($sp['current_balance']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'stock_valuation'): ?>
<!-- ================= STOCK VALUATION (SHOP & WAREHOUSES) ================= -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-primary border-4 shadow-sm bg-white h-100">
            <div class="small text-muted fw-bold">CONSOLIDATED STOCK VALUE (COST)</div>
            <h4 class="fw-bold text-primary mb-0 mt-1"><?= formatCurrency($totalCompanyCost) ?></h4>
            <span class="small text-muted">Shop + Godowns Capital Asset</span>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-success border-4 shadow-sm bg-white h-100">
            <div class="small text-muted fw-bold">TOTAL RETAIL VALUATION</div>
            <h4 class="fw-bold text-success mb-0 mt-1"><?= formatCurrency($totalCompanyRetail) ?></h4>
            <span class="small text-muted">Total Potential Gross Sales</span>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-info border-4 shadow-sm bg-white h-100">
            <div class="small text-muted fw-bold">POTENTIAL GROSS PROFIT</div>
            <h4 class="fw-bold text-info mb-0 mt-1"><?= formatCurrency($totalCompanyRetail - $totalCompanyCost) ?></h4>
            <span class="small text-muted"><?= $totalCompanyCost > 0 ? round((($totalCompanyRetail - $totalCompanyCost) / $totalCompanyCost) * 100, 1) : 0 ?>% ROI on Inventory</span>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-warning border-4 shadow-sm bg-white h-100">
            <div class="small text-muted fw-bold">TOTAL UNITS ACROSS BUSINESS</div>
            <h4 class="fw-bold text-dark mb-0 mt-1"><?= number_format($totalCompanyUnits, 0) ?> Units</h4>
            <span class="small text-muted">Shop: <?= number_format((float)$shopVal['units']) ?> | Godowns: <?= number_format($totalCompanyUnits - (float)$shopVal['units']) ?></span>
        </div>
    </div>
</div>

<!-- Location Breakdown Summary Table -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-warehouse text-warning me-2"></i> Location-Wise Valuation Breakdown
        </h6>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print Report
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-uppercase">
                    <tr>
                        <th>Storage Facility / Location</th>
                        <th class="text-center">Stored SKUs</th>
                        <th class="text-center">Total Units</th>
                        <th class="text-end">Cost Valuation</th>
                        <th class="text-end">Retail Valuation</th>
                        <th class="text-end">Potential Profit</th>
                        <th class="text-center">Share of Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Main Shop -->
                    <tr class="table-light-subtle">
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="rounded p-2 bg-light text-primary me-2 border">
                                    <i class="fa-solid fa-store"></i>
                                </div>
                                <div>
                                    <strong class="text-dark">🏬 Main Shop / Storefront Counter</strong>
                                    <div class="text-muted" style="font-size: 0.72rem;">Active POS Counter Inventory</div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center"><span class="badge bg-light text-dark border"><?= number_format($shopVal['skus']) ?> SKUs</span></td>
                        <td class="text-center fw-bold fs-6 text-primary"><?= number_format((float)$shopVal['units'], 0) ?></td>
                        <td class="text-end font-monospace fw-bold text-success"><?= formatCurrency((float)$shopVal['cost_val']) ?></td>
                        <td class="text-end font-monospace"><?= formatCurrency((float)$shopVal['retail_val']) ?></td>
                        <td class="text-end font-monospace text-info"><?= formatCurrency((float)$shopVal['retail_val'] - (float)$shopVal['cost_val']) ?></td>
                        <td class="text-center font-monospace">
                            <?= $totalCompanyUnits > 0 ? round(((float)$shopVal['units'] / $totalCompanyUnits) * 100, 1) : 0 ?>%
                        </td>
                    </tr>

                    <!-- Warehouses -->
                    <?php foreach ($warehousesVal as $wh): 
                        $whProfit = (float)$wh['total_retail_value'] - (float)$wh['total_cost_value'];
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded p-2 bg-light text-warning me-2 border">
                                        <i class="fa-solid fa-warehouse"></i>
                                    </div>
                                    <div>
                                        <a href="warehouse_view.php?id=<?= $wh['id'] ?>" class="text-decoration-none fw-bold text-dark">
                                            🏭 <?= htmlspecialchars($wh['name']) ?>
                                        </a>
                                        <div class="text-muted font-monospace" style="font-size: 0.72rem;">
                                            Code: <?= htmlspecialchars($wh['warehouse_code']) ?>
                                            <?= $wh['manager_name'] ? ' &bull; In-Charge: ' . htmlspecialchars($wh['manager_name']) : '' ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center"><span class="badge bg-light text-dark border"><?= number_format($wh['total_skus']) ?> SKUs</span></td>
                            <td class="text-center fw-bold fs-6 text-primary"><?= number_format((float)$wh['total_units'], 0) ?></td>
                            <td class="text-end font-monospace fw-bold text-success"><?= formatCurrency((float)$wh['total_cost_value']) ?></td>
                            <td class="text-end font-monospace"><?= formatCurrency((float)$wh['total_retail_value']) ?></td>
                            <td class="text-end font-monospace text-info"><?= formatCurrency($whProfit) ?></td>
                            <td class="text-center font-monospace">
                                <?= $totalCompanyUnits > 0 ? round(((float)$wh['total_units'] / $totalCompanyUnits) * 100, 1) : 0 ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>TOTAL COMPANY ASSETS (CONSOLIDATED):</td>
                        <td class="text-center">—</td>
                        <td class="text-center text-primary fs-6"><?= number_format($totalCompanyUnits, 0) ?></td>
                        <td class="text-end text-success font-monospace fs-6"><?= formatCurrency($totalCompanyCost) ?></td>
                        <td class="text-end font-monospace fs-6"><?= formatCurrency($totalCompanyRetail) ?></td>
                        <td class="text-end text-info font-monospace fs-6"><?= formatCurrency($totalCompanyRetail - $totalCompanyCost) ?></td>
                        <td class="text-center">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Top Capital Inventory Items -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-ranking-star text-primary me-2"></i> Highest Valuation Items in Inventory (Top 30)
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-uppercase">
                    <tr>
                        <th>Product Title</th>
                        <th>Category</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Retail Price</th>
                        <th class="text-center">Shop Qty</th>
                        <th class="text-center">Godown Qty</th>
                        <th class="text-center">Total Qty</th>
                        <th class="text-end">Total Capital Asset (Cost)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topStockItems as $item): ?>
                        <tr>
                            <td>
                                <strong class="text-dark"><?= htmlspecialchars($item['title']) ?></strong>
                                <small class="text-muted font-monospace d-block">Code: <?= htmlspecialchars($item['product_code']) ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['category_name'] ?: 'General') ?></span></td>
                            <td class="text-end font-monospace"><?= formatCurrency((float)$item['purchase_price']) ?></td>
                            <td class="text-end font-monospace text-primary fw-semibold"><?= formatCurrency((float)$item['retail_price']) ?></td>
                            <td class="text-center"><?= number_format((float)$item['current_stock'], 0) ?></td>
                            <td class="text-center"><?= number_format((float)$item['wh_stock'], 0) ?></td>
                            <td class="text-center fw-bold text-dark fs-6"><?= number_format((float)$item['total_stock'], 0) ?></td>
                            <td class="text-end font-monospace fw-bold text-success"><?= formatCurrency((float)$item['total_cost_valuation']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
