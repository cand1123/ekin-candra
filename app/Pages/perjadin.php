<?php
require_once __DIR__ . '/../helpers/session.php';
require_login();

// =========================================================================
// INISIALISASI DATA LAPORAN & FORMAT TANDA TANGAN
// =========================================================================

// Data Header
$header_judul_default = "LAPORAN KEGIATAN\nDUKUNGAN PENYELENGGARA OPERASIONAL SEKOLAH RAKYAT\nHOME VISIT SISWA MENGUNDURKAN DIRI (a.n. JUANDA & ARYA HIDAYATULLOH)\nSERTA PENDATAAN DAN PENGANGKUTAN ASET SRT 53 PONTIANAK.";
$header_tanggal_default = "2 s.d 4 Oktober 2026";
$header_instansi_default = "PUSAT PENDIDIKAN, PELATIHAN, DAN PENGEMBANGAN PROFESI\nKESEJAHTERAAN SOSIAL";

// 1. Dasar Pelaksanaan
$dasar_baku_list = [
    "Undang-Undang Nomor 17 Tahun 2003 tentang Keuangan Negara;",
    "Undang-Undang Nomor 1 Tahun 2004 tentang Perbendaharaan Negara;",
    "Undang-Undang Nomor 15 Tahun 2004 tentang Pemeriksaan Pengelolaan dan Tanggung Jawab Keuangan Negara;",
    "Instruksi Presiden Nomor 8 Tahun 2025 tentang Optimalisasi Pelaksanaan Pengentasan Kemiskinan dan Penghapusan Kemiskinan Ekstrem;",
    "Keputusan Menteri Sosial RI Nomor: 49/HUK/2025 tentang Tim Formatur Penyelenggaraan Sekolah Rakyat;"
];
$surat_tugas_default = "Surat Tugas Kepala SRT 03 Kalimantan Barat Nomor: ST/123/X/SRT-03/2026 tanggal 1 Oktober 2026.";

// 2. Waktu & Tempat
$waktu_tempat_default = "Kegiatan perjalanan dinas ini dilaksanakan selama 3 (tiga) hari kerja, terhitung mulai tanggal 2 s.d 4 Oktober 2026. Lokasi pelaksanaan kegiatan dipusatkan di Wilayah Kota Pontianak, Provinsi Kalimantan Barat, dengan fokus utama kunjungan ke kediaman siswa yang mengundurkan diri (a.n. Juanda & Arya Hidayatulloh) serta pelaksanaan pendataan dan pengangkutan aset operasional SRT 03 Kalimantan Barat. Selama tiga hari pelaksanaan, tim penugasan mendatangi keluarga siswa, melakukan koordinasi perihal alasan pengunduran diri, serta menginventarisasi dan memindahkan aset penunjang operasional sekolah. Pelaksanaan kegiatan ini berlangsung secara efisien dan terstruktur dengan memanfaatkan jam kerja efektif secara optimal demi mendukung akuntabilitas tata kelola sarana prasarana serta keberlanjutan program pengetasan kemiskinan berbasis pendidikan di wilayah tersebut.";

// 3. Tujuan Pelaksanaan
$tujuan_default = implode("\n", [
    "Melakukan home visit dan verifikasi alasan pengunduran diri siswa atas nama Juanda dan Arya Hidayatulloh.",
    "Memberikan konseling awal dan koordinasi dengan pihak keluarga terkait keberlanjutan pendidikan anak.",
    "Menginventarisasi seluruh daftar fisik aset milik SRT 03 Kalimantan Barat secara rinci dan terdata.",
    "Melakukan proses pengangkutan dan pengamanan aset SRT 03 Kalimantan Barat ke lokasi penyimpanan resmi.",
    "Menyusun berita acara penyerahan kembali sarana prasarana pendukung operasional sekolah.",
    "Menyusun rekomendasi tindak lanjut bagi Pusat Pendidikan, Pelatihan, dan Pengembangan Profesi Kesejahteraan Sosial."
]);

// 4. Petugas Pelaksana Sesuai Contoh Gambar
$petugas_default = [
    ["nama" => "AMIRUDIN, S.T., M.Pd", "nip" => "198104082010011015", "jabatan" => "Ketua Tim Pelaksana / Analis Kebijakan Muda"],
    ["nama" => "MUHAMMAD NUR RAHMAN, S.E", "nip" => "197802222025211025", "jabatan" => "Pengolah Data & Informasi"],
    ["nama" => "LALANG ATLETIKA, S.Pd., Gr", "nip" => "199903132025211056", "jabatan" => "Anggota Pelaksana"],
    ["nama" => "NOVIAN CANDRA KURNIAWAN, S.Pd., Gr", "nip" => "200111052025211020", "jabatan" => "Anggota Pelaksana"]
];

$dokumentasi_default = [
    ["tanggal" => "2 Oktober 2026", "foto" => []],
    ["tanggal" => "3 Oktober 2026", "foto" => []],
    ["tanggal" => "4 Oktober 2026", "foto" => []]
];

function dokumentasi_extension_valid($filename)
{
    $extension = strtolower((string)pathinfo((string)$filename, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    return in_array($extension, $allowedExtensions, true);
}

function dokumentasi_image_to_data_uri($tmpPath)
{
    if (!is_string($tmpPath) || $tmpPath === '' || !is_file($tmpPath)) {
        return '';
    }

    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false || empty($imageInfo['mime'])) {
        return '';
    }

    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($imageInfo['mime'], $allowedMime, true)) {
        return '';
    }

    $binary = @file_get_contents($tmpPath);
    if ($binary === false) {
        return '';
    }

    return 'data:' . $imageInfo['mime'] . ';base64,' . base64_encode($binary);
}

$bulan_id_map = [
    'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
    'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12
];

// Ubah teks tanggal header menjadi nilai awal pemilih tanggal (mulai & selesai)
function header_tanggal_ke_rentang($teks, array $bulanMap)
{
    $teks = trim((string)$teks);
    if ($teks === '') {
        return ['', ''];
    }

    // Rentang: "2 s.d 4 Oktober 2026"
    if (preg_match('/^\s*(\d{1,2})\s*(?:s\.?\s*d\.?|sampai|[-–—])\s*(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})\s*$/u', $teks, $m)) {
        $bulan = $bulanMap[strtolower($m[3])] ?? 0;
        if ($bulan > 0) {
            return [
                sprintf('%04d-%02d-%02d', (int)$m[4], $bulan, (int)$m[1]),
                sprintf('%04d-%02d-%02d', (int)$m[4], $bulan, (int)$m[2]),
            ];
        }
    }

    // Tanggal tunggal: "2 Oktober 2026"
    if (preg_match('/^\s*(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})\s*$/u', $teks, $m)) {
        $bulan = $bulanMap[strtolower($m[2])] ?? 0;
        if ($bulan > 0) {
            $tanggal = sprintf('%04d-%02d-%02d', (int)$m[3], $bulan, (int)$m[1]);
            return [$tanggal, $tanggal];
        }
    }

    return ['', ''];
}

// POST Processing
$header_judul = $_POST['header_judul'] ?? $header_judul_default;
$header_tanggal = $_POST['header_tanggal'] ?? $header_tanggal_default;
$header_instansi = $_POST['header_instansi'] ?? $header_instansi_default;

list($header_tanggal_mulai, $header_tanggal_selesai) = header_tanggal_ke_rentang($header_tanggal, $bulan_id_map);

$surat_tugas = trim($_POST['surat_tugas'] ?? $surat_tugas_default);
$dasar_list = $dasar_baku_list;
if ($surat_tugas !== '') {
    $dasar_list[] = $surat_tugas;
}

$waktu_tempat = $_POST['waktu_tempat'] ?? $waktu_tempat_default;

$tujuan_raw = $_POST['tujuan'] ?? $tujuan_default;
$tujuan_list = array_filter(array_map('trim', explode("\n", $tujuan_raw)));

if (isset($_POST['petugas_nama'])) {
    $petugas_list = [];
    for ($i = 0; $i < count($_POST['petugas_nama']); $i++) {
        if (!empty($_POST['petugas_nama'][$i])) {
            $petugas_list[] = [
                'nama' => $_POST['petugas_nama'][$i],
                'nip' => $_POST['petugas_nip'][$i] ?? '-',
                'jabatan' => $_POST['petugas_jabatan'][$i] ?? '-'
            ];
        }
    }
} else {
    $petugas_list = $petugas_default;
}

if (isset($_POST['dokumentasi_tanggal'])) {
    $dokumentasi_list = [];
    for ($i = 0; $i < count($_POST['dokumentasi_tanggal']); $i++) {
        $tanggal = trim($_POST['dokumentasi_tanggal'][$i] ?? '');
        $fotoList = [];
        $fotoField = 'dokumentasi_foto_' . $i;

        if (isset($_FILES[$fotoField]) && is_array($_FILES[$fotoField]['name'] ?? null)) {
            $fotoCount = count($_FILES[$fotoField]['name']);
            for ($j = 0; $j < $fotoCount; $j++) {
                $fotoName = $_FILES[$fotoField]['name'][$j] ?? '';
                $fotoTmp = $_FILES[$fotoField]['tmp_name'][$j] ?? '';
                $fotoError = $_FILES[$fotoField]['error'][$j] ?? UPLOAD_ERR_NO_FILE;

                if ($fotoError === UPLOAD_ERR_NO_FILE || $fotoName === '' || $fotoTmp === '') {
                    continue;
                }

                if ($fotoError !== UPLOAD_ERR_OK || !dokumentasi_extension_valid($fotoName)) {
                    continue;
                }

                $fotoSrc = dokumentasi_image_to_data_uri($fotoTmp);
                if ($fotoSrc !== '') {
                    $fotoList[] = [
                        'nama' => $fotoName,
                        'src' => $fotoSrc
                    ];
                }
            }
        }

        if ($tanggal !== '' || !empty($fotoList)) {
            $dokumentasi_list[] = [
                'tanggal' => $tanggal,
                'foto' => $fotoList
            ];
        }
    }
} else {
    $dokumentasi_list = $dokumentasi_default;
}

$subheading_1 = $_POST['subheading_1'] ?? "Pelaksanaan Home Visit Siswa Mengundurkan Diri";
$paragraf_1 = $_POST['paragraf_1'] ?? "Tim telah mendatangi rumah orang tua dari siswa a.n. Juanda dan Arya Hidayatulloh. Berdasarkan hasil wawancara lapangan, ditemukan faktor kendala keluarga yang menjadi pertimbangan utama pengunduran diri, dan data ini telah didokumentasikan dalam form evaluasi peserta.";

$subheading_2 = $_POST['subheading_2'] ?? "Pendataan & Inventarisasi Aset SRT 03 Kalimantan Barat";
$paragraf_2 = $_POST['paragraf_2'] ?? "Proses penatausahaan dan inventarisasi aset telah selesai dilaksanakan secara menyeluruh. Seluruh barang inventaris dicatat kondisi fisiknya dan disesuaikan dengan kartu inventaris barang (KIB) yang berlaku.";

$subheading_3 = $_POST['subheading_3'] ?? "Pengangkutan dan Pengamanan Aset";
$paragraf_3 = $_POST['paragraf_3'] ?? "Aset SRT 03 Kalimantan Barat telah diangkut menggunakan armada transportasi yang aman dan ditempatkan pada gudang penyimpanan resmi dengan berita acara serah terima yang ditandatangani oleh saksi setempat.";

$kesimpulan = $_POST['kesimpulan'] ?? "Secara umum kegiatan pendataan, pendampingan home visit, serta pemindahan aset SRT 03 Kalimantan Barat telah terlaksana sesuai rencana kerja tanpa kendala berarti. Seluruh dokumen pertanggungjawaban fisik dan administrasi telah siap ditindaklanjuti.";

$kepala_jabatan = $_POST['kepala_jabatan'] ?? "Kepala SRT 03 Kalimantan Barat,";
$kepala_nama = $_POST['kepala_nama'] ?? "AMIRUDIN, S.T., M.Pd";
$kepala_nip = $_POST['kepala_nip'] ?? "198104082010011015";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Perjalanan Dinas - Presisi Tanda Tangan</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            color: #0f172a;
        }

        .header-bar {
            background: #0f172a;
            color: #fff;
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-bar h1 { margin: 0; font-size: 17px; font-weight: 600; }

        .main-container {
            display: flex;
            gap: 24px;
            padding: 24px;
            max-width: 1750px;
            margin: 0 auto;
            height: calc(100vh - 60px);
        }

        .form-section {
            flex: 1;
            background: #ffffff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            overflow-y: auto;
        }

        .section-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .section-card-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 0;
            margin-bottom: 12px;
            text-transform: uppercase;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 6px;
        }

        .form-group { margin-bottom: 14px; }
        .form-group label { font-weight: 600; display: block; margin-bottom: 6px; font-size: 12px; color: #475569; }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            background: #ffffff;
        }

        .form-group input[type="file"] {
            padding: 8px 10px;
            height: auto;
            min-height: 0;
            background: #ffffff;
        }

        .date-range { display: flex; align-items: center; gap: 8px; }
        .date-range input[type="date"] { flex: 1 1 0; min-width: 0; }
        .date-range__sep { font-size: 12px; color: #64748b; }
        .date-range__hint { margin-top: 6px; font-size: 12px; color: #64748b; }

        .petugas-box {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        #petugas-container .petugas-box {
            min-height: 182px;
        }

        .petugas-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        #petugas-container .petugas-header {
            min-height: 24px;
        }

        #petugas-container .form-group input {
            height: 1.1cm;
            min-height: 1.1cm;
        }

        .btn-add { background: #059669; color: white; border: none; padding: 8px 14px; font-size: 12px; border-radius: 6px; cursor: pointer; width: 100%; }
        .btn-remove { background: #ef4444; color: white; border: none; padding: 4px 8px; font-size: 11px; border-radius: 4px; cursor: pointer; }
        .btn-submit { background: #2563eb; color: #fff; border: none; padding: 12px 20px; font-size: 14px; border-radius: 8px; cursor: pointer; width: 100%; }
        .btn-print { background: #2563eb; color: white; border: none; padding: 8px 18px; font-size: 13px; border-radius: 6px; cursor: pointer; }

        .header-actions { display: flex; align-items: center; gap: 10px; }
        .btn-ghost { background: transparent; color: #ffffff; border: 1px solid rgba(255,255,255,0.4); padding: 8px 14px; font-size: 13px; border-radius: 6px; cursor: pointer; }
        .btn-ghost:hover { background: rgba(255,255,255,0.12); }

        /* Mode fokus: sembunyikan panel form agar pratinjau tampil penuh */
        .main-container.focus-mode .form-section { display: none; }
        .main-container.focus-mode .preview-section { flex: 1; }

        /* Kanvas dokumen — disamakan dengan .pdf-preview-canvas */
        .preview-section {
            position: relative;
            flex: 1 1 auto;
            min-height: 0;
            overflow-x: auto;
            overflow-y: auto;
            padding: 24px 16px 40px;
            background: #e5e7eb;
            -webkit-overflow-scrolling: touch;
            border: none;
            border-radius: 0;
            display: block;
        }

        .preview-stack {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 32px;
            width: fit-content;
            min-width: 100%;
        }

        /* Lembar Kertas Dokumen */
        .paper {
            background: #fff;
            width: 210mm;
            max-width: 210mm;
            flex: 0 0 auto;
            min-height: 297mm;
            padding: 15mm 20mm 20mm 20mm;
            border: none;
            box-shadow: none;
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.65;
            color: #111827;
            height: fit-content;
            margin: 0 auto;
        }

        /* Kop Surat */
        .letterhead {
            width: 100%;
            margin-bottom: 12px;
        }
        .letterhead__image {
            display: block;
            width: 100%;
            height: auto;
        }

        .report-header {
            margin-bottom: 20px;
            text-align: center;
            border: none;
            background: #d7d7d7;
        }
        .report-header__top {
            padding: 18px 24px 22px 24px;
            background: #d7d7d7;
        }
        .report-header__title {
            font-weight: bold;
            font-size: 11pt;
            line-height: 1.35;
            white-space: pre-line;
            text-transform: uppercase;
            margin-bottom: 18px;
            color: #000000;
        }
        .report-header__date {
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 0;
            color: #000000;
        }
        .report-header__instansi {
            font-size: 11pt;
            line-height: 1.35;
            white-space: pre-line;
            color: #000000;
            font-weight: bold;
            padding: 10px 24px 8px 24px;
            background: #c8c8c8;
            border-top: none;
        }
        .doc-intro {
            margin-bottom: 14px;
            padding-bottom: 10px;
            font-weight: bold;
        }
        .doc-section { margin-top: 0; }
        .doc-section + .doc-section {
            margin-top: 12px;
            padding-top: 10px;
        }
        .sec-title {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-weight: bold;
            margin: 0 0 6px 0;
            line-height: 1.35;
        }
        .sec-title__number {
            flex: 0 0 auto;
            min-width: 28px;
        }
        .sec-title__text,
        .sec-subtitle__text {
            flex: 1 1 auto;
        }
        .sec-title__text {
            letter-spacing: 0.01em;
        }
        .sec-lead {
            margin-bottom: 5px;
        }
        .sec-body {
            margin-left: 36px;
            max-width: calc(100% - 36px);
        }
        .sec-list {
            margin-top: 0;
            margin-left: 36px;
            padding-left: 24px;
            max-width: calc(100% - 36px);
        }
        .sec-list li { text-align: justify; margin-bottom: 4px; }
        .sec-table-wrap {
            margin-left: 36px;
            width: calc(100% - 36px);
            max-width: calc(100% - 36px);
        }
        .sec-subtitle {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-weight: bold;
            margin-top: 8px;
            margin-left: 36px;
            max-width: calc(100% - 36px);
            line-height: 1.35;
        }
        .sec-subtitle__text {
            letter-spacing: 0.01em;
        }
        p.doc-p { text-align: justify; text-indent: 24px; margin-top: 4px; margin-bottom: 10px; }
        p.doc-no-indent { text-indent: 0 !important; }

        table.tbl-petugas { width: 100%; border-collapse: collapse; margin: 6px 0 0 0; }
        table.tbl-petugas th, table.tbl-petugas td { border: 1px solid #6b7280; padding: 0; font-size: 10pt; }
        table.tbl-petugas td { height: 1.1cm; }
        table.tbl-petugas { table-layout: fixed; }
        table.tbl-petugas th {
            background-color: #eceff3;
            text-align: center;
            font-weight: bold;
            letter-spacing: 0.03em;
        }
        .tbl-petugas__nama,
        .tbl-petugas__jabatan {
            white-space: normal;
            overflow-wrap: break-word;
            word-break: break-word;
            padding: 6px 8px !important;
            vertical-align: middle;
        }

        .tbl-lampiran {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .tbl-lampiran th,
        .tbl-lampiran td {
            border: 1px solid #6b7280;
            font-size: 10pt;
        }

        .tbl-lampiran th {
            padding: 4px 6px;
            text-align: center;
            font-weight: bold;
            background: #eceff3;
        }

        .tbl-lampiran td {
            padding: 8px 10px;
            vertical-align: top;
            height: auto;
        }

        .tbl-lampiran__tanggal {
            width: 28%;
            white-space: nowrap;
        }

        .lampiran-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .lampiran-gallery__item {
            width: calc(50% - 4px);
        }

        .lampiran-gallery__image {
            display: block;
            width: 100%;
            height: 120px;
            object-fit: cover;
            border: 1px solid #cbd5e1;
        }

        .lampiran-gallery__empty {
            font-style: italic;
            color: #64748b;
        }

        .paper--lampiran {
            page-break-before: always;
        }

        .lampiran-title {
            font-weight: bold;
            text-align: center;
            margin: 0 0 12px 0;
            text-transform: uppercase;
        }

        /* TANDA TANGAN DENGAN PRESISI GAMBAR CONTOH */
        .signature-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            border: none;
            margin-top: 22px;
            padding-top: 12px;
            border-top: none;
            page-break-inside: avoid;
        }

        .signature-table td {
            vertical-align: top;
            border: none;
            width: 50%;
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.25;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .signature-table__left {
            padding-right: 15px;
            padding-left: 36px;
        }

        .signature-table__right {
            padding-left: 15px;
        }

        .signature-head {
            min-height: 40px;
        }

        .signature-space {
            height: 54px;
        }

        .sign-block {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .sign-block:last-child {
            margin-bottom: 0;
        }

        .sign-name {
            font-weight: bold;
            text-decoration: none;
            display: block;
        }

        .sign-nip {
            margin-top: 2px;
            display: block;
        }

        .signature-head,
        .sign-name,
        .sign-nip {
            max-width: 100%;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        @media print {
            body {
                padding: 0;
                background: none;
                /* Agar blok berwarna (kop surat & judul) ikut tercetak seperti di pratinjau */
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .header-bar, .form-section { display: none !important; }
            .preview-section { background: none; padding: 0; overflow: visible; display: block; }
            .main-container { display: block; padding: 0; height: auto; }
            .preview-stack { display: block; }
            /* Geometri cetak disamakan dengan pratinjau:
               margin @page (15/20/20/20) menggantikan padding .paper di layar,
               sehingga area isi sama persis (170mm) dan tidak memicu penskalaan printer. */
            .paper {
                box-shadow: none;
                border: none;
                width: auto;
                max-width: none;
                min-height: 0;
                padding: 0;
                margin: 0;
            }
            .paper + .paper { page-break-before: always; }
            @page { size: A4; margin: 15mm 20mm 20mm 20mm; }
        }
    </style>
</head>
<body>

<div class="header-bar">
    <h1>Laporan Perjalanan Dinas - Format Baku Tanda Tangan</h1>
    <div class="header-actions">
        <button type="button" class="btn-ghost" id="toggleFormBtn" onclick="toggleFormPanel()">
            <span id="toggleFormLabel">Sembunyikan Form</span>
        </button>
        <button class="btn-print" onclick="window.print()">Cetak / Simpan Ke PDF</button>
    </div>
</div>

<div class="main-container">

    <div class="form-section">
        <form action="" method="POST" id="laporanForm" enctype="multipart/form-data">
            
            <div class="section-card">
                <div class="section-card-title">Header Banner</div>
                <div class="form-group">
                    <label>Judul Laporan Header:</label>
                    <textarea name="header_judul" rows="4"><?= htmlspecialchars($header_judul); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Tanggal Kegiatan Header:</label>
                    <div class="date-range">
                        <input type="date" id="headerTanggalMulai" value="<?= htmlspecialchars($header_tanggal_mulai); ?>" aria-label="Tanggal mulai">
                        <span class="date-range__sep">s.d</span>
                        <input type="date" id="headerTanggalSelesai" value="<?= htmlspecialchars($header_tanggal_selesai); ?>" aria-label="Tanggal selesai">
                    </div>
                    <input type="hidden" name="header_tanggal" id="headerTanggalText" value="<?= htmlspecialchars($header_tanggal); ?>">
                    <div class="date-range__hint" id="headerTanggalPreview"><?= htmlspecialchars($header_tanggal); ?></div>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Nama Instansi Header:</label>
                    <textarea name="header_instansi" rows="2"><?= htmlspecialchars($header_instansi); ?></textarea>
                </div>
            </div>

            <div class="section-card">
                <div class="section-card-title">1. Dasar Pelaksanaan - Surat Tugas</div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Surat Tugas:</label>
                    <textarea name="surat_tugas" rows="2"><?= htmlspecialchars($surat_tugas); ?></textarea>
                </div>
            </div>

            <div class="section-card">
                <div class="section-card-title">2. Waktu & Tempat</div>
                <div class="form-group" style="margin-bottom: 0;">
                    <textarea name="waktu_tempat" rows="5"><?= htmlspecialchars($waktu_tempat); ?></textarea>
                </div>
            </div>

            <div class="section-card">
                <div class="section-card-title">3. Tujuan Pelaksanaan</div>
                <div class="form-group" style="margin-bottom: 0;">
                    <textarea name="tujuan" rows="5"><?= htmlspecialchars($tujuan_raw); ?></textarea>
                </div>
            </div>

            <div class="section-card">
                <div class="section-card-title">4. Petugas Pelaksana</div>
                <div id="petugas-container">
                    <?php foreach ($petugas_list as $index => $p): ?>
                    <div class="petugas-box">
                        <div class="petugas-header">
                            <span style="font-weight: bold; font-size: 12px; color: #2563eb;">Petugas #<span class="petugas-index"><?= $index + 1; ?></span></span>
                            <?php if ($index > 0): ?>
                                <button type="button" class="btn-remove" onclick="removePetugas(this)">Hapus</button>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <input type="text" name="petugas_nama[]" placeholder="Nama Lengkap & Gelar" value="<?= htmlspecialchars($p['nama']); ?>" required>
                        </div>
                        <div class="form-group">
                            <input type="text" name="petugas_nip[]" placeholder="NIP" value="<?= htmlspecialchars($p['nip']); ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <input type="text" name="petugas_jabatan[]" placeholder="Jabatan" value="<?= htmlspecialchars($p['jabatan']); ?>">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-add" onclick="addPetugas()">+ Tambah Petugas</button>
            </div>

            <div class="section-card">
                <div class="section-card-title">5. Hasil Pelaksanaan</div>
                <div class="form-group">
                    <input type="text" name="subheading_1" value="<?= htmlspecialchars($subheading_1); ?>" style="margin-bottom: 6px;">
                    <textarea name="paragraf_1" rows="2"><?= htmlspecialchars($paragraf_1); ?></textarea>
                </div>
                <div class="form-group">
                    <input type="text" name="subheading_2" value="<?= htmlspecialchars($subheading_2); ?>" style="margin-bottom: 6px;">
                    <textarea name="paragraf_2" rows="2"><?= htmlspecialchars($paragraf_2); ?></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" name="subheading_3" value="<?= htmlspecialchars($subheading_3); ?>" style="margin-bottom: 6px;">
                    <textarea name="paragraf_3" rows="2"><?= htmlspecialchars($paragraf_3); ?></textarea>
                </div>
            </div>

            <div class="section-card">
                <div class="section-card-title">6. Kesimpulan</div>
                <div class="form-group" style="margin-bottom: 0;">
                    <textarea name="kesimpulan" rows="3"><?= htmlspecialchars($kesimpulan); ?></textarea>
                </div>
            </div>

            <div class="section-card">
                <div class="section-card-title">7. Pengaturan Tanda Tangan</div>
                <div class="form-group">
                    <label>Jabatan Kepala (Mengetahui):</label>
                    <input type="text" name="kepala_jabatan" value="<?= htmlspecialchars($kepala_jabatan); ?>">
                </div>
                <div class="form-group">
                    <label>Nama Kepala:</label>
                    <input type="text" name="kepala_nama" value="<?= htmlspecialchars($kepala_nama); ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>NIP Kepala:</label>
                    <input type="text" name="kepala_nip" value="<?= htmlspecialchars($kepala_nip); ?>">
                </div>
            </div>

            <div class="section-card">
                <div class="section-card-title">8. Lampiran Dokumentasi</div>
                <div id="dokumentasi-container">
                    <?php foreach ($dokumentasi_list as $index => $dokumentasi): ?>
                    <div class="petugas-box">
                        <div class="petugas-header">
                            <span style="font-weight: bold; font-size: 12px; color: #2563eb;">Dokumentasi #<span class="dokumentasi-index"><?= $index + 1; ?></span></span>
                            <?php if ($index > 0): ?>
                                <button type="button" class="btn-remove" onclick="removeDokumentasi(this)">Hapus</button>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <input type="text" name="dokumentasi_tanggal[]" placeholder="Tanggal" value="<?= htmlspecialchars($dokumentasi['tanggal']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Unggah Foto Dokumentasi</label>
                            <input
                                type="file"
                                name="dokumentasi_foto_<?= $index ?>[]"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                data-dokumentasi-foto-input
                            >
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-add" onclick="addDokumentasi()">+ Tambah Dokumentasi</button>
            </div>

            <button type="submit" class="btn-submit">Perbarui Pratinjau Laporan</button>
        </form>
    </div>

    <!-- PRATINJAU DOKUMEN -->
    <div class="preview-section">
        <div class="preview-stack">
        <div class="paper">
            
            <div class="letterhead">
                <img
                    class="letterhead__image"
                    src="assets/images/Screenshot%202026-10-27%20230102.png"
                    alt="Kop surat Sekolah Rakyat Terintegrasi 3 Provinsi Kalimantan Barat"
                >
            </div>

            <div class="report-header">
                <div class="report-header__top">
                    <div class="report-header__title"><?= htmlspecialchars($header_judul); ?></div>
                    <div class="report-header__date"><?= htmlspecialchars($header_tanggal); ?></div>
                </div>
                <div class="report-header__instansi"><?= htmlspecialchars($header_instansi); ?></div>
            </div>

            <p class="doc-p doc-no-indent doc-intro">Kepada Yth.<br>Kepala Pusat Pendidikan, Pelatihan dan Pengembangan Profesi<br>Berikut disampaikan laporan kegiatan:</p>

            <div class="doc-section">
                <div class="sec-title">
                    <span class="sec-title__number">1.</span>
                    <span class="sec-title__text">Dasar Pelaksanaan Kegiatan</span>
                </div>
                <p class="doc-p doc-no-indent sec-body sec-lead">Yang menjadi dasar dari kegiatan ini adalah:</p>
                <ol class="sec-list">
                    <?php foreach ($dasar_list as $item): ?>
                        <li><?= htmlspecialchars($item); ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <div class="doc-section">
                <div class="sec-title">
                    <span class="sec-title__number">2.</span>
                    <span class="sec-title__text">Waktu dan Tempat Pelaksanaan</span>
                </div>
                <p class="doc-p sec-body"><?= htmlspecialchars($waktu_tempat); ?></p>
            </div>

            <div class="doc-section">
                <div class="sec-title">
                    <span class="sec-title__number">3.</span>
                    <span class="sec-title__text">Tujuan Pelaksanaan</span>
                </div>
                <ol class="sec-list">
                    <?php foreach ($tujuan_list as $tujuan): ?>
                        <li><?= htmlspecialchars($tujuan); ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <div class="doc-section">
                <div class="sec-title">
                    <span class="sec-title__number">4.</span>
                    <span class="sec-title__text">Petugas Pelaksana</span>
                </div>
                <div class="sec-table-wrap">
                    <table class="tbl-petugas">
                        <thead>
                            <tr>
                                <th style="width: 36%;">NAMA</th>
                                <th style="width: 27%;">NIP</th>
                                <th style="width: 37%;">JABATAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($petugas_list as $p): ?>
                            <tr>
                                <td class="tbl-petugas__nama"><b><?= htmlspecialchars($p['nama']); ?></b></td>
                                <td style="text-align: center;"><?= htmlspecialchars($p['nip']); ?></td>
                                <td class="tbl-petugas__jabatan"><?= htmlspecialchars($p['jabatan']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="doc-section">
                <div class="sec-title">
                    <span class="sec-title__number">5.</span>
                    <span class="sec-title__text">Hasil Pelaksanaan Kegiatan</span>
                </div>
                <div class="sec-subtitle">
                    <span class="sec-subtitle__text"><?= htmlspecialchars($subheading_1); ?></span>
                </div>
                <p class="doc-p sec-body"><?= htmlspecialchars($paragraf_1); ?></p>

                <div class="sec-subtitle">
                    <span class="sec-subtitle__text"><?= htmlspecialchars($subheading_2); ?></span>
                </div>
                <p class="doc-p sec-body"><?= htmlspecialchars($paragraf_2); ?></p>

                <div class="sec-subtitle">
                    <span class="sec-subtitle__text"><?= htmlspecialchars($subheading_3); ?></span>
                </div>
                <p class="doc-p sec-body"><?= htmlspecialchars($paragraf_3); ?></p>
            </div>

            <div class="doc-section">
                <div class="sec-title">
                    <span class="sec-title__number">6.</span>
                    <span class="sec-title__text">Kesimpulan</span>
                </div>
                <p class="doc-p sec-body"><?= htmlspecialchars($kesimpulan); ?></p>
            </div>

            <!-- SECTION TANDA TANGAN (FORMAT BAKU SESUAI GAMBAR SAMPER) -->
            <table class="signature-table">
                <tr>
                    <!-- Sebelah Kiri: Mengetahui -->
                    <td class="signature-table__left">
                        <div class="signature-head">
                            Mengetahui<br>
                            <?= htmlspecialchars($kepala_jabatan); ?>
                        </div>
                        <div class="sign-block">
                            <div class="signature-space"></div>
                            <div class="sign-name"><?= htmlspecialchars($kepala_nama); ?></div>
                            <div class="sign-nip">NIP. <?= htmlspecialchars($kepala_nip); ?></div>
                        </div>
                    </td>

                    <!-- Sebelah Kanan: Yang Melaksanakan Perjalanan Dinas -->
                    <td class="signature-table__right">
                        <div class="signature-head">
                            Yang Melaksanakan Perjalanan Dinas
                        </div>

                        <?php foreach ($petugas_list as $index => $p): ?>
                            <div class="sign-block">
                                <div class="signature-space"></div>
                                <div class="sign-name"><?= htmlspecialchars($p['nama']); ?></div>
                                <div class="sign-nip">NIP. <?= htmlspecialchars($p['nip']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>

        </div>
        <div class="paper paper--lampiran">
            <p class="lampiran-title">Lampiran Dokumentasi</p>
            <table class="tbl-lampiran">
                <thead>
                    <tr>
                        <th class="tbl-lampiran__tanggal">TANGGAL</th>
                        <th>FOTO DOKUMENTASI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dokumentasi_list as $dokumentasi): ?>
                    <tr>
                        <td class="tbl-lampiran__tanggal"><?= htmlspecialchars($dokumentasi['tanggal']); ?></td>
                        <td>
                            <?php if (!empty($dokumentasi['foto'])): ?>
                                <div class="lampiran-gallery">
                                    <?php foreach ($dokumentasi['foto'] as $foto): ?>
                                        <div class="lampiran-gallery__item">
                                            <img
                                                class="lampiran-gallery__image"
                                                src="<?= htmlspecialchars($foto['src']); ?>"
                                                alt="<?= htmlspecialchars($foto['nama']); ?>"
                                            >
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="lampiran-gallery__empty">Belum ada foto dokumentasi.</div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>

</div>

<script>
function toggleFormPanel() {
    const container = document.querySelector('.main-container');
    const label = document.getElementById('toggleFormLabel');
    if (!container) return;

    const hidden = container.classList.toggle('focus-mode');
    if (label) {
        label.textContent = hidden ? 'Tampilkan Form' : 'Sembunyikan Form';
    }
}

function addPetugas() {
    const container = document.getElementById('petugas-container');
    const count = container.querySelectorAll('.petugas-box').length + 1;

    const box = document.createElement('div');
    box.className = 'petugas-box';
    box.innerHTML = `
        <div class="petugas-header">
            <span style="font-weight: bold; font-size: 12px; color: #2563eb;">Petugas #<span class="petugas-index">${count}</span></span>
            <button type="button" class="btn-remove" onclick="removePetugas(this)">Hapus</button>
        </div>
        <div class="form-group">
            <input type="text" name="petugas_nama[]" placeholder="Nama Lengkap & Gelar" required>
        </div>
        <div class="form-group">
            <input type="text" name="petugas_nip[]" placeholder="NIP">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <input type="text" name="petugas_jabatan[]" placeholder="Jabatan">
        </div>
    `;

    container.appendChild(box);
    updatePetugasIndexes();
}

function removePetugas(button) {
    const box = button.closest('.petugas-box');
    box.remove();
    updatePetugasIndexes();
}

function updatePetugasIndexes() {
    const container = document.getElementById('petugas-container');
    const boxes = container ? container.querySelectorAll('.petugas-box') : [];
    boxes.forEach((box, index) => {
        const titleIndex = box.querySelector('.petugas-index');
        if (titleIndex) {
            titleIndex.textContent = index + 1;
        }
    });
}

const BULAN_ID = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

function formatTanggalIndonesia(tanggal) {
    return tanggal.getDate() + ' ' + BULAN_ID[tanggal.getMonth()] + ' ' + tanggal.getFullYear();
}

function parseTanggalInput(nilai) {
    const bagian = nilai.split('-');
    return new Date(parseInt(bagian[0], 10), parseInt(bagian[1], 10) - 1, parseInt(bagian[2], 10));
}

// Sinkronkan rentang tanggal header: teks header + baris Tanggal pada Lampiran Dokumentasi
function sinkronisasiRentangTanggal() {
    const mulaiEl = document.getElementById('headerTanggalMulai');
    const selesaiEl = document.getElementById('headerTanggalSelesai');
    if (!mulaiEl || !mulaiEl.value) return;

    const mulai = mulaiEl.value;
    let selesai = (selesaiEl && selesaiEl.value) ? selesaiEl.value : mulai;
    if (selesai < mulai) {
        selesai = mulai;
        if (selesaiEl) selesaiEl.value = mulai;
    }

    const tanggalMulai = parseTanggalInput(mulai);
    const tanggalSelesai = parseTanggalInput(selesai);

    let teks;
    if (mulai === selesai) {
        teks = formatTanggalIndonesia(tanggalMulai);
    } else if (tanggalMulai.getMonth() === tanggalSelesai.getMonth()
        && tanggalMulai.getFullYear() === tanggalSelesai.getFullYear()) {
        teks = tanggalMulai.getDate() + ' s.d ' + formatTanggalIndonesia(tanggalSelesai);
    } else {
        teks = formatTanggalIndonesia(tanggalMulai) + ' s.d ' + formatTanggalIndonesia(tanggalSelesai);
    }

    const teksEl = document.getElementById('headerTanggalText');
    if (teksEl) teksEl.value = teks;

    const previewEl = document.getElementById('headerTanggalPreview');
    if (previewEl) previewEl.textContent = teks;

    // Sesuaikan jumlah baris Lampiran Dokumentasi dengan jumlah hari pada rentang
    const container = document.getElementById('dokumentasi-container');
    if (!container) return;

    const jumlahHari = Math.round((tanggalSelesai - tanggalMulai) / 86400000) + 1;
    let boxes = container.querySelectorAll('.petugas-box');

    while (boxes.length < jumlahHari) {
        addDokumentasi();
        boxes = container.querySelectorAll('.petugas-box');
    }

    // Baris berlebih dihapus dari bawah, kecuali baris tersebut sudah ada foto terunggah
    while (boxes.length > jumlahHari) {
        const barisTerakhir = boxes[boxes.length - 1];
        const fotoInput = barisTerakhir.querySelector('[data-dokumentasi-foto-input]');
        if (fotoInput && fotoInput.files && fotoInput.files.length > 0) break;
        barisTerakhir.remove();
        boxes = container.querySelectorAll('.petugas-box');
    }

    updateDokumentasiIndexes();

    // Isi Tanggal tiap baris berurutan per hari dalam rentang
    container.querySelectorAll('[name="dokumentasi_tanggal[]"]').forEach((input, index) => {
        const tanggal = new Date(tanggalMulai.getTime());
        tanggal.setDate(tanggal.getDate() + index);
        input.value = formatTanggalIndonesia(tanggal);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const mulaiEl = document.getElementById('headerTanggalMulai');
    const selesaiEl = document.getElementById('headerTanggalSelesai');
    if (mulaiEl) mulaiEl.addEventListener('change', sinkronisasiRentangTanggal);
    if (selesaiEl) selesaiEl.addEventListener('change', sinkronisasiRentangTanggal);
});

function addDokumentasi() {
    const container = document.getElementById('dokumentasi-container');
    const count = container.querySelectorAll('.petugas-box').length + 1;
    const fieldIndex = count - 1;

    const box = document.createElement('div');
    box.className = 'petugas-box';
    box.innerHTML = `
        <div class="petugas-header">
            <span style="font-weight: bold; font-size: 12px; color: #2563eb;">Dokumentasi #<span class="dokumentasi-index">${count}</span></span>
            <button type="button" class="btn-remove" onclick="removeDokumentasi(this)">Hapus</button>
        </div>
        <div class="form-group">
            <input type="text" name="dokumentasi_tanggal[]" placeholder="Tanggal">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label>Unggah Foto Dokumentasi</label>
            <input
                type="file"
                name="dokumentasi_foto_${fieldIndex}[]"
                accept="image/jpeg,image/png,image/webp"
                multiple
                data-dokumentasi-foto-input
            >
        </div>
    `;

    container.appendChild(box);
    updateDokumentasiIndexes();
}

function removeDokumentasi(button) {
    const box = button.closest('.petugas-box');
    box.remove();
    updateDokumentasiIndexes();
}

function updateDokumentasiIndexes() {
    const container = document.getElementById('dokumentasi-container');
    const boxes = container ? container.querySelectorAll('.petugas-box') : [];
    boxes.forEach((box, index) => {
        const titleIndex = box.querySelector('.dokumentasi-index');
        if (titleIndex) {
            titleIndex.textContent = index + 1;
        }
        const fotoInput = box.querySelector('[data-dokumentasi-foto-input]');
        if (fotoInput) {
            fotoInput.name = 'dokumentasi_foto_' + index + '[]';
        }
        const removeButton = box.querySelector('.btn-remove');
        if (removeButton) {
            removeButton.style.display = index === 0 ? 'none' : '';
        }
    });
}
</script>

</body>
</html>
