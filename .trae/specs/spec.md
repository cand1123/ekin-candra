# Redesign Total Pemerintahan Formal - Sistem Laporan ASN
# Product Requirements Document

## Overview
- **Summary**: Merombak total UI Sistem Laporan ASN dari arah "Minimalis Editorial (Substack / serif)" ke arah desain **Formal Pemerintahan Modern**, dengan fokus utama pada halaman **Laporan Harian** (clean row-based table, sans-serif, flat, tanpa card berlebihan), penambahan sub-branding Kementerian Pendidikan, Kebudayaan, Riset dan Teknologi, logo kementerian, ikon notifikasi, menu **Pengaturan** baru di sidebar, dan footer formal.
- **Purpose**: Memenuhi desain spesifikasi terbaru (`MD_Design_Sistem_Laporan_ASN.md`) yang menuntut nuansa resmi, profesional, dan berorientasi data untuk lingkup kementerian. Menghilangkan elemen dekoratif editorial (serif Book Antiqua, small-caps eyebrow, accent left-border pull-quote, badge alpha-transparan, radius besar) dan diganti dengan gaya sans-serif yang bersih, formal, flat-border, dengan **Dark Navy #0F2747** sebagai warna accent sekunder.
- **Target Users**: Novian Candra Kurniawan (Guru Ahli Pertama, Kementerian Pendidikan, Kebudayaan, Riset dan Teknologi) dan pengguna ASN lainnya yang membutuhkan antarmuka laporan formal, rapi, dan mudah dibaca untuk administrasi pemerintahan.

## Goals
- Ganti total sistem font dari **Book Antiqua (serif)** ke **Inter / Poppins / Roboto (sans-serif)** agar sesuai dengan standar UI aplikasi pemerintahan modern.
- Terapkan branding resmi: **Logo kementerian** di Topbar + sub-judul "Kementerian Pendidikan, Kebudayaan, Riset dan Teknologi" di area branding.
- Tambahkan **8 menu sidebar** (Dashboard, Laporan Harian, Data Indikator, Rekap Bulanan, Dokumen, Profil Saya, **Pengaturan**, Keluar) sesuai urutan spesifikasi.
- Rombak halaman **Laporan Harian** menjadi fokus utama: remove card wrapper filter + statistik, gunakan table row-based flat (tanpa card per row), padding luas, border tipis, kolom aksi **hanya icon tanpa tombol besar** (Edit=Biru #2563EB, Cetak=Navy #0F2747, Hapus=Merah #EF4444).
- Terapkan palet warna baru: tambahkan **Dark Navy #0F2747** dan update variabel danger ke **#EF4444**, tanpa mengubah Primary #2563EB dan Background #F8FAFC yang sudah sesuai.
- Kurangi elemen card/tidak penting di seluruh aplikasi (Laporan Harian, Dashboard) sesuai prinsip UX "Kurangi elemen visual tidak penting, data laporan fokus utama".
- Tambahkan komponen **Ikon Notifikasi** di Topbar sebelah Profil.
- Tambahkan **Footer Formal** berisi copyright © 2026 Sistem Laporan ASN + 3 link (Privasi, Ketentuan, Bantuan).
- Pertahankan semua constraint teknis yang berlaku (PHP 7.3.33, cache-busting `?v=time()`, global `tanggal_id()` DD MMMM, onclick global JS, format data SQL yang sudah berjalan, dll).

## Non-Goals
- Tidak mengubah struktur database / query SQL / logika bisnis PHP (hanya ubah CSS visual dan struktur HTML shell/UI).
- Tidak mengubah format cetak PDF / cetak laporan harian (file `cetak_pdf.php` & `cetak_laporan_harian.php` tetap tanpa perubahan UI, kecuali jika ada referensi CSS global yang bentrok — cukup @media print preserve).
- Tidak membuat halaman **Pengaturan** secara fungsional (hanya menu link placeholder yang menuju `#` atau halaman kosong, karena spec tidak mendeskripsikan fitur Pengaturan).
- Tidak mengubah logic modal Tambah Laporan / Preview Laporan / Hapus / Upload Bukti (hanya ganti styling CSS-nya ke formal, struktur HTML form dan event handler JS tetap sama).
- Tidak mengubah responsive behavior (Desktop full sidebar, Tablet compact 84px, Mobile off-canvas hamburger) — hanya styling visual yang diubah, nama class state `.sidebar-collapsed` dan `.sidebar-open` dipertahankan agar app.js tetap jalan.

## Background & Context
1. **Arah desain SEBELUMNYA (ARCHIVED)**: "Minimalis Editorial Substack Style" — font serif Book Antiqua, accent-left 3px signature, small-caps uppercase label, badge border alpha-transparan. Arah ini **DITOLAK / DI-SUPERSEDE** oleh user yang secara eksplisit memberikan file spesifikasi baru `MD_Design_Sistem_Laporan_ASN.md` dan pesan "Robak total desain UI nya".
2. **Spesifikasi BARU (ACTIVE)**: File [MD_Design_Sistem_Laporan_ASN.md](file:///c:/laragon/www/asn-candra/MD_Design_Sistem_Laporan_ASN.md) menjadi ACUAN TUNGGAL untuk arah desain pemerintahan formal.
3. **User spesifik (dari spec)**: Nama = Novian Candra Kurniawan, Jabatan = Guru Ahli Pertama, Instansi = Kementerian Pendidikan, Kebudayaan, Riset dan Teknologi.
4. **Technical constraints permanen (dari project memory, tidak boleh dilanggar)**:
   - Runtime PHP 7.3.33 (tidak boleh pakai arrow function, typed property, null coalescing assignment).
   - Format tanggal **DD MMMM** via `tanggal_id()` global di `includes/header.php:7-17`.
   - Onclick inline data aman via `json_encode(...) + htmlspecialchars(..., ENT_QUOTES)`.
   - Fungsi JS global diekspos ke `window.*` untuk menghindari ReferenceError.
   - CSS / JS include wajib cache-busting `?v=<?= time() ?>`.
   - Constraint responsive 3-tier (sidebar width 260px > 1200px, compact 84px 992-1199px, off-canvas <992px).

## Functional Requirements
- **FR-1**: Topbar (Header) menampilkan 4 komponen berurutan: (1) Logo kementerian / icon formal + nama sistem "Sistem Laporan ASN" + sub-branding "Kementerian Pendidikan, Kebudayaan, Riset dan Teknologi" (ukuran kecil di bawah nama); (2) Hamburger toggle untuk sidebar; (3) Ikon Notifikasi (bell, tanpa jumlah badge untuk saat ini); (4) Profil pengguna dropdown (nama, jabatan, avatar, menu Profil Saya / Keluar).
- **FR-2**: Sidebar memiliki **URUTAN 8 MENU WAJIB** (tidak boleh diubah): Dashboard → Laporan Harian → Data Indikator → Rekap Bulanan → Dokumen → Profil Saya → **Pengaturan** (BARU, placeholder) → Keluar. Menu Pengaturan: link `pengaturan.php` (placeholder halaman sederhana) atau `#` dengan alert jika file tidak dibuat.
- **FR-3**: Halaman `laporan_harian.php` (mode list):
  - Header halaman: Judul "Laporan Harian" + deskripsi "Kelola data laporan kegiatan harian Anda." + Button utama "+ Tambah Laporan" (masih memanggil modal yang sama).
  - Area Filter: **TIDAK DIBUNGKUS CARD** (flat div, tanpa border/shadow), komponen filter = Pilihan Bulan (select) + Pilihan Tahun (select) + Tombol Cari + Tombol Cetak. Input flat, border abu tipis, button biru formal.
  - **Statistik Ringkas (Total Laporan, Total Kegiatan)** jika tetap ada: TIDAK DIBUNGKUS CARD dan TIDAK ADA icon gradient / border-left. Cukup teks label kecil + angka bold, rata kiri, ukuran kompak.
  - **Area Table**: TIDAK DIBUNGKUS CARD (table flat langsung), **TANPA CARD-HEADER / CARD-FOOTER wrapper**. Judul section data laporan cukup teks h5 di atas table.
  - **Struktur Table (7 kolom URUT SESUAI SPEC)**: Tanggal → Indikator → Uraian Kegiatan → Waktu → Qty → Bukti → Aksi.
  - **Kolom Aksi**: HANYA ICON (TANPA TOMBOL BESAR / SQUARE BUTTON BORDER), 3 aksi berurutan:
    - Edit: icon `bi-pencil-square` warna **Primary #2563EB**.
    - Cetak: icon `bi-printer-fill` warna **Dark Navy #0F2747**.
    - Hapus: icon `bi-trash3-fill` warna **Danger #EF4444**.
    - Ketiga icon diberi jarak gap cukup, hover = opacity lebih tinggi + underline tipis, tooltip title tetap.
- **FR-4**: Dashboard: Mengurangi penggunaan card. 4 Stat Card (Total Laporan / Bulan Ini / Kegiatan Selesai / Dokumen) bisa tetap tapi styling flat tanpa accent-left / border signature editorial. Card Profil Pengguna di Dashboard & Card "Laporan Terbaru" bisa tetap tapi tanpa shadow tebal / radius besar.
- **FR-5**: Footer (di `includes/footer.php`, **SEBELUM** tag penutup script): Container lebar penuh, background putih / border atas tipis, teks rata tengah: © 2026 Sistem Laporan ASN. Di samping / bawahnya link **Privasi** · **Ketentuan** · **Bantuan** (link placeholder ke `#`).
- **FR-6**: Login Page: Ganti font ke sans-serif, hapus elemen small-caps editorial, ganti ke arah formal (nama sistem + sub branding kementerian di bawah logo, card flat border 1px radius kompak 12px).

## Non-Functional Requirements
- **NFR-1 (Typography)**: Seluruh elemen aplikasi (termasuk input, button, small, badge) menggunakan **sans-serif (Inter, fallback Poppins / Roboto)**. `!important` jika perlu agar override Bootstrap serif. Heading Bold, body Regular, tidak ada letter-spacing negatif besar, tidak ada small-caps uppercase dekoratif kecuali label badge yang memang butuh.
- **NFR-2 (Color Consistency)**: Wajib menggunakan palet sesuai spec:
  - Primary: `#2563EB`
  - Dark Navy (BARU): `#0F2747` (wajib ada sebagai CSS Variable, dipakai icon cetak, link formal).
  - Background: `#F8FAFC`
  - Text: `#1E293B`
  - Border: `#E2E8F0`
  - Danger: `#EF4444` (update dari #DC2626)
- **NFR-3 (Minimal Cards, Max Data)**: Hanya gunakan `<div class="card">` jika benar-benar membutuhkan pembungkus yang jelas (contoh: wrapper form Tambah Laporan mode non-modal, jika masih ada). Untuk area filter, tabel list laporan, dan area statistik ringkas: **hindari class card**, pakai flat div dengan padding + border tipis jika perlu.
- **NFR-4 (Readability)**: Table Laporan Harian wajib memiliki padding cell luas, border antar baris tipis 1px, hover row ringan (bg biru muda sangat lembut `#EFF6FF`), font size body 15px, line-height 1.65 (bukan 1.75 editorial yang terlalu longgar).
- **NFR-5 (Responsive Preserved)**: Semua perilaku responsive 3-tier dari redesign sebelumnya (Desktop >1200px sidebar 260px, Tablet 992-1199px sidebar compact 84px icon-only, Mobile <992px off-canvas hamburger + backdrop) **TETAP BERLAKU, tidak boleh rusak**, hanya CSS visual yang diganti ke gaya formal.
- **NFR-6 (Cache Busting Preserved)**: Semua link `<link rel="stylesheet">` dan `<script src>` wajib tetap memiliki `?v=<?= time() ?>` agar CSS/JS baru tidak di-cache browser saat redesign diterapkan.
- **NFR-7 (PHP 7.3 Compatible)**: Seluruh sintaks PHP yang ditambahkan / diubah **wajib lulus PHP Lint** dengan runtime `C:\laragon\bin\php\php-7.3.33-Win32-VC15-x64\php.exe -l <file>`. Tidak boleh arrow function, null coalescing assignment, typed property.
- **NFR-8 (JS Global Alias Preserved)**: Fungsi JS `window.openPreviewLaporan`, `window.confirmDelete`, `window.preparePrint`, `window.setupImagePreview`, `window.loadBukti` **TETAP EKSPOS ke scope global** (tidak boleh dihapus / diubah scope), karena onclick inline di seluruh HTML memanggilnya.

## Constraints
- **Technical**:
  1. Runtime PHP 7.3.33 (satu-satunya yang didukung).
  2. Struktur 3-Zone App Shell (Topbar sticky + Sidebar + Main Content) **TIDAK BOLEH DIUBAH nama class utamanya** (.topbar, .app-wrapper, .sidebar, .main-content), dan class state **TIDAK BOLEH DIUBAH NAMANYA** (.sidebar-collapsed untuk desktop, .sidebar-open untuk mobile) agar JS di `app.js` yang menangani toggle sidebar **TETAP BERJALAN TANPA PERUBAHAN SEKALI PUN** (JS preserved — non-goal).
  3. Global helper `tanggal_id()` di `includes/header.php:7-17` **HARUS TETAP JADI SATU-SATUNYA SUMBER** format tanggal DD MMMM (tidak boleh duplikat function di file lain, sudah fix sebelumnya).
  4. Tidak menambah library CSS/JS baru selain Bootstrap 5.3.0 dan Bootstrap Icons 1.11.0 yang sudah include.
- **Business**:
  1. Urutan 8 menu sidebar **WAJIB SESUAI FR-2** (tidak boleh ditukar, menu Pengaturan harus ada sebelum Keluar).
  2. Palet warna NFR-2 **WAJIB SESUAI MD spec**, khususnya Dark Navy #0F2747 sebagai accent baru dan Danger #EF4444.
  3. Kolom Aksi di Laporan Harian **WAJIB hanya icon tanpa tombol besar**, warna persis sesuai spec (Edit biru, Cetak NAVY, Hapus merah) — tidak boleh border pada tombol aksi.
  4. Sub-branding **"Kementerian Pendidikan, Kebudayaan, Riset dan Teknologi"** **WAJIB MUNCUL** setidaknya di (a) Topbar (bawah nama sistem), dan (b) Login page (bawah nama sistem).
- **Dependencies**: Bootstrap 5.3.0 (CDN), Bootstrap Icons 1.11.0 (CDN). Tidak perlu install dependensi baru via Composer / npm.

## Assumptions
1. Karena spec tidak mendeskripsikan isi halaman Pengaturan, maka menu "Pengaturan" di sidebar akan menuju placeholder `pengaturan.php` yang isinya header + card sederhana "Fitur Pengaturan akan segera hadir" (agar tidak 404, dan tetap lulus lint PHP).
2. Karena logo kementerian Kemdikbud tidak tersedia sebagai file gambar di project, maka "Logo kementerian" di Topbar dan Login page diwakili dengan **icon formal Bootstrap** yang sesuai (contoh: `bi-mortarboard-fill` warna Dark Navy #0F2747) dengan styling circle border ringkas (agar tetap terlihat seperti logo instansi). Jika user ingin mengganti dengan gambar logo resmi nanti, cukup ganti icon `<i>` dengan `<img src>` di lokasi yang sama.
3. Icon Notifikasi di Topbar untuk saat ini **hanya UI static** (bell icon dengan badge titik kecil optional, tidak memiliki fitur dropdown notifikasi fungsional karena spec tidak mendeskripsikannya).
4. Spec fokus ke Laporan Harian tapi user minta "robak total desain UI" → diasumsikan SELURUH HALAMAN (Dashboard, Data Indikator, Rekap Bulanan, Dokumen, Profil Saya, Login) **juga ikut berubah styling CSS ke arah formal** (karena CSS global `style.css` dan `responsive.css` di-rewrite total), meskipun penyesuaian struktur HTML prioritas utama di Laporan Harian + Topbar + Sidebar + Footer.

## Open Questions
- [x] **Q1**: Apakah "robak total" berarti SELURUH halaman (tidak hanya Laporan Harian) ikut berubah styling ke arah formal? **Jawaban (diasumsikan)**: Ya, karena CSS global di-rewrite dan user berkata "total". (Tidak perlu ditanyakan user karena defaultnya "robak total" = seluruh UI).
- [x] **Q2**: Apakah logo kementerian perlu berupa file gambar atau bisa icon Bootstrap placeholder? **Jawaban (diasumsikan)**: Icon Bootstrap placeholder `bi-mortarboard-fill` cukup untuk tahap implementasi ini; user bisa ganti dengan logo resmi nanti.
- [ ] **Q3 (Opsional, tapi tidak block implementasi)**: Di area filter Laporan Harian, apakah "Total Laporan & Total Kegiatan" tetap ditampilkan atau dihapus seluruhnya? **Keputusan implementasi**: Tetap ditampilkan tapi **diformat flat kompak (TANPA CARD, TANPA ICON GRADIENT)**, hanya label small + angka bold. User bisa kasih feedback nanti jika ingin dihapus.

---

## Acceptance Criteria

### AC-1: Font Seluruh Aplikasi Berganti ke Sans-Serif Formal
- **Type**: `rule`
- **Given**: Pengguna membuka SEMUA halaman (Login, Dashboard, Laporan Harian, dll) setelah hard refresh.
- **When**: Melihat teks apapun (body, heading, input, button, badge, small).
- **Then**: Seluruh teks menggunakan font **sans-serif (Inter / Poppins / Roboto)**, **TIDAK ADA SATU PUN ELEMEN** yang menggunakan Book Antiqua / Palatino / Times New Roman / serif.
- **Pass Condition**: CSS global `* { font-family: "Inter", "Poppins", "Roboto", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important; }` TERAPKAN di baris paling awal `style.css`. Di DevTools Computed Style: elemen body → font-family = sans-serif stack di atas, BUKAN serif.
- **Evidence**: File [style.css](file:///c:/laragon/www/asn-candra/assets/css/style.css) baris 1-15 (font override). DevTools screenshot / Computed style.

### AC-2: Topbar (Header) Punya 4 Komponen Formal + Sub Branding Kemdikbud
- **Type**: `rule`
- **Given**: Pengguna sudah login (lihat Topbar).
- **When**: Melihat struktur Topbar dari kiri ke kanan.
- **Then**: Urutan muncul: (1) **Icon Logo Kementerian (mortarboard-fill Dark Navy)** → **Nama Sistem "Sistem Laporan ASN" tebal** → **Teks kecil "Kementerian Pendidikan, Kebudayaan, Riset dan Teknologi"** di bawah nama sistem; (2) Tombol hamburger toggle sidebar; (3) **Ikon Notifikasi (bell)**; (4) Dropdown user (teks jabatan kecil + nama tebal + avatar inisial).
- **Pass Condition**: File [includes/header.php](file:///c:/laragon/www/asn-candra/includes/header.php) area topbar-left/right berisi icon logo kementerian + sub branding, icon notifikasi. PHP lint file header.php exit code 0.
- **Evidence**: Screenshot Topbar. Diff header.php. PHP lint output.

### AC-3: Sidebar Punya 8 Menu dengan Urutan yang Benar (Tambah Pengaturan)
- **Type**: `rule`
- **Given**: Pengguna login, sidebar desktop terbuka penuh.
- **When**: Membaca menu Sidebar dari ATAS ke BAWAH.
- **Then**: Urutan menu persis: 1. Dashboard → 2. Laporan Harian → 3. Data Indikator → 4. Rekap Bulanan → 5. Dokumen → 6. Profil Saya → 7. **Pengaturan** → 8. Keluar. Menu Pengaturan tidak 404 (ada file placeholder).
- **Pass Condition**: File `includes/header.php` di `<ul class="sidebar-menu">` punya 8 `<li>` berurutan sesuai. File `pengaturan.php` tersedia dan lulus PHP lint.
- **Evidence**: Screenshot sidebar. Diff header.php. PHP lint `pengaturan.php` OK.

### AC-4: Halaman Laporan Harian Clean Row-Based Tanpa Card Berlebihan + Icon Aksi 3 Warna
- **Type**: `rule`
- **Given**: Pengguna membuka `laporan_harian.php` (mode list).
- **When**: Memeriksa struktur area Filter, Statistik, dan Table.
- **Then**:
  (a) Area filter **TIDAK punya class `.card`** (flat div). Filter berisi: select Bulan + select Tahun + Tombol Cari + Tombol Cetak (flat border 1px).
  (b) Statistik Total Laporan & Total Kegiatan **TIDAK dibungkus card / icon gradient**. Cukup label small + angka bold.
  (c) Table laporan **LANGSUNG ke table tanpa wrapper `.card` / `.card-header` / `.card-footer`**. Judul section "Data Laporan ..." berupa teks h5 di atas table.
  (d) **7 Kolom** berurutan: Tanggal | Indikator | Uraian Kegiatan | Waktu | Qty | Bukti | Aksi.
  (e) **Kolom Aksi HANYA ICON (TANPA TOMBOL BESAR / SQUARE BORDER)**:
      - Edit icon warna #2563EB (Primary).
      - Cetak icon warna #0F2747 (Dark Navy — BARU).
      - Hapus icon warna #EF4444 (Danger).
- **Pass Condition**: File `laporan_harian.php` memenuhi poin a-e. PHP lint OK.
- **Evidence**: Screenshot Laporan Harian. Diff laporan_harian.php. PHP lint OK.

### AC-5: CSS Variable Dark Navy #0F2747 & Danger #EF4444 Terapkan Sesuai Spec
- **Type**: `rule`
- **Given**: File `style.css` berhasil di-rewrite.
- **When**: Melihat blok `:root` CSS Variable.
- **Then**: Terdapat variabel CSS:
  - `--asn-primary: #2563EB;` ✅
  - **BARU `--asn-dark-navy: #0F2747;`** (digunakan untuk icon cetak & logo kementerian).
  - `--asn-bg: #F8FAFC;` ✅, `--asn-text: #1E293B;` ✅, `--asn-border: #E2E8F0;` ✅.
  - **UPDATE `--asn-danger: #EF4444;`** (bukan DC2626).
- **Pass Condition**: Semua 5 variabel di atas ada di `:root` `style.css` dengan nilai persis hex spec. Dipakai di selector yang relevan (danger text → button hapus, dark navy → icon cetak & logo).
- **Evidence**: Cuplikan `style.css` :root + selector yang menggunakan variabel baru.

### AC-6: Footer Formal Ada di Semua Halaman dengan Copyright + 3 Link
- **Type**: `rule`
- **Given**: Pengguna membuka halaman apapun (Dashboard, Laporan Harian, dll) dan scroll ke paling bawah.
- **When**: Melihat area paling bawah (setelah main content, sebelum script load).
- **Then**: Muncul Footer container dengan: (1) Teks tengah **© 2026 Sistem Laporan ASN**; (2) 3 link berurutan dengan pemisah titik tengah · : **Privasi** · **Ketentuan** · **Bantuan**. Link = placeholder `#`.
- **Pass Condition**: File `includes/footer.php` menambahkan HTML footer **SEBELUM** tag `</main></div>` dan sebelum `<script src=...>`. PHP lint footer.php OK.
- **Evidence**: Screenshot Footer. Diff footer.php. PHP lint OK.

### AC-7: Login Page Formal Tanpa Elemen Editorial, Ada Sub Branding Kemdikbud
- **Type**: `rule`
- **Given**: Pengguna membuka `login.php`.
- **When**: Melihat Auth Card & Logo area.
- **Then**:
  (a) Tidak ada class `.small-caps` "Personal Dashboard" dan "Area Masuk / Pendaftaran Baru" (dihapus).
  (b) Logo area: Icon Kementerian (mortarboard) Dark Navy → judul "Sistem Laporan ASN" → **Sub branding "Kementerian Pendidikan, Kebudayaan, Riset dan Teknologi"** (teks kecil abu di bawah nama).
  (c) Heading "Selamat Datang Kembali" / "Buat Akun Pribadi" sans-serif bold.
  (d) Card auth: flat border 1px, radius 12px (bukan 16px editorial), tanpa accent bar gradient top.
- **Pass Condition**: File `login.php` area auth logo & section mode berisi poin a-d. PHP lint login.php OK.
- **Evidence**: Screenshot Login. Diff login.php. PHP lint OK.

### AC-8: Sidebar Toggle (Collapse Desktop / Off-Canvas Mobile) TETAP BERJALAN (TIDAK RUSAK)
- **Type**: `rule`
- **Given**: app.js TIDAK diubah (sama seperti sebelumnya).
- **When**: (a) Resize window ke Desktop >1200px → klik hamburger → body tambah/remove class `.sidebar-collapsed` (sidebar 84px icon-only); (b) Resize ke Mobile <992px → klik hamburger → body tambah/remove `.sidebar-open` (sidebar slide in dari kiri, backdrop muncul); (c) Tekan ESC / klik backdrop → sidebar mobile menutup.
- **Then**: Semua perilaku di atas MASIH BERJALAN SAMA PERSIS. Tidak ada error JS di console.
- **Pass Condition**: app.js TIDAK diubah (dibuktikan dengan sha / diff tidak ada perubahan). Manual test 3 perilaku toggle di atas berjalan sukses tanpa error.
- **Evidence**: Screenshot responsive 3 breakpoint. Console tab (no error). Diff app.js = KOSONG (tidak diubah).

### AC-9: Semua File PHP yang Diubah Lulus PHP Lint (PHP 7.3.33)
- **Type**: `rule`
- **Given**: Selesai implementasi semua file (minimal: style.css, responsive.css, includes/header.php, includes/footer.php, dashboard.php, login.php, laporan_harian.php, pengaturan.php (placeholder)).
- **When**: Menjalankan perintah `php -l <file>` untuk SEMUA file PHP di project yang relevan (13 file: 12 lama + pengaturan.php BARU).
- **Then**: Semua exit code 0. Tidak ada "PHP Parse error".
- **Pass Condition**: Output command lint 13/13 file menuliskan `No syntax errors detected in ...`.
- **Evidence**: Output RunCommand PHP lint.

### AC-10: Estetika Formal & Data Fokus (Rubrik Kualitas)
- **Type**: `rubric`
- **Dimension**: Tingkat kesesuaian estetika dengan "Aplikasi Pemerintahan Formal Modern" & tingkat fokus pada data laporan.
- **Scale**: 1-5
- **Anchors**:
  1 = Masih banyak elemen dekoratif editorial (serif, accent-left, small-caps, card berlebihan), tidak terlihat formal pemerintahan.
  3 = Font sudah sans-serif tapi masih ada elemen card berlebihan di table Laporan Harian, warna Dark Navy / Danger belum konsisten.
  5 = Seluruh UI bersih, flat-border sans-serif, Laporan Harian table clean row-based TANPA card wrapper, kolom aksi hanya icon 3 warna sesuai spec, sub branding Kemdikbud muncul jelas di Topbar & Login, footer formal ada.
- **Pass Threshold**: >= 4
- **Evidence**: Screenshot full page Login, Dashboard, Laporan Harian (Desktop & Tablet). Rasio data vs elemen UI dekoratif. Konsistensi palet warna #2563EB / #0F2747 / #EF4444.
