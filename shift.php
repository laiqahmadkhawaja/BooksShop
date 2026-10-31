<?php
// shift.php - Cashier & POS Register Shift Management
require_once __DIR__ . '/config/app.php';
requireAuth();
requirePagePermission('pos.php');

ensureShiftSchema();
$user = authUser();

// Handle Shift Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. START SHIFT
    if (isset($_POST['start_shift'])) {
        $existing = getCurrentShift($user['id']);
        if ($existing) {
            setFlash('warning', "You already have an active open Shift (#{$existing['id']}). Please close it before starting a new one.");
        } else {
            $openingCash = max(0, (float)($_POST['opening_cash'] ?? 0));
            $notes = trim($_POST['start_notes'] ?? '');
            
            dbQuery("INSERT INTO register_shifts (user_id, start_time, opening_cash, notes, status) 
                     VALUES (?, NOW(), ?, ?, 'open')", [$user['id'], $openingCash, $notes]);
            $newShiftId = dbInsertId();
            setFlash('success', "Shift #$newShiftId started successfully with opening cash " . formatCurrency($openingCash));
        }
        redirect('shift.php');
    }

    // 2. CLOSE SHIFT
    if (isset($_POST['close_shift'])) {
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $shift = dbFetchOne("SELECT * FROM register_shifts WHERE id = ? AND status = 'open'", [$shiftId]);

        if (!$shift) {
            setFlash('danger', "Active shift not found or already closed.");
            redirect('shift.php');
        }

        // Count Denominations
        $denoms = [5000, 1000, 500, 100, 50, 20, 10];
        $denomData = [];
        $totalPhysical = 0;
        foreach ($denoms as $d) {
            $count = max(0, (int)($_POST['d_' . $d] ?? 0));
            $denomData[$d] = $count;
            $totalPhysical += ($d * $count);
        }
        $coins = max(0, (float)($_POST['d_coins'] ?? 0));
        $denomData['coins'] = $coins;
        $totalPhysical += $coins;

        // Calculate Real-Time Shift Cash Movement
        $summary = getShiftCashSummary($shift);
        $systemExpected = (float)$summary['system_expected'];
        $variance = $totalPhysical - $systemExpected;

        $varianceNotes = trim($_POST['notes'] ?? '');
        $combinedNotes = $shift['notes'];
        if ($varianceNotes) {
            $combinedNotes = $combinedNotes ? ($combinedNotes . " | Close Note: " . $varianceNotes) : $varianceNotes;
        }

        // Close Shift
        dbQuery("UPDATE register_shifts 
                 SET end_time = NOW(),
                     cash_sales = ?,
                     customer_receipts_cash = ?,
                     other_cash_inflow = ?,
                     cash_outflow = ?,
                     system_expected_cash = ?,
                     physical_counted_cash = ?,
                     variance = ?,
                     denominations = ?,
                     notes = ?,
                     status = 'closed'
                 WHERE id = ?", [
                     $summary['cash_sales'],
                     $summary['customer_receipts'],
                     $summary['other_inflows'],
                     $summary['total_outflows'],
                     $systemExpected,
                     $totalPhysical,
                     $variance,
                     json_encode($denomData),
                     $combinedNotes,
                     $shiftId
                 ]);

        setFlash('success', "Shift #$shiftId closed successfully! Counted: " . formatCurrency($totalPhysical) . ", Variance: " . formatCurrency($variance));
        redirect("print_shift.php?id=$shiftId");
    }
}

// Current Active Shift
$activeShift = getCurrentShift($user['id']);
$shiftSummary = $activeShift ? getShiftCashSummary($activeShift) : null;

// Suggested Opening Cash from Drawer Account (ID 1)
$drawerCashBalance = (float)(dbFetchOne("SELECT balance FROM accounts WHERE id = 1")['balance'] ?? 0);

// Past Shifts History
$shiftsHistory = dbFetchAll("SELECT rs.*, u.full_name as cashier_name, u.username 
                             FROM register_shifts rs 
                             JOIN users u ON u.id = rs.user_id 
                             ORDER BY rs.id DESC LIMIT 30");

$pageTitle = 'Register Shift Management';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Register Shift Management
        </h4>
        <small class="text-muted">Start, monitor cash movements, enter physical denomination counts, and close register shifts</small>
    </div>
    <div class="d-flex gap-2">
        <a href="pos.php" class="btn btn-pos btn-sm shadow-sm">
            <i class="fa-solid fa-cash-register me-1"></i> POS Billing Terminal (F2)
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <?php if ($activeShift): ?>
        <!-- ================= ACTIVE OPEN SHIFT VIEW ================= -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 border-top border-success border-4 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-success me-2 px-2 py-1"><i class="fa-solid fa-circle-dot me-1"></i> ACTIVE SHIFT</span>
                        <h5 class="fw-bold d-inline text-dark mb-0">Shift #<?= $activeShift['id'] ?></h5>
                    </div>
                    <span class="text-muted small">
                        Started: <strong><?= date('d M Y, h:i A', strtotime($activeShift['start_time'])) ?></strong>
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded border">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Cashier On Duty</small>
                                <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($activeShift['cashier_name'] ?: $user['full_name']) ?></span>
                                <div class="small text-muted">@<?= htmlspecialchars($activeShift['username'] ?: $user['username']) ?></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded border">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Shift Duration</small>
                                <?php
                                    $startTs = strtotime($activeShift['start_time']);
                                    $diffSec = time() - $startTs;
                                    $hours = floor($diffSec / 3600);
                                    $mins = floor(($diffSec % 3600) / 60);
                                ?>
                                <span class="fw-bold text-dark fs-6"><?= $hours ?> hrs <?= $mins ?> mins</span>
                                <div class="small text-success"><i class="fa-solid fa-clock me-1"></i> Currently Running</div>
                            </div>
                        </div>
                    </div>

                    <!-- Shift Cash Movement Breakdown -->
                    <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-chart-pie text-primary me-2"></i> Cash Movement During Shift</h6>
                    <div class="table-responsive border rounded mb-3">
                        <table class="table table-hover align-middle mb-0 small">
                            <tbody>
                                <tr class="table-light">
                                    <td class="fw-bold" style="width: 65%;">1. Opening Cash / Starting Float</td>
                                    <td class="text-end font-monospace fw-bold" style="width: 35%;"><?= formatCurrency($shiftSummary['opening_cash']) ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-3 text-success"><i class="fa-solid fa-arrow-down-long me-1"></i> Counter POS Cash Sales</td>
                                    <td class="text-end font-monospace text-success">+ <?= formatCurrency($shiftSummary['cash_sales']) ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-3 text-success"><i class="fa-solid fa-arrow-down-long me-1"></i> Customer Khata Recoveries in Cash</td>
                                    <td class="text-end font-monospace text-success">+ <?= formatCurrency($shiftSummary['customer_receipts']) ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-3 text-success"><i class="fa-solid fa-arrow-down-long me-1"></i> Other Cash Inflows & Bank Transfers In</td>
                                    <td class="text-end font-monospace text-success">+ <?= formatCurrency($shiftSummary['other_inflows']) ?></td>
                                </tr>
                                <tr class="fw-bold table-light">
                                    <td>Total Inflows Collected</td>
                                    <td class="text-end font-monospace text-success">+ <?= formatCurrency($shiftSummary['total_inflows']) ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-3 text-danger"><i class="fa-solid fa-arrow-up-long me-1"></i> Cash Purchases Paid</td>
                                    <td class="text-end font-monospace text-danger">- <?= formatCurrency($shiftSummary['cash_purchases']) ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-3 text-danger"><i class="fa-solid fa-arrow-up-long me-1"></i> Supplier / Publisher Khata Paid in Cash</td>
                                    <td class="text-end font-monospace text-danger">- <?= formatCurrency($shiftSummary['supplier_payments']) ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-3 text-danger"><i class="fa-solid fa-arrow-up-long me-1"></i> Operating Expenses from Drawer</td>
                                    <td class="text-end font-monospace text-danger">- <?= formatCurrency($shiftSummary['cash_expenses']) ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-3 text-danger"><i class="fa-solid fa-arrow-up-long me-1"></i> Cash Deposited / Transferred to Bank</td>
                                    <td class="text-end font-monospace text-danger">- <?= formatCurrency($shiftSummary['transfers_out']) ?></td>
                                </tr>
                                <tr class="fw-bold table-light">
                                    <td>Total Outflows Paid</td>
                                    <td class="text-end font-monospace text-danger">- <?= formatCurrency($shiftSummary['total_outflows']) ?></td>
                                </tr>
                                <tr class="table-primary fw-bold fs-6">
                                    <td>SYSTEM EXPECTED CLOSING CASH</td>
                                    <td class="text-end font-monospace text-primary"><?= formatCurrency($shiftSummary['system_expected']) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($activeShift['notes']): ?>
                        <div class="alert alert-light border small mb-0">
                            <strong>Shift Opening Note:</strong> <?= htmlspecialchars($activeShift['notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ================= CLOSE SHIFT FORM ================= -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 border-top border-danger border-4 h-100">
                <div class="card-header bg-dark text-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fa-solid fa-lock text-warning me-2"></i> Close Register Shift & Count Cash</h6>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">Count the physical currency notes and coins in the drawer. The system will compare with expected cash to verify reconciliation.</p>

                    <form method="POST" action="shift.php" onsubmit="return confirm('Are you sure you want to close this shift? This will finalize all cash counts and print the shift slip.')">
                        <input type="hidden" name="close_shift" value="1">
                        <input type="hidden" name="shift_id" value="<?= $activeShift['id'] ?>">

                        <!-- Denominations Table -->
                        <?php $denoms = [5000, 1000, 500, 100, 50, 20, 10]; ?>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr class="text-muted small">
                                        <th>Note</th>
                                        <th style="width: 110px;">Count</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($denoms as $d): ?>
                                        <tr>
                                            <td class="fw-bold text-dark">Rs. <?= number_format($d) ?></td>
                                            <td>
                                                <input type="number" min="0" step="1" name="d_<?= $d ?>" 
                                                       class="form-control form-control-sm text-center denom-shift-input fw-bold" 
                                                       data-value="<?= $d ?>" value="0" oninput="calculateShiftCash()">
                                            </td>
                                            <td class="text-end font-monospace text-muted denom-shift-subtotal" id="shift_sub_<?= $d ?>">Rs. 0.00</td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td class="fw-bold text-dark">Coins & Loose</td>
                                        <td>
                                            <input type="number" min="0" step="1" name="d_coins" id="shiftCoinsInput" 
                                                   class="form-control form-control-sm text-center fw-bold" value="0" oninput="calculateShiftCash()">
                                        </td>
                                        <td class="text-end font-monospace text-muted" id="shift_sub_coins">Rs. 0.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Live Reconciliation Summary Box -->
                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-muted text-uppercase fw-semibold">Physical Counted Cash:</span>
                                <h5 class="fw-bold text-primary mb-0 font-monospace" id="totalShiftPhysicalCount">Rs. 0.00</h5>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2 border-top pt-2">
                                <span class="small text-muted text-uppercase fw-semibold">System Expected:</span>
                                <span class="fw-bold text-dark font-monospace"><?= formatCurrency($shiftSummary['system_expected']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center border-top pt-2">
                                <span class="small fw-bold">Difference / Variance:</span>
                                <h6 class="fw-bold mb-0 font-monospace" id="shiftCashDifference">Rs. 0.00</h6>
                            </div>
                        </div>

                        <!-- Variance Explanation Note -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Variance Explanation & Shift Notes</label>
                            <textarea name="notes" class="form-control" rows="2" 
                                      placeholder="Explain reasons for any shortage or excess (e.g. Rs. 5 shortage due to lack of coin change)..."></textarea>
                            <small class="text-muted">This note will appear on the printed closing slip and permanent audit log.</small>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger btn-lg fw-bold shadow-sm">
                                <i class="fa-solid fa-lock me-2"></i> Save & Close Shift (Print Slip)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ================= NO ACTIVE SHIFT: START SHIFT FORM ================= -->
        <div class="col-lg-6 col-md-8 mx-auto">
            <div class="card shadow-sm border-0 border-top border-primary border-4 text-center">
                <div class="card-body p-4 p-md-5">
                    <div class="mb-3 text-primary">
                        <i class="fa-solid fa-door-open fa-3x"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">Start New Register Shift</h4>
                    <p class="text-muted small mb-4">You do not have an active register shift open. Start a shift to track drawer cash, sales, and end-of-shift reconciliation.</p>

                    <form method="POST" action="shift.php" class="text-start">
                        <input type="hidden" name="start_shift" value="1">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Opening Cash in Register Drawer (Starting Float) *</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light fw-bold">Rs.</span>
                                <input type="number" step="0.01" min="0" name="opening_cash" 
                                       class="form-control form-control-lg fw-bold text-success font-monospace" 
                                       value="<?= number_format($drawerCashBalance, 2, '.', '') ?>" required>
                            </div>
                            <small class="text-muted">Current drawer account balance: <strong><?= formatCurrency($drawerCashBalance) ?></strong></small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold">Starting Shift Notes (Optional)</label>
                            <input type="text" name="start_notes" class="form-control" placeholder="e.g. Morning counter shift, cash float verified">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm">
                                <i class="fa-solid fa-play me-2"></i> Open & Start Shift
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ================= SHIFTS AUDIT HISTORY TABLE ================= -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-list-check text-primary me-2"></i> Register Shifts Audit History
        </h6>
        <span class="badge bg-light text-secondary border">Last 30 Shifts</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light text-uppercase">
                    <tr>
                        <th>Shift #</th>
                        <th>Cashier</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th class="text-end">Opening Float</th>
                        <th class="text-end">Expected Cash</th>
                        <th class="text-end">Counted Cash</th>
                        <th class="text-center">Variance</th>
                        <th>Notes & Explanations</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shiftsHistory)): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">No shifts recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($shiftsHistory as $sh): ?>
                            <tr>
                                <td><strong class="text-primary">#<?= $sh['id'] ?></strong></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($sh['cashier_name'] ?: 'Cashier') ?></div>
                                    <div class="text-muted small">@<?= htmlspecialchars($sh['username'] ?: '') ?></div>
                                </td>
                                <td>
                                    <div><?= date('d M Y', strtotime($sh['start_time'])) ?></div>
                                    <div class="text-muted small"><?= date('h:i A', strtotime($sh['start_time'])) ?></div>
                                </td>
                                <td>
                                    <?php if ($sh['end_time']): ?>
                                        <div><?= date('d M Y', strtotime($sh['end_time'])) ?></div>
                                        <div class="text-muted small"><?= date('h:i A', strtotime($sh['end_time'])) ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success">Running...</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-monospace"><?= formatCurrency($sh['opening_cash']) ?></td>
                                <td class="text-end font-monospace"><?= formatCurrency($sh['system_expected_cash']) ?></td>
                                <td class="text-end font-monospace fw-bold text-primary"><?= formatCurrency($sh['physical_counted_cash']) ?></td>
                                <td class="text-center font-monospace">
                                    <?php if ($sh['status'] === 'closed'): ?>
                                        <?php if (abs($sh['variance']) < 0.01): ?>
                                            <span class="badge bg-success">Balanced (0.00)</span>
                                        <?php elseif ($sh['variance'] > 0): ?>
                                            <span class="badge bg-primary">+ <?= formatCurrency($sh['variance']) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">- <?= formatCurrency(abs($sh['variance'])) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sh['notes']): ?>
                                        <span class="small text-dark" title="<?= htmlspecialchars($sh['notes']) ?>">
                                            <?= htmlspecialchars(mb_strimwidth($sh['notes'], 0, 45, '...')) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($sh['status'] === 'open'): ?>
                                        <span class="badge bg-success">OPEN</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">CLOSED</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="print_shift.php?id=<?= $sh['id'] ?>" target="_blank" class="btn btn-outline-dark btn-sm py-1 px-2" title="Print Shift Reconciliation Slip">
                                        <i class="fa-solid fa-print me-1"></i> Print
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const systemExpectedCash = <?= $shiftSummary ? (float)$shiftSummary['system_expected'] : 0 ?>;

function calculateShiftCash() {
    let total = 0;
    document.querySelectorAll('.denom-shift-input').forEach(input => {
        const noteVal = parseFloat(input.dataset.value) || 0;
        const count = parseInt(input.value) || 0;
        const sub = noteVal * count;
        total += sub;
        const subEl = document.getElementById('shift_sub_' + noteVal);
        if (subEl) subEl.textContent = 'Rs. ' + sub.toLocaleString(undefined, {minimumFractionDigits: 2});
    });

    const coins = parseFloat(document.getElementById('shiftCoinsInput')?.value) || 0;
    total += coins;
    const subCoinsEl = document.getElementById('shift_sub_coins');
    if (subCoinsEl) subCoinsEl.textContent = 'Rs. ' + coins.toLocaleString(undefined, {minimumFractionDigits: 2});

    const totalEl = document.getElementById('totalShiftPhysicalCount');
    if (totalEl) totalEl.textContent = 'Rs. ' + total.toLocaleString(undefined, {minimumFractionDigits: 2});

    const diff = total - systemExpectedCash;
    const diffEl = document.getElementById('shiftCashDifference');
    if (diffEl) {
        if (Math.abs(diff) < 0.01) {
            diffEl.textContent = 'Rs. 0.00 (Balanced)';
            diffEl.className = 'fw-bold mb-0 font-monospace text-success';
        } else if (diff > 0) {
            diffEl.textContent = '+ Rs. ' + diff.toLocaleString(undefined, {minimumFractionDigits: 2}) + ' (Excess)';
            diffEl.className = 'fw-bold mb-0 font-monospace text-primary';
        } else {
            diffEl.textContent = '- Rs. ' + Math.abs(diff).toLocaleString(undefined, {minimumFractionDigits: 2}) + ' (Shortage)';
            diffEl.className = 'fw-bold mb-0 font-monospace text-danger';
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
