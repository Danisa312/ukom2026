<?php
/**
 * Modul Pengguna - Hapus Akun Pengguna (Khusus Owner)
 * Sistem Inventaris Perpustakaan
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('owner');

$id = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? ($_POST['csrf_token'] ?? '');

if (!verify_csrf($token)) {
    set_flash('danger', 'Token keamanan form tidak valid.');
    redirect('modules/users/index.php');
}

// Tidak boleh menghapus akun diri sendiri yang sedang login
if ($id === (int)$currentUser['id']) {
    set_flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.');
    redirect('modules/users/index.php');
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('danger', 'Akun pengguna tidak ditemukan.');
    redirect('modules/users/index.php');
}

try {
    $stmtDelete = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmtDelete->execute([$id]);

    log_activity($db, 'HAPUS_USER', "Menghapus akun pengguna: {$user['nama']} (@{$user['username']}, role: {$user['role']})");
    set_flash('success', "Akun pengguna <strong>" . htmlspecialchars($user['nama']) . "</strong> berhasil dihapus.");
} catch (PDOException $e) {
    error_log('User delete PDOException: ' . $e->getMessage());
    set_flash('danger', 'Gagal menghapus akun pengguna dari database.');
}

redirect('modules/users/index.php');
