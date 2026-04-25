<?php
require_once 'config.php';
authorize(['admin', 'hr']);

$message = '';
$error = '';

/**
 * Auto-generate next Employee ID: EMP-2026-XXX
 */
function generateNextEmployeeID($pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT employee_id FROM employees WHERE employee_id LIKE ? ORDER BY employee_id DESC LIMIT 1");
    $stmt->execute(["EMP-$year-%"]);
    $last_id = $stmt->fetchColumn();

    if ($last_id) {
        $last_num = (int)substr($last_id, -3);
        $next_num = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $next_num = '001';
    }
    return "EMP-$year-$next_num";
}

// Handle Add/Edit Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name']);
        $position = trim($_POST['position']);
        $salary = (float)$_POST['salary'];
        $email = trim($_POST['email'] ?? '');
        $birthdate = $_POST['birthdate'] ?? null;
        $shift = $_POST['shift'] ?? 'Night';

        if (empty($name) || empty($position) || $salary <= 0 || empty($birthdate)) {
            $error = "All fields (including Birthdate) are required for new employees.";
        } else {
            try {
                $pdo->beginTransaction();
                $employee_id = generateNextEmployeeID($pdo);
                $stmt = $pdo->prepare("INSERT INTO employees (employee_id, name, position, salary, email, birthdate, shift) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$employee_id, $name, $position, $salary, $email, $birthdate, $shift]);
                $new_emp_id = $pdo->lastInsertId();
                
                $username = strtolower(str_replace(' ', '', $name));
                // New logic: name + birthdate (YYYYMMDD)
                $bday_numeric = str_replace('-', '', $birthdate);
                $temp_password = $username . $bday_numeric;
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                
                $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role, employee_id, first_login) VALUES (?, ?, 'employee', ?, TRUE)");
                $stmt_user->execute([$username, $hashed_password, $new_emp_id]);

                // Add System Notification
                $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                $stmt_notif->execute([$_SESSION['user_id'], 'New Employee Added', "Employee $name ($employee_id) has been successfully added to the system."]);

                $pdo->commit();
                header("Location: employees.php?msg=" . urlencode("Employee added successfully! Username: $username"));
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Error adding employee: " . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        // Enhanced debugging: Capture the raw POST value
        $raw_id = $_POST['update_target_id'] ?? 'MISSING';
        $id = (int)$raw_id;
        $eid = isset($_POST['edit_employee_id']) ? strtoupper(trim($_POST['edit_employee_id'])) : '';
        $name = isset($_POST['edit_name']) ? trim($_POST['edit_name']) : '';
        $pos = isset($_POST['edit_position']) ? trim($_POST['edit_position']) : '';
        $sal = isset($_POST['edit_salary']) ? (float)$_POST['edit_salary'] : 0;
        $email = (isset($_POST['edit_email']) && trim($_POST['edit_email']) !== '') ? trim($_POST['edit_email']) : null;
        $birthdate = $_POST['edit_birthdate'] ?? null;
        $shift = $_POST['edit_shift'] ?? 'Night';

        if ($id > 0 && !empty($name)) {
            try {
                $pdo->beginTransaction();

                // 1. Update Employee
                $stmt = $pdo->prepare("UPDATE employees SET employee_id = ?, name = ?, position = ?, salary = ?, email = ?, birthdate = ?, shift = ? WHERE id = ?");
                $stmt->execute([$eid, $name, $pos, $sal, $email, $birthdate, $shift, $id]);
                
                // 2. Sync User account username
                $new_username = strtolower(str_replace(' ', '', $name));
                $stmt_user = $pdo->prepare("UPDATE users SET username = ? WHERE employee_id = ?");
                $stmt_user->execute([$new_username, $id]);
                
                // Add System Notification for Update
                $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                $stmt_notif->execute([$_SESSION['user_id'], 'Record Updated', "The record for $name ($eid) was successfully updated."]);

                $pdo->commit();
                header("Location: employees.php?msg=" . urlencode("Employee record updated successfully!"));
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Update Failed: " . $e->getMessage();
            }
        } else {
            // Enhanced debug info
            $post_keys = implode(', ', array_keys($_POST));
            $error = "System Error: The record ID was lost during submission (ID: $id, Name: $name). Please refresh and try again.";
        }
    }
}

if (isset($_GET['msg'])) $message = $_GET['msg'];

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Fetch employee details before deleting for notification
        $stmt_emp = $pdo->prepare("SELECT name, employee_id FROM employees WHERE id = ?");
        $stmt_emp->execute([$id]);
        $emp_del = $stmt_emp->fetch();

        $pdo->exec("DELETE FROM employees WHERE id = $id");

        if ($emp_del) {
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $stmt_notif->execute([$_SESSION['user_id'], 'Employee Deleted', "Employee " . $emp_del['name'] . " (" . $emp_del['employee_id'] . ") has been removed from the system."]);
        }

        header("Location: employees.php?msg=" . urlencode("Employee deleted successfully."));
        exit;
    } catch (PDOException $e) {
        $error = "Error deleting employee.";
    }
}

$search = isset($_GET['search']) ? "%" . $_GET['search'] . "%" : "%%";
$stmt = $pdo->prepare("SELECT * FROM employees WHERE name LIKE ? OR employee_id LIKE ? ORDER BY id DESC");
$stmt->execute([$search, $search]);
$employees = $stmt->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold text-dark">Employee Management</h2>
        <p class="text-muted small mb-0">Manage your workforce and salary structures</p>
    </div>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
        <i class="fas fa-plus-circle me-2"></i> Add New Employee
    </button>
</div>

<?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm"><i class="fas fa-check-circle me-2"></i> <?php echo h($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo h($error); ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Employee</th>
                        <th>Position</th>
                        <th>Work Shift</th>
                        <th>Monthly Salary</th>
                        <th>Daily Rate</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): 
                        $daily = round($emp['salary'] / 22, 2);
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?php echo $emp['name']; ?></div>
                            <div class="text-muted smaller">ID: <?php echo $emp['employee_id']; ?></div>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?php echo $emp['position']; ?></span></td>
                        <td>
                            <span class="badge <?php echo ($emp['shift'] == 'Morning') ? 'bg-info-subtle text-info' : 'bg-dark-subtle text-dark'; ?> border fw-medium">
                                <i class="fas <?php echo ($emp['shift'] == 'Morning') ? 'fa-sun' : 'fa-moon'; ?> me-1"></i>
                                <?php echo ($emp['shift'] == 'Morning') ? 'Morning Shift' : 'Night Shift'; ?>
                            </span>
                        </td>
                        <td class="fw-bold">₱<?php echo number_format($emp['salary'], 2); ?></td>
                        <td class="text-muted">₱<?php echo number_format($daily, 2); ?></td>
                        <td class="text-end pe-4">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary edit-btn" 
                                        data-id="<?php echo $emp['id']; ?>"
                                        data-eid="<?php echo $emp['employee_id']; ?>"
                                        data-name="<?php echo h($emp['name']); ?>"
                                        data-email="<?php echo h($emp['email'] ?? ''); ?>"
                                        data-birthdate="<?php echo h($emp['birthdate'] ?? ''); ?>"
                                        data-pos="<?php echo h($emp['position']); ?>"
                                        data-salary="<?php echo $emp['salary']; ?>"
                                        data-shift="<?php echo $emp['shift']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editEmployeeModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?php echo $emp['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this employee?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Add New Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="Optional">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Birthdate</label>
                        <input type="date" name="birthdate" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Position</label>
                        <input type="text" name="position" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Monthly Salary (₱)</label>
                        <input type="number" step="0.01" name="salary" class="form-control salary-input" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Work Shift</label>
                        <select name="shift" class="form-select">
                            <option value="Morning">Morning Shift (9:00 AM - 5:00 PM)</option>
                            <option value="Night" selected>Night Shift (10:00 PM - 6:00 AM)</option>
                        </select>
                    </div>
                    <div class="p-3 bg-light rounded-3 border mt-3 salary-preview d-none">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small text-muted">Daily Rate:</span>
                            <span class="small fw-bold text-dark preview-daily">₱0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small text-muted">Hourly Rate:</span>
                            <span class="small fw-bold text-dark preview-hourly">₱0.00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="small text-muted">Est. 15-Day Pay:</span>
                            <span class="small fw-bold text-primary preview-15day">₱0.00</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary w-100">Save Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Employee Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="employees.php" method="POST" id="editEmployeeForm" autocomplete="off">
                <input type="hidden" name="action" value="edit">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Employee ID</label>
                        <input type="text" name="edit_employee_id" id="val_eid" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name</label>
                        <input type="text" name="edit_name" id="val_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email Address</label>
                        <input type="email" name="edit_email" id="val_email" class="form-control" placeholder="Optional">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Birthdate</label>
                        <input type="date" name="edit_birthdate" id="val_birthdate" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Position</label>
                        <input type="text" name="edit_position" id="val_pos" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Monthly Salary (₱)</label>
                        <input type="number" step="0.01" name="edit_salary" id="val_salary" class="form-control salary-input" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Work Shift</label>
                        <select name="edit_shift" id="val_shift" class="form-select">
                            <option value="Morning">Morning Shift (9:00 AM - 5:00 PM)</option>
                            <option value="Night">Night Shift (10:00 PM - 6:00 AM)</option>
                        </select>
                    </div>
                </div>
                <!-- Unique ID name and ID to prevent conflicts -->
                <input type="hidden" name="update_target_id" id="val_update_target_id">
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary w-100">Update Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Use Vanilla JS to ensure critical ID capture works even if jQuery has issues
document.addEventListener('DOMContentLoaded', function() {
    // Form submission guard
    const editForm = document.getElementById('editEmployeeForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            const targetId = document.getElementById('val_update_target_id').value;
            console.log("Submitting Form - Target ID:", targetId);
            
            if (!targetId || targetId === '0' || targetId === '') {
                e.preventDefault();
                alert("Error: The record ID is missing. Please close this modal and click the edit button again.");
                return false;
            }
        });
    }

    // Salary preview logic (keeping it jQuery for convenience as it's less critical than the ID)
    if (window.jQuery) {
        $('.salary-input').on('input', function() {
            const monthly = parseFloat($(this).val()) || 0;
            const preview = $(this).closest('form').find('.salary-preview');
            if (monthly > 0) {
                const daily = monthly / 22;
                const hourly = daily / 8;
                const cutoff = monthly / 2;
                preview.removeClass('d-none');
                preview.find('.preview-daily').text('₱' + daily.toLocaleString(undefined, {minimumFractionDigits: 2}));
                preview.find('.preview-hourly').text('₱' + hourly.toLocaleString(undefined, {minimumFractionDigits: 2}));
                preview.find('.preview-15day').text('₱' + cutoff.toLocaleString(undefined, {minimumFractionDigits: 2}));
            } else {
                preview.addClass('d-none');
            }
        });
    }

    // Aggressive edit button handler using Vanilla JS delegation
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.edit-btn');
        if (btn) {
            // Get attributes directly from the DOM element
            const id = btn.getAttribute('data-id');
            const eid = btn.getAttribute('data-eid');
            const name = btn.getAttribute('data-name');
            const email = btn.getAttribute('data-email');
            const birthdate = btn.getAttribute('data-birthdate');
            const pos = btn.getAttribute('data-pos');
            const salary = btn.getAttribute('data-salary');
            const shift = btn.getAttribute('data-shift');

            console.log("Edit Triggered - ID from DOM:", id);

            // Fill modal fields immediately using Vanilla JS
            document.getElementById('val_update_target_id').value = id;
            document.getElementById('val_eid').value = eid || '';
            document.getElementById('val_name').value = name || '';
            document.getElementById('val_email').value = email || '';
            document.getElementById('val_birthdate').value = birthdate || '';
            document.getElementById('val_pos').value = pos || '';
            document.getElementById('val_salary').value = salary || '';
            document.getElementById('val_shift').value = shift || 'Night';
            
            // Trigger input event for salary preview if jQuery is available
            if (window.jQuery) {
                $('#val_salary').trigger('input');
            }
        }
    });

    if (window.history.replaceState && window.location.href.indexOf('msg=') > -1) {
        var cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({path: cleanUrl}, '', cleanUrl);
    }
});
</script>

<?php include 'footer.php'; ?>
