<?php
/**
 * Helper Autentikasi & Otorisasi Berbasis Peran
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../config/database.php';

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'       => $_SESSION['user_id'],
        'nama'     => $_SESSION['user_nama'] ?? '',
        'username' => $_SESSION['user_username'] ?? '',
        'email'    => $_SESSION['user_email'] ?? '',
        'role'     => $_SESSION['user_role'] ?? ''
    ];
}

function is_admin() {
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

function is_owner() {
    $user = current_user();
    return $user && $user['role'] === 'owner';
}

function require_login() {
    if (!is_logged_in()) {
        set_flash('warning', 'Silakan masuk ke akun Anda terlebih dahulu.');
        redirect('modules/auth/login.php');
    }
}

function require_role($roles) {
    require_login();
    $roles = (array)$roles;
    $user = current_user();
    
    if (!$user || !in_array($user['role'], $roles)) {
        set_flash('danger', 'Akses ditolak! Akun Anda (' . ($user['role'] ?? 'guest') . ') tidak memiliki izin untuk halaman ini.');
        if ($user && $user['role'] === 'owner') {
            redirect('owner/dashboard.php');
        } else {
            redirect('admin/dashboard.php');
        }
    }
}
