# Perjadin Upload Dokumentasi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan form unggah beberapa foto per item dokumentasi pada `perjadin.php` dan menampilkannya di lampiran pratinjau tanpa penyimpanan permanen.

**Architecture:** Semua perubahan tetap di satu file `perjadin.php` mengikuti pola file saat ini. File upload diproses dari request `POST`, divalidasi sebagai gambar, lalu dikonversi menjadi data URI sementara agar dapat dirender di lampiran tanpa disimpan ke server.

**Tech Stack:** PHP 7.3, HTML form multipart, JavaScript DOM vanilla, CSS inline.

---

### Task 1: Ubah Form Dokumentasi Menjadi Upload Beberapa Foto

**Files:**
- Modify: `c:\laragon\www\asn-candra\perjadin.php`

- [ ] **Step 1: Tambah atribut multipart pada form**
- [ ] **Step 2: Tambah input file multiple pada setiap blok dokumentasi**
- [ ] **Step 3: Perbarui JavaScript addDokumentasi agar blok baru punya field upload**
- [ ] **Step 4: Perbarui updateDokumentasiIndexes agar nama field file tetap sinkron**

### Task 2: Proses Upload Foto di Backend

**Files:**
- Modify: `c:\laragon\www\asn-candra\perjadin.php`

- [ ] **Step 1: Tambah helper validasi tipe gambar**
- [ ] **Step 2: Baca file upload per item dokumentasi dari `$_FILES`**
- [ ] **Step 3: Filter file kosong dan file non-gambar**
- [ ] **Step 4: Ubah file valid menjadi data URI sementara**
- [ ] **Step 5: Gabungkan daftar foto ke struktur `$dokumentasi_list`**

### Task 3: Render Foto di Lampiran Dokumentasi

**Files:**
- Modify: `c:\laragon\www\asn-candra\perjadin.php`

- [ ] **Step 1: Tambah CSS galeri foto lampiran**
- [ ] **Step 2: Render keterangan dan beberapa foto dalam kolom dokumentasi**
- [ ] **Step 3: Pastikan item tanpa foto tetap tampil normal**

### Task 4: Verifikasi

**Files:**
- Modify: `c:\laragon\www\asn-candra\perjadin.php`

- [ ] **Step 1: Lint `perjadin.php` dengan PHP 7.3**
- [ ] **Step 2: Review cepat agar form dokumentasi benar-benar berubah dari textarea-only menjadi upload + preview**
