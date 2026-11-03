# Tasks: Redesign Total Sistem Laporan ASN

## Task 1: Setup Struktur Folder & Bootstrap

**Priority:** high
**Status:** pending
**AC Coverage:** AC-R1, AC-R2, AC-RB4

### Deskripsi
Buat struktur folder baru sesuai target enterprise. Buat bootstrap system yang memuat config, database, session, dan helpers secara konsisten.

### Sub-tasks
1.1 Buat folder: `app/helpers`, `app/controllers`, `app/models`, `config`, `public/assets/css`, `public/assets/js`, `public/assets/images`, `views/layouts`, `views/dashboard`, `views/laporan`, `views/auth`, `storage/uploads/laporan`, `storage/uploads/bukti`, `storage/logs`, `storage/generated/pdf`, `storage/generated/word`, `database`
1.2 Pindahkan `config/database.php` → tetap di `config/database.php` (sudah benar), tambah `config/app.php` dan `config/constants.php`
1.3 Pindahkan `includes/session.php` → `app/helpers/session.php`
1.4 Pindahkan `includes/header.php` → `views/layouts/header.php`, `includes/footer.php` → `views/layouts/footer.php`
1.5 Pindahkan `assets/css/style.css` → `public/assets/css/style.css`, `assets/css/responsive.css` → `public/assets/css/responsive.css`
1.6 Pindahkan `assets/js/app.js` → `public/assets/js/app.js`, `assets/js/validation.js` → `public/assets/js/validation.js`
1.7 Pindahkan `assets/img/` → `public/assets/images/`
1.8 Pindahkan `database.sql` → `database/database.sql`
1.9 Pindahkan `format-laporan/` → `storage/templates/` (template Word)
1.10 Pindahkan isi `uploads/bukti/` → `storage/uploads/bukti/` (jangan hapus data existing)
1.11 Buat `app/helpers/bootstrap.php` yang memuat: config/database, session, helpers
1.12 Buat `.htaccess` di root untuk protect folder app/, config/, views/, storage/, database/ (Deny from all)
1.13 Update `index.php` (root) sebagai entry point yang meroute ke login/dashboard

### Test Requirements
- **Rule:** Struktur folder sesuai target ada di filesystem (AC-R2)
- **Rule:** `app/helpers/bootstrap.php` berhasil include database + session tanpa error (AC-R14)
- **Rule:** Folder sensitif (app, config, views, storage) tidak bisa diakses langsung via browser (AC-R1)
- **Rubric (AC-RB4):** Struktur rapi, mudah dikembangkan. Skala 0-3, threshold ≥2

---

## Task 2: Redesign Sistem CSS (Design System)

**Priority:** high
**Status:** pending
**AC Coverage:** AC-RB1, AC-RB2

### Deskripsi
Buat design system CSS baru yang modern, enterprise, anti-AI. Ganti class `md-*` menjadi sistem yang lebih bersih dan reusable. Pertahankan kompatibilitas dengan halaman existing secara bertahap.

### Sub-tasks
2.1 Buat `public/assets/css/style.css` baru dengan:
  - CSS variables untuk warna (primary #1E40AF, neutral #F8FAFC, text #0F172A, muted #64748B)
  - Reset base (font Inter, box-sizing, dll)
  - Layout: sidebar, topbar, main-content, footer
  - Components: stat-card, progress-bar, badge, btn, modal, form-field, timeline, list
  - Tidak ada gradient, glassmorphism, blob, shadow kuat
  - Border-radius kecil (max 8px), border solid tipis
2.2 Buat `public/assets/css/responsive.css` dengan breakpoint:
  - Desktop (≥992px): sidebar fixed + full layout
  - Tablet (768-991px): sidebar collapse toggle
  - Mobile (<768px): bottom nav + FAB, stack layout
2.3 Pastikan icon 16-20px, spacing 8px grid, typography Inter
2.4 Hapus class dekoratif yang tidak fungsional

### Test Requirements
- **Rule:** Tidak ada CSS gradient/glassmorphism/shadow > 0.15 opacity di file (AC-RB1)
- **Rule:** Font-family Inter ter-set di body (AC-RB1)
- **Rule:** Border-radius maksimum 8px di semua komponen (AC-RB1)
- **Rubric (AC-RB1):** Kualitas visual. Skala 0-3, threshold ≥2
- **Rubric (AC-RB2):** Konsistensi. Skala 0-3, threshold ≥2

---

## Task 3: Redesign Layout (Header, Sidebar, Footer)

**Priority:** high
**Status:** pending
**AC Coverage:** AC-R11, AC-R16, AC-R17, AC-RB2

### Deskripsi
Redesign `views/layouts/header.php` dan `footer.php` dengan struktur baru: topbar dengan logo+search+notifikasi+avatar, sidebar dengan 6 menu, footer minimal.

### Sub-tasks
3.1 Rewrite `views/layouts/header.php`:
  - Topbar: kiri (toggle + logo "Sistem Laporan ASN" + subtitle), tengah (search bar), kanan (notifikasi bell, avatar bulat, nama+jabatan, dropdown Profil/Keluar)
  - Sidebar: 6 menu dengan icon bootstrap sederhana, active state jelas, hover ringan
  - User data diambil dari database (nama, nip, jabatan, foto)
  - Inisial avatar otomatis dari nama
3.2 Rewrite `views/layouts/footer.php`:
  - Footer minimal: copyright + link (Privasi, Ketentuan, Bantuan)
  - Load Bootstrap JS + app.js + validation.js
3.3 Pastikan active menu otomatis berdasarkan halaman saat ini
3.4 Update semua path asset ke `public/assets/`

### Test Requirements
- **Rule:** Sidebar menampilkan 6 menu: Dashboard, Rekap Kegiatan, Kegiatan, Rencana Aksi, Setting RHK, Keluar (AC-R16)
- **Rule:** Topbar memiliki logo, search bar, notifikasi, avatar user (AC-R17)
- **Rule:** Semua link sidebar tidak 404 (AC-R11)
- **Rubric (AC-RB2):** Konsistensi layout. Skala 0-3, threshold ≥2

---

## Task 4: Redesign Dashboard Page

**Priority:** high
**Status:** pending
**AC Coverage:** AC-R3, AC-R4, AC-R18, AC-RB1, AC-RB2, AC-RB3

### Deskripsi
Redesign `views/dashboard/index.php` (dari dashboard.php) dengan semua section: profile card, 4 statistik card, progress section, aktivitas terbaru timeline, quick action CTA, recent reports list, FAB, bottom nav.

### Sub-tasks
4.1 Pindahkan `dashboard.php` → `views/dashboard/index.php`
4.2 Buat entry point thin wrapper `dashboard.php` di root yang require bootstrap + view
4.3 Profile card: avatar inisial, nama, NIP, jabatan, tanggal aktif badge, link profil
4.4 4 Statistik card: Total Laporan, Kegiatan Selesai, Dokumen Bukti, Bulan Ini — masing-masing dengan icon kecil, angka besar, progress bar, status badge
4.5 Progress section: Kegiatan selesai (17/22), Dokumen bukti (26/30) dengan progress bar
4.6 Aktivitas terbaru: timeline 5 entri terakhir dengan icon, judul, waktu, status
4.7 Quick action: CTA "Tambah Laporan Baru" dengan subtitle
4.8 Laporan terbaru: list 5 entri dengan status badge, action buttons
4.9 FAB + bottom nav untuk mobile
4.10 Pertahankan semua query database existing (total laporan, bulan ini, kegiatan, dokumen, recent)

### Test Requirements
- **Rule:** Dashboard menampilkan 4 statistik card (AC-R18)
- **Rule:** Login berhasil menuju dashboard tanpa error (AC-R3, AC-R4)
- **Rule:** Query database existing tetap berjalan (data muncul) (AC-R14)
- **Rubric (AC-RB1):** Visual quality. Skala 0-3, threshold ≥2
- **Rubric (AC-RB3):** Responsive. Skala 0-3, threshold ≥2

---

## Task 5: Redesign Modal Form Tambah Laporan

**Priority:** high
**Status:** pending
**AC Coverage:** AC-R5, AC-R15, AC-RB5

### Deskripsi
Redesign modal tambah laporan dengan 4 section yang jelas dan upload area modern.

### Sub-tasks
5.1 Modal dengan 4 section:
  - Informasi Kegiatan: Tanggal (date input), Rencana Aksi (select dinamis)
  - Detail Aktivitas: Kegiatan Harian (textarea)
  - Waktu Pelaksanaan: Jam Mulai, Jam Selesai (sejajar 50/50)
  - Dokumentasi: Upload area dengan drag-drop, icon, format info, preview
5.2 Upload area: drag & drop zone, icon upload, teks "Seret file ke sini atau klik untuk memilih", info format JPG/PNG, maks 5 file, 5MB
5.3 Image preview thumbnail sebelum submit
5.4 Validasi field wajib dengan alert merah sebelum submit
5.5 Footer: Batal (ghost) + Simpan Data (primary)
5.6 Pertahankan logic simpan_laporan.php existing

### Test Requirements
- **Rule:** Modal memiliki 4 section: Informasi Kegiatan, Detail Aktivitas, Waktu Pelaksanaan, Dokumentasi (AC-R15)
- **Rule:** Upload bukti berhasil tersimpan (AC-R5)
- **Rule:** Validasi field wajib menampilkan alert jika kosong (AC-RB5)
- **Rubric (AC-RB5):** Usability form. Skala 0-3, threshold ≥2

---

## Task 6: Refactor Halaman Laporan Harian & Edit

**Priority:** high
**Status:** pending
**AC Coverage:** AC-R5, AC-R11, AC-R14

### Deskripsi
Pindahkan dan redesign halaman laporan_harian.php dan edit_laporan.php ke struktur baru.

### Sub-tasks
6.1 Pindahkan `laporan_harian.php` → `views/laporan/index.php`, buat wrapper root `laporan_harian.php`
6.2 Pindahkan `edit_laporan.php` → `views/laporan/edit.php`, buat wrapper root `edit_laporan.php`
6.3 Update header/footer include ke `views/layouts/`
6.4 Update asset path ke `public/assets/`
6.5 Update upload path ke `storage/uploads/bukti/`
6.6 Update simpan_laporan.php path upload
6.7 Redesign tampilan list laporan dengan styling baru
6.8 Pertahankan semua CRUD logic

### Test Requirements
- **Rule:** Halaman laporan_harian bisa diakses tanpa error (AC-R11)
- **Rule:** Edit laporan berfungsi (AC-R14)
- **Rule:** Upload bukti di laporan tersimpan di storage/uploads/bukti (AC-R5)

---

## Task 7: Refactor Halaman RHK & Rencana Aksi

**Priority:** high
**Status:** pending
**AC Coverage:** AC-R8, AC-R9, AC-R11, AC-R14

### Deskripsi
Pindahkan dan redesign rhk.php dan pengaturan.php.

### Sub-tasks
7.1 Pindahkan `rhk.php` → `views/rhk/index.php`, wrapper `rhk.php`
7.2 Pindahkan `pengaturan.php` → `views/rhk/pengaturan.php`, wrapper `pengaturan.php`
7.3 Update includes dan asset paths
7.4 Redesign tampilan dengan styling baru (tabel, form, modal)
7.5 Pertahankan logic RHK bulanan dan rencana aksi
7.6 Pertahankan modal konfirmasi simpan RHK

### Test Requirements
- **Rule:** CRUD RHK berfungsi (AC-R8)
- **Rule:** CRUD Rencana Aksi berfungsi (AC-R9)
- **Rule:** Halaman bisa diakses tanpa error (AC-R11, AC-R14)

---

## Task 8: Refactor Rekap Bulanan & Cetak PDF/Word

**Priority:** high
**Status:** pending
**AC Coverage:** AC-R6, AC-R7, AC-R10, AC-R11

### Deskripsi
Pindahkan dan pastikan rekap_bulanan.php, cetak_pdf.php, cetak_laporan_harian.php, isi_dari_format_word.php berfungsi.

### Sub-tasks
8.1 Pindahkan `rekap_bulanan.php` → `views/laporan/rekap.php`, wrapper `rekap_bulanan.php`
8.2 Pindahkan `cetak_pdf.php` → `views/laporan/cetak_pdf.php`, wrapper `cetak_pdf.php`
8.3 Pindahkan `cetak_laporan_harian.php` → `views/laporan/cetak_harian.php`, wrapper `cetak_laporan_harian.php`
8.4 Pindahkan `isi_dari_format_word.php` → `app/helpers/word_generator.php` (logic), wrapper `isi_dari_format_word.php`
8.5 Update path template Word ke `storage/templates/`
8.6 Update DomPDF include path
8.7 Redesign tampilan rekap dengan styling baru
8.8 Pertahankan semua logic generate PDF/Word

### Test Requirements
- **Rule:** Generate PDF berhasil di-download (AC-R6)
- **Rule:** Generate Word berhasil di-download (AC-R7)
- **Rule:** Rekap bulanan menampilkan data benar (AC-R10)
- **Rule:** Halaman bisa diakses (AC-R11)

---

## Task 9: Refactor Auth & Profil & Utility Files

**Priority:** high
**Status:** pending
**AC Coverage:** AC-R3, AC-R4, AC-R11, AC-R14

### Deskripsi
Pindahkan login.php, logout.php, profil.php, google_verify.php, ajax_rhk_options.php, get_bukti.php, hapus_laporan.php, hapus_bukti.php.

### Sub-tasks
9.1 Pindahkan `login.php` → `views/auth/login.php`, wrapper `login.php`
9.2 Pindahkan `logout.php` → `views/auth/logout.php`, wrapper `logout.php`
9.3 Pindahkan `profil.php` → `views/auth/profil.php`, wrapper `profil.php`
9.4 Pindahkan `google_verify.php` → `app/controllers/GoogleVerify.php`, wrapper `google_verify.php`
9.5 Pindahkan `ajax_rhk_options.php` → `app/controllers/AjaxRhkOptions.php`, wrapper `ajax_rhk_options.php`
9.6 Pindahkan `get_bukti.php` → `app/controllers/GetBukti.php`, wrapper `get_bukti.php`
9.7 Pindahkan `hapus_laporan.php` → `app/controllers/HapusLaporan.php`, wrapper `hapus_laporan.php`
9.8 Pindahkan `hapus_bukti.php` → `app/controllers/HapusBukti.php`, wrapper `hapus_bukti.php`
9.9 Update semua includes dan paths
9.10 Pertahankan Google OAuth logic

### Test Requirements
- **Rule:** Login local berhasil (AC-R3)
- **Rule:** Login Google berhasil (AC-R4)
- **Rule:** Logout berfungsi (AC-R14)
- **Rule:** Profil bisa diakses (AC-R11)
- **Rule:** AJAX RHK options berfungsi (AC-R14)

---

## Task 10: Update Path & Asset di Semua File

**Priority:** high
**Status:** pending
**AC Coverage:** AC-R12, AC-R13, AC-R14

### Deskripsi
Pastikan semua path include/require, asset CSS/JS, upload, dan link navigasi diperbarui di SEMUA file.

### Sub-tasks
10.1 Ganti semua `require_once 'includes/...'` → `require_once 'app/helpers/...'` atau `views/layouts/...`
10.2 Ganti semua `assets/css/` → `public/assets/css/`
10.3 Ganti semua `assets/js/` → `public/assets/js/`
10.4 Ganti semua `assets/img/` → `public/assets/images/`
10.5 Ganti semua `uploads/bukti/` → `storage/uploads/bukti/`
10.6 Ganti semua `format-laporan/` → `storage/templates/`
10.7 Update link navigasi di sidebar (jika ada perubahan)
10.8 Update form action URLs (simpan_laporan.php, dll)
10.9 Jalankan grep untuk memastikan tidak ada path lama yang tersisa

### Test Requirements
- **Rule:** Semua asset CSS/JS ter-load tanpa 404 (AC-R12)
- **Rule:** Upload bukti tersimpan di storage/uploads/bukti dan bisa diakses (AC-R13)
- **Rule:** Tidak ada error PHP karena path salah (AC-R14)

---

## Task 11: Testing & Verifikasi Akhir

**Priority:** high
**Status:** pending
**AC Coverage:** Semua AC

### Deskripsi
Verifikasi semua fitur berjalan setelah refactoring.

### Sub-tasks
11.1 Test login local
11.2 Test login Google (jika tersedia)
11.3 Test dashboard (data muncul, tidak error)
11.4 Test tambah laporan + upload bukti
11.5 Test edit laporan
11.6 Test hapus laporan
11.7 Test RHK CRUD
11.8 Test rencana aksi CRUD
11.9 Test rekap bulanan
11.10 Test cetak PDF
11.11 Test cetak Word
11.12 Test profil
11.13 Test responsive (mobile, tablet, desktop)
11.14 Cek error log PHP
11.15 Cek tidak ada file PHP page di root selain wrapper

### Test Requirements
- **Rule:** Semua AC-R (rule) lulus
- **Rubric:** Semua AC-RB (rubric) ≥ threshold
