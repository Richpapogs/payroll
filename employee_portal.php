<?php
require_once 'config.php';
authorize(['employee']);

$emp_id = $_SESSION['employee_id'];
$message = '';
$error = '';

// Handle Leave Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_leave'])) {
    $type = $_POST['leave_type'];
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];
    $reason = trim($_POST['reason']);
    $duration = $_POST['duration'];
    $requested_hours = ($duration === 'Full Day') ? 8 : (float)$_POST['requested_hours'];

    if (strtotime($start) < strtotime(date('Y-m-d'))) {
        $error = "Start date cannot be in the past.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, duration, requested_hours) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$emp_id, $type, $start, $end, $reason, $duration, $requested_hours]);
            $message = "Leave request submitted successfully!";
        } catch (PDOException $e) {
            $error = "Error submitting request: " . $e->getMessage();
        }
    }
}

// Fetch Records
$stmt_att = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 10");
$stmt_att->execute([$emp_id]);
$attendance = $stmt_att->fetchAll();

$stmt_pay = $pdo->prepare("SELECT * FROM payroll WHERE employee_id = ? ORDER BY cutoff_end DESC LIMIT 5");
$stmt_pay->execute([$emp_id]);
$payrolls = $stmt_pay->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="row g-4">
    <!-- Attendance Log -->
    <div class="col-md-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold text-dark"><i class="fas fa-user-clock me-2 text-primary"></i>Recent Attendance</h5>
                <a href="my_attendance.php" class="small text-decoration-none">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 small fw-bold">Date</th>
                                <th class="small fw-bold">Log</th>
                                <th class="small fw-bold">Hours</th>
                                <th class="small fw-bold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance as $a): ?>
                            <tr>
                                <td class="ps-4 small"><?php echo date('M d, Y', strtotime($a['attendance_date'])); ?></td>
                                <td class="small">
                                    <?php if ($a['time_in']): ?>
                                        <span class="text-dark fw-medium"><?php echo date('H:i', strtotime($a['time_in'])); ?> - <?php echo date('H:i', strtotime($a['time_out'])); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">--:--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small fw-bold"><?php echo $a['total_hours']; ?> hrs</td>
                                <td>
                                    <?php 
                                    $badge = 'bg-success';
                                    if ($a['status'] === 'Absent') $badge = 'bg-danger';
                                    if ($a['status'] === 'Leave') $badge = 'bg-info';
                                    ?>
                                    <span class="badge <?php echo $badge; ?>-subtle text-dark border smaller"><?php echo $a['status']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Leave Request -->
    <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="card-title mb-0 fw-bold text-dark"><i class="fas fa-paper-plane me-2 text-success"></i>File a Leave</h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success border-0 small py-2 mb-3"><i class="fas fa-check-circle me-1"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 small py-2 mb-3"><i class="fas fa-exclamation-triangle me-1"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                <form action="" method="POST">
                    <input type="hidden" name="request_leave" value="1">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Leave Type</label>
                        <select name="leave_type" class="form-select form-select-sm" required>
                            <option value="Sick">Sick Leave</option>
                            <option value="Vacation">Vacation Leave</option>
                            <option value="Emergency">Emergency Leave</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted">Leave Duration</label>
                            <select name="duration" id="leave_duration" class="form-select form-select-sm" required>
                                <option value="Full Day">Full Day (8 hrs)</option>
                                <option value="Partial Day">Partial Day (Specify hrs)</option>
                            </select>
                        </div>
                    </div>
                    <div id="partial_hrs_container" class="mb-3 d-none">
                        <label class="form-label small fw-bold text-muted">Requested Hours</label>
                        <input type="number" name="requested_hours" step="0.5" min="0.5" max="8" class="form-control form-control-sm" value="8">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Start Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">End Date</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Reason</label>
                        <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Briefly explain your reason..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-2 fw-bold small"><i class="fas fa-save me-1"></i> Submit Request</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Payroll History -->
    <div class="col-12 mt-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold text-dark"><i class="fas fa-money-bill-wave me-2 text-success"></i>My Payroll Summary</h5>
                <a href="my_payslips.php" class="small text-decoration-none">View All Payslips</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 small fw-bold">Cutoff Period</th>
                                <th class="small fw-bold">Worked</th>
                                <th class="small fw-bold">Overtime</th>
                                <th class="small fw-bold">Deductions</th>
                                <th class="small fw-bold">Net Pay</th>
                                <th class="text-end pe-4 small fw-bold">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payrolls as $p): 
                                // Estimate days from basic pay
                                $stmt_e = $pdo->prepare("SELECT salary FROM employees WHERE id = ?");
                                $stmt_e->execute([$p['employee_id']]);
                                $m_sal = $stmt_e->fetchColumn();
                                $d_rate = $m_sal / 22;
                                $days = $d_rate > 0 ? round($p['basic_pay'] / $d_rate, 1) : 0;

                                // OT hours
                                $h_rate = $d_rate / 8;
                                $ot_hrs = $h_rate > 0 ? round($p['overtime_pay'] / $h_rate, 1) : 0;
                            ?>
                            <tr>
                                <td class="ps-4 small fw-medium text-dark">
                                    <?php echo date('M d', strtotime($p['cutoff_start'])); ?> - <?php echo date('M d, Y', strtotime($p['cutoff_end'])); ?>
                                </td>
                                <td class="small"><?php echo $days; ?> Days</td>
                                <td class="small text-success">+<?php echo $ot_hrs; ?> hrs</td>
                                <td class="small text-danger">-₱<?php echo number_format($p['total_deductions'], 2); ?></td>
                                <td class="small fw-bold text-success">₱<?php echo number_format($p['net_pay'], 2); ?></td>
                                <td class="text-end pe-4">
                                    <a href="payslip_gen.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-dark"><i class="fas fa-download me-1"></i> PDF</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const leaveDuration = document.getElementById('leave_duration');
    const partialHrsContainer = document.getElementById('partial_hrs_container');
    const requestedHoursInput = document.querySelector('input[name="requested_hours"]');

    if (leaveDuration && partialHrsContainer) {
        leaveDuration.addEventListener('change', function() {
            if (this.value === 'Partial Day') {
                partialHrsContainer.classList.remove('d-none');
            } else {
                partialHrsContainer.classList.add('d-none');
                if (requestedHoursInput) requestedHoursInput.value = 8;
            }
        });
    }
});
</script>
<?php include 'footer.php'; ?>
