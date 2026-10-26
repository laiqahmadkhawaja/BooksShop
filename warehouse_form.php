<?php
// warehouse_form.php - Add or Edit Warehouse / Godown Facility
require_once __DIR__ . '/config/app.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
$warehouse = [
    'id' => 0,
    'warehouse_code' => '',
    'name' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'city' => '',
    'manager_name' => '',
    'is_primary' => 0,
    'status' => 1
];

if ($id > 0) {
    $existing = dbFetchOne("SELECT * FROM warehouses WHERE id = ?", [$id]);
    if ($existing) {
        $warehouse = $existing;
    } else {
        setFlash('danger', 'Warehouse not found.');
        redirect('warehouses.php');
    }
}

// Handle Save POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_warehouse'])) {
    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['warehouse_code'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $manager = trim($_POST['manager_name'] ?? '');
    $isPrimary = isset($_POST['is_primary']) ? 1 : 0;
    $status = isset($_POST['status']) ? 1 : 0;

    if (empty($name)) {
        setFlash('danger', 'Warehouse name is required.');
    } else {
        if (empty($code)) {
            $code = 'WH-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        }

        // Check if code taken
        $check = dbFetchOne("SELECT id FROM warehouses WHERE warehouse_code = ? AND id != ?", [$code, $id]);
        if ($check) {
            setFlash('danger', "Warehouse code '$code' is already in use by another facility.");
        } else {
            if ($isPrimary) {
                dbQuery("UPDATE warehouses SET is_primary = 0");
            }

            if ($id > 0) {
                dbQuery("UPDATE warehouses SET warehouse_code=?, name=?, phone=?, email=?, address=?, city=?, manager_name=?, is_primary=?, status=? WHERE id=?",
                        [$code, $name, $phone, $email, $address, $city, $manager, $isPrimary, $status, $id]);
                setFlash('success', "Warehouse '$name' updated successfully.");
            } else {
                dbQuery("INSERT INTO warehouses (warehouse_code, name, phone, email, address, city, manager_name, is_primary, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                         [$code, $name, $phone, $email, $address, $city, $manager, $isPrimary, $status]);
                setFlash('success', "Warehouse '$name' added successfully.");
            }
            redirect('warehouses.php');
        }
    }
}

$pageTitle = $id > 0 ? 'Edit Warehouse' : 'Add New Warehouse';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-9 col-lg-7">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <a href="warehouses.php" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Warehouses
                </a>
                <h4 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-warehouse text-warning me-2"></i> <?= $pageTitle ?>
                </h4>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="save_warehouse" value="1">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Warehouse / Godown Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required
                                   placeholder="e.g. Central Godown, Basement Storage, Annex 2"
                                   value="<?= htmlspecialchars($warehouse['name']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Warehouse Code</label>
                            <input type="text" name="warehouse_code" class="form-control font-monospace"
                                   placeholder="e.g. WH-01, GODOWN-B"
                                   value="<?= htmlspecialchars($warehouse['warehouse_code']) ?>">
                            <div class="form-text small">Leave blank to auto-generate.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Manager / In-Charge Name</label>
                            <input type="text" name="manager_name" class="form-control"
                                   placeholder="e.g. Muhammad Aslam"
                                   value="<?= htmlspecialchars($warehouse['manager_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Contact Phone Number</label>
                            <input type="text" name="phone" class="form-control"
                                   placeholder="e.g. +92 300 1234567"
                                   value="<?= htmlspecialchars($warehouse['phone']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control"
                                   placeholder="e.g. godown@kitabghar.pk"
                                   value="<?= htmlspecialchars($warehouse['email']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">City / Area</label>
                            <input type="text" name="city" class="form-control"
                                   placeholder="e.g. Lahore / Urdu Bazaar"
                                   value="<?= htmlspecialchars($warehouse['city']) ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Full Physical Address</label>
                            <textarea name="address" class="form-control" rows="2"
                                      placeholder="Street address, building name, plot number"><?= htmlspecialchars($warehouse['address']) ?></textarea>
                        </div>

                        <div class="col-12 border-top pt-3 mt-4">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_primary" id="is_primary" value="1"
                                       <?= $warehouse['is_primary'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="is_primary">
                                    Set as Primary / Main Godown
                                </label>
                                <div class="form-text small">Primary godown is chosen by default during inter-location stock transfers.</div>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="status" id="wh_status" value="1"
                                       <?= $warehouse['status'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="wh_status">
                                    Facility Active & Operational
                                </label>
                            </div>
                        </div>

                        <div class="col-12 text-end pt-3 border-top mt-4">
                            <a href="warehouses.php" class="btn btn-secondary px-4 me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4 fw-bold">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Warehouse
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
