<?php
require_once __DIR__ . '/../helpers/session.php';
require_login();
require_once dirname(__DIR__, 2) . '/app/Config/database.php';

$renderPdf = isset($_GET['output']) && $_GET['output'] === 'pdf';
$downloadPdf = $renderPdf && isset($_GET['download']) && $_GET['download'] === '1';
$embeddedViewer = isset($_GET['viewer']) && $_GET['viewer'] === '1';
ob_start();

$uid = current_user_id();
$namaBulan = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

$mode = 'day';
$laporan_id = 0;
$filter_tanggal = '';
$filter_bulan = 0;
$filter_tahun = 0;

if (isset($_GET['id'])) {
    $mode = 'single';
    $laporan_id = (int)$_GET['id'];
} elseif (isset($_GET['bulan']) && isset($_GET['tahun'])) {
    $mode = 'month';
    $filter_bulan = (int)$_GET['bulan'];
    $filter_tahun = (int)$_GET['tahun'];
    if ($filter_bulan < 1 || $filter_bulan > 12) $filter_bulan = (int)date('m');
    if ($filter_tahun < 2000 || $filter_tahun > 2100) $filter_tahun = (int)date('Y');
} else {
    $mode = 'day';
    $filter_tanggal = $_GET['tanggal'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filter_tanggal) ||
        !DateTime::createFromFormat('Y-m-d', (string)$filter_tanggal)) {
        $filter_tanggal = date('Y-m-d');
    }
}

function tanggal_indonesia($tgl) {
    global $namaBulan;
    $pecah = explode('-', $tgl);
    if (count($pecah) !== 3) return $tgl;
    return (int)$pecah[2] . ' ' . $namaBulan[(int)$pecah[1]] . ' ' . $pecah[0];
}

function tanggal_indonesia_tanpa_tahun($tgl) {
    global $namaBulan;
    $pecah = explode('-', $tgl);
    if (count($pecah) !== 3) return $tgl;
    return sprintf('%02d %s', (int)$pecah[2], $namaBulan[(int)$pecah[1]]);
}

function tgl_hari_ini() {
    global $namaBulan;
    $d = getdate();
    return $d['mday'] . ' ' . $namaBulan[$d['mon']] . ' ' . $d['year'];
}

$stmtU = $mysqli->prepare('SELECT id, nama, nip, jabatan, unit_kerja, instansi FROM users WHERE id = ? LIMIT 1');
$stmtU->bind_param('i', $uid);
$stmtU->execute();
$user = $stmtU->get_result()->fetch_assoc();
$stmtU->close();

if (!$user) {
    die('Data pegawai tidak ditemukan.');
}

$whereSql = '';
$whereTypes = '';
$whereParams = [];
$titleTanggal = '';
$ttdTanggal = tgl_hari_ini();
$namaFileDokumen = '';

if ($mode === 'single') {
    $whereSql = 'lh.id = ? AND lh.user_id = ?';
    $whereTypes = 'ii';
    $whereParams = [$laporan_id, $uid];
    $titleTanggal = tgl_hari_ini();
    $namaFileDokumen = tanggal_indonesia_tanpa_tahun(date('Y-m-d'));
} elseif ($mode === 'month') {
    $start = $filter_tahun . '-' . str_pad($filter_bulan, 2, '0', STR_PAD_LEFT) . '-01';
    $end = $filter_tahun . '-' . str_pad($filter_bulan, 2, '0', STR_PAD_LEFT) . '-31';
    $whereSql = 'lh.user_id = ? AND lh.tanggal BETWEEN ? AND ?';
    $whereTypes = 'iss';
    $whereParams = [$uid, $start, $end];
    $titleTanggal = 'Bulan ' . $namaBulan[$filter_bulan] . ' Tahun ' . $filter_tahun;
    $namaFileDokumen = 'Laporan Bulan ' . $namaBulan[$filter_bulan];
} else {
    $whereSql = 'lh.user_id = ? AND lh.tanggal = ?';
    $whereTypes = 'is';
    $whereParams = [$uid, $filter_tanggal];
    $titleTanggal = tanggal_indonesia($filter_tanggal);
    $ttdTanggal = tanggal_indonesia($filter_tanggal);
    $namaFileDokumen = tanggal_indonesia_tanpa_tahun($filter_tanggal);
}

$sql = "SELECT lh.id AS laporan_id, lh.tanggal, lh.jam_mulai, lh.jam_selesai,
               lh.uraian_kegiatan, lh.hasil, lh.jumlah_kegiatan,
               COALESCE(rhk.nama_rhk, '-') AS nama_rhk
        FROM laporan_harian lh
        LEFT JOIN rencana_hasil_kerja rhk ON lh.rhk_id = rhk.id
        WHERE {$whereSql}
        ORDER BY lh.tanggal ASC, lh.jam_mulai ASC";

$stmtLap = $mysqli->prepare($sql);
if ($whereTypes && $whereParams) {
    $stmtLap->bind_param($whereTypes, ...$whereParams);
}
$stmtLap->execute();
$result_laporan = $stmtLap->get_result();

if ($mode === 'single' && $result_laporan->num_rows > 0) {
    $first = $result_laporan->fetch_assoc();
    $titleTanggal = tanggal_indonesia($first['tanggal']);
    $ttdTanggal = $titleTanggal;
    $namaFileDokumen = tanggal_indonesia_tanpa_tahun((string)$first['tanggal']);
    $result_laporan->data_seek(0);
}

$laporan_ids = [];
while ($row = $result_laporan->fetch_assoc()) {
    $laporan_ids[] = (int)$row['laporan_id'];
}
$result_laporan->data_seek(0);

$buktiMap = [];
if ($laporan_ids) {
    $inPlaceholders = implode(',', array_fill(0, count($laporan_ids), '?'));
    $stmtBukti = $mysqli->prepare("SELECT laporan_id, nama_file, path_file FROM bukti_kegiatan WHERE laporan_id IN ({$inPlaceholders}) ORDER BY id ASC");
    $types = str_repeat('i', count($laporan_ids));
    $stmtBukti->bind_param($types, ...$laporan_ids);
    $stmtBukti->execute();
    $resBukti = $stmtBukti->get_result();
    while ($b = $resBukti->fetch_assoc()) {
        if (!isset($buktiMap[$b['laporan_id']])) $buktiMap[$b['laporan_id']] = [];
        $buktiMap[$b['laporan_id']][] = $b;
    }
    $stmtBukti->close();
}

function imgToBase64($relPath) {
    if (!function_exists('asn_read_stored_bytes')) return '';
    $got = asn_read_stored_bytes($relPath);
    if (!$got || $got['data'] === '') return '';
    return 'data:' . $got['mime'] . ';base64,' . base64_encode($got['data']);
}

$tahun_laporan = $filter_tahun ?: (int)date('Y', strtotime($filter_tanggal ?: 'now'));
if ($mode === 'single' && isset($first['tanggal'])) {
    $tahun_laporan = (int)explode('-', $first['tanggal'])[0];
}

$logoPath = 'assets/images/logo_kemsos.png';
$logoSrc  = file_exists(dirname(__DIR__, 2) . '/public/' . $logoPath) ? imgToBase64($logoPath) : '';
$ttdPath = 'assets/images/ttd-candra.png';
$ttdSrc = file_exists(dirname(__DIR__, 2) . '/public/' . $ttdPath) ? imgToBase64($ttdPath) : '';

$stmtLap->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($namaFileDokumen) ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 12mm 14mm 12mm;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: none; }
            .page-break { margin-top: 0; }
            @page { margin: 12mm 12mm 14mm 12mm; size: A4 portrait; }
            html, body { width: 100%; margin: 0; padding: 0; }
        }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box; }
        html, body { width: 100%; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.35;
            margin: 0;
            padding: 0;
            background-color: #fff;
            width: 100%;
        }
        .page {
            width: 100%;
            padding: 0 14px;
        }
        .page-break { page-break-before: always; break-before: page; margin-top: 0; }
        .header-container {
            text-align: center;
            margin: 0 0 16px 0;
            width: 100%;
            border-bottom: none;
            padding: 10px 0;
            background-color: #D9D9D9;
        }
        .logo-instansi {
            width: 60px;
            height: auto;
            margin-bottom: 8px;
            display: inline-block;
        }
        .instansi-line {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.2;
        }
        .unitkerja-line {
            font-size: 9.5pt;
            margin-top: 4px;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .periode-laporan {
            font-size: 9.5pt;
            margin-top: 8px;
            line-height: 1.2;
        }
        .title-laporan {
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.4;
            margin: 8px auto 0 auto;
            padding: 2px 0 6px 0;
            display: inline-block;
            position: relative;
            white-space: nowrap;
        }
        .title-laporan::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            border-bottom: 1.5px solid #000;
        }
        .table-biodata {
            margin: 14px 0 12px 0;
            border-collapse: collapse;
            border-spacing: 0;
            font-size: 10pt;
            width: 100%;
        }
        .table-biodata td {
            padding: 1.5px 5px;
            vertical-align: top;
            line-height: 1.3;
        }
        .table-biodata td.label { width: 110px; padding-left: 0; }
        .table-biodata td.separator { width: 12px; text-align: center; padding: 1.5px 0; }
        .table-data {
            width: 100% !important;
            table-layout: fixed;
            border-collapse: collapse;
            border-spacing: 0;
            margin: 5px 0 0 0;
            font-size: 9.5pt;
        }
        .table-data th, .table-data td {
            border: 1px solid #000;
            padding: 4px 5px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .table-data th {
            font-weight: bold;
            text-align: center;
            background-color: #fff;
            page-break-inside: avoid;
        }
        .table-data td.text-center { text-align: center; }
        .date-group {
            background: #f8f9fa;
            font-weight: bold;
        }
        .td-semibold { font-weight: bold; }
        .small-muted { font-size: 8.5pt; color: #444; }
        .badge-inline {
            display: inline-block;
            border: 1px solid #146EF5;
            color: #0b3a80;
            background: #eaf0ff;
            padding: 1px 5px;
            font-size: 8pt;
            margin-top: 4px;
        }
        .ttd-container {
            width: 100%;
            margin-top: 32px;
            page-break-inside: avoid;
            position: relative;
        }
        .ttd-box {
            float: right;
            width: 240px;
            text-align: center;
            font-size: 10pt;
            padding: 0 10px 0 0;
        }
        .ttd-space { height: 10px; }
        .ttd-img {
            max-width: 140px;
            max-height: 60px;
            height: auto;
            width: auto;
            display: block;
            margin: 0 auto;
        }
        .clear { clear: both; }
        .bukti-list { margin: 0; padding-left: 18px; font-size: 9pt; }
        .bukti-list li { margin-bottom: 1px; }
        .doc-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 6px;
            margin: 0;
            padding: 0;
            width: 100%;
        }
        .doc-item {
            width: 100%;
            max-width: 100%;
            margin: 0 0 4px 0;
            break-inside: avoid;
            page-break-inside: avoid;
            box-sizing: border-box;
        }
        .doc-label {
            font-size: 8pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .doc-img {
            display: block;
            width: auto !important;
            max-width: 100% !important;
            height: 5.6cm;
            max-height: 5.6cm;
            object-fit: contain;
            background: #fff;
            border: none;
            padding: 0;
            box-sizing: border-box;
            margin: 0 auto;
            box-shadow: none;
            filter: none;
            -webkit-filter: none;
        }
        .doc-img-missing {
            height: 5.6cm;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 8pt;
            font-style: italic;
            border: 1px dashed #000;
            box-shadow: none;
            filter: none;
            -webkit-filter: none;
        }
        .doc-caption {
            font-size: 7.5pt;
            color: #475569;
            margin-top: 3px;
            word-break: break-all;
            line-height: 1.2;
        }
        .no-print {
            margin: 12px 0 20px 0;
            padding: 10px;
            background: #F5F7FA;
            border: 1px solid #cbd5e1;
            text-align: center;
            width: 100%;
        }
        .btn-print {
            background-color: #0284c7;
            color: #fff;
            border: none;
            padding: 7px 16px;
            font-size: 9.5pt;
            cursor: pointer;
        }
        .btn-print:hover { background-color: #0369a1; }
        .btn-back {
            background-color: #64748b;
            color: #fff;
            border: none;
            padding: 7px 16px;
            font-size: 9.5pt;
            cursor: pointer;
            margin-right: 8px;
        }
        .btn-back:hover { background-color: #475569; }
    </style>
    <script>
        function windowFocusAndPrint() {
            window.focus();
            setTimeout(function () {
                window.print();
            }, 350);
        }
    </script>
</head>
<body<?= (!$renderPdf && !$embeddedViewer) ? ' onload="windowFocusAndPrint()"' : '' ?>>
<div class="page">
    <?php if (!$renderPdf && !$embeddedViewer): ?>
    <div class="no-print">
        <button class="btn-back" onclick="window.location.href='laporan_harian.php'">&larr; Kembali</button>
        <button class="btn-print" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
    <?php endif; ?>

    <div class="header-container">
        <?php if ($logoSrc): ?>
            <img src="<?= $logoSrc ?>" alt="Logo" class="logo-instansi">
        <?php endif; ?>
        <div class="instansi-line"><?= htmlspecialchars($user['instansi']) ?></div>
        <div class="unitkerja-line"><?= htmlspecialchars($user['unit_kerja']) ?></div>
        <div class="title-laporan">LAPORAN HARIAN ASN P3K SEKOLAH RAKYAT</div>
    </div>

    <table class="table-biodata">
        <tr><td class="label">Nama</td><td class="separator">:</td><td><?= htmlspecialchars($user['nama']) ?></td></tr>
        <tr><td class="label">NIP</td><td class="separator">:</td><td><?= htmlspecialchars($user['nip']) ?></td></tr>
        <tr><td class="label">Jabatan</td><td class="separator">:</td><td><?= htmlspecialchars($user['jabatan']) ?></td></tr>
        <tr><td class="label">Unit Kerja</td><td class="separator">:</td><td><?= htmlspecialchars($user['unit_kerja']) ?></td></tr>
    </table>

    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <?php if ($mode === 'month'): ?><th style="width: 15%;">Tanggal</th><?php endif; ?>
                <th style="width: 40%;">Kegiatan</th>
                <th style="width: 40%;">Dokumentasi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result_laporan->num_rows > 0): ?>
                <?php $no = 1; $lastDate = ''; while ($row = $result_laporan->fetch_assoc()):
                    $lpid = (int)$row['laporan_id'];
                    $listBukti = $buktiMap[$lpid] ?? [];
                ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <?php if ($mode === 'month'): ?>
                            <td class="text-center"><?= (new DateTime($row['tanggal']))->format('d/m/Y') ?></td>
                        <?php endif; ?>
                        <td>
                            <?= nl2br(htmlspecialchars($row['uraian_kegiatan'])) ?>
                            <?php if (!empty($row['jumlah_kegiatan']) && (int)$row['jumlah_kegiatan'] > 1): ?>
                                <br><small>Jumlah: <?= (int)$row['jumlah_kegiatan'] ?> kegiatan</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($listBukti): ?>
                                <div class="doc-grid">
                                    <?php $bfn = 1; foreach ($listBukti as $b):
                                        $img64 = imgToBase64($b['path_file']);
                                        $hasImg = !empty($img64);
                                        if (!$hasImg) continue;
                                    ?>
                                        <div class="doc-item">
                                            <img src="<?= $img64 ?>" class="doc-img" alt="<?= htmlspecialchars($b['nama_file']) ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #aaa;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= $mode === 'month' ? 4 : 3 ?>" class="text-center" style="padding: 18px; color: #666;">
                        Tidak ada data laporan pada periode yang dipilih.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="ttd-container">
        <div class="ttd-box">
            <div style="text-align: left;">Singkawang, <?= $ttdTanggal ?></div><br>
            <?php if ($ttdSrc): ?>
                <div style="text-align: left; padding-left: 10px;">
                    <img src="<?= $ttdSrc ?>" alt="Tanda Tangan" class="ttd-img" style="max-width: 180px; max-height: 80px;">
                </div>
            <?php endif; ?>
            <div class="ttd-space"></div>
            <div style="text-align: left;"><strong><?= htmlspecialchars($user['nama']) ?></strong></div>
            <div style="text-align: left;">NIP. <?= htmlspecialchars($user['nip']) ?></div>
        </div>
        <div class="clear"></div>
    </div>
</div>
</body>
</html>
<?php
$__reportHtml = ob_get_clean();
if ($renderPdf) {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->loadHtml($__reportHtml, 'UTF-8');
    $dompdf->render();

    $__filename = $namaFileDokumen . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($downloadPdf ? 'attachment' : 'inline') . '; filename="' . $__filename . '"');
    echo $dompdf->output();
    exit;
}

echo $__reportHtml;
