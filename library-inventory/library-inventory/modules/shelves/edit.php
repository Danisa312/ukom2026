<?php
/**
 * Edit Rak Buku
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM shelves WHERE id = ?");
$stmt->execute([$id]);
$shelf = $stmt->fetch();

if (!$shelf) {
    set_flash('danger', 'Data rak tidak ditemukan.');
    redirect('modules/shelves/index.php');
}

$error = '';
$namaRak = $shelf['nama_rak'];
$lokasi = $shelf['lokasi'];
$keterangan = $shelf['keterangan'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'Token sesi tidak valid.';
    } else {
        $namaRak = trim($_POST['nama_rak'] ?? '');
        $lokasi = trim($_POST['lokasi'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        if (empty($namaRak)) {
            $error = 'Nama rak wajib diisi.';
        } else {
            $stmtCek = $db->prepare("SELECT id FROM shelves WHERE nama_rak = ? AND id != ?");
            $stmtCek->execute([$namaRak, $id]);
            if ($stmtCek->fetch()) {
                $error = 'Nama rak tersebut sudah terdaftar.';
            } else {
                $stmtUpdate = $db->prepare("UPDATE shelves SET nama_rak = ?, lokasi = ?, keterangan = ? WHERE id = ?");
                $stmtUpdate->execute([$namaRak, $lokasi, $keterangan, $id]);

                log_activity($db, 'EDIT_RAK', "Memperbarui rak ID {$id}: '{$namaRak}'");
                set_flash('success', "Data rak '{$namaRak}' berhasil diperbarui!");
                redirect('modules/shelves/index.php');
            }
        }
    }
}

$pageTitle = 'Edit Rak Buku';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h3 class="fw-bold mb-1">Edit Lokasi Rak</h3>
                <p class="text-muted small mb-0">Ubah informasi nama, lokasi, dan keterangan rak buku.</p>
            </div>
            <a href="<?= base_url('modules/shelves/index.php') ?>" class="btn btn-outline-secondary btn-sm">
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
                <h5 class="card-title fw-bold mb-0 text-primary">Form Edit Rak</h5>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/shelves/edit.php?id=' . $id) ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="mb-3">
                        <label for="nama_rak" class="form-label fw-semibold text-secondary small">Nama / Kode Rak <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_rak" name="nama_rak" 
                               value="<?= htmlspecialchars($namaRak) ?>" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="lokasi" class="form-label fw-semibold text-secondary small">Lokasi Fisik</label>
                        <input type="text" class="form-control" id="lokasi" name="lokasi" 
                               value="<?= htmlspecialchars($lokasi) ?>">
                    </div>

                    <div class="mb-4">
                        <label for="keterangan" class="form-label fw-semibold text-secondary small">Keterangan Tambahan</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="3"><?= htmlspecialchars($keterangan) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <a href="<?= base_url('modules/shelves/index.php') ?>" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
