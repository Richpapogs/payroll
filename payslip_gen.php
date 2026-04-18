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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo h($p['name']); ?> - <?php echo h($p['eid']); ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; padding: 40px 0; }
        .payslip-container { max-width: 800px; margin: 0 auto; background: white; padding: 50px; border-radius: 0; box-shadow: 0 0 20px rgba(0,0,0,0.05); }
        .brand-section { border-bottom: 2px solid #3b82f6; padding-bottom: 20px; margin-bottom: 30px; }
        .info-label { color: #64748b; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
        .info-value { color: #1e293b; font-weight: 600; }
        .table-custom thead th { background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 0.75rem; text-transform: uppercase; color: #64748b; }
        .table-custom td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; }
        .total-row { background: #f8fafc; font-weight: 700; font-size: 1.1rem; }
        .footer-note { border-top: 1px solid #e2e8f0; margin-top: 50px; padding-top: 20px; color: #94a3b8; font-style: italic; font-size: 0.85rem; }
        
        @media print {
            body { background: white; padding: 0; }
            .payslip-container { box-shadow: none; border: none; max-width: 100%; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container no-print mb-4 text-center">
        <button onclick="window.print()" class="btn btn-primary px-4 shadow-sm me-2">
            <i class="fas fa-print me-2"></i> Print Payslip
        </button>
        <a href="javascript:window.close()" class="btn btn-light border px-4">Close Tab</a>
    </div>

    <div class="payslip-container">
        <div class="brand-section d-flex justify-content-between align-items-center">
            <div>
                <h3 class="fw-bold mb-0 text-primary">PAYROLL PRO</h3>
                <p class="text-muted small mb-0">Enterprise Management Solutions</p>
            </div>
            <div class="text-end">
                <h4 class="fw-bold mb-0">OFFICIAL PAYSLIP</h4>
                <p class="text-muted small mb-0">Reference ID: <?php echo h($p['id']); ?></p>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-6">
                <div class="mb-3">
                    <div class="info-label">Employee Name</div>
                    <div class="info-value h5 mb-0"><?php echo h($p['name']); ?></div>
                </div>
                <div class="mb-3">
                    <div class="info-label">Employee ID</div>
                    <div class="info-value"><?php echo h($p['eid']); ?></div>
                </div>
                <div>
                    <div class="info-label">Position</div>
                    <div class="info-value"><?php echo h($p['position']); ?></div>
                </div>
            </div>
            <div class="col-6 text-end">
                <div class="mb-3">
                    <div class="info-label">Pay Period</div>
                    <div class="info-value"><?php echo date('M d, Y', strtotime($p['cutoff_start'])); ?> - <?php echo date('M d, Y', strtotime($p['cutoff_end'])); ?></div>
                </div>
                <div class="mb-3">
                    <div class="info-label">Date Processed</div>
                    <div class="info-value"><?php echo date('F d, Y', strtotime($p['payroll_date'])); ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="fw-bold mb-3 border-bottom pb-2">EARNINGS BREAKDOWN</h6>
                <table class="table table-custom w-100">
                    <tbody>
                        <tr>
                            <td>Basic Salary</td>
                            <td class="text-end fw-bold">₱<?php echo number_format($p['base_salary'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Adjustments (OT/Bonus)</td>
                            <td class="text-end fw-bold">₱<?php echo number_format($p['gross_pay'] - $p['base_salary'] + ($p['total_deductions'] - $p['withholding_tax'] - $p['sss'] - $p['philhealth'] - $p['pagibig']), 2); ?></td>
                        </tr>
                        <tr class="table-light">
                            <td class="fw-bold">Total Gross Earnings</td>
                            <td class="text-end fw-bold">₱<?php echo number_format($p['gross_pay'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold mb-3 border-bottom pb-2">DEDUCTIONS BREAKDOWN</h6>
                <table class="table table-custom w-100">
                    <tbody>
                        <tr>
                            <td>SSS Contribution</td>
                            <td class="text-end text-danger fw-bold">-₱<?php echo number_format($p['sss'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>PhilHealth</td>
                            <td class="text-end text-danger fw-bold">-₱<?php echo number_format($p['philhealth'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Pag-IBIG</td>
                            <td class="text-end text-danger fw-bold">-₱<?php echo number_format($p['pagibig'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Withholding Tax</td>
                            <td class="text-end text-danger fw-bold">-₱<?php echo number_format($p['withholding_tax'], 2); ?></td>
                        </tr>
                        <tr class="table-light">
                            <td class="fw-bold">Total Deductions</td>
                            <td class="text-end fw-bold text-danger">-₱<?php echo number_format($p['total_deductions'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5 p-4 rounded-3 total-row d-flex justify-content-between align-items-center">
            <span class="text-uppercase tracking-wider">NET TAKE-HOME PAY</span>
            <span class="h3 fw-bold mb-0 text-success">₱<?php echo number_format($p['net_pay'], 2); ?></span>
        </div>

        <div class="footer-note text-center">
            This is a computer-generated document. No manual signature is required for validity. 
            For inquiries regarding this payslip, please contact the HR Department.
        </div>
    </div>
</body>
</html>
