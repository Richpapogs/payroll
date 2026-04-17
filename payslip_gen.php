<?php
require_once 'config.php';
require_once 'assets/fpdf/fpdf.php';
checkLogin();

if (!isset($_GET['id'])) {
    die("Payroll ID is required.");
}

$payroll_id = (int)$_GET['id'];

// Fetch payroll data
$stmt = $pdo->prepare("SELECT p.*, e.name, e.employee_id as eid, e.position, e.salary as base_salary 
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

// Create PDF
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'PAYROLL MANAGEMENT SYSTEM', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, 'OFFICIAL PAYSLIP', 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Employee Details
$pdf->Cell(100, 7, 'Employee Name: ' . $p['name'], 0, 0);
$pdf->Cell(0, 7, 'Employee ID: ' . $p['eid'], 0, 1);
$pdf->Cell(100, 7, 'Position: ' . $p['position'], 0, 0);
$pdf->Cell(0, 7, 'Pay Period: ' . $p['cutoff_start'] . ' to ' . $p['cutoff_end'], 0, 1);
$pdf->Ln(10);

// Salary Breakdown
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'SALARY BREAKDOWN', 1, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(100, 7, 'Basic Salary:', 1, 0);
$pdf->Cell(0, 7, 'P ' . number_format($p['base_salary'], 2), 1, 1, 'R');
$pdf->Cell(100, 7, 'Gross Pay (after adjustments):', 1, 0);
$pdf->Cell(0, 7, 'P ' . number_format($p['gross_pay'], 2), 1, 1, 'R');
$pdf->Ln(5);

// Deductions
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'DEDUCTIONS', 1, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(100, 7, 'SSS:', 1, 0);
$pdf->Cell(0, 7, 'P ' . number_format($p['sss'], 2), 1, 1, 'R');
$pdf->Cell(100, 7, 'PhilHealth:', 1, 0);
$pdf->Cell(0, 7, 'P ' . number_format($p['philhealth'], 2), 1, 1, 'R');
$pdf->Cell(100, 7, 'Pag-IBIG:', 1, 0);
$pdf->Cell(0, 7, 'P ' . number_format($p['pagibig'], 2), 1, 1, 'R');
$pdf->Cell(100, 7, 'Withholding Tax:', 1, 0);
$pdf->Cell(0, 7, 'P ' . number_format($p['withholding_tax'], 2), 1, 1, 'R');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(100, 7, 'Total Deductions:', 1, 0);
$pdf->Cell(0, 7, 'P ' . number_format($p['total_deductions'], 2), 1, 1, 'R');
$pdf->Ln(10);

// Net Pay
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(100, 10, 'NET PAY:', 1, 0, 'L', true);
$pdf->Cell(0, 10, 'P ' . number_format($p['net_pay'], 2), 1, 1, 'R', true);

$pdf->Ln(20);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 10, 'This is a computer-generated payslip. No signature required.', 0, 1, 'C');

$pdf->Output('I', 'Payslip_' . $p['eid'] . '.pdf');
?>
