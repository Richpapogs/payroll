<!-- Sidebar -->
<nav id="sidebar">
    <div class="sidebar-header">
        <h3>PAYROLL PRO</h3>
    </div>

    <ul class="list-unstyled components">
        <p>Main Menu</p>
        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
            <a href="dashboard.php"><i class="fas fa-th-large me-3"></i> Dashboard</a>
        </li>
        <?php if (isAdmin() || isHR()): ?>
        <p>Administration</p>
        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'employees.php') ? 'active' : ''; ?>">
            <a href="employees.php"><i class="fas fa-users me-3"></i> Employees</a>
        </li>
        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'attendance.php') ? 'active' : ''; ?>">
            <a href="attendance.php"><i class="fas fa-calendar-check me-3"></i> Attendance</a>
        </li>
        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'payroll.php') ? 'active' : ''; ?>">
            <a href="payroll.php"><i class="fas fa-money-check-alt me-3"></i> Payroll Management</a>
        </li>
        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>">
            <a href="reports.php"><i class="fas fa-chart-pie me-3"></i> Analytics & Reports</a>
        </li>
        <?php if (isAdmin()): ?>
        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'maintenance.php') ? 'active' : ''; ?>">
            <a href="maintenance.php"><i class="fas fa-tools me-3"></i> System Maintenance</a>
        </li>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isEmployee()): ?>
        <p>Self Service</p>
        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'employee_portal.php') ? 'active' : ''; ?>">
            <a href="employee_portal.php"><i class="fas fa-th-large me-3"></i> Dashboard</a>
        </li>
        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">
            <a href="profile.php"><i class="fas fa-user-circle me-3"></i> My Profile</a>
        </li>
        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'my_attendance.php') ? 'active' : ''; ?>">
            <a href="my_attendance.php"><i class="fas fa-history me-3"></i> My Attendance</a>
        </li>
        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'my_payslips.php') ? 'active' : ''; ?>">
            <a href="my_payslips.php"><i class="fas fa-file-invoice-dollar me-3"></i> My Payroll / Payslips</a>
        </li>
        <?php endif; ?>

        <p>Account</p>
        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'notifications.php') ? 'active' : ''; ?>">
            <a href="notifications.php"><i class="fas fa-bell me-3"></i> Notifications</a>
        </li>
        <li>
            <a href="logout.php"><i class="fas fa-sign-out-alt me-3"></i> Logout</a>
        </li>
    </ul>
</nav>

<!-- Page Content -->
<div id="content">
    <header class="main-header">
        <button type="button" id="sidebarCollapse" class="btn btn-light border">
            <i class="fas fa-bars"></i>
        </button>
        <div class="d-flex align-items-center">
            <?php
                // Fetch unread notifications count for header
                $stmt_unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                $stmt_unread->execute([$_SESSION['user_id']]);
                $unread_count = $stmt_unread->fetchColumn();
            ?>
            <a href="notifications.php" class="nav-link me-3 position-relative">
                <i class="fas fa-bell text-muted"></i>
                <?php if ($unread_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                    <?php echo $unread_count; ?>
                </span>
                <?php endif; ?>
            </a>
            <div class="dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; font-size: 0.8rem;">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </div>
                    <span class="d-none d-md-inline text-dark">Welcome, <strong><?php echo $_SESSION['username']; ?></strong></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                    <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user-cog me-2 text-muted"></i> Profile Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Sign Out</a></li>
                </ul>
            </div>
        </div>
    </header>
    <div class="container-fluid py-4">
