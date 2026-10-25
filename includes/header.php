<?php
// includes/header.php
require_once __DIR__ . '/../config/app.php';
requireAuth();

if (isset($_GET['set_lang'])) {
    setLanguage($_GET['set_lang']);
    $redirectUrl = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: $redirectUrl");
    exit;
}

$user = authUser();
$currentLang = getLanguage();
$shopName = getSetting('shop_name', 'Kitab Ghar & Stationery');
$shopNameUrdu = getSetting('shop_name_urdu', 'کتاب گھر اینڈ اسٹیشنری مارٹ');
$flash = getFlash();

// Low stock counter for header bell
$lowStockCount = dbFetchOne("SELECT COUNT(*) as cnt FROM products WHERE current_stock <= min_stock_alert AND status = 1")['cnt'] ?? 0;
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $currentLang === 'ur' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($currentLang === 'ur' ? $shopNameUrdu : $shopName) ?> - <?= htmlspecialchars($pageTitle ?? 'Management System') ?></title>
    <!-- Bootstrap 5 CSS -->
    <?php if ($currentLang === 'ur'): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet">
    <?php else: ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <?php endif; ?>
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= $currentLang === 'ur' ? 'lang-ur' : '' ?>">

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <!-- Topbar -->
    <header class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggleBtn">
                <i class="fa-solid fa-bars"></i>
            </button>
            <span class="fw-semibold text-secondary d-none d-md-inline">
                <i class="fa-solid fa-store me-1 text-primary"></i> <?= htmlspecialchars($shopName) ?>
            </span>
        </div>

        <div class="d-flex align-items-center gap-3">
            <!-- Fast POS Button -->
            <a href="pos.php" class="btn btn-sm btn-pos shadow-sm px-3">
                <i class="fa-solid fa-cash-register me-1"></i> <strong><?= __t('pos') ?></strong> (F2)
            </a>

            <!-- Low Stock Bell Alert -->
            <a href="inventory.php?filter=low_stock" class="btn btn-sm btn-outline-danger position-relative" title="Low Stock Items">
                <i class="fa-solid fa-bell"></i>
                <?php if ($lowStockCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $lowStockCount ?>
                    </span>
                <?php endif; ?>
            </a>

            <!-- Language Switcher -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fa-solid fa-globe me-1"></i> <?= $currentLang === 'ur' ? 'اردو (Urdu)' : 'English' ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item <?= $currentLang === 'en' ? 'active' : '' ?>" href="?set_lang=en"><i class="fa-solid fa-check me-2 <?= $currentLang === 'en' ? '' : 'text-transparent' ?>"></i> English (LTR)</a></li>
                    <li><a class="dropdown-item <?= $currentLang === 'ur' ? 'active' : '' ?>" href="?set_lang=ur"><i class="fa-solid fa-check me-2 <?= $currentLang === 'ur' ? '' : 'text-transparent' ?>"></i> اردو - پاکستان (RTL)</a></li>
                </ul>
            </div>

            <!-- User Menu -->
            <div class="dropdown">
                <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                    <i class="fa-solid fa-user-circle text-primary fs-5"></i>
                    <span class="d-none d-sm-inline fw-semibold"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></span>
                    <span class="badge bg-primary text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($user['role'] ?? '') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><h6 class="dropdown-header"><?= htmlspecialchars($user['full_name'] ?? 'User') ?> (<?= htmlspecialchars($user['username'] ?? '') ?>)</h6></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fa-solid fa-gear me-2 text-secondary"></i> System Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> <?= __t('logout') ?></a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Flash Notification -->
    <div class="container-fluid px-4 pt-3">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Body Container Begins -->
    <div class="container-fluid px-4 pb-5 flex-grow-1">
