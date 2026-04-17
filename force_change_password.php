<?php
require_once 'config.php';
checkLogin();

// Only allow access if user is in first_login state
if (!isset($_SESSION['first_login']) || !$_SESSION['first_login']) {
    redirect('dashboard.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user && password_verify($current_password, $user['password'])) {
        if (strlen($new_password) < 8) {
            $error = "New password must be at least 8 characters long.";
        } elseif ($new_password === $confirm_password) {
            if ($new_password === $current_password) {
                $error = "New password cannot be the same as the temporary password.";
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                
                try {
                    $pdo->beginTransaction();
                    
                    // Update password and set first_login = FALSE
                    $stmt_update = $pdo->prepare("UPDATE users SET password = ?, first_login = FALSE WHERE id = ?");
                    $stmt_update->execute([$hashed, $_SESSION['user_id']]);
                    
                    // Update session state
                    $_SESSION['first_login'] = 0;
                    
                    logActivity($pdo, $_SESSION['user_id'], 'Password Reset (First Login)', 'User updated their temporary password');
                    
                    $pdo->commit();
                    $message = "Password updated successfully! You can now access your dashboard.";
                    // We can't redirect immediately if we want to show the message, 
                    // but for a force-change, redirecting to dashboard is usually best.
                    header("refresh:2;url=dashboard.php");
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Error updating password: " . $e->getMessage();
                }
            }
        } else {
            $error = "New passwords do not match.";
        }
    } else {
        $error = "Current temporary password is incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | Payroll Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); height: 100vh; display: flex; align-items: center; justify-content: center; }
        .force-card { width: 100%; max-width: 450px; background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .brand-logo { width: 48px; height: 48px; background: #ef4444; color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 20px; }
    </style>
</head>
<body>
    <div class="force-card">
        <div class="brand-logo"><i class="fas fa-shield-alt"></i></div>
        <h3 class="text-center fw-bold mb-2">First Login Security</h3>
        <p class="text-center text-muted small mb-4">You are required to change your temporary password before proceeding.</p>

        <?php if ($message): ?>
            <div class="alert alert-success border-0 shadow-sm mb-4">
                <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">Temporary Password</label>
                <input type="password" name="current_password" class="form-control" placeholder="Enter temporary password" required>
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
            <button type="submit" class="btn btn-danger w-100 py-2 fw-bold">Update & Continue</button>
        </form>
    </div>
</body>
</html>