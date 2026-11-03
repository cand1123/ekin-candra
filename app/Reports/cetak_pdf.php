<?php
require_once __DIR__ . '/../helpers/session.php';
require_login();
require_once dirname(__DIR__, 2) . '/app/Config/database.php';

$renderPdf = isset($_GET['output']) && $_GET['output'] === 'pdf';
$downloadPdf = $renderPdf && isset($_GET['download']) && $_GET['download'] === '1';
$embeddedViewer = isset($_GET['viewer']) && $_GET['viewer'] === '1';
ob_start();

$uid = current_user_id();

$namaBulanPanjang = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
$namaBulan = [
    1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
    'Jul', 'Agu', 'Sept', 'Okt', 'Nov', 'Des'
];

$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));

if ($bulan < 1 || $bulan > 12) {
    $bulan = (int)date('m');
}
if ($tahun < 2000 || $tahun > 2100) {
    $tahun = (int)date('Y');
}

$startDate  = sprintf('%04d-%02d-01', $tahun, $bulan);
$endDate    = date('Y-m-t', strtotime($startDate));
$labelBulan = 'Bulan ' . $namaBulanPanjang[$bulan] . ' Tahun ' . $tahun;
$namaFileDokumen = 'Laporan Bulan ' . $namaBulanPanjang[$bulan];

function tanggal_indonesia($tgl, $withTime = false)
{
    global $namaBulan;
    $pecah = explode('-', $tgl);
    if (count($pecah) !== 3) {
        return $tgl;
    }
    $res = sprintf('%02d', (int)$pecah[2]) . ' ' . $namaBulan[(int)$pecah[1]] . ' ' . $pecah[0];
    if ($withTime) {
        $res .= ' ' . date('H:i', strtotime($tgl));
    }
    return $res;
}

function tgl_hari_ini()
{
    global $namaBulanPanjang;
    $d = getdate();
    return sprintf('%02d', $d['mday']) . ' ' . $namaBulanPanjang[$d['mon']] . ' ' . $d['year'];
}

function imgToBase64($relPath)
{
    if (!function_exists('asn_read_stored_bytes')) {
        return '';
    }
    $got = asn_read_stored_bytes($relPath);
    if (!$got || $got['data'] === '') {
        return '';
    }
    return 'data:' . $got['mime'] . ';base64,' . base64_encode($got['data']);
}

$stmtUser = $mysqli->prepare(
    'SELECT nama, nip, jabatan, unit_kerja, instansi FROM users WHERE id = ? LIMIT 1'
);
$stmtUser->bind_param('i', $uid);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

if (!$user) {
    $user = [
        'nama'       => '-',
        'nip'        => '-',
        'jabatan'    => '-',
        'unit_kerja' => '-',
        'instansi'   => '',
    ];
}

$sqlLaporan = <<<SQL
SELECT r.id AS rhk_id,
       r.nama_rhk,
       COALESCE(NULLIF(ra.indikator, ''), r.indikator) AS indikator,
       lh.id,
       lh.tanggal,
       lh.uraian_kegiatan,
       lh.jam_mulai,
       lh.jam_selesai,
       lh.jumlah_kegiatan,
       lh.hasil
FROM laporan_harian lh
LEFT JOIN rencana_hasil_kerja r ON lh.rhk_id = r.id
LEFT JOIN rencana_aksi ra ON ra.rhk_id = r.id AND ra.bulan = ? AND ra.tahun = ?
WHERE lh.user_id = ? AND lh.tanggal BETWEEN ? AND ?
ORDER BY COALESCE(NULLIF(r.nama_rhk, ''), '(Tidak ada RHK)') ASC,
         COALESCE(NULLIF(ra.indikator, ''), NULLIF(r.indikator, ''), '(Tanpa Rencana Aksi)') ASC,
         lh.tanggal ASC,
         lh.jam_mulai ASC,
         lh.id ASC
SQL;

$stmtLaporan = $mysqli->prepare($sqlLaporan);
$stmtLaporan->bind_param('iiiss', $bulan, $tahun, $uid, $startDate, $endDate);
$stmtLaporan->execute();
$resultLaporan = $stmtLaporan->get_result();

$grouped = [];
while ($row = $resultLaporan->fetch_assoc()) {
    $namaRHK = trim((string)($row['nama_rhk'] ?? ''));
    $namaAksi = trim((string)($row['indikator'] ?? ''));
    $rhkId   = (int)($row['rhk_id'] ?? 0);

    if ($namaRHK !== '') {
        $key = '__rhk_name__' . mb_strtolower($namaRHK, 'UTF-8');
    } elseif ($rhkId > 0) {
        $key = '__rhk__' . $rhkId;
    } else {
        $key = '__rhk__0';
    }

    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'rhk_ids'      => [],
            'nama'         => $namaRHK !== '' ? $namaRHK : '(Tidak ada RHK)',
            'aksi_groups'  => [],
            'items'        => [],
        ];
    }

    if ($rhkId > 0 && !in_array($rhkId, $grouped[$key]['rhk_ids'], true)) {
        $grouped[$key]['rhk_ids'][] = $rhkId;
    }

    $aksiKey = $namaAksi !== '' && $namaAksi !== '-'
        ? mb_strtolower($namaAksi, 'UTF-8')
        : '(tanpa rencana aksi)';
    if (!isset($grouped[$key]['aksi_groups'][$aksiKey])) {
        $grouped[$key]['aksi_groups'][$aksiKey] = [
            'nama' => $namaAksi !== '' && $namaAksi !== '-' ? $namaAksi : '(Tanpa Rencana Aksi)',
            'items' => [],
        ];
    }
    $grouped[$key]['aksi_groups'][$aksiKey]['items'][] = $row;
    $grouped[$key]['items'][] = $row;
}
$stmtLaporan->close();

$capaianMap = [];
$stmtCapaian = $mysqli->prepare("SELECT rhk_id, output_hasil FROM rhk_bulanan_capaian WHERE user_id = ? AND bulan = ? AND tahun = ?");
if ($stmtCapaian) {
    $stmtCapaian->bind_param('iii', $uid, $bulan, $tahun);
    $stmtCapaian->execute();
    $resCapaian = $stmtCapaian->get_result();
    while ($c = $resCapaian->fetch_assoc()) {
        $capaianMap[(int)$c['rhk_id']] = $c['output_hasil'];
    }
    $stmtCapaian->close();
}

$allLaporanIds = [];
foreach ($grouped as $g) {
    if (empty($g['items']) || !is_array($g['items'])) continue;
    foreach ($g['items'] as $it) {
        $allLaporanIds[] = (int)$it['id'];
    }
}

$buktiMap     = [];
$globalFotoNo = 0;
if ($allLaporanIds) {
    $inPlaceholders = implode(',', array_fill(0, count($allLaporanIds), '?'));
    $stmtBukti = $mysqli->prepare(
        "SELECT id, laporan_id, nama_file, path_file
         FROM bukti_kegiatan
         WHERE laporan_id IN ({$inPlaceholders})
         ORDER BY laporan_id ASC, id ASC"
    );
    $types = str_repeat('i', count($allLaporanIds));
    $stmtBukti->bind_param($types, ...$allLaporanIds);
    $stmtBukti->execute();
    $resultBukti = $stmtBukti->get_result();
    while ($b = $resultBukti->fetch_assoc()) {
        $globalFotoNo++;
        $b['no_global'] = $globalFotoNo;
        if (!isset($buktiMap[$b['laporan_id']])) {
            $buktiMap[$b['laporan_id']] = [];
        }
        $buktiMap[$b['laporan_id']][] = $b;
    }
    $stmtBukti->close();
}

$logoPath = 'assets/images/logo_kemsos.png';
$logoSrc  = file_exists(dirname(__DIR__, 2) . '/public/' . $logoPath) ? imgToBase64($logoPath) : '';
$ttdPath = 'assets/images/ttd-candra.png';
$ttdSrc = file_exists(dirname(__DIR__, 2) . '/public/' . $ttdPath) ? imgToBase64($ttdPath) : '';
$ttdTanggal = tgl_hari_ini();
$tahunLaporan = $tahun;
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
        html, body {
            width: 100%;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.35;
            margin: 0;
            padding: 0;
            background-color: #fff;
            width: 100%;
            box-shadow: none !important;
            filter: none;
        }
        * {
            box-shadow: none !important;
        }
        .page {
            width: 100%;
            padding: 0 14px;
        }
        .clear {
            clear: both;
        }
        .page-break {
            page-break-before: always;
            break-before: page;
            margin-top: 0;
        }
        .header-container {
            text-align: center;
            margin: 0 0 16px 0;
            width: 100%;
            border-bottom: none;
            padding: 10px 0;
            background-color: #D9D9D9;
        }
        .header-container-lampiran {
            margin: 0 0 12px 0;
            padding: 10px 0;
        }
        .header-container-lampiran .title-laporan {
            margin: 0 auto;
            padding: 0;
            background: transparent;
        }
        .header-main-row {
            width: 100%;
            margin-bottom: 4px;
            text-align: center;
        }
        .header-logo-top {
            width: 100%;
            text-align: center;
            margin-bottom: 4px;
            padding-bottom: 0;
        }
        .header-text-center {
            width: 100%;
            text-align: center;
            line-height: 1.2;
        }
        .logo-instansi {
            width: 70px;
            height: auto;
            display: inline-block;
        }
        .logo-kemsos {
            width: 60px;
            height: auto;
            max-width: 60px;
            max-height: 60px;
            display: block;
            margin: 0 auto;
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
        .title-laporan {
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.4;
            margin: 8px auto 0 auto;
            padding: 2px 0 6px 0;
            display: inline-block;
            white-space: nowrap;
        }
        .title-laporan--lined {
            border-bottom: 1.5px solid #000;
        }
        .periode-laporan {
            font-size: 9.5pt;
            margin-top: 8px;
            line-height: 1.2;
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
        .table-biodata td.label {
            width: 110px;
            padding-left: 0;
        }
        .table-biodata td.separator {
            width: 12px;
            text-align: center;
            padding: 1.5px 0;
        }
        table.table-data {
            width: 100% !important;
            table-layout: fixed;
            border-collapse: collapse;
            border-spacing: 0;
            margin: 5px 0 0 0;
            font-size: 9.5pt;
        }
        table.table-data th,
        table.table-data td {
            border: 1px solid #000;
            padding: 4px 5px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.table-data th {
            font-weight: bold;
            text-align: center;
            background-color: #fff;
            page-break-inside: avoid;
        }
        table.table-data td.text-center {
            text-align: center;
        }
        .rhk-block {
            background-color: transparent;
            border: none;
            padding: 0;
            max-width: none;
            margin: 0 0 14px 0;
            page-break-after: auto;
            break-after: auto;
            page-break-inside: auto;
            break-inside: auto;
            box-shadow: none !important;
        }
        .rhk-label-row {
            display: flex;
            margin-bottom: 6px;
            font-weight: bold;
        }
        .rhk-label-row .lbl-cell {
            width: 80px;
            color: #000000;
            flex-shrink: 0;
        }
        .rhk-label-row .val-cell {
            flex: 1;
            color: #000000;
            padding: 0;
            border-bottom: none;
            line-height: 1.5;
            font-size: 11pt;
            font-weight: normal;
        }
        .rhk-label-row .val-cell-full {
            flex-basis: 100%;
            width: 100%;
            font-weight: bold;
            color: #000000;
            padding-bottom: 0;
            border-bottom: none;
        }
        .rhk-block-separator {
            height: 1px;
            background-color: #000000;
            margin: 15px 0;
            border: none;
        }
        .rhk-sub-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            margin: 5px 0 12px 0;
            table-layout: fixed;
            word-wrap: break-word;
            font-size: 9.5pt;
            color: #000000;
        }
        .rhk-sub-table thead th,
        .rhk-sub-table tbody td {
            border: 1px solid #000000;
            padding: 4px 5px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            color: #000000;
        }
        .rhk-sub-table thead th {
            background-color: #fff;
            text-align: center;
            font-weight: bold;
            color: #000000;
            page-break-inside: avoid;
        }
        .rhk-sub-table tbody td {
            vertical-align: top;
            line-height: 1.35;
            color: #000000;
        }
        .rhk-sub-table tbody td.group-label-cell {
            font-weight: bold;
            vertical-align: middle;
        }
        .rhk-sub-table tbody td.group-empty-cell {
            background-color: #fff;
        }
        .rhk-sub-table tbody td.rhk-group-cell {
            font-weight: bold;
            font-size: 10.5pt;
            padding: 7px 8px;
            background-color: #f3f4f6;
        }
        .rhk-sub-table tbody td.aksi-group-cell {
            font-weight: bold;
            padding: 6px 8px;
            background-color: #fafafa;
        }
        .rhk-sub-table tbody td.output-group-cell {
            padding: 7px 8px;
            line-height: 1.55;
        }
        .text-center {
            text-align: center !important;
        }
        .rhk-sub-table tbody td.text-center { vertical-align: middle; }
        .rhk-output-label {
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 5px;
            font-size: 11pt;
            text-transform: none;
            letter-spacing: 0;
            color: #000000;
        }
        .rhk-output-content {
            padding: 10px 14px;
            background-color: #ffffff;
            border: 1px solid #000000;
            font-size: 11pt;
            line-height: 1.7;
            color: #000000;
            border-left: 3px solid #000000;
            margin-top: 4px;
            text-align: justify;
        }
        .rhk-output-end {
            margin-top: 10px;
            border-top: 2px solid #000000;
        }
        .rhk-output-end strong {
            font-weight: bold;
            color: #000000;
        }
        .small-muted {
            font-size: 9pt;
            color: #000000;
        }
        .badge-inline {
            display: inline-block;
            border: 1px solid #000000;
            color: #000000;
            background: #fff;
            padding: 1px 6px;
            font-size: 8.5pt;
            margin-top: 4px;
            box-shadow: none;
            filter: none;
            -webkit-filter: none;
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
            text-align: left;
            font-size: 10pt;
            padding: 0 10px 0 0;
        }
        .ttd-space {
            height: 10px;
        }
        .ttd-img {
            max-width: 140px;
            max-height: 60px;
            height: auto;
            width: auto;
            display: block;
            margin: 0;
        }
        table.attachment-table {
            width: 100% !important;
            table-layout: fixed;
            border-collapse: collapse;
            border-spacing: 0;
            font-size: 9.5pt;
            margin: 8px 0 0 0;
            color: #000000;
        }
        table.attachment-table th,
        table.attachment-table td {
            border: 1px solid #000000;
            padding: 6px 8px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            color: #000000;
        }
        table.attachment-table th {
            text-align: center;
            font-weight: bold;
            background-color: #fff;
            page-break-inside: avoid;
            color: #000000;
        }
        table.attachment-table td.text-center {
            text-align: center;
            color: #000000;
        }
        table.attachment-table th:first-child,
        table.attachment-table td:first-child {
            width: 44px;
            text-align: center;
        }
        .foto-label {
            display: inline-block;
            border: 1px solid #000000;
            color: #000000;
            background: #fff;
            padding: 1px 8px;
            font-size: 8.5pt;
            margin-right: 6px;
            margin-bottom: 2px;
            font-weight: bold;
            box-shadow: none;
            filter: none;
            -webkit-filter: none;
        }
        .thumb-img {
            display: block;
            width: auto;
            max-width: 100%;
            height: 5.6cm;
            max-height: 5.6cm;
            object-fit: contain;
            background: #fff;
            border: 1px solid #000;
            padding: 2px;
            box-sizing: border-box;
            box-shadow: none;
            filter: none;
            -webkit-filter: none;
            margin: 0 auto;
        }
        .thumb-img-missing {
            height: 5.6cm;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 8.5pt;
            font-style: italic;
            border: 1px dashed #000;
            box-shadow: none;
            filter: none;
            -webkit-filter: none;
        }
        .doc-caption {
            font-size: 8pt;
            color: #000;
            margin-top: 3px;
            word-break: break-all;
            line-height: 1.2;
        }
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
            margin: 0;
            break-inside: avoid;
            page-break-inside: avoid;
            box-sizing: border-box;
            text-align: center;
        }
        .doc-img {
            display: block;
            width: auto !important;
            max-width: 100% !important;
            height: 5.2cm;
            max-height: 5.2cm;
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
            height: 5.2cm;
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
            padding: 8px 18px;
            font-size: 10pt;
            cursor: pointer;
        }
        .btn-print:hover {
            background-color: #0369a1;
        }
        .btn-back {
            background-color: #64748b;
            color: #fff;
            border: none;
            padding: 8px 18px;
            font-size: 10pt;
            cursor: pointer;
            margin-right: 8px;
        }
        .btn-back:hover {
            background-color: #475569;
        }
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

    <?php if (!$renderPdf && !$embeddedViewer): ?>
    <div class="no-print">
        <button class="btn-back" onclick="window.location.href='rekap_bulanan.php?bulan=<?= (int)$bulan ?>&amp;tahun=<?= (int)$tahun ?>'">&larr; Kembali</button>
        <button class="btn-print" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
    <?php endif; ?>

    <!-- ==================== HALAMAN 1: RINCIAN KEGIATAN ==================== -->
    <div class="page">

        <div class="header-container">
            <div class="header-main-row">
                <div class="header-logo-top">
                    <?php if ($logoSrc): ?>
                        <img src="<?= $logoSrc ?>" class="logo-kemsos" alt="Logo Kementerian Sosial">
                    <?php endif; ?>
                </div>
                <div class="header-text-center">
                    <div class="instansi-line"><?= htmlspecialchars($user['instansi'] ?? '-') ?></div>
                    <div class="unitkerja-line"><?= htmlspecialchars($user['unit_kerja'] ?? '-') ?></div>
                    <div class="title-laporan title-laporan--lined">LAPORAN BULAN <?= strtoupper($namaBulanPanjang[$bulan]) ?> ASN P3K SEKOLAH RAKYAT</div>
                </div>
            </div>
        </div>

        <table class="table-biodata" cellpadding="0" cellspacing="0">
            <tr><td class="label">Nama</td><td class="separator">:</td><td><?= htmlspecialchars($user['nama'] ?? '-') ?></td></tr>
            <tr><td class="label">NIP</td><td class="separator">:</td><td><?= htmlspecialchars($user['nip'] ?? '-') ?></td></tr>
            <tr><td class="label">Jabatan</td><td class="separator">:</td><td><?= htmlspecialchars($user['jabatan'] ?? '-') ?></td></tr>
            <tr><td class="label">Unit Kerja</td><td class="separator">:</td><td><?= htmlspecialchars($user['unit_kerja'] ?? '-') ?></td></tr>
        </table>
        <?php if (empty($grouped)): ?>
            <table class="rhk-sub-table" cellpadding="0" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width: 7%;">NO</th>
                        <th style="width: 18%;">TANGGAL</th>
                        <th style="width: 75%;">KEGIATAN</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="3" class="text-center" style="padding: 18px; color: #666;">
                            Belum ada laporan untuk periode <?= htmlspecialchars($labelBulan) ?>.
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php else: ?>
            <?php foreach ($grouped as $g): ?>
                <?php
                $noRow = 1;
                $items = $g['items'];
                $rhkIdsCap = isset($g['rhk_ids']) && is_array($g['rhk_ids']) ? $g['rhk_ids'] : [];
                $capaParts = [];
                foreach ($rhkIdsCap as $rid) {
                    $ridI = (int)$rid;
                    if ($ridI > 0 && isset($capaianMap[$ridI]) && trim((string)$capaianMap[$ridI]) !== '') {
                        $txt = trim((string)$capaianMap[$ridI]);
                        if (!in_array($txt, $capaParts, true)) $capaParts[] = $txt;
                    }
                }
                $capaian = count($capaParts) > 0 ? implode("\n\n", $capaParts) : null;
                ?>
                <div class="rhk-block">
                    <table class="rhk-sub-table" cellpadding="0" cellspacing="0">
                        <thead>
                            <tr>
                                <th style="width: 7%;">NO</th>
                                <th style="width: 18%;">TANGGAL</th>
                                <th style="width: 75%;">KEGIATAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="3" class="rhk-group-cell">RHK: <?= htmlspecialchars($g['nama'] ?? '-') ?></td>
                            </tr>
                            <?php foreach ($g['aksi_groups'] as $aksiGroup): ?>
                                <tr>
                                    <td colspan="3" class="aksi-group-cell">Rencana Aksi: <?= htmlspecialchars($aksiGroup['nama']) ?></td>
                                </tr>
                                <?php foreach ($aksiGroup['items'] as $row): ?>
                                    <?php $tglInd = tanggal_indonesia((string)($row['tanggal'] ?? '')); ?>
                                    <tr>
                                        <td class="text-center group-label-cell"><?= $noRow++ ?></td>
                                        <td class="text-center group-label-cell"><?= htmlspecialchars($tglInd) ?></td>
                                        <td><?= nl2br(htmlspecialchars($row['uraian_kegiatan'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="3" class="output-group-cell">
                                    <strong>Output:</strong>
                                    <?php if (!empty($capaian)): ?>
                                        <?= nl2br(htmlspecialchars($capaian)) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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

    <!-- ==================== HALAMAN 2: LAMPIRAN DOKUMENTASI ==================== -->
    <?php if ($globalFotoNo > 0): ?>
    <div class="page-break"></div>
    <div class="page">

        <div class="header-container header-container-lampiran">
            <div class="title-laporan">LAMPIRAN DOKUMENTASI KEGIATAN</div>
        </div>

        <table class="attachment-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 20%;">Tanggal</th>
                    <th>Dokumentasi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $lampiranRows = [];
                foreach ($grouped as $g):
                    if (empty($g['items']) || !is_array($g['items'])) continue;
                    foreach ($g['items'] as $row):
                        $listBukti = $buktiMap[(int)$row['id']] ?? [];
                        if (count($listBukti) === 0) {
                            continue;
                        }
                        $validBukti = [];
                        foreach ($listBukti as $b) {
                            $img64 = imgToBase64($b['path_file']);
                            if (!empty($img64)) {
                                $validBukti[] = ['img' => $img64, 'name' => $b['nama_file']];
                            }
                        }
                        if (count($validBukti) === 0) {
                            continue;
                        }
                        $tglKey = $row['tanggal'];
                        $urutBukti = 0;
                        foreach ($validBukti as $vb) {
                            $lampiranRows[] = [
                                'tanggal' => $tglKey,
                                'laporan_id' => (int)$row['id'],
                                'urut' => $urutBukti++,
                                'img' => $vb['img'],
                                'name' => $vb['name'],
                            ];
                        }
                    endforeach;
                endforeach;

                /* Dokumentasi diurutkan berdasarkan tanggal kegiatan. */
                usort($lampiranRows, function ($a, $b) {
                    $cmp = strcmp((string)$a['tanggal'], (string)$b['tanggal']);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                    $cmp = (int)$a['laporan_id'] - (int)$b['laporan_id'];
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                    return (int)$a['urut'] - (int)$b['urut'];
                });

                $nomorLampiran = 1;
                if (count($lampiranRows) === 0):
                ?>
                    <tr>
                        <td class="text-center" style="padding: 24px 10px;">-</td>
                        <td style="padding: 24px 10px; text-align: center; color: #555;" colspan="2">
                            Belum ada bukti dokumentasi kegiatan yang diunggah pada periode ini.
                        </td>
                    </tr>
                <?php
                else:
                foreach ($lampiranRows as $vb):
                ?>
                    <tr>
                        <td class="text-center td-semibold" style="vertical-align: middle;"><?= $nomorLampiran++ ?></td>
                        <td class="text-center" style="vertical-align: middle;"><?= htmlspecialchars(tanggal_indonesia((string)$vb['tanggal'])) ?></td>
                        <td>
                            <div class="doc-item">
                                <img src="<?= $vb['img'] ?>" class="doc-img" alt="<?= htmlspecialchars($vb['name']) ?>">
                            </div>
                        </td>
                    </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>

    </div>
    <?php endif; ?>

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
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($downloadPdf ? 'attachment' : 'inline') . '; filename="' . $__filename . '"');
    echo $dompdf->output();
    exit;
}

echo $__reportHtml;
