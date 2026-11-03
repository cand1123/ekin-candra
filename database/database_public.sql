-- ============================================================
-- SISTEM LAPORAN ASN PRIBADI
-- Versi    : 2.0 (public/shared hosting)
-- Catatan  : File ini untuk server public:
--            - tanpa CREATE DATABASE / USE
--            - tanpa akun admin bawaan
--            - import ke database yang sudah dibuat dari panel hosting
-- ============================================================

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
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABEL: laporan_harian
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
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_lh_rhk
        FOREIGN KEY (rhk_id)
        REFERENCES rencana_hasil_kerja(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABEL: rencana_aksi
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
