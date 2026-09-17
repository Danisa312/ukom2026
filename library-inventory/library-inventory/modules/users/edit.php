<?php
/**
 * Modul Pengguna - Edit Data Pengguna (Khusus Owner)
 * Sistem Inventaris Perpustakaan
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('owner');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'ID pengguna tidak valid.');
    redirect('modules/users/index.php');
}

$stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$id]);
$user = $stmtUser->fetch();

if (!$user) {
    set_flash('danger', 'Pengguna tidak ditemukan.');
    redirect('modules/users/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi form tidak valid. Silakan muat ulang halaman.');
        redirect('modules/users/edit.php?id=' . $id);
    }

    $nama            = trim($_POST['nama'] ?? '');
    $username        = strtolower(trim($_POST['username'] ?? ''));
    $email           = strtolower(trim($_POST['email'] ?? ''));
    $role            = trim($_POST['role'] ?? 'admin');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validasi dasar
    if (empty($nama))     $errors[] = 'Nama lengkap wajib diisi.';
    if (empty($username)) $errors[] = 'Username wajib diisi.';
    elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
        $errors[] = 'Username harus 3-30 karakter alfanumerik.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    if (!in_array($role, ['admin', 'owner'])) {
        $role = 'admin';
    }

    // Jika ingin ubah password
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = 'Password baru minimal 6 karakter.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Konfirmasi password baru tidak cocok.';
        }
    }

    // Cek duplikasi username / email ke user lain
    if (empty($errors)) {
        $stmtCek = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmtCek->execute([$username, $email, $id]);
        if ($stmtCek->fetch()) {
            $errors[] = 'Username atau email sudah digunakan oleh akun lain.';
        }
    }

    // Jangan izinkan owner mencabut role owner dirinya sendiri jika hanya ada 1 owner
    if (empty($errors) && $id === (int)$currentUser['id'] && $role !== 'owner') {
        $errors[] = 'Anda tidak dapat menurunkan peran (role) akun Anda sendiri.';
    }

    if (empty($errors)) {
        try {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    UPDATE users
                    SET nama = ?, username = ?, email = ?, role = ?, password = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nama, $username, $email, $role, $hash, $id]);
            } else {
                $stmt = $db->prepare("
                    UPDATE users
                    SET nama = ?, username = ?, email = ?, role = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nama, $username, $email, $role, $id]);
            }

            log_activity($db, 'EDIT_USER', "Update data pengguna: {$nama} (@{$username})");
            set_flash('success', "Akun pengguna <strong>{$nama}</strong> berhasil diperbarui.");
            redirect('modules/users/index.php');
        } catch (PDOException $e) {
            error_log('User edit PDOException: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan perubahan akun pengguna.';
        }
    }

    $user['nama']     = $nama;
    $user['username'] = $username;
    $user['email']    = $email;
    $user['role']     = $role;
}

$pageTitle = 'Edit Pengguna - ' . $user['nama'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <a href="<?= base_url('modules/users/index.php') ?>" class="text-muted text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Kembali ke Manajemen Pengguna
                </a>
                <h4 class="fw-bold mt-1 mb-0">Edit Akun Pengguna</h4>
            </div>
        </div>

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

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" required autofocus>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">@</span>
                                <input type="text" name="username" class="form-control font-monospace" value="<?= htmlspecialchars($user['username']) ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Peran / Hak Akses <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin (Operasional Perpustakaan)</option>
                                <option value="owner" <?= $user['role'] === 'owner' ? 'selected' : '' ?>>Owner (Kepala Perpustakaan / Akses Penuh)</option>
                            </select>
                        </div>

                        <div class="col-12 mt-4 pt-2 border-top">
                            <h6 class="fw-bold text-secondary mb-1">Ubah Kata Sandi</h6>
                            <small class="text-muted d-block mb-3">Kosongkan jika Anda tidak ingin mengubah kata sandi akun ini.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kata Sandi Baru</label>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Konfirmasi Sandi Baru</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi kata sandi baru">
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4 pt-2 border-top">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-circle me-1"></i> Simpan Perubahan
                        </button>
                        <a href="<?= base_url('modules/users/index.php') ?>" class="btn btn-outline-secondary px-4">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
