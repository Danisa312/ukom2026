<?php
/**
 * Tambah Anggota Perpustakaan
 * Sistem Inventaris Perpustakaan
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$db = get_db();

// Auto generate kode anggota — gunakan suffix random untuk menghindari race condition
function generate_kode_anggota(): string {
    // Format: MBR-YYYYMMDD-XXXX (aman terhadap concurrent insert)
    return 'MBR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
}
$autoKode = generate_kode_anggota();

$error = '';
$kodeAnggota = $autoKode;
$nama = '';
$noIdentitas = '';
$kontak = '';
$alamat = '';
$status = 'aktif';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    // Poin 4: early stop jika CSRF gagal
    if (!verify_csrf($token)) {
        set_flash('danger', 'Sesi form tidak valid. Silakan muat ulang halaman.');
        redirect('modules/members/create.php');
    }

    $kodeAnggota = trim($_POST['kode_anggota'] ?? '');
    $nama        = trim($_POST['nama']         ?? '');
    $noIdentitas = trim($_POST['no_identitas'] ?? '');
    $kontak      = trim($_POST['kontak']       ?? '');
    $alamat      = trim($_POST['alamat']       ?? '');
    $status      = trim($_POST['status']       ?? 'aktif');

    if (empty($kodeAnggota) || empty($nama) || empty($noIdentitas) || empty($kontak)) {
        $error = 'Harap lengkapi seluruh bidang yang bertanda bintang (*).';
    } else {
        // Cek duplikasi no_identitas atau kode_anggota
        $stmtCek = $db->prepare("SELECT id FROM members WHERE kode_anggota = ? OR no_identitas = ?");
        $stmtCek->execute([$kodeAnggota, $noIdentitas]);
        if ($stmtCek->fetch()) {
            $error = 'Kode anggota atau Nomor Identitas sudah pernah terdaftar di sistem.';
        } else {
            $stmtInsert = $db->prepare("
                INSERT INTO members (kode_anggota, nama, no_identitas, kontak, alamat, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([$kodeAnggota, $nama, $noIdentitas, $kontak, $alamat, $status]);

            log_activity($db, 'TAMBAH_ANGGOTA', "Pendaftaran anggota baru: {$nama} ({$kodeAnggota})");
            set_flash('success', "Anggota '{$nama}' berhasil didaftarkan dengan kode {$kodeAnggota}!");
            redirect('modules/members/index.php');
        }
    }
}

$pageTitle = 'Tambah Anggota Perpustakaan';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h3 class="fw-bold mb-1">Registrasi Anggota Baru</h3>
                <p class="text-muted small mb-0">Daftarkan anggota perpustakaan untuk transaksi sirkulasi buku.</p>
            </div>
            <a href="<?= base_url('modules/members/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div><?= htmlspecialchars($error) ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="card-title fw-bold mb-0 text-primary">Form Registrasi Anggota</h5>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/members/create.php') ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="kode_anggota" class="form-label fw-semibold text-secondary small">Kode Anggota <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="kode_anggota" name="kode_anggota"
                                   value="<?= htmlspecialchars($kodeAnggota) ?>" required>
                            <div class="form-text">Dibuat otomatis, dapat disesuaikan bila perlu.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label fw-semibold text-secondary small">Status Keanggotaan <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif (Bisa Meminjam)</option>
                                <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif (Ditangguhkan)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="nama" class="form-label fw-semibold text-secondary small">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama" name="nama"
                               value="<?= htmlspecialchars($nama) ?>" placeholder="Contoh: Muhammad Ihsan" required autofocus>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="no_identitas" class="form-label fw-semibold text-secondary small">No. Identitas (NIK/KTP/NIM/NISN) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="no_identitas" name="no_identitas"
                                   value="<?= htmlspecialchars($noIdentitas) ?>" placeholder="3201..." required>
                        </div>
                        <div class="col-md-6">
                            <label for="kontak" class="form-label fw-semibold text-secondary small">No. HP / WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="kontak" name="kontak"
                                   value="<?= htmlspecialchars($kontak) ?>" placeholder="08..." required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="alamat" class="form-label fw-semibold text-secondary small">Alamat Domisili Lengkap</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="3"
                                  placeholder="Nama jalan, nomor rumah, RT/RW, kota..."><?= htmlspecialchars($alamat) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <a href="<?= base_url('modules/members/index.php') ?>" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Data Anggota
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
