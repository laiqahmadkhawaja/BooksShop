<?php
// income_form.php - Record Non-Sale Income Voucher Full Page
require_once __DIR__ . '/config/app.php';
requireAuth();

$accounts = dbFetchAll("SELECT * FROM accounts WHERE status = 1 ORDER BY id ASC");
$categories = dbFetchAll("SELECT * FROM expense_categories WHERE type = 'income' ORDER BY name ASC");

$pageTitle = 'Record Income Voucher';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-arrow-trend-up text-success me-2"></i> Record Income Voucher</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="accounting.php" class="text-decoration-none">Accounting</a></li>
                <li class="breadcrumb-item active" aria-current="page">Income</li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="accounting.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Accounts
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white py-3">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-hand-holding-dollar me-2"></i> Cash / Bank Inflow Voucher</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="accounting.php">
                    <input type="hidden" name="save_income" value="1">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Deposit To Account *</label>
                        <select name="account_id" class="form-select form-select-lg" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>">
                                    <?= htmlspecialchars($acc['account_name']) ?> (Bal: <?= formatCurrency($acc['balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold mb-0">Income Category / Source *</label>
                            <button type="button" class="btn btn-sm btn-outline-success py-0 px-2 fw-semibold" style="font-size: 0.8rem;" onclick="showAddCategoryModal()">
                                <i class="fa-solid fa-plus-circle me-1"></i> + New Category
                            </button>
                        </div>
                        <select name="category_name" id="categorySelect" class="form-select form-select-lg" required onchange="handleCategoryChange(this)">
                            <option value="">-- Select Income Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="__new__" class="fw-bold text-success">+ Add New Category...</option>
                        </select>

                        <!-- Inline quick-input if user selects '+ Add New Category...' -->
                        <div id="inlineNewCatDiv" class="mt-2" style="display: none;">
                            <div class="input-group">
                                <span class="input-group-text bg-success-subtle text-success fw-bold"><i class="fa-solid fa-tag"></i></span>
                                <input type="text" id="inlineNewCatInput" name="new_category_name" class="form-control" placeholder="Enter new income category name...">
                                <button type="button" class="btn btn-success" onclick="saveCategoryViaAjax('income')">
                                    <i class="fa-solid fa-check me-1"></i> Save
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="cancelInlineCategory()">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                            <div class="form-text text-muted small">Enter category name and click Save, or fill the voucher and it will be created automatically.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Income Amount (Rs.) *</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" step="0.01" min="1" name="amount" class="form-control form-control-lg fw-bold text-success" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Voucher / Receipt #</label>
                            <input type="text" name="reference_no" class="form-control" value="INC-<?= date('Ymd-His') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Description / Notes</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Details of income source"></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="accounting.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-check me-1"></i> Post Income
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Quick Add Income Category -->
<div class="modal fade" id="quickAddCatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle me-2"></i> Add New Income Category</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Category Name *</label>
                    <input type="text" id="modalCatName" class="form-control form-control-lg" placeholder="e.g. Scrap Sale, Consulting, Rental Income" required>
                    <div id="modalCatError" class="text-danger small mt-1" style="display: none;"></div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Description / Notes (Optional)</label>
                    <textarea id="modalCatDesc" class="form-control" rows="2" placeholder="Brief explanation of this income source"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="modalSaveBtn" class="btn btn-success btn-sm px-3 fw-bold" onclick="submitModalCategory('income')">
                    <i class="fa-solid fa-save me-1"></i> Save & Select
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function handleCategoryChange(select) {
    const inlineDiv = document.getElementById('inlineNewCatDiv');
    const inlineInput = document.getElementById('inlineNewCatInput');
    if (select.value === '__new__') {
        inlineDiv.style.display = 'block';
        inlineInput.focus();
    } else {
        inlineDiv.style.display = 'none';
        inlineInput.value = '';
    }
}

function showAddCategoryModal() {
    const modalEl = document.getElementById('quickAddCatModal');
    const modal = new bootstrap.Modal(modalEl);
    document.getElementById('modalCatName').value = '';
    document.getElementById('modalCatDesc').value = '';
    document.getElementById('modalCatError').style.display = 'none';
    modal.show();
    setTimeout(() => document.getElementById('modalCatName').focus(), 400);
}

function cancelInlineCategory() {
    const inlineDiv = document.getElementById('inlineNewCatDiv');
    const inlineInput = document.getElementById('inlineNewCatInput');
    const select = document.getElementById('categorySelect');
    inlineDiv.style.display = 'none';
    inlineInput.value = '';
    if (select.value === '__new__') {
        select.value = select.options[1] ? select.options[1].value : '';
    }
}

function saveCategoryViaAjax(type) {
    const input = document.getElementById('inlineNewCatInput');
    const name = input.value.trim();
    if (!name) {
        alert('Please enter a category name.');
        input.focus();
        return;
    }
    executeCategorySave(name, '', type, () => {
        document.getElementById('inlineNewCatDiv').style.display = 'none';
        document.getElementById('inlineNewCatInput').value = '';
    });
}

function submitModalCategory(type) {
    const nameInput = document.getElementById('modalCatName');
    const descInput = document.getElementById('modalCatDesc');
    const errDiv = document.getElementById('modalCatError');
    const name = nameInput.value.trim();
    const desc = descInput.value.trim();
    
    if (!name) {
        errDiv.innerText = 'Category name is required.';
        errDiv.style.display = 'block';
        nameInput.focus();
        return;
    }
    errDiv.style.display = 'none';
    
    executeCategorySave(name, desc, type, () => {
        const modalEl = document.getElementById('quickAddCatModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    });
}

function executeCategorySave(name, desc, type, callback) {
    const formData = new FormData();
    formData.append('ajax_add_category', '1');
    formData.append('name', name);
    formData.append('type', type);
    formData.append('description', desc);

    fetch('accounting.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const select = document.getElementById('categorySelect');
            let found = false;
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value.toLowerCase() === res.name.toLowerCase()) {
                    select.selectedIndex = i;
                    found = true;
                    break;
                }
            }
            if (!found) {
                const newOpt = new Option(res.name, res.name, true, true);
                const lastIdx = select.options.length - 1;
                if (lastIdx >= 0 && select.options[lastIdx].value === '__new__') {
                    select.add(newOpt, select.options[lastIdx]);
                } else {
                    select.add(newOpt);
                }
                select.value = res.name;
            }
            if (callback) callback();
        } else {
            alert(res.message || 'Error saving category.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error while saving category.');
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
