<?php
require_once 'config.php';
authorize(['admin', 'hr']);

$message = '';
$error = '';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Handle Mark Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $attendance_date = $_POST['attendance_date'];
    $statuses = $_POST['status']; // Array: [employee_id => status]

    if (strtotime($attendance_date) > strtotime(date('Y-m-d'))) {
        $error = "Cannot mark attendance for future dates.";
    } else {
        try {
            $pdo->beginTransaction();
            foreach ($statuses as $employee_id => $status) {
                $employee_id = (int)$employee_id;
                // Check if entry already exists
                $stmt_check = $pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
                $stmt_check->execute([$employee_id, $attendance_date]);
                
                if ($stmt_check->rowCount() > 0) {
                    // Update
                    $stmt_update = $pdo->prepare("UPDATE attendance SET status = ? WHERE employee_id = ? AND attendance_date = ?");
                    $stmt_update->execute([$status, $employee_id, $attendance_date]);
                } else {
                    // Insert
                    $stmt_insert = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status) VALUES (?, ?, ?)");
                    $stmt_insert->execute([$employee_id, $attendance_date, $status]);
                }
            }
            logActivity($pdo, $_SESSION['user_id'], 'Mark Attendance', "Attendance for $attendance_date");
            $pdo->commit();
            $message = "Attendance marked successfully for $attendance_date!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error marking attendance: " . $e->getMessage();
        }
    }
}

// Fetch Employees and their attendance for selected date
// Only show active employees or employees who already have attendance for this date
$stmt = $pdo->prepare("SELECT e.*, a.status FROM employees e 
                       LEFT JOIN attendance a ON e.id = a.employee_id AND a.attendance_date = ? 
                       WHERE e.status = 'Active' OR a.id IS NOT NULL
                       ORDER BY e.name ASC");
$stmt->execute([$date]);
$employees = $stmt->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold text-dark">Attendance Tracking</h2>
        <p class="text-muted small mb-0">Monitor and record daily employee presence</p>
    </div>
    <form action="" method="GET" class="d-flex align-items-center bg-white p-2 rounded-3 border shadow-sm">
        <label class="me-2 small fw-bold text-muted ps-2">Select Date:</label>
        <input type="date" name="date" class="form-control form-control-sm border-0 bg-light" value="<?php echo $date; ?>" onchange="this.form.submit()">
    </form>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0 fw-bold text-dark">Attendance Sheet</h5>
            <p class="text-muted small mb-0"><?php echo date('l, F j, Y', strtotime($date)); ?></p>
        </div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-medium">Daily Log</span>
    </div>
    <div class="card-body p-0">
        <form action="" method="POST">
            <input type="hidden" name="mark_attendance" value="1">
            <input type="hidden" name="attendance_date" value="<?php echo $date; ?>">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Employee</th>
                            <th>Status Assignment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px; color: #3b82f6; font-size: 0.8rem;">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo $emp['name']; ?></div>
                                        <div class="text-muted small"><?php echo $emp['employee_id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group shadow-sm rounded-3 overflow-hidden" role="group">
                                    <input type="radio" class="btn-check" name="status[<?php echo $emp['id']; ?>]" id="present_<?php echo $emp['id']; ?>" value="Present" <?php echo ($emp['status'] == 'Present') ? 'checked' : ''; ?> required>
                                    <label class="btn btn-outline-success btn-sm px-3" for="present_<?php echo $emp['id']; ?>"><i class="fas fa-check me-1"></i> Present</label>

                                    <input type="radio" class="btn-check" name="status[<?php echo $emp['id']; ?>]" id="absent_<?php echo $emp['id']; ?>" value="Absent" <?php echo ($emp['status'] == 'Absent') ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-danger btn-sm px-3" for="absent_<?php echo $emp['id']; ?>"><i class="fas fa-times me-1"></i> Absent</label>

                                    <input type="radio" class="btn-check" name="status[<?php echo $emp['id']; ?>]" id="leave_<?php echo $emp['id']; ?>" value="Leave" <?php echo ($emp['status'] == 'Leave') ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-warning btn-sm px-3" for="leave_<?php echo $emp['id']; ?>"><i class="fas fa-calendar-minus me-1"></i> Leave</label>

                                    <input type="radio" class="btn-check" name="status[<?php echo $emp['id']; ?>]" id="halfday_<?php echo $emp['id']; ?>" value="Half-day" <?php echo ($emp['status'] == 'Half-day') ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-info btn-sm px-3" for="halfday_<?php echo $emp['id']; ?>"><i class="fas fa-clock me-1"></i> Half-day</label>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 text-end">
                <button type="submit" class="btn btn-primary px-5 shadow-sm">
                    <i class="fas fa-save me-2"></i> Save Daily Attendance
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
