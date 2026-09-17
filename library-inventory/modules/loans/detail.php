<?php
/**
 * Modul Loans - Detail Transaksi Peminjaman
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT l.*, m.nama AS nama_anggota, m.kode_anggota, m.kontak, m.alamat,
                              b.judul, b.kode_buku, b.penulis, b.penerbit, b.isbn,
                              u.nama AS nama_petugas
                       FROM loans l
                       JOIN members m ON m.id = l.member_id
                       JOIN books b ON b.id = l.book_id
                       LEFT JOIN users u ON u.id = l.user_id
                       WHERE l.id = ?");
$stmt->execute([$id]);
$loan = $stmt->fetch();

if (!$loan) {
    set_flash('danger', 'Transaksi peminjaman tidak ditemukan.');
    redirect('modules/loans/index.php');
}

$dendaBerjalan = hitung_denda_berjalan($loan['tgl_jatuh_tempo'], $loan['status']);
$displayStatus = get_display_status_pinjam($loan['tgl_jatuh_tempo'], $loan['status']);

$statusMap = [
    'dipinjam'     => ['label' => 'Dipinjam', 'class' => 'bg-primary'],
    'dikembalikan' => ['label' => 'Dikembalikan', 'class' => 'bg-success'],
    'terlambat'    => ['label' => 'Terlambat', 'class' => 'bg-danger'],
    'hilang'       => ['label' => 'Hilang', 'class' => 'bg-dark'],
    'rusak'        => ['label' => 'Rusak', 'class' => 'bg-warning text-dark'],
];
$st = $statusMap[$displayStatus] ?? ['label' => ucfirst($displayStatus), 'class' => 'bg-secondary'];

$pageTitle = 'Detail Peminjaman';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="mb-4">
    <a href="<?= base_url('modules/loans/index.php') ?>" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left"></i> Kembali ke Daftar Peminjaman
    </a>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
        <div>
            <h4 class="fw-bold mb-1"><?= htmlspecialchars($loan['kode_transaksi']) ?></h4>
            <span class="badge <?= $st['class'] ?>"><?= $st['label'] ?></span>
        </div>
        <?php if (in_array($loan['status'], ['dipinjam', 'terlambat'])): ?>
            <a href="<?= base_url('modules/loans/return.php?id=' . $loan['id']) ?>" class="btn btn-success">
                <i class="bi bi-box-arrow-in-left me-1"></i> Proses Kembali
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-person me-1"></i> Data Peminjam</h6>
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted" width="40%">Nama</td><td class="fw-semibold"><?= htmlspecialchars($loan['nama_anggota']) ?></td></tr>
                    <tr><td class="text-muted">Kode Anggota</td><td><?= htmlspecialchars($loan['kode_anggota']) ?></td></tr>
                    <tr><td class="text-muted">Kontak</td><td><?= htmlspecialchars($loan['kontak']) ?></td></tr>
                    <tr><td class="text-muted">Alamat</td><td><?= htmlspecialchars($loan['alamat']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-book me-1"></i> Data Buku</h6>
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted" width="40%">Judul</td><td class="fw-semibold"><?= htmlspecialchars($loan['judul']) ?></td></tr>
                    <tr><td class="text-muted">Kode Buku</td><td><?= htmlspecialchars($loan['kode_buku']) ?></td></tr>
                    <tr><td class="text-muted">Penulis</td><td><?= htmlspecialchars($loan['penulis']) ?></td></tr>
                    <tr><td class="text-muted">Penerbit</td><td><?= htmlspecialchars($loan['penerbit']) ?></td></tr>
                    <tr><td class="text-muted">ISBN</td><td><?= htmlspecialchars($loan['isbn']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-info-circle me-1"></i> Detail Transaksi</h6>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Tanggal Pinjam</div>
                        <div class="fw-semibold"><?= format_tanggal($loan['tgl_pinjam']) ?></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Jatuh Tempo</div>
                        <div class="fw-semibold"><?= format_tanggal($loan['tgl_jatuh_tempo']) ?></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Tanggal Kembali</div>
                        <div class="fw-semibold"><?= $loan['tgl_kembali'] ? format_tanggal($loan['tgl_kembali']) : '-' ?></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Denda</div>
                        <div class="fw-semibold text-danger">
                            <?= format_rupiah($loan['status'] === 'dikembalikan' ? $loan['denda'] : $dendaBerjalan) ?>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Diproses oleh</div>
                        <div class="fw-semibold"><?= htmlspecialchars($loan['nama_petugas'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-9 mb-3">
                        <div class="text-muted small">Catatan</div>
                        <div><?= $loan['catatan'] ? htmlspecialchars($loan['catatan']) : '-' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
