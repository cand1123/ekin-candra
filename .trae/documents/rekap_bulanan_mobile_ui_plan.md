# Perbaikan UI Mobile `rekap_bulanan.php` Implementation Plan

## Repository Research

File yang sedang dibuka pengguna: `rekap_bulanan.php`. Berdasarkan analisis, ditemukan beberapa masalah UI mobile:

### Masalah 1: Filter Bar Structure Tidak Sesuai Sistem `md-filter-form`
- Form hanya punya class `w-100`, TIDAK ada class `md-filter-form`. Akibatnya CSS `.md-filter-bar form.w-100.md-filter-form` (yang membuat form horizontal flex tanpa scroll) TIDAK berlaku.
- Field `Bulan` dan `Tahun` hanya punya class `md-field`, TIDAK ada `md-filter-form__field`.
- Ada inline style `min-width:150px` dan `min-width:120px` (melanggar aturan proyek: tidak boleh inline style untuk spacing/width).
- Tombol "Cari Data" selalu ditampilkan. Sesuai preferensi user, filter harus auto-submit, tombol hanya di `<noscript>`.
- Tidak ada JavaScript auto-submit pada dropdown Bulan/Tahun.

### Masalah 2: `md-card__header` di Mobile Tidak Proporsional
- Header card RHK menempatkan tombol "Edit Output"/"Tambah Output" di sebelah kanan info RHK.
- Pada mobile, `flex-wrap:wrap` memindahkan tombol ke baris bawah, tapi tombol tidak full-width → tampak sempit dan tidak proporsional.
- Tidak ada override CSS di `responsive.css` untuk `.md-card__header` di mobile.

### Masalah 3: Lampiran Gambar Inline Style Tidak Responsif
- Gambar bukti menggunakan inline style `max-width:300px; height:150px`.
- Pada mobile, gambar harus full-width dengan height yang proporsional.

### Masalah 4: Box Output RHK Menggunakan Inline Style
- Box output RHK (`md-form-tip`) menggunakan inline style untuk `background`, `border-color`, `color` pada ikon. Sebaiknya gunakan class modifier.

## Files and Modules
- `rekap_bulanan.php`: Perbaiki struktur filter bar, hapus inline style, tambahkan auto-submit JS, perbaiki lampiran gambar.
- `assets/css/responsive.css`: Tambahkan override untuk `.md-card__header` di mobile agar tombol full-width, dan atur gambar lampiran responsif.

## Implementation Steps
1. **Perbaiki Filter Bar** di `rekap_bulanan.php`:
   - Tambahkan class `md-filter-form` pada `<form>`.
   - Tambahkan class `md-filter-form__field` pada field Bulan dan Tahun (tahun dapat `md-filter-form__field--tahun`).
   - Hapus inline style `min-width` dan `margin-bottom`.
   - Pindahkan tombol "Cari Data" ke dalam `<noscript>`.
   - Tambahkan script auto-submit pada dropdown Bulan dan Tahun.

2. **Perbaiki `md-card__header` Mobile** di `responsive.css`:
   - Pada breakpoint ≤575.98px, atur `.md-card__header` agar tombol di container kanan menjadi full-width (`width:100%`), tombol menjadi `width:100%`.

3. **Perbaiki Lampiran Gambar** di `rekap_bulanan.php` + `responsive.css`:
   - Hapus inline style `max-width:300px` dan `height:150px` dari `<a>` dan `<img>`.
   - Tambahkan class CSS untuk gambar lampiran agar responsif (full-width di mobile, height auto/cover).

4. **Bersihkan Inline Style Box Output RHK** di `rekap_bulanan.php`:
   - Pindahkan inline style `background`, `border-color`, `color` ke class modifier CSS atau gunakan class yang sudah ada.

## Dependencies and Considerations
- Kompatibilitas PHP 7.3.33 (tidak boleh pakai fitur PHP 8+).
- Aturan proyek: tidak boleh inline style untuk spacing/width; gunakan class reusable `md-*`.
- Auto-submit filter: tambahkan delay kecil agar user bisa memilih dengan nyaman.
- Pastikan tidak ada horizontal scroll pada filter bar.

## Validation
- Buka `rekap_bulanan.php` di mobile viewport (375px).
- Pastikan filter bar Bulan/Tahun sejajar horizontal (tablet) / 2 kolom (mobile kecil) tanpa scroll horizontal.
- Pastikan dropdown Bulan/Tahun auto-submit saat diganti.
- Pastikan tombol "Edit Output"/"Tambah Output" full-width di mobile.
- Pastikan gambar lampiran full-width di mobile.
- Cek tidak ada error PHP/JS.

## Risks
- Perubahan struktur filter bar mungkin mempengaruhi tampilan desktop — pastikan class `md-filter-form` sudah ada CSS-nya untuk desktop.
- Auto-submit tanpa tombol visible bisa membingungkan user non-JS — sediakan `<noscript>` fallback.
