<?php
/**
 * Dashboard Admin
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'owner']);

$pageTitle = 'Dashboard Admin';
require_once __DIR__ . '/../includes/header.php';

$db = get_db();

// Ambil Statistik
$totalBuku = $db->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalAnggota = $db->query("SELECT COUNT(*) FROM members WHERE status = 'aktif'")->fetchColumn();
$pinjamanAktif = (int)$db->query("SELECT COUNT(*) FROM loans WHERE status IN ('dipinjam', 'terlambat')")->fetchColumn();
$jatuhTempoHariIni = (int)$db->query("SELECT COUNT(*) FROM loans WHERE status IN ('dipinjam', 'terlambat') AND tgl_jatuh_tempo <= CURDATE()")->fetchColumn();

// Ambil Buku yang Perlu Perhatian (Jatuh tempo atau terlambat)
$stmtPerhatian = $db->query("
    SELECT l.*, b.judul, b.kode_buku, m.nama AS nama_anggota, m.kontak
    FROM loans l
    JOIN books b ON l.book_id = b.id
    JOIN members m ON l.member_id = m.id
    WHERE l.status IN ('dipinjam', 'terlambat') AND l.tgl_jatuh_tempo <= CURDATE()
    ORDER BY l.tgl_jatuh_tempo ASC
    LIMIT 5
");
$daftarPerhatian = $stmtPerhatian->fetchAll();
?>

<!-- Header Selamat Datang -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h3 class="fw-bold mb-1">Halo, <?= htmlspecialchars($currentUser['nama']) ?>! 👋</h3>
                <p class="text-muted small mb-0">Selamat datang di panel operasional inventaris perpustakaan.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= base_url('modules/loans/create.php') ?>" class="btn btn-primary shadow-sm">
                    <i class="bi bi-plus-circle me-1"></i> Transaksi Pinjam
                </a>
                <a href="<?= base_url('modules/books/create.php') ?>" class="btn btn-outline-primary shadow-sm">
                    <i class="bi bi-book me-1"></i> Tambah Buku
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Kartu Metrik Ringkasan -->
<div class="row g-3 mb-4">
    <!-- Total Buku -->
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card card-hover border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Judul Buku</span>
                    <h3 class="fw-bold my-1 text-dark"><?= number_format($totalBuku) ?></h3>
                    <a href="<?= base_url('modules/books/index.php') ?>" class="text-decoration-none small text-primary fw-medium">
                        Lihat katalog <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="stat-icon-wrapper bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-journal-bookmark-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Anggota Aktif -->
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card card-hover border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Anggota Aktif</span>
                    <h3 class="fw-bold my-1 text-dark"><?= number_format($totalAnggota) ?></h3>
                    <a href="<?= base_url('modules/members/index.php') ?>" class="text-decoration-none small text-success fw-medium">
                        Kelola anggota <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Peminjaman Aktif -->
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card card-hover border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Buku Sedang Dipinjam</span>
                    <h3 class="fw-bold my-1 text-dark"><?= number_format($pinjamanAktif) ?></h3>
                    <a href="<?= base_url('modules/loans/index.php') ?>" class="text-decoration-none small text-warning fw-medium">
                        Daftar sirkulasi <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="stat-icon-wrapper bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Jatuh Tempo Hari Ini / Terlambat -->
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card card-hover border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Jatuh Tempo / Terlambat</span>
                    <h3 class="fw-bold my-1 text-danger"><?= number_format($jatuhTempoHariIni) ?></h3>
                    <span class="small text-danger fw-medium">Perlu ditindaklanjuti</span>
                </div>
                <div class="stat-icon-wrapper bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Daftar Pengembalian Mendesak -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-bell-fill text-danger"></i>
                    <h5 class="card-title fw-bold mb-0">Peringatan Jatuh Tempo &amp; Keterlambatan</h5>
                </div>
                <a href="<?= base_url('modules/loans/index.php?status=terlambat') ?>" class="btn btn-sm btn-outline-danger">
                    Lihat Semua Pinjaman Terlambat
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Kode Transaksi</th>
                                <th>Peminjam</th>
                                <th>Buku</th>
                                <th>Tgl Pinjam</th>
                                <th>Jatuh Tempo</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftarPerhatian)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="bi bi-check2-circle fs-3 text-success d-block mb-1"></i>
                                        Tidak ada buku yang jatuh tempo hari ini atau terlambat. Semua transaksi tertib!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daftarPerhatian as $item): 
                                    $isOverdue = is_overdue($item['tgl_jatuh_tempo'], $item['status']);
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($item['kode_transaksi']) ?></strong></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($item['nama_anggota']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($item['kontak']) ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-medium text-dark"><?= htmlspecialchars($item['judul']) ?></div>
                                            <small class="text-secondary"><?= htmlspecialchars($item['kode_buku']) ?></small>
                                        </td>
                                        <td><?= format_tanggal($item['tgl_pinjam']) ?></td>
                                        <td>
                                            <span class="badge <?= $isOverdue ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                                <?= format_tanggal($item['tgl_jatuh_tempo']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-soft-danger px-2 py-1">
                                                <?= $isOverdue ? 'Terlambat' : 'Jatuh Tempo Hari Ini' ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= base_url('modules/loans/return.php?id=' . $item['id']) ?>" class="btn btn-sm btn-success">
                                                <i class="bi bi-box-arrow-in-down me-1"></i> Proses Kembali
                                            </a>
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
