<?php
/**
 * Modul Buku - Daftar Buku Koleksi Perpustakaan
 */
require_once __DIR__ . '/../../config/database.php';
$pageTitle = 'Manajemen Buku';
require_once __DIR__ . '/../../includes/header.php';

// Filter pencarian
$cari     = trim($_GET['cari']       ?? '');
$katId    = (int)($_GET['kategori']  ?? 0);
$rakId    = (int)($_GET['rak']       ?? 0);
$statusF  = trim($_GET['status']     ?? '');

// Halaman
$perPage  = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

// Build WHERE
$where  = ['1=1'];
$params = [];

if ($cari !== '') {
    $where[]  = '(b.kode_buku LIKE ? OR b.judul LIKE ? OR b.penulis LIKE ? OR b.isbn LIKE ?)';
    $like     = "%{$cari}%";
    $params   = array_merge($params, [$like, $like, $like, $like]);
}
if ($katId > 0) {
    $where[]  = 'b.kategori_id = ?';
    $params[] = $katId;
}
if ($rakId > 0) {
    $where[]  = 'b.rak_id = ?';
    $params[] = $rakId;
}
if (in_array($statusF, ['tersedia', 'dipinjam', 'hilang'])) {
    $where[]  = 'b.status = ?';
    $params[] = $statusF;
}

$whereSql = implode(' AND ', $where);

// Total count
$stmtTotal = $db->prepare("SELECT COUNT(*) FROM books b WHERE {$whereSql}");
$stmtTotal->execute($params);
$total = (int)$stmtTotal->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// Data buku + join kategori & rak + hitung pinjaman aktif per buku
$stmtBuku = $db->prepare("
    SELECT b.*, c.nama_kategori, s.nama_rak,
           (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.id AND l.status IN ('dipinjam', 'terlambat')) AS dipinjam_count
    FROM books b
    LEFT JOIN categories c ON c.id = b.kategori_id
    LEFT JOIN shelves s    ON s.id  = b.rak_id
    WHERE {$whereSql}
    ORDER BY b.judul ASC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmtBuku->execute($params);
$books = $stmtBuku->fetchAll();

// Dropdown filter
$categories = $db->query("SELECT id, nama_kategori FROM categories ORDER BY nama_kategori ASC")->fetchAll();
$shelves    = $db->query("SELECT id, nama_rak FROM shelves ORDER BY nama_rak ASC")->fetchAll();

// Ringkasan statistik (Konsisten dengan Dashboard Admin & Owner)
$totalJudul     = (int)$db->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalStok      = (int)$db->query("SELECT SUM(stok) FROM books")->fetchColumn() ?: 0;
$sedangDipinjam = (int)$db->query("SELECT COUNT(*) FROM loans WHERE status IN ('dipinjam', 'terlambat')")->fetchColumn();
$stokHabis      = (int)$db->query("SELECT COUNT(*) FROM books WHERE stok <= 0")->fetchColumn();

$stats = [
    'total'           => $totalJudul,
    'total_stok'      => $totalStok,
    'sedang_dipinjam' => $sedangDipinjam,
    'stok_habis'      => $stokHabis,
];
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Buku</h4>
        <p class="text-muted mb-0 small">Kelola seluruh koleksi buku perpustakaan.</p>
    </div>
    <a href="<?= base_url('modules/books/create.php') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Tambah Buku
    </a>
</div>

<!-- Statistik ringkas -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-primary"><?= number_format($stats['total']) ?></div>
            <div class="text-muted small">Total Judul</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-success"><?= number_format($stats['total_stok']) ?></div>
            <div class="text-muted small">Total Stok</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-warning"><?= number_format($stats['sedang_dipinjam']) ?></div>
            <div class="text-muted small">Sedang Dipinjam</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-2 fw-bold text-danger"><?= number_format($stats['stok_habis']) ?></div>
            <div class="text-muted small">Stok Habis</div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Cari Buku</label>
                <input type="text" name="cari" class="form-control form-control-sm" placeholder="Judul, pengarang, kode, ISBN..."
                       value="<?= htmlspecialchars($cari) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Kategori</label>
                <select name="kategori" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $katId === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Rak</label>
                <select name="rak" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($shelves as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $rakId === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nama_rak']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="tersedia"  <?= $statusF === 'tersedia'  ? 'selected' : '' ?>>Tersedia</option>
                    <option value="dipinjam"  <?= $statusF === 'dipinjam'  ? 'selected' : '' ?>>Dipinjam</option>
                    <option value="hilang"    <?= $statusF === 'hilang'    ? 'selected' : '' ?>>Hilang</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="<?= base_url('modules/books/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($books)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-book fs-1 d-block mb-2"></i>
                Tidak ada buku yang cocok dengan filter yang dipilih.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Kode</th>
                        <th>Judul &amp; Pengarang</th>
                        <th>Kategori / Rak</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $i => $b): ?>
                    <tr>
                        <td class="ps-3 text-muted small"><?= $offset + $i + 1 ?></td>
                        <td>
                            <span class="badge bg-light text-secondary font-monospace"><?= htmlspecialchars($b['kode_buku']) ?></span>
                            <?php if ($b['isbn']): ?>
                                <div class="text-muted" style="font-size:.72rem">ISBN: <?= htmlspecialchars($b['isbn']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($b['judul']) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($b['penulis']) ?>
                                <?php if ($b['tahun_terbit']): ?>
                                    &middot; <?= $b['tahun_terbit'] ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="small">
                            <?php if ($b['nama_kategori']): ?>
                                <span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($b['nama_kategori']) ?></span>
                            <?php endif; ?>
                            <?php if ($b['nama_rak']): ?>
                                <div class="text-muted mt-1"><i class="bi bi-archive me-1"></i><?= htmlspecialchars($b['nama_rak']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="fw-bold <?= $b['stok'] <= 0 ? 'text-danger' : 'text-success' ?>"><?= $b['stok'] ?></span>
                            <div class="text-muted" style="font-size:.72rem">/ <?= $b['stok_total'] ?? $b['stok'] ?></div>
                        </td>
                        <td class="text-center">
                            <?php
                            $statusMap = [
                                'tersedia' => ['success', 'Tersedia'],
                                'dipinjam' => ['warning',  'Dipinjam'],
                                'hilang'   => ['danger',   'Hilang'],
                            ];
                            [$cls, $lbl] = $statusMap[$b['status']] ?? ['secondary', $b['status']];
                            ?>
                            <span class="badge bg-<?= $cls ?>-subtle text-<?= $cls ?>"><?= $lbl ?></span>
                        </td>
                        <td class="text-end pe-3">
                            <a href="<?= base_url('modules/books/edit.php?id=' . $b['id']) ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    onclick="konfirmasiHapus(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['judul'])) ?>')"
                                    title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginasi -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <small class="text-muted">Menampilkan <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> dari <?= $total ?> buku</small>
            <nav><ul class="pagination pagination-sm mb-0">
                <?php
                $q = $_GET;
                for ($p = 1; $p <= $totalPages; $p++):
                    $q['page'] = $p;
                    $url = base_url('modules/books/index.php') . '?' . http_build_query($q);
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

<!-- Form hapus tersembunyi -->
<form id="formHapus" method="POST" action="<?= base_url('modules/books/delete.php') ?>" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="id" id="hapusId">
</form>

<script>
function konfirmasiHapus(id, judul) {
    if (confirm('Hapus buku "' + judul + '"?\n\nBuku yang masih memiliki riwayat pinjaman tidak bisa dihapus.')) {
        document.getElementById('hapusId').value = id;
        document.getElementById('formHapus').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
