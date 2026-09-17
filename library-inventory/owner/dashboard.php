<?php
/**
 * Dashboard Owner (Kepala Perpustakaan)
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('owner');

$pageTitle = 'Dashboard Eksekutif Owner';
$includeChartJs = true;
require_once __DIR__ . '/../includes/header.php';

$db = get_db();

// 1. Statistik Kartu Utama
$totalBuku = $db->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalStok = $db->query("SELECT SUM(stok) FROM books")->fetchColumn() ?: 0;
$totalAnggota = $db->query("SELECT COUNT(*) FROM members WHERE status = 'aktif'")->fetchColumn();
$pinjamanAktif = $db->query("SELECT COUNT(*) FROM loans WHERE status IN ('dipinjam', 'terlambat')")->fetchColumn();
$totalDenda = $db->query("SELECT SUM(denda) FROM loans WHERE status = 'dikembalikan'")->fetchColumn() ?: 0;

// 2. Data Grafik Peminjaman 6 Bulan Terakhir
$monthlyLabels = [];
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $monthYear = date('Y-m', strtotime("-$i months"));
    $monthLabel = date('M Y', strtotime("-$i months"));
    $monthlyLabels[] = $monthLabel;

    $stmtMonthly = $db->prepare("SELECT COUNT(*) FROM loans WHERE DATE_FORMAT(tgl_pinjam, '%Y-%m') = ?");
    $stmtMonthly->execute([$monthYear]);
    $monthlyData[] = (int)$stmtMonthly->fetchColumn();
}

// 3. Data Distribusi Buku per Kategori
$stmtKategori = $db->query("
    SELECT c.nama_kategori, COUNT(b.id) AS total_buku
    FROM categories c
    LEFT JOIN books b ON c.id = b.kategori_id
    GROUP BY c.id, c.nama_kategori
    ORDER BY total_buku DESC
");
$kategoriRows = $stmtKategori->fetchAll();
$categoryLabels = array_column($kategoriRows, 'nama_kategori');
$categoryData = array_column($kategoriRows, 'total_buku');

// 4. Top 10 Buku Paling Sering Dipinjam
$stmtTopBuku = $db->query("
    SELECT b.kode_buku, b.judul, b.penulis, c.nama_kategori, COUNT(l.id) AS frekuensi_pinjam
    FROM books b
    JOIN categories c ON b.kategori_id = c.id
    LEFT JOIN loans l ON b.id = l.book_id
    GROUP BY b.id
    ORDER BY frekuensi_pinjam DESC, b.judul ASC
    LIMIT 10
");
$topBuku = $stmtTopBuku->fetchAll();

// 5. Audit Log Terbaru (5 data terakhir)
$stmtRecentLogs = $db->query("
    SELECT a.*, u.nama, u.username, u.role
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 5
");
$recentLogs = $stmtRecentLogs->fetchAll();
?>

<!-- Header Selamat Datang Owner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h3 class="fw-bold mb-1">Executive Dashboard - <?= htmlspecialchars($currentUser['nama']) ?> 👑</h3>
                <p class="text-muted small mb-0">Laporan statistik kinerja, sirkulasi buku, dan analitik perpustakaan.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= base_url('modules/reports/index.php') ?>" class="btn btn-primary shadow-sm">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i> Buka Laporan Lengkap
                </a>
                <a href="<?= base_url('modules/logs/index.php') ?>" class="btn btn-outline-secondary shadow-sm">
                    <i class="bi bi-clock-history me-1"></i> Audit Trail
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Kartu Metrik Utama -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl">
        <div class="card stat-card card-hover border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Judul / Total Fisik</span>
                    <h3 class="fw-bold my-1 text-primary"><?= number_format($totalBuku) ?> <span class="fs-6 fw-normal text-muted">(<?= number_format($totalStok) ?> eks)</span></h3>
                    <small class="text-muted">Koleksi buku aktif</small>
                </div>
                <div class="stat-icon-wrapper bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-book-half"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl">
        <div class="card stat-card card-hover border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Anggota Aktif</span>
                    <h3 class="fw-bold my-1 text-success"><?= number_format($totalAnggota) ?></h3>
                    <small class="text-muted">Member terdaftar</small>
                </div>
                <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl">
        <div class="card stat-card card-hover border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Peminjaman Aktif</span>
                    <h3 class="fw-bold my-1 text-warning"><?= number_format($pinjamanAktif) ?></h3>
                    <small class="text-muted">Buku di tangan peminjam</small>
                </div>
                <div class="stat-icon-wrapper bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-arrow-left-right"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl">
        <div class="card stat-card card-hover border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Denda Terkumpul</span>
                    <h3 class="fw-bold my-1 text-danger"><?= format_rupiah($totalDenda) ?></h3>
                    <small class="text-muted">Kas masuk denda</small>
                </div>
                <div class="stat-icon-wrapper bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grafik Analitik -->
<div class="row g-4 mb-4">
    <!-- Tren Peminjaman Bulanan -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-graph-up-arrow text-primary"></i>
                    <h5 class="card-title fw-bold mb-0">Tren Frekuensi Peminjaman (6 Bulan Terakhir)</h5>
                </div>
                <span class="badge bg-light text-secondary border">Chart.js Offline</span>
            </div>
            <div class="card-body p-4">
                <div style="height: 280px; position: relative;">
                    <canvas id="monthlyLoanChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Distribusi Kategori Buku -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-pie-chart text-info"></i>
                    <h5 class="card-title fw-bold mb-0">Distribusi Kategori</h5>
                </div>
            </div>
            <div class="card-body p-3 d-flex align-items-center justify-content-center">
                <div style="height: 260px; width: 100%; position: relative;">
                    <canvas id="categoryDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Top 10 Buku & Snippet Audit Log -->
<div class="row g-4">
    <!-- Top 10 Buku Paling Sering Dipinjam -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-trophy-fill text-warning"></i>
                    <h5 class="card-title fw-bold mb-0">Top 10 Buku Paling Banyak Diminati</h5>
                </div>
                <a href="<?= base_url('modules/books/index.php') ?>" class="btn btn-sm btn-outline-primary">Lihat Katalog</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>Buku &amp; Penulis</th>
                                <th>Kategori</th>
                                <th class="text-center">Dipinjam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topBuku)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Belum ada riwayat peminjaman buku.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($topBuku as $idx => $buku): ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?= $idx < 3 ? 'bg-warning text-dark' : 'bg-light text-muted border' ?> rounded-pill">
                                                <?= $idx + 1 ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($buku['judul']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($buku['penulis']) ?> &bull; <?= htmlspecialchars($buku['kode_buku']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-soft-info"><?= htmlspecialchars($buku['nama_kategori']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill px-3"><?= (int)$buku['frekuensi_pinjam'] ?>x</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Aktivitas Terbaru (Audit Trail) -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check text-success"></i>
                    <h5 class="card-title fw-bold mb-0">Aktivitas Sistem Terkini</h5>
                </div>
                <a href="<?= base_url('modules/logs/index.php') ?>" class="btn btn-sm btn-outline-secondary">Semua Log</a>
            </div>
            <div class="card-body p-3">
                <div class="list-group list-group-flush">
                    <?php if (empty($recentLogs)): ?>
                        <div class="text-center py-4 text-muted">Belum ada log aktivitas.</div>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="list-group-item px-0 py-2 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="badge bg-dark text-uppercase fs-8" style="font-size: 0.7rem;">
                                            <?= htmlspecialchars($log['aksi']) ?>
                                        </span>
                                        <span class="fw-semibold small ms-1 text-dark">
                                            <?= htmlspecialchars($log['nama'] ?? 'System') ?>
                                        </span>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        <?= date('d/m H:i', strtotime($log['created_at'])) ?>
                                    </small>
                                </div>
                                <div class="small text-secondary mt-1">
                                    <?= htmlspecialchars($log['detail'] ?? '-') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Chart Tren Peminjaman
    const ctxMonthly = document.getElementById('monthlyLoanChart').getContext('2d');
    new Chart(ctxMonthly, {
        type: 'line',
        data: {
            labels: <?= json_encode($monthlyLabels) ?>,
            datasets: [{
                label: 'Jumlah Peminjaman',
                data: <?= json_encode($monthlyData) ?>,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.12)',
                borderWidth: 3,
                tension: 0.35,
                fill: true,
                pointBackgroundColor: '#2563eb',
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });

    // 2. Chart Distribusi Kategori
    const ctxCategory = document.getElementById('categoryDistributionChart').getContext('2d');
    new Chart(ctxCategory, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($categoryLabels) ?>,
            datasets: [{
                data: <?= json_encode($categoryData) ?>,
                backgroundColor: [
                    '#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#64748b'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { size: 11 } }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
