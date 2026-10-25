<?php
// includes/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentLang = getLanguage();
$userRole = $_SESSION['user']['role'] ?? '';
?>
<aside class="sidebar" id="mainSidebar">
    <!-- Brand -->
    <div class="brand-section justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-book-bookmark text-warning fs-3"></i>
            <div>
                <h5 class="mb-0"><?= $currentLang === 'ur' ? 'کتاب گھر' : 'Kitab Ghar' ?></h5>
                <small class="text-white-50" style="font-size: 0.72rem;"><?= $currentLang === 'ur' ? 'بک شاپ مینجمنٹ' : 'Bookshop POS & ERP' ?></small>
            </div>
        </div>
        <button class="btn btn-sm btn-link text-white-50 d-lg-none p-1 text-decoration-none" id="sidebarCloseBtn" type="button" aria-label="Close Sidebar">
            <i class="fa-solid fa-xmark fs-4"></i>
        </button>
    </div>

    <!-- Navigation links -->
    <div class="py-2">
        <div class="nav-category"><?= $currentLang === 'ur' ? 'مرکزی' : 'Main Menu' ?></div>

        <a href="index.php" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-pie"></i>
            <span><?= __t('dashboard') ?></span>
        </a>

        <a href="pos.php" class="nav-link <?= $currentPage === 'pos.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-cash-register text-warning"></i>
            <span><?= __t('pos') ?></span>
            <span class="badge bg-warning text-dark ms-auto">F2</span>
        </a>

        <div class="nav-category"><?= $currentLang === 'ur' ? 'کتب و فروخت' : 'Sales & Catalog' ?></div>

        <a href="books.php" class="nav-link <?= $currentPage === 'books.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-book-open"></i>
            <span><?= __t('books_catalog') ?></span>
        </a>

        <a href="sales.php" class="nav-link <?= $currentPage === 'sales.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span><?= __t('sales_history') ?></span>
        </a>

        <a href="schools.php" class="nav-link <?= $currentPage === 'schools.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-graduation-cap text-info"></i>
            <span><?= __t('schools') ?></span>
        </a>

        <div class="nav-category"><?= $currentLang === 'ur' ? 'اسٹاک و خریداری' : 'Inventory & Purchase' ?></div>

        <a href="inventory.php" class="nav-link <?= $currentPage === 'inventory.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-boxes-stacked"></i>
            <span><?= __t('inventory') ?></span>
        </a>

        <a href="purchases.php" class="nav-link <?= $currentPage === 'purchases.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-cart-flatbed"></i>
            <span><?= __t('purchases') ?></span>
        </a>

        <a href="suppliers.php" class="nav-link <?= $currentPage === 'suppliers.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-truck-field"></i>
            <span><?= __t('suppliers') ?></span>
        </a>

        <div class="nav-category"><?= $currentLang === 'ur' ? 'کھاتہ و مالیات' : 'Khata & Finance' ?></div>

        <a href="customers.php" class="nav-link <?= $currentPage === 'customers.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i>
            <span><?= __t('customers') ?></span>
        </a>

        <a href="accounting.php" class="nav-link <?= $currentPage === 'accounting.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-wallet"></i>
            <span><?= __t('accounting') ?></span>
        </a>

        <a href="reports.php" class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-line"></i>
            <span><?= __t('reports') ?></span>
        </a>

        <?php if ($userRole === 'admin'): ?>
            <div class="nav-category"><?= $currentLang === 'ur' ? 'انتظامیہ' : 'Administration' ?></div>

            <a href="users.php" class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-shield"></i>
                <span><?= __t('users') ?></span>
            </a>

            <a href="settings.php" class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-sliders"></i>
                <span><?= __t('settings') ?></span>
            </a>
        <?php endif; ?>

        <div class="pt-3 pb-4">
            <a href="logout.php" class="nav-link text-danger-emphasis">
                <i class="fa-solid fa-power-off text-danger"></i>
                <span><?= __t('logout') ?></span>
            </a>
        </div>
    </div>
</aside>
