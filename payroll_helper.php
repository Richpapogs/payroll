<?php
/**
 * Payroll Deduction Helper Functions (Philippines 2026 Rules - V2)
 * 
 * CORE SETTINGS:
 * Divisor: 22 working days
 * Standard Hours: 8 hours/day
 */

define('WORKING_DAYS_DIVISOR', 22);
define('STANDARD_HOURS', 8);

function calculateDailyRate($monthly_salary) {
    return round($monthly_salary / WORKING_DAYS_DIVISOR, 2);
}

function calculateHourlyRate($daily_rate) {
    return round($daily_rate / STANDARD_HOURS, 2);
}

/**
 * Calculate SSS Employee Share based on Monthly Salary Credit (MSC) - 2026 Rules
 * Employee Share: 4.5%
 */
function calculateSSS($monthly_salary) {
    if ($monthly_salary < 4250) {
        $msc = 4000;
    } elseif ($monthly_salary >= 29750) {
        $msc = 30000;
    } else {
        $msc = floor(($monthly_salary - 4250) / 500) * 500 + 4500;
        if ($msc > 30000) $msc = 30000;
    }
    return round($msc * 0.045, 2);
}

/**
 * Calculate PhilHealth Employee Share - 2026 Rules
 */
function calculatePhilHealth($monthly_salary) {
    $floor = 10000;
    $ceiling = 100000;
    $employee_share_rate = 0.025; // 2.5%
    
    $base = $monthly_salary;
    if ($base < $floor) $base = $floor;
    if ($base > $ceiling) $base = $ceiling;
    
    return round($base * $employee_share_rate, 2);
}

/**
 * Calculate Pag-IBIG Employee Share
 */
function calculatePagIBIG($monthly_salary) {
    $rate = ($monthly_salary <= 1500) ? 0.01 : 0.02;
    $max_contribution = 100;
    $contribution = $monthly_salary * $rate;
    return round(min($contribution, $max_contribution), 2);
}

/**
 * Calculate BIR Withholding Tax (TRAIN Law 2023-2026 Brackets)
 * Input is TAXABLE INCOME for the cutoff
 */
function calculateTax($taxable_income_cutoff) {
    // Annualize for bracket check (based on 24 cutoffs per year)
    $annual_taxable = $taxable_income_cutoff * 24;

    if ($annual_taxable <= 250000) {
        $annual_tax = 0;
    } elseif ($annual_taxable <= 400000) {
        $annual_tax = ($annual_taxable - 250000) * 0.15;
    } elseif ($annual_taxable <= 800000) {
        $annual_tax = 22500 + ($annual_taxable - 400000) * 0.20;
    } elseif ($annual_taxable <= 2000000) {
        $annual_tax = 102500 + ($annual_taxable - 800000) * 0.25;
    } elseif ($annual_taxable <= 8000000) {
        $annual_tax = 402500 + ($annual_taxable - 2000000) * 0.30;
    } else {
        $annual_tax = 2202500 + ($annual_taxable - 8000000) * 0.35;
    }

    return round($annual_tax / 24, 2);
}

/**
 * Calculate Base Pay based on actual hours worked (converted to days for rate application)
 */
function calculateBasePay($daily_rate, $days_present_decimal) {
    return round($daily_rate * $days_present_decimal, 2);
}

/**
 * Calculate Detailed Gross Pay
 */
function calculateGrossPay($base_pay, $overtime_hrs, $hourly_rate, $bonus, $double_pay_days, $daily_rate, $late_mins, $undertime_mins) {
    $ot_pay = round($overtime_hrs * $hourly_rate, 2);
    $double_pay_bonus = round($double_pay_days * $daily_rate, 2); // Additional daily rate for double pay days
    
    $late_deduction = round(($late_mins / 60) * $hourly_rate, 2);
    $undertime_deduction = round(($undertime_mins / 60) * $hourly_rate, 2);
    $att_deductions = $late_deduction + $undertime_deduction;

    $gross = ($base_pay + $ot_pay + $bonus + $double_pay_bonus);

    return [
        'gross' => round($gross, 2),
        'ot_amt' => $ot_pay,
        'double_pay_amt' => $double_pay_bonus,
        'att_deduction' => $att_deductions
    ];
}

/**
 * Recalculate an existing payroll record based on latest attendance from scratch
 */
function recalculatePayroll($pdo, $payroll_id) {
    // 1. Fetch payroll details to get employee and period
    $stmt = $pdo->prepare("SELECT employee_id, cutoff_start, cutoff_end, bonus_pay FROM payroll WHERE id = ?");
    $stmt->execute([$payroll_id]);
    $p = $stmt->fetch();
    if (!$p) return false;

    $employee_id = $p['employee_id'];
    $cutoff_start = $p['cutoff_start'];
    $cutoff_end = $p['cutoff_end'];
    $bonus = (float)$p['bonus_pay'];

    // 2. Fetch employee details for salary/rates
    $stmt_emp = $pdo->prepare("SELECT salary FROM employees WHERE id = ?");
    $stmt_emp->execute([$employee_id]);
    $employee = $stmt_emp->fetch();
    if (!$employee) return false;

    // Standard Philippines Rates (Monthly / 22 / 8)
    $daily_rate = $employee['salary'] / WORKING_DAYS_DIVISOR;
    $hourly_rate = $daily_rate / STANDARD_HOURS;

    // 3. FORCE RECALCULATION: Fetch ALL attendance logs for the period
    $stmt_att = $pdo->prepare("SELECT total_hours, late_minutes, undertime_minutes, is_double_pay FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?");
    $stmt_att->execute([$employee_id, $cutoff_start, $cutoff_end]);
    $attendance = $stmt_att->fetchAll(PDO::FETCH_ASSOC);

    $total_worked_hrs = 0;
    $total_ot_hrs = 0;
    $total_double_pay_days = 0;
    $total_late_mins = 0;
    $total_undertime_mins = 0;

    foreach ($attendance as $att) {
        $hrs = (float)$att['total_hours'];
        if ($hrs > 0) {
            $total_worked_hrs += $hrs;
            if ($hrs > STANDARD_HOURS) {
                $total_ot_hrs += ($hrs - STANDARD_HOURS);
            }
            if ($att['is_double_pay']) {
                $total_double_pay_days += ($hrs / STANDARD_HOURS); // Pro-rated double pay
            }
            $total_late_mins += (int)$att['late_minutes'];
            $total_undertime_mins += (int)$att['undertime_minutes'];
        }
    }

    // 4. Add Approved-Paid Leaves
    $stmt_leaves = $pdo->prepare("SELECT SUM(requested_hours) FROM leave_requests WHERE employee_id = ? AND status = 'Approved' AND payment_status = 'Paid' AND (start_date BETWEEN ? AND ? OR end_date BETWEEN ? AND ?)");
    $stmt_leaves->execute([$employee_id, $cutoff_start, $cutoff_end, $cutoff_start, $cutoff_end]);
    $paid_leave_hours = (float)($stmt_leaves->fetchColumn() ?: 0);
    $total_worked_hrs += $paid_leave_hours;

    // 5. MATH FIX: Calculate EVERYTHING from scratch
    $days_worked_decimal = round($total_worked_hrs / STANDARD_HOURS, 3);
    $base_pay = round($daily_rate * $days_worked_decimal, 2);
    
    $ot_pay = round($total_ot_hrs * $hourly_rate, 2);
    $double_pay_bonus = round($total_double_pay_days * $daily_rate, 2);
    
    $late_deduction = round(($total_late_mins / 60) * $hourly_rate, 2);
    $undertime_deduction = round(($total_undertime_mins / 60) * $hourly_rate, 2);
    $att_deductions = $late_deduction + $undertime_deduction;

    // Calculate Gross Pay (Earnings before government deductions)
    // MATH FIX: Gross Pay = (Total Hours / 8) * Daily Rate + OT + Bonus + Double Pay
    // We do NOT subtract att_deductions here because base_pay already accounts for actual hours worked.
    $gross_pay = ($base_pay + $ot_pay + $bonus + $double_pay_bonus);
    
    // ZERO-FLOOR LOGIC: Gross pay cannot be negative
    if ($gross_pay < 0) $gross_pay = 0;
    $gross_pay = round($gross_pay, 2);
    
    // Government Deductions (Calculated based on FULL monthly salary)
    $sss = calculateSSS($employee['salary']);
    $philhealth = calculatePhilHealth($employee['salary']);
    $pagibig = calculatePagIBIG($employee['salary']);

    // Withholding Tax (Based on Taxable Income)
    $taxable_income = $gross_pay - ($sss + $philhealth + $pagibig);
    if ($taxable_income < 0) $taxable_income = 0;
    $tax = calculateTax($taxable_income);

    $total_deductions = round($sss + $philhealth + $pagibig + $tax, 2);
    $net_pay = $gross_pay - $total_deductions;
    
    // ZERO-FLOOR LOGIC: Net pay cannot be negative
    if ($net_pay < 0) $net_pay = 0;
    $net_pay = round($net_pay, 2);

    // 6. Update record in a single operation
    $stmt_upd = $pdo->prepare("UPDATE payroll SET 
        days_worked = ?, 
        basic_pay = ?, 
        overtime_pay = ?, 
        bonus_pay = ?, 
        double_pay_amt = ?, 
        attendance_deductions = ?, 
        gross_pay = ?, 
        sss = ?, 
        philhealth = ?, 
        pagibig = ?, 
        withholding_tax = ?, 
        total_deductions = ?, 
        net_pay = ? 
        WHERE id = ?");
    
    return $stmt_upd->execute([
        $days_worked_decimal, 
        $base_pay, 
        $ot_pay, 
        $bonus, 
        $double_pay_bonus, 
        $att_deductions, 
        $gross_pay, 
        $sss, 
        $philhealth, 
        $pagibig, 
        $tax, 
        $total_deductions, 
        $net_pay, 
        $payroll_id
    ]);
}

/**
 * Upsert Payroll Record (Update or Insert)
 * Used when saving attendance to ensure payroll is immediately updated/created
 */
function upsertPayroll($pdo, $employee_id, $attendance_date) {
    // 1. Determine the cutoff period for this date
    // Standard periods: 1-15 and 16-end, OR user-defined periods
    // For this project, we'll look for an existing payroll record covering this date first
    $stmt = $pdo->prepare("SELECT id FROM payroll WHERE employee_id = ? AND ? BETWEEN cutoff_start AND cutoff_end");
    $stmt->execute([$employee_id, $attendance_date]);
    $payroll = $stmt->fetch();

    if ($payroll) {
        // Update existing
        return recalculatePayroll($pdo, $payroll['id']);
    } else {
        // Create new payroll record for the current month's default cutoff
        $day = (int)date('d', strtotime($attendance_date));
        $month = date('m', strtotime($attendance_date));
        $year = date('Y', strtotime($attendance_date));
        
        if ($day <= 15) {
            $start = "$year-$month-01";
            $end = "$year-$month-15";
        } else {
            $start = "$year-$month-16";
            $end = date('Y-m-t', strtotime($attendance_date));
        }

        $stmt_ins = $pdo->prepare("INSERT INTO payroll (employee_id, cutoff_start, cutoff_end, payroll_date, status) VALUES (?, ?, ?, ?, 'Pending')");
        $stmt_ins->execute([$employee_id, $start, $end, date('Y-m-d')]);
        $new_id = $pdo->lastInsertId();
        
        return recalculatePayroll($pdo, $new_id);
    }
}
?>
