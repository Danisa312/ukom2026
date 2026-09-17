<?php
/**
 * Modul Master Data Kategori Buku
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = 'Kategori Buku';
require_once __DIR__ . '/../../includes/header.php';

$db = get_db();
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT c.*, COUNT(b.id) AS total_buku
    FROM categories c
    LEFT JOIN books b ON c.id = b.kategori_id
";
$params = [];

if (!empty($search)) {
    $sql .= " WHERE c.nama_kategori LIKE ? OR c.deskripsi LIKE ?";
    $term = "%{$search}%";
    $params = [$term, $term];
}

$sql .= " GROUP BY c.id ORDER BY c.nama_kategori ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h3 class="fw-bold mb-1">Kategori Buku</h3>
                <p class="text-muted small mb-0">Klasifikasi genre dan bidang keilmuan koleksi buku perpustakaan.</p>
            </div>
            <a href="<?= base_url('modules/categories/create.php') ?>" class="btn btn-primary shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Tambah Kategori
            </a>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <form action="<?= base_url('modules/categories/index.php') ?>" method="GET" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" name="search" 
                           placeholder="Cari nama kategori atau deskripsi..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search)): ?>
                    <a href="<?= base_url('modules/categories/index.php') ?>" class="btn btn-outline-secondary">Reset</a>
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
                        <th>Nama Kategori</th>
                        <th>Deskripsi</th>
                        <th class="text-center">Jumlah Buku</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-tags fs-2 text-secondary d-block mb-2"></i>
                                Belum ada data kategori buku.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $idx => $cat): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($cat['nama_kategori']) ?></span>
                                </td>
                                <td>
                                    <span class="text-secondary small"><?= htmlspecialchars($cat['deskripsi'] ?: '-') ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary rounded-pill px-3 py-1">
                                        <?= (int)$cat['total_buku'] ?> Judul
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/categories/edit.php?id=' . $cat['id']) ?>" class="btn btn-outline-primary" title="Edit Kategori">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="<?= base_url('modules/categories/delete.php?id=' . $cat['id'] . '&csrf_token=' . csrf_token()) ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('Yakin ingin menghapus kategori \'<?= htmlspecialchars(addslashes($cat['nama_kategori'])) ?>\'?');"
                                           title="Hapus Kategori">
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
