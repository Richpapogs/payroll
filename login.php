<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['employee_id'] = $user['employee_id'];
            $_SESSION['first_login'] = $user['first_login'];

            // Log successful login
            logActivity($pdo, $user['id'], 'Login', 'User logged in successfully');

            if ($user['first_login']) {
                redirect('force_change_password.php');
            }

            redirect('dashboard.php');
        } else {
            $error = "Invalid username or password.";
            // Log failed login attempt
            logActivity($pdo, null, 'Failed Login', "Username: $username");
        }
    } else {
        $error = "Please enter both username and password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Payroll Pro Enterprise</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
        body { 
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            width: 100%;
            max-width: 440px;
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        .brand-logo {
            width: 48px;
            height: 48px;
            background: var(--primary);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 24px;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.4);
        }
        .login-header h2 {
            font-weight: 700;
            color: #1e293b;
            font-size: 1.75rem;
            letter-spacing: -0.025em;
            margin-bottom: 8px;
            text-align: center;
        }
        .login-header p {
            color: #64748b;
            text-align: center;
            margin-bottom: 32px;
            font-size: 0.95rem;
        }
        .form-label {
            font-weight: 500;
            color: #475569;
            font-size: 0.875rem;
            margin-bottom: 8px;
        }
        .form-control {
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            transition: all 0.2s;
            font-size: 0.95rem;
        }
        .form-control:focus {
            background-color: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        .btn-primary {
            background-color: var(--primary);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            margin-top: 8px;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .input-group-text {
            background: transparent;
            border-right: none;
            color: #94a3b8;
        }
        .has-icon .form-control {
            border-left: none;
        }
        .alert {
            border-radius: 10px;
            font-size: 0.875rem;
            padding: 12px 16px;
            margin-bottom: 24px;
            border: none;
        }
        .alert-danger {
            background-color: #fef2f2;
            color: #991b1b;
        }
        .footer-text {
            text-align: center;
            margin-top: 24px;
            color: #94a3b8;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="brand-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p>Sign in to your Payroll Pro account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-4">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group has-icon">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <label for="password" class="form-label">Password</label>
                        <a href="forgot_password.php" class="text-primary text-decoration-none small fw-medium">Forgot?</a>
                    </div>
                    <div class="input-group has-icon">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>
                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label small text-muted" for="remember">Remember this device</label>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    Sign In <i class="fas fa-arrow-right ms-2 small"></i>
                </button>
            </form>
        </div>
        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> Payroll Pro Enterprise. All rights reserved.
        </div>
    </div>
</body>
</html>
