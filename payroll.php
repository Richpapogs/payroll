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
        
        $stmt_emp = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt_emp->execute([$employee_id]);
        $employee = $stmt_emp->fetch();

        if (!$employee) throw new Exception("Employee not found.");

        if (!$is_edit) {
            $stmt_dup = $pdo->prepare("SELECT id FROM payroll WHERE employee_id = ? AND cutoff_start = ? AND cutoff_end = ?");
            $stmt_dup->execute([$employee_id, $cutoff_start, $cutoff_end]);
            if ($stmt_dup->rowCount() > 0) throw new Exception("Payroll already exists for this cutoff.");
        }

        $daily_rate = calculateDailyRate($employee['salary']);
        $hourly_rate = calculateHourlyRate($daily_rate);

        $stmt_att = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?");
        $stmt_att->execute([$employee_id, $cutoff_start, $cutoff_end]);
        $attendance = $stmt_att->fetchAll();

        $days_worked = 0;
        $total_ot_hrs = 0;
        $total_double_pay_days = 0;
        $total_late_mins = 0;
        $total_undertime_mins = 0;

        foreach ($attendance as $att) {
            if ($att['status'] === 'Present') {
                $days_worked++;
                if ($att['total_hours'] > STANDARD_HOURS) {
                    $total_ot_hrs += ($att['total_hours'] - STANDARD_HOURS);
                }
                if ($att['is_double_pay']) $total_double_pay_days++;
                $total_late_mins += $att['late_minutes'];
                $total_undertime_mins += $att['undertime_minutes'];
            } elseif ($att['status'] === 'Half-day') {
                $days_worked += 0.5;
                $total_late_mins += $att['late_minutes'];
                $total_undertime_mins += $att['undertime_minutes'];
            }
        }

        $base_pay = calculateBasePay($daily_rate, $days_worked);
        $calc = calculateGrossPay($base_pay, $total_ot_hrs, $hourly_rate, $bonus, $total_double_pay_days, $daily_rate, $total_late_mins, $total_undertime_mins);
        $gross_pay = $calc['gross'];
        
        $sss = calculateSSS($employee['salary']);
        $philhealth = calculatePhilHealth($employee['salary']);
        $pagibig = calculatePagIBIG($employee['salary']);

        $taxable_income = $gross_pay - ($sss + $philhealth + $pagibig);
        $tax = calculateTax($taxable_income);

        $total_deductions = round($sss + $philhealth + $pagibig + $tax, 2);
        $net_pay = round($gross_pay - $total_deductions, 2);

        if ($is_edit) {
            $stmt_upd = $pdo->prepare("UPDATE payroll SET basic_pay=?, overtime_pay=?, bonus_pay=?, double_pay_amt=?, attendance_deductions=?, gross_pay=?, sss=?, philhealth=?, pagibig=?, withholding_tax=?, total_deductions=?, net_pay=? WHERE id=?");
            $stmt_upd->execute([$base_pay, $calc['ot_amt'], $bonus, $calc['double_pay_amt'], $calc['att_deduction'], $gross_pay, $sss, $philhealth, $pagibig, $tax, $total_deductions, $net_pay, $payroll_id]);
            
            // Notify Admin
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $stmt_notif->execute([$_SESSION['user_id'], 'Payroll Updated', "Payroll for " . $employee['name'] . " ($cutoff_start to $cutoff_end) has been recalculated."]);

            $message = "Payroll record updated successfully!";
        } else {
            $stmt_save = $pdo->prepare("INSERT INTO payroll (employee_id, cutoff_start, cutoff_end, basic_pay, overtime_pay, bonus_pay, double_pay_amt, attendance_deductions, gross_pay, sss, philhealth, pagibig, withholding_tax, total_deductions, net_pay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_save->execute([$employee_id, $cutoff_start, $cutoff_end, $base_pay, $calc['ot_amt'], $bonus, $calc['double_pay_amt'], $calc['att_deduction'], $gross_pay, $sss, $philhealth, $pagibig, $tax, $total_deductions, $net_pay]);
            
            // Notify Admin
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $stmt_notif->execute([$_SESSION['user_id'], 'Payroll Generated', "Payroll for " . $employee['name'] . " ($cutoff_start to $cutoff_end) was successfully generated."]);

            // Notify Employee
            $stmt_user_id = $pdo->prepare("SELECT id FROM users WHERE employee_id = ?");
            $stmt_user_id->execute([$employee_id]);
            $target_user_id = $stmt_user_id->fetchColumn();
            
            if ($target_user_id) {
                $stmt_notif->execute([$target_user_id, 'New Payslip Available', "Your payslip for the period " . date('M d', strtotime($cutoff_start)) . " to " . date('M d', strtotime($cutoff_end)) . " is now available."]);
            }

            $message = "Payroll generated successfully!";
        }

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
$stmt = $pdo->prepare("SELECT p.*, e.name, e.employee_id as eid FROM payroll p JOIN employees e ON p.employee_id = e.id ORDER BY p.payroll_date DESC");
$stmt->execute();
$payrolls = $stmt->fetchAll();
$employees = $pdo->query("SELECT id, name, employee_id FROM employees ORDER BY name ASC")->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold text-dark">Payroll Management</h2>
        <p class="text-muted small mb-0">Attendance-based Salary Computation (Divisor: 22)</p>
    </div>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#calculatePayrollModal">
        <i class="fas fa-plus me-2"></i> Process Cutoff
    </button>
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
                    <?php foreach ($payrolls as $p): 
                        // Days worked = basic_pay / daily_rate (reversing for display)
                        $stmt_e = $pdo->prepare("SELECT salary FROM employees WHERE id = ?");
                        $stmt_e->execute([$p['employee_id']]);
                        $m_sal = $stmt_e->fetchColumn();
                        $d_rate = $m_sal / 22;
                        $days = $d_rate > 0 ? round($p['basic_pay'] / $d_rate, 1) : 0;
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo $p['name']; ?></div>
                            <div class="text-muted smaller"><?php echo $p['eid']; ?></div>
                        </td>
                        <td class="small"><?php echo date('M d', strtotime($p['cutoff_start'])); ?> - <?php echo date('M d', strtotime($p['cutoff_end'])); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo $days; ?> Days</span></td>
                        <td class="fw-bold">₱<?php echo number_format($p['gross_pay'], 2); ?></td>
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
                            <?php foreach ($employees as $e): ?>
                                <option value="<?php echo $e['id']; ?>"><?php echo $e['name']; ?> (<?php echo $e['employee_id']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Start Date</label>
                            <input type="date" name="cutoff_start" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">End Date</label>
                            <input type="date" name="cutoff_end" class="form-control" required>
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
                <h5 class="modal-title fw-bold">Edit / Recalculate Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="edit_payroll" value="1">
                <input type="hidden" name="payroll_id" id="edit_p_id">
                <input type="hidden" name="employee_id" id="edit_p_eid">
                <div class="modal-body py-4">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Start Date</label>
                            <input type="date" name="cutoff_start" id="edit_p_start" class="form-control" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">End Date</label>
                            <input type="date" name="cutoff_end" id="edit_p_end" class="form-control" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Bonus/Adjustments (₱)</label>
                        <input type="number" step="0.01" name="bonus" id="edit_p_bonus" class="form-control" required>
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
      $('#edit_p_id').val(data.id);
      $('#edit_p_eid').val(data.eid);
      $('#edit_p_start').val(data.start);
      $('#edit_p_end').val(data.end);
      $('#edit_p_bonus').val(data.bonus);
  });
});
</script>

<?php include 'footer.php'; ?>
