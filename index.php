<?php
// index.php - Comprehensive Executive & Operational Dashboard
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$todayDate = date('Y-m-d');
$firstDayMonth = date('Y-m-01');

// 1. KPI Cards data
$totalActiveProducts = dbFetchOne("SELECT COUNT(*) as cnt FROM products WHERE status = 1")['cnt'] ?? 0;
$totalDepartments = dbFetchOne("SELECT COUNT(*) as cnt FROM product_types")['cnt'] ?? 0;
$totalCategories = dbFetchOne("SELECT COUNT(*) as cnt FROM categories")['cnt'] ?? 0;

// Stock quantity & valuation
$stockSummary = dbFetchOne("SELECT 
    SUM(current_stock) as total_qty, 
    SUM(current_stock * purchase_price) as total_cost_value,
    SUM(current_stock * retail_price) as total_retail_value
    FROM products WHERE status = 1");
$totalStockQty = (float)($stockSummary['total_qty'] ?? 0);
$totalCostValuation = (float)($stockSummary['total_cost_value'] ?? 0);
$totalRetailValuation = (float)($stockSummary['total_retail_value'] ?? 0);

// Today's Sales
$todaySalesData = dbFetchOne("SELECT 
    COUNT(*) as total_orders,
    SUM(grand_total) as total_sales,
    SUM(paid_amount) as cash_received,
    SUM(due_amount) as credit_sales
    FROM sales WHERE DATE(sale_date) = ? AND hold_status = 0", [$todayDate]);
$todaySales = (float)($todaySalesData['total_sales'] ?? 0);
$todayOrders = (int)($todaySalesData['total_orders'] ?? 0);
$todayCredit = (float)($todaySalesData['credit_sales'] ?? 0);

// Today's COGS for Profit calculation
$todayCogsData = dbFetchOne("SELECT SUM(si.quantity * si.cost_price) as cogs
    FROM sale_items si 
    JOIN sales s ON s.id = si.sale_id 
    WHERE DATE(s.sale_date) = ? AND s.hold_status = 0", [$todayDate]);
$todayCogs = (float)($todayCogsData['cogs'] ?? 0);
$todayGrossProfit = $todaySales - $todayCogs;

// Today's Expenses
$todayExpensesData = dbFetchOne("SELECT SUM(amount) as total_expense 
    FROM cash_transactions 
    WHERE DATE(transaction_date) = ? AND transaction_type = 'expense'", [$todayDate]);
$todayExpenses = (float)($todayExpensesData['total_expense'] ?? 0);
$todayNetProfit = $todayGrossProfit - $todayExpenses;

// Receivables & Payables
$customerReceivables = dbFetchOne("SELECT SUM(current_balance) as rec FROM customers WHERE current_balance > 0")['rec'] ?? 0;
$supplierPayables = dbFetchOne("SELECT SUM(current_balance) as pay FROM suppliers WHERE current_balance > 0")['pay'] ?? 0;

// Cash in Counter Drawer
$drawerCash = dbFetchOne("SELECT balance FROM accounts WHERE id = 1")['balance'] ?? 0;

// Low-stock products list
$lowStockItems = dbFetchAll("SELECT * FROM products WHERE current_stock <= min_stock_alert AND status = 1 ORDER BY current_stock ASC LIMIT 8");

// Recent sales
$recentSales = dbFetchAll("SELECT s.*, c.name as customer_name 
    FROM sales s 
    LEFT JOIN customers c ON c.id = s.customer_id 
    WHERE s.hold_status = 0 
    ORDER BY s.id DESC LIMIT 8");

// Top Selling Products
$topSelling = dbFetchAll("SELECT p.title, p.product_code, p.product_type, SUM(si.quantity) as sold_qty, SUM(si.subtotal) as total_amount
    FROM sale_items si
    JOIN products p ON p.id = si.product_id
    GROUP BY p.id
    ORDER BY sold_qty DESC LIMIT 6");

// Monthly Chart Data (Last 6 Months)
$chartMonths = [];
$chartSales = [];
$chartProfits = [];
for ($i = 5; $i >= 0; $i--) {
    $mStart = date('Y-m-01', strtotime("-$i months"));
    $mEnd = date('Y-m-t', strtotime("-$i months"));
    $mLabel = date('M Y', strtotime("-$i months"));
    $chartMonths[] = $mLabel;

    $sData = dbFetchOne("SELECT SUM(grand_total) as monthly_sales FROM sales WHERE DATE(sale_date) BETWEEN ? AND ? AND hold_status = 0", [$mStart, $mEnd]);
    $mSales = (float)($sData['monthly_sales'] ?? 0);
    $chartSales[] = $mSales;

    $cData = dbFetchOne("SELECT SUM(si.quantity * si.cost_price) as monthly_cogs FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.hold_status = 0", [$mStart, $mEnd]);
    $mCogs = (float)($cData['monthly_cogs'] ?? 0);

    $eData = dbFetchOne("SELECT SUM(amount) as monthly_exp FROM cash_transactions WHERE DATE(transaction_date) BETWEEN ? AND ? AND transaction_type = 'expense'", [$mStart, $mEnd]);
    $mExp = (float)($eData['monthly_exp'] ?? 0);

    $chartProfits[] = ($mSales - $mCogs) - $mExp;
}
?>

<!-- Quick Action Shortcuts Bar -->
<div class="row g-2 mb-4">
    <div class="col-12">
        <div class="card p-3 bg-white shadow-sm border-0">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark"><i class="fa-solid fa-bolt text-warning me-1"></i> Quick Actions:</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="pos.php" class="btn btn-pos btn-sm shadow-sm">
                        <i class="fa-solid fa-cash-register me-1"></i> New POS Bill (F2)
                    </a>
                    <a href="product_form.php" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-plus-circle me-1"></i> Add Product
                    </a>
                    <a href="purchases.php?action=new" class="btn btn-success btn-sm">
                        <i class="fa-solid fa-cart-arrow-down me-1"></i> Record Purchase
                    </a>
                    <a href="categories.php" class="btn btn-info text-white btn-sm">
                        <i class="fa-solid fa-tags me-1"></i> Categories & Depts
                    </a>
                    <a href="accounting.php?action=expense" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-receipt me-1"></i> Add Expense
                    </a>
                    <a href="customers.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-hand-holding-dollar me-1"></i> Receive Payment
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Primary KPI Stat Cards -->
<div class="row g-3 mb-4">
    <!-- Today's Sales -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card bg-gradient-blue shadow-sm">
            <div class="stat-label"><i class="fa-solid fa-calendar-day me-1"></i> <?= __t('today_sales') ?></div>
            <h3><?= formatCurrency($todaySales) ?></h3>
            <div class="small opacity-75">
                <span><?= $todayOrders ?> Invoices</span> &bull; 
                <span>Credit: <?= formatCurrency($todayCredit) ?></span>
            </div>
            <i class="fa-solid fa-cash-register stat-icon"></i>
        </div>
    </div>

    <!-- Today's Net Profit -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card bg-gradient-green shadow-sm">
            <div class="stat-label"><i class="fa-solid fa-chart-line me-1"></i> <?= __t('today_profit') ?> (Net)</div>
            <h3><?= formatCurrency($todayNetProfit) ?></h3>
            <div class="small opacity-75">
                <span>Gross: <?= formatCurrency($todayGrossProfit) ?></span> &bull; 
                <span>Exp: <?= formatCurrency($todayExpenses) ?></span>
            </div>
            <i class="fa-solid fa-sack-dollar stat-icon"></i>
        </div>
    </div>

    <!-- Customer Receivables -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card bg-gradient-orange shadow-sm">
            <div class="stat-label"><i class="fa-solid fa-users me-1"></i> <?= __t('customer_receivables') ?></div>
            <h3><?= formatCurrency($customerReceivables) ?></h3>
            <div class="small opacity-75">
                <a href="customers.php?filter=due" class="text-white text-decoration-underline">View Active Khata Ledgers &rarr;</a>
            </div>
            <i class="fa-solid fa-hand-holding-dollar stat-icon"></i>
        </div>
    </div>

    <!-- Supplier Payables -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card bg-gradient-red shadow-sm">
            <div class="stat-label"><i class="fa-solid fa-truck-field me-1"></i> <?= __t('supplier_payables') ?></div>
            <h3><?= formatCurrency($supplierPayables) ?></h3>
            <div class="small opacity-75">
                <a href="suppliers.php?filter=due" class="text-white text-decoration-underline">View Pending Supplier Bills &rarr;</a>
            </div>
            <i class="fa-solid fa-file-invoice-dollar stat-icon"></i>
        </div>
    </div>
</div>

<!-- Secondary Stats (Inventory Valuation & Catalog) -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-primary border-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-secondary small fw-semibold">Inventory Valuation (Cost)</span>
                    <h5 class="fw-bold mb-0 text-primary mt-1"><?= formatCurrency($totalCostValuation) ?></h5>
                    <span class="text-muted small">Retail: <?= formatCurrency($totalRetailValuation) ?></span>
                </div>
                <div class="p-3 bg-light rounded text-primary"><i class="fa-solid fa-warehouse fs-4"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-success border-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-secondary small fw-semibold">Cash in Register Drawer</span>
                    <h5 class="fw-bold mb-0 text-success mt-1"><?= formatCurrency($drawerCash) ?></h5>
                    <span class="text-muted small">Main Counter Drawer</span>
                </div>
                <div class="p-3 bg-light rounded text-success"><i class="fa-solid fa-money-bill-wave fs-4"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-info border-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-secondary small fw-semibold">Active Catalog Products</span>
                    <h5 class="fw-bold mb-0 text-info mt-1"><?= number_format($totalActiveProducts) ?> Items</h5>
                    <span class="text-muted small"><?= number_format($totalCategories) ?> Categories &bull; <?= number_format($totalDepartments) ?> Depts</span>
                </div>
                <div class="p-3 bg-light rounded text-info"><i class="fa-solid fa-boxes-stacked fs-4"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-start border-warning border-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-secondary small fw-semibold">Total Stock in Hand</span>
                    <h5 class="fw-bold mb-0 text-warning mt-1"><?= number_format($totalStockQty) ?> Units</h5>
                    <span class="text-muted small"><?= $lowStockCount > 0 ? "<span class='text-danger fw-semibold'><i class='fa-solid fa-triangle-exclamation me-1'></i>{$lowStockCount} Low stock alerts</span>" : "<span class='text-success'>All stock healthy</span>" ?></span>
                </div>
                <div class="p-3 bg-light rounded text-warning"><i class="fa-solid fa-layer-group fs-4"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Charts & Best Sellers -->
<div class="row g-3 mb-4">
    <!-- Monthly Sales & Profit Chart -->
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-chart-area text-primary me-2"></i> Monthly Sales & Net Profit Trends</h6>
                <span class="badge bg-light text-secondary border">Last 6 Months</span>
            </div>
            <div class="card-body">
                <canvas id="salesProfitChart" height="120"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-fire text-danger me-2"></i> Best-Selling Products</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($topSelling)): ?>
                        <li class="list-group-item text-center text-muted py-4">No sales recorded yet.</li>
                    <?php else: ?>
                        <?php foreach ($topSelling as $tp): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                <div>
                                    <div class="fw-semibold small text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($tp['title']) ?>">
                                        <?= htmlspecialchars($tp['title']) ?>
                                    </div>
                                    <span class="badge bg-light text-secondary border" style="font-size: 0.7rem;"><?= htmlspecialchars($tp['product_code']) ?> &bull; <?= ucfirst($tp['product_type']) ?></span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-success small"><?= (float)$tp['sold_qty'] ?> Sold</div>
                                    <span class="text-muted" style="font-size: 0.75rem;"><?= formatCurrency($tp['total_amount']) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Low-Stock Alerts & Recent Transactions -->
<div class="row g-3">
    <!-- Low Stock Emergency Alert -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100 border-top border-danger border-3">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Low-Stock & Out-of-Stock Alerts</h6>
                <a href="inventory.php?filter=low_stock" class="small text-decoration-none">View All &rarr;</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Item / Title</th>
                                <th>Stock</th>
                                <th>Min Alert</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lowStockItems)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">All inventory stock levels are healthy!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lowStockItems as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-truncate" style="max-width: 170px;" title="<?= htmlspecialchars($item['title']) ?>">
                                                <?= htmlspecialchars($item['title']) ?>
                                            </div>
                                            <span class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($item['product_code']) ?> &bull; <?= htmlspecialchars($item['rack_location'] ?? 'Rack') ?></span>
                                        </td>
                                        <td>
                                            <?php if ($item['current_stock'] <= 0): ?>
                                                <span class="badge bg-danger">OUT OF STOCK (0)</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><?= (float)$item['current_stock'] ?> <?= htmlspecialchars($item['unit']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= (float)$item['min_stock_alert'] ?></td>
                                        <td>
                                            <a href="purchases.php?action=new&product_id=<?= $item['id'] ?>" class="btn btn-outline-primary btn-xs py-1 px-2" style="font-size: 0.75rem;" title="Order from Supplier">
                                                <i class="fa-solid fa-cart-plus"></i> Reorder
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Sales List -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Recent POS Sales</h6>
                <a href="sales.php" class="small text-decoration-none">View All Sales &rarr;</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th class="text-end">Print</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSales)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">No recent sales found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentSales as $sale): ?>
                                    <tr>
                                        <td>
                                            <strong><a href="sales.php?invoice=<?= urlencode($sale['invoice_no']) ?>" class="text-decoration-none"><?= htmlspecialchars($sale['invoice_no']) ?></a></strong>
                                            <div class="text-muted" style="font-size: 0.7rem;"><?= date('d M, h:i A', strtotime($sale['sale_date'])) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in Customer') ?></td>
                                        <td class="fw-bold"><?= formatCurrency($sale['grand_total']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.7rem;">
                                                <?= htmlspecialchars($sale['payment_method']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($sale['payment_status'] === 'paid'): ?>
                                                <span class="badge bg-success">PAID</span>
                                            <?php elseif ($sale['payment_status'] === 'partial'): ?>
                                                <span class="badge bg-warning text-dark">PARTIAL</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">CREDIT / DUE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="print_invoice.php?id=<?= $sale['id'] ?>&type=thermal" target="_blank" class="btn btn-outline-secondary btn-sm py-0 px-2" title="Thermal 80mm Receipt">
                                                <i class="fa-solid fa-receipt"></i>
                                            </a>
                                            <a href="print_invoice.php?id=<?= $sale['id'] ?>&type=a4" target="_blank" class="btn btn-outline-primary btn-sm py-0 px-2" title="A4 Invoice">
                                                <i class="fa-solid fa-file-lines"></i>
                                            </a>
                                        </td>
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

<!-- Chart.js for Dashboard Visualizations -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Render Chart.js
    const ctx = document.getElementById('salesProfitChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartMonths) ?>,
            datasets: [
                {
                    label: 'Gross Sales (Rs.)',
                    data: <?= json_encode($chartSales) ?>,
                    borderColor: '#2980b9',
                    backgroundColor: 'rgba(41, 128, 185, 0.1)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2
                },
                {
                    label: 'Net Profit (Rs.)',
                    data: <?= json_encode($chartProfits) ?>,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'Rs. ' + value.toLocaleString(); }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
