<?php
/**
 * Modul Profil & Ubah Password
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$currentUser = current_user();
$userId = $currentUser['id'];
$db = get_db();

// Ambil data user terkini
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'Token keamanan tidak valid. Silakan coba lagi.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $nama = trim($_POST['nama'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($nama) || empty($email)) {
                $error = 'Nama dan email tidak boleh kosong.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Format alamat email tidak valid.';
            } else {
                // Cek apakah email sudah dipakai user lain
                $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $checkStmt->execute([$email, $userId]);
                if ($checkStmt->fetch()) {
                    $error = 'Alamat email tersebut sudah digunakan oleh akun lain.';
                } else {
                    $updateStmt = $db->prepare("UPDATE users SET nama = ?, email = ? WHERE id = ?");
                    $updateStmt->execute([$nama, $email, $userId]);

                    $_SESSION['user_nama'] = $nama;
                    $_SESSION['user_email'] = $email;
                    $currentUser['nama'] = $nama;
                    $currentUser['email'] = $email;

                    log_activity($db, 'UPDATE_PROFIL', "Pengguna @{$user['username']} memperbarui nama/email.");
                    set_flash('success', 'Profil Anda berhasil diperbarui.');
                    redirect('modules/auth/profile.php');
                }
            }
        } elseif ($action === 'change_password') {
            $currentPass = $_POST['current_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';

            if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
                $error = 'Semua bidang kata sandi wajib diisi.';
            } elseif (!password_verify($currentPass, $user['password'])) {
                $error = 'Kata sandi saat ini yang Anda masukkan salah.';
            } elseif (strlen($newPass) < 6) {
                $error = 'Kata sandi baru minimal harus 6 karakter.';
            } elseif ($newPass !== $confirmPass) {
                $error = 'Konfirmasi kata sandi baru tidak cocok.';
            } else {
                $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                $updatePassStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updatePassStmt->execute([$hashed, $userId]);

                log_activity($db, 'GANTI_PASSWORD', "Pengguna @{$user['username']} berhasil mengubah kata sandi.");
                set_flash('success', 'Kata sandi Anda berhasil diperbarui!');
                redirect('modules/auth/profile.php');
            }
        }
    }
}

$pageTitle = 'Profil Pengguna & Keamanan';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h3 class="fw-bold mb-1">Pengaturan Profil &amp; Keamanan</h3>
                <p class="text-muted small mb-0">Kelola informasi akun dan kata sandi Anda</p>
            </div>
            <a href="<?= is_owner() ? base_url('owner/dashboard.php') : base_url('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
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

<div class="row g-4">
    <!-- Card Data Profil -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center gap-2">
                <i class="bi bi-person-circle fs-5 text-primary"></i>
                <h5 class="card-title fw-bold mb-0">Informasi Pribadi</h5>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/auth/profile.php') ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Username (ID Akun)</label>
                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['username']) ?>" readonly disabled>
                        <small class="text-muted">Username digunakan untuk masuk dan tidak dapat diubah.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Peran Pengguna (Role)</label>
                        <div>
                            <span class="badge <?= $user['role'] === 'owner' ? 'bg-primary' : 'bg-success' ?> text-uppercase px-3 py-2">
                                <?= htmlspecialchars($user['role']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="nama" class="form-label text-secondary small fw-semibold">Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($user['nama']) ?>" required>
                    </div>

                    <div class="mb-4">
                        <label for="email" class="form-label text-secondary small fw-semibold">Alamat Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan Profil
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Card Ganti Password -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center gap-2">
                <i class="bi bi-shield-lock fs-5 text-danger"></i>
                <h5 class="card-title fw-bold mb-0">Perbarui Kata Sandi</h5>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/auth/profile.php') ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label for="current_password" class="form-label text-secondary small fw-semibold">Kata Sandi Saat Ini</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required placeholder="••••••••">
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label text-secondary small fw-semibold">Kata Sandi Baru (Min. 6 Karakter)</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6" placeholder="••••••••">
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label text-secondary small fw-semibold">Konfirmasi Kata Sandi Baru</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6" placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-key me-1"></i> Perbarui Kata Sandi
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
