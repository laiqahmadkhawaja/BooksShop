<?php
// expense_form.php - Record Expense Voucher Full Page (Zero-Modal)
require_once __DIR__ . '/config/app.php';
requireAuth();

$accounts = dbFetchAll("SELECT * FROM accounts WHERE status = 1 ORDER BY id ASC");
$categories = dbFetchAll("SELECT * FROM expense_categories WHERE type = 'expense' ORDER BY name ASC");

$pageTitle = 'Record Expense Voucher';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-12 col-md-6 mb-2 mb-md-0">
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-arrow-trend-down text-danger me-2"></i> Record Expense Voucher</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="accounting.php" class="text-decoration-none">Accounting</a></li>
                <li class="breadcrumb-item active" aria-current="page">Expense</li>
            </ol>
        </nav>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="expense_categories.php" class="btn btn-outline-secondary btn-sm me-2">
            <i class="fa-solid fa-tags me-1"></i> Manage Categories
        </a>
        <a href="accounting.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Accounts
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white py-3">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-receipt me-2"></i> Cash / Bank Outflow Voucher</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="accounting.php">
                    <input type="hidden" name="save_expense" value="1">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pay From Account *</label>
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
                            <label class="form-label small fw-bold mb-0">Expense Category / Head *</label>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 fw-semibold" style="font-size: 0.8rem;" onclick="toggleInlineCategoryForm()">
                                <i class="fa-solid fa-plus-circle me-1"></i> + New Category
                            </button>
                        </div>
                        <select name="category_name" id="categorySelect" class="form-select form-select-lg" required onchange="handleCategoryChange(this)">
                            <option value="">-- Select Expense Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="__new__" class="fw-bold text-danger">+ Add New Category...</option>
                        </select>

                        <!-- In-Page Inline Expandable Category Creator (Zero-Modal) -->
                        <div id="inlineNewCatCard" class="card mt-2 border-danger-subtle bg-light shadow-sm" style="display: none;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold small text-danger">
                                        <i class="fa-solid fa-folder-plus me-1"></i> Add New Expense Category
                                    </span>
                                    <button type="button" class="btn-close btn-sm" onclick="closeInlineCategoryForm()" aria-label="Close"></button>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold mb-1">Category Name *</label>
                                    <input type="text" id="inlineCatName" name="new_category_name" class="form-control" placeholder="e.g. Generator Fuel, Marketing, Shipping">
                                    <div id="inlineCatError" class="text-danger small mt-1" style="display: none;"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold mb-1">Description / Notes (Optional)</label>
                                    <textarea id="inlineCatDesc" class="form-control form-control-sm" rows="2" placeholder="Brief explanation of this expense head"></textarea>
                                </div>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="closeInlineCategoryForm()">Cancel</button>
                                    <button type="button" class="btn btn-danger btn-sm px-3 fw-bold shadow-sm" onclick="saveCategoryInline('expense')">
                                        <i class="fa-solid fa-check me-1"></i> Save & Select
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Expense Amount (Rs.) *</label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" step="0.01" min="1" name="amount" id="expenseAmountInput" class="form-control form-control-lg fw-bold text-danger" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Voucher / Bill #</label>
                            <input type="text" name="reference_no" class="form-control" value="EXP-<?= date('Ymd-His') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Payment Description / Remarks</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Details of the expense (paid to whom, invoice #, etc.)"></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="accounting.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-danger px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-check me-1"></i> Post Expense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function handleCategoryChange(select) {
    const inlineCard = document.getElementById('inlineNewCatCard');
    const inlineInput = document.getElementById('inlineCatName');
    if (select.value === '__new__') {
        inlineCard.style.display = 'block';
        inlineInput.focus();
    } else {
        inlineCard.style.display = 'none';
        inlineInput.value = '';
    }
}

function toggleInlineCategoryForm() {
    const inlineCard = document.getElementById('inlineNewCatCard');
    const inlineInput = document.getElementById('inlineCatName');
    const select = document.getElementById('categorySelect');
    if (inlineCard.style.display === 'none' || inlineCard.style.display === '') {
        inlineCard.style.display = 'block';
        select.value = '__new__';
        inlineInput.focus();
    } else {
        closeInlineCategoryForm();
    }
}

function closeInlineCategoryForm() {
    const inlineCard = document.getElementById('inlineNewCatCard');
    const inlineInput = document.getElementById('inlineCatName');
    const inlineDesc = document.getElementById('inlineCatDesc');
    const errDiv = document.getElementById('inlineCatError');
    const select = document.getElementById('categorySelect');

    inlineCard.style.display = 'none';
    inlineInput.value = '';
    if (inlineDesc) inlineDesc.value = '';
    if (errDiv) errDiv.style.display = 'none';

    if (select.value === '__new__') {
        select.value = select.options[1] ? select.options[1].value : '';
    }
}

function saveCategoryInline(type) {
    const nameInput = document.getElementById('inlineCatName');
    const descInput = document.getElementById('inlineCatDesc');
    const errDiv = document.getElementById('inlineCatError');
    const name = nameInput.value.trim();
    const desc = descInput ? descInput.value.trim() : '';

    if (!name) {
        errDiv.innerText = 'Category name is required.';
        errDiv.style.display = 'block';
        nameInput.focus();
        return;
    }
    errDiv.style.display = 'none';

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
            // Smoothly close inline form and focus amount input
            document.getElementById('inlineNewCatCard').style.display = 'none';
            nameInput.value = '';
            if (descInput) descInput.value = '';
            const amtInput = document.getElementById('expenseAmountInput');
            if (amtInput) amtInput.focus();
        } else {
            errDiv.innerText = res.message || 'Error saving category.';
            errDiv.style.display = 'block';
        }
    })
    .catch(err => {
        console.error(err);
        errDiv.innerText = 'Network error while saving category.';
        errDiv.style.display = 'block';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
