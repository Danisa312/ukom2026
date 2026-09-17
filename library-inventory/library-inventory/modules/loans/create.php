<?php
/**
 * Modul Loans - Form Peminjaman Buku Baru
 * Perbaikan: race condition kode transaksi, overselling stok,
 *            validasi bisnis tambahan, error message aman.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

define('MAX_LAMA_PINJAM', 30); // Poin 5: batas maksimum hari peminjaman

$errors = [];

/**
 * Generate kode transaksi dengan retry loop untuk menghindari race condition.
 * Poin 1: jika INSERT gagal karena duplikat UNIQUE, coba ulang max 3x.
 * Kode berbasis timestamp + suffix random agar tidak bergantung COUNT.
 */
function generate_kode_transaksi_aman(): string {
    $prefix = 'TRX-' . date('Ymd') . '-';
    // 4 karakter acak hex → peluang tabrakan sangat kecil
    return $prefix . strtoupper(bin2hex(random_bytes(2)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Poin 4: jika CSRF gagal, langsung stop tanpa query lanjutan
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi form tidak valid, silakan muat ulang halaman dan coba lagi.';
    } else {
        $member_id  = (int)($_POST['member_id']  ?? 0);
        $book_id    = (int)($_POST['book_id']    ?? 0);
        $tgl_pinjam = trim($_POST['tgl_pinjam']  ?? date('Y-m-d'));
        $lama_pinjam = (int)($_POST['lama_pinjam'] ?? DEFAULT_PINJAM_HARI);
        $catatan    = sanitize($_POST['catatan']  ?? '');

        // --- Validasi dasar ---
        if ($member_id <= 0)  $errors[] = 'Anggota wajib dipilih.';
        if ($book_id   <= 0)  $errors[] = 'Buku wajib dipilih.';
        if ($lama_pinjam <= 0) {
            $lama_pinjam = DEFAULT_PINJAM_HARI;
        }
        // Poin 5: batasi lama_pinjam maksimum
        if ($lama_pinjam > MAX_LAMA_PINJAM) {
            $errors[] = "Lama peminjaman maksimal adalah " . MAX_LAMA_PINJAM . " hari.";
        }
        if (!empty($tgl_pinjam) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_pinjam)) {
            $errors[] = 'Format tanggal pinjam tidak valid.';
        }

        if (empty($errors)) {
            // Poin 5: cek duplikat pinjam aktif untuk pasangan member+buku yang sama
            $stmtDuplikat = $db->prepare("
                SELECT COUNT(*) FROM loans
                WHERE member_id = ? AND book_id = ? AND status IN ('dipinjam', 'terlambat')
            ");
            $stmtDuplikat->execute([$member_id, $book_id]);
            if ($stmtDuplikat->fetchColumn() > 0) {
                $errors[] = 'Anggota ini masih memiliki peminjaman aktif untuk buku yang sama. Selesaikan dahulu sebelum meminjam lagi.';
            }
        }

        if (empty($errors)) {
            $tgl_jatuh_tempo = date('Y-m-d', strtotime($tgl_pinjam . " +{$lama_pinjam} days"));

            // Poin 1 & 2: retry loop untuk mengatasi race condition kode duplikat
            $maxRetry = 3;
            $berhasil  = false;

            for ($percobaan = 1; $percobaan <= $maxRetry; $percobaan++) {
                try {
                    $db->beginTransaction();

                    // Poin 2: cek & kurangi stok dalam satu UPDATE atomik di dalam transaksi
                    $stmtStok = $db->prepare(
                        "UPDATE books
                         SET stok = stok - 1,
                             status = IF(stok - 1 <= 0, 'dipinjam', 'tersedia')
                         WHERE id = ? AND stok > 0"
                    );
                    $stmtStok->execute([$book_id]);

                    if ($stmtStok->rowCount() === 0) {
                        // Poin 2: stok habis oleh transaksi lain saat ini
                        $db->rollBack();
                        $errors[] = 'Stok buku baru saja habis oleh transaksi lain. Silakan pilih buku lain.';
                        break;
                    }

                    // Poin 2: ambil judul buku untuk log (setelah lock stok)
                    $stmtBuku = $db->prepare("SELECT judul FROM books WHERE id = ?");
                    $stmtBuku->execute([$book_id]);
                    $judulBuku = $stmtBuku->fetchColumn() ?: 'Tidak diketahui';

                    // Cek status anggota di dalam transaksi
                    $stmtAnggota = $db->prepare("SELECT nama, status FROM members WHERE id = ?");
                    $stmtAnggota->execute([$member_id]);
                    $anggota = $stmtAnggota->fetch();

                    if (!$anggota || $anggota['status'] !== 'aktif') {
                        $db->rollBack();
                        $errors[] = 'Anggota berstatus nonaktif dan tidak dapat meminjam buku.';
                        break;
                    }

                    // Poin 1: generate kode dengan suffix random (bukan COUNT)
                    $kode = generate_kode_transaksi_aman();

                    $stmtInsert = $db->prepare("
                        INSERT INTO loans
                            (kode_transaksi, member_id, book_id, user_id, tgl_pinjam, tgl_jatuh_tempo, status, denda, catatan)
                        VALUES (?, ?, ?, ?, ?, ?, 'dipinjam', 0.00, ?)
                    ");
                    $stmtInsert->execute([
                        $kode,
                        $member_id,
                        $book_id,
                        $_SESSION['user_id'] ?? null,
                        $tgl_pinjam,
                        $tgl_jatuh_tempo,
                        $catatan
                    ]);

                    log_activity($db, 'TRANSAKSI_PINJAM',
                        "Peminjaman buku \"{$judulBuku}\" oleh {$anggota['nama']} - Kode: {$kode}");

                    $db->commit();
                    set_flash('success', "Transaksi peminjaman <strong>{$kode}</strong> berhasil dibuat.");
                    redirect('modules/loans/index.php');
                    $berhasil = true;
                    break;

                } catch (PDOException $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    // Poin 1: jika error duplikat UNIQUE pada kode_transaksi, retry
                    if ($e->getCode() === '23000' && $percobaan < $maxRetry) {
                        continue; // coba lagi dengan kode baru
                    }
                    // Poin 3: jangan bocorkan pesan DB mentah ke user
                    error_log('Loan create PDOException (percobaan ' . $percobaan . '): ' . $e->getMessage());
                    $errors[] = 'Gagal menyimpan transaksi peminjaman. Silakan coba lagi beberapa saat.';
                    break;
                } catch (Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    error_log('Loan create Exception: ' . $e->getMessage());
                    $errors[] = 'Terjadi kesalahan pada sistem. Silakan coba lagi beberapa saat.';
                    break;
                }
            }
        }
    }
}

// Data untuk dropdown (setelah proses POST agar tidak error saat redirect)
$members = $db->query("SELECT id, kode_anggota, nama FROM members WHERE status = 'aktif' ORDER BY nama ASC")->fetchAll();
$books   = $db->query("SELECT id, kode_buku, judul, stok FROM books WHERE stok > 0 ORDER BY judul ASC")->fetchAll();

$pageTitle = 'Pinjam Buku Baru';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="mb-4">
    <a href="<?= base_url('modules/loans/index.php') ?>" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left"></i> Kembali ke Daftar Peminjaman
    </a>
    <h4 class="fw-bold mt-2 mb-1">Pinjam Buku Baru</h4>
    <p class="text-muted mb-0">Isi form berikut untuk mencatat transaksi peminjaman buku.</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
        <div>
            <?php if (count($errors) === 1): ?>
                <?= htmlspecialchars($errors[0]) ?>
            <?php else: ?>
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Anggota Peminjam <span class="text-danger">*</span></label>
                    <select name="member_id" class="form-select" required>
                        <option value="">-- Pilih Anggota Aktif --</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= (($_POST['member_id'] ?? '') == $m['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nama']) ?> (<?= htmlspecialchars($m['kode_anggota']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($members)): ?>
                        <small class="text-danger">Belum ada anggota aktif terdaftar.</small>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Buku yang Dipinjam <span class="text-danger">*</span></label>
                    <select name="book_id" class="form-select" required>
                        <option value="">-- Pilih Buku Tersedia --</option>
                        <?php foreach ($books as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= (($_POST['book_id'] ?? '') == $b['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['judul']) ?> (Stok: <?= $b['stok'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($books)): ?>
                        <small class="text-danger">Tidak ada buku dengan stok tersedia saat ini.</small>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tanggal Pinjam <span class="text-danger">*</span></label>
                    <input type="date" name="tgl_pinjam" class="form-control"
                           value="<?= htmlspecialchars($_POST['tgl_pinjam'] ?? date('Y-m-d')) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Lama Peminjaman (hari) <span class="text-danger">*</span></label>
                    <input type="number" name="lama_pinjam" min="1" max="<?= MAX_LAMA_PINJAM ?>"
                           class="form-control"
                           value="<?= htmlspecialchars((string)($_POST['lama_pinjam'] ?? DEFAULT_PINJAM_HARI)) ?>" required>
                    <small class="text-muted">Default <?= DEFAULT_PINJAM_HARI ?> hari. Maks. <?= MAX_LAMA_PINJAM ?> hari.</small>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tarif Denda Keterlambatan</label>
                    <input type="text" class="form-control bg-light" value="<?= format_rupiah(DENDA_PER_HARI) ?> / hari" disabled>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Catatan (opsional)</label>
                    <textarea name="catatan" class="form-control" rows="2"
                              placeholder="Keperluan peminjaman, kondisi awal buku, dsb."><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-circle me-1"></i> Simpan Peminjaman
                </button>
                <a href="<?= base_url('modules/loans/index.php') ?>" class="btn btn-outline-secondary px-4">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
