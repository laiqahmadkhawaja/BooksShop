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

// Fetch categories
$categories = dbFetchAll("SELECT * FROM categories ORDER BY name ASC");

// Build query
$where = ["p.status = 1"];
$params = [];

if ($tab === 'books') {
    $where[] = "p.product_type = 'book'";
} elseif ($tab === 'stationery') {
    $where[] = "p.product_type = 'stationery'";
} elseif ($tab === 'low_stock') {
    $where[] = "p.current_stock <= p.min_stock_alert";
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
    ORDER BY p.id DESC");

$pageTitle = 'Books & Products';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-book-open text-primary me-2"></i> Books & Stationery Catalog</h4>
        <small class="text-muted">Manage textbook syllabus, general books, barcodes, and stationery items</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#categoryManageModal">
            <i class="fa-solid fa-tags me-1"></i> Manage Categories
        </button>
        <a href="?export_csv=1" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-file-excel me-1"></i> Export CSV
        </a>
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#productModal" onclick="openNewProductModal()">
            <i class="fa-solid fa-plus-circle me-1"></i> Add New Product / Book
        </button>
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
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $tab === 'books' ? 'active fw-bold text-primary' : '' ?>" href="books.php?tab=books">
                    <i class="fa-solid fa-book me-1"></i> Books & Syllabus (<?= dbFetchOne("SELECT COUNT(*) as c FROM products WHERE product_type='book' AND status=1")['c'] ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-nowrap <?= $tab === 'stationery' ? 'active fw-bold text-warning' : '' ?>" href="books.php?tab=stationery">
                    <i class="fa-solid fa-pencil me-1"></i> Stationery (<?= dbFetchOne("SELECT COUNT(*) as c FROM products WHERE product_type='stationery' AND status=1")['c'] ?>)
                </a>
            </li>
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
                                    <span class="badge <?= $p['product_type'] === 'book' ? 'bg-primary' : 'bg-warning text-dark' ?>">
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
                                        <button type="button" class="btn btn-outline-secondary" onclick='openEditProductModal(<?= json_encode($p) ?>)' title="Edit Product">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
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
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="books.php">
                <input type="hidden" name="save_product" value="1">
                <input type="hidden" name="product_id" id="prod_id" value="0">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="productModalTitle"><i class="fa-solid fa-book text-primary me-2"></i> Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Product Type & Code -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Product Type *</label>
                            <select name="product_type" id="prod_type" class="form-select" onchange="toggleProductTypeFields()">
                                <option value="book">Book / Textbook / Novel</option>
                                <option value="stationery">Stationery / Office Supply</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Product Code / SKU *</label>
                            <input type="text" name="product_code" id="prod_code" class="form-control" required placeholder="e.g. BK-1025 or ST-2030">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Barcode (EAN-13 / Code128)</label>
                            <input type="text" name="barcode" id="prod_barcode" class="form-control" placeholder="Scan or leave blank to use code">
                        </div>

                        <!-- Titles -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Title / Item Name *</label>
                            <input type="text" name="title" id="prod_title" class="form-control" required placeholder="e.g. Oxford Progressive English 5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Urdu Title (اردو نام)</label>
                            <input type="text" name="urdu_title" id="prod_urdu_title" class="form-control" placeholder="اردو میں کتاب یا آئٹم کا نام">
                        </div>

                        <!-- Category & Unit -->
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold mb-0">Category</label>
                                <a href="javascript:void(0)" class="small text-decoration-none fw-bold text-primary" data-bs-toggle="modal" data-bs-target="#categoryManageModal">+ Add Category</a>
                            </div>
                            <select name="category_id" id="prod_category_id" class="form-select">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?> (<?= ucfirst($cat['type']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Selling Unit</label>
                            <select name="unit" id="prod_unit" class="form-select">
                                <option value="Piece">Piece / Single Book</option>
                                <option value="Pack">Pack</option>
                                <option value="Box">Box</option>
                                <option value="Dozen">Dozen</option>
                                <option value="Carton">Carton</option>
                                <option value="Set">Complete Course Set</option>
                            </select>
                        </div>

                        <!-- Book-Specific Fields Container -->
                        <div id="bookSpecificFields" class="row g-3 m-0 p-3 bg-light rounded border">
                            <div class="col-12"><h6 class="fw-bold text-primary mb-0"><i class="fa-solid fa-graduation-cap me-1"></i> Academic & Book Details</h6></div>
                            <div class="col-md-4">
                                <label class="form-label small">ISBN</label>
                                <input type="text" name="isbn" id="prod_isbn" class="form-control form-control-sm" placeholder="e.g. 978-0199408221">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Author</label>
                                <input type="text" name="author" id="prod_author" class="form-control form-control-sm" placeholder="Author name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Publisher</label>
                                <input type="text" name="publisher" id="prod_publisher" class="form-control form-control-sm" placeholder="e.g. Oxford, PCTB">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Edition</label>
                                <input type="text" name="edition" id="prod_edition" class="form-control form-control-sm" placeholder="e.g. 3rd Edition">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Class / Grade</label>
                                <input type="text" name="class_grade" id="prod_class_grade" class="form-control form-control-sm" placeholder="e.g. Class 5 / Matric">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Educational Board</label>
                                <input type="text" name="educational_board" id="prod_board" class="form-control form-control-sm" placeholder="e.g. Punjab Board, Cambridge">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Language</label>
                                <select name="language" id="prod_language" class="form-select form-select-sm">
                                    <option value="English">English</option>
                                    <option value="Urdu">Urdu</option>
                                    <option value="Arabic">Arabic</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <!-- Pricing & Stock -->
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Purchase Cost (Rs.)</label>
                            <input type="number" step="0.01" name="purchase_price" id="prod_cost" class="form-control" value="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Retail Price / MRP (Rs.) *</label>
                            <input type="number" step="0.01" name="retail_price" id="prod_retail" class="form-control" required value="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Wholesale Price (Rs.)</label>
                            <input type="number" step="0.01" name="wholesale_price" id="prod_wholesale" class="form-control" value="0.00">
                        </div>

                        <!-- Stock levels -->
                        <div class="col-md-4" id="initialStockDiv">
                            <label class="form-label small fw-bold">Initial Opening Stock</label>
                            <input type="number" step="0.01" name="initial_stock" id="prod_stock" class="form-control" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Min Stock Alert Level</label>
                            <input type="number" step="0.01" name="min_stock_alert" id="prod_min_stock" class="form-control" value="5">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Rack / Storage Location</label>
                            <input type="text" name="rack_location" id="prod_rack" class="form-control" placeholder="e.g. Rack B, Row 2">
                        </div>

                        <div class="col-12">
                            <label class="form-label small">Description / Notes</label>
                            <textarea name="description" id="prod_desc" class="form-control" rows="2" placeholder="Course syllabus notes, tags, or description"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Quick Stock Adjustment -->
<div class="modal fade" id="stockAdjustModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="books.php">
                <input type="hidden" name="adjust_stock_quick" value="1">
                <input type="hidden" name="product_id" id="adj_prod_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-sliders text-info me-2"></i> Stock Adjustment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-info text-white"><i class="fa-solid fa-check me-1"></i> Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Barcode Label Sticker Preview -->
<div class="modal fade" id="barcodeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-barcode me-2"></i> Print Barcode Sticker</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div id="barcodePrintArea" class="border p-3 rounded bg-white shadow-sm d-inline-block">
                    <div class="small fw-bold text-uppercase" id="barcodeStoreName"><?= htmlspecialchars(getSetting('shop_name', 'Kitab Ghar')) ?></div>
                    <div class="small fw-semibold text-truncate my-1" style="max-width: 250px;" id="barcodeTitle"></div>
                    <svg id="barcodeCanvas"></svg>
                    <div class="fw-bold fs-6 text-primary mt-1" id="barcodePrice"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-dark" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Print Sticker</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Manage & Add Product Categories -->
<div class="modal fade" id="categoryManageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-tags me-2"></i> Product Categories Manager</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
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
                                    <option value="book">Book / Syllabus</option>
                                    <option value="stationery">Stationery</option>
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
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-dark" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Print Sticker</button>
            </div>
        </div>
    </div>
</div>

<!-- JsBarcode for Barcode Label Generation -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

<script>
function toggleProductTypeFields() {
    const type = document.getElementById('prod_type').value;
    const bookFields = document.getElementById('bookSpecificFields');
    if (type === 'stationery') {
        bookFields.style.display = 'none';
    } else {
        bookFields.style.display = 'flex';
    }
}

function openNewProductModal() {
    document.getElementById('productModalTitle').innerHTML = '<i class="fa-solid fa-book text-primary me-2"></i> Add New Product / Book';
    document.getElementById('prod_id').value = '0';
    document.getElementById('prod_code').value = 'BK-' + Math.floor(1000 + Math.random() * 9000);
    document.getElementById('prod_barcode').value = '';
    document.getElementById('prod_title').value = '';
    document.getElementById('prod_urdu_title').value = '';
    document.getElementById('prod_type').value = 'book';
    document.getElementById('prod_category_id').value = '';
    document.getElementById('prod_isbn').value = '';
    document.getElementById('prod_author').value = '';
    document.getElementById('prod_publisher').value = '';
    document.getElementById('prod_edition').value = '';
    document.getElementById('prod_class_grade').value = '';
    document.getElementById('prod_board').value = '';
    document.getElementById('prod_language').value = 'English';
    document.getElementById('prod_unit').value = 'Piece';
    document.getElementById('prod_cost').value = '0.00';
    document.getElementById('prod_retail').value = '0.00';
    document.getElementById('prod_wholesale').value = '0.00';
    document.getElementById('prod_stock').value = '0';
    document.getElementById('initialStockDiv').style.display = 'block';
    document.getElementById('prod_min_stock').value = '5';
    document.getElementById('prod_rack').value = '';
    document.getElementById('prod_desc').value = '';
    toggleProductTypeFields();
}

function openEditProductModal(p) {
    document.getElementById('productModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-secondary me-2"></i> Edit Product';
    document.getElementById('prod_id').value = p.id;
    document.getElementById('prod_code').value = p.product_code;
    document.getElementById('prod_barcode').value = p.barcode || '';
    document.getElementById('prod_title').value = p.title;
    document.getElementById('prod_urdu_title').value = p.urdu_title || '';
    document.getElementById('prod_type').value = p.product_type;
    document.getElementById('prod_category_id').value = p.category_id || '';
    document.getElementById('prod_isbn').value = p.isbn || '';
    document.getElementById('prod_author').value = p.author || '';
    document.getElementById('prod_publisher').value = p.publisher || '';
    document.getElementById('prod_edition').value = p.edition || '';
    document.getElementById('prod_class_grade').value = p.class_grade || '';
    document.getElementById('prod_board').value = p.educational_board || '';
    document.getElementById('prod_language').value = p.language || 'English';
    document.getElementById('prod_unit').value = p.unit || 'Piece';
    document.getElementById('prod_cost').value = p.purchase_price;
    document.getElementById('prod_retail').value = p.retail_price;
    document.getElementById('prod_wholesale').value = p.wholesale_price;
    document.getElementById('initialStockDiv').style.display = 'none'; // stock adjusted via adjustment modal
    document.getElementById('prod_min_stock').value = p.min_stock_alert;
    document.getElementById('prod_rack').value = p.rack_location || '';
    document.getElementById('prod_desc').value = p.description || '';
    toggleProductTypeFields();
    new bootstrap.Modal(document.getElementById('productModal')).show();
}

function openStockAdjustModal(id, name, curStock) {
    document.getElementById('adj_prod_id').value = id;
    document.getElementById('adj_prod_name').innerText = name;
    document.getElementById('adj_current_stock').innerText = curStock;
    new bootstrap.Modal(document.getElementById('stockAdjustModal')).show();
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
    new bootstrap.Modal(document.getElementById('barcodeModal')).show();
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
