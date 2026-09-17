        </div>
    </main>

    <!-- Footer di dalam Main Wrapper -->
    <footer class="bg-white border-top py-3 mt-auto no-print text-muted">
        <div class="container-fluid px-4 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <div class="small">
                &copy; <?= date('Y') ?> <strong>Sistem Inventaris Perpustakaan</strong> (SIPerpus).
            </div>
            <div class="small text-secondary">
                Mode Offline XAMPP &bull; PHP Native &amp; MySQL
            </div>
        </div>
    </footer>
</div><!-- /main-wrapper -->

<!-- Offline Local JS Scripts -->
<script src="<?= base_url('assets/js/bootstrap.bundle.min.js') ?>"></script>
<?php if (isset($includeChartJs) && $includeChartJs): ?>
<script src="<?= base_url('assets/js/chart.umd.min.js') ?>"></script>
<?php endif; ?>

<!-- Script Interaksi Toggle Sidebar Vertikal -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnToggle = document.getElementById('btnToggleSidebar');
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    // Cek preferensi state sidebar desktop yang tersimpan
    if (window.innerWidth >= 992) {
        const isCollapsed = localStorage.getItem('siperpus_sidebar_collapsed') === 'true';
        if (isCollapsed) {
            document.body.classList.add('sidebar-collapsed');
        }
    }

    if (btnToggle) {
        btnToggle.addEventListener('click', function() {
            if (window.innerWidth >= 992) {
                // Mode Desktop: Toggle collapse/expand dan simpan ke localStorage
                document.body.classList.toggle('sidebar-collapsed');
                const collapsed = document.body.classList.contains('sidebar-collapsed');
                localStorage.setItem('siperpus_sidebar_collapsed', collapsed);
            } else {
                // Mode Mobile: Toggle overlay & slide sidebar
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
});
</script>

</body>
</html>
