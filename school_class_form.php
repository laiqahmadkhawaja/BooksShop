<?php
// school_class_form.php - Dedicated page for Creating / Editing a Class Syllabus Package
require_once __DIR__ . '/config/app.php';
requireAuth();

$classId = (int)($_GET['id'] ?? 0);
$defaultSchoolId = (int)($_GET['school_id'] ?? 0);

$classData = [
    'id' => 0,
    'school_id' => $defaultSchoolId,
    'class_name' => '',
    'academic_session' => '2026-2027',
    'notes' => ''
];

if ($classId > 0) {
    $existing = dbFetchOne("SELECT * FROM school_classes WHERE id = ?", [$classId]);
    if ($existing) {
        $classData = $existing;
    }
}

$schools = dbFetchAll("SELECT * FROM schools WHERE status = 1 ORDER BY name ASC");

$pageTitle = ($classId > 0 ? 'Edit' : 'Create') . ' Class Syllabus Package';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-md-9">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <a href="schools.php<?= $classId > 0 ? '?class_id=' . $classId : '' ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to School Packages
                </a>
                <h4 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-graduation-cap text-info me-2"></i>
                    <?= $classId > 0 ? 'Edit Class Package' : 'Create Class Syllabus Package' ?>
                </h4>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" action="schools.php">
                    <input type="hidden" name="save_class" value="1">
                    <input type="hidden" name="class_id" value="<?= $classData['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Select School / College <span class="text-danger">*</span></label>
                        <select name="school_id" class="form-select form-select-lg" required>
                            <option value="">-- Choose School --</option>
                            <?php foreach ($schools as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $classData['school_id'] == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Class Name / Grade / Section <span class="text-danger">*</span></label>
                        <input type="text" name="class_name" class="form-control form-control-lg" required
                               value="<?= htmlspecialchars($classData['class_name']) ?>"
                               placeholder="e.g. Grade 5, Class 9 (Computer Science), Prep Rose">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Academic Session</label>
                        <input type="text" name="academic_session" class="form-control" 
                               value="<?= htmlspecialchars($classData['academic_session']) ?>"
                               placeholder="e.g. 2026-2027">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Package Notes / Instructions</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Syllabus edition, notes for parents..."><?= htmlspecialchars($classData['notes']) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="schools.php<?= $classId > 0 ? '?class_id=' . $classId : '' ?>" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-info text-white px-4">
                            <i class="fa-solid fa-check me-1"></i> <?= $classId > 0 ? 'Update Package' : 'Create Package' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
