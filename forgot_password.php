<?php
require_once 'config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity']);
    $employee_id_code = trim($_POST['employee_id']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($identity) || empty($employee_id_code) || empty($new_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Verify user and employee ID match using Full Name OR Username
        $stmt = $pdo->prepare("SELECT u.id FROM users u 
                               JOIN employees e ON u.employee_id = e.id 
                               WHERE (e.name = ? OR u.username = ?) AND e.employee_id = ?");
        $stmt->execute([$identity, $identity, $employee_id_code]);
        $user = $stmt->fetch();

        if ($user) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$hashed, $user['id']]);
            
            logActivity($pdo, $user['id'], 'Password Reset', 'User reset their password via forgot password page');
            $message = "Password has been reset successfully! <a href='login.php' class='alert-link'>Click here to login</a>";
        } else {
            $error = "Invalid Full Name/Username or Employee ID verification failed.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Payroll Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); height: 100vh; display: flex; align-items: center; justify-content: center; }
        .reset-card { width: 100%; max-width: 450px; background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .brand-logo { width: 48px; height: 48px; background: #2563eb; color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 20px; }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="brand-logo"><i class="fas fa-key"></i></div>
        <h3 class="text-center fw-bold mb-2">Reset Password</h3>
        <p class="text-center text-muted small mb-4">Provide your details to securely reset your password</p>

        <?php if ($message): ?>
            <div class="alert alert-success border-0 shadow-sm mb-4"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">Full Name or Username</label>
                <input type="text" name="identity" class="form-control" placeholder="Enter your full name or username" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">Employee ID (Verification)</label>
                <input type="text" name="employee_id" class="form-control" placeholder="e.g. EMP-2023-001" required>
            </div>
            <hr class="my-4">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters" required>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm your new password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Reset Password</button>
            <div class="text-center mt-4">
                <a href="login.php" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
            </div>
        </form>
    </div>
</body>
</html>