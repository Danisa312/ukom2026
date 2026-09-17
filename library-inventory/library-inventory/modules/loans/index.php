<?php
/**
 * Modul Loans - Daftar Transaksi Peminjaman & Pengembalian
 */
require_once __DIR__ . '/../../config/database.php';
$pageTitle = 'Peminjaman & Denda';
require_once __DIR__ . '/../../includes/header.php';

// Filter status (opsional, via ?status=)
$filterStatus = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT l.*, m.nama AS nama_anggota, m.kontak, b.judul, b.kode_buku
        FROM loans l
        JOIN members m ON m.id = l.member_id
        JOIN books b ON b.id = l.book_id
        WHERE 1=1";
$params = [];

if ($filterStatus === 'terlambat') {
    // Transaksi berstatus 'terlambat' atau yang jatuh temponya sudah lewat hari ini
    $sql .= " AND (l.status = 'terlambat' OR (l.status = 'dipinjam' AND l.tgl_jatuh_tempo < CURDATE()))";
} elseif ($filterStatus === 'dipinjam') {
    // Transaksi aktif yang masih dalam masa pinjam (belum jatuh tempo)
    $sql .= " AND l.status = 'dipinjam' AND l.tgl_jatuh_tempo >= CURDATE()";
} elseif ($filterStatus !== '') {
    $sql .= " AND l.status = ?";
    $params[] = $filterStatus;
}
if ($search !== '') {
    $sql .= " AND (l.kode_transaksi LIKE ? OR m.nama LIKE ? OR b.judul LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$sql .= " ORDER BY l.tgl_pinjam DESC, l.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll();

if (!function_exists('status_badge')) {
    function status_badge($status) {
        return status_badge_pinjam('', $status);
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">Peminjaman &amp; Denda</h4>
        <p class="text-muted mb-0">Kelola transaksi peminjaman dan pengembalian buku.</p>
    </div>
    <a href="<?= base_url('modules/loans/create.php') ?>" class="btn btn-primary shadow-sm">
        <i class="bi bi-plus-circle me-1"></i> Pinjam Buku Baru
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control" placeholder="Cari kode transaksi, peminjam, atau judul buku..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <?php foreach (['dipinjam', 'dikembalikan', 'terlambat', 'hilang', 'rusak'] as $st): ?>
                        <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button>
            </div>
            <?php if ($filterStatus !== '' || $search !== ''): ?>
            <div class="col-md-2">
                <a href="<?= base_url('modules/loans/index.php') ?>" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Kode Transaksi</th>
                    <th>Peminjam</th>
                    <th>Buku</th>
                    <th>Tgl Pinjam</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                    <th>Denda</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($loans)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada transaksi peminjaman.</td></tr>
                <?php endif; ?>
                <?php foreach ($loans as $l): ?>
                    <?php $dendaBerjalan = $l['status'] === 'dikembalikan' ? $l['denda'] : hitung_denda_berjalan($l['tgl_jatuh_tempo'], $l['status']); ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($l['kode_transaksi']) ?></td>
                        <td>
                            <?= htmlspecialchars($l['nama_anggota']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($l['kontak']) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($l['judul']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($l['kode_buku']) ?></small>
                        </td>
                        <td><?= format_tanggal($l['tgl_pinjam']) ?></td>
                        <td>
                            <span class="badge <?= ($dendaBerjalan > 0) ? 'bg-danger' : 'bg-light text-dark border' ?>">
                                <?= format_tanggal($l['tgl_jatuh_tempo']) ?>
                            </span>
                        </td>
                        <td><?= status_badge_pinjam($l['tgl_jatuh_tempo'], $l['status']) ?></td>
                        <td><?= $dendaBerjalan > 0 ? format_rupiah($dendaBerjalan) : '-' ?></td>
                        <td class="text-end">
                            <a href="<?= base_url('modules/loans/detail.php?id=' . $l['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Detail">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (in_array($l['status'], ['dipinjam', 'terlambat'])): ?>
                                <a href="<?= base_url('modules/loans/return.php?id=' . $l['id']) ?>" class="btn btn-sm btn-success" title="Proses Kembali">
                                    <i class="bi bi-box-arrow-in-left"></i> Proses Kembali
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
