<?php
/**
 * Modul Buku - Hapus Buku
 * Hanya menerima POST + CSRF; blok hapus jika ada pinjaman aktif.
 */
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/books/index.php');
}

// CSRF early stop
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Permintaan tidak sah.');
    redirect('modules/books/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'ID buku tidak valid.');
    redirect('modules/books/index.php');
}

// Ambil data buku
$stmtBuku = $db->prepare("SELECT judul FROM books WHERE id = ?");
$stmtBuku->execute([$id]);
$buku = $stmtBuku->fetch();

if (!$buku) {
    set_flash('danger', 'Buku tidak ditemukan.');
    redirect('modules/books/index.php');
}

// Cek pinjaman aktif
$stmtAktif = $db->prepare("SELECT COUNT(*) FROM loans WHERE book_id = ? AND status IN ('dipinjam', 'terlambat')");
$stmtAktif->execute([$id]);
if ($stmtAktif->fetchColumn() > 0) {
    set_flash('warning', "Buku <strong>{$buku['judul']}</strong> tidak bisa dihapus karena masih ada pinjaman aktif.");
    redirect('modules/books/index.php');
}

// Cek riwayat pinjaman (sudah dikembalikan) — soft restriction
$stmtRiwayat = $db->prepare("SELECT COUNT(*) FROM loans WHERE book_id = ?");
$stmtRiwayat->execute([$id]);
$adaRiwayat = $stmtRiwayat->fetchColumn() > 0;

try {
    if ($adaRiwayat) {
        // Jika ada riwayat, tandai hilang daripada hapus fisik (integritas data)
        $stmtUpd = $db->prepare("UPDATE books SET status = 'hilang', stok = 0 WHERE id = ?");
        $stmtUpd->execute([$id]);
        log_activity($db, 'NONAKTIF_BUKU', "Buku \"{$buku['judul']}\" dinonaktifkan (ada riwayat pinjaman).");
        set_flash('info', "Buku <strong>{$buku['judul']}</strong> dinonaktifkan (stok 0) karena memiliki riwayat pinjaman. Data riwayat tetap terjaga.");
    } else {
        // Hapus fisik jika tidak ada riwayat sama sekali
        $stmtDel = $db->prepare("DELETE FROM books WHERE id = ?");
        $stmtDel->execute([$id]);
        log_activity($db, 'HAPUS_BUKU', "Buku \"{$buku['judul']}\" (ID: {$id}) dihapus dari sistem.");
        set_flash('success', "Buku <strong>{$buku['judul']}</strong> berhasil dihapus.");
    }
} catch (PDOException $e) {
    error_log('Book delete PDOException: ' . $e->getMessage());
    set_flash('danger', 'Gagal menghapus buku. Silakan coba lagi.');
}

redirect('modules/books/index.php');
