<?php
/**
 * Hapus Rak Buku
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../../includes/auth.php';
require_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? ($_POST['csrf_token'] ?? '');

if (!verify_csrf($token)) {
    set_flash('danger', 'Token keamanan tidak valid.');
    redirect('modules/shelves/index.php');
}

$stmt = $db->prepare("SELECT * FROM shelves WHERE id = ?");
$stmt->execute([$id]);
$shelf = $stmt->fetch();

if (!$shelf) {
    set_flash('danger', 'Data rak tidak ditemukan.');
    redirect('modules/shelves/index.php');
}

// Cek apakah ada buku di rak ini
$stmtCek = $db->prepare("SELECT COUNT(*) FROM books WHERE rak_id = ?");
$stmtCek->execute([$id]);
$bukuCount = $stmtCek->fetchColumn();

if ($bukuCount > 0) {
    set_flash('danger', "Rak '{$shelf['nama_rak']}' tidak dapat dihapus karena masih memuat {$bukuCount} buku!");
    redirect('modules/shelves/index.php');
}

$stmtDelete = $db->prepare("DELETE FROM shelves WHERE id = ?");
$stmtDelete->execute([$id]);

log_activity($db, 'HAPUS_RAK', "Menghapus rak '{$shelf['nama_rak']}' (ID: {$id})");
set_flash('success', "Rak '{$shelf['nama_rak']}' berhasil dihapus.");
redirect('modules/shelves/index.php');
