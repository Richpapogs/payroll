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

    $gross = ($base_pay + $ot_pay + $bonus + $double_pay_bonus) - $att_deductions;

    return [
        'gross' => round($gross, 2),
        'ot_amt' => $ot_pay,
        'double_pay_amt' => $double_pay_bonus,
        'att_deduction' => $att_deductions
    ];
}
?>
