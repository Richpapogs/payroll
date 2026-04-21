<?php
require_once 'config.php';
authorize(['admin', 'hr']);

$message = '';
$error = '';

// Handle Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $request_id = (int)$_POST['request_id'];
    $status = $_POST['status'];
    $payment_status = $_POST['payment_status'] ?? 'Unpaid';

    try {
        $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, payment_status = ? WHERE id = ?");
        $stmt->execute([$status, $payment_status, $request_id]);
        
        // Notify Employee
        $stmt_emp = $pdo->prepare("SELECT lr.employee_id, u.id as user_id, lr.leave_type FROM leave_requests lr JOIN users u ON lr.employee_id = u.employee_id WHERE lr.id = ?");
        $stmt_emp->execute([$request_id]);
        $notif_data = $stmt_emp->fetch();
        
        if ($notif_data) {
            $msg = "Your " . $notif_data['leave_type'] . " leave request has been " . $status . " as " . $payment_status . ".";
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $stmt_notif->execute([$notif_data['user_id'], 'Leave Request Update', $msg]);
        }

        $message = "Leave request updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating request: " . $e->getMessage();
    }
}

// Fetch Pending Requests
$stmt_pending = $pdo->query("SELECT lr.*, e.name, e.employee_id as eid FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.status = 'Pending' ORDER BY lr.created_at ASC");
$pending_requests = $stmt_pending->fetchAll();

// Fetch History
$stmt_history = $pdo->query("SELECT lr.*, e.name, e.employee_id as eid FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.status != 'Pending' ORDER BY lr.created_at DESC LIMIT 20");
$history_requests = $stmt_history->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold text-dark">Leave Management</h2>
        <p class="text-muted small mb-0">Approve or reject employee leave requests</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm"><i class="fas fa-check-circle me-2"></i> <?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?></div>
<?php endif; ?>

<!-- Pending Requests -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-0">
        <h5 class="card-title mb-0 fw-bold text-dark"><i class="fas fa-clock me-2 text-warning"></i>Pending Requests</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Employee</th>
                        <th>Type</th>
                        <th>Period</th>
                        <th>Duration</th>
                        <th>Reason</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pending_requests) > 0): ?>
                        <?php foreach ($pending_requests as $r): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo h($r['name']); ?></div>
                                <div class="text-muted smaller"><?php echo h($r['eid']); ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo h($r['leave_type']); ?></span></td>
                            <td class="small"><?php echo date('M d', strtotime($r['start_date'])); ?> - <?php echo date('M d, Y', strtotime($r['end_date'])); ?></td>
                            <td><span class="badge bg-primary-subtle text-primary border"><?php echo h($r['duration']); ?> (<?php echo $r['requested_hours']; ?> hrs)</span></td>
                            <td class="small text-muted" title="<?php echo h($r['reason']); ?>"><?php echo substr(h($r['reason']), 0, 30) . (strlen($r['reason']) > 30 ? '...' : ''); ?></td>
                            <td class="text-end pe-4">
                                <form action="" method="POST" class="d-inline">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                    <input type="hidden" name="status" value="Approved">
                                    <div class="btn-group">
                                        <select name="payment_status" class="form-select form-select-sm me-2" style="width: auto;">
                                            <option value="Paid">Paid</option>
                                            <option value="Unpaid">Unpaid</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                        <button type="submit" name="status" value="Rejected" class="btn btn-sm btn-danger">Reject</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No pending requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- History -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-0">
        <h5 class="card-title mb-0 fw-bold text-dark"><i class="fas fa-history me-2 text-primary"></i>Recent History</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Employee</th>
                        <th>Type</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th class="text-end pe-4">Processed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history_requests as $r): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo h($r['name']); ?></div>
                            <div class="text-muted smaller"><?php echo h($r['eid']); ?></div>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?php echo h($r['leave_type']); ?></span></td>
                        <td class="small"><?php echo date('M d', strtotime($r['start_date'])); ?> - <?php echo date('M d, Y', strtotime($r['end_date'])); ?></td>
                        <td>
                            <span class="badge <?php echo $r['status'] === 'Approved' ? 'bg-success' : 'bg-danger'; ?>-subtle text-dark border">
                                <?php echo h($r['status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $r['payment_status'] === 'Paid' ? 'bg-info' : 'bg-secondary'; ?>-subtle text-dark border">
                                <?php echo h($r['payment_status']); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4 small text-muted"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
