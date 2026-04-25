<?php
require_once 'config.php';
require_once 'assets/fpdf/fpdf_protection.php';
checkLogin();

if (!isset($_GET['id'])) {
    die("Payroll ID is required.");
}

$payroll_id = (int)$_GET['id'];

// Fetch payroll data with employee birthdate for protection
$stmt = $pdo->prepare("SELECT p.*, e.name, e.employee_id as eid, e.position, e.salary as monthly_base, e.birthdate 
                       FROM payroll p 
                       JOIN employees e ON p.employee_id = e.id 
                       WHERE p.id = ?");
$stmt->execute([$payroll_id]);
$p = $stmt->fetch();

if (!$p) {
    die("Payroll record not found.");
}

// Check permissions
if (isEmployee() && $p['employee_id'] != $_SESSION['employee_id']) {
    die("Access denied.");
}

// Calculate values
$daily_rate = round($p['monthly_base'] / 22, 2);
$days_worked = $daily_rate > 0 ? round($p['basic_pay'] / $daily_rate, 1) : 0;
$hourly_rate = round($daily_rate / 8, 2);
$ot_hrs = $hourly_rate > 0 ? round($p['overtime_pay'] / $hourly_rate, 1) : 0;

// Setup PDF with protection
$pdf = new FPDF_Protection();
// Password: Employee ID + Birthdate (e.g. EMP-2026-0011990-01-01)
$password = $p['eid'] . $p['birthdate'];
$pdf->SetProtection(array('print'), $password, $password);

$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 20);
$pdf->SetTextColor(59, 130, 246); // Primary blue
$pdf->Cell(0, 10, 'PAYROLL PRO', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(0, 5, 'Philippine Payroll System (v2026)', 0, 1, 'L');

$pdf->SetY(10);
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 10, 'OFFICIAL PAYSLIP', 0, 1, 'R');
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(0, 5, 'Ref: #' . str_pad($p['id'], 6, '0', STR_PAD_LEFT), 0, 1, 'R');

$pdf->Ln(15);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(10);

// Employee Details
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(95, 5, 'EMPLOYEE DETAILS', 0, 0);
$pdf->Cell(95, 5, 'PAY PERIOD (CUTOFF)', 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(95, 7, $p['name'], 0, 0);
$pdf->Cell(95, 7, date('M d', strtotime($p['cutoff_start'])) . ' - ' . date('M d, Y', strtotime($p['cutoff_end'])), 0, 1, 'R');

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(95, 5, $p['position'] . ' (' . $p['eid'] . ')', 0, 0);
$pdf->Cell(95, 5, 'Processed: ' . date('F d, Y', strtotime($p['payroll_date'])), 0, 1, 'R');

$pdf->Ln(10);

// Earnings Breakdown
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(51, 65, 85);
$pdf->Cell(0, 8, 'EARNINGS BREAKDOWN', 'B', 1);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(140, 8, 'Base Pay (' . $days_worked . ' days worked)', 0, 0);
$pdf->Cell(50, 8, 'P' . number_format($p['basic_pay'], 2), 0, 1, 'R');

if ($p['overtime_pay'] > 0) {
    $pdf->Cell(140, 8, 'Overtime (' . $ot_hrs . ' hrs logged)', 0, 0);
    $pdf->Cell(50, 8, 'P' . number_format($p['overtime_pay'], 2), 0, 1, 'R');
}
if ($p['double_pay_amt'] > 0) {
    $pdf->Cell(140, 8, 'Double Pay Adjustment', 0, 0);
    $pdf->Cell(50, 8, 'P' . number_format($p['double_pay_amt'], 2), 0, 1, 'R');
}
if ($p['bonus_pay'] > 0) {
    $pdf->Cell(140, 8, 'Bonuses / Adjustments', 0, 0);
    $pdf->Cell(50, 8, 'P' . number_format($p['bonus_pay'], 2), 0, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(140, 10, 'TOTAL GROSS PAY', 0, 0);
$pdf->Cell(50, 10, 'P' . number_format($p['gross_pay'], 2), 0, 1, 'R');

$pdf->Ln(5);

// Deductions
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(185, 28, 28); // Red
$pdf->Cell(0, 8, 'DEDUCTIONS', 'B', 1);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(51, 65, 85);
$pdf->Cell(140, 8, 'Late / Undertime / Absences', 0, 0);
$pdf->Cell(50, 8, '-P' . number_format($p['attendance_deductions'], 2), 0, 1, 'R');

$pdf->Cell(140, 8, 'SSS Employee Share (4.5%)', 0, 0);
$pdf->Cell(50, 8, '-P' . number_format($p['sss'], 2), 0, 1, 'R');

$pdf->Cell(140, 8, 'PhilHealth Share (2.5%)', 0, 0);
$pdf->Cell(50, 8, '-P' . number_format($p['philhealth'], 2), 0, 1, 'R');

$pdf->Cell(140, 8, 'Pag-IBIG Contribution', 0, 0);
$pdf->Cell(50, 8, '-P' . number_format($p['pagibig'], 2), 0, 1, 'R');

$pdf->Cell(140, 8, 'Withholding Tax (BIR)', 0, 0);
$pdf->Cell(50, 8, '-P' . number_format($p['withholding_tax'], 2), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(185, 28, 28);
$pdf->Cell(140, 10, 'TOTAL DEDUCTIONS', 0, 0);
$pdf->Cell(50, 10, '-P' . number_format($p['total_deductions'], 2), 0, 1, 'R');

$pdf->Ln(10);

// Net Pay
$pdf->SetFillColor(248, 250, 252);
$pdf->Rect(10, $pdf->GetY(), 190, 25, 'F');
$pdf->SetY($pdf->GetY() + 5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(59, 130, 246);
$pdf->Cell(95, 5, 'NET TAKE-HOME PAY', 0, 0, 'L');
$pdf->SetTextColor(15, 23, 42);
$pdf->SetFont('Arial', 'B', 24);
$pdf->Cell(85, 15, 'P' . number_format($p['net_pay'], 2), 0, 1, 'R');

$pdf->Ln(20);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 5, 'CONFIDENTIAL DOCUMENT', 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(148, 163, 184);
$pdf->MultiCell(0, 5, "Issued by PAYROLL PRO v2026.\nAll calculations strictly follow Philippine Labor standards and TRAIN Law.", 0, 'C');

$filename = "Payslip_" . str_replace(' ', '_', $p['name']) . "_" . $p['eid'] . ".pdf";
$pdf->Output('I', $filename);
?>
