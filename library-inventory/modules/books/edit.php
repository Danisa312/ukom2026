<?php
/**
 * Modul Buku - Edit Data Buku
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'ID buku tidak valid.');
    redirect('modules/books/index.php');
}

// Ambil data buku yang akan diedit
$stmtBuku = $db->prepare("SELECT * FROM books WHERE id = ?");
$stmtBuku->execute([$id]);
$buku = $stmtBuku->fetch();

if (!$buku) {
    set_flash('danger', 'Buku tidak ditemukan.');
    redirect('modules/books/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF early stop
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi form tidak valid. Silakan muat ulang halaman.');
        redirect('modules/books/edit.php?id=' . $id);
    }

    $kodeBuku    = trim($_POST['kode_buku']    ?? '');
    $judul       = trim($_POST['judul']        ?? '');
    $pengarang   = trim($_POST['pengarang']    ?? '');
    $penerbit    = trim($_POST['penerbit']     ?? '');
    $isbn        = trim($_POST['isbn']         ?? '');
    $tahunTerbit = trim($_POST['tahun_terbit'] ?? '');
    $kategoriId  = (int)($_POST['category_id'] ?? 0);
    $rakId       = (int)($_POST['shelf_id']    ?? 0);
    $stok        = (int)($_POST['stok']        ?? 0);
    $stokTotal   = (int)($_POST['stok_total']  ?? $stok);
    $deskripsi   = sanitize($_POST['deskripsi'] ?? '');

    // Validasi input sesuai aturan kolom tabel books
    if (empty($kodeBuku))    $errors[] = 'Kode buku wajib diisi.';
    if (empty($judul))       $errors[] = 'Judul buku wajib diisi.';
    if (empty($pengarang))   $errors[] = 'Nama pengarang / penulis wajib diisi.';
    if (empty($penerbit))    $errors[] = 'Nama penerbit wajib diisi.';
    if (empty($isbn))        $errors[] = 'Nomor ISBN wajib diisi.';
    if (empty($tahunTerbit)) {
        $errors[] = 'Tahun terbit wajib diisi.';
    } elseif (!is_numeric($tahunTerbit) || strlen($tahunTerbit) !== 4) {
        $errors[] = 'Tahun terbit harus 4 digit angka (contoh: 2024).';
    }
    if ($stok < 0)           $errors[] = 'Jumlah stok tidak boleh negatif.';
    if ($kategoriId <= 0)    $errors[] = 'Kategori wajib dipilih.';
    if ($rakId <= 0)         $errors[] = 'Rak penyimpanan wajib dipilih.';

    if (empty($errors)) {
        // Cek duplikat kode buku (kecualikan buku ini sendiri)
        $stmtCekKode = $db->prepare("SELECT id FROM books WHERE kode_buku = ? AND id != ?");
        $stmtCekKode->execute([$kodeBuku, $id]);
        if ($stmtCekKode->fetch()) {
            $errors[] = "Kode buku '{$kodeBuku}' sudah digunakan oleh buku lain.";
        }

        // Cek duplikat ISBN (kecualikan buku ini sendiri)
        $stmtCekIsbn = $db->prepare("SELECT id FROM books WHERE isbn = ? AND id != ?");
        $stmtCekIsbn->execute([$isbn, $id]);
        if ($stmtCekIsbn->fetch()) {
            $errors[] = "Nomor ISBN '{$isbn}' sudah digunakan oleh buku lain.";
        }
    }

    if (empty($errors)) {
        $statusBuku = $stok > 0 ? 'tersedia' : 'dipinjam';
        try {
            $stmt = $db->prepare("
                UPDATE books
                SET kode_buku = ?, judul = ?, penulis = ?, penerbit = ?,
                    isbn = ?, tahun_terbit = ?, kategori_id = ?, rak_id = ?,
                    stok = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $kodeBuku,
                $judul,
                $pengarang,
                $penerbit,
                $isbn,
                (int)$tahunTerbit,
                $kategoriId,
                $rakId,
                $stok,
                $statusBuku,
                $id
            ]);
            log_activity($db, 'EDIT_BUKU', "Update data buku: \"{$judul}\" (ID: {$id})");
            set_flash('success', "Data buku <strong>{$judul}</strong> berhasil diperbarui.");
            redirect('modules/books/index.php');
        } catch (PDOException $e) {
            error_log('Book edit PDOException: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan perubahan ke database. Silakan periksa kembali data atau coba beberapa saat lagi.';
        }
    }

    // Jika ada error, timpa nilai tampilan dengan POST data
    $buku = array_merge($buku, [
        'kode_buku'    => $kodeBuku,
        'judul'        => $judul,
        'penulis'      => $pengarang,
        'penerbit'     => $penerbit,
        'isbn'         => $isbn,
        'tahun_terbit' => $tahunTerbit,
        'kategori_id'  => $kategoriId,
        'rak_id'       => $rakId,
        'stok'         => $stok,
    ]);
}

$categories = $db->query("SELECT id, nama_kategori FROM categories ORDER BY nama_kategori ASC")->fetchAll();
$shelves    = $db->query("SELECT id, nama_rak FROM shelves ORDER BY nama_rak ASC")->fetchAll();

$pageTitle = 'Edit Buku';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <a href="<?= base_url('modules/books/index.php') ?>" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Kembali ke Daftar Buku
        </a>
        <h4 class="fw-bold mt-1 mb-0">Edit Buku</h4>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
        <div>
            <?php if (count($errors) === 1): echo htmlspecialchars($errors[0]);
            else: ?>
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

            <h6 class="fw-bold text-uppercase text-muted mb-3" style="letter-spacing:.05em;font-size:.8rem">Informasi Buku</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Kode Buku <span class="text-danger">*</span></label>
                    <input type="text" name="kode_buku" class="form-control font-monospace"
                           value="<?= htmlspecialchars($buku['kode_buku']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">ISBN <span class="text-danger">*</span></label>
                    <input type="text" name="isbn" class="form-control"
                           value="<?= htmlspecialchars($buku['isbn'] ?? '') ?>" placeholder="978-..." required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Tahun Terbit <span class="text-danger">*</span></label>
                    <input type="number" name="tahun_terbit" class="form-control"
                           min="1900" max="<?= date('Y') + 1 ?>"
                           value="<?= htmlspecialchars($buku['tahun_terbit'] ?? date('Y')) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Judul Buku <span class="text-danger">*</span></label>
                    <input type="text" name="judul" class="form-control"
                           value="<?= htmlspecialchars($buku['judul']) ?>" required autofocus>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Pengarang <span class="text-danger">*</span></label>
                    <input type="text" name="pengarang" class="form-control"
                           value="<?= htmlspecialchars($buku['penulis'] ?? $buku['pengarang'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Penerbit <span class="text-danger">*</span></label>
                    <input type="text" name="penerbit" class="form-control"
                           value="<?= htmlspecialchars($buku['penerbit'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Deskripsi / Sinopsis</label>
                    <textarea name="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($buku['deskripsi'] ?? '') ?></textarea>
                </div>
            </div>

            <hr>
            <h6 class="fw-bold text-uppercase text-muted mb-3" style="letter-spacing:.05em;font-size:.8rem">Lokasi &amp; Stok</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Kategori <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($buku['kategori_id'] ?? $buku['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Rak Penyimpanan <span class="text-danger">*</span></label>
                    <select name="shelf_id" class="form-select" required>
                        <option value="">-- Pilih Rak --</option>
                        <?php foreach ($shelves as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($buku['rak_id'] ?? $buku['shelf_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nama_rak']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Stok Tersedia <span class="text-danger">*</span></label>
                    <input type="number" name="stok" id="inputStok" class="form-control" min="0"
                           value="<?= (int)$buku['stok'] ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Total Eksemplar</label>
                    <input type="number" name="stok_total" id="inputStokTotal" class="form-control" min="0"
                           value="<?= (int)($buku['stok_total'] ?? $buku['stok']) ?>">
                    <div class="form-text">Termasuk yang dipinjam.</div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-circle me-1"></i> Simpan Perubahan
                </button>
                <a href="<?= base_url('modules/books/index.php') ?>" class="btn btn-outline-secondary px-4">Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('inputStok').addEventListener('input', function() {
    const stokTotal = document.getElementById('inputStokTotal');
    if (parseInt(stokTotal.value) < parseInt(this.value)) {
        stokTotal.value = this.value;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
