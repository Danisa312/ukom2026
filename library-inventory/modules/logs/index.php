<?php
/**
 * Modul Log Aktivitas - Audit Trail Sistem
 * Hanya bisa diakses oleh role OWNER
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

// Hanya owner yang boleh akses
if (!is_owner()) {
    set_flash('danger', 'Akses ditolak. Halaman ini hanya untuk Owner.');
    redirect('admin/dashboard.php');
}

// Filter
$cari    = trim($_GET['cari']    ?? '');
$aksiF   = trim($_GET['aksi']   ?? '');
$userF   = (int)($_GET['user_id'] ?? 0);
$tglDari = trim($_GET['tgl_dari'] ?? '');
$tglSampai = trim($_GET['tgl_sampai'] ?? '');

// Paginasi
$perPage = 25;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Build WHERE
$where  = ['1=1'];
$params = [];

if ($cari !== '') {
    $where[]  = '(al.detail LIKE ? OR al.ip_address LIKE ?)';
    $like     = "%{$cari}%";
    $params   = array_merge($params, [$like, $like]);
}
if ($aksiF !== '') {
    $where[]  = 'al.aksi = ?';
    $params[] = $aksiF;
}
if ($userF > 0) {
    $where[]  = 'al.user_id = ?';
    $params[] = $userF;
}
if ($tglDari !== '') {
    $where[]  = 'DATE(al.created_at) >= ?';
    $params[] = $tglDari;
}
if ($tglSampai !== '') {
    $where[]  = 'DATE(al.created_at) <= ?';
    $params[] = $tglSampai;
}

$whereSql = implode(' AND ', $where);

// Total
$stmtTotal = $db->prepare("SELECT COUNT(*) FROM activity_logs al WHERE {$whereSql}");
$stmtTotal->execute($params);
$total = (int)$stmtTotal->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// Data log
$stmtLog = $db->prepare("
    SELECT al.*, u.nama AS nama_user, u.username
    FROM activity_logs al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE {$whereSql}
    ORDER BY al.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmtLog->execute($params);
$logs = $stmtLog->fetchAll();

// Dropdown filter
$aksiList = $db->query("SELECT DISTINCT aksi FROM activity_logs ORDER BY aksi ASC")->fetchAll(PDO::FETCH_COLUMN);
$userList = $db->query("SELECT id, nama, username FROM users ORDER BY nama ASC")->fetchAll();

$pageTitle = 'Log Aktivitas Sistem';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Log Aktivitas Sistem</h4>
        <p class="text-muted mb-0 small">Rekam jejak seluruh aktivitas pengguna sebagai audit trail.</p>
    </div>
    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
        <i class="bi bi-shield-lock me-1"></i> Hanya Owner
    </span>
</div>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Cari (Detail / IP)</label>
                <input type="text" name="cari" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($cari) ?>" placeholder="Cari detail log...">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Jenis Aksi</label>
                <select name="aksi" class="form-select form-select-sm">
                    <option value="">Semua Aksi</option>
                    <?php foreach ($aksiList as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= $aksiF === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Pengguna</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($userList as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $userF === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Dari Tanggal</label>
                <input type="date" name="tgl_dari" class="form-control form-control-sm" value="<?= htmlspecialchars($tglDari) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Sampai Tanggal</label>
                <input type="date" name="tgl_sampai" class="form-control form-control-sm" value="<?= htmlspecialchars($tglSampai) ?>">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search"></i></button>
                <a href="<?= base_url('modules/logs/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Log -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
        <small class="text-muted">Total <strong><?= number_format($total) ?></strong> entri log ditemukan</small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-clock-history fs-1 d-block mb-2"></i>
                Tidak ada log yang sesuai filter.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.875rem">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Waktu</th>
                        <th>Pengguna</th>
                        <th>Aksi</th>
                        <th>Detail</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="ps-3 text-muted" style="white-space:nowrap;font-size:.8rem">
                            <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                        </td>
                        <td>
                            <?php if ($log['nama_user']): ?>
                                <div class="fw-semibold"><?= htmlspecialchars($log['nama_user']) ?></div>
                                <div class="text-muted" style="font-size:.75rem">@<?= htmlspecialchars($log['username']) ?></div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $aksiKelas = match(true) {
                                str_starts_with($log['aksi'], 'HAPUS')    => 'danger',
                                str_starts_with($log['aksi'], 'TAMBAH')   => 'success',
                                str_starts_with($log['aksi'], 'EDIT')     => 'primary',
                                str_starts_with($log['aksi'], 'TRANSAKSI')=> 'warning',
                                str_starts_with($log['aksi'], 'LOGIN')    => 'info',
                                default                                   => 'secondary',
                            };
                            ?>
                            <span class="badge bg-<?= $aksiKelas ?>-subtle text-<?= $aksiKelas ?> border border-<?= $aksiKelas ?>-subtle">
                                <?= htmlspecialchars($log['aksi']) ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($log['detail'] ?? '-') ?></td>
                        <td class="font-monospace text-muted small"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginasi -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <small class="text-muted">Hal <?= $page ?> dari <?= $totalPages ?></small>
            <nav><ul class="pagination pagination-sm mb-0">
                <?php
                $q = $_GET;
                for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++):
                    $q['page'] = $p;
                    $url = base_url('modules/logs/index.php') . '?' . http_build_query($q);
                ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $url ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
