    </div><!-- End of container-fluid -->
</div><!-- End of #content -->
</div><!-- End of .wrapper -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Font Awesome -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function () {
    // Check for saved sidebar state
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
        $('#sidebar').addClass('collapsed');
    }

    // Sidebar toggle
    $('#sidebarCollapse').on('click', function () {
        $('#sidebar').toggleClass('collapsed');
        // Save state to localStorage
        localStorage.setItem('sidebar-collapsed', $('#sidebar').hasClass('collapsed'));
    });

    // Initialize all popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl)
    });
});
</script>
</body>
</html>
