<?php
require_once 'config.php';
authorize(['admin', 'hr']);

$type = isset($_GET['type']) ? $_GET['type'] : 'attendance';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

$report_data = [];
if ($type === 'attendance') {
    // Enhanced Attendance Summary with Hours-Based Logic
    // Fix: Count 'Leave' days as Present Days (since they are paid) 
    // and correctly sum total_hours (which now include auto-credited leave hours)
    $stmt = $pdo->prepare("SELECT e.id, e.name, e.employee_id, 
                           SUM(CASE WHEN a.total_hours > 0 OR a.status = 'Leave' THEN 1 ELSE 0 END) as present_days,
                           SUM(a.total_hours) as total_hours,
                           SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                           SUM(CASE WHEN a.status = 'Leave' THEN 1 ELSE 0 END) as leave_days,
                           SUM(a.late_minutes) as total_late,
                           SUM(a.undertime_minutes) as total_undertime
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

// Ajax handler for drill-down
if (isset($_GET['get_details']) && isset($_GET['emp_id'])) {
    $emp_id = (int)$_GET['emp_id'];
    $stmt_emp = $pdo->prepare("SELECT name, employee_id FROM employees WHERE id = ?");
    $stmt_emp->execute([$emp_id]);
    $emp = $stmt_emp->fetch();

    $stmt = $pdo->prepare("SELECT a.*, l.leave_type as requested_leave 
                           FROM attendance a 
                           LEFT JOIN leave_requests l ON a.employee_id = l.employee_id 
                           AND a.attendance_date BETWEEN l.start_date AND l.end_date
                           WHERE a.employee_id = ? AND a.attendance_date LIKE ?
                           ORDER BY a.attendance_date ASC");
    $stmt->execute([$emp_id, $month . '%']);
    $details = $stmt->fetchAll();
    
    if (empty($details)) {
        echo '<div class="p-4 text-center text-muted">No attendance logs found for this month.</div>';
    } else {
        $total_hrs = array_sum(array_column($details, 'total_hours'));
        $total_late = array_sum(array_column($details, 'late_minutes'));
        $total_ut = array_sum(array_column($details, 'undertime_minutes'));
        $total_absent = count(array_filter($details, function($d) { return $d['status'] == 'Absent'; }));
        $total_leave = count(array_filter($details, function($d) { return $d['status'] == 'Leave'; }));

        echo '<div id="printable-log-' . $emp_id . '">
                <div class="p-4 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-1 text-dark">' . $emp['name'] . ' (' . $emp['employee_id'] . ')</h6>
                        <p class="text-muted smaller mb-0">Detailed Attendance Log for ' . date('F Y', strtotime($month)) . '</p>
                    </div>
                    <div class="text-end no-print">
                        <button class="btn btn-sm btn-dark px-3 fw-bold" onclick="printIndividualLog(' . $emp_id . ')">
                            <i class="fas fa-print me-2"></i> Print This Log
                        </button>
                    </div>
                </div>
                <div class="row g-0 text-center border-bottom bg-white py-3">
                    <div class="col-md-2 border-end">
                        <div class="small text-muted mb-1">Total Hours</div>
                        <div class="h5 mb-0 fw-bold text-primary">' . $total_hrs . ' hrs</div>
                    </div>
                    <div class="col-md-2 border-end">
                        <div class="small text-muted mb-1">Late Minutes</div>
                        <div class="h5 mb-0 fw-bold ' . ($total_late > 0 ? 'text-warning' : 'text-muted') . '">' . $total_late . ' m</div>
                    </div>
                    <div class="col-md-2 border-end">
                        <div class="small text-muted mb-1">Undertime</div>
                        <div class="h5 mb-0 fw-bold ' . ($total_ut > 0 ? 'text-danger' : 'text-muted') . '">' . $total_ut . ' m</div>
                    </div>
                    <div class="col-md-2 border-end">
                        <div class="small text-muted mb-1">Absent Days</div>
                        <div class="h5 mb-0 fw-bold ' . ($total_absent > 0 ? 'text-danger' : 'text-muted') . '">' . $total_absent . '</div>
                    </div>
                    <div class="col-md-2">
                        <div class="small text-muted mb-1">Leave Days</div>
                        <div class="h5 mb-0 fw-bold text-info">' . $total_leave . '</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-2 small fw-bold">Date</th>
                                <th class="py-2 small fw-bold">Time In</th>
                                <th class="py-2 small fw-bold">Time Out</th>
                                <th class="py-2 small fw-bold">Worked</th>
                                <th class="py-2 small fw-bold">Status</th>
                                <th class="py-2 small fw-bold">Late/UT</th>
                                <th class="pe-4 py-2 small fw-bold">Leave Info</th>
                            </tr>
                        </thead>
                        <tbody>';
        foreach ($details as $d) {
            $row_class = '';
            if ($d['status'] == 'Absent') $row_class = 'bg-danger-subtle opacity-75';
            if ($d['status'] == 'Leave') $row_class = 'bg-info-subtle';
            
            $status_badge = 'bg-success';
            if ($d['status'] == 'Absent') $status_badge = 'bg-danger';
            if ($d['status'] == 'Leave') $status_badge = 'bg-info';
            
            $late_class = $d['late_minutes'] > 0 ? 'text-warning fw-bold' : 'text-muted opacity-50';
            $ut_class = $d['undertime_minutes'] > 0 ? 'text-danger fw-bold' : 'text-muted opacity-50';
            
            echo '<tr class="' . $row_class . '">
                    <td class="ps-4 py-2 small">' . date('M d, Y', strtotime($d['attendance_date'])) . '</td>
                    <td class="py-2 small">' . ($d['time_in'] ? date('h:i A', strtotime($d['time_in'])) : '—') . '</td>
                    <td class="py-2 small">' . ($d['time_out'] ? date('h:i A', strtotime($d['time_out'])) : '—') . '</td>
                    <td class="py-2 small fw-bold">' . $d['total_hours'] . ' hrs</td>
                    <td class="py-2"><span class="badge ' . $status_badge . ' text-white smaller">' . $d['status'] . '</span></td>
                    <td class="py-2 small">
                        <span class="' . $late_class . '">L: ' . $d['late_minutes'] . 'm</span> | 
                        <span class="' . $ut_class . '">U: ' . $d['undertime_minutes'] . 'm</span>
                    </td>
                    <td class="pe-4 py-2 small italic text-muted">' . ($d['requested_leave'] ?: '—') . '</td>
                  </tr>';
        }
        echo '</tbody></table></div></div>';
    }
    exit;
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

<div class="card border-0 shadow-sm overflow-hidden no-print">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0 fw-bold text-dark">
                <?php echo ($type == 'attendance') ? 'Attendance Analytics' : 'Monthly Payroll Report'; ?>
            </h5>
            <p class="text-muted small mb-0"><?php echo date('F Y', strtotime($month)); ?></p>
        </div>
        <div class="d-flex">
            <button class="btn btn-sm btn-outline-dark me-2" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Print Summary
            </button>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-medium d-flex align-items-center">Hours-Based Tracking</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <?php if ($type === 'attendance'): ?>
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Employee</th>
                            <th class="text-center">Present Days</th>
                            <th class="text-center">Total Hours</th>
                            <th class="text-center">Absent Days</th>
                            <th class="text-center">Leave Days</th>
                            <th class="text-end pe-4">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                        <tr class="main-row" data-emp-id="<?php echo $row['id']; ?>">
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo $row['name']; ?></div>
                                <div class="text-muted small"><?php echo $row['employee_id']; ?></div>
                            </td>
                            <td class="text-center"><span class="badge bg-success-subtle text-success border border-success-subtle fw-medium"><?php echo $row['present_days']; ?></span></td>
                            <td class="text-center fw-bold"><?php echo number_format($row['total_hours'], 1); ?> hrs</td>
                            <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-medium"><?php echo $row['absent_days']; ?></span></td>
                            <td class="text-center"><span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-medium"><?php echo $row['leave_days']; ?></span></td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-dark me-1" onclick="printIndividualLog(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="btn btn-sm btn-light border drill-down-btn" data-emp-id="<?php echo $row['id']; ?>">
                                        View Details <i class="fas fa-chevron-down ms-1"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr class="details-row d-none" id="details-<?php echo $row['id']; ?>">
                            <td colspan="6" class="p-0 bg-white">
                                <div class="details-content border-top border-bottom">
                                    <div class="text-center py-4">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        <span class="ms-2 small text-muted">Loading logs...</span>
                                    </div>
                                </div>
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

<style>
@media print {
    .sidebar, .header, .no-print, .btn, .drill-down-btn, .details-row.d-none {
        display: none !important;
    }
    .card { border: none !important; box-shadow: none !important; }
    .table { width: 100% !important; border-collapse: collapse !important; }
    .main-row { display: table-row !important; }
    .details-row:not(.d-none) { display: table-row !important; }
}
</style>

<script>
$(document).on('click', '.drill-down-btn', function() {
    const btn = $(this);
    const empId = btn.data('emp-id');
    const detailsRow = $('#details-' + empId);
    const icon = btn.find('i');
    
    if (detailsRow.hasClass('d-none')) {
        // Close other open rows first
        $('.details-row').addClass('d-none');
        $('.drill-down-btn i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        
        // Open this one
        detailsRow.removeClass('d-none');
        icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        
        // Fetch content via AJAX
        $.get('reports.php', {
            get_details: 1,
            emp_id: empId,
            month: '<?php echo $month; ?>'
        }, function(data) {
            detailsRow.find('.details-content').html(data);
        }).fail(function() {
            detailsRow.find('.details-content').html('<div class="p-4 text-center text-danger">Error loading details. Please refresh.</div>');
        });
    } else {
        detailsRow.addClass('d-none');
        icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
    }
});

function printIndividualLog(empId) {
    const logElement = document.getElementById('printable-log-' + empId);
    
    if (logElement) {
        performPrint(logElement.innerHTML);
    } else {
        // Fetch it first if not loaded
        $.get('reports.php', {
            get_details: 1,
            emp_id: empId,
            month: '<?php echo $month; ?>'
        }, function(data) {
            // Create a temporary hidden div to hold the data
            const tempDiv = document.createElement('div');
            tempDiv.style.display = 'none';
            tempDiv.innerHTML = data;
            document.body.appendChild(tempDiv);
            
            const printableArea = tempDiv.querySelector('[id^="printable-log-"]').innerHTML;
            performPrint(printableArea);
            
            document.body.removeChild(tempDiv);
        });
    }
}

function performPrint(htmlContent) {
    const originalContent = document.body.innerHTML;
    document.body.innerHTML = '<html><head><title>Print Attendance Log</title>' +
                              '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">' +
                              '<style>body{padding:40px;} .no-print{display:none !important;} .table{font-size:12px;}</style>' +
                              '</head><body>' + htmlContent + '</body></html>';
    
    window.print();
    document.body.innerHTML = originalContent;
    location.reload(); // Reload to restore events
}
</script>

<?php include 'footer.php'; ?>
