# Rencana Implementasi: Fitur RHK Per-Bulan & Rencana Aksi Terpisah

## Task 1: Migrasi Database — Buat Tabel `rencana_aksi` & Sesuaikan `bulan` RHK
- **Status**: `pending`
- **Priority**: high
- **Depends On**: None
- **Description**:
  - Di `rhk.php` (pattern auto-migration seperti kolom `bulan` yang sudah ada), tambahkan blok `CREATE TABLE IF NOT EXISTS rencana_aksi` dengan kolom: `id INT PK AUTO_INCREMENT, rhk_id INT NOT NULL, bulan TINYINT NOT NULL, tahun YEAR NOT NULL, indikator TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
  - Tambahkan `UNIQUE KEY uk_ra_rhk_bulan_tahun (rhk_id, bulan, tahun)` dan `INDEX idx_ra_rhk (rhk_id)`, foreign key `rhk_id` → `rencana_hasil_kerja(id) ON DELETE CASCADE`.
  - Tambahkan blok `ALTER TABLE rencana_hasil_kerja MODIFY COLUMN bulan TINYINT NOT NULL DEFAULT 1` agar default bukan 0 (opsional, tetap aman dengan validasi).
  - Defensive: bungkus setiap query migrasi dengan cek `DESCRIBE` terlebih dahulu agar tidak error jika sudah ada.
- **Acceptance Criteria Addressed**: AC-3
- **Test Requirements**:
  - `rule` TR-1.1: Tabel `rencana_aksi` terbentuk otomatis saat halaman RHK dimuat. Evidence: `DESCRIBE rencana_aksi` mengembalikan kolom sesuai.
  - `rule` TR-1.2: PHP lint `rhk.php` exit code 0.

---

## Task 2: Sesuaikan Form & List RHK (rhk.php) — Hapus "Semua Bulan" & `indikator`, Tambah Tombol "Kelola Rencana Aksi"
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - Hapus opsi `value="0"` "Semua Bulan" dari dropdown bulan di form modal RHK dan filter. Loop `for ($m = 1; $m <= 12; $m++)`.
  - Hapus field `indikator` (textarea) dari form modal RHK. Form hanya: `nama_rhk, bulan, tahun, target, output`.
  - Ubah INSERT/UPDATE query RHK: tidak lagi menyertakan `indikator` (tetap boleh simpan `indikator` kosong untuk kompatibilitas, atau hapus dari query).
  - Ubah validasi: `indikator` tidak wajib lagi (karena dikelola di rencana_aksi).
  - Di list RHK, tambahkan tombol **"Kelola Rencana Aksi"** di setiap item (samping tombol Edit/Hapus).
  - Tampilkan status rencana aksi bulan berjalan di badge (misal: "Belum Ada Aksi" jika belum ada rencana_aksi bulan ini, "Aksi Aktif" jika sudah).
  - Query list RHK: LEFT JOIN `rencana_aksi` untuk bulan filter guna menampilkan indikator bulan tersebut sebagai fallback display.
- **Acceptance Criteria Addressed**: AC-1, AC-2, AC-4 (sebagian)
- **Test Requirements**:
  - `rule` TR-2.1: Tidak ada `<option value="0">Semua Bulan</option>` di `rhk.php`.
  - `rule` TR-2.2: Tidak ada `name="indikator"` di form RHK modal `rhk.php`.
  - `rule` TR-2.3: Ada elemen dengan teks "Kelola Rencana Aksi" di setiap baris RHK.
  - `rule` TR-2.4: PHP lint `rhk.php` exit code 0.

---

## Task 3: Buat Modal & Handler "Kelola Rencana Aksi" (rhk.php)
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 2
- **Description**:
  - Tambah modal `#rencanaAksiModal` di `rhk.php`:
    - Header: "Kelola Rencana Aksi — [nama RHK]".
    - Bagian **Bulan Berjalan**: textarea `indikator` + tombol simpan. Hanya aktif jika `bulan == date('n') && tahun == date('Y')`; jika tidak, tampilkan pesan "Hanya dapat diedit pada bulan berjalan" dan textarea disabled.
    - Bagian **Riwayat Bulan Lalu**: daftar rencana aksi bulan-bulan sebelumnya (read-only, teks saja).
  - JS: tombol "Kelola Rencana Aksi" mengirim `rhk_id`, `nama_rhk`, `bulan`, `tahun` ke modal; fetch data rencana aksi via AJAX atau langsung inject PHP.
  - Handler POST simpan rencana aksi (di `rhk.php` atau endpoint baru `simpan_rencana_aksi.php`):
    - Validasi: `bulan == date('n') && tahun == date('Y')`. Jika tidak, error.
    - UPSERT: `INSERT ... ON DUPLICATE KEY UPDATE indikator=VALUES(indikator), updated_at=NOW()` memanfaatkan unique key `(rhk_id, bulan, tahun)`.
    - Redirect kembali dengan flash message.
  - Untuk menghindari file baru, handler bisa ditempatkan di `rhk.php` dengan deteksi `$_POST['simpan_rencana_aksi']`.
- **Acceptance Criteria Addressed**: AC-4, AC-5, AC-6
- **Test Requirements**:
  - `rule` TR-3.1: Ada modal dengan id `rencanaAksiModal` berisi textarea indikator.
  - `rule` TR-3.2: Textarea bulan berjalan `disabled` jika bukan bulan berjalan.
  - `rule` TR-3.3: Kode handler memeriksa `if ($bulan !== (int)date('n') || $tahun !== (int)date('Y'))` sebelum simpan.
  - `rule` TR-3.4: PHP lint `rhk.php` exit code 0.

---

## Task 4: Sesuaikan `pengaturan.php` — Hapus "Semua Bulan", Sinkronisasi dengan rhk.php
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 2
- **Description**:
  - Hapus opsi "Semua Bulan" dari dropdown filter dan form modal tambah RHK di `pengaturan.php`.
  - Hapus field `indikator` dari form modal (sudah tidak ada, hanya hidden `indikator=""` — tetap aman).
  - Query list RHK: LEFT JOIN `rencana_aksi` untuk bulan filter, tampilkan status rencana aksi.
  - Tambahkan tombol "Kelola Rencana Aksi" di setiap baris (link ke `rhk.php?aksi=kelola_aksi&id=...` atau buka modal).
  - Pastikan quick-add RHK dari pengaturan tetap berfungsi (tanpa indikator).
- **Acceptance Criteria Addressed**: AC-1, AC-2, AC-4
- **Test Requirements**:
  - `rule` TR-4.1: Tidak ada `<option value="0">Semua Bulan</option>` di `pengaturan.php`.
  - `rule` TR-4.2: PHP lint `pengaturan.php` exit code 0.

---

## Task 5: Sesuaikan Dropdown Rencana Aksi di Form Laporan (laporan_harian.php, edit_laporan.php, dashboard.php, ajax_rhk_options.php)
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - Ubah query pengambilan RHK untuk dropdown: tambahkan `LEFT JOIN rencana_aksi ra ON ra.rhk_id = r.id AND ra.bulan = ? AND ra.tahun = ?`.
  - Ganti label dari `r.indikator` menjadi `COALESCE(NULLIF(ra.indikator, ''), r.indikator, r.nama_rhk)` agar mengambil rencana aksi bulan laporan, fallback ke indikator lama, lalu nama RHK.
  - File yang diubah:
    - `laporan_harian.php` (aksi=tambah & modal)
    - `edit_laporan.php`
    - `dashboard.php` (modal tambah laporan)
    - `ajax_rhk_options.php`
  - Pastikan binding parameter bulan/tahun sesuai (bulan dari tanggal laporan).
- **Acceptance Criteria Addressed**: AC-7
- **Test Requirements**:
  - `rule` TR-5.1: Query di keempat file menggunakan `LEFT JOIN rencana_aksi` dan `COALESCE(..., r.indikator, r.nama_rhk)`.
  - `rule` TR-5.2: PHP lint semua file exit code 0.

---

## Task 6: Tambah Section "Generate RHK Bulan Ini" di Dashboard
- **Status**: `pending`
- **Priority**: medium
- **Depends On**: Task 2
- **Description**:
  - Di `dashboard.php`, tambahkan section CTA "Generate RHK Bulan Ini" (menggunakan class `md-cta` konsisten).
  - Tombol membuka modal tambah RHK (bisa reuse modal dari `pengaturan.php` atau link ke `rhk.php?aksi=tambah&bulan=X&tahun=Y`).
  - Bulan & tahun otomatis diisi bulan & tahun berjalan.
  - Modal bisa dibuat inline di dashboard (mirip modal tambah laporan) atau redirect ke rhk.php. Pilih pendekatan reuse: link ke `rhk.php?aksi=tambah&bulan=<bulan ini>&tahun=<tahun ini>` agar modal otomatis terbuka (menggunakan auto-open pattern yang sudah ada).
- **Acceptance Criteria Addressed**: AC-8
- **Test Requirements**:
  - `rule` TR-6.1: Ada elemen dengan teks "Generate RHK" atau "RHK Bulan Ini" di dashboard.
  - `rule` TR-6.2: Link/button mengarah ke `rhk.php?aksi=tambah` dengan parameter bulan & tahun berjalan.
  - `rule` TR-6.3: PHP lint `dashboard.php` exit code 0.

---

## Task 7: Validasi Lint PHP 7.3.33 untuk Semua File yang Diubah
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1–6
- **Description**:
  - Jalankan `php -l` untuk: `rhk.php`, `pengaturan.php`, `laporan_harian.php`, `edit_laporan.php`, `dashboard.php`, `ajax_rhk_options.php`.
  - Perbaiki jika ada error.
- **Acceptance Criteria Addressed**: AC-9
- **Test Requirements**:
  - `rule` TR-7.1: Semua file exit code 0.
