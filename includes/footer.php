<?php
// includes/footer.php
?>
    </div> <!-- Close container-fluid from header -->

    <footer class="bg-white border-top py-3 text-center text-secondary small mt-auto">
        <div class="container-fluid">
            <span>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars(getSetting('shop_name', 'Kitab Ghar')) ?></strong> &mdash; Book Shop Management System. Designed for Fast Bookstore & Stationery Billing.</span>
        </div>
    </footer>
</div> <!-- Close main-content -->

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- JsBarcode -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

<script>
    // Sidebar Toggle for Mobile
    document.getElementById('sidebarToggleBtn')?.addEventListener('click', function() {
        document.getElementById('mainSidebar').classList.toggle('show');
    });

    // Global Hotkeys
    document.addEventListener('keydown', function(e) {
        // F2 - Jump to POS
        if (e.key === 'F2') {
            e.preventDefault();
            if (!window.location.pathname.endsWith('pos.php')) {
                window.location.href = 'pos.php';
            } else {
                document.getElementById('barcodeSearchInput')?.focus();
            }
        }
    });
</script>
</body>
</html>
