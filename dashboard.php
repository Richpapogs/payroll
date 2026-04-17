<?php
require_once 'config.php';
authorize(['admin', 'hr', 'employee']);

if (isEmployee()) {
    $emp_id = $_SESSION['employee_id'];
    
    // Fetch Employee Stats
    // 1. Employee Details
    $stmt_emp = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt_emp->execute([$emp_id]);
    $employee = $stmt_emp->fetch();
    
    // 2. Latest Payslip
    $stmt_payslip = $pdo->prepare("SELECT * FROM payroll WHERE employee_id = ? ORDER BY payroll_date DESC LIMIT 1");
    $stmt_payslip->execute([$emp_id]);
    $latest_payslip = $stmt_payslip->fetch();
    
    // 3. Attendance this month
    $month_start = date('Y-m-01');
    $stmt_att = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND attendance_date >= ? AND status = 'Present'");
    $stmt_att->execute([$emp_id, $month_start]);
    $present_days = $stmt_att->fetchColumn();
    
    // 4. Notification Count
    $stmt_notif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt_notif->execute([$_SESSION['user_id']]);
    $notif_count = $stmt_notif->fetchColumn();

    include 'header.php';
    include 'sidebar.php';
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1 fw-bold text-dark">Welcome back, <?php echo h($employee['name']); ?>!</h2>
            <p class="text-muted small mb-0">Here's an overview of your account for <?php echo date('F Y'); ?></p>
        </div>
        <span class="text-muted small fw-medium"><?php echo date('F d, Y'); ?></span>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stat-card h-100 border-0 shadow-sm" style="border-left: 4px solid #3b82f6 !important;">
                <div class="card-body">
                    <div class="icon bg-light text-primary mb-3">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <h6 class="card-title text-muted small fw-bold text-uppercase mb-1">Monthly Base Salary</h6>
                    <h2 class="mb-0 fw-bold">₱<?php echo number_format($employee['salary'], 2); ?></h2>
                    <p class="text-muted small mb-0 mt-2">Fixed Monthly Rate</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100 border-0 shadow-sm" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body">
                    <div class="icon bg-light text-success mb-3">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <h6 class="card-title text-muted small fw-bold text-uppercase mb-1">Latest Net Pay</h6>
                    <h2 class="mb-0 fw-bold">₱<?php echo $latest_payslip ? number_format($latest_payslip['net_pay'], 2) : '0.00'; ?></h2>
                    <p class="text-muted small mb-0 mt-2"><?php echo $latest_payslip ? 'Issued ' . date('M d, Y', strtotime($latest_payslip['payroll_date'])) : 'No records found'; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100 border-0 shadow-sm" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body">
                    <div class="icon bg-light text-warning mb-3">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h6 class="card-title text-muted small fw-bold text-uppercase mb-1">Days Present</h6>
                    <h2 class="mb-0 fw-bold"><?php echo $present_days; ?> Days</h2>
                    <p class="text-muted small mb-0 mt-2">Current Month Activity</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card h-100 border-0 shadow-sm" style="border-left: 4px solid #ef4444 !important;">
                <div class="card-body">
                    <div class="icon bg-light text-danger mb-3">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h6 class="card-title text-muted small fw-bold text-uppercase mb-1">New Notifications</h6>
                    <h2 class="mb-0 fw-bold"><?php echo $notif_count; ?></h2>
                    <p class="text-muted small mb-0 mt-2">Action items for you</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title mb-0 fw-bold text-dark">Personal Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold text-uppercase mb-1 d-block">Full Name</label>
                            <div class="fw-bold text-dark"><?php echo h($employee['name']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold text-uppercase mb-1 d-block">Employee ID</label>
                            <div class="fw-bold text-dark"><?php echo h($employee['employee_id']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold text-uppercase mb-1 d-block">Position</label>
                            <div class="fw-bold text-dark"><?php echo h($employee['position']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small fw-bold text-uppercase mb-1 d-block">Joining Date</label>
                            <div class="fw-bold text-dark"><?php echo date('F d, Y', strtotime($employee['created_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title mb-0 fw-bold text-dark">Quick Shortcuts</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-3">
                        <a href="my_payslips.php" class="btn btn-outline-primary text-start py-2 border-dashed"><i class="fas fa-file-invoice-dollar me-2"></i> View Payslips</a>
                        <a href="my_attendance.php" class="btn btn-outline-success text-start py-2 border-dashed"><i class="fas fa-history me-2"></i> Attendance Log</a>
                        <a href="profile.php" class="btn btn-outline-dark text-start py-2 border-dashed"><i class="fas fa-user-cog me-2"></i> Account Settings</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include 'footer.php';
    exit;
}

// Admin/HR Stats Fetching
$total_employees = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn();

// 2. Today's Attendance %
$today = date('Y-m-d');
$stmt_today_att = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND status = 'Present'");
$stmt_today_att->execute([$today]);
$present_today = $stmt_today_att->fetchColumn();
$attendance_percentage = ($total_employees > 0) ? ($present_today / $total_employees) * 100 : 0;

// 3. Total Monthly Payroll (Current Month)
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$stmt_monthly = $pdo->prepare("SELECT SUM(net_pay) as total_net, SUM(total_deductions) as total_ded FROM payroll WHERE payroll_date BETWEEN ? AND ?");
$stmt_monthly->execute([$month_start . ' 00:00:00', $month_end . ' 23:59:59']);
$monthly_stats = $stmt_monthly->fetch();
$total_monthly_payroll = $monthly_stats['total_net'] ?? 0;
$total_monthly_deductions = $monthly_stats['total_ded'] ?? 0;

// Fetch Chart Data (Last 6 Months)
$chart_data = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $m_start = $m . '-01 00:00:00';
    $m_end = date('Y-m-t', strtotime("-$i months")) . ' 23:59:59';
    
    $stmt_chart = $pdo->prepare("SELECT SUM(net_pay) as total FROM payroll WHERE payroll_date BETWEEN ? AND ?");
    $stmt_chart->execute([$m_start, $m_end]);
    $total = $stmt_chart->fetchColumn() ?? 0;
    
    $chart_data[] = [
        'month' => date('M Y', strtotime("-$i months")),
        'total' => $total
    ];
}

// Fetch Recent Activity
$stmt_activity = $pdo->prepare("SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 5");
$stmt_activity->execute();
$recent_activity = $stmt_activity->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0 fw-bold text-dark">Admin Dashboard</h2>
    <span class="text-muted small fw-medium"><?php echo date('F d, Y'); ?></span>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card h-100 border-0 shadow-sm" style="border-left: 4px solid #3b82f6 !important;">
            <div class="card-body">
                <div class="icon bg-light text-primary mb-3">
                    <i class="fas fa-users"></i>
                </div>
                <h6 class="card-title text-muted small fw-bold text-uppercase mb-1">Total Employees</h6>
                <h2 class="mb-0 fw-bold"><?php echo $total_employees; ?></h2>
                <p class="text-success small mb-0 mt-2"><i class="fas fa-arrow-up me-1"></i> Active Workforce</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100 border-0 shadow-sm" style="border-left: 4px solid #10b981 !important;">
            <div class="card-body">
                <div class="icon bg-light text-success mb-3">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h6 class="card-title text-muted small fw-bold text-uppercase mb-1">Attendance Today</h6>
                <h2 class="mb-0 fw-bold"><?php echo number_format($attendance_percentage, 1); ?>%</h2>
                <p class="text-muted small mb-0 mt-2">Daily Presence Rate</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100 border-0 shadow-sm" style="border-left: 4px solid #8b5cf6 !important;">
            <div class="card-body">
                <div class="icon bg-light text-purple mb-3" style="color: #8b5cf6;">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <h6 class="card-title text-muted small fw-bold text-uppercase mb-1">Monthly Payroll</h6>
                <h2 class="mb-0 fw-bold">₱<?php echo number_format($total_monthly_payroll, 2); ?></h2>
                <p class="text-muted small mb-0 mt-2">Current Month Total</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card h-100 border-0 shadow-sm" style="border-left: 4px solid #f59e0b !important;">
            <div class="card-body">
                <div class="icon bg-light text-warning mb-3">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <h6 class="card-title text-muted small fw-bold text-uppercase mb-1">Total Deductions</h6>
                <h2 class="mb-0 fw-bold">₱<?php echo number_format($total_monthly_deductions, 2); ?></h2>
                <p class="text-muted small mb-0 mt-2">Current Month Total</p>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Recent Activity -->
<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="card-title mb-0 fw-bold text-dark">Payroll Trends</h5>
                <p class="text-muted small mb-0">Overview of the last 6 months</p>
            </div>
            <div class="card-body pt-0">
                <canvas id="payrollChart" height="150"></canvas>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="card-title mb-0 fw-bold text-dark">System Audit Logs</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">User</th>
                                <th>Action</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activity as $activity): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-medium"><?php echo $activity['username'] ?: 'System'; ?></span>
                                </td>
                                <td><?php echo $activity['action']; ?></td>
                                <td class="text-muted small"><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="card-title mb-0 fw-bold text-dark">Quick Actions</h5>
            </div>
            <div class="card-body pt-0">
                <div class="d-grid gap-3">
                    <a href="employees.php" class="btn btn-outline-primary text-start py-2 border-dashed"><i class="fas fa-plus-circle me-2"></i> Add New Employee</a>
                    <a href="attendance.php" class="btn btn-outline-success text-start py-2 border-dashed"><i class="fas fa-check-double me-2"></i> Mark Attendance</a>
                    <a href="payroll.php" class="btn btn-outline-dark text-start py-2 border-dashed"><i class="fas fa-calculator me-2"></i> Generate Payroll</a>
                    <a href="reports.php" class="btn btn-outline-secondary text-start py-2 border-dashed"><i class="fas fa-file-export me-2"></i> Export Reports</a>
                </div>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="card-title mb-0 fw-bold text-dark">Quick Status</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-bold">Active Employees</span>
                        <span class="small text-muted"><?php echo $total_employees; ?>/<?php echo $total_employees; ?></span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-primary" style="width: 100%"></div>
                    </div>
                </div>
                <div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-bold">Attendance Completion</span>
                        <span class="small text-muted"><?php echo round($attendance_percentage); ?>%</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $attendance_percentage; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('payrollChart').getContext('2d');
    const chartData = <?php echo json_encode($chart_data); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.map(d => d.month),
            datasets: [{
                label: 'Monthly Net Payroll (₱)',
                data: chartData.map(d => d.total),
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderColor: '#3b82f6',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#3b82f6',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false,
                        color: '#f1f5f9'
                    },
                    ticks: {
                        callback: function(value) {
                            return '₱' + (value / 1000) + 'k';
                        },
                        font: {
                            size: 11
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include 'footer.php'; ?>
