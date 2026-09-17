<?php
/**
 * Tambah Kategori Buku
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$db = get_db();
$error = '';
$namaKategori = '';
$deskripsi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $namaKategori = trim($_POST['nama_kategori'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');

        if (empty($namaKategori)) {
            $error = 'Nama kategori wajib diisi.';
        } else {
            // Cek duplikasi nama
            $stmtCek = $db->prepare("SELECT id FROM categories WHERE nama_kategori = ?");
            $stmtCek->execute([$namaKategori]);
            if ($stmtCek->fetch()) {
                $error = 'Kategori dengan nama tersebut sudah ada.';
            } else {
                $stmt = $db->prepare("INSERT INTO categories (nama_kategori, deskripsi) VALUES (?, ?)");
                $stmt->execute([$namaKategori, $deskripsi]);

                log_activity($db, 'TAMBAH_KATEGORI', "Menambahkan kategori baru: '{$namaKategori}'");
                set_flash('success', "Kategori '{$namaKategori}' berhasil ditambahkan!");
                redirect('modules/categories/index.php');
            }
        }
    }
}

$pageTitle = 'Tambah Kategori Buku';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h3 class="fw-bold mb-1">Tambah Kategori Baru</h3>
                <p class="text-muted small mb-0">Tambahkan klasifikasi kategori baru untuk koleksi buku.</p>
            </div>
            <a href="<?= base_url('modules/categories/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div><?= htmlspecialchars($error) ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="card-title fw-bold mb-0 text-primary">Form Kategori</h5>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/categories/create.php') ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="mb-3">
                        <label for="nama_kategori" class="form-label fw-semibold text-secondary small">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_kategori" name="nama_kategori" 
                               value="<?= htmlspecialchars($namaKategori) ?>" placeholder="Contoh: Teknologi, Sastra, Kedokteran" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label for="deskripsi" class="form-label fw-semibold text-secondary small">Deskripsi (Opsional)</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" 
                                  placeholder="Keterangan singkat cakupan kategori..."><?= htmlspecialchars($deskripsi) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <a href="<?= base_url('modules/categories/index.php') ?>" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Kategori
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
