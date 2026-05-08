<?php
require_once 'config.php';

echo "Force Syncing Attendance Statuses...\n";

try {
    // Find all approved leave requests
    $stmt = $pdo->query("SELECT * FROM leave_requests WHERE status = 'Approved'");
    $leaves = $stmt->fetchAll();

    foreach ($leaves as $l) {
        $begin = new DateTime($l['start_date']);
        $end = new DateTime($l['end_date']);
        $end->modify('+1 day');
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($begin, $interval ,$end);
        
        $status_to_set = ($l['payment_status'] === 'Paid') ? 'Leave | Paid' : 'Leave | Un Paid';
        $hrs = ($l['payment_status'] === 'Paid') ? (float)$l['requested_hours'] : 0.00;

        foreach($daterange as $date){
            $att_date = $date->format("Y-m-d");
            
            // Check if attendance exists
            $stmt_check = $pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $stmt_check->execute([$l['employee_id'], $att_date]);
            
            if ($stmt_check->rowCount() > 0) {
                // Update existing
                $stmt_upd = $pdo->prepare("UPDATE attendance SET status = ?, total_hours = ? WHERE employee_id = ? AND attendance_date = ?");
                $stmt_upd->execute([$status_to_set, $hrs, $l['employee_id'], $att_date]);
                echo "Updated: Emp {$l['employee_id']} on $att_date to $status_to_set\n";
            } else {
                // Insert new
                $stmt_ins = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status, total_hours) VALUES (?, ?, ?, ?)");
                $stmt_ins->execute([$l['employee_id'], $att_date, $status_to_set, $hrs]);
                echo "Inserted: Emp {$l['employee_id']} on $att_date as $status_to_set\n";
            }
        }
    }
    echo "Sync Complete!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>