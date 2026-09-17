<?php
/**
 * Modul Pengguna - Tambah Pengguna Baru (Khusus Owner)
 * Sistem Inventaris Perpustakaan
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('owner');

$errors = [];
$nama     = '';
$username = '';
$email    = '';
$role     = 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Sesi form tidak valid. Silakan muat ulang halaman.');
        redirect('modules/users/create.php');
    }

    $nama            = trim($_POST['nama'] ?? '');
    $username        = strtolower(trim($_POST['username'] ?? ''));
    $email           = strtolower(trim($_POST['email'] ?? ''));
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role            = trim($_POST['role'] ?? 'admin');

    // Validasi
    if (empty($nama))     $errors[] = 'Nama lengkap wajib diisi.';
    if (empty($username)) $errors[] = 'Username wajib diisi.';
    elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
        $errors[] = 'Username harus 3-30 karakter alfanumerik (boleh huruf, angka, _, -, .).';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format alamat email tidak valid.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password minimal terdiri dari 6 karakter.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }
    if (!in_array($role, ['admin', 'owner'])) {
        $role = 'admin';
    }

    // Cek duplikasi username / email
    if (empty($errors)) {
        $stmtCek = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmtCek->execute([$username, $email]);
        if ($stmtCek->fetch()) {
            $errors[] = 'Username atau Email sudah pernah digunakan oleh pengguna lain.';
        }
    }

    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                INSERT INTO users (nama, username, email, password, role)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nama, $username, $email, $hashedPassword, $role]);

            log_activity($db, 'TAMBAH_USER', "Menambahkan akun pengguna baru: {$nama} (@{$username}, role: {$role})");
            set_flash('success', "Akun pengguna <strong>{$nama}</strong> berhasil dibuat!");
            redirect('modules/users/index.php');
        } catch (PDOException $e) {
            error_log('User create PDOException: ' . $e->getMessage());
            $errors[] = 'Gagal menyimpan akun baru ke database.';
        }
    }
}

$pageTitle = 'Tambah Pengguna Baru';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <a href="<?= base_url('modules/users/index.php') ?>" class="text-muted text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Kembali ke Manajemen Pengguna
                </a>
                <h4 class="fw-bold mt-1 mb-0">Tambah Pengguna Baru</h4>
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
                            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($nama) ?>" 
                                   placeholder="Contoh: Budi Prasetyo" required autofocus>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">@</span>
                                <input type="text" name="username" class="form-control font-monospace" value="<?= htmlspecialchars($username) ?>" 
                                       placeholder="budipras" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Alamat Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" 
                                   placeholder="budi@perpustakaan.local" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Peran / Hak Akses <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin (Operasional Katalog, Sirkulasi & Anggota)</option>
                                <option value="owner" <?= $role === 'owner' ? 'selected' : '' ?>>Owner (Kepala Perpustakaan / Akses Penuh)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kata Sandi <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Konfirmasi Sandi <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi kata sandi" required>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4 pt-2 border-top">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-person-check me-1"></i> Buat Akun Pengguna
                        </button>
                        <a href="<?= base_url('modules/users/index.php') ?>" class="btn btn-outline-secondary px-4">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
