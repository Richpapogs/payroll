<?php
require_once 'config.php';
authorize(['employee']);

$employee_id = $_SESSION['employee_id'];

// Fetch employee full name for password logic
$stmt_emp = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
$stmt_emp->execute([$employee_id]);
$full_name = $stmt_emp->fetchColumn() ?: $_SESSION['username'];

$stmt = $pdo->prepare("SELECT * FROM payroll WHERE employee_id = ? ORDER BY payroll_date DESC");
$stmt->execute([$employee_id]);
$payrolls = $stmt->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold text-dark">My Payslips</h2>
        <p class="text-muted small mb-0">View and download your salary statements</p>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0 fw-bold text-dark">Payslip History</h5>
        <span class="badge bg-success-subtle text-success border border-success-subtle fw-medium">All Paid</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Pay Period</th>
                        <th>Gross Earnings</th>
                        <th>Total Deductions</th>
                        <th>Net Take-Home</th>
                        <th>Date Issued</th>
                        <th class="text-end pe-4">Statement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($payrolls) > 0): ?>
                        <?php foreach ($payrolls as $p): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo date('M d', strtotime($p['cutoff_start'])); ?> - <?php echo date('M d, Y', strtotime($p['cutoff_end'])); ?></div>
                                <div class="text-muted smaller">Full Cutoff Period</div>
                            </td>
                            <td class="fw-medium">₱<?php echo number_format($p['gross_pay'], 2); ?></td>
                            <td class="text-danger small">
                                -₱<?php echo number_format($p['total_deductions'], 2); ?>
                                <button class="btn btn-link btn-sm text-muted p-0 ms-1" 
                                        type="button" 
                                        data-bs-toggle="popover" 
                                        data-bs-trigger="focus"
                                        title="Deduction Breakdown" 
                                        data-bs-html="true"
                                        data-bs-content="<div class='small'>
                                            <div class='d-flex justify-content-between mb-1'><span>SSS:</span> <span class='fw-bold ms-3'>₱<?php echo number_format($p['sss'], 2); ?></span></div>
                                            <div class='d-flex justify-content-between mb-1'><span>PhilHealth:</span> <span class='fw-bold ms-3'>₱<?php echo number_format($p['philhealth'], 2); ?></span></div>
                                            <div class='d-flex justify-content-between mb-1'><span>Pag-IBIG:</span> <span class='fw-bold ms-3'>₱<?php echo number_format($p['pagibig'], 2); ?></span></div>
                                            <div class='d-flex justify-content-between'><span>Tax:</span> <span class='fw-bold ms-3'>₱<?php echo number_format($p['withholding_tax'], 2); ?></span></div>
                                        </div>">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </td>
                            <td>
                                <div class="fw-bold text-success">₱<?php echo number_format($p['net_pay'], 2); ?></div>
                            </td>
                            <td class="text-muted small"><?php echo date('M d, Y', strtotime($p['payroll_date'])); ?></td>
                            <td class="text-end pe-4">
                                <button type="button" 
                                        class="btn btn-sm btn-outline-primary view-payslip-btn" 
                                        data-id="<?php echo $p['id']; ?>"
                                        data-name="<?php echo h($full_name); ?>"
                                        data-eid="<?php echo h($_SESSION['employee_id_val'] ?? ''); ?>">
                                    <i class="fas fa-file-pdf me-1"></i> View Payslip
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted mb-2"><i class="fas fa-file-invoice-dollar fa-3x opacity-25"></i></div>
                                <div class="fw-bold">No payslips available</div>
                                <div class="small text-muted">Your issued payslips will appear here</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Password Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">Security Verification</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="small text-muted mb-3">Please enter your payslip password.</p>
                <div class="mb-3">
                    <input type="password" id="payslip_password" class="form-control" placeholder="Enter Password">
                    <div id="password_error" class="text-danger smaller mt-1 d-none">Incorrect password. Please try again.</div>
                </div>
                <button type="button" id="verify_password_btn" class="btn btn-primary w-100 fw-bold py-2">View Payslip</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let currentPayrollId = null;
    let expectedPassword = '';

    $('.view-payslip-btn').on('click', function() {
        currentPayrollId = $(this).data('id');
        const name = $(this).data('name');
        const eid = $(this).data('eid');
        
        // Generate expected password: [FirstInitial_Uppercase][LastName_Lowercase][Last4_EmployeeID]
        const nameParts = name.trim().split(' ');
        const firstName = nameParts[0];
        const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
        
        const firstInitial = firstName.charAt(0).toUpperCase();
        const lastNameLower = lastName.toLowerCase();
        const last4Id = eid.slice(-4);
        
        expectedPassword = firstInitial + lastNameLower + last4Id;
        
        $('#payslip_password').val('');
        $('#password_error').addClass('d-none');
        $('#passwordModal').modal('show');
    });

    $('#verify_password_btn').on('click', function() {
        const enteredPassword = $('#payslip_password').val();
        if (enteredPassword === expectedPassword) {
            $('#passwordModal').modal('hide');
            window.open('payslip_gen.php?id=' + currentPayrollId, '_blank');
        } else {
            $('#password_error').removeClass('d-none');
            $('#payslip_password').addClass('is-invalid').focus();
        }
    });

    $('#payslip_password').on('keypress', function(e) {
        if (e.which === 13) {
            $('#verify_password_btn').click();
        }
    }).on('input', function() {
        $(this).removeClass('is-invalid');
        $('#password_error').addClass('d-none');
    });
});
</script>

<?php include 'footer.php'; ?>
