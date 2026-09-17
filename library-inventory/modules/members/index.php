<?php
/**
 * Modul Master Data Anggota Perpustakaan
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = 'Data Anggota Perpustakaan';
require_once __DIR__ . '/../../includes/header.php';

$db = get_db();
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "
    SELECT m.*, 
           COUNT(CASE WHEN l.status IN ('dipinjam', 'terlambat') THEN 1 END) AS pinjaman_aktif,
           COUNT(l.id) AS total_transaksi
    FROM members m
    LEFT JOIN loans l ON m.id = l.member_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (m.nama LIKE ? OR m.kode_anggota LIKE ? OR m.no_identitas LIKE ? OR m.kontak LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term, $term];
}

if (!empty($status)) {
    $sql .= " AND m.status = ?";
    $params[] = $status;
}

$sql .= " GROUP BY m.id ORDER BY m.nama ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h3 class="fw-bold mb-1">Data Anggota Perpustakaan</h3>
                <p class="text-muted small mb-0">Kelola informasi anggota terdaftar, status keanggotaan, dan pantau histori peminjaman.</p>
            </div>
            <a href="<?= base_url('modules/members/create.php') ?>" class="btn btn-primary shadow-sm">
                <i class="bi bi-person-plus me-1"></i> Tambah Anggota
            </a>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <form action="<?= base_url('modules/members/index.php') ?>" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" name="search" 
                           placeholder="Cari nama, kode anggota, no. KTP/SIM, kontak..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">-- Semua Status --</option>
                    <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($status)): ?>
                    <a href="<?= base_url('modules/members/index.php') ?>" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th>Kode &amp; Nama</th>
                        <th>No. Identitas</th>
                        <th>Kontak &amp; Alamat</th>
                        <th>Status</th>
                        <th class="text-center">Pinjaman Aktif</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-2 text-secondary d-block mb-2"></i>
                                Tidak ditemukan data anggota perpustakaan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $m): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($m['nama']) ?></div>
                                    <span class="badge bg-light text-primary border font-monospace"><?= htmlspecialchars($m['kode_anggota']) ?></span>
                                </td>
                                <td>
                                    <span class="small font-monospace text-secondary"><?= htmlspecialchars($m['no_identitas']) ?></span>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark"><?= htmlspecialchars($m['kontak']) ?></div>
                                    <small class="text-muted text-truncate d-block" style="max-width: 240px;" title="<?= htmlspecialchars($m['alamat']) ?>">
                                        <?= htmlspecialchars($m['alamat']) ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($m['status'] === 'aktif'): ?>
                                        <span class="badge badge-soft-success px-2 py-1">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-soft-danger px-2 py-1">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($m['pinjaman_aktif'] > 0): ?>
                                        <span class="badge bg-warning text-dark px-2 py-1"><?= $m['pinjaman_aktif'] ?> buku</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">0 buku</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/members/history.php?id=' . $m['id']) ?>" class="btn btn-outline-info" title="Riwayat Peminjaman">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <a href="<?= base_url('modules/members/edit.php?id=' . $m['id']) ?>" class="btn btn-outline-primary" title="Edit Anggota">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="<?= base_url('modules/members/delete.php?id=' . $m['id'] . '&csrf_token=' . csrf_token()) ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('Yakin ingin menghapus anggota \'<?= htmlspecialchars(addslashes($m['nama'])) ?>\'?');"
                                           title="Hapus Anggota">
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
