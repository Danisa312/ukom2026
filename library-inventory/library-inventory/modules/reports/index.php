<?php
/**
 * Modul Laporan & Export (Khusus Owner)
 * Sistem Inventaris Perpustakaan
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('owner');

// Filter parameter
$jenisLaporan = trim($_GET['jenis'] ?? 'peminjaman'); // peminjaman, buku, denda
$tglDari      = trim($_GET['tgl_dari'] ?? date('Y-m-01')); // awal bulan
$tglSampai    = trim($_GET['tgl_sampai'] ?? date('Y-m-d')); // hari ini
$statusFilter = trim($_GET['status'] ?? '');

// 1. Ringkasan Statistik Periode
$stmtSummary = $db->prepare("
    SELECT
        COUNT(*) AS total_transaksi,
        SUM(CASE WHEN status IN ('dipinjam', 'terlambat') THEN 1 ELSE 0 END) AS total_dipinjam,
        SUM(CASE WHEN status = 'dikembalikan' THEN 1 ELSE 0 END) AS total_kembali,
        SUM(denda) AS total_denda
    FROM loans
    WHERE tgl_pinjam BETWEEN ? AND ?
");
$stmtSummary->execute([$tglDari, $tglSampai]);
$summary = $stmtSummary->fetch();

// 2. Data Laporan berdasarkan Jenis
$dataLaporan = [];

if ($jenisLaporan === 'buku') {
    // Laporan Inventaris & Sirkulasi Buku
    $stmt = $db->prepare("
        SELECT b.*, c.nama_kategori, s.nama_rak,
               COUNT(l.id) AS total_dipinjam
        FROM books b
        LEFT JOIN categories c ON b.kategori_id = c.id
        LEFT JOIN shelves s ON b.rak_id = s.id
        LEFT JOIN loans l ON b.id = l.book_id AND (l.tgl_pinjam BETWEEN ? AND ?)
        GROUP BY b.id
        ORDER BY total_dipinjam DESC, b.judul ASC
    ");
    $stmt->execute([$tglDari, $tglSampai]);
    $dataLaporan = $stmt->fetchAll();
} elseif ($jenisLaporan === 'denda') {
    // Laporan Penerimaan Denda Keterlambatan
    $stmt = $db->prepare("
        SELECT l.*, b.judul, b.kode_buku, m.nama AS nama_anggota, m.kode_anggota
        FROM loans l
        JOIN books b ON l.book_id = b.id
        JOIN members m ON l.member_id = m.id
        WHERE l.tgl_pinjam BETWEEN ? AND ?
          AND l.denda > 0
        ORDER BY l.tgl_kembali DESC, l.id DESC
    ");
    $stmt->execute([$tglDari, $tglSampai]);
    $dataLaporan = $stmt->fetchAll();
} else {
    // Default: Laporan Transaksi Peminjaman
    $sql = "
        SELECT l.*, b.judul, b.kode_buku, m.nama AS nama_anggota, m.kode_anggota, m.kontak,
               u.nama AS nama_petugas
        FROM loans l
        JOIN books b ON l.book_id = b.id
        JOIN members m ON l.member_id = m.id
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.tgl_pinjam BETWEEN ? AND ?
    ";
    $params = [$tglDari, $tglSampai];

    if (!empty($statusFilter)) {
        $sql .= " AND l.status = ?";
        $params[] = $statusFilter;
    }

    $sql .= " ORDER BY l.tgl_pinjam DESC, l.id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $dataLaporan = $stmt->fetchAll();
}

$pageTitle = 'Laporan & Audit Eksekutif';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
    <div>
        <h4 class="fw-bold mb-1">Laporan Perpustakaan &amp; Export</h4>
        <p class="text-muted small mb-0">Rekapitulasi sirkulasi peminjaman, statistik buku, dan audit denda operasional.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary shadow-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Cetak Dokumen Laporan
        </button>
    </div>
</div>

<!-- Header Dokumen Cetak (Hanya tampil saat print) -->
<div class="d-none d-print-block text-center mb-4 pb-3 border-bottom">
    <h3 class="fw-bold mb-1">SISTEM INVENTARIS PERPUSTAKAAN (SIPERPUS)</h3>
    <p class="mb-1">Laporan Resmi: <?= strtoupper(htmlspecialchars($jenisLaporan)) ?></p>
    <small class="text-muted">Periode: <?= format_tanggal($tglDari) ?> s/d <?= format_tanggal($tglSampai) ?> | Dicetak oleh: <?= htmlspecialchars($currentUser['nama']) ?> (<?= date('d/m/Y H:i') ?>)</small>
</div>

<!-- Form Filter Periode & Jenis Laporan -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Jenis Laporan</label>
                <select name="jenis" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="peminjaman" <?= $jenisLaporan === 'peminjaman' ? 'selected' : '' ?>>Sirkulasi Peminjaman</option>
                    <option value="buku" <?= $jenisLaporan === 'buku' ? 'selected' : '' ?>>Inventaris &amp; Minat Buku</option>
                    <option value="denda" <?= $jenisLaporan === 'denda' ? 'selected' : '' ?>>Penerimaan Denda</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Tanggal Mulai</label>
                <input type="date" name="tgl_dari" class="form-control form-control-sm" value="<?= htmlspecialchars($tglDari) ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Tanggal Sampai</label>
                <input type="date" name="tgl_sampai" class="form-control form-control-sm" value="<?= htmlspecialchars($tglSampai) ?>" required>
            </div>

            <?php if ($jenisLaporan === 'peminjaman'): ?>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Status Pinjaman</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- Semua Status --</option>
                        <option value="dipinjam" <?= $statusFilter === 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
                        <option value="dikembalikan" <?= $statusFilter === 'dikembalikan' ? 'selected' : '' ?>>Dikembalikan</option>
                        <option value="terlambat" <?= $statusFilter === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="col-md d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-3 flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Terapkan
                </button>
                <a href="<?= base_url('modules/reports/index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Kartu Ringkasan Statistik -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 bg-white shadow-sm">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Transaksi</span>
                    <h3 class="fw-bold my-1 text-dark"><?= number_format((int)($summary['total_transaksi'] ?? 0)) ?></h3>
                    <small class="text-muted">Periode terpilih</small>
                </div>
                <div class="stat-icon-wrapper bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-arrow-left-right"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 bg-white shadow-sm">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Buku Dipinjam</span>
                    <h3 class="fw-bold my-1 text-warning"><?= number_format((int)($summary['total_dipinjam'] ?? 0)) ?></h3>
                    <small class="text-muted">Belum kembali</small>
                </div>
                <div class="stat-icon-wrapper bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-book"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 bg-white shadow-sm">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Telah Kembali</span>
                    <h3 class="fw-bold my-1 text-success"><?= number_format((int)($summary['total_kembali'] ?? 0)) ?></h3>
                    <small class="text-muted">Selesai tertib</small>
                </div>
                <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check2-circle"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 bg-white shadow-sm">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Pemasukan Denda</span>
                    <h3 class="fw-bold my-1 text-danger"><?= format_rupiah($summary['total_denda'] ?? 0) ?></h3>
                    <small class="text-muted">Keterlambatan</small>
                </div>
                <div class="stat-icon-wrapper bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Laporan Konten -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold text-dark mb-0">
            <i class="bi bi-table me-1 text-primary"></i> 
            <?php 
                if ($jenisLaporan === 'buku') echo 'Data Koleksi & Frekuensi Sirkulasi Buku';
                elseif ($jenisLaporan === 'denda') echo 'Rekapitulasi Penerimaan Denda';
                else echo 'Daftar Transaksi Peminjaman Periode ' . format_tanggal($tglDari) . ' s/d ' . format_tanggal($tglSampai);
            ?>
        </h6>
        <span class="badge bg-light text-secondary border"><?= count($dataLaporan) ?> Baris Data</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <?php if (empty($dataLaporan)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 text-secondary d-block mb-2"></i>
                    Tidak ada data pada periode tanggal yang dipilih.
                </div>
            <?php elseif ($jenisLaporan === 'buku'): ?>
                <!-- Tabel Laporan Buku -->
                <table class="table table-custom table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Buku</th>
                            <th>Judul &amp; Penulis</th>
                            <th>Kategori</th>
                            <th>Lokasi Rak</th>
                            <th class="text-center">Sisa Stok</th>
                            <th class="text-center">Dipinjam (Periode Ini)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dataLaporan as $i => $row): ?>
                            <tr>
                                <td class="text-muted small ps-3"><?= $i + 1 ?></td>
                                <td><span class="badge bg-light text-secondary font-monospace"><?= htmlspecialchars($row['kode_buku']) ?></span></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($row['judul']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($row['penulis']) ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['nama_kategori'] ?? '-') ?></span></td>
                                <td><small class="text-secondary"><?= htmlspecialchars($row['nama_rak'] ?? '-') ?></small></td>
                                <td class="text-center fw-bold <?= $row['stok'] <= 0 ? 'text-danger' : 'text-success' ?>"><?= $row['stok'] ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1"><?= $row['total_dipinjam'] ?> kali</span>
                                </td>
                                <td>
                                    <span class="badge badge-soft-<?= $row['status'] === 'tersedia' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($jenisLaporan === 'denda'): ?>
                <!-- Tabel Laporan Denda -->
                <table class="table table-custom table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Transaksi</th>
                            <th>Nama Anggota</th>
                            <th>Buku</th>
                            <th>Tgl Pinjam</th>
                            <th>Jatuh Tempo</th>
                            <th>Tgl Kembali</th>
                            <th class="text-end">Jumlah Denda</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dataLaporan as $i => $row): ?>
                            <tr>
                                <td class="text-muted small ps-3"><?= $i + 1 ?></td>
                                <td><span class="fw-bold font-monospace"><?= htmlspecialchars($row['kode_transaksi']) ?></span></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($row['nama_anggota']) ?></div>
                                    <small class="text-muted font-monospace"><?= htmlspecialchars($row['kode_anggota']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-medium"><?= htmlspecialchars($row['judul']) ?></div>
                                    <small class="text-secondary"><?= htmlspecialchars($row['kode_buku']) ?></small>
                                </td>
                                <td><?= format_tanggal($row['tgl_pinjam']) ?></td>
                                <td><?= format_tanggal($row['tgl_jatuh_tempo']) ?></td>
                                <td><span class="text-success fw-medium"><?= format_tanggal($row['tgl_kembali']) ?></span></td>
                                <td class="text-end pe-3 fw-bold text-danger"><?= format_rupiah($row['denda']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <!-- Tabel Laporan Peminjaman Default -->
                <table class="table table-custom table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode TRX</th>
                            <th>Peminjam</th>
                            <th>Buku</th>
                            <th>Tgl Pinjam</th>
                            <th>Jatuh Tempo</th>
                            <th>Tgl Kembali</th>
                            <th>Status</th>
                            <th class="text-end">Denda</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dataLaporan as $i => $row): 
                            $badgeClass = [
                                'dipinjam'     => 'bg-primary',
                                'dikembalikan' => 'bg-success',
                                'terlambat'    => 'bg-danger',
                                'hilang'       => 'bg-dark',
                                'rusak'        => 'bg-warning text-dark'
                            ][$row['status']] ?? 'bg-secondary';
                        ?>
                            <tr>
                                <td class="text-muted small ps-3"><?= $i + 1 ?></td>
                                <td><span class="fw-bold font-monospace"><?= htmlspecialchars($row['kode_transaksi']) ?></span></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($row['nama_anggota']) ?></div>
                                    <small class="text-muted font-monospace"><?= htmlspecialchars($row['kode_anggota']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark"><?= htmlspecialchars($row['judul']) ?></div>
                                    <small class="text-secondary"><?= htmlspecialchars($row['kode_buku']) ?></small>
                                </td>
                                <td><?= format_tanggal($row['tgl_pinjam']) ?></td>
                                <td><?= format_tanggal($row['tgl_jatuh_tempo']) ?></td>
                                <td>
                                    <?php if (!empty($row['tgl_kembali'])): ?>
                                        <span class="text-success"><?= format_tanggal($row['tgl_kembali']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $badgeClass ?>"><?= ucfirst($row['status']) ?></span></td>
                                <td class="text-end pe-3">
                                    <?= (float)$row['denda'] > 0 ? '<span class="text-danger fw-semibold">' . format_rupiah($row['denda']) . '</span>' : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
