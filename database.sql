-- ==========================================================
-- Database: library_inventory
-- Sistem Inventaris Perpustakaan (PHP Native + MySQL)
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `library_inventory` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `library_inventory`;

-- ----------------------------------------------------------
-- 1. Tabel Users (Admin & Owner)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'owner') NOT NULL DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. Tabel Categories (Kategori Buku)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_kategori` VARCHAR(100) NOT NULL,
    `deskripsi` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. Tabel Shelves (Rak Buku)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shelves` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_rak` VARCHAR(50) NOT NULL,
    `lokasi` VARCHAR(100) NULL,
    `keterangan` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. Tabel Books (Buku)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `books` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kode_buku` VARCHAR(30) NOT NULL UNIQUE,
    `judul` VARCHAR(255) NOT NULL,
    `penulis` VARCHAR(100) NOT NULL,
    `penerbit` VARCHAR(100) NOT NULL,
    `tahun_terbit` INT NOT NULL,
    `isbn` VARCHAR(30) NOT NULL UNIQUE,
    `kategori_id` INT NOT NULL,
    `rak_id` INT NOT NULL,
    `stok` INT NOT NULL DEFAULT 0,
    `status` ENUM('tersedia', 'dipinjam', 'rusak', 'hilang') NOT NULL DEFAULT 'tersedia',
    `cover` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_books_category` FOREIGN KEY (`kategori_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_books_shelf` FOREIGN KEY (`rak_id`) REFERENCES `shelves` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. Tabel Members (Anggota Perpustakaan)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kode_anggota` VARCHAR(30) NOT NULL UNIQUE,
    `nama` VARCHAR(100) NOT NULL,
    `no_identitas` VARCHAR(50) NOT NULL UNIQUE,
    `kontak` VARCHAR(25) NOT NULL,
    `alamat` TEXT NOT NULL,
    `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6. Tabel Loans (Peminjaman & Pengembalian)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `loans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kode_transaksi` VARCHAR(30) NOT NULL UNIQUE,
    `member_id` INT NOT NULL,
    `book_id` INT NOT NULL,
    `user_id` INT NULL,
    `tgl_pinjam` DATE NOT NULL,
    `tgl_jatuh_tempo` DATE NOT NULL,
    `tgl_kembali` DATE NULL,
    `status` ENUM('dipinjam', 'dikembalikan', 'terlambat', 'hilang', 'rusak') NOT NULL DEFAULT 'dipinjam',
    `denda` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `catatan` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_loans_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_loans_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_loans_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. Tabel Activity Logs (Audit Trail)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `aksi` VARCHAR(100) NOT NULL,
    `detail` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- DATA SEED / DUMMY UNTUK DEMO
-- ==========================================================

-- Data Users: Password admin = 'admin123', password owner = 'owner123'
INSERT INTO `users` (`id`, `nama`, `username`, `email`, `password`, `role`) VALUES
(1, 'Administrator Perpustakaan', 'admin', 'admin@perpustakaan.local', '$2y$10$INKb/.4oqNiFs26Yt6VR7OuB8FwcqM1yJ5ulxLqFzBHxkRLYugD42', 'admin'),
(2, 'Owner / Kepala Perpustakaan', 'owner', 'owner@perpustakaan.local', '$2y$10$CV7Z0LjuslVjgPt5AL3l7uaZ4NUtPKBjvoRf0tIc5htQf7QGMiQY6', 'owner')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Data Kategori
INSERT INTO `categories` (`id`, `nama_kategori`, `deskripsi`) VALUES
(1, 'Teknologi & Komputer', 'Buku pemrograman, kecerdasan buatan, jaringan, dan rekayasa perangkat lunak'),
(2, 'Sains & Matematika', 'Buku fisika, kimia, biologi, kalkulus, dan sains populer'),
(3, 'Sastra & Fiksi', 'Novel fiksi, antologi cerpen, puisi, dan karya sastra klasik'),
(4, 'Bisnis & Manajemen', 'Ekonomi, kepemimpinan, strategi pemasaran, dan keuangan bisnis'),
(5, 'Sejarah & Budaya', 'Sejarah peradaban, antropologi, sosiologi, dan kebudayaan nusantara')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Data Rak
INSERT INTO `shelves` (`id`, `nama_rak`, `lokasi`, `keterangan`) VALUES
(1, 'Rak TI-01', 'Lantai 1 - Sayap Barat', 'Kategori Teknologi dan Pemrograman Komputer'),
(2, 'Rak SA-01', 'Lantai 1 - Sayap Timur', 'Kategori Sains, Eksperimen, dan Matematika Dasar'),
(3, 'Rak SF-01', 'Lantai 2 - Ruang Membaca Barat', 'Kategori Sastra, Novel, dan Fiksi Populer'),
(4, 'Rak BM-01', 'Lantai 2 - Sayap Timur', 'Kategori Bisnis, Startup, dan Manajemen Keuangan')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Data Buku
INSERT INTO `books` (`id`, `kode_buku`, `judul`, `penulis`, `penerbit`, `tahun_terbit`, `isbn`, `kategori_id`, `rak_id`, `stok`, `status`, `cover`) VALUES
(1, 'BK-2026-0001', 'Clean Code: A Handbook of Agile Software Craftsmanship', 'Robert C. Martin', 'Prentice Hall', 2008, '978-0132350884', 1, 1, 4, 'tersedia', NULL),
(2, 'BK-2026-0002', 'Design Patterns: Elements of Reusable Object-Oriented Software', 'Erich Gamma et al.', 'Addison-Wesley', 1994, '978-0201633610', 1, 1, 2, 'tersedia', NULL),
(3, 'BK-2026-0003', 'A Brief History of Time', 'Stephen Hawking', 'Bantam Books', 1988, '978-0553380163', 2, 2, 5, 'tersedia', NULL),
(4, 'BK-2026-0004', 'Cosmos', 'Carl Sagan', 'Random House', 1980, '978-0345331359', 2, 2, 3, 'tersedia', NULL),
(5, 'BK-2026-0005', 'Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', 2005, '978-9793062792', 3, 3, 6, 'tersedia', NULL),
(6, 'BK-2026-0006', 'Bumi Manusia', 'Pramoedya Ananta Toer', 'Hasta Mitra', 1980, '978-9799731234', 3, 3, 3, 'tersedia', NULL),
(7, 'BK-2026-0007', 'The Lean Startup', 'Eric Ries', 'Crown Business', 2011, '978-0307887894', 4, 4, 4, 'tersedia', NULL),
(8, 'BK-2026-0008', 'Nusantara: Sejarah Indonesia', 'Bernard H.M. Vlekke', 'Komunitas Bambu', 2008, '978-9793731339', 5, 4, 5, 'tersedia', NULL)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Data Anggota
INSERT INTO `members` (`id`, `kode_anggota`, `nama`, `no_identitas`, `kontak`, `alamat`, `status`) VALUES
(1, 'MBR-0001', 'Ahmad Fauzi', '3201019508890001', '081234567890', 'Jl. Merdeka No. 45, Jakarta Pusat', 'aktif'),
(2, 'MBR-0002', 'Siti Rahmawati', '3201019602920002', '081398765432', 'Jl. Sudirman No. 12, Bandung', 'aktif'),
(3, 'MBR-0003', 'Budi Santoso', '3201019011880003', '082155667788', 'Jl. Diponegoro No. 78, Surabaya', 'aktif'),
(4, 'MBR-0004', 'Dewi Lestari', '3201019804950004', '085712344321', 'Jl. Malioboro No. 23, Yogyakarta', 'aktif'),
(5, 'MBR-0005', 'Rian Hidayat', '3201019409910005', '087899887766', 'Jl. Gatot Subroto No. 90, Semarang', 'aktif')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Data Transaksi Peminjaman
-- Catatan:
-- 1. Transaksi 1: Sudah dikembalikan tepat waktu (denda 0)
-- 2. Transaksi 2: Sedang dipinjam, jatuh tempo masih mendatang (aktif)
-- 3. Transaksi 3: Sedang dipinjam, telah lewat jatuh tempo (untuk demo perhitungan denda otomatis)
INSERT INTO `loans` (`id`, `kode_transaksi`, `member_id`, `book_id`, `user_id`, `tgl_pinjam`, `tgl_jatuh_tempo`, `tgl_kembali`, `status`, `denda`, `catatan`) VALUES
(1, 'TRX-20260901-0001', 1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'dikembalikan', 0.00, 'Buku dikembalikan dalam kondisi prima'),
(2, 'TRX-20260910-0002', 2, 5, 1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), NULL, 'dipinjam', 0.00, 'Peminjaman reguler anggota'),
(3, 'TRX-20260905-0003', 3, 2, 1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), NULL, 'terlambat', 0.00, 'Peminjaman melewati jatuh tempo 3 hari')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Data Awal Log Aktivitas
INSERT INTO `activity_logs` (`id`, `user_id`, `aksi`, `detail`, `ip_address`, `created_at`) VALUES
(1, 1, 'SYSTEM_INIT', 'Sistem Inventaris Perpustakaan berhasil diinstalasi dengan database default', '127.0.0.1', NOW()),
(2, 1, 'TRANSAKSI_PINJAM', 'Peminjaman buku Clean Code oleh Ahmad Fauzi (TRX-20260901-0001)', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 14 DAY)),
(3, 1, 'TRANSAKSI_KEMBALI', 'Pengembalian buku Clean Code oleh Ahmad Fauzi tanpa denda', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(4, 1, 'TRANSAKSI_PINJAM', 'Peminjaman buku Laskar Pelangi oleh Siti Rahmawati (TRX-20260910-0002)', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 1, 'TRANSAKSI_PINJAM', 'Peminjaman buku Design Patterns oleh Budi Santoso (TRX-20260905-0003)', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 10 DAY))
ON DUPLICATE KEY UPDATE `id`=`id`;
