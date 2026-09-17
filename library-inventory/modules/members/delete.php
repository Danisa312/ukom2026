<?php
/**
 * Modul Anggota - Hapus Data Anggota Perpustakaan
 * Sistem Inventaris Perpustakaan
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? ($_POST['csrf_token'] ?? '');

if (!verify_csrf($token)) {
    set_flash('danger', 'Token keamanan form tidak valid.');
    redirect('modules/members/index.php');
}

$stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    set_flash('danger', 'Data anggota tidak ditemukan.');
    redirect('modules/members/index.php');
}

// 1. Cek apakah anggota masih memiliki pinjaman aktif
$stmtAktif = $db->prepare("SELECT COUNT(*) FROM loans WHERE member_id = ? AND status IN ('dipinjam', 'terlambat')");
$stmtAktif->execute([$id]);
$aktifCount = (int)$stmtAktif->fetchColumn();

if ($aktifCount > 0) {
    set_flash('danger', "Anggota '{$member['nama']}' tidak dapat dihapus karena masih meminjam {$aktifCount} buku yang belum dikembalikan!");
    redirect('modules/members/index.php');
}

// 2. Cek apakah anggota memiliki riwayat transaksi peminjaman (FK ON DELETE RESTRICT)
$stmtRiwayat = $db->prepare("SELECT COUNT(*) FROM loans WHERE member_id = ?");
$stmtRiwayat->execute([$id]);
$riwayatCount = (int)$stmtRiwayat->fetchColumn();

if ($riwayatCount > 0) {
    set_flash('danger', "Anggota '{$member['nama']}' tidak dapat dihapus permanen karena memiliki {$riwayatCount} catatan histori transaksi di perpustakaan. Silakan ubah status anggota menjadi 'Nonaktif' lewat menu Edit.");
    redirect('modules/members/index.php');
}

// 3. Eksekusi hapus jika benar-benar bersih dari transaksi
try {
    $stmtDelete = $db->prepare("DELETE FROM members WHERE id = ?");
    $stmtDelete->execute([$id]);

    log_activity($db, 'HAPUS_ANGGOTA', "Menghapus data anggota: {$member['nama']} ({$member['kode_anggota']})");
    set_flash('success', "Anggota <strong>" . htmlspecialchars($member['nama']) . "</strong> berhasil dihapus.");
} catch (PDOException $e) {
    error_log('Member delete PDOException: ' . $e->getMessage());
    set_flash('danger', 'Gagal menghapus anggota dari database.');
}

redirect('modules/members/index.php');
