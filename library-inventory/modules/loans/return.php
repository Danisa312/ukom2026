<?php
/**
 * Modul Loans - Proses Pengembalian Buku + Perhitungan Denda Otomatis
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];

$stmt = $db->prepare("SELECT l.*, m.nama AS nama_anggota, b.judul, b.id AS book_id
                       FROM loans l
                       JOIN members m ON m.id = l.member_id
                       JOIN books b ON b.id = l.book_id
                       WHERE l.id = ?");
$stmt->execute([$id]);
$loan = $stmt->fetch();

if (!$loan) {
    set_flash('danger', 'Transaksi peminjaman tidak ditemukan.');
    redirect('modules/loans/index.php');
}
if (!in_array($loan['status'], ['dipinjam', 'terlambat'])) {
    set_flash('warning', 'Transaksi ini sudah diproses sebelumnya.');
    redirect('modules/loans/detail.php?id=' . $id);
}

// Hitung denda otomatis berdasarkan tanggal kembali (hari ini, kecuali diubah manual)
function hitung_denda($tgl_jatuh_tempo, $tgl_kembali) {
    $due = new DateTime($tgl_jatuh_tempo);
    $kembali = new DateTime($tgl_kembali);
    if ($kembali <= $due) return 0;
    return $due->diff($kembali)->days * DENDA_PER_HARI;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Poin 4: early stop jika CSRF gagal
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi form tidak valid. Silakan muat ulang halaman dan coba lagi.');
        redirect('modules/loans/return.php?id=' . $id);
    }

    $tgl_kembali = $_POST['tgl_kembali'] ?? date('Y-m-d');
    $kondisi = $_POST['kondisi'] ?? 'baik'; // baik, rusak, hilang
    $catatan = sanitize($_POST['catatan'] ?? '');
    $dendaFinal = hitung_denda($loan['tgl_jatuh_tempo'], $tgl_kembali);

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $statusBaru = 'dikembalikan';
            if ($kondisi === 'rusak') $statusBaru = 'rusak';
            if ($kondisi === 'hilang') $statusBaru = 'hilang';

            $stmt = $db->prepare("UPDATE loans SET tgl_kembali = ?, status = ?, denda = ?, catatan = CONCAT(IFNULL(catatan, ''), ?) WHERE id = ?");
            $catatanTambahan = $catatan !== '' ? " | Pengembalian: $catatan" : '';
            $stmt->execute([$tgl_kembali, $statusBaru, $dendaFinal, $catatanTambahan, $id]);

            // Kembalikan stok buku jika kondisi baik/rusak (tetap fisik ada), tidak jika hilang
            if ($kondisi !== 'hilang') {
                $stmt = $db->prepare("UPDATE books SET stok = stok + 1, status = 'tersedia' WHERE id = ?");
                $stmt->execute([$loan['book_id']]);
            } else {
                $stmt = $db->prepare("UPDATE books SET status = 'hilang' WHERE id = ?");
                $stmt->execute([$loan['book_id']]);
            }

            $detailLog = "Pengembalian buku \"{$loan['judul']}\" oleh {$loan['nama_anggota']} ({$loan['kode_transaksi']})";
            if ($dendaFinal > 0) $detailLog .= " dengan denda " . format_rupiah($dendaFinal);
            log_activity($db, 'TRANSAKSI_KEMBALI', $detailLog);

            $db->commit();
            set_flash('success', 'Pengembalian buku berhasil diproses.' . ($dendaFinal > 0 ? ' Denda: ' . format_rupiah($dendaFinal) : ''));
            redirect('modules/loans/index.php');
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            // Poin 3: log detail error, tampilkan pesan aman ke user
            error_log('Loan return Exception: ' . $e->getMessage());
            $errors[] = 'Gagal memproses pengembalian. Silakan coba lagi beberapa saat.';
        }
    }
}

$dendaPreview = hitung_denda($loan['tgl_jatuh_tempo'], date('Y-m-d'));

$pageTitle = 'Proses Pengembalian';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="mb-4">
    <a href="<?= base_url('modules/loans/detail.php?id=' . $id) ?>" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left"></i> Kembali ke Detail Transaksi
    </a>
    <h4 class="fw-bold mt-2 mb-1">Proses Pengembalian Buku</h4>
    <p class="text-muted mb-0">Kode Transaksi: <strong><?= htmlspecialchars($loan['kode_transaksi']) ?></strong></p>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold text-primary mb-3">Ringkasan Peminjaman</h6>
                <table class="table table-borderless table-sm mb-0">
                    <tr><td class="text-muted" width="45%">Peminjam</td><td class="fw-semibold"><?= htmlspecialchars($loan['nama_anggota']) ?></td></tr>
                    <tr><td class="text-muted">Buku</td><td class="fw-semibold"><?= htmlspecialchars($loan['judul']) ?></td></tr>
                    <tr><td class="text-muted">Tgl Pinjam</td><td><?= format_tanggal($loan['tgl_pinjam']) ?></td></tr>
                    <tr><td class="text-muted">Jatuh Tempo</td><td><?= format_tanggal($loan['tgl_jatuh_tempo']) ?></td></tr>
                    <tr>
                        <td class="text-muted">Estimasi Denda Hari Ini</td>
                        <td class="fw-bold <?= $dendaPreview > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= $dendaPreview > 0 ? format_rupiah($dendaPreview) : 'Tidak ada (tepat waktu)' ?>
                        </td>
                    </tr>
                </table>
                <small class="text-muted d-block mt-2">
                    Denda dihitung otomatis: <?= format_rupiah(DENDA_PER_HARI) ?>/hari keterlambatan, dan akan menyesuaikan jika kamu mengubah tanggal kembali di form.
                </small>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= $id ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tanggal Kembali</label>
                        <input type="date" name="tgl_kembali" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kondisi Buku</label>
                        <select name="kondisi" class="form-select" required>
                            <option value="baik">Baik / Normal</option>
                            <option value="rusak">Rusak</option>
                            <option value="hilang">Hilang</option>
                        </select>
                        <small class="text-muted">Jika "Hilang", stok buku tidak akan dikembalikan.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan (opsional)</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan tambahan mengenai kondisi buku..."></textarea>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-success px-4">
                            <i class="bi bi-check-circle me-1"></i> Konfirmasi Pengembalian
                        </button>
                        <a href="<?= base_url('modules/loans/detail.php?id=' . $id) ?>" class="btn btn-outline-secondary px-4">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
