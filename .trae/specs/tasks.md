# Redesign Total Pemerintahan Formal - Sistem Laporan ASN
# Implementation Plan

## Task 1: Rewrite Total style.css — Ganti Font Sans-Serif + Palet Formal + Hapus Elemen Editorial
- **Status**: `pending`
- **Priority**: high
- **Depends On**: None
- **Description**:
  - Override total `*` selector ganti dari Book Antiqua serif ke Inter / Poppins / Roboto sans-serif `!important`.
  - Update blok `:root` CSS Variable: tambah **--asn-dark-navy: #0F2747** (BARU), update **--asn-danger: #EF4444** (dari DC2626), hapus variabel editorial yang tidak dibutuhkan (`--asn-accent-left`, `--asn-divider-dashed`), set radius card / input menjadi lebih kompak (card 12px, bukan 14px editorial).
  - Hapus / rewrite semua styling editorial: `.small-caps` (hapus atau non-aktifkan), `.eyebrow`, `.stat-card` yang ada accent-left 3px (ganti ke flat border 1px tanpa accent kiri), `.tanggal-cell` border-left accent (ganti ke flat cell normal), table thead/tbody styling yang editorial (ganti ke flat row-based), button hover underline yang editorial (ganti ke hover bg solid ringan formal).
  - Rewrite `.topbar` styling: menyiapkan ruang untuk icon logo kementerian + sub branding teks kecil 2 baris di nama sistem, menambah styling untuk **.topbar-notif** (bell icon) sebelum user dropdown.
  - Rewrite `.sidebar-menu .nav-link.active`: ganti dari "border-left accent 3px + titik kanan" (editorial) ke **highlight biru muda flat** (bg `#EFF6FF` + border-radius 8px, warna teks primary) sesuai spec: "Menu aktif menggunakan highlight biru muda - Tidak menggunakan shadow berat".
  - Rewrite table laporan: hapus card wrapper styling, padding cell luas (14px-16px), border bottom 1px per row, hover row `#EFF6FF` (biru muda lembut), hapus accent-left border di td:first-child saat hover.
  - Rewrite `.badge` styling: hapus alpha transparan border editorial, ganti ke badge flat bg soft atau outline formal sesuai spec tidak dekoratif.
  - **PENTING**: Jangan UBAH NAMA CLASS UTAMA apapun yang berhubungan dengan state toggle sidebar (`.sidebar-collapsed`, `.sidebar-open`, `.topbar`, `.app-wrapper`, `.sidebar`, `.main-content`, `#sidebarBackdrop`) — hanya ubah value CSS-nya, nama class tetap sama agar JS app.js toggle TETAP BERJALAN.
- **Acceptance Criteria Addressed**: AC-1, AC-5, (partial AC-4 styling table, partial AC-2 styling topbar, partial AC-3 styling sidebar active)
- **Test Requirements**:
  - `rule` TR-1.1: Global font override sans-serif. Evidence: Buka style.css baris awal, selector `*` → `font-family` berisi stack sans-serif (Inter / Poppins / Roboto / Segoe UI / sans-serif) dengan `!important`. Tidak ada "Book Antiqua" di selector `*` pertama.
  - `rule` TR-1.2: CSS Variable di :root sesuai spec. Evidence: Blok `:root` berisi `--asn-primary: #2563EB;`, `--asn-dark-navy: #0F2747;`, `--asn-bg: #F8FAFC;`, `--asn-text: #1E293B;`, `--asn-border: #E2E8F0;`, `--asn-danger: #EF4444;` — NILAI HEX PERSIS.
  - `rule` TR-1.3: Sidebar nav-link.active tidak punya border-left accent editorial. Evidence: `.sidebar-menu .nav-link.active` → property `background-color` (biru muda `rgba(37, 99, 235, 0.08)` / `#EFF6FF`) + border-radius 8px, **TIDAK ADA `border-left` dengan tebal 3px**.
  - `rubric` TR-1.4: Estetika keseluruhan flat, formal, tanpa ornamen editorial berlebihan. Scale 1-5; 1=masih banyak accent-left small-caps, 3=campuran, 5=full flat sans-serif formal; threshold >=4; Evidence: Cuplikan 3 area key CSS (topbar, sidebar active, table tbody).

---

## Task 2: Rewrite responsive.css — Preserve State Collapsed / Open + Formal Style
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 1
- **Description**:
  - Rewrite total `responsive.css` agar gaya visual cocok dengan style.css formal baru (sans-serif, flat, radius kompak).
  - **PENTING UTAMA**: JANGAN UBAH NAMA CLASS STATE toggle sidebar di media query apapun:
    - `@media (min-width: 992px) body.sidebar-collapsed → ...` → NAMA CLASS INI WAJIB TETAP (agar JS toggle yang merubah class `sidebar-collapsed` di body tag tetap jalan). Set `--asn-sidebar-width: 84px;` untuk compact.
    - `@media (max-width: 991.98px) body.sidebar-open → ...` → NAMA CLASS INI WAJIB TETAP (agar JS toggle mobile merubah class `sidebar-open` di body tag tetap jalan). Sidebar fixed top, left -100% → 0, width min(86%, 340px), `#sidebarBackdrop` tampil.
  - Sesuaikan typography scale untuk tablet & mobile (sedikit lebih kecil dari editorial).
  - Sesuaikan padding stat card di breakpoint kecil, **jika stat card diubah gaya flat, pastikan tidak center (bisa rata kiri)**.
  - `@media print` tetap: hide topbar, sidebar, tombol, action-group, backdrop.
- **Acceptance Criteria Addressed**: AC-8 (preserved toggle state + responsive behavior 3-tier)
- **Test Requirements**:
  - `rule` TR-2.1: Nama class state toggle TIDAK DIUBAH. Evidence: Search di responsive.css string `sidebar-collapsed` dan `sidebar-open` KEDUANYA MASIH ADA dan digabung dengan selector `body.sidebar-collapsed` / `body.sidebar-open` (bukan nama class baru).
  - `rule` TR-2.2: 3-tier responsive width value TETAP BENAR. Evidence: Media query (a) min 992px (desktop collapsed 84px); (b) 991.98px kebawah (mobile off-canvas). Baca width sidebar value: default 260px di style.css, 84px saat collapsed. Mobile off-canvas width antara 80-90%.
  - `rubric` TR-2.3: Estetika responsive tablet & mobile bersih, tidak ada overflow horizontal, elemen tidak tabrakan. Scale 1-5; threshold >=4; Evidence: Screenshot / simulasi breakpoint 1100px (tablet compact) dan 700px (mobile off-canvas).

---

## Task 3: Update includes/header.php — Topbar (Logo + Sub Branding + Notifikasi) + Sidebar 8 Menu + Pengaturan
- **Status**: `pending`
- **Priority**: high
- **Depends On**: None (bisa parallel dengan Task 1/2)
- **Description**:
  - **AREA TOPBAR (HEADER)**: Ubah struktur `.topbar-left`:
    1. Sebelum brand nama sistem: Tambah **icon logo kementerian** = `<i class="bi bi-mortarboard-fill" style="color: var(--asn-dark-navy); font-size: 1.35rem;"></i>` dengan wrapper styling circle / padding agar terlihat formal.
    2. Setelah icon brand: Tambah wrapper `.brand-text` 2 BARIS:
       - Baris 1 (besar): `<span class="brand-name">Sistem Laporan ASN</span>` → tebal.
       - Baris 2 (kecil, abu): `<small class="brand-sub">Kementerian Pendidikan, Kebudayaan, Riset dan Teknologi</small>`.
    3. Urutan di `.topbar-left` sekarang: **Hamburger Toggle ☰** → **Icon Logo Kemdikbud** → **Brand Text 2 baris** (nama sistem + sub branding).
  - **AREA TOPBAR-RIGHT (sebelum user dropdown)**: TAMBAH IKON NOTIFIKASI:
    - Tombol `<button type="button" class="topbar-notif" aria-label="Notifikasi"><i class="bi bi-bell"></i></button>` → styling di style.css nanti (Task 1 sudah sediakan class). Bisa tambah badge titik kecil optional `<span class="notif-dot"></span>` tapi tanpa jumlah.
    - **PENTING**: Letakkan tombol notifikasi INI sebelum tag `<div class="dropdown">` (user avatar dropdown), jadi urutan topbar-right dari kiri ke kanan: IKON NOTIF → USER DROPDOWN.
  - **AREA SIDEBAR `<ul class="sidebar-menu">`**: Ubah 7 menu menjadi 8 menu, URUTAN WAJIB = **Dashboard → Laporan Harian → Data Indikator → Rekap Bulanan → Dokumen → Profil Saya → PENGATURAN (BARU) → Keluar**.
    - Menu **Pengaturan (BARU)**: Icon `bi bi-gear-fill`, link href = `pengaturan.php`, aktif class = `$_activePage('pengaturan.php')`. Letakkan DI BAWAH Profil Saya, DI ATAS Keluar.
    - **JANGAN UBAH URUTAN 7 menu PERTAMA (kecuali nomor 7 adalah Pengaturan baru)**: Pastikan Keluar SELALU nomor 8 / PALING BAWAH.
    - Sidebar footer "Selamat Bekerja" bisa tetap atau dihapus jika ingin lebih formal (optional, tapi disarankan tetap ringkas).
  - **User-text di topbar-user**: Tetap `<small> jabatan </small>` + `<strong> Nama </strong>`, hanya styling yang berubah dari CSS.
  - Pastikan semua variable PHP dan query user di atas `<head>` TETAP SAMA (tidak diubah), hanya struktur HTML visual Topbar & Sidebar menu yang diubah.
- **Acceptance Criteria Addressed**: AC-2 (Topbar 4 komponen + sub branding), AC-3 (Sidebar 8 menu urutan benar + Pengaturan)
- **Test Requirements**:
  - `rule` TR-3.1: Topbar ada icon `bi-mortarboard-fill` sebelum nama sistem. Evidence: Cari string "bi-mortarboard-fill" di header.php, muncul di area topbar-left sebelum brand text.
  - `rule` TR-3.2: Topbar brand-sub berisi string persis "Kementerian Pendidikan, Kebudayaan, Riset dan Teknologi". Evidence: Cari string di header.php, tag `<small class="brand-sub">` berisi teks persis tersebut.
  - `rule` TR-3.3: Topbar ada icon notifikasi `bi bi-bell` SEBELUM user avatar dropdown. Evidence: Urutan tag di topbar-right: button notif (`bi-bell`) muncul sebelum `<div class="dropdown">` user.
  - `rule` TR-3.4: Sidebar 8 menu URUTAN BENAR. Evidence: Baca 8 `<li>` menu di `<ul class="sidebar-menu">`, urutan string: (1) Dashboard (2) Laporan Harian (3) Data Indikator (4) Rekap Bulanan (5) Dokumen (6) Profil Saya (7) **Pengaturan** (8) Keluar. Persis. Menu Pengaturan link = `pengaturan.php`.
  - `rule` TR-3.5: PHP Lint OK. Evidence: `php -l includes/header.php` exit code 0.

---

## Task 4: Update includes/footer.php — Tambah Footer Formal Copyright + 3 Link
- **Status**: `pending`
- **Priority**: medium
- **Depends On**: None
- **Description**:
  - Saat ini footer.php hanya berisi: `</main></div>` + script load.
  - **TAMBAHKAN HTML FOOTER SEBELUM** tag penutup `</main>` (yaitu di BARIS PALING ATAS footer.php, sebelum baris 1 yang sekarang `    </main>`):
    - Container `<footer class="app-footer">` → class styling di CSS nanti (border top 1px, bg putih, padding, text center).
    - Isi:
      - Baris 1: `<small>&copy; 2026 Sistem Laporan ASN. Hak Cipta Dilindungi.</small>` → abu kecil.
      - Baris 2: Link 3 item dengan pemisah titik tengah `&middot;`:
        - `<a href="#">Privasi</a> &middot; <a href="#">Ketentuan</a> &middot; <a href="#">Bantuan</a>`
    - Pastikan tag `<footer>` ini BERADA DIDALAM `.main-content` (yaitu sebelum `</main>`), sehingga footer ikut scroll di area konten, bukan fixed (lebih cocok untuk admin panel).
  - Script load Bootstrap bundle & app.js & validation.js TETAP SAMA, cache busting `?v=<?= time() ?>` JANGAN DIHAPUS.
- **Acceptance Criteria Addressed**: AC-6 (Footer formal copyright + 3 link di semua halaman)
- **Test Requirements**:
  - `rule` TR-4.1: Footer berada DI DALAM main. Evidence: Struktur order di footer.php = HTML `<footer>` → `</main>` → `</div>` → script.
  - `rule` TR-4.2: Copyright berisi "© 2026 Sistem Laporan ASN". Evidence: String `&copy; 2026 Sistem Laporan ASN` ada di dalam tag footer.
  - `rule` TR-4.3: 3 Link Privasi · Ketentuan · Bantuan semuanya placeholder `#`. Evidence: 3 tag `<a href="#">` dengan text Persis.
  - `rule` TR-4.4: PHP Lint OK. Evidence: `php -l includes/footer.php` exit code 0.

---

## Task 5: Update login.php — Hapus Editorial Small-Caps + Tambah Sub Branding Kemdikbud Formal
- **Status**: `pending`
- **Priority**: medium
- **Depends On**: Task 3 (karena sub branding teks persis sama dengan header.php brand-sub)
- **Description**:
  - **AREA AUTH LOGO**:
    1. Sebelum judul "Sistem Laporan ASN": Tambah **icon logo kementerian besar** = `<i class="bi bi-mortarboard-fill auth-logo-icon"></i>` (wrapper circle, warna dark navy, ukuran besar 56px).
    2. Di BAWAH `<h1>Sistem Laporan ASN</h1>` (atau di area auth logo setelah judul): TAMBAH **small sub branding**: `<p class="auth-kemdikbud-sub small-caps-ganti">Kementerian Pendidikan, Kebudayaan, Riset dan Teknologi</p>` → styling abu kecil, text center.
    3. **HAPUS class `.small-caps` "Personal Dashboard"** yang editorial — hapus span `<span class="small-caps">Personal Dashboard</span>` secara keseluruhan (karena spec formal tidak butuh).
  - **AREA MODE LOGIN (mode===login)**:
    4. **HAPUS eyebrow editorial**: `<span class="small-caps" ...>Area Masuk</span>` — hapus total span tersebut. Judul langsung ke "Selamat Datang Kembali".
    5. (Optional tapi disarankan): Auth subtitle diperpendek menjadi formal (tidak terlalu editorial), misal "Silakan masuk untuk mengelola laporan kegiatan harian Anda."
  - **AREA MODE DAFTAR (else / mode===daftar)**:
    6. **HAPUS eyebrow editorial**: `<span class="small-caps" ...>Pendaftaran Baru</span>` — hapus total. Judul langsung ke "Buat Akun Pribadi".
  - Pastikan struktur form (input username / email / nip / dll) TETAP SAMA, hanya elemen visual branding & eyebrow editorial yang diubah / dihapus.
- **Acceptance Criteria Addressed**: AC-7 (Login page formal tanpa small-caps, sub branding Kemdikbud ada)
- **Test Requirements**:
  - `rule` TR-5.1: TIDAK ADA LAGI string "Personal Dashboard" dan class small-caps di area auth logo. Evidence: Cari string "Personal Dashboard" = 0 hasil di login.php. Cari `<span class="small-caps">` di area auth = 0 hasil.
  - `rule` TR-5.2: Ada sub branding Kemdikbud di auth logo. Evidence: String "Kementerian Pendidikan, Kebudayaan, Riset dan Teknologi" ADA di area auth logo (setelah h1 judul), tag `<p class="auth-kemdikbud-sub">` atau sejenis.
  - `rule` TR-5.3: Eyebrow "Area Masuk" dan "Pendaftaran Baru" (small-caps editorial) SUDAH DIHAPUS. Evidence: Cari string "Area Masuk" dan "Pendaftaran Baru" di tag small-caps → HILANG. Bisa ada string jika tetap di text biasa tapi small-caps class TIDAK ADA dan string dihapus jika sesuai.
  - `rule` TR-5.4: PHP Lint OK. Evidence: `php -l login.php` exit code 0.

---

## Task 6: Robak Struktur HTML laporan_harian.php — Clean Row-Based Tanpa Card, Kolom Aksi Hanya Icon 3 Warna
- **Status**: `pending`
- **Priority**: high (fokus utama spec)
- **Depends On**: None (bisa parallel)
- **Description**:
  - **HEADER HALAMAN**: Tetap "Laporan Harian" + deskripsi + button Tambah Laporan (modal) — tidak diubah.
  - **AREA STATISTIK + FILTER (yang sekarang DI BUNGKUS `.card stat-card`)**:
    1. **HAPUS wrapper `<div class="card stat-card shadow-sm mb-4">` dan child `<div class="card-body">`-nya SEMUA**. Ganti dengan `<div class="laporan-filter-wrapper mb-4 p-3 bg-white border rounded-3">` → flat div, TANPA SHADOW, border 1px `--asn-border`, padding, radius 12px.
    2. Di dalam wrapper filter:
       - **Statistik Total Laporan & Total Kegiatan**: HAPUS wrapper `.d-flex` dengan icon gradient. Ganti menjadi 2 baris / inline flat:
         - `<div class="laporan-summary d-flex flex-wrap gap-4 mb-3 pb-3 border-bottom">` →
           - `<div><small class="text-muted">Total Laporan: </small><strong class="fs-6 text-primary"><?= $totalLaporan ?></strong></div>`
           - `<div><small class="text-muted">Total Kegiatan: </small><strong class="fs-6 text-success"><?= $totalKegiatan ?></strong></div>`
         - `</div>`
       - **Filter Card yang sekarang DI DALAM card-body col-md-4**: KELUARKAN dari struktur col, jadikan 1 row flat:
         - Filter toggle Bulan / Tanggal (TETAP ADA, tapi styling button flat dari CSS baru) →
         - Form filter Bulan-Tahun / Tanggal →
         - Tombol Cari & Cetak.
    - Tujuannya: Area filter + statistik **TIDAK TERLIHAT SEPERTI CARD BESAR**, tapi flat panel kompak.
  - **AREA TABLE + CARD-HEADER / CARD-FOOTER (yang sekarang `<div class="card shadow-sm">` membungkus table + header + footer)**:
    3. **HAPUS TOTAL wrapper `<div class="card shadow-sm">` → `<div class="card-header ... report-header">` → `<div class="table-responsive">` yang DI DALAM card. Ganti struktur menjadi**:
       - **Judul section (yang dulu di card-header)**: `<div class="d-flex justify-content-between align-items-center mb-3 mt-4 px-1">` → h5 judul "Data Laporan ..." + button Cetak Dokumen di kanan. `</div>` → (TANPA wrapper card-header, flat langsung).
       - **Table responsive**: `<div class="table-responsive bg-white border rounded-3 overflow-hidden">` → wrapper table flat, bg putih, border tipis, radius 12px (agar sudut table tidak tajam), TANPA SHADOW.
       - **Footer (Menampilkan X laporan)**: Pindahkan ke BAWAH table-responsive, sebagai `<small class="text-muted d-block mt-2 px-1">Menampilkan <?= $result->num_rows ?> laporan.</small>`.
    - **HASIL AKHIR**: TIDAK ADA LAGI class `.card` / `.card-header` / `.card-footer` yang membungkus area table & filter. Semua flat panel.
  - **KOLOM AKSI DI TABLE BODY (TR BARIS DATA) — PERUBAHAN PALING PENTING SESUAI SPEC**:
    4. **HAPUS TOTAL `<div class="action-group" role="group">` yang memakai `btn btn-outline-secondary / info / danger` (tombol square border besar)**. Ganti menjadi **HANYA ICON, TANPA TOMBOL BORDER SAMA SEKALI**:
       - Struktur baru kolom Aksi (seluruh isi `<td>`):
         ```php
         <td>
             <div class="table-actions d-flex align-items-center gap-3 justify-content-center">
                 <a href="edit_laporan.php?id=<?= $row['id'] ?>"
                    class="btn-icon-action"
                    style="color: var(--asn-primary);"
                    title="Edit Data"
                    data-bs-toggle="tooltip">
                     <i class="bi bi-pencil-square"></i>
                 </a>
                 <?php
                     // ... variabel $_urlCetak dll TETAP SAMA seperti sebelumnya ...
                     $_onclickCetak = 'return openPreviewLaporan(...)';
                 ?>
                 <a href="#"
                    class="btn-icon-action"
                    style="color: var(--asn-dark-navy);"
                    title="Cetak Dokumen"
                    data-bs-toggle="tooltip"
                    onclick='<?= htmlspecialchars($_onclickCetak, ENT_QUOTES, 'UTF-8') ?>'>
                     <i class="bi bi-printer-fill"></i>
                 </a>
                 <button type="button"
                    class="btn-icon-action"
                    style="color: var(--asn-danger);"
                    title="Hapus Data"
                    data-bs-toggle="tooltip"
                    onclick="confirmDelete('hapus_laporan.php?id=<?= $row['id'] ?>')">
                     <i class="bi bi-trash3-fill"></i>
                 </button>
             </div>
         </td>
         ```
       - **PENTING**:
         - **Edit**: WARNA = `var(--asn-primary)` → `#2563EB`.
         - **Cetak**: WARNA = `var(--asn-dark-navy)` → `#0F2747` (BARU WAJIB SESUAI SPEC, bukan biru info / navy yang lain).
         - **Hapus**: WARNA = `var(--asn-danger)` → `#EF4444`.
         - Class `.btn-icon-action`: HANYA styling link/button tanpa background, tanpa border, icon ukuran 18px, hover opacity 0.75 + text-decoration underline. CSS class ini ditambahkan di style.css (Task 1 nanti, tapi di HTML kita pakai class agar styling konsisten).
         - Pastikan variabel PHP `$_onclick` untuk cetak Laporan ID **TETAP SAMA logic dan json_encode htmlspecialchars** seperti sebelumnya, HANYA struktur wrapper tombol yang diganti dari btn border ke icon-only.
  - Moda tambah (aksi=tambah) yang menggunakan card form bisa tetap memakai card (karena form memang butuh pembungkus jelas — tidak melanggar "hindari card berlebihan" karena card untuk form masih bisa diterima, spec fokus ke area table list data).
  - Modal Tambah Laporan, Bukti Modal, Preview Modal: Struktur HTML TETAP SAMA, hanya styling visual berubah dari CSS baru.
- **Acceptance Criteria Addressed**: AC-4 (Laporan Harian Clean Row-Based Tanpa Card + Icon Aksi 3 Warna Persis Spec)
- **Test Requirements**:
  - `rule` TR-6.1: Area filter + statistik TIDAK dibungkus class `.card` dan `.stat-card`. Evidence: Cari string `<div class="card stat-card"` (yang ada di line 248 laporan_harian.php sebelumnya) = **0 HASIL** setelah edit. Semua area filter ada di wrapper div flat tanpa class card.
  - `rule` TR-6.2: Area table TIDAK dibungkus class `.card`, `.card-header`, `.card-footer`. Evidence: Cari 3 class ini di area table list (mode list else block) = 0 hasil. Judul section flat h5 di atas table-responsive.
  - `rule` TR-6.3: Kolom Aksi HANYA ICON TANPA TOMBOL BORDER. Evidence: Cari string `<div class="action-group"` = 0 hasil. Struktur baru memakai `<div class="table-actions d-flex gap-3">` dengan 3 anchor/button class `.btn-icon-action`, **TIDAK ADA class `btn btn-outline-*` di kolom aksi table**.
  - `rule` TR-6.4: Warna 3 icon aksi PERSIS SESUAI SPEC. Evidence: (a) Edit = style color `var(--asn-primary)` → `#2563EB`. (b) Cetak = style color `var(--asn-dark-navy)` → `#0F2747`. (c) Hapus = style color `var(--asn-danger)` → `#EF4444`.
  - `rule` TR-6.5: PHP Lint OK. Evidence: `php -l laporan_harian.php` exit code 0.

---

## Task 7: Minor Tweak dashboard.php — Ganti Stat Card & Profil Card ke Flat Formal (Tanpa Accent Editorial)
- **Status**: `pending`
- **Priority**: medium
- **Depends On**: Task 1 (karena class stat-card baru styling ada di CSS)
- **Description**:
  - **4 STAT CARD (Total Laporan / Bulan Ini / Kegiatan Selesai / Dokumen)**:
    1. HTML `<span class="stat-desc">` editorial (deskripsi kecil di bawah angka) BISA TETAP ADA, tapi styling di CSS sudah flat tanpa accent-left. Di PHP tidak perlu dihapus (lebih baik tetap informatif).
    2. **TIDAK ADA PERUBAHAN QUERY SQL** sama sekali — semua hitungan angka tetap.
    3. Pastikan struktur HTML stat card (stat-icon + stat-text) TETAP SAMA, hanya styling CSS dari Task 1 yang mengubah visual menjadi flat tanpa border-left accent.
  - **CARD PROFIL USER (card mb-4 shadow-sm di atas stat card)**:
    4. Bisa tetap memakai class card, tapi HTML TETAP SAMA (nama, NIP, Jabatan) — tidak diubah, styling shadow / radius dari CSS baru yang flat.
  - **CARD LAPORAN TERBARU (table recent 5)**:
    5. TIDAK PERLU DI-ROBAK struktur seperti Laporan Harian (karena spec fokus ke halaman Laporan Harian utama, dan ini recent di Dashboard ringkas) — hanya styling CSS baru yang berubah, kolom aksi action-group bisa tetap tapi styling dari CSS sudah flat.
  - Tujuan: Tidak butuh perubahan besar di dashboard.php, hanya pastikan tidak ada elemen editorial yang tersisa di HTML (semua dari CSS Task 1 yang bersihkan).
- **Acceptance Criteria Addressed**: AC-10 (Rubrik — seluruh UI konsisten formal termasuk Dashboard)
- **Test Requirements**:
  - `rule` TR-7.1: 4 Stat Card HTML struktur INTACT, TIDAK ADA query SQL yang diubah. Evidence: Diff dashboard.php hanya mengandung minor perubahan (jika ada). Baca line hitung COUNT / SUM query = SAMA dengan sebelumnya.
  - `rule` TR-7.2: PHP Lint OK. Evidence: `php -l dashboard.php` exit code 0.

---

## Task 8: Buat File Placeholder pengaturan.php Agar Menu Pengaturan Tidak 404
- **Status**: `pending`
- **Priority**: low
- **Depends On**: Task 3 (header.php sudah link ke pengaturan.php)
- **Description**:
  - Buat file `c:\laragon\www\asn-candra\pengaturan.php` berisi struktur minimal:
    - require_once `includes/header.php` (agar otomatis punya topbar + sidebar + styling CSS baru).
    - Set `$pageTitle = 'Pengaturan - Sistem Laporan ASN';`.
    - Konten utama:
      - Header halaman: `<h2 class="fw-bold mb-0">Pengaturan</h2>` + `<p class="text-muted">Kelola preferensi & pengaturan akun Anda.</p>`.
      - Card flat menampilkan pesan placeholder formal:
        - Icon `bi bi-tools` warna dark navy besar.
        - Judul: "Fitur Akan Segera Hadir"
        - Deskripsi: "Halaman pengaturan akun & preferensi aplikasi sedang dalam tahap pengembangan. Anda akan dapat mengubah sandi, preferensi notifikasi, dan pengaturan lainnya segera."
      - Tombol kembali ke Dashboard.
    - require_once `includes/footer.php` (agar footer formal & script load).
  - Pastikan sintaks PHP 7.3.33 OK (tidak arrow function / typed property).
- **Acceptance Criteria Addressed**: AC-3 (Menu Pengaturan tidak 404, placeholder tersedia)
- **Test Requirements**:
  - `rule` TR-8.1: File pengaturan.php TIDAK 404. Evidence: File exist di root project.
  - `rule` TR-8.2: PHP Lint OK. Evidence: `php -l pengaturan.php` exit code 0.
  - `rule` TR-8.3: Muat header & footer yang benar. Evidence: File berisi `require_once 'includes/header.php'` di awal dan `require_once 'includes/footer.php'` di akhir.

---

## Task 9: PHP Lint 13 File Utama — Validasi Semua File PHP yang Diubah / Baru
- **Status**: `pending`
- **Priority**: high
- **Depends On**: Task 3, 4, 5, 6, 7, 8 (semua file PHP selesai di-edit / dibuat)
- **Description**:
  - Jalankan command PHP lint untuk 13 file (12 file sebelumnya yang tercatat OK + pengaturan.php BARU):
    1. login.php
    2. includes/header.php
    3. includes/footer.php
    4. dashboard.php
    5. laporan_harian.php
    6. rhk.php (tidak di-edit, tapi lint ulang)
    7. profil.php (tidak di-edit)
    8. rekap_bulanan.php (tidak di-edit)
    9. simpan_laporan.php (tidak di-edit)
    10. edit_laporan.php (tidak di-edit)
    11. cetak_pdf.php (tidak di-edit)
    12. cetak_laporan_harian.php (tidak di-edit)
    13. pengaturan.php (BARU)
  - Command per file: `C:\laragon\bin\php\php-7.3.33-Win32-VC15-x64\php.exe -l <nama_file.php>`
  - Pastikan **ALL 13/13 exit code 0**, jika ada yang error, kembali ke task terkait untuk memperbaiki.
- **Acceptance Criteria Addressed**: AC-9 (PHP Lint 13 file OK)
- **Test Requirements**:
  - `rule` TR-9.1: 13/13 file exit code 0. Evidence: Output RunCommand semua file menuliskan "No syntax errors detected in ..." atau [file.php] OK.

---

### Ringkasan Mapping AC → Task:
| AC | Covered By Tasks |
|----|------------------|
| AC-1 (Font Sans-Serif) | Task 1 |
| AC-2 (Topbar + Sub Branding + Notif) | Task 3 |
| AC-3 (8 Menu Sidebar + Pengaturan) | Task 3, Task 8 |
| AC-4 (Laporan Harian Clean + Icon 3 Warna) | Task 6 |
| AC-5 (CSS Var Dark Navy + Danger) | Task 1 |
| AC-6 (Footer Formal) | Task 4 |
| AC-7 (Login Page Formal) | Task 5 |
| AC-8 (Sidebar Toggle Preserved) | Task 2 |
| AC-9 (PHP Lint 13 File) | Task 9 |
| AC-10 (Rubrik Estetika) | Task 1, Task 2, Task 7 |
