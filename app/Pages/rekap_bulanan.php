<?php
$pageTitle = 'Rekap Bulanan - Sistem Laporan ASN';

function rekap_clean_preview_text($text, $maxLength = 180) {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$text)));
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength);
    }
    return substr($text, 0, $maxLength);
}

function rekap_append_query($url, array $extraParams) {
    $parts = parse_url((string)$url);
    if ($parts === false || empty($parts['path'])) {
        return '';
    }
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    foreach ($extraParams as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    return basename($parts['path']) . (count($query) ? '?' . http_build_query($query) : '');
}

function rekap_build_preview_url($rawUrl) {
    $parts = parse_url(trim((string)$rawUrl));
    if ($parts === false || empty($parts['path'])) {
        return '';
    }

    $script = basename($parts['path']);
    $allowed = ['cetak_pdf.php', 'cetak_laporan_harian.php'];
    if (!in_array($script, $allowed, true)) {
        return '';
    }

    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    if ($script === 'cetak_pdf.php') {
        $bulan = isset($query['bulan']) ? (int)$query['bulan'] : 0;
        $tahun = isset($query['tahun']) ? (int)$query['tahun'] : 0;
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2100) {
            return '';
        }
        return $script . '?' . http_build_query(['bulan' => $bulan, 'tahun' => $tahun]);
    }

    if (isset($query['id']) && (int)$query['id'] > 0) {
        return $script . '?' . http_build_query(['id' => (int)$query['id']]);
    }

    if (!empty($query['tanggal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$query['tanggal'])) {
        return $script . '?' . http_build_query(['tanggal' => (string)$query['tanggal']]);
    }

    if (isset($query['bulan'], $query['tahun'])) {
        $bulan = (int)$query['bulan'];
        $tahun = (int)$query['tahun'];
        if ($bulan >= 1 && $bulan <= 12 && $tahun >= 2000 && $tahun <= 2100) {
            return $script . '?' . http_build_query(['bulan' => $bulan, 'tahun' => $tahun]);
        }
    }

    return '';
}

$previewSourceUrl = rekap_build_preview_url($_GET['preview'] ?? '');
$previewSubtitle = rekap_clean_preview_text($_GET['preview_subtitle'] ?? '');
$isPreviewMode = $previewSourceUrl !== '';
$bodyClass = '';
if ($isPreviewMode) {
    $pageTitle = 'Preview PDF - Sistem Laporan ASN';
    $bodyClass = 'pdf-preview-mode';
}

require_once __DIR__ . '/../Views/layouts/header.php';

$uid = current_user_id();
$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
$startDate = sprintf('%04d-%02d-01', $tahun, $bulan);
$endDate = date('Y-m-t', strtotime($startDate));
$namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$labelBulan = $namaBulan[$bulan] . ' ' . $tahun;

function ensure_rhk_schema_ready($mysqli) {
    $rhkCols = [];
    $rhkColsRes = $mysqli->query('DESCRIBE rencana_hasil_kerja');
    if ($rhkColsRes) {
        while ($row = $rhkColsRes->fetch_assoc()) {
            $rhkCols[$row['Field']] = true;
        }
        $rhkColsRes->free();
    }
    if (!isset($rhkCols['bulan'])) {
        @$mysqli->query("ALTER TABLE rencana_hasil_kerja ADD COLUMN bulan TINYINT NOT NULL DEFAULT 0 COMMENT '0=Semua Bulan, 1-12=Januari-Desember' AFTER tahun");
        @$mysqli->query("ALTER TABLE rencana_hasil_kerja ADD INDEX idx_rhk_bulan_tahun (user_id, bulan, tahun)");
    }

    $raTableExists = false;
    $raColsRes = $mysqli->query('DESCRIBE rencana_aksi');
    if ($raColsRes) {
        $raTableExists = true;
        $raColsRes->free();
    }
    if (!$raTableExists) {
        @$mysqli->query("CREATE TABLE rencana_aksi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rhk_id INT NOT NULL,
            bulan TINYINT NOT NULL,
            tahun YEAR NOT NULL,
            indikator TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_ra_rhk_bulan_tahun (rhk_id, bulan, tahun),
            INDEX idx_ra_rhk (rhk_id),
            CONSTRAINT fk_ra_rhk FOREIGN KEY (rhk_id)
                REFERENCES rencana_hasil_kerja(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

ensure_rhk_schema_ready($mysqli);

/* ============================================================
   1) AUTO-MIGRASI: Tabel `rhk_bulanan_capaian` (capaian PER RHK PER BULAN)
      Composite PRIMARY KEY: (user_id, rhk_id, bulan, tahun)
      = 1 record per (User X RHK X Bulan X Tahun)
   ============================================================ */
(function ($mysqli) {
    static $done = false;
    if ($done) return; $done = true;
    @$mysqli->query("CREATE TABLE IF NOT EXISTS rhk_bulanan_capaian (
        user_id INT NOT NULL,
        rhk_id INT NOT NULL,
        bulan TINYINT UNSIGNED NOT NULL,
        tahun SMALLINT UNSIGNED NOT NULL,
        output_hasil TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, rhk_id, bulan, tahun),
        KEY idx_capaian_user_tahun (user_id, tahun, bulan)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
})($mysqli);

/* ============================================================
   2) HANDLER POST: Simpan / Update Capaian RHK Bulanan
   ============================================================ */
$flashMsg = '';
$flashType = 'success';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['aksi'] ?? '') === 'simpan_capaian_rhk') {
    $__rhkId = (int)($_POST['rhk_id'] ?? 0);
    $__bulan  = (int)($_POST['bulan'] ?? 0);
    $__tahun  = (int)($_POST['tahun'] ?? 0);
    $__output = trim($_POST['output_hasil'] ?? '');
    if ($__rhkId > 0 && $__bulan >= 1 && $__bulan <= 12 && $__tahun > 2000) {
        $__stmt = $mysqli->prepare("INSERT INTO rhk_bulanan_capaian (user_id, rhk_id, bulan, tahun, output_hasil)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE output_hasil = VALUES(output_hasil), updated_at = CURRENT_TIMESTAMP");
        if ($__stmt) {
            $__outputDb = $__output === '' ? null : $__output;
            $__stmt->bind_param('iiiis', $uid, $__rhkId, $__bulan, $__tahun, $__outputDb);
            if ($__stmt->execute()) {
                $flashMsg = 'Output/Hasil RHK berhasil diperbarui.';
            } else {
                $flashMsg = 'Gagal menyimpan: ' . $mysqli->error;
                $flashType = 'danger';
            }
            $__stmt->close();
        } else {
            $flashMsg = 'Kesalahan kueri sistem.';
            $flashType = 'danger';
        }
    } else {
        $flashMsg = 'Parameter simpan tidak lengkap.';
        $flashType = 'warning';
    }
}

$stmt = $mysqli->prepare('SELECT r.id AS rhk_id, r.nama_rhk, r.indikator,
                                 COALESCE(NULLIF(ra.indikator,\'\'), NULLIF(r.indikator,\'\'), r.nama_rhk) AS label_rencana_aksi,
                                 lh.id, lh.tanggal, lh.uraian_kegiatan, lh.jam_mulai, lh.jam_selesai, lh.jumlah_kegiatan, lh.hasil
                          FROM laporan_harian lh
                          LEFT JOIN rencana_hasil_kerja r ON lh.rhk_id = r.id
                          LEFT JOIN rencana_aksi ra ON ra.rhk_id = r.id AND ra.bulan = ? AND ra.tahun = ?
                          WHERE lh.user_id = ? AND lh.tanggal BETWEEN ? AND ?
                          ORDER BY r.nama_rhk ASC, lh.tanggal ASC');
$stmt->bind_param('iiiss', $bulan, $tahun, $uid, $startDate, $endDate);
$stmt->execute();
$res = $stmt->get_result();
$grouped = [];
while ($row = $res->fetch_assoc()) {
    $namaRHK = trim((string)($row['nama_rhk'] ?? ''));
    $rhkIdI = (int)($row['rhk_id'] ?? 0);
    if ($namaRHK !== '') {
        $keyL1 = '__rhk__' . mb_strtolower($namaRHK);
    } else {
        $keyL1 = $rhkIdI > 0 ? (string)$rhkIdI : '0';
    }
    if (!isset($grouped[$keyL1])) {
        $grouped[$keyL1] = [
            'nama' => $namaRHK !== '' ? $namaRHK : '(Tidak ada RHK)',
            'rhk_ids' => [],
            'rencana_aksi' => [],
            'total' => 0
        ];
    }
    if ($rhkIdI > 0 && !in_array($rhkIdI, $grouped[$keyL1]['rhk_ids'], true)) {
        $grouped[$keyL1]['rhk_ids'][] = $rhkIdI;
    }
    $keyRA = trim((string)($row['label_rencana_aksi'] ?? ''));
    if ($keyRA === '') {
        $keyRA = trim((string)($row['indikator'] ?? ''));
    }
    if ($keyRA === '') {
        $keyRA = trim((string)($row['nama_rhk'] ?? ''));
    }
    if ($keyRA === '') {
        $keyRA = '-';
    }
    if (!isset($grouped[$keyL1]['rencana_aksi'][$keyRA])) {
        $grouped[$keyL1]['rencana_aksi'][$keyRA] = [
            'name' => $keyRA,
            'items' => []
        ];
    }
    $grouped[$keyL1]['rencana_aksi'][$keyRA]['items'][] = $row;
    $grouped[$keyL1]['total']++;
}
$stmt->close();

/* ============================================================
   3) PRELOAD Capaian RHK untuk BULAN & TAHUN ini (semua group)
      $capaianMap[rhk_id] = output_hasil / NULL jika belum isi
   ============================================================ */
$capaianMap = [];
$stmtC = $mysqli->prepare("SELECT rhk_id, output_hasil FROM rhk_bulanan_capaian WHERE user_id = ? AND bulan = ? AND tahun = ?");
if ($stmtC) {
    $stmtC->bind_param('iii', $uid, $bulan, $tahun);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    while ($c = $resC->fetch_assoc()) {
        $capaianMap[(int)$c['rhk_id']] = $c['output_hasil'];
    }
    $stmtC->close();
}

$allLaporanIds = [];
foreach ($grouped as $g) {
    if (empty($g['rencana_aksi']) || !is_array($g['rencana_aksi'])) {
        continue;
    }
    foreach ($g['rencana_aksi'] as $ra) {
        if (empty($ra['items']) || !is_array($ra['items'])) {
            continue;
        }
        foreach ($ra['items'] as $it) {
            $allLaporanIds[] = (int)$it['id'];
        }
    }
}
$buktiMap = [];
$globalFotoCounter = 0;
if ($allLaporanIds) {
    $inPh = implode(',', array_fill(0, count($allLaporanIds), '?'));
    $stmtB = $mysqli->prepare("SELECT id, laporan_id, nama_file, path_file FROM bukti_kegiatan WHERE laporan_id IN ($inPh) ORDER BY laporan_id ASC, id ASC");
    $types = str_repeat('i', count($allLaporanIds));
    $stmtB->bind_param($types, ...$allLaporanIds);
    $stmtB->execute();
    $resB = $stmtB->get_result();
    while ($b = $resB->fetch_assoc()) {
        $globalFotoCounter++;
        $b['no_global'] = $globalFotoCounter;
        if (!isset($buktiMap[$b['laporan_id']])) $buktiMap[$b['laporan_id']] = [];
        $buktiMap[$b['laporan_id']][] = $b;
    }
    $stmtB->close();
}

$previewViewerUrl = $isPreviewMode ? rekap_append_query($previewSourceUrl, ['output' => 'pdf']) : '';
$previewDownloadUrl = $isPreviewMode ? rekap_append_query($previewSourceUrl, ['output' => 'pdf', 'download' => 1]) : '';
/* Halaman HTML rekap tanpa auto-print — dipakai untuk mencetak dari toolbar viewer. */
$previewPrintUrl = $isPreviewMode ? rekap_append_query($previewSourceUrl, ['viewer' => 1]) : '';
?>

<?php if ($isPreviewMode): ?>
<?php
$pdfPreview = [
    'active' => true,
    'title' => 'Preview Rekap Kegiatan ASN',
    'subtitle' => $previewSubtitle !== '' ? $previewSubtitle : 'Dokumen rekap siap ditinjau, dicetak, atau diunduh.',
    'viewer_url' => $previewViewerUrl,
    'download_url' => $previewDownloadUrl,
    'print_url' => $previewPrintUrl
];
require __DIR__ . '/../Views/partial/pdf_preview_panel.php';
?>
<?php else: ?>
<!-- ============================================================
     PAGE HEADER MD-SPEC
     Kiri: Judul + Info Pengguna
     Kanan: Dropdown Bulan/Tahun md-filter-bar
     ============================================================ -->
<div class="md-page-header">
    <div>
        <h2 class="md-page-header__title"><i class="bi bi-bar-chart-line"></i>Rincian Kegiatan Bulan <?= htmlspecialchars($labelBulan) ?></h2>
        <p class="md-page-header__subtitle">
            <strong><?= $_namaUser ?></strong>
            <span class="md-page-header__sep">·</span>
            <?= htmlspecialchars($currUser['jabatan'] ?? 'ASN') ?>
        </p>
    </div>
</div>

<div class="md-filter-bar md-mt-0">
    <form method="GET" action="" class="w-100 md-filter-form">
        <div class="md-field md-filter-form__field">
            <label class="md-label">Bulan</label>
            <div class="md-label-group">
                <span class="md-label-group__icon"><i class="bi bi-calendar-month"></i></span>
                <select name="bulan" id="filter-bulan" class="md-select md-label-group__text">
                    <?php for ($b = 1; $b <= 12; $b++): ?>
                        <option value="<?= $b ?>" <?= $bulan == $b ? 'selected' : '' ?>>
                            <?= $namaBulan[$b] ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="md-field md-filter-form__field md-filter-form__field--tahun">
            <label class="md-label">Tahun</label>
            <div class="md-label-group">
                <span class="md-label-group__icon"><i class="bi bi-calendar-check"></i></span>
                <select name="tahun" id="filter-tahun" class="md-select md-label-group__text">
                    <?php for ($y = (int)date('Y') - 5; $y <= (int)date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="md-filter-form__actions align-self-end">
            <noscript>
                <button type="submit" class="md-btn md-btn--primary md-btn--sm">
                    <i class="bi bi-funnel"></i>Cari Data
                </button>
            </noscript>
        </div>
    </form>
</div>

<!-- ============================================================
     CTA CETAK DOKUMEN (SPEC [I] md-cta SOLID NAVY)
     ============================================================ -->
<?php
$_pdfUrl = 'cetak_pdf.php?bulan=' . $bulan . '&tahun=' . $tahun;
$_subPdf = 'Periode: ' . $labelBulan;
$_onclickPdf = 'return openPreviewLaporan(' . json_encode($_pdfUrl, JSON_UNESCAPED_UNICODE) . ',' . json_encode($_subPdf, JSON_UNESCAPED_UNICODE) . ')';
?>
<div class="md-cta md-mt-4">
    <div class="md-cta__group">
        <span class="md-cta__icon"><i class="bi bi-file-earmark-pdf"></i></span>
        <div class="md-cta__text">
            <h5 class="md-cta__title">Cetak Dokumen Rekap Bulanan</h5>
            <p class="md-cta__sub">Periode <?= htmlspecialchars($labelBulan) ?> — Download format PDF</p>
        </div>
    </div>
    <a href="#" class="md-cta__btn" onclick='<?= htmlspecialchars($_onclickPdf, ENT_QUOTES, 'UTF-8') ?>'>
        <i class="bi bi-printer"></i>Cetak PDF
    </a>
</div>

<?php if (!empty($flashMsg)):
    $flashTypeClass = $flashType === 'success' ? 'success' : ($flashType === 'danger' ? 'danger' : 'warning');
?>
<div class="md-alert md-alert--<?= $flashTypeClass ?> md-sp-6" role="alert">
    <span class="md-alert__icon"><i class="bi bi-<?= $flashType === 'success' ? 'check-circle' : ($flashType === 'danger' ? 'exclamation-triangle' : 'info-circle') ?>"></i></span>
    <div>
        <div class="md-alert__title"><?= $flashType === 'success' ? 'Berhasil' : ($flashType === 'danger' ? 'Gagal' : 'Peringatan') ?></div>
        <div class="md-alert__text"><?= htmlspecialchars($flashMsg) ?></div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($grouped)): ?>
    <div class="md-empty">
        <div class="md-empty__icon"><i class="bi bi-calendar-x"></i></div>
        <h5 class="md-empty__title">Tidak ada laporan pada periode ini</h5>
        <p class="md-empty__desc">Belum ada data laporan kegiatan untuk bulan <?= htmlspecialchars($labelBulan) ?>. Silakan buat laporan harian terlebih dahulu.</p>
        <button type="button" class="md-btn md-btn--primary" data-bs-toggle="modal" data-bs-target="#tambahLaporanModal">
            <i class="bi bi-plus-lg"></i>Buat Laporan Baru
        </button>
    </div>
<?php else: ?>

<!-- ============================================================
     RHK SECTION CARDS (SPEC #6: Expandable Section Card)
     BUKAN tabel besar border biru. Satu card = satu RHK
     ============================================================ -->
<?php $noRHK = 1; foreach ($grouped as $g):
    $n = (int)$g['total'];
    $sumQty = 0;
    foreach ($g['rencana_aksi'] as $igSub) {
        foreach ($igSub['items'] as $itSub) {
            $sumQty += (int)$itSub['jumlah_kegiatan'];
        }
    }
    $rhkIdsG = array_values(array_filter(array_map('intval', $g['rhk_ids'])));
    $rhkIdCap = isset($rhkIdsG[0]) ? (int)$rhkIdsG[0] : 0;
    $capaParts = [];
    foreach ($rhkIdsG as $ridI) {
        $txtCap = isset($capaianMap[$ridI]) ? (string)$capaianMap[$ridI] : '';
        if (trim($txtCap) !== '' && !in_array($txtCap, $capaParts, true)) {
            $capaParts[] = $txtCap;
        }
    }
    $capaianRHK = count($capaParts) > 0 ? implode("\n\n", $capaParts) : null;
    $countRA = count($g['rencana_aksi']);
?>
<section class="md-card md-sp-6" id="rhk-section-<?= $noRHK ?>">
    <div class="md-card__accent"></div>
    <!-- ===== CARD HEADER: RHK Info + Tombol Tambah Output ===== -->
    <div class="md-card__header">
        <div class="md-card__header-main">
            <span class="md-badge md-badge--primary md-mb-2">
                <i class="bi bi-bullseye"></i>RHK #<?= $noRHK ?>
            </span>
            <h3 class="md-card__title"><?= htmlspecialchars($g['nama']) ?></h3>
            <div class="md-card__subtitle md-mt-2">
                <span class="md-badge md-badge--info md-mb-2">
                    <i class="bi bi-journal-check"></i><?= $n ?> kegiatan
                </span>
                <span class="md-badge md-badge--success md-mb-2">
                    <i class="bi bi-list-check"></i><?= $countRA ?> rencana aksi
                </span>
                <span class="md-badge md-badge--violet md-mb-2">
                    <i class="bi bi-hash"></i><?= (int)$sumQty ?> total volume
                </span>
            </div>
        </div>
        <div>
            <?php if ($capaianRHK !== null && trim($capaianRHK) !== ''): ?>
                <button type="button"
                        class="md-btn md-btn--secondary"
                        data-bs-toggle="modal" data-bs-target="#modalCapaianRHK"
                        data-rhk-id="<?= $rhkIdCap ?>"
                        data-nama-rhk="<?= htmlspecialchars($g['nama'], ENT_QUOTES) ?>"
                        data-bulan="<?= $bulan ?>" data-tahun="<?= $tahun ?>"
                        data-output="<?= htmlspecialchars($capaianRHK, ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi bi-pencil-square"></i>Edit Output
                </button>
            <?php else: ?>
                <button type="button"
                        class="md-btn md-btn--primary"
                        data-bs-toggle="modal" data-bs-target="#modalCapaianRHK"
                        data-rhk-id="<?= $rhkIdCap ?>"
                        data-nama-rhk="<?= htmlspecialchars($g['nama'], ENT_QUOTES) ?>"
                        data-bulan="<?= $bulan ?>" data-tahun="<?= $tahun ?>"
                        data-output="">
                    <i class="bi bi-plus-circle"></i>Tambah Output
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== CAPAIAN OUTPUT BOX (jika SUDAH ADA) ===== -->
    <?php if ($capaianRHK !== null && trim($capaianRHK) !== ''): ?>
    <div class="md-card__output-wrap">
        <div class="md-form-tip md-form-tip--secondary">
            <span class="md-form-tip__icon"><i class="bi bi-trophy"></i></span>
            <div class="md-form-tip__text">
                <strong>Output RHK Bulan Ini</strong>
                <div class="md-mt-2 md-card__output-text"><?= nl2br(htmlspecialchars($capaianRHK)) ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== CARD BODY: Rencana Aksi + List Kegiatan (md-list) ===== -->
    <div class="md-card__body">
        <?php
        $firstTableNoRA = 1;
        foreach ($g['rencana_aksi'] as $ig):
            $itemsRA = $ig['items'];
            $countItemsRA = is_array($itemsRA) ? count($itemsRA) : 0;
            usort($itemsRA, function($a, $b) {
                $cmp = strcmp((string)($a['tanggal'] ?? ''), (string)($b['tanggal'] ?? ''));
                if ($cmp !== 0) return $cmp;
                return strcmp((string)($a['jam_mulai'] ?? ''), (string)($b['jam_mulai'] ?? ''));
            });
        ?>
        <!-- === DETAIL RENCANA AKSI === -->
        <div class="md-meta md-mt-4 md-mb-3">
            <span class="md-meta__icon md-meta__icon--success"><i class="bi bi-clipboard-check"></i></span>
            <div class="md-meta__body">
                <div class="md-meta__label">Rencana Aksi · <span class="md-badge md-badge--success md-mt-1"><?= $countItemsRA ?> kegiatan</span></div>
                <div class="md-meta__value md-meta__value--strong"><?= htmlspecialchars($ig['name']) ?></div>
            </div>
        </div>

        <!-- === LIST KEGIATAN (md-list native, stacked per item) === -->
        <ul class="md-list md-sp-6">
            <?php $noKeg = 1; foreach ($itemsRA as $row):
                $mulai = substr((string)($row['jam_mulai'] ?? ''), 0, 5);
                $selesai = substr((string)($row['jam_selesai'] ?? ''), 0, 5);
                $jamStr = trim($mulai . ' - ' . $selesai);
                if ($jamStr === '-') { $jamStr = '-'; }
                try {
                    $dtI = new DateTime($row['tanggal']);
                    $tglObjLokal = DateTime::createFromFormat('Y-m-d', $row['tanggal']);
                    $tglSingkat = (int)$tglObjLokal->format('d') . ' ' . substr($namaBulan[(int)$tglObjLokal->format('m')], 0, 3);
                    $tgl = tanggal_id($row['tanggal']) . ' ' . $dtI->format('Y');
                } catch (Exception $eI) {
                    $tglSingkat = tanggal_id($row['tanggal']);
                    $tgl = tanggal_id($row['tanggal']);
                }
            ?>
            <li class="md-list__item">
                <div class="md-list__trailing">
                    <div class="md-list__trailing-date">
                        <span class="md-list__trailing-day"><?= (int)($tglObjLokal->format('d') ?? date('d')) ?></span>
                        <span class="md-list__trailing-month"><?= substr($namaBulan[(int)($tglObjLokal->format('m') ?? date('m'))], 0, 3) ?></span>
                    </div>
                </div>
                <div class="md-list__icon md-list__icon--success"><i class="bi bi-<?= $noKeg <= 9 ? (string)$noKeg : 'check2-circle' ?>"></i></div>
                <div class="md-list__main">
                    <h6 class="md-list__title">
                        Kegiatan #<?= $noKeg ?>
                    </h6>
                    <p class="md-list__desc md-list__desc--full">
                        <?= nl2br(htmlspecialchars($row['uraian_kegiatan'] ?? '')) ?>
                    </p>
                    <div class="md-list__meta">
                        <span class="md-badge md-badge--primary">
                            <i class="bi bi-clock"></i><?= htmlspecialchars($jamStr) ?> WIB
                        </span>
                        <span class="md-badge md-badge--info">
                            <i class="bi bi-calendar-event"></i><?= htmlspecialchars($tgl) ?>
                        </span>
                        <?php if ((int)$row['jumlah_kegiatan'] > 1): ?>
                            <span class="md-badge md-badge--violet">
                                <i class="bi bi-hash"></i>Jumlah: <?= (int)$row['jumlah_kegiatan'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
            <?php $noKeg++; endforeach; ?>
        </ul>
        <?php $firstTableNoRA++; endforeach; ?>
    </div>
</section>
<?php $noRHK++; endforeach; ?>

<!-- ============================================================
     LAMPIRAN DOKUMENTASI KEGIATAN (md-card + md-list)
     ============================================================ -->
<section class="md-card md-sp-6">
    <div class="md-card__accent md-card__accent--secondary"></div>
    <div class="md-card__header">
        <div>
            <h5 class="md-card__title"><i class="bi bi-folder2-open me-2"></i>Lampiran Dokumentasi Kegiatan</h5>
            <p class="md-card__subtitle">
                <span class="md-badge md-badge--info md-mt-2">
                    <i class="bi bi-file-earmark-image"></i>Total <?= $globalFotoCounter ?> dokumen
                </span>
            </p>
        </div>
    </div>
    <div class="md-card__body">
        <?php
        $nomorLampiran = 1;
        $foundAnyLampiran = false;
        $allItemsLampiran = [];
        foreach ($grouped as $g):
            foreach ($g['rencana_aksi'] as $ig):
                foreach ($ig['items'] as $row):
                    $listBukti = $buktiMap[(int)$row['id']] ?? [];
                    $jml = count($listBukti);
                    if ($jml === 0) continue;
                    $foundAnyLampiran = true;
                    try {
                        $tgl = tanggal_id($row['tanggal']) . ' ' . (new DateTime($row['tanggal']))->format('Y');
                    } catch (Exception $eTgl) {
                        $tgl = tanggal_id($row['tanggal']);
                    }
                    foreach ($listBukti as $bk):
                        $allItemsLampiran[] = [
                            'no' => $nomorLampiran,
                            'bk' => $bk,
                            'tgl' => $tgl,
                            'ig_name' => $ig['name'],
                        ];
                    endforeach;
                    $nomorLampiran++;
                endforeach;
            endforeach;
        endforeach;

        if (!$foundAnyLampiran): ?>
            <div class="md-empty md-empty--sm">
                <div class="md-empty__icon"><i class="bi bi-images"></i></div>
                <h5 class="md-empty__title">Belum ada bukti dokumentasi</h5>
                <p class="md-empty__desc">Tidak ada foto bukti yang diunggah pada periode ini.</p>
            </div>
        <?php else: ?>
            <ul class="md-list">
                <?php foreach ($allItemsLampiran as $lampItem):
                    $bk = $lampItem['bk'];
                    $thumbPath = htmlspecialchars($bk['path_file']);
                    $namaFileSingkat = strlen($bk['nama_file']) > 50 ? substr($bk['nama_file'], 0, 50) . '...' : $bk['nama_file'];
                    $igNameSingkat = strlen($lampItem['ig_name']) > 55 ? substr($lampItem['ig_name'], 0, 55) . '...' : $lampItem['ig_name'];
                ?>
                <li class="md-list__item">
                    <div class="md-list__icon md-list__icon--violet"><i class="bi bi-image"></i></div>
                    <div class="md-list__main">
                        <h6 class="md-list__title">
                            <span class="md-badge md-badge--primary me-2">Foto #<?= (int)$bk['no_global'] ?></span>
                            <?= htmlspecialchars($namaFileSingkat) ?>
                        </h6>
                        <p class="md-list__desc">
                            <span class="md-badge md-badge--violet md-mb-2">
                                <i class="bi bi-calendar-event"></i><?= htmlspecialchars($lampItem['tgl']) ?>
                            </span>
                            <span class="md-badge md-badge--info md-mb-2">
                                <i class="bi bi-list-check"></i><?= htmlspecialchars($igNameSingkat) ?>
                            </span>
                        </p>
                        <div class="md-mt-3">
                            <a href="<?= $thumbPath ?>" target="_blank" class="md-thumb-link">
                                <img src="<?= $thumbPath ?>" alt="bukti" class="md-thumb-img">
                                <div class="md-hint md-mt-1 md-thumb-caption">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Klik untuk buka di tab baru
                                </div>
                            </a>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<?php endif; ?>

<!-- ============================================================
     MODAL GLOBAL: Tambah / Edit Output/Hasil PER RHK PER BULAN
     ============================================================ -->
<div class="modal fade md-modal" id="modalCapaianRHK" tabindex="-1" aria-labelledby="modalCapaianLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable md-modal__dialog md-modal__dialog--md">
        <div class="modal-content md-modal__content">
            <form method="POST" action="" id="formCapaianRHK" data-guard-submit>
                <input type="hidden" name="aksi" value="simpan_capaian_rhk">
                <input type="hidden" name="rhk_id" id="cap_rhk_id" value="">
                <input type="hidden" name="bulan"  id="cap_bulan" value="">
                <input type="hidden" name="tahun"  id="cap_tahun" value="">
                <div class="modal-header md-modal__header">
                    <span class="md-icon-tile md-icon-tile--primary" aria-hidden="true"><i class="bi bi-trophy"></i></span>
                    <div>
                        <h5 class="md-modal__title" id="modalCapaianLabel">Output / Hasil Rencana Hasil Kerja (RHK)</h5>
                        <p class="md-modal__subtitle" id="cap_subtitle">Periode</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body md-modal__body">
                    <div class="md-form-tip md-mb-4">
                        <span class="md-form-tip__icon" aria-hidden="true"><i class="bi bi-info-circle"></i></span>
                        <div class="md-form-tip__text">
                            Output / Hasil disimpan per Rencana Hasil Kerja (RHK) &amp; periode bulan aktif (<strong><?= htmlspecialchars($labelBulan) ?></strong>).
                        </div>
                    </div>
                    <div class="md-form-section">
                        <div class="md-form-section__title"><i class="bi bi-trophy" aria-hidden="true"></i> Capaian RHK</div>
                        <div class="md-field">
                            <label class="md-label md-label--req" for="cap_output">Isi Output / Hasil Capaian RHK</label>
                            <textarea name="output_hasil" id="cap_output" rows="7"
                                      class="md-textarea"
                                      placeholder="Contoh:
1. Total 150 jam tatap muka terlaksana 100% tanpa hambatan
2. 32 siswa lulus ujian kompetensi kejuruan dengan nilai rata-rata 88,5
3. 1 berkas laporan tahunan kegiatan PMM sudah dikirim ke atasan...
Isi secara ringkas, jelas, dan terukur."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer md-modal__footer">
                    <button type="button" class="md-btn md-btn--ghost" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle" aria-hidden="true"></i> Batal
                    </button>
                    <button type="submit" class="md-btn md-btn--primary">
                        <i class="bi bi-save" aria-hidden="true"></i> Simpan Capaian RHK
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var mdl = document.getElementById('modalCapaianRHK');
    if (!mdl) return;
    var fRhk   = document.getElementById('cap_rhk_id');
    var fBln   = document.getElementById('cap_bulan');
    var fThn   = document.getElementById('cap_tahun');
    var fOut   = document.getElementById('cap_output');
    var lbl    = document.getElementById('modalCapaianLabel');
    var sub    = document.getElementById('cap_subtitle');
    var bulanNama = <?= json_encode($namaBulan, JSON_UNESCAPED_UNICODE) ?>;

    mdl.addEventListener('show.bs.modal', function (ev) {
        var btn = ev.relatedTarget;
        if (!btn) return;
        var rhkId = btn.getAttribute('data-rhk-id');
        var nama  = btn.getAttribute('data-nama-rhk') || 'RHK';
        var bln   = parseInt(btn.getAttribute('data-bulan') || '0', 10);
        var thn   = parseInt(btn.getAttribute('data-tahun') || '0', 10);
        var out   = btn.getAttribute('data-output') || '';

        fRhk.value = rhkId || '';
        fBln.value = isNaN(bln) ? '' : bln;
        fThn.value = isNaN(thn) ? '' : thn;
        fOut.value = out.replace(/\\n/g, '\n');
        lbl.textContent = 'Output / Hasil: ' + nama;
        sub.textContent = 'Periode: ' + (bulanNama[bln] || '') + ' ' + (thn || '');
    });
    mdl.addEventListener('shown.bs.modal', function () { try { fOut.focus(); } catch(e) {} });
})();
</script>

<script>
(function () {
    var fBulan = document.getElementById('filter-bulan');
    var fTahun = document.getElementById('filter-tahun');
    var fForm  = document.querySelector('form.md-filter-form');
    var submitTimer = null;
    function autoSubmit(delay) {
        if (submitTimer) clearTimeout(submitTimer);
        submitTimer = setTimeout(function () {
            if (fForm) fForm.submit();
        }, typeof delay === 'number' ? delay : 0);
    }
    if (fBulan) fBulan.addEventListener('change', function () { autoSubmit(120); });
    if (fTahun) fTahun.addEventListener('change', function () { autoSubmit(120); });
})();
</script>

<?php
/* Opsi Rencana Aksi untuk modal "Tambah Data Laporan Harian" (periode bulan/tahun aktif) */
$today = (new DateTime())->format('Y-m-d');
$listRHK = [];
$__stmtRHK = $mysqli->prepare('SELECT DISTINCT r.id, TRIM(ra.indikator) AS label
                             FROM rencana_hasil_kerja r
                             INNER JOIN rencana_aksi ra ON ra.rhk_id = r.id AND ra.bulan = ? AND ra.tahun = ?
                             WHERE r.user_id = ? AND (r.bulan = ? OR r.bulan = 0) AND r.tahun = ?
                               AND NULLIF(TRIM(ra.indikator), \'\') IS NOT NULL
                             ORDER BY label ASC, r.nama_rhk ASC, r.id DESC');
if ($__stmtRHK) {
    $__stmtRHK->bind_param('iiiii', $bulan, $tahun, $uid, $bulan, $tahun);
    if ($__stmtRHK->execute()) {
        $__resRHK = $__stmtRHK->get_result();
        while ($__rowRHK = $__resRHK->fetch_assoc()) {
            $listRHK[] = $__rowRHK;
        }
    }
    $__stmtRHK->close();
}

require __DIR__ . '/../Views/partial/modal_tambah_laporan.php';
?>
<?php endif; ?>

<?php require_once __DIR__ . '/../Views/layouts/footer.php'; ?>
<?php if (!$isPreviewMode): ?>
<script>
(function () {
    /* Modal Tambah Laporan di halaman rekap: sinkronkan Rencana Aksi mengikuti tanggal */
    var updater = window.__updateRHKSelectByTanggal || null;
    var mdlEl = document.getElementById('tambahLaporanModal');
    var mT = document.getElementById('mdTanggal');
    var mR = document.getElementById('mdRHK');
    var mEmpty = document.getElementById('mdRHKEmpty');
    var mEmptyLink = document.getElementById('mdRHKEmptyLink');
    function mTrigger() { if (updater && mT && mR) return updater(mT, mR, mEmpty, mEmptyLink); return Promise.resolve(false); }
    if (mT && mR) {
        mT.addEventListener('change', function () { mTrigger(); });
        mT.addEventListener('blur', function () { mTrigger(); });
    }
    if (mdlEl && typeof bootstrap !== 'undefined' && bootstrap && bootstrap.Modal) {
        mdlEl.addEventListener('shown.bs.modal', function () {
            setTimeout(function () { mTrigger(); }, 60);
        });
        mdlEl.addEventListener('hide.bs.modal', function () {
            if (mT) {
                var dft = mT.getAttribute('data-default');
                if (dft) { mT.value = dft; }
            }
            if (mR) { mR.selectedIndex = 0; }
        });
    }
})();
</script>
<script>
    // app.js dimuat di footer, jadi inisialisasi pratinjau foto menunggu DOM siap.
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof setupImagePreview === 'function') {
            setupImagePreview('bukti_input_modal', 'bukti_preview_modal', 5);
        }
    });
</script>
<?php endif; ?>
