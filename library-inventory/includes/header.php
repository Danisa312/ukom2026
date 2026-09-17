<?php
/**
 * Header & Layout Sidebar Vertikal
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/auth.php';
require_login();

$currentUser = current_user();
$pageTitle = $pageTitle ?? 'Dashboard';
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

// Helper untuk menandai menu aktif
function is_active_menu($path) {
    global $currentPath;
    return strpos($currentPath, $path) !== false ? 'active' : '';
}
$isMasterActive = (
    strpos($currentPath, 'modules/books') !== false ||
    strpos($currentPath, 'modules/categories') !== false ||
    strpos($currentPath, 'modules/shelves') !== false ||
    strpos($currentPath, 'modules/members') !== false
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - SIPerpus</title>
    
    <!-- Offline Local CSS: Bootstrap 5 & Bootstrap Icons -->
    <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>">
</head>
<body>

<!-- 1. SIDEBAR VERTIKAL KIRI -->
<aside class="app-sidebar no-print" id="appSidebar">
    <!-- Bagian Atas: Logo SIPerpus & Badge Role -->
    <div class="sidebar-header">
        <a href="<?= is_owner() ? base_url('owner/dashboard.php') : base_url('admin/dashboard.php') ?>" class="sidebar-brand">
            <span class="fs-3">📚</span>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold tracking-tight text-white fs-5">SIPerpus</span>
                    <span class="badge <?= is_owner() ? 'bg-primary' : 'bg-success' ?> text-uppercase px-2 py-0.5" style="font-size: 0.65rem;">
                        <?= htmlspecialchars($currentUser['role']) ?>
                    </span>
                </div>
                <small class="text-white-50 d-block" style="font-size: 0.725rem;">Inventaris Perpustakaan</small>
            </div>
        </a>
    </div>

    <!-- Bagian Tengah: Menu Navigasi Vertikal -->
    <div class="sidebar-body">
        <div class="sidebar-heading">Menu Utama</div>

        <!-- Dashboard -->
        <?php if (is_owner()): ?>
            <a href="<?= base_url('owner/dashboard.php') ?>" class="sidebar-link <?= is_active_menu('owner/dashboard.php') ?>">
                <i class="bi bi-speedometer2 text-primary"></i>
                <span>Dashboard Owner</span>
            </a>
        <?php else: ?>
            <a href="<?= base_url('admin/dashboard.php') ?>" class="sidebar-link <?= is_active_menu('admin/dashboard.php') ?>">
                <i class="bi bi-speedometer2 text-primary"></i>
                <span>Dashboard Admin</span>
            </a>
        <?php endif; ?>

        <!-- Transaksi Peminjaman & Pengembalian -->
        <a href="<?= base_url('modules/loans/index.php') ?>" class="sidebar-link <?= is_active_menu('modules/loans') ?>">
            <i class="bi bi-arrow-left-right text-warning"></i>
            <span>Peminjaman &amp; Denda</span>
        </a>

        <!-- Data Master (Collapsible Submenu) -->
        <div class="my-1">
            <a class="sidebar-link justify-content-between <?= $isMasterActive ? 'active' : '' ?>" data-bs-toggle="collapse" href="#menuMaster" role="button" aria-expanded="<?= $isMasterActive ? 'true' : 'false' ?>">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-collection text-info"></i>
                    <span>Data Master</span>
                </div>
                <i class="bi bi-chevron-down small" style="font-size: 0.75rem; width: auto;"></i>
            </a>
            <div class="collapse <?= $isMasterActive ? 'show' : '' ?>" id="menuMaster">
                <ul class="sidebar-submenu mt-1">
                    <li>
                        <a href="<?= base_url('modules/books/index.php') ?>" class="sidebar-link <?= is_active_menu('modules/books') ?>">
                            <i class="bi bi-book"></i> Data Buku
                        </a>
                    </li>
                    <li>
                        <a href="<?= base_url('modules/categories/index.php') ?>" class="sidebar-link <?= is_active_menu('modules/categories') ?>">
                            <i class="bi bi-tags"></i> Kategori Buku
                        </a>
                    </li>
                    <li>
                        <a href="<?= base_url('modules/shelves/index.php') ?>" class="sidebar-link <?= is_active_menu('modules/shelves') ?>">
                            <i class="bi bi-grid-3x3"></i> Rak Penyimpanan
                        </a>
                    </li>
                    <li>
                        <a href="<?= base_url('modules/members/index.php') ?>" class="sidebar-link <?= is_active_menu('modules/members') ?>">
                            <i class="bi bi-people"></i> Data Anggota
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Menu Khusus Owner -->
        <?php if (is_owner()): ?>
            <div class="sidebar-heading mt-3">Eksekutif &amp; Audit</div>

            <a href="<?= base_url('modules/reports/index.php') ?>" class="sidebar-link <?= is_active_menu('modules/reports') ?>">
                <i class="bi bi-file-earmark-bar-graph text-success"></i>
                <span>Laporan &amp; Export</span>
            </a>

            <a href="<?= base_url('modules/logs/index.php') ?>" class="sidebar-link <?= is_active_menu('modules/logs') ?>">
                <i class="bi bi-clock-history text-danger"></i>
                <span>Log Aktivitas (Audit)</span>
            </a>

            <a href="<?= base_url('modules/users/index.php') ?>" class="sidebar-link <?= is_active_menu('modules/users') ?>">
                <i class="bi bi-person-badge text-info"></i>
                <span>Kelola Akun Admin</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Bagian Bawah: Info User (Administrator Perpustakaan / Owner) & Tombol Profil/Keluar -->
    <div class="sidebar-footer">
        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 38px; height: 38px; font-size: 0.9rem; flex-shrink: 0;">
                <?= strtoupper(substr($currentUser['nama'], 0, 1)) ?>
            </div>
            <div class="overflow-hidden">
                <div class="fw-bold text-white text-truncate small" title="<?= htmlspecialchars($currentUser['nama']) ?>">
                    <?= htmlspecialchars($currentUser['nama']) ?>
                </div>
                <div class="text-white-50" style="font-size: 0.75rem;">
                    @<?= htmlspecialchars($currentUser['username']) ?> &bull; <?= ucfirst(htmlspecialchars($currentUser['role'])) ?>
                </div>
            </div>
        </div>
        <div class="d-flex gap-1 pt-1 border-top border-secondary border-opacity-25">
            <a href="<?= base_url('modules/auth/profile.php') ?>" class="btn btn-sm btn-outline-light flex-grow-1 py-1" style="font-size: 0.78rem;" title="Pengaturan Profil">
                <i class="bi bi-gear me-1"></i> Profil
            </a>
            <a href="<?= base_url('modules/auth/logout.php') ?>" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size: 0.78rem;" onclick="return confirm('Yakin ingin keluar dari sistem?');" title="Keluar">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Overlay untuk tampilan mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- 2. WRAPPER KONTEN UTAMA DI SISI KANAN -->
<div class="main-wrapper" id="mainWrapper">
    <!-- Topbar Header Konten -->
    <header class="top-bar no-print">
        <div class="d-flex align-items-center gap-3">
            <!-- Tombol Toggle Sidebar -->
            <button class="btn btn-light border btn-sm px-2 py-1 shadow-sm" id="btnToggleSidebar" type="button" title="Sembunyikan/Tampilkan Sidebar">
                <i class="bi bi-layout-sidebar-inset fs-5 text-secondary"></i>
            </button>
            <h5 class="mb-0 fw-bold text-dark d-none d-sm-inline"><?= htmlspecialchars($pageTitle) ?></h5>
        </div>

        <div class="d-flex align-items-center gap-2">
            <!-- Tanggal Hari Ini -->
            <span class="badge bg-light text-secondary border px-3 py-2 d-none d-md-inline-block">
                <i class="bi bi-calendar3 me-1 text-primary"></i> <?= date('d M Y') ?>
            </span>
            <!-- Tombol Cepat Profil -->
            <a href="<?= base_url('modules/auth/profile.php') ?>" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-md-inline small fw-semibold"><?= htmlspecialchars($currentUser['nama']) ?></span>
            </a>
        </div>
    </header>

    <!-- Area Konten Utama -->
    <main class="py-4 flex-grow-1">
        <div class="container-fluid px-4">
            <?php 
            $flash = get_flash();
            if ($flash): 
            ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show shadow-sm border-0 d-flex align-items-center gap-2 mb-4" role="alert">
                    <?php if ($flash['type'] === 'success'): ?>
                        <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                    <?php elseif ($flash['type'] === 'danger'): ?>
                        <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
                    <?php elseif ($flash['type'] === 'warning'): ?>
                        <i class="bi bi-exclamation-circle-fill fs-5 text-warning"></i>
                    <?php else: ?>
                        <i class="bi bi-info-circle-fill fs-5 text-info"></i>
                    <?php endif; ?>
                    <div><?= $flash['message'] ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
