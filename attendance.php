<?php
require_once 'config.php';
require_once 'payroll_helper.php';
authorize(['admin', 'hr']);

$message = '';
$error = '';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Handle Mark Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $attendance_date = $_POST['attendance_date'];
    $records = $_POST['attendance']; // Array: [employee_id => [status, time_in, time_out, is_double_pay]]

    if (strtotime($attendance_date) > strtotime(date('Y-m-d'))) {
        $error = "Cannot mark attendance for future dates.";
    } else {
        try {
            $pdo->beginTransaction();
            foreach ($records as $employee_id => $data) {
                $status = $data['status'];
                $time_in = !empty($data['time_in']) ? $data['time_in'] : null;
                $time_out = !empty($data['time_out']) ? $data['time_out'] : null;
                $is_double_pay = isset($data['is_double_pay']) ? 1 : 0;
                
                // Calculate hours and lates if Present
                $total_hours = 0;
                $late_mins = 0;
                $undertime_mins = 0;

                if (($status === 'Present' || $status === 'Half-day') && $time_in && $time_out) {
                    // Fetch employee shift
                    $stmt_s = $pdo->prepare("SELECT shift FROM employees WHERE id = ?");
                    $stmt_s->execute([$employee_id]);
                    $emp_shift = $stmt_s->fetchColumn() ?: 'Night';

                    $start = strtotime($time_in);
                    $end = strtotime($time_out);
                    
                    if ($end < $start) {
                        // Night shift crossing midnight
                        $end += 86400; // Add 24 hours
                    }
                    
                    $diff = ($end - $start) / 3600;
                    $total_hours = round($diff, 2);

                    // Shift times
                    if ($emp_shift === 'Night') {
                        $shift_start_str = "22:00:00";
                        $shift_end_str = "06:00:00";
                    } else {
                        $shift_start_str = "09:00:00";
                        $shift_end_str = "17:00:00";
                    }

                    $standard_start = strtotime($shift_start_str);
                    $standard_end = strtotime($shift_end_str);
                    
                    if ($emp_shift === 'Night' && $standard_end < $standard_start) {
                        $standard_end += 86400;
                    }

                    if ($start > $standard_start) {
                        $late_mins = round(($start - $standard_start) / 60);
                    }
                    if ($end < $standard_end) {
                        $undertime_mins = round(($standard_end - $end) / 60);
                    }
                }

                // Check if entry already exists
                $stmt_check = $pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
                $stmt_check->execute([$employee_id, $attendance_date]);
                
                if ($stmt_check->rowCount() > 0) {
                    $stmt_update = $pdo->prepare("UPDATE attendance SET status = ?, time_in = ?, time_out = ?, total_hours = ?, late_minutes = ?, undertime_minutes = ?, is_double_pay = ? WHERE employee_id = ? AND attendance_date = ?");
                    $stmt_update->execute([$status, $time_in, $time_out, $total_hours, $late_mins, $undertime_mins, $is_double_pay, $employee_id, $attendance_date]);
                } else {
                    $stmt_insert = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status, time_in, time_out, total_hours, late_minutes, undertime_minutes, is_double_pay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_insert->execute([$employee_id, $attendance_date, $status, $time_in, $time_out, $total_hours, $late_mins, $undertime_mins, $is_double_pay]);
                }
            }

            // Automatic Trigger: Upsert payroll records for every employee updated
            foreach ($records as $employee_id => $data) {
                upsertPayroll($pdo, $employee_id, $attendance_date);
            }

            logActivity($pdo, $_SESSION['user_id'], 'Mark Attendance', "Attendance for $attendance_date (Double Pay: " . (isset($_POST['global_double_pay']) ? 'Yes' : 'No') . ")");
            $pdo->commit();
            $message = "Attendance logs updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error marking attendance: " . $e->getMessage();
        }
    }
}

// Fetch Employees and their attendance
$stmt = $pdo->prepare("SELECT e.*, a.status, a.time_in, a.time_out, a.is_double_pay, a.total_hours FROM employees e 
                       LEFT JOIN attendance a ON e.id = a.employee_id AND a.attendance_date = ? 
                       WHERE e.status = 'Active' OR a.id IS NOT NULL
                       ORDER BY e.name ASC");
$stmt->execute([$date]);
$employees = $stmt->fetchAll();

// Add shift to employee data if not already there (it should be there since we did SELECT e.*)
include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold text-dark">Daily Attendance & Hours</h2>
        <p class="text-muted small mb-0">Track daily logs, overtime, and holiday pay</p>
    </div>
    <form action="" method="GET" class="d-flex align-items-center bg-white p-2 rounded-3 border shadow-sm">
        <label class="me-2 small fw-bold text-muted ps-2">Select Date:</label>
        <input type="date" name="date" class="form-control form-control-sm border-0 bg-light" value="<?php echo $date; ?>" onchange="this.form.submit()">
    </form>
</div>

<?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<form action="" method="POST">
    <input type="hidden" name="mark_attendance" value="1">
    <input type="hidden" name="attendance_date" value="<?php echo $date; ?>">

    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold text-dark"><i class="fas fa-calendar-day me-2 text-primary"></i>Attendance Sheet: <?php echo date('M d, Y', strtotime($date)); ?></h5>
            <div class="form-check form-switch bg-warning-subtle px-3 py-1 rounded-pill border border-warning-subtle">
                <input class="form-check-input" type="checkbox" id="selectAllDoublePay">
                <label class="form-check-label small fw-bold text-warning-emphasis" for="selectAllDoublePay">Mark All as Double Pay</label>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Employee</th>
                            <th>Status</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Total Hrs</th>
                            <th class="text-center">Double Pay</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo $emp['name']; ?></div>
                                <div class="text-muted smaller"><?php echo $emp['employee_id']; ?> | <span class="text-primary fw-bold"><?php echo ($emp['shift'] == 'Morning') ? 'Morning Shift' : 'Night Shift'; ?></span></div>
                            </td>
                            <td>
                                <select name="attendance[<?php echo $emp['id']; ?>][status]" class="form-select form-select-sm status-select">
                                    <option value="Present" <?php echo ($emp['status'] === 'Present') ? 'selected' : ''; ?>>Present</option>
                                    <option value="Half-day" <?php echo ($emp['status'] === 'Half-day') ? 'selected' : ''; ?>>Half-day</option>
                                    <option value="Absent" <?php echo ($emp['status'] === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                                    <option value="Leave" <?php echo ($emp['status'] === 'Leave') ? 'selected' : ''; ?>>Leave</option>
                                </select>
                            </td>
                            <td>
                                <?php 
                                    $default_in = ($emp['shift'] === 'Morning') ? '09:00' : '22:00';
                                ?>
                                <input type="time" name="attendance[<?php echo $emp['id']; ?>][time_in]" class="form-control form-control-sm" value="<?php echo $emp['time_in'] ?: $default_in; ?>">
                            </td>
                            <td>
                                <?php 
                                    $default_out = ($emp['shift'] === 'Morning') ? '17:00' : '06:00';
                                ?>
                                <input type="time" name="attendance[<?php echo $emp['id']; ?>][time_out]" class="form-control form-control-sm" value="<?php echo $emp['time_out'] ?: $default_out; ?>">
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo $emp['total_hours'] ?: '0.00'; ?> hrs</span>
                            </td>
                            <td class="text-center">
                                <input class="form-check-input dp-checkbox" type="checkbox" name="attendance[<?php echo $emp['id']; ?>][is_double_pay]" <?php echo ($emp['is_double_pay']) ? 'checked' : ''; ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white py-3 border-0 text-end">
            <button type="submit" class="btn btn-primary px-5 shadow-sm">
                <i class="fas fa-save me-2"></i> Save Daily Logs
            </button>
        </div>
    </div>
</form>

<script>
$(document).ready(function() {
    $('#selectAllDoublePay').on('change', function() {
        $('.dp-checkbox').prop('checked', $(this).prop('checked'));
    });
});
</script>

<?php include 'footer.php'; ?>
