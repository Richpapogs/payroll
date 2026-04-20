<?php
require_once 'config.php';
checkLogin();

if (!isset($_GET['id'])) {
    die("Payroll ID is required.");
}

$payroll_id = (int)$_GET['id'];

// Fetch payroll data with enhanced fields
$stmt = $pdo->prepare("SELECT p.*, e.name, e.employee_id as eid, e.position, e.salary as monthly_base 
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

// Derive values for payslip
$daily_rate = round($p['monthly_base'] / 22, 2);
$days_worked = $daily_rate > 0 ? round($p['basic_pay'] / $daily_rate, 1) : 0;
$hourly_rate = round($daily_rate / 8, 2);
$ot_hrs = $hourly_rate > 0 ? round($p['overtime_pay'] / $hourly_rate, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo h($p['name']); ?> - <?php echo h($p['eid']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; padding: 40px 0; }
        .payslip-container { max-width: 850px; margin: 0 auto; background: white; padding: 60px; border-radius: 4px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .brand-section { border-bottom: 3px solid #3b82f6; padding-bottom: 25px; margin-bottom: 40px; }
        .info-label { color: #64748b; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.1em; }
        .info-value { color: #0f172a; font-weight: 600; font-size: 1rem; }
        .section-header { font-size: 0.85rem; font-weight: 800; color: #334155; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 20px; }
        .table-custom { font-size: 0.9rem; }
        .table-custom td { padding: 10px 0; border-bottom: 1px dashed #e2e8f0; }
        .table-custom .amount { font-weight: 600; text-align: right; }
        .net-pay-section { background: #f8fafc; border: 2px solid #3b82f6; padding: 25px; margin-top: 40px; }
        .footer-note { margin-top: 60px; padding-top: 20px; color: #94a3b8; font-size: 0.75rem; border-top: 1px solid #f1f5f9; }
        
        @media print {
            body { background: white; padding: 0; }
            .payslip-container { box-shadow: none; border: 1px solid #eee; max-width: 100%; width: 100%; padding: 40px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container no-print mb-4 text-center">
        <button onclick="window.print()" class="btn btn-primary px-4 shadow-sm me-2 fw-bold"><i class="fas fa-print me-2"></i> Print Payslip</button>
        <a href="javascript:window.close()" class="btn btn-light border px-4 fw-bold">Close Tab</a>
    </div>

    <div class="payslip-container">
        <div class="brand-section d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-0 text-primary" style="letter-spacing: -1px;">PAYROLL PRO</h2>
                <p class="text-muted small mb-0 fw-medium">Philippine Payroll System (v2026)</p>
            </div>
            <div class="text-end">
                <h4 class="fw-bold mb-0 text-dark">OFFICIAL PAYSLIP</h4>
                <p class="text-muted small mb-0">Ref: #<?php echo str_pad($p['id'], 6, '0', STR_PAD_LEFT); ?></p>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-6">
                <div class="info-label">Employee Details</div>
                <div class="info-value h5 mb-0 fw-bold"><?php echo h($p['name']); ?></div>
                <div class="text-muted small"><?php echo h($p['position']); ?> (<?php echo h($p['eid']); ?>)</div>
                <div class="mt-2">
                    <span class="badge bg-light text-dark border">Monthly Base: ₱<?php echo number_format($p['monthly_base'], 2); ?></span>
                </div>
            </div>
            <div class="col-6 text-end">
                <div class="info-label">Pay Period (Cutoff)</div>
                <div class="info-value fw-bold"><?php echo date('M d', strtotime($p['cutoff_start'])); ?> - <?php echo date('M d, Y', strtotime($p['cutoff_end'])); ?></div>
                <div class="text-muted smaller mt-1">Processed: <?php echo date('F d, Y', strtotime($p['payroll_date'])); ?></div>
            </div>
        </div>

        <div class="row g-5">
            <div class="col-md-6">
                <div class="section-header">EARNINGS BREAKDOWN</div>
                <table class="table-custom w-100">
                    <tbody>
                        <tr>
                            <td>Base Pay (<?php echo $days_worked; ?> days worked)</td>
                            <td class="amount">₱<?php echo number_format($p['basic_pay'], 2); ?></td>
                        </tr>
                        <?php if ($p['overtime_pay'] > 0): ?>
                        <tr>
                            <td>Overtime (<?php echo $ot_hrs; ?> hrs logged)</td>
                            <td class="amount">₱<?php echo number_format($p['overtime_pay'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($p['double_pay_amt'] > 0): ?>
                        <tr>
                            <td>Double Pay Adjustment</td>
                            <td class="amount">₱<?php echo number_format($p['double_pay_amt'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($p['bonus_pay'] > 0): ?>
                        <tr>
                            <td>Bonuses / Adjustments</td>
                            <td class="amount">₱<?php echo number_format($p['bonus_pay'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="fw-bold text-dark border-0">
                            <td class="pt-3 h6 fw-bold">TOTAL GROSS PAY</td>
                            <td class="pt-3 amount h6 fw-bold">₱<?php echo number_format($p['gross_pay'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>

                <div class="section-header mt-4 text-danger">ATTENDANCE DEDUCTIONS</div>
                <table class="table-custom w-100">
                    <tbody>
                        <tr>
                            <td class="text-muted">Late / Undertime / Absences</td>
                            <td class="amount text-danger">-₱<?php echo number_format($p['attendance_deductions'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="col-md-6">
                <div class="section-header">GOVERNMENT DEDUCTIONS (PH)</div>
                <table class="table-custom w-100">
                    <tbody>
                        <tr>
                            <td>SSS Employee Share (4.5%)</td>
                            <td class="amount">-₱<?php echo number_format($p['sss'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>PhilHealth Share (2.5%)</td>
                            <td class="amount">-₱<?php echo number_format($p['philhealth'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Pag-IBIG Contribution</td>
                            <td class="amount">-₱<?php echo number_format($p['pagibig'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Withholding Tax (BIR)</td>
                            <td class="amount">-₱<?php echo number_format($p['withholding_tax'], 2); ?></td>
                        </tr>
                        <tr class="fw-bold text-danger border-0">
                            <td class="pt-3 h6 fw-bold">TOTAL DEDUCTIONS</td>
                            <td class="pt-3 amount h6 fw-bold">-₱<?php echo number_format($p['total_deductions'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="net-pay-section rounded d-flex justify-content-between align-items-center">
            <div>
                <div class="info-label text-primary">NET TAKE-HOME PAY</div>
                <div class="small text-muted fw-medium mt-1">Final Payable Amount</div>
            </div>
            <div class="text-end">
                <h1 class="fw-bold mb-0 text-dark" style="font-size: 2.8rem; letter-spacing: -2px;">₱<?php echo number_format($p['net_pay'], 2); ?></h1>
            </div>
        </div>

        <div class="footer-note text-center">
            <p class="mb-1 fw-bold text-dark">CONFIDENTIAL DOCUMENT</p>
            Issued by <strong>PAYROLL PRO v2026</strong>. 
            All calculations strictly follow Philippine Labor standards and TRAIN Law.
        </div>
    </div>
</body>
</html>
