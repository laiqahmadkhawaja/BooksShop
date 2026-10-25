<?php
// reports.php - Financial & Operational Reports & Analytics
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/includes/header.php';

$reportType = $_GET['report'] ?? 'pnl';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// 1. Profit & Loss Report Data
if ($reportType === 'pnl') {
    // Gross Sales Revenue
    $salesData = dbFetchOne("SELECT COUNT(*) as orders_count, SUM(subtotal) as gross_subtotal, 
                                    SUM(discount_amount) as total_discount, SUM(grand_total) as net_sales 
                             FROM sales 
                             WHERE DATE(sale_date) BETWEEN ? AND ? AND hold_status = 0", [$startDate, $endDate]);
    $netSales = (float)($salesData['net_sales'] ?? 0);
    $totalDiscount = (float)($salesData['total_discount'] ?? 0);

    // Cost of Goods Sold (COGS)
    $cogsData = dbFetchOne("SELECT SUM(si.quantity * si.cost_price) as total_cogs
                            FROM sale_items si
                            JOIN sales s ON s.id = si.sale_id
                            WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.hold_status = 0", [$startDate, $endDate]);
    $totalCogs = (float)($cogsData['total_cogs'] ?? 0);
    $grossProfit = $netSales - $totalCogs;

    // Operating Expenses by Category
    $expensesList = dbFetchAll("SELECT category_name, SUM(amount) as cat_amount 
                                FROM cash_transactions 
                                WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'expense'
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
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-chart-line text-primary me-2"></i> Financial & Inventory Reports</h4>
        <small class="text-muted">Generate Profit & Loss statements, product analytics, and customer balances</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- Report Navigation Tabs & Date Range -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white p-0">
        <ul class="nav nav-tabs card-header-tabs m-0">
            <li class="nav-item">
                <a class="nav-link <?= $reportType === 'pnl' ? 'active fw-bold text-success' : '' ?>" href="reports.php?report=pnl&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                    <i class="fa-solid fa-scale-balanced me-1"></i> Profit & Loss (P&L)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $reportType === 'product_sales' ? 'active fw-bold text-primary' : '' ?>" href="reports.php?report=product_sales&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                    <i class="fa-solid fa-book me-1"></i> Product-Wise Sales
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $reportType === 'category_sales' ? 'active fw-bold text-warning' : '' ?>" href="reports.php?report=category_sales&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                    <i class="fa-solid fa-chart-pie me-1"></i> Category Breakdown
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $reportType === 'balances' ? 'active fw-bold text-danger' : '' ?>" href="reports.php?report=balances">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Receivables & Payables
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body bg-light">
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
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-filter me-1"></i> Apply Date Filter</button>
            </div>
        </form>
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
                    <td class="ps-4">Gross Sales & Course Sets Invoiced</td>
                    <td class="text-end fw-semibold"><?= formatCurrency($salesData['gross_subtotal'] ?? 0) ?></td>
                </tr>
                <?php if ($totalDiscount > 0): ?>
                    <tr>
                        <td class="ps-4 text-danger">Less: Customer Discounts Allowed</td>
                        <td class="text-end text-danger">- <?= formatCurrency($totalDiscount) ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="fw-bold">
                    <td class="ps-4">Net Sales Revenue</td>
                    <td class="text-end text-primary"><?= formatCurrency($netSales) ?></td>
                </tr>

                <tr class="table-light">
                    <td colspan="2" class="fw-bold text-danger"><i class="fa-solid fa-boxes-stacked me-1"></i> 2. COST OF GOODS SOLD (COGS)</td>
                </tr>
                <tr>
                    <td class="ps-4">Cost of Textbooks, Books & Stationery Sold</td>
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
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
