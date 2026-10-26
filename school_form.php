<?php
// school_form.php - Dedicated page for Adding / Editing a School Profile
require_once __DIR__ . '/config/app.php';
requireAuth();

$schoolId = (int)($_GET['id'] ?? 0);
$school = [
    'id' => 0,
    'name' => '',
    'contact_person' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'notes' => ''
];

if ($schoolId > 0) {
    $existing = dbFetchOne("SELECT * FROM schools WHERE id = ?", [$schoolId]);
    if ($existing) {
        $school = $existing;
    }
}

$pageTitle = ($schoolId > 0 ? 'Edit' : 'Add') . ' School Profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <a href="schools.php" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to School Packages
                </a>
                <h4 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-school text-primary me-2"></i>
                    <?= $schoolId > 0 ? 'Edit School Profile' : 'Add New School / College' ?>
                </h4>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="schools.php">
                    <input type="hidden" name="save_school" value="1">
                    <input type="hidden" name="school_id" value="<?= $school['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">School / College / Institution Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-lg" required 
                               value="<?= htmlspecialchars($school['name']) ?>"
                               placeholder="e.g. Lahore Grammar School, Army Public School">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Contact Person / Principal</label>
                            <input type="text" name="contact_person" class="form-control" 
                                   value="<?= htmlspecialchars($school['contact_person']) ?>"
                                   placeholder="e.g. Sir Asif, Madam Farah">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Phone / WhatsApp</label>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?= htmlspecialchars($school['phone']) ?>"
                                   placeholder="e.g. 03001234567">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($school['email']) ?>"
                                   placeholder="e.g. info@school.edu.pk">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Campus Address / City</label>
                            <input type="text" name="address" class="form-control" 
                                   value="<?= htmlspecialchars($school['address']) ?>"
                                   placeholder="e.g. Main Branch, GT Road, Gujranwala">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Internal Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Discounts negotiated, special instructions, contact timings..."><?= htmlspecialchars($school['notes']) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="schools.php" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fa-solid fa-save me-1"></i> <?= $schoolId > 0 ? 'Update School' : 'Save School' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
