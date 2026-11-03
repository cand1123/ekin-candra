# Spesifikasi: Redesign Total Sistem Laporan ASN

## 1. Problem Statement

Aplikasi Sistem Laporan ASN saat ini menggunakan arsitektur PHP procedural dengan seluruh file bercampur di root directory. Tampilan UI menggunakan sistem class `md-*` yang sudah ada, namun user menginginkan perombakan total agar terlihat seperti aplikasi enterprise modern (referensi: Microsoft Admin, Linear, Notion, Shopify Admin, Stripe Dashboard) tanpa ciri khas desain AI (gradient berlebihan, glassmorphism, blob, shadow kuat).

Selain itu, struktur project perlu dirapikan agar mengikuti standar aplikasi web modern dengan pemisahan logic, tampilan, asset, konfigurasi, dan storage.

## 2. Users & Goals

**User utama:** ASN (Novian Candra Kurniawan, Guru Ahli Pertama) yang menggunakan aplikasi untuk pelaporan kinerja harian.

**Goals:**
- Dashboard yang informatif dan mudah dipindai (scannable)
- Proses input laporan harian yang cepat (< 30 detik)
- Navigasi yang jelas antar modul
- Tampilan yang profesional dan nyaman digunakan sehari-hari
- Struktur project yang rapi untuk memudahkan pengembangan selanjutnya

## 3. Functional Requirements

### 3.1 UI/UX Dashboard

**FR-1: Layout Dashboard Modern**
- Sidebar kiri fixed dengan menu: Dashboard, Rekap Kegiatan, Kegiatan, Rencana Aksi, Setting RHK, Keluar
- Top navigation bar dengan: logo + nama app, search bar, notifikasi, avatar user + dropdown
- Konten utama: profile card, 4 statistik card, progress section, aktivitas terbaru, quick action
- Bottom navigation untuk mobile (5 item: Beranda, Laporan, Kalender, Analitik, Profil)
- FAB (Floating Action Button) "Tambah Laporan" di mobile

**FR-2: Header / Topbar**
- Kiri: Logo + "Sistem Laporan ASN" + subtitle "Pelaporan Kinerja Harian"
- Tengah: Search bar placeholder "Cari menu, kegiatan, atau laporan..."
- Kanan: icon notifikasi, avatar bulat, nama user, jabatan, dropdown (Profil, Keluar)

**FR-3: Profile Card (Welcome Card)**
- Avatar inisial (NC)
- Nama lengkap
- NIP + Jabatan
- Tanggal aktif (badge)
- Link ke halaman profil

**FR-4: Statistik Card (4 kartu)**
- Total Laporan (icon journal, angka, progress bar, status badge)
- Kegiatan Selesai (icon check, angka, progress bar, status)
- Dokumen Bukti (icon file, angka, progress bar, status)
- Bulan Ini (icon calendar, angka, progress bar, status)

**FR-5: Progress Section**
- Kegiatan selesai: 17/22 dengan progress bar
- Dokumen bukti: 26/30 dengan progress bar

**FR-6: Aktivitas Terbaru**
- Timeline sederhana 5 entri terakhir
- Item: icon, judul aktivitas, waktu, status badge

**FR-7: Quick Action**
- Tombol besar "Tambah Laporan Baru" (primary navy solid)
- Subtitle: "Isi kegiatan hari ini dalam kurang dari 30 detik"
- Icon plus + arrow right

**FR-8: Modal Form Tambah Laporan**
- Section: Informasi Kegiatan (Tanggal, Rencana Aksi)
- Section: Detail Aktivitas (Kegiatan Harian textarea)
- Section: Waktu Pelaksanaan (Jam Mulai, Jam Selesai sejajar 50/50)
- Section: Dokumentasi (drag & drop upload area, format JPG/PNG, maks 5MB, maks 5 file)
- Footer: Batal + Simpan Data

### 3.2 Struktur Project

**FR-9: Struktur Folder Baru**
```
asn-candra/
├── app/
│   ├── controllers/
│   ├── models/
│   └── helpers/
├── config/
│   ├── database.php
│   ├── app.php
│   └── constants.php
├── public/
│   ├── index.php
│   └── assets/
│       ├── css/
│       ├── js/
│       └── images/
├── views/
│   ├── layouts/
│   ├── dashboard/
│   ├── laporan/
│   └── auth/
├── storage/
│   ├── uploads/
│   ├── logs/
│   └── generated/
├── database/
│   └── database.sql
├── vendor/
├── composer.json
└── composer.lock
```

**FR-10: Pemindahan File**
- Semua PHP page (dashboard.php, login.php, dll) dipindahkan ke `views/` sesuai kategori
- Entry point thin wrapper di `public/` atau root (sesuai konfigurasi web server)
- Asset dipindahkan ke `public/assets/`
- Upload dipindahkan ke `storage/uploads/`
- database.sql dipindahkan ke `database/`
- includes/ dipindahkan ke `app/helpers/` atau `views/layouts/`

**FR-11: Path & Routing**
- Semua `require_once`/`include` diperbarui ke path baru
- Asset loading (CSS/JS) diperbarui ke `public/assets/`
- Upload path diperbarui ke `storage/uploads/`
- Semua link navigasi diperbarui

### 3.3 Fitur Existing yang Harus Tetap Berjalan

**FR-12: Fitur Wajib Dipertahankan**
- Login (local + Google OAuth)
- Database connection (local + hosting auto-detect)
- CRUD Laporan Harian
- Upload foto bukti (maks 5 file, JPG/PNG)
- Generate PDF (DomPDF)
- Generate Word (PHPWord template)
- RHK (Rencana Hasil Kerja) bulanan
- Rencana Aksi
- Rekap Bulanan
- Profil user

## 4. Non-Functional Requirements

**NFR-1: Desain Anti-AI**
- Tidak ada gradient berlebihan, glassmorphism, blob, shadow kuat
- Warna solid: Primary #2563EB / #1E40AF, Neutral #F8FAFC / #FFFFFF / #64748B / #0F172A
- Status: hijau soft, orange soft, merah soft
- Icon 16-20px, konsisten
- Spacing natural (tidak semua card sama ukuran)
- Fokus informasi, bukan dekorasi

**NFR-2: Tipografi**
- Font: Inter (sans-serif)
- Heading: Semi-bold
- Body: Regular
- Tidak ada text besar berlebihan seperti landing page

**NFR-3: Responsive**
- Desktop: sidebar + konten penuh
- Tablet: sidebar collapse (toggle)
- Mobile: bottom navigation + FAB, layout stack

**NFR-4: Performa**
- Asset dengan cache-busting `?v=time()`
- Tidak ada blocking render yang tidak perlu

**NFR-5: Kompatibilitas**
- PHP 7.3.33 (tidak boleh pakai fitur PHP 8+)
- MySQL (mysqli)
- Bootstrap 5.3
- DomPDF 1.2.2

## 5. Constraints

- PHP 7.3.33: gunakan `strpos() !== false` bukan `str_contains()`
- Tidak boleh menghapus fitur existing
- Semua path include/require harus diperbaiki
- Upload foto harus tetap berfungsi setelah pindah folder
- Generate PDF/Word harus tetap berfungsi
- Google OAuth login harus tetap berfungsi

## 6. Assumptions

- Web server (Laragon) menggunakan project root sebagai document root. Jika public/ dijadikan web root, perlu konfigurasi. Untuk kompatibilitas, entry point bisa tetap di root sebagai thin wrapper.
- User memiliki akses untuk membuat folder dan memindahkan file.
- Database `laporan_asn` sudah ada dengan data existing.

## 7. Acceptance Criteria

### Rule (dapat diverifikasi objektif)

**AC-R1:** Tidak ada file PHP page (kecuali entry point thin wrapper) langsung di root directory.

**AC-R2:** Struktur folder `app/`, `config/`, `public/assets/`, `views/`, `storage/`, `database/` tersedia.

**AC-R3:** Login local berhasil menuju dashboard.

**AC-R4:** Login Google OAuth berhasil menuju dashboard.

**AC-R5:** Tambah laporan harian + upload bukti berhasil tersimpan.

**AC-R6:** Generate PDF laporan bulanan berhasil di-download.

**AC-R7:** Generate Word laporan berhasil di-download.

**AC-R8:** CRUD RHK berfungsi (tambah, edit, hapus).

**AC-R9:** CRUD Rencana Aksi berfungsi.

**AC-R10:** Rekap bulanan menampilkan data dengan benar.

**AC-R11:** Semua link navigasi di sidebar berfungsi (tidak 404).

**AC-R12:** Semua asset CSS/JS ter-load tanpa 404.

**AC-R13:** Upload bukti tersimpan di `storage/uploads/` dan dapat diakses.

**AC-R14:** Tidak ada error PHP fatal di semua halaman.

**AC-R15:** Modal tambah laporan memiliki 4 section: Informasi Kegiatan, Detail Aktivitas, Waktu Pelaksanaan, Dokumentasi.

**AC-R16:** Sidebar memiliki 6 menu: Dashboard, Rekap Kegiatan, Kegiatan, Rencana Aksi, Setting RHK, Keluar.

**AC-R17:** Topbar memiliki logo, search bar, notifikasi, avatar user.

**AC-R18:** Dashboard memiliki 4 statistik card.

### Rubric (evaluasi kualitas)

**AC-RB1: Kualitas Visual Dashboard (skala 0-3)**
- 0: Masih terlihat seperti template AI (gradient, blob, glassmorphism, shadow kuat)
- 1: Sebagian besar bersih tapi masih ada elemen dekoratif berlebih
- 2: Bersih, profesional, solid colors, spacing natural, informasi jelas
- 3: Setara kualitas Linear/Stripe/Shopify Admin — natural, human-designed, fokus usability
- Threshold: ≥ 2

**AC-RB2: Konsistensi Sistem UI (skala 0-3)**
- 0: Setiap halaman punya style berbeda, tidak ada konsistensi
- 1: Sebagian konsisten, beberapa halaman masih style lama
- 2: Konsisten di semua halaman utama, ada minor inkonsistensi
- 3: Konsisten penuh di seluruh aplikasi, reusable components jelas
- Threshold: ≥ 2

**AC-RB3: Responsiveness (skala 0-3)**
- 0: Layout rusak di mobile/tablet
- 1: Sebagian elemen overflow atau tidak nyaman di mobile
- 2: Layout menyesuaikan, mobile usable dengan bottom nav + FAB
- 3: Smooth di semua breakpoint, tidak ada horizontal scroll
- Threshold: ≥ 2

**AC-RB4: Kerapian Struktur Project (skala 0-3)**
- 0: File masih bercampur di root
- 1: Sebagian file dipindahkan tapi masih ada di root
- 2: Struktur sesuai target, semua path diperbaiki
- 3: Struktur enterprise-grade, autoload, config terpisah, mudah dikembangkan
- Threshold: ≥ 2

**AC-RB5: Usability Form Laporan (skala 0-3)**
- 0: Form membingungkan, banyak field tidak jelas
- 1: Form berfungsi tapi layout acak
- 2: Form terstruktur per section, field jelas, validasi ada
- 3: Form insting, bisa diisi < 30 detik, drag-drop upload, feedback jelas
- Threshold: ≥ 2
