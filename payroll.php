<?php
require_once 'config.php';
require_once 'payroll_helper.php';
authorize(['admin', 'hr']);

$message = '';
$error = '';

// Handle Payroll Calculation / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['calculate_payroll']) || isset($_POST['edit_payroll']))) {
    $employee_id = (int)$_POST['employee_id'];
    $cutoff_start = $_POST['cutoff_start'];
    $cutoff_end = $_POST['cutoff_end'];
    $bonus = (float)$_POST['bonus'];
    $is_edit = isset($_POST['edit_payroll']);
    $payroll_id = $is_edit ? (int)$_POST['payroll_id'] : null;

    try {
        $pdo->beginTransaction();
        
        if ($is_edit) {
            // Update bonus first so recalculatePayroll picks it up
            $stmt_upd_bonus = $pdo->prepare("UPDATE payroll SET bonus_pay = ?, cutoff_start = ?, cutoff_end = ? WHERE id = ?");
            $stmt_upd_bonus->execute([$bonus, $cutoff_start, $cutoff_end, $payroll_id]);
        } else {
            // Create initial record
            $stmt_ins = $pdo->prepare("INSERT INTO payroll (employee_id, cutoff_start, cutoff_end, bonus_pay, status, payroll_date) VALUES (?, ?, ?, ?, 'Pending', ?)");
            $stmt_ins->execute([$employee_id, $cutoff_start, $cutoff_end, $bonus, date('Y-m-d')]);
            $payroll_id = $pdo->lastInsertId();
        }

        // Use the centralized helper for all math and the single-operation UPDATE
        if (!recalculatePayroll($pdo, $payroll_id)) {
            throw new Exception("Failed to calculate payroll.");
        }

        // Fetch employee name for notifications
        $stmt_emp = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
        $stmt_emp->execute([$employee_id]);
        $emp_name = $stmt_emp->fetchColumn();

        // Notify Admin
        $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        $stmt_notif->execute([$_SESSION['user_id'], $is_edit ? 'Payroll Updated' : 'Payroll Generated', "Payroll for $emp_name ($cutoff_start to $cutoff_end) has been processed."]);

        if (!$is_edit) {
            // Notify Employee
            $stmt_user_id = $pdo->prepare("SELECT id FROM users WHERE employee_id = ?");
            $stmt_user_id->execute([$employee_id]);
            $target_user_id = $stmt_user_id->fetchColumn();
            
            if ($target_user_id) {
                $stmt_notif->execute([$target_user_id, 'New Payslip Available', "Your payslip for the period " . date('M d', strtotime($cutoff_start)) . " to " . date('M d', strtotime($cutoff_end)) . " is now available."]);
            }
        }

        $message = $is_edit ? "Payroll record updated successfully!" : "Payroll generated successfully!";
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM payroll WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($pdo, $_SESSION['user_id'], 'Delete Payroll', "Deleted payroll record ID: $id");
        $message = "Record deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Payroll List
$stmt = $pdo->prepare("SELECT p.*, e.name, e.employee_id as eid, e.salary as monthly_base
                       FROM payroll p 
                       JOIN employees e ON p.employee_id = e.id 
                       ORDER BY p.payroll_date DESC, e.name ASC");
$stmt->execute();
$payrolls = $stmt->fetchAll();

// Re-fetch active employees for the modal dropdown
$employees_list = $pdo->query("SELECT id, name, employee_id FROM employees WHERE status = 'Active' ORDER BY name ASC")->fetchAll();

// Calculate default cutoff dates (21st of current/prev month to 20th of current/next month)
$today = new DateTime();
$day = (int)$today->format('d');

if ($day >= 21) {
    $default_start = $today->format('Y-m-21');
    $next_month = clone $today;
    $next_month->modify('+1 month');
    $default_end = $next_month->format('Y-m-20');
} else {
    $prev_month = clone $today;
    $prev_month->modify('-1 month');
    $default_start = $prev_month->format('Y-m-21');
    $default_end = $today->format('Y-m-20');
}

include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold text-dark">Payroll Management</h2>
        <p class="text-muted small mb-0">Attendance-based Salary Computation (Divisor: 22)</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm"><i class="fas fa-check-circle me-2"></i> <?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Employee</th>
                        <th>Period</th>
                        <th>Days Worked</th>
                        <th>Gross Pay</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payrolls as $p): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo $p['name']; ?></div>
                            <div class="text-muted smaller"><?php echo $p['eid']; ?></div>
                        </td>
                        <td class="small">
                            <?php 
                                echo date('F d, Y', strtotime($p['cutoff_start'])) . ' - ' . date('F d, Y', strtotime($p['cutoff_end']));
                            ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <?php echo number_format($p['days_worked'], 3); ?> Days
                            </span>
                        </td>
                        <td class="fw-bold">
                            ₱<?php echo number_format($p['gross_pay'], 2); ?>
                        </td>
                        <td>
                            <span class="text-danger small fw-bold" data-bs-toggle="tooltip" data-bs-html="true" title="SSS: ₱<?php echo number_format($p['sss'], 2); ?><br>PhilHealth: ₱<?php echo number_format($p['philhealth'], 2); ?><br>Pag-IBIG: ₱<?php echo number_format($p['pagibig'], 2); ?><br>Tax: ₱<?php echo number_format($p['withholding_tax'], 2); ?>">
                                -₱<?php echo number_format($p['total_deductions'], 2); ?> <i class="fas fa-info-circle smaller"></i>
                            </span>
                        </td>
                        <td class="text-success fw-bold">₱<?php echo number_format($p['net_pay'], 2); ?></td>
                        <td class="text-end pe-4">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary edit-payroll-btn"
                                        data-id="<?php echo $p['id']; ?>"
                                        data-eid="<?php echo $p['employee_id']; ?>"
                                        data-start="<?php echo $p['cutoff_start']; ?>"
                                        data-end="<?php echo $p['cutoff_end']; ?>"
                                        data-bonus="<?php echo $p['bonus_pay']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editPayrollModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="payslip_gen.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="fas fa-file-invoice"></i></a>
                                <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this payroll record?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="calculatePayrollModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Calculate Cutoff Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="calculate_payroll" value="1">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Employee</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">Choose employee...</option>
                            <?php foreach ($employees_list as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo h($emp['name']); ?> (<?php echo h($emp['employee_id']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Start Date</label>
                            <input type="date" name="cutoff_start" class="form-control" value="<?php echo $default_start; ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">End Date</label>
                            <input type="date" name="cutoff_end" class="form-control" value="<?php echo $default_end; ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Bonus/Adjustments (₱)</label>
                        <input type="number" step="0.01" name="bonus" class="form-control" value="0.00">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Calculate & Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editPayrollModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Payroll Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="edit_payroll" value="1">
                <input type="hidden" name="payroll_id" id="edit_payroll_id">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Employee</label>
                        <select name="employee_id" id="edit_employee_id" class="form-select" required>
                            <?php foreach ($employees_list as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo h($emp['name']); ?> (<?php echo h($emp['employee_id']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Start Date</label>
                            <input type="date" name="cutoff_start" id="edit_cutoff_start" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">End Date</label>
                            <input type="date" name="cutoff_end" id="edit_cutoff_end" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold">Additional Bonus (₱)</label>
                        <input type="number" step="0.01" name="bonus" id="edit_bonus" class="form-control" value="0.00">
                    </div>
                    <div class="alert alert-info smaller border-0 mb-0">
                        <i class="fas fa-info-circle me-1"></i> Editing will recalculate all values based on current attendance logs and salary rates.
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Update & Recalculate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
  $('[data-bs-toggle="tooltip"]').tooltip();

  $('.edit-payroll-btn').on('click', function() {
        const data = $(this).data();
        $('#edit_payroll_id').val(data.id);
        $('#edit_employee_id').val(data.eid);
        $('#edit_cutoff_start').val(data.start);
        $('#edit_cutoff_end').val(data.end);
        $('#edit_bonus').val(data.bonus);
    });
});
</script>

<?php include 'footer.php'; ?>
