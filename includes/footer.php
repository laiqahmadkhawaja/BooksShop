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

    // Safely reparent existing modals on initial DOM load before any user interaction
    function reparentModals() {
        document.querySelectorAll('.modal').forEach(function(modal) {
            if (modal.parentElement && modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', reparentModals);
    } else {
        reparentModals();
    }

    // Android WebView: Bootstrap's .fade transition can get stuck, leaving modal
    // invisible (the grey-line symptom). This listener fires AFTER Bootstrap's
    // shown.bs.modal event completes and force-paints the modal visible.
    // NOTE: we use shown (not show) to avoid interfering with Bootstrap's transition FSM.
    document.addEventListener('shown.bs.modal', function(e) {
        var modal = e.target;
        var dialog = modal.querySelector('.modal-dialog');
        var content = modal.querySelector('.modal-content');
        // Force visibility — these are additive overrides, not replacing Bootstrap classes
        modal.style.cssText += '; display:block !important; opacity:1 !important;';
        if (dialog) {
            dialog.style.cssText += '; opacity:1 !important; transform:none !important;';
        }
        if (content) {
            // If content collapsed to 0, give it a floor so it becomes visible
            var h = content.getBoundingClientRect().height;
            if (h < 50) {
                content.style.cssText += '; min-height:250px !important;';
            }
        }
    });

    // Also patch Bootstrap's _isTransitioning flag reset on show.bs.modal
    // to handle cases where transitionend never fires in WebView
    document.addEventListener('show.bs.modal', function(e) {
        var modal = e.target;
        // Force-clear _isTransitioning after a safe delay so Bootstrap doesn't stay locked
        setTimeout(function() {
            try {
                var bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal && bsModal._isTransitioning) {
                    bsModal._isTransitioning = false;
                }
            } catch(err) {}
            // Also force-show
            modal.style.display = 'block';
            modal.style.opacity = '1';
        }, 350);
    });

    // Android APK Native Share / Print Interceptor
    var originalWindowPrint = window.print;
    window.print = function() {
        if (window.AndroidPrinter) {
            if (typeof window.AndroidPrinter.sharePageAsImage === 'function') {
                window.AndroidPrinter.sharePageAsImage(document.title || 'Document');
                return;
            } else if (typeof window.AndroidPrinter.printOrShare === 'function') {
                window.AndroidPrinter.printOrShare(window.location.href, document.title || 'Document');
                return;
            }
        }
        if (originalWindowPrint) {
            originalWindowPrint.call(window);
        }
    };

    window.triggerAppShare = function(url, title) {
        url = url || window.location.href;
        title = title || document.title || 'Document';
        if (window.AndroidPrinter) {
            if (typeof window.AndroidPrinter.sharePageAsImage === 'function') {
                window.AndroidPrinter.sharePageAsImage(title);
                return;
            } else if (typeof window.AndroidPrinter.printOrShare === 'function') {
                window.AndroidPrinter.printOrShare(url, title);
                return;
            }
        }
        if (navigator.share) {
            navigator.share({ title: title, url: url }).catch(function(){});
            return;
        }
        window.print();
    };

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
