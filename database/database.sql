-- ============================================================
-- SISTEM LAPORAN ASN PRIBADI
-- Database : laporan_asn
-- Versi    : 2.0 (local/dev bootstrap lengkap)
-- Catatan  : File ini untuk lokal/dev:
--            - membuat database dan memilih database aktif
--            - menyertakan akun admin bawaan untuk bootstrap awal
--            - sudah mencakup skema terbaru aplikasi
-- ============================================================

CREATE DATABASE IF NOT EXISTS laporan_asn
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE laporan_asn;

-- ------------------------------------------------------------
-- TABEL: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    google_id VARCHAR(255) DEFAULT NULL,
    auth_provider ENUM('local','google') NOT NULL DEFAULT 'local',
    nip VARCHAR(50) NOT NULL,
    jabatan VARCHAR(255) NOT NULL,
    unit_kerja VARCHAR(255) NOT NULL,
    instansi VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) DEFAULT NULL,
    foto VARCHAR(512) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_email (email),
    INDEX idx_users_google_id (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users
    (nama, nip, jabatan, unit_kerja, instansi, username, password)
VALUES
    ('NOVIAN CANDRA KURNIAWAN',
     '198104082010011015',
     'Guru Ahli Pertama',
     'SEKOLAH RAKYAT TERINTEGRASI 1 KALIMANTAN BARAT',
     'Pemerintah Provinsi Kalimantan Barat',
     'admin',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE username = username;

-- ------------------------------------------------------------
-- TABEL: rencana_hasil_kerja
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rencana_hasil_kerja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nama_rhk VARCHAR(255) NOT NULL,
    indikator TEXT NOT NULL,
    target VARCHAR(255) NOT NULL,
    output VARCHAR(255) NOT NULL,
    tahun YEAR NOT NULL,
    bulan TINYINT NOT NULL DEFAULT 0 COMMENT '0=Semua Bulan, 1-12=Januari-Desember',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rhk_user (user_id),
    INDEX idx_rhk_tahun (user_id, tahun),
    INDEX idx_rhk_bulan_tahun (user_id, bulan, tahun),
    CONSTRAINT fk_rhk_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABEL: laporan_harian
-- rhk_id     : NULLABLE (user belum punya RHK / RHK dihapus)
-- hasil      : NULLABLE  (tidak wajib diisi pada form)
-- jam_mulai  / jam_selesai : ada default value
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS laporan_harian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rhk_id INT NULL DEFAULT NULL,
    tanggal DATE NOT NULL,
    uraian_kegiatan TEXT NOT NULL,
    jam_mulai TIME NOT NULL DEFAULT '07:30:00',
    jam_selesai TIME NOT NULL DEFAULT '16:00:00',
    jumlah_kegiatan INT NOT NULL DEFAULT 1,
    hasil TEXT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lh_user (user_id),
    INDEX idx_lh_tanggal (tanggal),
    INDEX idx_lh_user_tanggal (user_id, tanggal),
    INDEX idx_lh_rhk (rhk_id),
    CONSTRAINT fk_lh_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_lh_rhk
        FOREIGN KEY (rhk_id)
        REFERENCES rencana_hasil_kerja(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABEL: rencana_aksi
-- 1 RHK dapat memiliki 1 rencana aksi per bulan per tahun
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rencana_aksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rhk_id INT NOT NULL,
    bulan TINYINT NOT NULL,
    tahun YEAR NOT NULL,
    indikator TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ra_rhk_bulan_tahun (rhk_id, bulan, tahun),
    INDEX idx_ra_rhk (rhk_id),
    CONSTRAINT fk_ra_rhk
        FOREIGN KEY (rhk_id)
        REFERENCES rencana_hasil_kerja(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABEL: rhk_bulanan_capaian
-- Menyimpan output/capaian per RHK per bulan per user
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rhk_bulanan_capaian (
    user_id INT NOT NULL,
    rhk_id INT NOT NULL,
    bulan TINYINT UNSIGNED NOT NULL,
    tahun SMALLINT UNSIGNED NOT NULL,
    output_hasil TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, rhk_id, bulan, tahun),
    KEY idx_capaian_user_tahun (user_id, tahun, bulan),
    KEY idx_capaian_rhk (rhk_id),
    CONSTRAINT fk_capaian_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_capaian_rhk
        FOREIGN KEY (rhk_id)
        REFERENCES rencana_hasil_kerja(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABEL: bukti_kegiatan
-- Kolom ditambah:
--   user_id     -> defense in depth ownership check
--   mime_type   -> 'image/jpeg' / 'image/png'
--   ukuran_file -> bytes
--   created_at  -> audit trail + sorting urutan upload
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bukti_kegiatan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    laporan_id INT NOT NULL,
    user_id INT NOT NULL,
    nama_file VARCHAR(512) NOT NULL,
    path_file VARCHAR(512) NOT NULL,
    mime_type VARCHAR(50) DEFAULT NULL,
    ukuran_file INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bk_laporan (laporan_id),
    INDEX idx_bk_user (user_id),
    INDEX idx_bk_laporan_user (laporan_id, user_id),
    CONSTRAINT fk_bk_laporan
        FOREIGN KEY (laporan_id)
        REFERENCES laporan_harian(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_bk_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  MIGRASI (jika database sudah berisi data lama)
--  Jalankan per block berikut di menu SQL phpMyAdmin secara
--  BERURUT. JANGAN jalankan semua sekaligus jika CONSTRAINT
--  FOREIGN KEY LAMA belum di-drop terlebih dahulu.
--  Jika nama FK tidak sesuai, jalankan:
--     SHOW CREATE TABLE nama_tabel;
--  untuk melihat nama FK yang aktif.
-- ============================================================

-- ------------------------------------------------------------
-- Migrasi 1 — Tabel laporan_harian
-- ------------------------------------------------------------
/*
ALTER TABLE laporan_harian
    DROP FOREIGN KEY laporan_harian_ibfk_2,
    CHANGE COLUMN rhk_id rhk_id INT NULL DEFAULT NULL,
    CHANGE COLUMN hasil hasil TEXT NULL DEFAULT NULL,
    CHANGE COLUMN jam_mulai jam_mulai TIME NOT NULL DEFAULT '07:30:00',
    CHANGE COLUMN jam_selesai jam_selesai TIME NOT NULL DEFAULT '16:00:00',
    ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ADD INDEX idx_lh_user_tanggal (user_id, tanggal),
    ADD CONSTRAINT fk_lh_rhk_new
        FOREIGN KEY (rhk_id)
        REFERENCES rencana_hasil_kerja(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;
*/

-- ------------------------------------------------------------
-- Migrasi 2 — Tabel rencana_hasil_kerja
-- ------------------------------------------------------------
/*
ALTER TABLE rencana_hasil_kerja
    ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ADD INDEX idx_rhk_tahun (user_id, tahun);
*/

-- ------------------------------------------------------------
-- Migrasi 3 — Tabel bukti_kegiatan
-- ------------------------------------------------------------
/*
ALTER TABLE bukti_kegiatan
    ADD COLUMN user_id INT NOT NULL AFTER laporan_id,
    ADD COLUMN mime_type VARCHAR(50) DEFAULT NULL AFTER path_file,
    ADD COLUMN ukuran_file INT UNSIGNED DEFAULT NULL AFTER mime_type,
    ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CHANGE COLUMN nama_file nama_file VARCHAR(512) NOT NULL,
    CHANGE COLUMN path_file path_file VARCHAR(512) NOT NULL,
    ADD INDEX idx_bk_user (user_id),
    ADD INDEX idx_bk_laporan_user (laporan_id, user_id),
    ADD CONSTRAINT fk_bk_user_new
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE;
*/

-- ------------------------------------------------------------
-- Migrasi 4 — Backfill user_id pada bukti_kegiatan
-- ------------------------------------------------------------
/*
UPDATE bukti_kegiatan bk
    INNER JOIN laporan_harian lh ON bk.laporan_id = lh.id
    SET bk.user_id = lh.user_id
    WHERE bk.user_id = 0 OR bk.user_id IS NULL;
*/

-- ------------------------------------------------------------
-- Migrasi 5 — Tabel users (panjang kolom foto)
-- ------------------------------------------------------------
/*
ALTER TABLE users
    CHANGE COLUMN foto foto VARCHAR(512) DEFAULT NULL;
*/

-- ------------------------------------------------------------
-- Migrasi 6 — Tabel rencana_hasil_kerja (kolom bulan)
-- ------------------------------------------------------------
/*
ALTER TABLE rencana_hasil_kerja
    ADD COLUMN bulan TINYINT NOT NULL DEFAULT 0
        COMMENT '0=Semua Bulan, 1-12=Januari-Desember'
        AFTER tahun,
    ADD INDEX idx_rhk_bulan_tahun (user_id, bulan, tahun);
*/

-- ------------------------------------------------------------
-- Migrasi 7 — Tabel users (Google login)
-- ------------------------------------------------------------
/*
ALTER TABLE users
    ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER nama,
    ADD COLUMN google_id VARCHAR(255) DEFAULT NULL AFTER email,
    ADD COLUMN auth_provider ENUM('local','google') NOT NULL DEFAULT 'local' AFTER google_id,
    MODIFY COLUMN password VARCHAR(255) DEFAULT NULL,
    ADD INDEX idx_users_email (email),
    ADD INDEX idx_users_google_id (google_id);
*/

-- ------------------------------------------------------------
-- Migrasi 8 — Tabel rencana_aksi
-- ------------------------------------------------------------
/*
CREATE TABLE IF NOT EXISTS rencana_aksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rhk_id INT NOT NULL,
    bulan TINYINT NOT NULL,
    tahun YEAR NOT NULL,
    indikator TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ra_rhk_bulan_tahun (rhk_id, bulan, tahun),
    INDEX idx_ra_rhk (rhk_id),
    CONSTRAINT fk_ra_rhk
        FOREIGN KEY (rhk_id)
        REFERENCES rencana_hasil_kerja(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/

-- ------------------------------------------------------------
-- Migrasi 9 — Tabel rhk_bulanan_capaian
-- ------------------------------------------------------------
/*
CREATE TABLE IF NOT EXISTS rhk_bulanan_capaian (
    user_id INT NOT NULL,
    rhk_id INT NOT NULL,
    bulan TINYINT UNSIGNED NOT NULL,
    tahun SMALLINT UNSIGNED NOT NULL,
    output_hasil TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, rhk_id, bulan, tahun),
    KEY idx_capaian_user_tahun (user_id, tahun, bulan),
    KEY idx_capaian_rhk (rhk_id),
    CONSTRAINT fk_capaian_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_capaian_rhk
        FOREIGN KEY (rhk_id)
        REFERENCES rencana_hasil_kerja(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/
