<?php
/**
 * Modul Login Pengguna
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../../includes/auth.php';

// Jika sudah login, langsung arahkan ke dashboard
if (is_logged_in()) {
    if (is_owner()) {
        redirect('owner/dashboard.php');
    } else {
        redirect('admin/dashboard.php');
    }
}

$error = '';
$usernameOrEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'Sesi keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $usernameOrEmail = trim($_POST['username_or_email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($usernameOrEmail) || empty($password)) {
            $error = 'Harap isi username/email dan kata sandi Anda.';
        } else {
            $db = get_db();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Regenerasi session ID untuk mencegah session fixation
                session_regenerate_id(true);

                $_SESSION['user_id']       = $user['id'];
                $_SESSION['user_nama']     = $user['nama'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_email']    = $user['email'];
                $_SESSION['user_role']     = $user['role'];

                // Catat log aktivitas
                log_activity($db, 'LOGIN', "Pengguna @{$user['username']} ({$user['role']}) berhasil masuk.");

                set_flash('success', "Selamat datang kembali, " . htmlspecialchars($user['nama']) . "!");

                if ($user['role'] === 'owner') {
                    redirect('owner/dashboard.php');
                } else {
                    redirect('admin/dashboard.php');
                }
            } else {
                $error = 'Username / Email atau Kata Sandi yang Anda masukkan salah.';
            }
        }
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Sistem Inventaris Perpustakaan</title>
    
    <!-- Offline CSS Assets -->
    <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            max-width: 440px;
            width: 100%;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            color: #ffffff;
            padding: 2.2rem 2rem 1.8rem;
            text-align: center;
        }

    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="display-6 mb-2">📚</div>
        <h4 class="fw-bold mb-1">SIPerpus</h4>
        <p class="small text-white-50 mb-0">Sistem Inventaris Perpustakaan Digital</p>
    </div>

    <div class="p-4 p-md-4">
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show small py-2" role="alert">
                <?= htmlspecialchars($flash['message']) ?>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show small py-2 d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div><?= htmlspecialchars($error) ?></div>
                <button type="button" class="btn-close ms-auto py-2" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('modules/auth/login.php') ?>" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="mb-3">
                <label for="username_or_email" class="form-label fw-semibold small text-secondary">Username atau Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="username_or_email" name="username_or_email" 
                           placeholder="admin atau owner" value="<?= htmlspecialchars($usernameOrEmail) ?>" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-semibold small text-secondary">Kata Sandi</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" 
                           placeholder="••••••••" required>
                    <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePasswordBtn">
                        <i class="bi bi-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm mb-0">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Sistem
            </button>
        </form>
    </div>
</div>

<script src="<?= base_url('assets/js/bootstrap.bundle.min.js') ?>"></script>
<script>
    // Fitur toggle view password
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePasswordIcon');
    
    toggleBtn.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        toggleIcon.classList.toggle('bi-eye');
        toggleIcon.classList.toggle('bi-eye-slash');
    });


</script>
</body>
</html>
