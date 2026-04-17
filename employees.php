<?php
require_once 'config.php';
authorize(['admin', 'hr']);

$message = '';
$error = '';

// Handle Add/Edit Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // CSRF Protection could be added here
        
        // Input Validation
        $name = trim($_POST['name']);
        $employee_id = strtoupper(trim($_POST['employee_id']));
        $username = trim($_POST['username'] ?? '');
        $position = trim($_POST['position']);
        $salary = (float)$_POST['salary'];
        $email = trim($_POST['email'] ?? '');

        if (empty($name) || empty($employee_id) || empty($position) || $salary <= 0) {
            $error = "Name, Employee ID, Position are required and salary must be positive.";
        } elseif (!preg_match('/^EMP-\d{4}-\d{3}$/', $employee_id)) {
            $error = "Invalid Employee ID format. Must be EMP-0000-000 (e.g., EMP-2023-001).";
        } else {
            if ($_POST['action'] === 'add') {
                if (empty($username)) {
                    $error = "Username is required for new employees.";
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        $stmt = $pdo->prepare("INSERT INTO employees (employee_id, name, position, salary, email) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$employee_id, $name, $position, $salary, $email]);
                        $new_emp_id = $pdo->lastInsertId();
                        
                        // Generate temporary password: [Username][EmployeeID]
                        $temp_password = $username . $employee_id;
                        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                        
                        // Create employee user account (First Login = True)
                        $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role, employee_id, first_login) VALUES (?, ?, 'employee', ?, TRUE)");
                        $stmt_user->execute([$username, $hashed_password, $new_emp_id]);

                        logActivity($pdo, $_SESSION['user_id'], 'Add Employee', "Added employee: $name ($employee_id) with username: $username");
                        $pdo->commit();
                        $message = "Employee added successfully! Login: $username / $temp_password";
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        if ($e->getCode() == 23000) {
                            $error = "Error: Employee ID or Username already exists. Please use a unique identifier.";
                        } else {
                            $error = "Error adding employee: " . $e->getMessage();
                        }
                    }
                }
            } elseif ($_POST['action'] === 'edit') {
                $id = (int)$_POST['id'];
                try {
                    $stmt = $pdo->prepare("UPDATE employees SET employee_id = ?, name = ?, position = ?, salary = ?, email = ? WHERE id = ?");
                    $stmt->execute([$employee_id, $name, $position, $salary, $email, $id]);
                    
                    logActivity($pdo, $_SESSION['user_id'], 'Edit Employee', "Updated employee ID: $id");
                    $message = "Employee updated successfully!";
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error = "Error: Employee ID already exists in another record.";
                    } else {
                        $error = "Error updating employee: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($pdo, $_SESSION['user_id'], 'Delete Employee', "Deleted employee ID: $id");
        $message = "Employee deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting employee: " . $e->getMessage();
    }
}

// Fetch Employees
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
        <p class="text-muted small mb-0">Manage your workforce and their compensation</p>
    </div>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
        <i class="fas fa-plus-circle me-2"></i> Add New Employee
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

<!-- Search and Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form action="" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control bg-light border-start-0 ps-0" placeholder="Search by name or Employee ID..." value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100">Filter Results</button>
            </div>
            <?php if (isset($_GET['search']) && $_GET['search'] != ''): ?>
            <div class="col-md-1">
                <a href="employees.php" class="btn btn-link text-muted small">Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Employee Table -->
<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Employee</th>
                        <th>Position</th>
                        <th>Basic Salary</th>
                        <th>Date Joined</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($employees) > 0): ?>
                        <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; color: #3b82f6;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo $emp['name']; ?></div>
                                        <div class="text-muted small">ID: <?php echo $emp['employee_id']; ?> | <?php echo $emp['email']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-medium"><?php echo $emp['position']; ?></span>
                            </td>
                            <td>
                                <div class="fw-bold">₱<?php echo number_format($emp['salary'], 2); ?></div>
                                <div class="text-muted small">Monthly Base</div>
                            </td>
                            <td class="text-muted small">
                                <?php echo date('M d, Y', strtotime($emp['created_at'])); ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" 
                                            data-id="<?php echo $emp['id']; ?>"
                                            data-eid="<?php echo $emp['employee_id']; ?>"
                                            data-name="<?php echo $emp['name']; ?>"
                                            data-email="<?php echo $emp['email']; ?>"
                                            data-pos="<?php echo $emp['position']; ?>"
                                            data-salary="<?php echo $emp['salary']; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editEmployeeModal"
                                            title="Edit Employee">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                    <a href="?delete=<?php echo $emp['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to delete this employee record?')"
                                       title="Delete Record">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted mb-2"><i class="fas fa-users-slash fa-3x opacity-25"></i></div>
                                <div class="fw-bold">No employees found</div>
                                <div class="small">Try adjusting your search criteria</div>
                            </td>
                        </tr>
                    <?php endif; ?>
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
                        <label class="form-label fw-medium small text-muted">Employee ID</label>
                        <input type="text" name="employee_id" class="form-control" placeholder="EMP-0000-000" pattern="EMP-\d{4}-\d{3}" title="Format: EMP-0000-000" required>
                        <div class="form-text smaller text-muted">Format: EMP-2026-001</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. johndoe123" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Full Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. john@company.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Position</label>
                        <input type="text" name="position" class="form-control" placeholder="e.g. Senior Developer" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Basic Monthly Salary (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">₱</span>
                            <input type="number" step="0.01" name="salary" class="form-control" placeholder="0.00" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Save Employee</button>
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
            <form action="" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Employee ID</label>
                        <input type="text" name="employee_id" id="edit_eid" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Full Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Email Address</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Position</label>
                        <input type="text" name="position" id="edit_pos" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Basic Monthly Salary (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">₱</span>
                            <input type="number" step="0.01" name="salary" id="edit_salary" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Update Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Re-open Add Modal if there was an error
    <?php if ($error && isset($_POST['action']) && $_POST['action'] === 'add'): ?>
    var addModal = new bootstrap.Modal(document.getElementById('addEmployeeModal'));
    addModal.show();
    <?php endif; ?>

    const editBtns = document.querySelectorAll('.edit-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_eid').value = this.dataset.eid;
            document.getElementById('edit_name').value = this.dataset.name;
            document.getElementById('edit_email').value = this.dataset.email;
            document.getElementById('edit_pos').value = this.dataset.pos;
            document.getElementById('edit_salary').value = this.dataset.salary;
        });
    });
});
</script>

<?php include 'footer.php'; ?>
