<?php
require_once 'config.php';
require_once 'payroll_helper.php';

echo "Force Syncing All Payroll Records...\n";

try {
    $stmt = $pdo->query("SELECT id FROM payroll");
    $payrolls = $stmt->fetchAll();
    
    $count = 0;
    foreach ($payrolls as $p) {
        if (recalculatePayroll($pdo, $p['id'])) {
            $count++;
        }
    }
    
    echo "Successfully synced $count payroll records.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>