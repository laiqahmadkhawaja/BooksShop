<?php
// books.php - Book & Stationery Product Catalog Management
require_once __DIR__ . '/config/app.php';
requireAuth();

$action = $_GET['action'] ?? '';
$tab = $_GET['tab'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';

// Handle Product Add / Edit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_product'])) {
        $id = (int)($_POST['product_id'] ?? 0);
        $code = trim($_POST['product_code'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '') ?: $code;
        $title = trim($_POST['title'] ?? '');
        $urduTitle = trim($_POST['urdu_title'] ?? '');
        $type = $_POST['product_type'] ?? 'book';
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $isbn = trim($_POST['isbn'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $publisher = trim($_POST['publisher'] ?? '');
        $edition = trim($_POST['edition'] ?? '');
        $year = trim($_POST['publication_year'] ?? '');
        $classGrade = trim($_POST['class_grade'] ?? '');
        $board = trim($_POST['educational_board'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $lang = trim($_POST['language'] ?? 'English');
        $unit = trim($_POST['unit'] ?? 'Piece');
        $cost = (float)($_POST['purchase_price'] ?? 0);
        $retail = (float)($_POST['retail_price'] ?? 0);
        $wholesale = (float)($_POST['wholesale_price'] ?? 0);
        $discount = (float)($_POST['discount_percent'] ?? 0);
        $minStock = (float)($_POST['min_stock_alert'] ?? 5);
        $rack = trim($_POST['rack_location'] ?? '');
        $shelf = trim($_POST['shelf_location'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if (!$code || !$title) {
            setFlash('danger', 'Product Code and Title are required.');
        } else {
            if ($id > 0) {
                // Update
                $sql = "UPDATE products SET product_code=?, barcode=?, title=?, urdu_title=?, product_type=?, category_id=?, 
                        isbn=?, author=?, publisher=?, edition=?, publication_year=?, class_grade=?, educational_board=?, 
                        subject=?, language=?, unit=?, purchase_price=?, retail_price=?, wholesale_price=?, 
                        discount_percent=?, min_stock_alert=?, rack_location=?, shelf_location=?, description=? 
                        WHERE id=?";
                dbQuery($sql, [$code, $barcode, $title, $urduTitle, $type, $categoryId, $isbn, $author, $publisher, 
                               $edition, $year, $classGrade, $board, $subject, $lang, $unit, $cost, $retail, $wholesale, 
                               $discount, $minStock, $rack, $shelf, $desc, $id]);
                setFlash('success', "Product '$title' updated successfully.");
            } else {
                // Insert
                $initialStock = (float)($_POST['initial_stock'] ?? 0);
                $sql = "INSERT INTO products (product_code, barcode, title, urdu_title, product_type, category_id, 
                        isbn, author, publisher, edition, publication_year, class_grade, educational_board, 
                        subject, language, unit, purchase_price, retail_price, wholesale_price, discount_percent, 
                        current_stock, min_stock_alert, rack_location, shelf_location, description)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                dbQuery($sql, [$code, $barcode, $title, $urduTitle, $type, $categoryId, $isbn, $author, $publisher, 
                               $edition, $year, $classGrade, $board, $subject, $lang, $unit, $cost, $retail, $wholesale, 
                               $discount, $initialStock, $minStock, $rack, $shelf, $desc]);
                $newId = dbInsertId();
                if ($initialStock > 0) {
                    recordStockMovement($newId, 'adjustment_in', null, $initialStock, 'Initial Opening Stock Entry', $cost);
                }
                setFlash('success', "Product '$title' added successfully.");
            }
            header("Location: books.php");
            exit;
        }
    }

    // Quick Stock Adjustment POST
    if (isset($_POST['adjust_stock_quick'])) {
        $pId = (int)$_POST['product_id'];
        $adjType = $_POST['adjustment_type']; // 'add' or 'subtract'
        $qty = (float)$_POST['quantity'];
        $reason = trim($_POST['reason'] ?? 'Manual Stock Adjustment');

        if ($pId > 0 && $qty > 0) {
            $change = ($adjType === 'subtract') ? -$qty : $qty;
            $moveType = ($adjType === 'subtract') ? 'adjustment_out' : 'adjustment_in';
            recordStockMovement($pId, $moveType, null, $change, $reason);
            setFlash('success', "Stock updated successfully!");
        }
        header("Location: books.php");
        exit;
    }

    // Product Type Add / Edit POST
    if (isset($_POST['save_product_type'])) {
        $tId = (int)($_POST['type_id'] ?? 0);
        $tName = trim($_POST['type_name'] ?? '');
        $tCode = trim($_POST['type_code'] ?? '');
        $tDesc = trim($_POST['type_description'] ?? '');
        $isAjax = !empty($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        $res = saveProductType($tName, $tCode, $tDesc, $tId);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($res);
            exit;
        }

        if ($res['success']) {
            setFlash('success', $res['message']);
        } else {
            setFlash('danger', $res['message']);
        }
        header("Location: books.php");
        exit;
    }

    // Product Type Delete POST
    if (isset($_POST['delete_product_type'])) {
        $delId = (int)$_POST['type_id'];
        $isAjax = !empty($_POST['ajax']);
        $res = deleteProductType($delId);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($res);
            exit;
        }

        if ($res['success']) {
            setFlash('success', $res['message']);
        } else {
            setFlash('danger', $res['message']);
        }
        header("Location: books.php");
        exit;
    }

    // Category Add / Edit POST
    if (isset($_POST['save_category'])) {
        $cId = (int)($_POST['category_id'] ?? 0);
        $cName = trim($_POST['cat_name'] ?? '');
        $cUrdu = trim($_POST['cat_urdu_name'] ?? '');
        $cType = $_POST['cat_type'] ?? 'book';
        $cDesc = trim($_POST['cat_description'] ?? '');

        if ($cName) {
            if ($cId > 0) {
                dbQuery("UPDATE categories SET name=?, urdu_name=?, type=?, description=? WHERE id=?", [$cName, $cUrdu, $cType, $cDesc, $cId]);
                setFlash('success', "Category '$cName' updated successfully.");
            } else {
                dbQuery("INSERT INTO categories (name, urdu_name, type, description) VALUES (?, ?, ?, ?)", [$cName, $cUrdu, $cType, $cDesc]);
                setFlash('success', "Category '$cName' created successfully.");
            }
        }
        header("Location: books.php");
        exit;
    }

    // Category Delete POST
    if (isset($_POST['delete_category'])) {
        $delId = (int)$_POST['category_id'];
        $prodCount = dbFetchOne("SELECT COUNT(*) as cnt FROM products WHERE category_id = ?", [$delId])['cnt'] ?? 0;
        if ($prodCount > 0) {
            setFlash('danger', "Cannot delete category: $prodCount products are linked to it. Please reassign those products first.");
        } else {
            dbQuery("DELETE FROM categories WHERE id = ?", [$delId]);
            setFlash('success', "Category deleted successfully.");
        }
        header("Location: books.php");
        exit;
    }
}

// Handle Export CSV
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=books_products_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Code', 'Barcode', 'Title', 'Type', 'ISBN', 'Author', 'Publisher', 'Class', 'Cost Price', 'Retail Price', 'Stock', 'Rack']);
    $rows = dbFetchAll("SELECT id, product_code, barcode, title, product_type, isbn, author, publisher, class_grade, purchase_price, retail_price, current_stock, rack_location FROM products ORDER BY id ASC");
    foreach ($rows as $r) {
        fputcsv($output, $r);
    }
    fclose($output);
    exit;
}

// Fetch categories and product types
$categories = dbFetchAll("SELECT * FROM categories ORDER BY name ASC");
$productTypes = getProductTypes();

// Build query
$where = ["p.status = 1"];
$params = [];

if ($tab === 'books' || $tab === 'book') {
    $where[] = "p.product_type = 'book'";
} elseif ($tab === 'stationery') {
    $where[] = "p.product_type = 'stationery'";
} elseif ($tab === 'low_stock') {
    $where[] = "p.current_stock <= p.min_stock_alert";
} elseif ($tab !== 'all' && !empty($tab)) {
    $where[] = "p.product_type = ?";
    $params[] = $tab;
}

if ($categoryFilter) {
    $where[] = "p.category_id = ?";
    $params[] = (int)$categoryFilter;
}

if ($search) {
    $where[] = "(p.title LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ? OR p.isbn LIKE ? OR p.author LIKE ? OR p.publisher LIKE ?)";
    $sParam = "%$search%";
    $params = array_merge($params, [$sParam, $sParam, $sParam, $sParam, $sParam, $sParam]);
}

$whereClause = implode(" AND ", $where);
$products = dbFetchAll("SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON c.id = p.category_id 
    WHERE $whereClause 
    ORDER BY p.id DESC", $params);

$pageTitle = 'Books & Products';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-book-open text-primary me-2"></i> Books & Stationery Catalog</h4>
        <small class="text-muted">Manage textbook syllabus, general books, barcodes, and stationery items</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#productTypeManageModal">
            <i class="fa-solid fa-layer-group me-1"></i> Manage Types
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#categoryManageModal">
            <i class="fa-solid fa-tags me-1"></i> Manage Categories
        </button>
        <a href="?export_csv=1" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-file-excel me-1"></i> Export CSV
        </a>
        <a href="product_form.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fa-solid fa-plus-circle me-1"></i> Add New Product / Book
        </a>
    </div>
</div>

<!-- Tabs & Search Filter -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white p-0">
        <ul class="nav nav-tabs card-header-tabs m-0 flex-nowrap overflow-auto">
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $tab === 'all' ? 'active fw-bold' : '' ?>" href="books.php?tab=all">
                    <i class="fa-solid fa-list me-1"></i> All Products (<?= dbFetchOne("SELECT COUNT(*) as c FROM products WHERE status=1")['c'] ?>)
                </a>
            </li>
            <?php foreach ($productTypes as $pt): ?>
                <?php
                    $pCode = $pt['code'];
                    $isActive = ($tab === $pCode || ($tab === 'books' && $pCode === 'book'));
                    $icon = 'fa-tag';
                    $textColor = 'text-dark';
                    if ($pCode === 'book') { $icon = 'fa-book'; $textColor = 'text-primary'; }
                    elseif ($pCode === 'stationery') { $icon = 'fa-pencil'; $textColor = 'text-warning'; }
                    elseif ($pCode === 'toys') { $icon = 'fa-puzzle-piece'; $textColor = 'text-info'; }
                    
                    $tCount = dbFetchOne("SELECT COUNT(*) as c FROM products WHERE product_type=? AND status=1", [$pCode])['c'] ?? 0;
                ?>
                <li class="nav-item">
                    <a class="nav-link text-nowrap <?= $isActive ? 'active fw-bold ' . $textColor : '' ?>" href="books.php?tab=<?= urlencode($pCode) ?>">
                        <i class="fa-solid <?= $icon ?> me-1"></i> <?= htmlspecialchars($pt['name']) ?> (<?= $tCount ?>)
                    </a>
                </li>
            <?php endforeach; ?>
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $tab === 'low_stock' ? 'active fw-bold text-danger' : '' ?>" href="books.php?tab=low_stock">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Low Stock Alert (<?= dbFetchOne("SELECT COUNT(*) as c FROM products WHERE current_stock <= min_stock_alert AND status=1")['c'] ?>)
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by Title, ISBN, Barcode, Author, Publisher..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="category" class="form-select">
                    <option value="">-- All Categories --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?> (<?= ucfirst($cat['type']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-secondary w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                <a href="books.php?tab=<?= $tab ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Product Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th>Item & Details</th>
                        <th>Type / Category</th>
                        <th>ISBN / Barcode</th>
                        <th>Author & Publisher</th>
                        <th>Rack / Shelf</th>
                        <th>Cost Price</th>
                        <th>Retail Price</th>
                        <th>Current Stock</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="fa-solid fa-book-open fa-3x mb-2 text-secondary opacity-50"></i><br>
                                No products found matching your filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($p['title']) ?></div>
                                    <?php if ($p['urdu_title']): ?>
                                        <div class="text-secondary small font-urdu"><?= htmlspecialchars($p['urdu_title']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-muted small">
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['product_code']) ?></span>
                                        <?php if ($p['class_grade']): ?>
                                            &bull; <span class="badge bg-info-subtle text-info-emphasis"><?= htmlspecialchars($p['class_grade']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($p['edition']): ?>
                                            &bull; <?= htmlspecialchars($p['edition']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                        $ptCode = $p['product_type'];
                                        $badgeCls = 'bg-secondary';
                                        if ($ptCode === 'book') $badgeCls = 'bg-primary';
                                        elseif ($ptCode === 'stationery') $badgeCls = 'bg-warning text-dark';
                                        elseif ($ptCode === 'toys') $badgeCls = 'bg-info text-dark';
                                    ?>
                                    <span class="badge <?= $badgeCls ?>">
                                        <?= ucfirst($p['product_type']) ?>
                                    </span>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($p['category_name'] ?? 'General') ?></div>
                                </td>
                                <td>
                                    <?php if ($p['isbn']): ?>
                                        <div class="small"><strong>ISBN:</strong> <?= htmlspecialchars($p['isbn']) ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted font-monospace"><i class="fa-solid fa-barcode me-1"></i><?= htmlspecialchars($p['barcode'] ?: $p['product_code']) ?></div>
                                </td>
                                <td>
                                    <div class="small"><?= htmlspecialchars($p['author'] ?: '-') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($p['publisher'] ?: '-') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($p['rack_location'] ?: 'Unassigned') ?>
                                        <?= $p['shelf_location'] ? ' / ' . htmlspecialchars($p['shelf_location']) : '' ?>
                                    </span>
                                </td>
                                <td class="small"><?= formatCurrency($p['purchase_price']) ?></td>
                                <td class="fw-bold text-primary"><?= formatCurrency($p['retail_price']) ?></td>
                                <td>
                                    <?php if ($p['current_stock'] <= 0): ?>
                                        <span class="badge bg-danger">OUT OF STOCK (0)</span>
                                    <?php elseif ($p['current_stock'] <= $p['min_stock_alert']): ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= (float)$p['current_stock'] ?> <?= htmlspecialchars($p['unit']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?= (float)$p['current_stock'] ?> <?= htmlspecialchars($p['unit']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Edit -->
                                        <a href="product_form.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary" title="Edit Product">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <!-- Adjust Stock -->
                                        <button type="button" class="btn btn-outline-info" onclick="openStockAdjustModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['title'])) ?>', <?= (float)$p['current_stock'] ?>)" title="Adjust Stock">
                                            <i class="fa-solid fa-sliders"></i>
                                        </button>
                                        <!-- Barcode Print Preview -->
                                        <button type="button" class="btn btn-outline-dark" onclick="openBarcodeModal('<?= htmlspecialchars($p['barcode'] ?: $p['product_code']) ?>', '<?= htmlspecialchars(addslashes($p['title'])) ?>', '<?= formatCurrency($p['retail_price']) ?>')" title="Print Barcode Sticker">
                                            <i class="fa-solid fa-barcode"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add / Edit Product -->
<!-- Product Add/Edit is managed via dedicated product_form.php -->

<!-- Modal: Quick Stock Adjustment -->
<!-- Bottom Sheet: Quick Stock Adjustment -->
<div class="offcanvas offcanvas-bottom" id="stockAdjustModal" tabindex="-1" style="height: 80vh; max-height: 80vh; border-top-left-radius: 20px; border-top-right-radius: 20px; box-shadow: 0 -10px 30px rgba(0,0,0,0.25);">
    <form method="POST" action="books.php" class="m-0 d-flex flex-column h-100">
        <input type="hidden" name="adjust_stock_quick" value="1">
        <input type="hidden" name="product_id" id="adj_prod_id">
        <div class="offcanvas-header bg-light border-bottom flex-shrink-0 py-3 px-4">
            <h5 class="offcanvas-title fw-bold"><i class="fa-solid fa-sliders text-info me-2"></i> Stock Adjustment</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body flex-grow-1 p-3 p-md-4" style="overflow-y: auto;">
                    <p class="mb-1 text-muted small">Product:</p>
                    <h6 class="fw-bold mb-3" id="adj_prod_name"></h6>
                    <div class="alert alert-light border py-2 mb-3">
                        Current System Stock: <strong id="adj_current_stock" class="text-primary">0</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Adjustment Action</label>
                        <select name="adjustment_type" class="form-select">
                            <option value="add">Add Stock (Found / Delivery Correction)</option>
                            <option value="subtract">Deduct Stock (Damage / Lost / Missing)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Quantity</label>
                        <input type="number" step="0.01" name="quantity" class="form-control" required min="0.01" value="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Reason / Note</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g. Damaged during rain, Stock count audit">
                    </div>
                </div>
                </div>
        <div class="p-3 bg-light border-top flex-shrink-0 d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">Close</button>
            <button type="submit" class="btn btn-info text-white fw-bold"><i class="fa-solid fa-check me-1"></i> Apply Adjustment</button>
        </div>
    </form>
</div>

<!-- Modal: Barcode Label Sticker Preview -->
<!-- Bottom Sheet: Barcode Label Sticker Preview -->
<div class="offcanvas offcanvas-bottom" id="barcodeModal" tabindex="-1" style="height: 70vh; max-height: 70vh; border-top-left-radius: 20px; border-top-right-radius: 20px; box-shadow: 0 -10px 30px rgba(0,0,0,0.25);">
    <div class="offcanvas-header bg-light border-bottom flex-shrink-0 py-3 px-4">
        <h5 class="offcanvas-title fw-bold"><i class="fa-solid fa-barcode me-2"></i> Print Barcode Sticker</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body text-center p-3 p-md-4" style="overflow-y: auto;">
                <div id="barcodePrintArea" class="border p-3 rounded bg-white shadow-sm d-inline-block">
                    <div class="small fw-bold text-uppercase" id="barcodeStoreName"><?= htmlspecialchars(getSetting('shop_name', 'Kitab Ghar')) ?></div>
                    <div class="small fw-semibold text-truncate my-1" style="max-width: 250px;" id="barcodeTitle"></div>
                    <svg id="barcodeCanvas"></svg>
                    <div class="fw-bold fs-6 text-primary mt-1" id="barcodePrice"></div>
                </div>
            </div>
            <div class="p-3 bg-light border-top flex-shrink-0 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">Close</button>
        <button type="button" class="btn btn-dark fw-bold" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Print Sticker</button>
    </div>
</div>

<!-- Modal: Manage & Add Product Categories -->
<!-- Bottom Sheet: Product Categories Manager -->
<div class="offcanvas offcanvas-bottom" id="categoryManageModal" tabindex="-1" style="height: 94vh; max-height: 94vh; border-top-left-radius: 20px; border-top-right-radius: 20px; box-shadow: 0 -10px 30px rgba(0,0,0,0.25);">
    <div class="offcanvas-header bg-primary text-white flex-shrink-0 py-3 px-4">
        <h5 class="offcanvas-title fw-bold"><i class="fa-solid fa-tags me-2"></i> Product Categories Manager</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-3 p-md-4" style="overflow-y: auto;">
                <!-- Add / Edit Category Form -->
                <div class="card bg-light border p-3 mb-4">
                    <h6 class="fw-bold mb-3 text-dark" id="catFormTitle"><i class="fa-solid fa-plus-circle text-primary me-1"></i> Add New Category</h6>
                    <form method="POST" action="books.php">
                        <input type="hidden" name="save_category" value="1">
                        <input type="hidden" name="category_id" id="edit_cat_id" value="0">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Category Name *</label>
                                <input type="text" name="cat_name" id="edit_cat_name" class="form-control form-control-sm" required placeholder="e.g. Oxford Primary Syllabus">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Urdu Name (اردو نام)</label>
                                <input type="text" name="cat_urdu_name" id="edit_cat_urdu" class="form-control form-control-sm" placeholder="اردو نام">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Category Type</label>
                                <select name="cat_type" id="edit_cat_type" class="form-select form-select-sm">
                                    <?php foreach ($productTypes as $pt): ?>
                                        <option value="<?= htmlspecialchars($pt['code']) ?>"><?= htmlspecialchars($pt['name']) ?></option>
                                    <?php endforeach; ?>
                                    <option value="general">General Merchandise</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                                    <i class="fa-solid fa-save me-1"></i> Save
                                </button>
                            </div>
                            <div class="col-12 mt-2">
                                <input type="text" name="cat_description" id="edit_cat_desc" class="form-control form-control-sm" placeholder="Optional category description or curriculum board notes">
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Existing Categories Table -->
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-list me-1"></i> Existing Product Categories (<?= count($categories) ?>)</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Category Name</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-center">Products Linked</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <?php 
                                    $pCount = dbFetchOne("SELECT COUNT(*) as cnt FROM products WHERE category_id = ?", [$cat['id']])['cnt'] ?? 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($cat['name']) ?></strong>
                                        <?php if ($cat['urdu_name']): ?>
                                            <div class="text-secondary small font-urdu"><?= htmlspecialchars($cat['urdu_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $cat['type'] === 'book' ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-dark' ?>">
                                            <?= ucfirst($cat['type']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?= htmlspecialchars($cat['description'] ?: '-') ?></td>
                                    <td class="text-center fw-bold text-primary"><?= $pCount ?> items</td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" onclick='editCategoryRow(<?= json_encode($cat) ?>)' title="Edit Category">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <form method="POST" action="books.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete category \'<?= htmlspecialchars(addslashes($cat['name'])) ?>\'?')">
                                                <input type="hidden" name="delete_category" value="1">
                                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete Category" <?= $pCount > 0 ? 'disabled' : '' ?>>
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="p-3 bg-light border-top flex-shrink-0 d-flex justify-content-end">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">Close</button>
    </div>
</div>

<!-- Modal: Manage & Add Product Types -->
<!-- Bottom Sheet: Product Types Manager -->
<div class="offcanvas offcanvas-bottom" id="productTypeManageModal" tabindex="-1" style="height: 94vh; max-height: 94vh; border-top-left-radius: 20px; border-top-right-radius: 20px; box-shadow: 0 -10px 30px rgba(0,0,0,0.25);">
    <div class="offcanvas-header bg-dark text-white flex-shrink-0 py-3 px-4">
        <h5 class="offcanvas-title fw-bold"><i class="fa-solid fa-layer-group me-2 text-warning"></i> Product Types Manager</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-3 p-md-4" style="overflow-y: auto;">
                <!-- Add / Edit Product Type Form -->
                <div class="card bg-light border p-3 mb-4">
                    <h6 class="fw-bold mb-3 text-dark" id="typeFormTitle"><i class="fa-solid fa-plus-circle text-primary me-1"></i> Add New Product Type</h6>
                    <form method="POST" action="books.php" id="formProductType">
                        <input type="hidden" name="save_product_type" value="1">
                        <input type="hidden" name="type_id" id="edit_type_id" value="0">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold">Type Name *</label>
                                <input type="text" name="type_name" id="edit_type_name" class="form-control form-control-sm" required placeholder="e.g. Toys, Uniform, Bags" oninput="autoGenerateTypeCode(this.value)">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Code / Slug *</label>
                                <input type="text" name="type_code" id="edit_type_code" class="form-control form-control-sm" required placeholder="e.g. toys">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                                    <i class="fa-solid fa-save me-1"></i> Save Type
                                </button>
                            </div>
                            <div class="col-12 mt-2">
                                <input type="text" name="type_description" id="edit_type_desc" class="form-control form-control-sm" placeholder="Optional notes (e.g. Educational toys, games, novelty items)">
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Existing Product Types Table -->
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-list me-1"></i> Existing Product Types (<?= count($productTypes) ?>)</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Type Name</th>
                                <th>Code / Key</th>
                                <th>Description</th>
                                <th class="text-center">Products Linked</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productTypes as $pt): ?>
                                <?php 
                                    $pCount = dbFetchOne("SELECT COUNT(*) as cnt FROM products WHERE product_type = ?", [$pt['code']])['cnt'] ?? 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($pt['name']) ?></strong>
                                        <?php if (!empty($pt['is_system'])): ?>
                                            <span class="badge bg-secondary-subtle text-secondary ms-1">System Default</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?= htmlspecialchars($pt['code']) ?></code>
                                    </td>
                                    <td class="text-muted"><?= htmlspecialchars($pt['description'] ?: '-') ?></td>
                                    <td class="text-center fw-bold text-primary"><?= $pCount ?> items</td>
                                    <td class="text-end">
                                        <?php if (empty($pt['is_system'])): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" onclick='editProductTypeRow(<?= json_encode($pt) ?>)' title="Edit Type">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <form method="POST" action="books.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete product type \'<?= htmlspecialchars(addslashes($pt['name'])) ?>\'?')">
                                                    <input type="hidden" name="delete_product_type" value="1">
                                                    <input type="hidden" name="type_id" value="<?= $pt['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Delete Type" <?= $pCount > 0 ? 'disabled' : '' ?>>
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Protected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="p-3 bg-light border-top flex-shrink-0 d-flex justify-content-end">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">Close</button>
    </div>
</div>

<!-- JsBarcode for Barcode Label Generation -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

<script>
function toggleProductTypeFields() {
    const type = document.getElementById('prod_type').value;
    const bookFields = document.getElementById('bookSpecificFields');
    if (type === 'book') {
        bookFields.style.display = 'flex';
    } else {
        bookFields.style.display = 'none';
    }
}

function openStockAdjustModal(id, name, curStock) {
    document.getElementById('adj_prod_id').value = id;
    document.getElementById('adj_prod_name').innerText = name;
    document.getElementById('adj_current_stock').innerText = curStock;
    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('stockAdjustModal')).show();
}

function openBarcodeModal(barcodeVal, title, price) {
    document.getElementById('barcodeTitle').innerText = title;
    document.getElementById('barcodePrice').innerText = price;
    JsBarcode("#barcodeCanvas", barcodeVal, {
        format: "CODE128",
        width: 1.8,
        height: 45,
        displayValue: true
    });
    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('barcodeModal')).show();
}

function editCategoryRow(c) {
    document.getElementById('catFormTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-secondary me-1"></i> Edit Category: ' + c.name;
    document.getElementById('edit_cat_id').value = c.id;
    document.getElementById('edit_cat_name').value = c.name;
    document.getElementById('edit_cat_urdu').value = c.urdu_name || '';
    document.getElementById('edit_cat_type').value = c.type || 'book';
    document.getElementById('edit_cat_desc').value = c.description || '';
    document.getElementById('edit_cat_name').focus();
}

function autoGenerateTypeCode(val) {
    if (document.getElementById('edit_type_id').value == '0') {
        const code = val.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        document.getElementById('edit_type_code').value = code;
    }
}

function editProductTypeRow(pt) {
    document.getElementById('typeFormTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-secondary me-1"></i> Edit Product Type: ' + pt.name;
    document.getElementById('edit_type_id').value = pt.id;
    document.getElementById('edit_type_name').value = pt.name;
    document.getElementById('edit_type_code').value = pt.code;
    document.getElementById('edit_type_desc').value = pt.description || '';
    document.getElementById('edit_type_name').focus();
}

document.addEventListener('DOMContentLoaded', function() {
    const typeForm = document.getElementById('formProductType');
    if (typeForm) {
        typeForm.addEventListener('submit', function(e) {
            const productModal = document.getElementById('productModal');
            const isProductModalOpen = productModal && productModal.classList.contains('show');

            if (isProductModalOpen) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('ajax', '1');

                fetch('books.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const sel = document.getElementById('prod_type');
                        let opt = sel.querySelector(`option[value="${data.code}"]`);
                        if (!opt) {
                            opt = document.createElement('option');
                            opt.value = data.code;
                            opt.textContent = data.name;
                            sel.appendChild(opt);
                        } else {
                            opt.textContent = data.name;
                        }
                        sel.value = data.code;
                        toggleProductTypeFields();

                        // Close productTypeManageModal
                        const modalEl = document.getElementById('productTypeManageModal');
                        const modalInstance = bootstrap.Offcanvas.getInstance(modalEl);
                        if (modalInstance) modalInstance.hide();

                        // Reset form
                        document.getElementById('edit_type_id').value = '0';
                        document.getElementById('edit_type_name').value = '';
                        document.getElementById('edit_type_code').value = '';
                        document.getElementById('edit_type_desc').value = '';
                        document.getElementById('typeFormTitle').innerHTML = '<i class="fa-solid fa-plus-circle text-primary me-1"></i> Add New Product Type';
                    } else {
                        alert(data.message || 'Error saving product type');
                    }
                })
                .catch(err => {
                    console.error(err);
                    typeForm.submit();
                });
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
