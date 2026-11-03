# Desain Fitur Upload Beberapa Foto Dokumentasi Perjadin

## Ringkasan

Halaman `perjadin.php` akan ditambah kemampuan unggah beberapa foto pada setiap item `Lampiran Dokumentasi`. Foto hanya dipakai untuk sesi form yang sedang berjalan, tidak disimpan permanen ke server, dan ditampilkan pada pratinjau dokumen/lampiran setelah form disubmit.

## Tujuan

- Memungkinkan setiap item dokumentasi memiliki lebih dari satu foto.
- Menjaga alur form tetap sederhana: tanggal, keterangan, lalu unggah beberapa foto.
- Menampilkan hasil foto langsung pada lampiran pratinjau dokumen.
- Tidak membuat penyimpanan permanen atau dependensi database baru.

## Ruang Lingkup

Yang dikerjakan:

- Mengubah form menjadi `multipart/form-data`.
- Menambah input file `multiple` pada setiap blok dokumentasi.
- Membaca file upload gambar pada request yang sedang berjalan.
- Memvalidasi tipe file gambar umum.
- Mengubah tampilan lampiran agar satu item dokumentasi dapat menampilkan beberapa foto.

Yang tidak dikerjakan:

- Penyimpanan file ke folder proyek.
- Penyimpanan metadata ke database.
- Riwayat foto lintas refresh halaman tanpa submit ulang.

## Pendekatan Teknis

### 1. Struktur Input

Setiap blok dokumentasi memakai input file dengan pola array bertingkat agar relasi foto tetap per-item, misalnya:

`name="dokumentasi_foto_0[]"`, `name="dokumentasi_foto_1[]"`, dan seterusnya.

Saat item baru ditambahkan lewat JavaScript, index input file mengikuti nomor dokumentasi yang aktif.

### 2. Pemrosesan Backend

Backend `perjadin.php` akan:

- tetap memproses `tanggal` dan `keterangan` seperti sekarang,
- membaca `$_FILES` untuk setiap item dokumentasi,
- memfilter file kosong,
- memvalidasi MIME/ekstensi gambar yang diizinkan: `jpg`, `jpeg`, `png`, `webp`,
- mengubah file yang valid menjadi data URI sementara untuk ditampilkan di HTML pratinjau.

Karena foto hanya untuk sesi form, data URI dipilih agar tidak perlu penyimpanan fisik.

### 3. Tampilan Form

Pada setiap blok dokumentasi akan ada:

- input `Tanggal`,
- textarea `Dokumentasi`,
- input `Unggah Foto Dokumentasi` dengan atribut `multiple` dan `accept="image/*"`.

### 4. Tampilan Lampiran

Tabel lampiran tetap berbasis item dokumentasi, tetapi kolom dokumentasi akan menampilkan:

- teks keterangan dokumentasi,
- galeri foto kecil di bawahnya untuk semua foto pada item tersebut.

Urutan item tetap mengikuti urutan dokumentasi di form.

### 5. JavaScript Dinamis

Fungsi `addDokumentasi()` akan diperbarui agar blok dokumentasi baru ikut memiliki input file `multiple` dengan nama field yang benar berdasarkan index.

Fungsi `updateDokumentasiIndexes()` akan ikut memperbarui nama input file ketika urutan dokumentasi berubah setelah item dihapus.

## Error Handling

- File bukan gambar diabaikan dari pratinjau.
- Jika item dokumentasi tidak memiliki foto, item tetap tampil dengan teks dokumentasi saja.
- Jika browser tidak mengirim file untuk item tertentu, proses form tetap berjalan normal.

## Dampak UI

- Form bertambah satu field upload pada bagian `Lampiran Dokumentasi`.
- Lampiran pratinjau menjadi lebih informatif karena memuat beberapa foto per item.
- Tidak ada perubahan pada struktur halaman utama selain area dokumentasi.

## Pengujian

- Submit form tanpa foto: lampiran tetap tampil normal.
- Submit satu item dokumentasi dengan beberapa foto: semua foto muncul pada lampiran.
- Submit beberapa item dokumentasi dengan jumlah foto berbeda: tiap foto tetap terikat ke item yang benar.
- Tambah dan hapus blok dokumentasi secara dinamis: nama field upload tetap sinkron.
- File non-gambar tidak ikut dirender pada pratinjau.
