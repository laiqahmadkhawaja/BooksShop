<?php
// schools.php - School, College & Academic Book Lists & Packages
require_once __DIR__ . '/config/app.php';
requireAuth();

$action = $_GET['action'] ?? '';
$selectedClassId = (int)($_GET['class_id'] ?? 0);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Add / Edit School
    if (isset($_POST['save_school'])) {
        $schoolId = (int)($_POST['school_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($name) {
            if ($schoolId > 0) {
                dbQuery("UPDATE schools SET name=?, contact_person=?, phone=?, email=?, address=?, notes=? WHERE id=?", 
                        [$name, $contact, $phone, $email, $address, $notes, $schoolId]);
                setFlash('success', "School updated successfully.");
            } else {
                dbQuery("INSERT INTO schools (name, contact_person, phone, email, address, notes) VALUES (?, ?, ?, ?, ?, ?)",
                        [$name, $contact, $phone, $email, $address, $notes]);
                setFlash('success', "School profile created successfully.");
            }
        }
        redirect("schools.php");
    }

    // 2. Add / Edit Class Syllabus Package
    if (isset($_POST['save_class'])) {
        $classId = (int)($_POST['class_id'] ?? 0);
        $schoolId = (int)$_POST['school_id'];
        $className = trim($_POST['class_name'] ?? '');
        $session = trim($_POST['academic_session'] ?? '2026-2027');
        $notes = trim($_POST['notes'] ?? '');

        if ($schoolId && $className) {
            if ($classId > 0) {
                dbQuery("UPDATE school_classes SET school_id=?, class_name=?, academic_session=?, notes=? WHERE id=?",
                        [$schoolId, $className, $session, $notes, $classId]);
                setFlash('success', "Class package updated.");
            } else {
                dbQuery("INSERT INTO school_classes (school_id, class_name, academic_session, notes) VALUES (?, ?, ?, ?)",
                        [$schoolId, $className, $session, $notes]);
                $classId = dbInsertId();
                setFlash('success', "Class package created. Now add syllabus books and stationery.");
            }
            redirect("schools.php?class_id=$classId");
        }
    }

    // 3. Add Item to Class Syllabus
    if (isset($_POST['add_class_item'])) {
        $classId = (int)$_POST['school_class_id'];
        $productId = (int)$_POST['product_id'];
        $qty = (float)($_POST['quantity_needed'] ?? 1);
        $customPrice = !empty($_POST['custom_price']) ? (float)$_POST['custom_price'] : null;
        $isMandatory = isset($_POST['is_mandatory']) ? 1 : 0;

        if ($classId > 0 && $productId > 0) {
            dbQuery("INSERT INTO school_class_items (school_class_id, product_id, is_mandatory, quantity_needed, custom_price)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantity_needed = VALUES(quantity_needed), custom_price = VALUES(custom_price)",
                     [$classId, $productId, $isMandatory, $qty, $customPrice]);
            setFlash('success', "Item added to class syllabus package.");
        }
        redirect("schools.php?class_id=$classId");
    }

    // 4. Remove Item from Syllabus
    if (isset($_POST['delete_class_item'])) {
        $itemId = (int)$_POST['item_id'];
        $classId = (int)$_POST['class_id'];
        dbQuery("DELETE FROM school_class_items WHERE id = ?", [$itemId]);
        setFlash('success', "Item removed from syllabus.");
        redirect("schools.php?class_id=$classId");
    }
}

// Fetch schools and classes
$schools = dbFetchAll("SELECT * FROM schools WHERE status = 1 ORDER BY name ASC");
$classes = dbFetchAll("SELECT sc.*, s.name as school_name, 
                       (SELECT COUNT(*) FROM school_class_items WHERE school_class_id = sc.id) as total_items
                       FROM school_classes sc
                       JOIN schools s ON s.id = sc.school_id
                       ORDER BY s.name ASC, sc.class_name ASC");

if (!$selectedClassId && !empty($classes)) {
    $selectedClassId = $classes[0]['id'];
}

// Fetch selected class details
$currentClass = null;
$packageItems = [];
$packageTotalPrice = 0;
$packageTotalCost = 0;

if ($selectedClassId) {
    $currentClass = dbFetchOne("SELECT sc.*, s.name as school_name, s.phone as school_phone, s.address as school_address 
                                FROM school_classes sc 
                                JOIN schools s ON s.id = sc.school_id 
                                WHERE sc.id = ?", [$selectedClassId]);

    $packageItems = dbFetchAll("SELECT sci.*, p.title, p.product_code, p.barcode, p.product_type, p.unit, 
                                       p.purchase_price, p.retail_price, p.current_stock, p.min_stock_alert, p.publisher, p.author
                                FROM school_class_items sci
                                JOIN products p ON p.id = sci.product_id
                                WHERE sci.school_class_id = ?
                                ORDER BY p.product_type ASC, p.title ASC", [$selectedClassId]);

    foreach ($packageItems as $pi) {
        $price = $pi['custom_price'] !== null ? (float)$pi['custom_price'] : (float)$pi['retail_price'];
        $packageTotalPrice += ($price * (float)$pi['quantity_needed']);
        $packageTotalCost += ((float)$pi['purchase_price'] * (float)$pi['quantity_needed']);
    }
}

// All products list for the syllabus item selector
$allProducts = dbFetchAll("SELECT id, title, product_code, product_type, retail_price, current_stock FROM products WHERE status = 1 ORDER BY title ASC");

$pageTitle = 'School Book Lists & Packages';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-graduation-cap text-info me-2"></i> Academic Book Lists & Class Packages</h4>
        <small class="text-muted">Manage school syllabus, class-wise textbook packages, stationery lists & bulk orders</small>
    </div>
    <div class="d-flex gap-2">
        <a href="school_form.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-school me-1"></i> Add School Profile
        </a>
        <a href="school_class_form.php" class="btn btn-info text-white btn-sm">
            <i class="fa-solid fa-plus-circle me-1"></i> Create Class Syllabus Package
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- LEFT COLUMN: Schools & Classes Sidebar (30%) -->
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-layer-group text-primary me-2"></i> Syllabus Packages</h6>
            </div>
            <div class="list-group list-group-flush directory-list-scroll">
                <?php if (empty($classes)): ?>
                    <div class="p-4 text-center text-muted">No classes created yet. Click "Create Class Syllabus Package".</div>
                <?php else: ?>
                    <?php foreach ($classes as $c): ?>
                        <a href="schools.php?class_id=<?= $c['id'] ?>" class="list-group-item list-group-item-action <?= $selectedClassId == $c['id'] ? 'active' : '' ?> py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge <?= $selectedClassId == $c['id'] ? 'bg-light text-dark' : 'bg-primary-subtle text-primary' ?>" style="font-size: 0.7rem;">
                                    <?= htmlspecialchars($c['school_name']) ?>
                                </span>
                                <span class="badge <?= $selectedClassId == $c['id'] ? 'bg-white text-dark' : 'bg-secondary' ?>" style="font-size: 0.7rem;">
                                    <?= $c['total_items'] ?> items
                                </span>
                            </div>
                            <h6 class="mb-1 mt-1 fw-bold"><?= htmlspecialchars($c['class_name']) ?></h6>
                            <small class="<?= $selectedClassId == $c['id'] ? 'text-white-50' : 'text-muted' ?>">
                                Session: <?= htmlspecialchars($c['academic_session']) ?>
                            </small>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Package Breakdown & Items Manager (70%) -->
    <div class="col-lg-8">
        <?php if (!$currentClass): ?>
            <div class="card shadow-sm p-5 text-center text-muted">
                <i class="fa-solid fa-book-bookmark fa-3x mb-3 text-secondary opacity-50"></i>
                <h5>Select a class syllabus package on the left to view its complete booklist.</h5>
            </div>
        <?php else: ?>
            <!-- Package Header Banner -->
            <div class="card shadow-sm mb-3">
                <div class="card-body p-4 bg-light border-bottom">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <span class="badge bg-primary mb-1"><?= htmlspecialchars($currentClass['school_name']) ?></span>
                            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($currentClass['class_name']) ?></h4>
                            <div class="small text-muted">
                                <i class="fa-solid fa-calendar me-1"></i> Session: <strong><?= htmlspecialchars($currentClass['academic_session']) ?></strong>
                                <?php if ($currentClass['school_phone']): ?>
                                    &bull; <i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($currentClass['school_phone']) ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Package Cost & Retail Pill -->
                        <div class="text-start text-sm-end">
                            <div class="small text-muted">Complete Course Package Price:</div>
                            <h3 class="fw-bold text-success mb-0"><?= formatCurrency($packageTotalPrice) ?></h3>
                            <span class="small text-muted">Estimated Margin: <?= formatCurrency($packageTotalPrice - $packageTotalCost) ?></span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                        <a href="pos.php" class="btn btn-pos btn-sm shadow-sm">
                            <i class="fa-solid fa-cart-arrow-down me-1"></i> Open in POS Terminal
                        </a>
                        <a href="school_item_form.php?class_id=<?= $selectedClassId ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fa-solid fa-plus me-1"></i> Add Book / Stationery Item
                        </a>
                        <a href="school_class_form.php?id=<?= $selectedClassId ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Class
                        </a>
                        <a href="print_report.php?report=school_syllabus&class_id=<?= $selectedClassId ?>" target="_blank" class="btn btn-outline-dark btn-sm">
                            <i class="fa-solid fa-print me-1"></i> Print Syllabus Booklist (New Page)
                        </a>
                    </div>
                </div>

                <!-- Package Items Table -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 40%;">Title / Book Description</th>
                                    <th style="width: 15%;">Type</th>
                                    <th style="width: 10%;" class="text-center">Required Qty</th>
                                    <th style="width: 12%;" class="text-center">Item Price</th>
                                    <th style="width: 10%;" class="text-center">Stock Alert</th>
                                    <th style="width: 8%;" class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($packageItems)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            No items added to this package yet. Click "Add Book / Stationery Item" above.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $idx = 1; foreach ($packageItems as $item): ?>
                                        <?php 
                                            $itemPrice = $item['custom_price'] !== null ? (float)$item['custom_price'] : (float)$item['retail_price'];
                                            $hasShortage = $item['current_stock'] < $item['quantity_needed'];
                                        ?>
                                        <tr>
                                            <td><?= $idx++ ?></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($item['title']) ?></div>
                                                <div class="small text-muted">
                                                    <?= htmlspecialchars($item['product_code']) ?>
                                                    <?php if ($item['publisher']): ?> &bull; <?= htmlspecialchars($item['publisher']) ?><?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?= $item['product_type'] === 'book' ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-dark' ?>">
                                                    <?= ucfirst($item['product_type']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center fw-bold"><?= (float)$item['quantity_needed'] ?> <?= htmlspecialchars($item['unit']) ?></td>
                                            <td class="text-center fw-bold text-primary"><?= formatCurrency($itemPrice) ?></td>
                                            <td class="text-center">
                                                <?php if ($item['current_stock'] <= 0): ?>
                                                    <span class="badge bg-danger">OUT OF STOCK (0)</span>
                                                <?php elseif ($hasShortage): ?>
                                                    <span class="badge bg-warning text-dark">Stock: <?= (float)$item['current_stock'] ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">In Stock (<?= (float)$item['current_stock'] ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <form method="POST" action="schools.php" onsubmit="return confirm('Remove this item from package?')">
                                                    <input type="hidden" name="delete_class_item" value="1">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
