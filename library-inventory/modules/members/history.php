<?php
/**
 * Modul Anggota - Riwayat Peminjaman Anggota
 * Sistem Inventaris Perpustakaan
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'ID anggota tidak valid.');
    redirect('modules/members/index.php');
}

// Ambil data anggota
$stmtMember = $db->prepare("SELECT * FROM members WHERE id = ?");
$stmtMember->execute([$id]);
$member = $stmtMember->fetch();

if (!$member) {
    set_flash('danger', 'Data anggota perpustakaan tidak ditemukan.');
    redirect('modules/members/index.php');
}

// Ambil seluruh riwayat transaksi peminjaman anggota
$stmtLoans = $db->prepare("
    SELECT l.*, b.judul, b.kode_buku, b.penulis, b.penerbit, u.nama AS nama_petugas
    FROM loans l
    JOIN books b ON l.book_id = b.id
    LEFT JOIN users u ON l.user_id = u.id
    WHERE l.member_id = ?
    ORDER BY l.tgl_pinjam DESC, l.id DESC
");
$stmtLoans->execute([$id]);
$loans = $stmtLoans->fetchAll();

// Hitung statistik peminjaman anggota
$totalTransaksi = count($loans);
$pinjamanAktif = 0;
$totalDikembalikan = 0;
$totalDenda = 0;

$today = new DateTime(date('Y-m-d'));

foreach ($loans as $l) {
    if (in_array($l['status'], ['dipinjam', 'terlambat'])) {
        $pinjamanAktif++;
    } elseif ($l['status'] === 'dikembalikan') {
        $totalDikembalikan++;
    }
    $totalDenda += (float)$l['denda'];
}

$pageTitle = 'Riwayat Peminjaman - ' . $member['nama'];
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Tombol Kembali & Header Halaman -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <a href="<?= base_url('modules/members/index.php') ?>" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Kembali ke Data Anggota
        </a>
        <h4 class="fw-bold mt-1 mb-0">Riwayat Peminjaman Buku</h4>
        <p class="text-muted small mb-0">Pantau seluruh catatan transaksi peminjaman dan pengembalian anggota.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/members/edit.php?id=' . $member['id']) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil-square me-1"></i> Edit Anggota
        </a>
        <?php if ($member['status'] === 'aktif'): ?>
            <a href="<?= base_url('modules/loans/create.php?member_id=' . $member['id']) ?>" class="btn btn-primary btn-sm shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Peminjaman Baru
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Kartu Profil Informasi Anggota -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="row g-3 align-items-center">
            <div class="col-auto">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold fs-3"
                     style="width: 64px; height: 64px;">
                    <?= strtoupper(substr($member['nama'], 0, 1)) ?>
                </div>
            </div>
            <div class="col">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($member['nama']) ?></h5>
                    <span class="badge bg-light text-primary border font-monospace"><?= htmlspecialchars($member['kode_anggota']) ?></span>
                    <?php if ($member['status'] === 'aktif'): ?>
                        <span class="badge badge-soft-success px-2 py-1">Aktif</span>
                    <?php else: ?>
                        <span class="badge badge-soft-danger px-2 py-1">Nonaktif</span>
                    <?php endif; ?>
                </div>
                <div class="row g-2 mt-2 text-muted small">
                    <div class="col-sm-6 col-md-4">
                        <i class="bi bi-card-text me-1 text-secondary"></i>
                        <strong>No. Identitas:</strong> <?= htmlspecialchars($member['no_identitas']) ?>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <i class="bi bi-telephone me-1 text-secondary"></i>
                        <strong>Kontak:</strong> <?= htmlspecialchars($member['kontak']) ?>
                    </div>
                    <div class="col-12 col-md-4">
                        <i class="bi bi-geo-alt me-1 text-secondary"></i>
                        <strong>Alamat:</strong> <?= htmlspecialchars($member['alamat']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Kartu Metrik Ringkasan Transaksi Anggota -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card card-hover border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Transaksi</span>
                    <h3 class="fw-bold my-1 text-dark"><?= number_format($totalTransaksi) ?></h3>
                    <small class="text-muted">Kali transaksi</small>
                </div>
                <div class="stat-icon-wrapper bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-journal-text"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card card-hover border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Buku Dipinjam</span>
                    <h3 class="fw-bold my-1 text-warning"><?= number_format($pinjamanAktif) ?></h3>
                    <small class="text-muted">Sedang aktif</small>
                </div>
                <div class="stat-icon-wrapper bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-book-half"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card card-hover border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Dikembalikan</span>
                    <h3 class="fw-bold my-1 text-success"><?= number_format($totalDikembalikan) ?></h3>
                    <small class="text-muted">Selesai tertib</small>
                </div>
                <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check2-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card card-hover border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Denda</span>
                    <h3 class="fw-bold my-1 text-danger"><?= format_rupiah($totalDenda) ?></h3>
                    <small class="text-muted">Denda tercatat</small>
                </div>
                <div class="stat-icon-wrapper bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-cash-coin"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Riwayat Peminjaman -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold text-dark mb-0">
            <i class="bi bi-clock-history me-1 text-primary"></i> Daftar Riwayat Peminjaman
        </h6>
        <span class="badge bg-light text-secondary border"><?= $totalTransaksi ?> Catatan</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode Transaksi</th>
                        <th>Buku Dipinjam</th>
                        <th>Tgl Pinjam</th>
                        <th>Jatuh Tempo</th>
                        <th>Tgl Kembali</th>
                        <th>Status</th>
                        <th>Denda</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($loans)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x fs-2 text-secondary d-block mb-2"></i>
                                Anggota ini belum memiliki riwayat peminjaman buku.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($loans as $index => $l): 
                            $isOverdue     = is_overdue($l['tgl_jatuh_tempo'], $l['status']);
                            $dendaBerjalan = hitung_denda_berjalan($l['tgl_jatuh_tempo'], $l['status']);
                        ?>
                            <tr>
                                <td class="text-muted small ps-3"><?= $index + 1 ?></td>
                                <td>
                                    <span class="fw-bold font-monospace text-dark"><?= htmlspecialchars($l['kode_transaksi']) ?></span>
                                    <?php if (!empty($l['nama_petugas'])): ?>
                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            Petugas: <?= htmlspecialchars($l['nama_petugas']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($l['judul']) ?></div>
                                    <small class="text-secondary"><?= htmlspecialchars($l['kode_buku']) ?> &bull; <?= htmlspecialchars($l['penulis']) ?></small>
                                </td>
                                <td><?= format_tanggal($l['tgl_pinjam']) ?></td>
                                <td>
                                    <span class="badge <?= $isOverdue ? 'bg-danger' : 'bg-light text-dark border' ?>">
                                        <?= format_tanggal($l['tgl_jatuh_tempo']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($l['tgl_kembali'])): ?>
                                        <span class="text-success fw-medium"><?= format_tanggal($l['tgl_kembali']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= status_badge_pinjam($l['tgl_jatuh_tempo'], $l['status']) ?>
                                </td>
                                <td>
                                    <?php if ($l['status'] === 'dikembalikan'): ?>
                                        <?= (float)$l['denda'] > 0 ? '<span class="text-danger fw-semibold">' . format_rupiah($l['denda']) . '</span>' : '-' ?>
                                    <?php elseif ($dendaBerjalan > 0): ?>
                                        <span class="text-danger fw-semibold" title="Denda keterlambatan berjalan"><?= format_rupiah($dendaBerjalan) ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/loans/detail.php?id=' . $l['id']) ?>" class="btn btn-outline-secondary" title="Lihat Detail Transaksi">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (in_array($l['status'], ['dipinjam', 'terlambat'])): ?>
                                            <a href="<?= base_url('modules/loans/return.php?id=' . $l['id']) ?>" class="btn btn-success" title="Proses Pengembalian">
                                                <i class="bi bi-box-arrow-in-left"></i> Kembali
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
