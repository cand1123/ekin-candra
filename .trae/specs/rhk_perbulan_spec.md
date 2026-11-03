# Fitur RHK Per-Bulan & Rencana Aksi Terpisah
# Spesifikasi Kebutuhan

## Masalah
Saat ini tabel `rencana_hasil_kerja` memungkinkan pembuatan RHK untuk "Semua Bulan" (`bulan = 0`), sehingga tidak ada batasan periode. Selain itu, field `indikator` (Rencana Aksi) menyatu dengan record RHK, sehingga tidak ada kontrol kapan rencana aksi dapat dibuat atau diedit. Pengguna ingin RHK hanya dibuat per bulan dan rencana aksi dipisahkan menjadi entitas tersendiri yang hanya dapat diedit pada bulan berjalan.

## Tujuan
1. RHK **hanya dapat dibuat per bulan** (bulan wajib 1–12, tanpa opsi "Semua Bulan").
2. Rencana Aksi dipisahkan ke tabel `rencana_aksi` tersendiri, satu RHK dapat memiliki rencana aksi per bulan.
3. Rencana Aksi **hanya dapat dibuat/diedit pada bulan berjalan** (edit window = bulan & tahun saat ini).
4. Dashboard memiliki fitur **Generate RHK Bulan Ini** untuk membuat RHK bulan berjalan dengan cepat.

## Non-Tujuan
- Tidak mengubah struktur tabel `laporan_harian`, `bukti_kegiatan`, `users`.
- Tidak mengubah format cetak PDF secara drastis (hanya menyesuaikan sumber teks indikator).
- Tidak menghapus kolom `indikator` lama dari `rencana_hasil_kerja` (dipakai sebagai fallback/kompatibilitas data lama).
- Tidak mengubah alur upload bukti dan logika laporan harian.

## Model Data
- `rencana_hasil_kerja` (existing, disesuaikan): `id, user_id, nama_rhk, indikator (legacy fallback), target, output, bulan (1-12, NOT NULL, tanpa default 0), tahun, created_at`.
- `rencana_aksi` (BARU): `id, rhk_id, bulan (1-12), tahun, indikator (TEXT NOT NULL), created_at, updated_at`.
  - Unique key `(rhk_id, bulan, tahun)` — satu rencana aksi per RHK per bulan.
  - Foreign key `rhk_id` → `rencana_hasil_kerja(id)` ON DELETE CASCADE.

## Kebutuhan Fungsional
- **FR-1**: Form tambah/edit RHK tidak menampilkan opsi "Semua Bulan"; dropdown bulan hanya berisi Januari–Desember (1–12).
- **FR-2**: Field `indikator` dihapus dari form RHK (dikelola di tabel `rencana_aksi`). Form RHK hanya berisi `nama_rhk, target, output, bulan, tahun`.
- **FR-3**: Setiap baris RHK di daftar (`rhk.php` & `pengaturan.php`) memiliki tombol **"Kelola Rencana Aksi"**.
- **FR-4**: Modal kelola rencana aksi menampilkan: (a) rencana aksi bulan berjalan (input textarea, dapat diedit/disimpan), (b) riwayat rencana aksi bulan-bulan lalu (read-only, tidak bisa diedit).
- **FR-5**: Validasi server-side: rencana aksi hanya dapat disimpan jika `bulan == date('n')` dan `tahun == date('Y')`. Jika tidak, kembalikan error tanpa menyimpan.
- **FR-6**: Query dropdown "Rencana Aksi" di form laporan harian (`laporan_harian.php`, `edit_laporan.php`, `dashboard.php`, `ajax_rhk_options.php`) mengambil `rencana_aksi.indikator` untuk bulan laporan, dengan fallback ke `rencana_hasil_kerja.indikator` jika tidak ada record `rencana_aksi`.
- **FR-7**: Dashboard memiliki section **"Generate RHK Bulan Ini"** yang membuka modal tambah RHK dengan `bulan` dan `tahun` otomatis terisi bulan & tahun berjalan.
- **FR-8**: Filter daftar RHK tetap mendukung pemilihan bulan (1–12) dan tahun.

## Kebutuhan Non-Fungsional
- **NFR-1**: Kompatibel PHP 7.3.33 (tidak boleh `str_contains`, arrow function, typed property).
- **NFR-2**: Cache-busting `?v=<?= time() ?>` pada aset CSS/JS.
- **NFR-3**: Auto-submit pada filter bulan/tahun.
- **NFR-4**: Gaya desain mengikuti sistem `md-*` yang ada (flat, navy solid `#1E40AF`, tanpa gradient/glassmorphism).
- **NFR-5**: Semua query menggunakan prepared statement.
- **NFR-6**: Data lama (RHK dengan `bulan=0` atau `indikator` di tabel RHK) tetap dapat ditampilkan sebagai fallback.

## Batasan
- Runtime PHP 7.3.33.
- MySQL/MariaDB (prepared statement via mysqli).
- Tidak menambah library eksternal.

## Asumsi
- Kolom `indikator` pada `rencana_hasil_kerja` dipertahankan sebagai fallback untuk data lama dan untuk kompatibilitas.
- "Generate RHK" di dashboard berarti membuka modal tambah RHK dengan bulan/tahun otomatis (bukan auto-generate dari template).
- Edit window rencana aksi = bulan & tahun saat ini; rencana aksi bulan lalu tetap bisa dilihat (read-only).

## Kriteria Penerimaan
### AC-1: RHK wajib bulan spesifik (tanpa "Semua Bulan")
- **Type**: `rule`
- **Given**: Pengguna membuka form tambah/edit RHK.
- **When**: Melihat dropdown Bulan.
- **Then**: Hanya ada opsi Januari–Desember (nilai 1–12), tidak ada "Semua Bulan".
- **Pass Condition**: Tidak ada `<option value="0">Semua Bulan</option>` di form RHK.
- **Evidence**: File `rhk.php` & `pengaturan.php` form bulan.

### AC-2: Form RHK tidak memiliki field indikator
- **Type**: `rule`
- **Given**: Form tambah/edit RHK.
- **When**: Melihat field form.
- **Then**: Tidak ada textarea/input `indikator` di form RHK.
- **Pass Condition**: Tidak ada `name="indikator"` di form RHK.
- **Evidence**: File `rhk.php`, `pengaturan.php`.

### AC-3: Tabel rencana_aksi tersedia & migrasi berjalan
- **Type**: `rule`
- **Given**: Aplikasi dijalankan.
- **When**: Memuat halaman RHK.
- **Then**: Tabel `rencana_aksi` otomatis dibuat (CREATE TABLE IF NOT EXISTS) dengan kolom yang benar.
- **Pass Condition**: `DESCRIBE rencana_aksi` mengembalikan kolom `id, rhk_id, bulan, tahun, indikator, created_at, updated_at`.
- **Evidence**: File migrasi di `rhk.php` (auto-migration pattern) atau SQL file.

### AC-4: Tombol "Kelola Rencana Aksi" ada di setiap baris RHK
- **Type**: `rule`
- **Given**: Daftar RHK memiliki data.
- **When**: Melihat setiap baris RHK.
- **Then**: Ada tombol/link "Kelola Rencana Aksi" yang membuka modal.
- **Pass Condition**: Setiap item RHK memiliki elemen dengan teks "Kelola Rencana Aksi".
- **Evidence**: File `rhk.php`, `pengaturan.php`.

### AC-5: Modal rencana aksi — bulan berjalan dapat diedit, bulan lalu read-only
- **Type**: `rule`
- **Given**: Pengguna membuka modal kelola rencana aksi.
- **When**: Melihat modal.
- **Then**: Bagian bulan berjalan memiliki textarea aktif; bagian riwayat bulan lalu hanya menampilkan teks (disabled/readonly).
- **Pass Condition**: Textarea bulan berjalan `disabled` jika bukan bulan berjalan; riwayat hanya teks.
- **Evidence**: File `rhk.php` modal rencana aksi.

### AC-6: Validasi server-side edit window rencana aksi
- **Type**: `rule`
- **Given**: User mengirim form simpan rencana aksi.
- **When**: `bulan` atau `tahun` pada form != bulan/tahun saat ini.
- **Then**: Server menolak penyimpanan dan mengembalikan pesan error.
- **Pass Condition**: Kode PHP memeriksa `if ($bulan !== (int)date('n') || $tahun !== (int)date('Y'))` sebelum INSERT/UPDATE.
- **Evidence**: File handler simpan rencana aksi.

### AC-7: Dropdown Rencana Aksi di laporan mengambil dari rencana_aksi
- **Type**: `rule`
- **Given**: Form tambah laporan harian.
- **When**: Mengambil daftar Rencana Aksi.
- **Then**: Query menggabungkan `rencana_aksi` (LEFT JOIN) dan memakai `COALESCE(ra.indikator, r.indikator)`.
- **Pass Condition**: SQL menggunakan `LEFT JOIN rencana_aksi ra ON ra.rhk_id = r.id AND ra.bulan = ? AND ra.tahun = ?` dan `COALESCE(ra.indikator, r.indikator)`.
- **Evidence**: File `laporan_harian.php`, `edit_laporan.php`, `dashboard.php`, `ajax_rhk_options.php`.

### AC-8: Dashboard punya section "Generate RHK Bulan Ini"
- **Type**: `rule`
- **Given**: Pengguna membuka dashboard.
- **When**: Melihat area menu/CTA.
- **Then**: Ada tombol/section "Generate RHK Bulan Ini" yang membuka modal tambah RHK dengan bulan/tahun bulan berjalan.
- **Pass Condition**: Ada elemen dengan teks "Generate RHK" atau "RHK Bulan Ini" di dashboard yang memicu modal.
- **Evidence**: File `dashboard.php`.

### AC-9: Semua file PHP yang diubah lulus lint PHP 7.3.33
- **Type**: `rule`
- **Given**: Selesai implementasi.
- **When**: Menjalankan `php -l` pada file yang diubah.
- **Then**: Semua exit code 0.
- **Pass Condition**: Output "No syntax errors detected".
- **Evidence**: Output command lint.
