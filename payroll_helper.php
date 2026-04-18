<?php
/**
 * Payroll Deduction Helper Functions (Philippines 2026 Rules)
 */

/**
 * Calculate SSS Employee Share based on Monthly Salary Credit (MSC)
 * @param float $salary
 * @return float
 */
function calculateSSS($salary) {
    // SSS Contribution Table 2025/2026
    // Brackets of 500 starting from 4,250 up to 30,000
    if ($salary < 4250) {
        $msc = 4000;
    } elseif ($salary >= 29750) {
        $msc = 30000;
    } else {
        // Find the bracket
        // Salary Range: [Range Start, Range End] -> MSC
        // Brackets are like 4250 - 4749.99 -> 4500
        // Formula: MSC = round((salary - 250) / 500) * 500
        // Let's use a more precise mapping if possible, but the 500 increment is standard.
        $msc = floor(($salary - 4250) / 500) * 500 + 4500;
        
        // Ensure MSC doesn't exceed 30,000
        if ($msc > 30000) $msc = 30000;
    }
    
    return round($msc * 0.045, 2);
}

/**
 * Calculate PhilHealth Employee Share
 * @param float $salary
 * @return float
 */
function calculatePhilHealth($salary) {
    $floor = 10000;
    $ceiling = 100000;
    $rate = 0.05; // Total 5%
    $employee_share_rate = 0.025; // 50% of 5%
    
    $base = $salary;
    if ($base < $floor) $base = $floor;
    if ($base > $ceiling) $base = $ceiling;
    
    return round($base * $employee_share_rate, 2);
}

/**
 * Calculate Pag-IBIG Employee Share
 * @param float $salary
 * @return float
 */
function calculatePagIBIG($salary) {
    $rate = ($salary <= 1500) ? 0.01 : 0.02;
    $max_contribution = 100;
    
    $contribution = $salary * $rate;
    return round(min($contribution, $max_contribution), 2);
}

/**
 * Calculate BIR Withholding Tax (TRAIN Law 2023-2026 Brackets)
 * @param float $taxable_income
 * @return float
 */
function calculateTax($taxable_income) {
    if ($taxable_income <= 20833) {
        return 0;
    } elseif ($taxable_income <= 33333) {
        return round(($taxable_income - 20833) * 0.15, 2);
    } elseif ($taxable_income <= 66667) {
        return round(1875 + ($taxable_income - 33333) * 0.20, 2);
    } elseif ($taxable_income <= 166667) {
        return round(8541.67 + ($taxable_income - 66667) * 0.25, 2);
    } elseif ($taxable_income <= 666667) {
        return round(33541.67 + ($taxable_income - 166667) * 0.30, 2);
    } else {
        return round(183541.67 + ($taxable_income - 666667) * 0.35, 2);
    }
}
?>