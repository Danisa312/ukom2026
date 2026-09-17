<?php
/**
 * Konfigurasi Database & Helper Global
 * Sistem Inventaris Perpustakaan (PHP Native + MySQL)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!ob_get_level()) {
    ob_start();
}

// Konfigurasi Database XAMPP
define('DB_HOST', 'localhost');
define('DB_NAME', 'library_inventory');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Konfigurasi Denda & Waktu Pinjam
define('DENDA_PER_HARI', 1000); // Rp 1.000 per hari keterlambatan
define('DEFAULT_PINJAM_HARI', 7); // Default durasi peminjaman 7 hari

// Inisialisasi Koneksi PDO
function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}

// Instance database global
$db = get_db();

// Helper Base URL
function base_url($path = '') {
    // Jalur relatif dari root localhost XAMPP
    $base = '/library-inventory';
    if ($path === '' || $path === '/') {
        return $base;
    }
    return $base . '/' . ltrim($path, '/');
}

// Helper Redirect
function redirect($path) {
    header("Location: " . base_url($path));
    exit;
}

// Helper Sanitasi Input
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

// Helper Format Rupiah
function format_rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// Helper Format Tanggal Indonesia
function format_tanggal($tanggal) {
    if (!$tanggal || $tanggal === '0000-00-00') return '-';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $split = explode('-', date('Y-m-d', strtotime($tanggal)));
    return (int)$split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

// Helper Flash Message
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Helper CSRF Protection
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

// Helper Activity Log (Audit Trail)
function log_activity($db, $aksi, $detail = null) {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, aksi, detail, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $aksi, $detail, $ip]);
    } catch (Exception $e) {
        // Jangan gagalkan operasi utama jika logging gagal
        error_log("Failed to write activity log: " . $e->getMessage());
    }
}

// ==========================================================
// Helper Pengecekan Status Peminjaman (Single Source of Truth)
// ==========================================================

/**
 * Mengecek apakah transaksi peminjaman sudah melewati tanggal jatuh tempo (terlambat)
 */
function is_overdue($tgl_jatuh_tempo, $status = 'dipinjam'): bool {
    if (!in_array($status, ['dipinjam', 'terlambat'])) {
        return false;
    }
    return strtotime(date('Y-m-d')) > strtotime($tgl_jatuh_tempo);
}

/**
 * Mengecek apakah transaksi peminjaman jatuh tempo hari ini atau sudah terlambat
 */
function is_due_today_or_overdue($tgl_jatuh_tempo, $status = 'dipinjam'): bool {
    if (!in_array($status, ['dipinjam', 'terlambat'])) {
        return false;
    }
    return strtotime(date('Y-m-d')) >= strtotime($tgl_jatuh_tempo);
}

/**
 * Menghitung estimasi denda berjalan secara dinamis berdasarkan hari keterlambatan
 */
function hitung_denda_berjalan($tgl_jatuh_tempo, $status = 'dipinjam'): float {
    if (!is_overdue($tgl_jatuh_tempo, $status)) {
        return 0.0;
    }
    $today = new DateTime(date('Y-m-d'));
    $due   = new DateTime($tgl_jatuh_tempo);
    $hari  = (int)$today->diff($due)->days;
    return (float)($hari * DENDA_PER_HARI);
}

/**
 * Mendapatkan status tampilan transaksi secara dinamis (otomatis 'terlambat' jika lewat jatuh tempo)
 */
function get_display_status_pinjam($tgl_jatuh_tempo, $status): string {
    if (is_overdue($tgl_jatuh_tempo, $status)) {
        return 'terlambat';
    }
    return $status;
}

/**
 * Menghasilkan HTML badge status peminjaman yang seragam di semua modul
 */
function status_badge_pinjam($tgl_jatuh_tempo, $status): string {
    $displayStatus = get_display_status_pinjam($tgl_jatuh_tempo, $status);
    $map = [
        'dipinjam'     => ['class' => 'bg-primary', 'label' => 'Dipinjam'],
        'dikembalikan' => ['class' => 'bg-success', 'label' => 'Dikembalikan'],
        'terlambat'    => ['class' => 'bg-danger', 'label' => 'Terlambat'],
        'hilang'       => ['class' => 'bg-dark', 'label' => 'Hilang'],
        'rusak'        => ['class' => 'bg-warning text-dark', 'label' => 'Rusak'],
    ];
    $item = $map[$displayStatus] ?? ['class' => 'bg-secondary', 'label' => ucfirst($displayStatus)];
    return '<span class="badge ' . $item['class'] . ' px-2 py-1">' . $item['label'] . '</span>';
}

