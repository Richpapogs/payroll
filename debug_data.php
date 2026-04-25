<?php
require_once 'config.php';

echo "Simulating payroll calculation for isa (ID: 3):\n";
$employee_id = 3;
$cutoff_start = '2026-04-25';
$cutoff_end = '2026-05-24';

$stmt_att = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?");
$stmt_att->execute([$employee_id, $cutoff_start, $cutoff_end]);
$attendance = $stmt_att->fetchAll(PDO::FETCH_ASSOC);

$total_worked_hrs = 0;
foreach ($attendance as $att) {
    if ($att['total_hours'] > 0) {
        $total_worked_hrs += $att['total_hours'];
    }
}
echo "Total Worked Hours calculated: " . $total_worked_hrs . "\n";
$days_worked = $total_worked_hrs / 8;
echo "Days Worked calculated: " . $days_worked . "\n";

echo "\nPayroll Data:\n";
$stmt = $pdo->query("SELECT p.*, e.name FROM payroll p JOIN employees e ON p.employee_id = e.id ORDER BY p.id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
?>
