<?php
require_once 'config.php';
authorize(['employee']);

$employee_id = $_SESSION['employee_id'];
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

$stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date LIKE ? ORDER BY attendance_date DESC");
$stmt->execute([$employee_id, $month . '%']);
$attendance = $stmt->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold text-dark">My Attendance</h2>
        <p class="text-muted small mb-0">Track your daily attendance and work history</p>
    </div>
    <form action="" method="GET" class="d-flex align-items-center bg-white p-2 rounded-3 border shadow-sm">
        <label class="me-2 small fw-bold text-muted ps-2">Filter Month:</label>
        <input type="month" name="month" class="form-control form-control-sm border-0 bg-light" value="<?php echo $month; ?>" onchange="this.form.submit()">
    </form>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0 fw-bold text-dark">Attendance Log for <?php echo date('F Y', strtotime($month)); ?></h5>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-medium">Employee Log</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Date</th>
                        <th>Day of the Week</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($attendance) > 0): ?>
                        <?php foreach ($attendance as $att): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($att['attendance_date'])); ?></div>
                            </td>
                            <td class="text-muted">
                                <?php echo date('l', strtotime($att['attendance_date'])); ?>
                            </td>
                            <td>
                                <?php 
                                    $badge_class = 'bg-secondary-subtle text-secondary border-secondary-subtle';
                                    $icon = 'fa-info-circle';
                                    if ($att['status'] == 'Present') {
                                        $badge_class = 'bg-success-subtle text-success border-success-subtle';
                                        $icon = 'fa-check-circle';
                                    }
                                    if ($att['status'] == 'Absent') {
                                        $badge_class = 'bg-danger-subtle text-danger border-danger-subtle';
                                        $icon = 'fa-times-circle';
                                    }
                                    if ($att['status'] == 'Leave') {
                                        $badge_class = 'bg-warning-subtle text-warning border-warning-subtle';
                                        $icon = 'fa-calendar-minus';
                                    }
                                    if ($att['status'] == 'Half-day') {
                                        $badge_class = 'bg-info-subtle text-info border-info-subtle';
                                        $icon = 'fa-clock';
                                    }
                                ?>
                                <span class="badge <?php echo $badge_class; ?> border fw-medium px-3 py-2">
                                    <i class="fas <?php echo $icon; ?> me-1"></i> <?php echo $att['status']; ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <span class="text-muted smaller">Verified</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <div class="text-muted mb-2"><i class="fas fa-calendar-times fa-3x opacity-25"></i></div>
                                <div class="fw-bold">No records found</div>
                                <div class="small text-muted">No attendance data for <?php echo date('F Y', strtotime($month)); ?></div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
