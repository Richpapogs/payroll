<?php
require_once 'config.php';
authorize(['admin', 'hr']);

$type = isset($_GET['type']) ? $_GET['type'] : 'attendance';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

$report_data = [];
if ($type === 'attendance') {
    // Monthly Attendance Report
    $stmt = $pdo->prepare("SELECT e.name, e.employee_id, 
                           SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_days,
                           SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                           SUM(CASE WHEN a.status = 'Leave' THEN 1 ELSE 0 END) as leave_days,
                           SUM(CASE WHEN a.status = 'Half-day' THEN 1 ELSE 0 END) as half_days
                           FROM employees e
                           LEFT JOIN attendance a ON e.id = a.employee_id AND a.attendance_date LIKE ?
                           GROUP BY e.id");
    $stmt->execute([$month . '%']);
    $report_data = $stmt->fetchAll();
} else {
    // Monthly Payroll Report
    $stmt = $pdo->prepare("SELECT p.*, e.name, e.employee_id as eid 
                           FROM payroll p 
                           JOIN employees e ON p.employee_id = e.id 
                           WHERE p.payroll_date LIKE ?
                           ORDER BY p.payroll_date DESC");
    $stmt->execute([$month . '%']);
    $report_data = $stmt->fetchAll();
}

include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold text-dark">Business Analytics</h2>
        <p class="text-muted small mb-0">Review performance and payroll summaries</p>
    </div>
    <div class="d-flex bg-white p-2 rounded-3 border shadow-sm">
        <select class="form-select form-select-sm border-0 bg-light me-2" onchange="location.href='?type=' + this.value + '&month=<?php echo $month; ?>'" style="width: auto;">
            <option value="attendance" <?php echo ($type == 'attendance') ? 'selected' : ''; ?>>Attendance Summary</option>
            <option value="payroll" <?php echo ($type == 'payroll') ? 'selected' : ''; ?>>Payroll Summary</option>
        </select>
        <input type="month" class="form-control form-control-sm border-0 bg-light" value="<?php echo $month; ?>" onchange="location.href='?type=<?php echo $type; ?>&month=' + this.value" style="width: auto;">
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0 fw-bold text-dark">
                <?php echo ($type == 'attendance') ? 'Monthly Attendance Report' : 'Monthly Payroll Report'; ?>
            </h5>
            <p class="text-muted small mb-0"><?php echo date('F Y', strtotime($month)); ?></p>
        </div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-medium">Admin View</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <?php if ($type === 'attendance'): ?>
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Employee</th>
                            <th class="text-center">Present</th>
                            <th class="text-center">Absent</th>
                            <th class="text-center">Leave</th>
                            <th class="text-center">Half-day</th>
                            <th class="text-end pe-4">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo $row['name']; ?></div>
                                <div class="text-muted small"><?php echo $row['employee_id']; ?></div>
                            </td>
                            <td class="text-center"><span class="badge bg-success-subtle text-success border border-success-subtle fw-medium"><?php echo $row['present_days']; ?></span></td>
                            <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-medium"><?php echo $row['absent_days']; ?></span></td>
                            <td class="text-center"><span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-medium"><?php echo $row['leave_days']; ?></span></td>
                            <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle fw-medium"><?php echo $row['half_days']; ?></span></td>
                            <td class="text-end pe-4">
                                <span class="text-muted smaller">Verified</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Employee</th>
                            <th>Gross Earnings</th>
                            <th>Total Deductions</th>
                            <th class="text-end pe-4">Net Salary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_net = 0;
                        foreach ($report_data as $row): 
                            $total_net += $row['net_pay'];
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo $row['name']; ?></div>
                                <div class="text-muted small"><?php echo $row['eid']; ?></div>
                            </td>
                            <td>₱<?php echo number_format($row['gross_pay'], 2); ?></td>
                            <td class="text-danger small">-₱<?php echo number_format($row['total_deductions'], 2); ?></td>
                            <td class="text-end pe-4 fw-bold text-success">₱<?php echo number_format($row['net_pay'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-light border-top-0">
                        <tr>
                            <th colspan="3" class="text-end py-3 ps-4">Total Monthly Expenditure:</th>
                            <th class="text-end pe-4 py-3 text-success h5 mb-0 fw-bold">₱<?php echo number_format($total_net, 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="mt-4 no-print">
    <button class="btn btn-dark shadow-sm px-4" onclick="window.print()">
        <i class="fas fa-print me-2"></i> Print Full Report
    </button>
</div>

<?php include 'footer.php'; ?>
