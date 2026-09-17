<?php
/**
 * Modul Anggota - Edit Data Anggota Perpustakaan
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
$stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    set_flash('danger', 'Data anggota perpustakaan tidak ditemukan.');
    redirect('modules/members/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi form tidak valid. Silakan muat ulang halaman.');
        redirect('modules/members/edit.php?id=' . $id);
    }

    $nama        = trim($_POST['nama']         ?? '');
    $noIdentitas = trim($_POST['no_identitas'] ?? '');
    $kontak      = trim($_POST['kontak']       ?? '');
    $alamat      = trim($_POST['alamat']       ?? '');
    $status      = trim($_POST['status']       ?? 'aktif');

    // Validasi input
    if (empty($nama))        $errors[] = 'Nama lengkap anggota wajib diisi.';
    if (empty($noIdentitas)) $errors[] = 'Nomor Identitas (KTP/Kartu Pelajar) wajib diisi.';
    if (empty($kontak))      $errors[] = 'Nomor kontak / WhatsApp wajib diisi.';
    if (!in_array($status, ['aktif', 'nonaktif'])) $status = 'aktif';

    // Cek duplikasi no_identitas pada anggota lain
    if (empty($errors)) {
        $stmtCek = $db->prepare("SELECT id FROM members WHERE no_identitas = ? AND id != ?");
        $stmtCek->execute([$noIdentitas, $id]);
        if ($stmtCek->fetch()) {
            $errors[] = "Nomor Identitas '{$noIdentitas}' sudah terdaftar pada anggota lain.";
        }
    }

    if (empty($errors)) {
        try {
            $stmtUpdate = $db->prepare("
                UPDATE members
                SET nama = ?, no_identitas = ?, kontak = ?, alamat = ?, status = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([$nama, $noIdentitas, $kontak, $alamat, $status, $id]);

            log_activity($db, 'EDIT_ANGGOTA', "Pembaruan data anggota: {$nama} ({$member['kode_anggota']})");
            set_flash('success', "Data anggota <strong>" . htmlspecialchars($nama) . "</strong> berhasil diperbarui.");
            redirect('modules/members/index.php');
        } catch (PDOException $e) {
            error_log('Member edit PDOException: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan perubahan data anggota ke database.';
        }
    }

    // Timpa nilai form dengan POST data jika ada error
    $member['nama']         = $nama;
    $member['no_identitas'] = $noIdentitas;
    $member['kontak']       = $kontak;
    $member['alamat']       = $alamat;
    $member['status']       = $status;
}

$pageTitle = 'Edit Anggota - ' . $member['nama'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Navigasi Kembali -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <a href="<?= base_url('modules/members/index.php') ?>" class="text-muted text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Kembali ke Data Anggota
                </a>
                <h4 class="fw-bold mt-1 mb-0">Edit Data Anggota</h4>
            </div>
            <a href="<?= base_url('modules/members/history.php?id=' . $member['id']) ?>" class="btn btn-outline-info btn-sm">
                <i class="bi bi-clock-history me-1"></i> Riwayat Pinjam
            </a>
        </div>

        <!-- Alert Error jika ada -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
                <div>
                    <?php if (count($errors) === 1): echo htmlspecialchars($errors[0]); else: ?>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kode Anggota</label>
                            <input type="text" class="form-control font-monospace bg-light text-muted" 
                                   value="<?= htmlspecialchars($member['kode_anggota']) ?>" readonly>
                            <div class="form-text">Kode anggota bersifat permanen dan unik.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status Keanggotaan <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="aktif" <?= $member['status'] === 'aktif' ? 'selected' : '' ?>>Aktif (Bisa Pinjam Buku)</option>
                                <option value="nonaktif" <?= $member['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif (Tidak Bisa Pinjam)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control" 
                                   value="<?= htmlspecialchars($member['nama']) ?>" placeholder="Nama lengkap anggota" required autofocus>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nomor Identitas (NIK / KTP / Pelajar) <span class="text-danger">*</span></label>
                            <input type="text" name="no_identitas" class="form-control font-monospace" 
                                   value="<?= htmlspecialchars($member['no_identitas']) ?>" placeholder="3201xxxxxxxxxxxx" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">No. Telepon / WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" name="kontak" class="form-control" 
                                   value="<?= htmlspecialchars($member['kontak']) ?>" placeholder="08xxxxxxxxxx" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Alamat Lengkap</label>
                            <textarea name="alamat" class="form-control" rows="3" 
                                      placeholder="Alamat domisili anggota"><?= htmlspecialchars($member['alamat']) ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4 pt-2 border-top">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-circle me-1"></i> Simpan Perubahan
                        </button>
                        <a href="<?= base_url('modules/members/index.php') ?>" class="btn btn-outline-secondary px-4">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
