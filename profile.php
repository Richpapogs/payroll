<?php
require_once 'config.php';
checkLogin();

$employee_id = $_SESSION['employee_id'];
$user_id = $_SESSION['user_id'];
$emp = null;

// Always fetch user info
$stmt_user_data = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user_data->execute([$user_id]);
$user_data = $stmt_user_data->fetch();

if ($employee_id) {
    // Fetch employee details
    $stmt_emp_data = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt_emp_data->execute([$employee_id]);
    $emp = $stmt_emp_data->fetch();
}

if (!$user_data) {
    die("User account not found.");
}

// Handle Profile Update
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $name = isset($_POST['name']) ? trim($_POST['name']) : null;
        $email = isset($_POST['email']) ? trim($_POST['email']) : null;
        
        if (empty($username)) {
            $error = "Username is required.";
        } else {
            try {
                $pdo->beginTransaction();

                // Check if username is taken by someone else
                $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt_check->execute([$username, $user_id]);
                if ($stmt_check->rowCount() > 0) {
                    throw new Exception("Username is already taken. Please choose another one.");
                }

                // Update user info
                $stmt_update_user = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt_update_user->execute([$username, $user_id]);
                
                // Update employee info if applicable
                if ($emp && $name && $email) {
                    $stmt_update_emp = $pdo->prepare("UPDATE employees SET name = ?, email = ? WHERE id = ?");
                    $stmt_update_emp->execute([$name, $email, $employee_id]);
                    
                    // Refresh employee data
                    $stmt_emp_data->execute([$employee_id]);
                    $emp = $stmt_emp_data->fetch();
                }

                // Refresh user data
                $stmt_user_data->execute([$user_id]);
                $user_data = $stmt_user_data->fetch();

                // Update session
                $_SESSION['username'] = $username;

                logActivity($pdo, $user_id, 'Update Profile', 'User updated their basic profile info');
                
                $pdo->commit();
                $message = "Profile updated successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error updating profile: " . $e->getMessage();
            }
        }
    }

    if (isset($_POST['change_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif ($new_password === $confirm_password) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_pass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt_pass->execute([$hashed, $_SESSION['user_id']]);
        
        // Add notification
        $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
        $stmt_notif->execute([$_SESSION['user_id'], 'Security Alert', 'Your account password was successfully updated.']);

        logActivity($pdo, $_SESSION['user_id'], 'Change Password', 'User updated their password');
            $message = "Password updated successfully!";
        } else {
            $error = "Passwords do not match.";
        }
    }
}

include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold text-dark">Personal Profile</h2>
        <p class="text-muted small mb-0">View your information and manage account security</p>
    </div>
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

<div class="row">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm mb-4 overflow-hidden">
            <div class="card-header bg-primary py-4 border-0 text-center">
                <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 80px; height: 80px; color: #3b82f6; font-size: 2rem;">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h5 class="text-white mb-0 fw-bold"><?php echo $emp ? h($emp['name']) : h($user_data['username']); ?></h5>
                <p class="text-white-50 small mb-0"><?php echo $emp ? h($emp['position']) : 'System Administrator'; ?></p>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <div class="list-group-item px-4 py-3 border-0 border-bottom">
                        <div class="text-muted small mb-1">Employee ID</div>
                        <div class="fw-bold text-dark"><?php echo $emp ? h($emp['employee_id']) : 'N/A'; ?></div>
                    </div>
                    <?php if ($emp): ?>
                    <div class="list-group-item px-4 py-3 border-0 border-bottom">
                        <div class="text-muted small mb-1">Basic Monthly Salary</div>
                        <div class="fw-bold text-dark">₱<?php echo number_format($emp['salary'], 2); ?></div>
                    </div>
                    <div class="list-group-item px-4 py-3 border-0">
                        <div class="text-muted small mb-1">Date Joined</div>
                        <div class="fw-bold text-dark"><?php echo date('F j, Y', strtotime($emp['created_at'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="card-title mb-0 fw-bold text-dark"><i class="fas fa-user-edit me-2 text-primary"></i> Edit Profile Information</h5>
            </div>
            <div class="card-body py-4">
                <form action="" method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Username</label>
                        <input type="text" name="username" class="form-control bg-light" value="<?php echo h($user_data['username']); ?>" required>
                    </div>
                    <?php if ($emp): ?>
                    <div class="mb-3">
                        <label class="form-label fw-medium small text-muted">Full Name</label>
                        <input type="text" name="name" class="form-control bg-light" value="<?php echo h($emp['name']); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-medium small text-muted">Email Address</label>
                        <input type="email" name="email" class="form-control bg-light" value="<?php echo h($emp['email']); ?>" required>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-dark px-4 py-2 shadow-sm">
                        <i class="fas fa-check-circle me-2 small"></i> Save Profile Changes
                    </button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="card-title mb-0 fw-bold text-dark"><i class="fas fa-lock me-2 text-primary"></i> Account Security</h5>
            </div>
            <div class="card-body py-4">
                <form action="" method="POST">
                    <input type="hidden" name="change_password" value="1">
                    <div class="mb-4">
                        <label class="form-label fw-medium small text-muted">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-key"></i></span>
                            <input type="password" name="new_password" class="form-control bg-light border-start-0 ps-0" placeholder="Enter new password" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-medium small text-muted">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-check-double"></i></span>
                            <input type="password" name="confirm_password" class="form-control bg-light border-start-0 ps-0" placeholder="Confirm new password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary px-4 py-2 shadow-sm">
                        <i class="fas fa-save me-2 small"></i> Update Security Credentials
                    </button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm bg-light-subtle">
            <div class="card-body p-4">
                <div class="d-flex">
                    <div class="me-3 text-primary"><i class="fas fa-info-circle fa-lg"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">Need to update your details?</h6>
                        <p class="text-muted small mb-0">If you notice any incorrect information in your profile, please contact the HR department for assistance.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
