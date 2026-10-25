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

<script>
    // Mobile Sidebar Drawer Management
    const sidebar = document.getElementById('mainSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('show');
        if (backdrop) backdrop.classList.add('show');
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('show');
        if (backdrop) backdrop.classList.remove('show');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (sidebar && sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            closeSidebar();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function() {
            closeSidebar();
        });
    }

    // Auto-close sidebar on mobile when resizing up or pressing Esc
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
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
