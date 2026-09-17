<?php
/**
 * Modul Manajemen Pengguna / Admin (Khusus Owner)
 * Sistem Inventaris Perpustakaan
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('owner');

$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (nama LIKE ? OR username LIKE ? OR email LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term];
}

if (!empty($roleFilter) && in_array($roleFilter, ['admin', 'owner'])) {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
}

$sql .= " ORDER BY role ASC, nama ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Statistik user
$totalAdmin = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalOwner = $db->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn();

$pageTitle = 'Kelola Akun Pengguna & Admin';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Pengguna &amp; Hak Akses</h4>
        <p class="text-muted small mb-0">Kelola akun administrator operasional perpustakaan dan akun eksekutif owner.</p>
    </div>
    <a href="<?= base_url('modules/users/create.php') ?>" class="btn btn-primary shadow-sm">
        <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna Baru
    </a>
</div>

<!-- Kartu Ringkasan Akun -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 bg-white shadow-sm">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Pengguna</span>
                    <h3 class="fw-bold my-1 text-dark"><?= count($users) ?></h3>
                    <small class="text-muted">Akun terdaftar</small>
                </div>
                <div class="stat-icon-wrapper bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 bg-white shadow-sm">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Akun Admin</span>
                    <h3 class="fw-bold my-1 text-success"><?= $totalAdmin ?></h3>
                    <small class="text-muted">Operasional harian</small>
                </div>
                <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success">
                    <i class="bi bi-shield-check"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card border-0 bg-white shadow-sm">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Akun Owner</span>
                    <h3 class="fw-bold my-1 text-info"><?= $totalOwner ?></h3>
                    <small class="text-muted">Kepala perpustakaan</small>
                </div>
                <div class="stat-icon-wrapper bg-info bg-opacity-10 text-info">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Pengguna -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" name="search"
                           placeholder="Cari nama, username, email..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">-- Semua Role --</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="owner" <?= $roleFilter === 'owner' ? 'selected' : '' ?>>Owner</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($roleFilter)): ?>
                    <a href="<?= base_url('modules/users/index.php') ?>" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Pengguna</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role Akses</th>
                        <th>Tgl Terdaftar</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-person-x fs-2 text-secondary d-block mb-2"></i>
                                Tidak ditemukan data pengguna.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $i => $u): 
                            $isSelf = ((int)$u['id'] === (int)$currentUser['id']);
                        ?>
                            <tr>
                                <td class="text-muted small ps-3"><?= $i + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle <?= $u['role'] === 'owner' ? 'bg-primary' : 'bg-success' ?> text-white d-flex align-items-center justify-content-center fw-bold"
                                             style="width: 34px; height: 34px; font-size: 0.85rem;">
                                            <?= strtoupper(substr($u['nama'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">
                                                <?= htmlspecialchars($u['nama']) ?>
                                                <?php if ($isSelf): ?>
                                                    <span class="badge bg-secondary ms-1" style="font-size: 0.65rem;">Anda</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="font-monospace text-secondary small">@<?= htmlspecialchars($u['username']) ?></span></td>
                                <td><span class="small text-muted"><?= htmlspecialchars($u['email']) ?></span></td>
                                <td>
                                    <span class="badge <?= $u['role'] === 'owner' ? 'bg-primary' : 'bg-success' ?> px-2 py-1 text-uppercase" style="font-size: 0.72rem;">
                                        <?= htmlspecialchars($u['role']) ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?= format_tanggal($u['created_at']) ?></small></td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/users/edit.php?id=' . $u['id']) ?>" class="btn btn-outline-primary" title="Edit Akun">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <?php if (!$isSelf): ?>
                                            <a href="<?= base_url('modules/users/delete.php?id=' . $u['id'] . '&csrf_token=' . csrf_token()) ?>"
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('Yakin ingin menghapus akun pengguna \'<?= htmlspecialchars(addslashes($u['nama'])) ?>\'?');"
                                               title="Hapus Akun">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
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
