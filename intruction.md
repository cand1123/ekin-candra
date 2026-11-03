# FINAL INSTRUCTION --- Redesign Sistem Laporan ASN

## Tujuan

Redesign seluruh antarmuka aplikasi **Sistem Laporan ASN** agar modern,
compact, konsisten, dan profesional berdasarkan referensi visual.
Terapkan perubahan pada kode proyek yang ada, bukan hanya membuat mockup
atau halaman contoh.

## Aturan utama

1.  Audit seluruh struktur proyek sebelum mengedit: framework, entry
    point, layout, halaman, komponen, stylesheet, aset, routing, dan
    alur data.
2.  Ubah semua file yang relevan dengan UI, termasuk layout bersama,
    halaman, komponen, CSS/SCSS, JavaScript/TypeScript, template, dan
    aset bila diperlukan.
3.  Pertahankan seluruh fitur, route, validasi, API, query database,
    nama field, autentikasi, dan alur bisnis yang sudah berjalan.
4.  Jangan mengganti stack teknologi atau menambah dependency tanpa
    alasan kuat.
5.  Jangan mengganti data asli dengan data dummy.
6.  Jangan menghapus kode lama sebelum memastikan kode tersebut tidak
    dipakai.
7.  Jalankan build, lint, atau test yang tersedia dan perbaiki error
    yang disebabkan perubahan.

## Arah desain

-   Gaya dashboard admin minimalis, datar, bersih, compact, dan
    profesional.
-   Warna utama: putih, abu-abu muda, teks navy/abu-abu gelap, dan biru
    sebagai aksen.
-   Biru digunakan untuk tombol utama, menu aktif, tautan penting, dan
    indikator interaksi.
-   Merah hanya untuk aksi berisiko seperti Hapus/Delete.
-   Gunakan garis pembatas tipis dan bayangan sangat halus jika
    diperlukan.
-   **Hindari rounded border berlebihan.** Utamakan sudut persegi atau
    radius kecil yang konsisten.
-   Hindari gradien mencolok, dekorasi berlebihan, ikon yang tidak
    konsisten, dan ruang kosong yang terlalu besar.
-   Gunakan tipografi sans-serif yang sudah tersedia dalam proyek.

## Layout global

-   Pertahankan sidebar di kiri dan topbar di atas.
-   Sidebar memuat identitas **Sistem Laporan ASN** dan menu Dashboard,
    Rekap Kegiatan, Kegiatan, Rencana Aksi, Setting RHK, dan Keluar.
-   Menu aktif menggunakan latar biru muda dan indikator biru yang
    sederhana.
-   Topbar tetap ringkas, memuat notifikasi dan profil pengguna.
-   Footer sederhana dengan copyright serta tautan Privasi, Ketentuan,
    dan Bantuan jika tersedia.
-   Terapkan layout responsif untuk desktop, tablet, dan ponsel. Pada
    layar kecil, sidebar boleh menjadi drawer/menu ringkas.

## Halaman Laporan Harian

Pertahankan seluruh fungsi dan data yang ada, dengan struktur berikut.

### Header

-   Judul `Laporan Harian`.
-   Deskripsi `Kelola data laporan kegiatan harian Anda.`
-   Tombol `+ Tambah Laporan` di sisi kanan desktop; sesuaikan pada
    layar kecil.

### Filter

-   Tanggal, bulan, tahun, pencarian, tombol Reset, dan tombol Cetak.
-   Susun satu baris pada desktop jika ruang cukup.
-   Pada tablet/ponsel, biarkan kontrol membungkus ke beberapa baris
    tanpa overflow.
-   Gunakan tinggi kontrol, jarak, dan ukuran font yang compact.

### Ringkasan harian

-   Tampilkan tanggal laporan, jumlah laporan, jumlah kegiatan, dan
    total durasi berdasarkan data asli.
-   Tampilkan tombol `Cetak Harian` jika fitur tersebut sudah tersedia.
-   Ringkasan harus ringkas dan tidak mengalahkan isi laporan.

### Daftar laporan: bukan tabel

-   **Jangan menggunakan HTML table atau tampilan bergaya tabel untuk
    daftar laporan.**
-   Gunakan satu baris/kartu horizontal yang compact untuk setiap
    laporan.
-   Area kiri: tanggal dalam blok kecil.
-   Area tengah: judul kegiatan, uraian, jam mulai--selesai, jumlah
    kegiatan, jumlah bukti, dan tanggal terkait.
-   Area kanan: tombol Edit, Print, dan Delete.
-   Gunakan pemisah tipis antararea hanya jika membantu keterbacaan.
-   Hindari padding besar dan tinggi kartu yang berlebihan.
-   Uraian panjang harus membungkus secara wajar tanpa menimpa tombol.
-   Pada ponsel, susun informasi secara vertikal dan letakkan tombol
    aksi pada bagian bawah item.
-   Gunakan indikator metadata ringkas: biru muda untuk
    waktu/tanggal/jumlah kegiatan, hijau lembut untuk bukti, merah hanya
    untuk Delete.
-   Jangan menambahkan kolom `Waktu`, `Info`, atau struktur kolom tabel.

## Tombol dan interaksi

-   Tombol compact dengan tinggi konsisten.
-   Tombol utama: biru solid.
-   Tombol sekunder: putih dengan border abu-abu tipis.
-   Tombol Delete: teks/border merah dan tetap memakai konfirmasi jika
    sudah ada.
-   Semua tombol harus mempertahankan aksi aslinya; jangan membuat
    kontrol visual tanpa fungsi.
-   Pertahankan loading, empty, error, dan notifikasi yang sudah
    tersedia.

## Tipografi dan spacing

-   Judul halaman desktop sekitar 24--30 px.
-   Judul kegiatan sekitar 14--16 px.
-   Isi dan metadata sekitar 12--14 px, tanpa mengorbankan keterbacaan.
-   Gunakan spacing yang konsisten dan kurangi margin/padding yang tidak
    perlu.
-   Pastikan kontras teks cukup dan fokus keyboard terlihat.

## Cakupan seluruh proyek

1.  Petakan seluruh file dan identifikasi file yang memengaruhi tampilan
    bersama.
2.  Perbarui semua halaman yang memakai layout atau komponen bersama
    agar desain tetap konsisten.
3.  Gunakan variabel/token warna, spacing, border, radius, dan tipografi
    jika sesuai dengan stack proyek.
4.  Hindari duplikasi CSS.
5.  Jangan mengubah backend, database, atau endpoint kecuali benar-benar
    diperlukan.
6.  Pastikan halaman lain tidak rusak akibat perubahan style global.

## Langkah kerja wajib

1.  Audit struktur proyek dan alur data.
2.  Identifikasi file yang akan diubah.
3.  Implementasikan desain pada seluruh file yang relevan.
4.  Pastikan semua route dan fitur lama tetap berjalan.
5.  Jalankan formatter, lint, test, atau build yang tersedia.
6.  Periksa tampilan desktop dan mobile.
7.  Perbaiki error yang muncul.
8.  Laporkan daftar file yang diubah, ringkasan perubahan, hasil
    pengujian, dan kendala tersisa.

## Kriteria penerimaan

-   UI modern, compact, rapi, dan konsisten.
-   Daftar laporan berbentuk baris/kartu horizontal, bukan tabel.
-   Tidak ada rounded border berlebihan.
-   Sidebar dan topbar konsisten pada seluruh halaman.
-   Semua fitur lama tetap bekerja dan data berasal dari sumber asli.
-   Seluruh file relevan diperbarui, bukan hanya satu halaman atau
    mockup.
-   Tidak ada error build/lint baru akibat perubahan.
-   Tampilan responsif tanpa horizontal overflow.
-   Tidak ada klaim bahwa semua file telah diubah jika implementasi
    belum dilakukan.

## Output akhir

Setelah implementasi, berikan: 1. Daftar file yang diubah dan alasan
singkat. 2. Ringkasan perubahan desain. 3. Hasil build/test/lint. 4.
Masalah yang belum terselesaikan, jika ada.
