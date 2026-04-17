<?php
require_once 'config.php';
authorize(['admin']);

$message = '';
$error = '';

if (isset($_POST['backup'])) {
    $tables = array();
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sqlScript = "";
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW CREATE TABLE $table");
        $row = $result->fetch(PDO::FETCH_NUM);
        $sqlScript .= "\n\n" . $row[1] . ";\n\n";

        $result = $pdo->query("SELECT * FROM $table");
        $columnCount = $result->columnCount();

        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $sqlScript .= "INSERT INTO $table VALUES(";
            for ($j = 0; $j < $columnCount; $j++) {
                if (isset($row[$j])) {
                    $sqlScript .= '"' . addslashes($row[$j]) . '"';
                } else {
                    $sqlScript .= 'NULL';
                }
                if ($j < ($columnCount - 1)) {
                    $sqlScript .= ',';
                }
            }
            $sqlScript .= ");\n";
        }
        $sqlScript .= "\n";
    }

    if (!empty($sqlScript)) {
        $backup_file_name = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . $backup_file_name . "\"");
        echo $sqlScript;
        logActivity($pdo, $_SESSION['user_id'], 'Database Backup', 'System backup generated');
        exit;
    }
}

include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold text-dark">System Maintenance</h2>
        <p class="text-muted small mb-0">Database backups and system health</p>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="card-title mb-0 fw-bold text-dark">Database Backup</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">Download a full backup of your system data including employees, payroll history, and audit logs.</p>
                <form action="" method="POST">
                    <button type="submit" name="backup" class="btn btn-primary px-4">
                        <i class="fas fa-download me-2"></i> Generate & Download Backup
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="card-title mb-0 fw-bold text-dark">System Information</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Database Engine</span>
                        <span class="fw-bold">MySQL (PDO)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>PHP Version</span>
                        <span class="fw-bold"><?php echo phpversion(); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Session Security</span>
                        <span class="badge bg-success">Secure</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>