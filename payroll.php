<?php
require_once 'config.php';
require_once 'payroll_helper.php';
authorize(['admin', 'hr']);

$message = '';
$error = '';

// Handle Payroll Calculation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_payroll'])) {
    $employee_id = (int)$_POST['employee_id'];
    $cutoff_start = $_POST['cutoff_start'];
    $cutoff_end = $_POST['cutoff_end'];
    $overtime = (float)$_POST['overtime'];
    $bonus = (float)$_POST['bonus'];

    try {
        $pdo->beginTransaction();
        
        // Fetch employee details
        $stmt_emp = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt_emp->execute([$employee_id]);
        $employee = $stmt_emp->fetch();

        if (!$employee) throw new Exception("Employee not found.");

        // Check for duplicates
        $stmt_dup = $pdo->prepare("SELECT id FROM payroll WHERE employee_id = ? AND cutoff_start = ? AND cutoff_end = ?");
        $stmt_dup->execute([$employee_id, $cutoff_start, $cutoff_end]);
        if ($stmt_dup->rowCount() > 0) throw new Exception("Payroll already exists for this employee and date range.");

        // Calculate Attendance-based deductions
        // Count absences and half-days in range
        $stmt_att = $pdo->prepare("SELECT status FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?");
        $stmt_att->execute([$employee_id, $cutoff_start, $cutoff_end]);
        $attendance = $stmt_att->fetchAll();

        $absences = 0;
        $halfdays = 0;
        foreach ($attendance as $att) {
            if ($att['status'] === 'Absent') $absences++;
            if ($att['status'] === 'Half-day') $halfdays++;
        }

        $daily_rate = $employee['salary'] / 22; // Assuming 22 working days
        $attendance_deduction = ($absences * $daily_rate) + ($halfdays * ($daily_rate / 2));

        // Gross Pay
        $gross_pay = ($employee['salary'] + $overtime + $bonus) - $attendance_deduction;

        // Philippine-based deductions (2026 Rules)
        $sss = calculateSSS($employee['salary']);
        $philhealth = calculatePhilHealth($employee['salary']);
        $pagibig = calculatePagIBIG($employee['salary']);

        // Taxable Income = Gross Pay - (SSS + PhilHealth + PagIBIG)
        $taxable_income = $gross_pay - ($sss + $philhealth + $pagibig);
        $withholding_tax = calculateTax($taxable_income);

        $total_deductions = round($sss + $philhealth + $pagibig + $withholding_tax, 2);
        $net_pay = round($gross_pay - $total_deductions, 2);

        // Save Payroll
        $stmt_save = $pdo->prepare("INSERT INTO payroll (employee_id, cutoff_start, cutoff_end, gross_pay, sss, philhealth, pagibig, withholding_tax, total_deductions, net_pay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_save->execute([$employee_id, $cutoff_start, $cutoff_end, $gross_pay, $sss, $philhealth, $pagibig, $withholding_tax, $total_deductions, $net_pay]);

        // Fetch User ID for notification
        $stmt_user_id = $pdo->prepare("SELECT id FROM users WHERE employee_id = ?");
        $stmt_user_id->execute([$employee_id]);
        $target_user_id = $stmt_user_id->fetchColumn();

        if ($target_user_id) {
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $notif_title = "New Payslip Issued";
            $notif_msg = "Your payslip for the period " . date('M d', strtotime($cutoff_start)) . " to " . date('M d, Y', strtotime($cutoff_end)) . " has been issued. Net Pay: ₱" . number_format($net_pay, 2);
            $stmt_notif->execute([$target_user_id, $notif_title, $notif_msg]);
        }

        logActivity($pdo, $_SESSION['user_id'], 'Generate Payroll', "Generated payroll for: " . $employee['name'] . " ($cutoff_start to $cutoff_end)");
        
        $pdo->commit();
        $message = "Payroll calculated and saved successfully for " . $employee['name'];
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error calculating payroll: " . $e->getMessage();
    }
}

// Handle Delete Payroll
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM payroll WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($pdo, $_SESSION['user_id'], 'Delete Payroll', "Deleted payroll record ID: $id");
        $message = "Payroll record deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting payroll: " . $e->getMessage();
    }
}

// Fetch Payroll List
$stmt = $pdo->prepare("SELECT p.*, e.name, e.employee_id as eid FROM payroll p JOIN employees e ON p.employee_id = e.id ORDER BY p.payroll_date DESC");
$stmt->execute();
$payrolls = $stmt->fetchAll();

// Fetch Employees for dropdown
$employees = $pdo->query("SELECT id, name, employee_id FROM employees ORDER BY name ASC")->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold text-dark">Payroll Processing</h2>
        <p class="text-muted small mb-0">Generate and manage employee compensation records</p>
    </div>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#calculatePayrollModal">
        <i class="fas fa-calculator me-2"></i> Process New Payroll
    </button>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo h($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white py-3 border-0">
        <h5 class="card-title mb-0 fw-bold text-dark">Payroll History</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Employee</th>
                        <th>Pay Period</th>
                        <th>Gross Amount</th>
                        <th>Total Deductions</th>
                        <th>Net Salary</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($payrolls) > 0): ?>
                        <?php foreach ($payrolls as $p): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px; color: #3b82f6; font-size: 0.8rem;">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo $p['name']; ?></div>
                                        <div class="text-muted small"><?php echo $p['eid']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="small">
                                <div class="text-dark fw-medium"><?php echo date('M d', strtotime($p['cutoff_start'])); ?> - <?php echo date('M d, Y', strtotime($p['cutoff_end'])); ?></div>
                                <div class="text-muted smaller">Processed on <?php echo date('M d', strtotime($p['payroll_date'])); ?></div>
                            </td>
                            <td class="fw-medium">₱<?php echo number_format($p['gross_pay'], 2); ?></td>
                            <td class="text-danger small">-₱<?php echo number_format($p['total_deductions'], 2); ?></td>
                            <td>
                                <div class="fw-bold text-success">₱<?php echo number_format($p['net_pay'], 2); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-success-subtle text-success border border-success-subtle fw-medium">Processed</span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <a href="payslip_gen.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-dark" target="_blank" title="Download Payslip">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                    <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to remove this payroll record?')" title="Delete Record">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted mb-2"><i class="fas fa-money-check-alt fa-3x opacity-25"></i></div>
                                <div class="fw-bold">No payroll history found</div>
                                <div class="small text-muted">Records will appear here after calculation</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Calculate Payroll Modal -->
<div class="modal fade" id="calculatePayrollModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Calculate Employee Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="calculate_payroll" value="1">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Select Employee</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">Choose employee...</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo $emp['name'] . ' (' . $emp['employee_id'] . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium small text-muted">Cutoff Start</label>
                            <input type="date" name="cutoff_start" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small text-muted">Cutoff End</label>
                            <input type="date" name="cutoff_end" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium small text-muted">Overtime Pay (₱)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">₱</span>
                                <input type="number" step="0.01" name="overtime" class="form-control" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small text-muted">Bonus / Adjustments (₱)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">₱</span>
                                <input type="number" step="0.01" name="bonus" class="form-control" value="0.00">
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 p-3 bg-light rounded-3 border">
                        <div class="small text-muted"><i class="fas fa-info-circle me-1"></i> 2026 Mandatory Deductions:</div>
                        <div class="mt-1 small">
                            <div class="d-flex justify-content-between">
                                <span>SSS (MSC based)</span>
                                <span class="fw-medium">4.5% Employee Share</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>PhilHealth</span>
                                <span class="fw-medium">2.5% Employee Share</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Pag-IBIG</span>
                                <span class="fw-medium">Max ₱100.00</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>BIR Tax</span>
                                <span class="fw-medium">TRAIN Law Brackets</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Generate Payroll</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Re-open Calculate Modal if there was an error
    <?php if ($error && isset($_POST['calculate_payroll'])): ?>
    var calcModal = new bootstrap.Modal(document.getElementById('calculatePayrollModal'));
    calcModal.show();
    <?php endif; ?>
});
</script>

<?php include 'footer.php'; ?>
