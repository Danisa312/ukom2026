<?php
/**
 * Hapus Kategori Buku
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../../includes/auth.php';
require_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? ($_POST['csrf_token'] ?? '');

if (!verify_csrf($token)) {
    set_flash('danger', 'Token keamanan tidak valid.');
    redirect('modules/categories/index.php');
}

$stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('danger', 'Kategori tidak ditemukan.');
    redirect('modules/categories/index.php');
}

// Cek apakah ada buku yang mengait ke kategori ini
$stmtCek = $db->prepare("SELECT COUNT(*) FROM books WHERE kategori_id = ?");
$stmtCek->execute([$id]);
$bukuCount = $stmtCek->fetchColumn();

if ($bukuCount > 0) {
    set_flash('danger', "Kategori '{$category['nama_kategori']}' tidak dapat dihapus karena masih digunakan oleh {$bukuCount} buku!");
    redirect('modules/categories/index.php');
}

$stmtDelete = $db->prepare("DELETE FROM categories WHERE id = ?");
$stmtDelete->execute([$id]);

log_activity($db, 'HAPUS_KATEGORI', "Menghapus kategori '{$category['nama_kategori']}' (ID: {$id})");
set_flash('success', "Kategori '{$category['nama_kategori']}' berhasil dihapus.");
redirect('modules/categories/index.php');
