<?php
/**
 * Modul Master Data Lokasi Rak Buku
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = 'Rak Penyimpanan Buku';
require_once __DIR__ . '/../../includes/header.php';

$db = get_db();
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT s.*, COUNT(b.id) AS total_buku
    FROM shelves s
    LEFT JOIN books b ON s.id = b.rak_id
";
$params = [];

if (!empty($search)) {
    $sql .= " WHERE s.nama_rak LIKE ? OR s.lokasi LIKE ? OR s.keterangan LIKE ?";
    $term = "%{$search}%";
    $params = [$term, $term, $term];
}

$sql .= " GROUP BY s.id ORDER BY s.nama_rak ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$shelves = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h3 class="fw-bold mb-1">Rak Penyimpanan Buku</h3>
                <p class="text-muted small mb-0">Kelola denah dan lokasi penempatan fisik buku di perpustakaan.</p>
            </div>
            <a href="<?= base_url('modules/shelves/create.php') ?>" class="btn btn-primary shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Tambah Rak Baru
            </a>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <form action="<?= base_url('modules/shelves/index.php') ?>" method="GET" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" name="search" 
                           placeholder="Cari nama rak, lokasi, atau keterangan..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search)): ?>
                    <a href="<?= base_url('modules/shelves/index.php') ?>" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th width="60">#</th>
                        <th>Nama / Kode Rak</th>
                        <th>Lokasi Fisik</th>
                        <th>Keterangan</th>
                        <th class="text-center">Koleksi Buku</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shelves)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-grid-3x3 fs-2 text-secondary d-block mb-2"></i>
                                Belum ada data rak buku.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($shelves as $idx => $sh): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($sh['nama_rak']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border">
                                        <i class="bi bi-geo-alt me-1 text-danger"></i> <?= htmlspecialchars($sh['lokasi'] ?: 'Belum diatur') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-muted"><?= htmlspecialchars($sh['keterangan'] ?: '-') ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1">
                                        <?= (int)$sh['total_buku'] ?> Judul
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/shelves/edit.php?id=' . $sh['id']) ?>" class="btn btn-outline-primary" title="Edit Rak">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="<?= base_url('modules/shelves/delete.php?id=' . $sh['id'] . '&csrf_token=' . csrf_token()) ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('Yakin ingin menghapus rak \'<?= htmlspecialchars(addslashes($sh['nama_rak'])) ?>\'?');"
                                           title="Hapus Rak">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
