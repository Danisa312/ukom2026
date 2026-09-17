<?php
/**
 * Modul Logout Pengguna
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../../includes/auth.php';

if (is_logged_in()) {
    $db = get_db();
    $username = $_SESSION['user_username'] ?? 'User';
    log_activity($db, 'LOGOUT', "Pengguna @{$username} berhasil logout.");

    // Hapus seluruh data sesi
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// Mulai sesi baru khusus untuk notifikasi logout
session_start();
set_flash('info', 'Anda telah berhasil keluar dari sistem.');
redirect('modules/auth/login.php');
