# Redesign UI - Minimalis Editorial (Substack Style) Implementation Plan

## Repository Research

Arah redesign yang dipilih user: **Minimalis Editorial (mirip Substack)**.

Current state (redesign sebelumnya — SaaS Modern Notion/Linear):
- Font wajib tetap: **Book Antiqua** (serif) di semua elemen.
- Palet wajib tetap (dari `UI_REDESIGN_INSTRUCTION.md`): `#2563EB` / `#1E40AF` / `#DBEAFE` / `#F8FAFC` / `#FFFFFF` / `#1E293B` / `#E2E8F0`.
- Layout shell baru di [header.php](file:///c:/laragon/www/asn-candra/includes/header.php): Topbar sticky 68px + .app-wrapper flex + .sidebar 260px + main-content padding 1.75rem.
- 7 menu sidebar (wajib): Dashboard, Laporan Harian, Data Indikator, Rekap Bulanan, Dokumen, Profil Saya, Keluar.
- Stat card Dashboard (wajib): Total Laporan, Laporan Bulan Ini, Kegiatan Selesai, Dokumen — query di [dashboard.php](file:///c:/laragon/www/asn-candra/dashboard.php#L12-L26).
- Komponen aktif di CSS: `.card radius18`, `.stat-card`, `.tanggal-cell gradient biru`, `.badge-indicator`, `.action-group`, `.filter-card filter-toggle`, `.modal`.
- Sidebar toggle logic di [app.js](file:///c:/laragon/www/asn-candra/assets/js/app.js#L247-L314): `.sidebar-collapsed` (desktop compact 84px) + `.sidebar-open` (mobile off-canvas + `#sidebarBackdrop`).
- Responsive di [responsive.css](file:///c:/laragon/www/asn-candra/assets/css/responsive.css) 4 breakpoint: 1400+ / 1199.98 / 991.98 / 767.98 / 575.98.
- Project constraint tetap: **PHP 7.3.33** (syntax lint `php -l` WAJIB dijalankan untuk 12 file).
- **Onclick inline wajib**: `json_encode + htmlspecialchars(..., ENT_QUOTES)` (tidak diubah di CSS-only redesign ini, tetapi dijaga tidak rusak).

Goal Minimalis Editorial / Substack:
  1. Kurangi shadow visual (shadow "hard" → super-soft atau border-only).
  2. Maksimalkan whitespace & breathing room (padding dinaikkan, line-height lebih longgar).
  3. **Typography editorial**: heading serif Book Antiqua lebih besar, letter-spacing lebih negatif untuk heading; sub-heading uppercase small-caps; body line-height 1.75.
  4. Border-based design (substitusi shadow → 1px border tipis `--asn-border-light`) + divider garis putus-putus untuk section.
  5. **Button minimalis**: border 1.5px + flat (bukan gradient), efek hover underline saja, icon kecil disebelah text.
  6. **Card minimalis**: radius 16px → 14px, bayangan dihapus → border tipis saja.
  7. **Sidebar clean**: hapus shadow card, gunakan border-right tipis, nav-link tanpa background — underline accent saja untuk item aktif.
  8. **Topbar transparan-frosted → flat white solid dengan border-bottom tipis**.
  9. **Stat card editorial**: angka besar serif + label uppercase kecil, divier kiri 3px accent biru (seperti pull-quote), tanpa gradient background icon — icon monochrome.
  10. **Table editorial**: header tanpa background (hanya border-bottom tebal 1.5px); setiap row dipisahkan border-bottom tipis; hover highlight subtle accent left-border.
  11. **Badge editorial**: chip small dengan uppercase small-caps & border 1px, bukan background penuh.
  12. **Auth page login**: hapus radial gradient → flat light, auth card hapus shadow → border tipis dan divider top kecil saja.
  13. Tetap mempertahankan 7 menu, 4 stat card, sidebar 260px (wajib instruksi).
  14. Tetap responsive (tablet compact / mobile hamburger).

## Files and Modules

- `c:\laragon\www\asn-candra\assets\css\style.css`:
  **FULL REWRITE** ke Minimalis Editorial style (typography scale, border-only card, flat button, editorial stat-card, sidebar clean underline, table editorial, auth minimalis, topbar flat).
- `c:\laragon\www\asn-candra\assets\css\responsive.css`:
  **UPDATE** sesuai scale typography baru dan sidebar-border-only di tiap breakpoint (utamakan tidak merusak collapsed state dan offcanvas state).
- `c:\laragon\www\asn-candra\dashboard.php`:
  **MINOR TWEAK** label & struktur 4 stat card → biar cocok dengan class "editorial stat" (opsional: tambahkan helper small description di bawah angka, div accent kiri).
- `c:\laragon\www\asn-candra\includes\header.php`:
  **MINOR TWEAK** opsional: hapus `bg-white/backdrop-blur` → flat border-bottom.
- `c:\laragon\www\asn-candra\login.php`:
  **MINOR TWEAK** auth-page (hapus radial gradient background, gunakan flat, teks editorial subtitle small-caps).
- `c:\laragon\www\asn-candra\assets\js\app.js`:
  **TIDAK DIUBAH**. Sidebar toggle & onclick global function tetap dipertahankan.
- Validation: lint semua file PHP di atas dengan `php -l` (minimal login, header, dashboard, karena file lain cuma CSS).

## Implementation Steps

1. Rewrite `assets/css/style.css` menjadi Minimalis Editorial (Substack):
   - Variables: tambah `--asn-radius-card 14px`, `--asn-border-divider`, `--asn-accent-left 3px`, `--asn-editorial-shadow (super-soft atau none)`.
   - Reset body: line-height 1.75, font-size 15.5px, bg #F8FAFC tetap.
   - Typography: h1 2.4rem / h2 1.9rem / h3 1.45rem (serif lebih besar). Small-caps helper class.
   - Auth wrapper: ganti radial gradient → #F8FAFC flat + border-top 1px subtle di viewport.
   - Auth card: border 1px E2E8F0, hapus shadow, radius 20→16px, divider top garis biru tipis 2px.
   - Topbar: flat white solid, border-bottom 1px E2E8F0, hapus backdrop-blur.
   - Sidebar: border-right 1px E2E8F0, hapus shadow. nav-link active = border-left 3px biru + font-weight 700 (no background fill).
   - Card: hapus shadow, tambah border 1px E2E8F0, radius 14px, divider dashed untuk card-header.
   - Stat card: border-left 3px accent biru, hapus shadow, border 1px E2E8F0. Icon hanya ikon serif biru tanpa gradient.
   - Button: hapus gradient + shadow → flat border 1.5px, radius 10→12, hover warna lebih gelap & underline text dalam button.
   - Form-control: border 1.5px, flat (no inner-shadow), radius 10→12, focus ring outline biru tipis tanpa shadow.
   - Badge: border 1px, label uppercase small-caps, background soft blue hanya opacity 0.08.
   - Table editorial: thead background putih, border-bottom 2px text-subtle; row dipisah border-bottom tipis; hover row ditambah border-left 3px accent biru 1px biru tipis.
   - Modal: border 1px, shadow super-soft.
   - Tanggal cell: hapus gradient biru penuh. Jadi border-left 3px accent biru + text bold + label bulan small-caps.
   - Filter-toggle: flat border pill, active flat biru solid, bukan gradient.
   - Action-group: square button border 1px, flat, icon biru monochrome.
   - Alert: border 1px, icon di kiri, tanpa shadow, background opacity 0.04.

2. Update `assets/css/responsive.css`:
   - Pertahankan sidebar collapse 84px (tablet) & offcanvas mobile (hamburger).
   - Scale typography agar tetap nyambung dengan editorial scale di style.css (h1/h2/h3 tetap besar di mobile).
   - Pertahankan sidebar backdrop di mobile.
   - Tambah override `.stat-card` di ≤575.98px: border-left accent tetap 3px, tapi padding lebih kecil & align kiri.

3. Minor tweak `dashboard.php`:
   - Tambahkan small description (opsional) pada setiap stat-card label — contoh "Total Laporan Keseluruhan".
   - Pastikan class wrapper `.stat-card` cukup dan cocok dengan gaya baru.

4. Minor tweak `includes/header.php`:
   - Hapus class `backdrop-blur` / bg transparan di topbar (jika ada), ganti jadi flat `bg-white` dan `border-bottom:1px solid var(--asn-border)`.
   - Pastikan div `#sidebarBackdrop` tetap ada.

5. Minor tweak `login.php`:
   - Hapus inline style atau class wrapper yang punya radial gradient (gunakan class baru editorial).
   - Ganti subtitle area menjadi small-caps (misal "Selamat Datang — Masuk ke akun Anda" dengan label kecil uppercase).

6. Validasi syntax PHP:
   - Jalankan `php -l` untuk: `login.php`, `header.php`, `dashboard.php`, `footer.php`, `laporan_harian.php`, `rhk.php`, `rekap_bulanan.php`, `profil.php`, `simpan_laporan.php`, `edit_laporan.php`, `cetak_pdf.php`, `cetak_laporan_harian.php`. Pastikan semua exit 0.

## Dependencies and Considerations

- **Font Book Antiqua HARUS TETAP** (instruksi). Minimalis editorial sangat cocok karena Book Antiqua adalah serif klasik (mirip font Substack "Miller" / "Tiempos").
- **7 menu sidebar WAJIB** dan **nama 4 stat card WAJIB** (tidak diubah, hanya class di CSS diubah style visualnya).
- **Layout shell 3-area (Topbar + Sidebar 260px + Main)** dipertahankan (tidak diganti struktur HTML). Hanya penampilan CSS yang diubah.
- **Responsive behaviour**: collapsed desktop 84px dan hamburger off-canvas mobile harus tetap fungsi; tidak mengubah ID `#sidebarToggleBtn` dan `#sidebarBackdrop` dan tidak mengubah class `.sidebar-open / .sidebar-collapsed` yang dipakai app.js.
- PHP 7.3.33 compatibility: tidak boleh menggunakan syntax PHP 7.4+ (arrow function, typed properties, dll). Karena kita cuma tweak minor & CSS rewrite, aman.
- Cache busting: header / footer masih menggunakan `?v=<?= time() ?>` → browser otomatis load CSS baru, safe.
- **JS function inline onclick**: semua `window.confirmDelete / openPreviewLaporan / preparePrint / loadBukti / setupImagePreview` tetap terekspos global di app.js bagian akhir → tidak berubah.
- PDF / cetak dokumen (`cetak_pdf.php`, `cetak_laporan_harian.php`) TIDAK diubah (tidak include header, pakai style sendiri), aman.

## Validation

1. **PHP Lint**: 12 file utama harus `No syntax errors detected`.
2. **Browser manual hard refresh (Ctrl+Shift+R)**:
   - Buka `login.php` → card auth flat border tipis, tanpa shadow, heading editorial besar.
   - Masuk ke `dashboard.php` → 4 stat card **dengan border-left accent biru** + label small-caps; topbar flat border-bottom; sidebar flat dengan border-right; active menu pakai border-left biru tanpa background.
   - Buka `laporan_harian.php` → cell tanggal (bukan gradient biru tapi border-left accent biru small-caps); table header editorial border-bottom 2px; row dipisah border tipis; badge indikator small-caps dengan border 1px.
3. **Responsive**:
   - Tablet (992-1199.98px): sidebar compact icon-only 84px.
   - Mobile (<992px): sidebar hidden, hamburger toggle muncul, click → slide off-canvas, backdrop overlay muncul.
4. **JS Console**: tidak ada error (terutama `openPreviewLaporan / confirmDelete / loadBukti` masih bekerja saat klik link).

## Risks

- **Risk 1**: Beberapa elemen di halaman seperti `.stat-card` di laporan_harian.php / rhk.php mungkin class yang tidak konsisten dengan yang diharapkan. Handling: cek jika class `.stat-card .stat-icon` masih dipakai di halaman lain, jika iya pastikan selector CSS di style.css cukup fleksibel untuk tidak merusak.
- **Risk 2**: Selector `.sidebar-menu .nav-link.active` berubah ke border-left-based. Handling: pastikan class `.nav-link.active` di sidebar tidak memiliki `background-color` override (jika ada di file CSS lama lain, tapi project hanya punya style.css).
- **Risk 3**: Mobile offcanvas shadow dihapus, mungkin terlihat "hilang". Handling: backdrop-blur overlay tetap ada, jadi sidebar tetap jelas.
- **Risk 4**: Minor tweak dashboard.php / login.php / header.php berpotensi syntax error jika tidak teliti. Handling: jalankan lint setelah edit.
