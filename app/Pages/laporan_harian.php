<?php
$pageTitle = 'Laporan Harian - Sistem Laporan ASN';

/* Jalur lama `?aksi=tambah` (form halaman penuh) dialihkan ke modal di halaman daftar
   agar hanya ada satu tampilan penambahan laporan. Harus dijalankan sebelum output dimulai. */
if (isset($_GET['aksi']) && $_GET['aksi'] === 'tambah') {
    $__qsTambah = ['tambah' => 1];
    if (isset($_GET['error'])) {
        $__qsTambah['error'] = (int)$_GET['error'];
    }
    header('Location: laporan_harian.php?' . http_build_query($__qsTambah));
    exit;
}

function laporan_harian_clean_preview_text($text, $maxLength = 180) {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$text)));
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength);
    }
    return substr($text, 0, $maxLength);
}

function laporan_harian_append_query($url, array $extraParams) {
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

function laporan_harian_build_preview_url($rawUrl) {
    $parts = parse_url(trim((string)$rawUrl));
    if ($parts === false || empty($parts['path'])) {
        return '';
    }

    $script = basename($parts['path']);
    $allowed = ['cetak_laporan_harian.php', 'cetak_pdf.php'];
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

$previewSourceUrl = laporan_harian_build_preview_url($_GET['preview'] ?? '');
$previewSubtitle = laporan_harian_clean_preview_text($_GET['preview_subtitle'] ?? '');
$isPreviewMode = $previewSourceUrl !== '';
$bodyClass = '';
if ($isPreviewMode) {
    $pageTitle = 'Preview PDF - Sistem Laporan ASN';
    $bodyClass = 'pdf-preview-mode';
}

require_once __DIR__ . '/../Views/layouts/header.php';

$uid = current_user_id();

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

function fetch_rhk_options_for_period($mysqli, $uid, $bulanRHK, $tahunRHK) {
    $items = [];
    $stmtR = $mysqli->prepare('SELECT DISTINCT r.id, TRIM(ra.indikator) AS label
                               FROM rencana_hasil_kerja r
                               INNER JOIN rencana_aksi ra ON ra.rhk_id = r.id AND ra.bulan = ? AND ra.tahun = ?
                               WHERE r.user_id=? AND (r.bulan = ? OR r.bulan = 0) AND r.tahun = ?
                                 AND NULLIF(TRIM(ra.indikator), \'\') IS NOT NULL
                               ORDER BY label ASC, r.nama_rhk ASC, r.id DESC');
    if (!$stmtR) {
        return $items;
    }
    $stmtR->bind_param('iiiii', $bulanRHK, $tahunRHK, $uid, $bulanRHK, $tahunRHK);
    if ($stmtR->execute()) {
        $resR = $stmtR->get_result();
        while ($row = $resR->fetch_assoc()) {
            $items[] = $row;
        }
    }
    $stmtR->close();
    return $items;
}

    $today = (new DateTime())->format('Y-m-d');

    $filterMode = 'bulan';
    $filter_bulan = (int)($_GET['bulan'] ?? (int)(new DateTime())->format('m'));
    $filter_tahun = (int)($_GET['tahun'] ?? (int)(new DateTime())->format('Y'));
    $filter_tanggal = trim($_GET['tanggal'] ?? '');
    if ($filter_tanggal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_tanggal)) {
        $dt = DateTime::createFromFormat('Y-m-d', $filter_tanggal);
        if ($dt && $dt->format('Y-m-d') === $filter_tanggal) {
            $filter_bulan = (int)$dt->format('m');
            $filter_tahun = (int)$dt->format('Y');
        }
    }
    if ($filter_bulan < 1 || $filter_bulan > 12) $filter_bulan = (int)(new DateTime())->format('m');
    if ($filter_tahun < 2000 || $filter_tahun > 2100) $filter_tahun = (int)(new DateTime())->format('Y');

    $listRHK = fetch_rhk_options_for_period($mysqli, $uid, $filter_bulan, $filter_tahun);

    $filter_q = trim($_GET['q'] ?? '');
    if ($filter_tanggal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_tanggal)) {
        $filterMode = 'tanggal';
        $dt = DateTime::createFromFormat('Y-m-d', $filter_tanggal);
        if ($dt && $dt->format('Y-m-d') === $filter_tanggal) {
            $filter_bulan = (int)$dt->format('m');
            $filter_tahun = (int)$dt->format('Y');
        } else {
            $filter_tanggal = '';
            $filterMode = 'bulan';
        }
    }
    if ($filter_bulan < 1 || $filter_bulan > 12) $filter_bulan = (int)(new DateTime())->format('m');
    if ($filter_tahun < 2000 || $filter_tahun > 2100) $filter_tahun = (int)(new DateTime())->format('Y');

    $whereClause = 'lh.user_id = ?';
    $types = 'i';
    $params = [$uid];

    if ($filterMode === 'tanggal') {
        $whereClause .= ' AND lh.tanggal = ?';
        $types .= 's';
        $params[] = $filter_tanggal;
        $startDate = $filter_tanggal;
        $endDate = $filter_tanggal;
    } else {
        $startDate = $filter_tahun . '-' . str_pad($filter_bulan, 2, '0', STR_PAD_LEFT) . '-01';
        $endDate = $filter_tahun . '-' . str_pad($filter_bulan, 2, '0', STR_PAD_LEFT) . '-31';
        $whereClause .= ' AND lh.tanggal BETWEEN ? AND ?';
        $types .= 'ss';
        $params[] = $startDate;
        $params[] = $endDate;
    }

    if ($filter_q !== '') {
        $whereClause .= ' AND (lh.uraian_kegiatan LIKE ? OR lh.hasil LIKE ? OR COALESCE(NULLIF(ra.indikator, \'\'), NULLIF(r.indikator, \'\'), r.nama_rhk) LIKE ? OR r.nama_rhk LIKE ?)';
        $searchTerm = '%' . $filter_q . '%';
        $types .= 'ssss';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $sql = "SELECT lh.id, lh.tanggal, lh.uraian_kegiatan, lh.jam_mulai, lh.jam_selesai, lh.jumlah_kegiatan, lh.hasil, r.nama_rhk, r.indikator,
                              COALESCE(NULLIF(ra.indikator, ''), NULLIF(r.indikator, ''), r.nama_rhk) AS label_rencana_aksi,
                              (SELECT COUNT(*) FROM bukti_kegiatan bk WHERE bk.laporan_id = lh.id) AS jml_bukti
                              FROM laporan_harian lh
                              LEFT JOIN rencana_hasil_kerja r ON lh.rhk_id = r.id
                              LEFT JOIN rencana_aksi ra ON ra.rhk_id = r.id AND ra.bulan = MONTH(lh.tanggal) AND ra.tahun = YEAR(lh.tanggal)
                              WHERE {$whereClause}
                              ORDER BY lh.tanggal DESC, lh.id DESC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $sqlTotal = "SELECT COUNT(*), COALESCE(SUM(jumlah_kegiatan),0) FROM laporan_harian WHERE user_id=?";
    if ($filterMode === 'tanggal') {
        $sqlTotal .= ' AND tanggal = ?';
        $typesTotal = 'is';
        $paramsTotal = [$uid, $filter_tanggal];
    } else {
        $sqlTotal .= ' AND tanggal BETWEEN ? AND ?';
        $typesTotal = 'iss';
        $paramsTotal = [$uid, $startDate, $endDate];
    }
    $stmtTotal = $mysqli->prepare($sqlTotal);
    $stmtTotal->bind_param($typesTotal, ...$paramsTotal);
    $stmtTotal->execute();
    $stmtTotal->bind_result($totalLaporan, $totalKegiatan);
    $stmtTotal->fetch();
    $stmtTotal->close();

    /**
     * HITUNG TOTAL DURASI JAM MENIT (filter mode tanggal / rentang bulan yang sama)
     * Fallback defensive: jam_mulai / jam_selesai NULL / invalid → skip row
     * Laporan harian = 1 hari, JIKA jam_mulai > jam_selesai → treat sebagai 0 (tidak valid / incomplete)
     */
    $totalDetikDurasi = 0;
    $sqlDurasi = "SELECT jam_mulai, jam_selesai FROM laporan_harian WHERE user_id=? AND jam_mulai IS NOT NULL AND jam_selesai IS NOT NULL";
    if ($filterMode === 'tanggal') {
        $sqlDurasi .= ' AND tanggal = ?';
        $typesDurasi = 'is';
        $paramsDurasi = [$uid, $filter_tanggal];
    } else {
        $sqlDurasi .= ' AND tanggal BETWEEN ? AND ?';
        $typesDurasi = 'iss';
        $paramsDurasi = [$uid, $startDate, $endDate];
    }
    $stmtDurasi = $mysqli->prepare($sqlDurasi);
    if ($stmtDurasi !== false) {
        $stmtDurasi->bind_param($typesDurasi, ...$paramsDurasi);
        $stmtDurasi->execute();
        $stmtDurasi->bind_result($jm, $js);
        while ($stmtDurasi->fetch()) {
            $jm = trim((string)$jm);
            $js = trim((string)$js);
            if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $jm, $mm)
                && preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $js, $ms)) {
                $detikA = ((int)$mm[1] * 3600) + ((int)$mm[2] * 60) + ((int)($mm[3] ?? 0));
                $detikB = ((int)$ms[1] * 3600) + ((int)$ms[2] * 60) + ((int)($ms[3] ?? 0));
                if ($detikB > $detikA) $totalDetikDurasi += ($detikB - $detikA);
            }
        }
        $stmtDurasi->close();
    }
    function format_durasi_label($detik) {
        $detik = max(0, (int)$detik);
        $jam = (int)floor($detik / 3600);
        $sisa = $detik - ($jam * 3600);
        $mnt = (int)floor($sisa / 60);
        if ($jam === 0 && $mnt === 0) return '0 menit';
        if ($jam === 0) return $mnt . ' menit';
        if ($mnt === 0) return $jam . ' jam';
        return $jam . ' jam ' . $mnt . ' menit';
    }
    $labelDurasi = format_durasi_label($totalDetikDurasi);

    $tahunOptions = range((int)date('Y') - 2, (int)date('Y') + 1);
    $namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $tanggalDefault = $filterMode === 'tanggal' ? $filter_tanggal : date('Y-m-d');
    function nama_hari_id($tgl) {
        $d = (int)DateTime::createFromFormat('Y-m-d', $tgl)->format('N');
        $arr = [1=>'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Ahad'];
        return $arr[$d] ?? '';
    }

    $previewViewerUrl = $isPreviewMode ? laporan_harian_append_query($previewSourceUrl, ['output' => 'pdf']) : '';
    $previewDownloadUrl = $isPreviewMode ? laporan_harian_append_query($previewSourceUrl, ['output' => 'pdf', 'download' => 1]) : '';
    /* Halaman HTML laporan tanpa auto-print — dipakai untuk mencetak dari toolbar viewer. */
    $previewPrintUrl = $isPreviewMode ? laporan_harian_append_query($previewSourceUrl, ['viewer' => 1]) : '';
?>

<?php if ($isPreviewMode): ?>
<?php
$pdfPreview = [
    'active' => true,
    'title' => 'Preview Laporan Harian ASN',
    'subtitle' => $previewSubtitle !== '' ? $previewSubtitle : 'Dokumen laporan siap ditinjau, dicetak, atau diunduh.',
    'viewer_url' => $previewViewerUrl,
    'download_url' => $previewDownloadUrl,
    'print_url' => $previewPrintUrl
];
require __DIR__ . '/../Views/partial/pdf_preview_panel.php';
?>
<?php else: ?>
<div class="md-page-header">
    <div>
        <h2 class="md-page-header__title"><i class="bi bi-journal-text"></i>Laporan Harian</h2>
        <p class="md-page-header__subtitle">Kelola data laporan kegiatan harian Anda.</p>
    </div>
    <div class="md-filter-bar__group">
        <button type="button" class="md-btn md-btn--primary" data-bs-toggle="modal" data-bs-target="#tambahLaporanModal">
            <i class="bi bi-plus-lg"></i>Tambah Laporan
        </button>
    </div>
</div>

<?php
$up_ok = (int)($_GET['up_ok'] ?? 0);
$up_warn = (int)($_GET['up_warn'] ?? 0);
$up_msg = trim($_GET['up_msg'] ?? '');
$err_msg = trim($_GET['msg'] ?? '');
?>
<?php if (isset($_GET['status']) && $_GET['status'] === 'sukses'): ?>
    <div class="md-alert md-alert--success md-sp-6" role="alert">
        <span class="md-alert__icon"><i class="bi bi-check-circle"></i></span>
        <div>
            <div class="md-alert__title">Berhasil</div>
            <div class="md-alert__text">
                Laporan berhasil disimpan.
                <?php if ($up_ok > 0): ?>
                    <span class="md-badge md-badge--success ms-2">
                        <i class="bi bi-image"></i><?= $up_ok ?> foto diunggah
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($up_warn && $up_msg): ?>
                <div class="md-alert__actions">
                    <div class="md-alert md-alert--warning md-alert--sm md-mb-0" style="box-shadow:none;">
                        <span class="md-alert__icon"><i class="bi bi-exclamation-triangle"></i></span>
                        <div>
                            <div class="md-alert__title">Peringatan Upload</div>
                            <div class="md-alert__text"><?= htmlspecialchars($up_msg) ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (isset($_GET['hapus']) && $_GET['hapus'] === 'sukses'): ?>
    <div class="md-alert md-alert--success md-sp-6" role="alert">
        <span class="md-alert__icon"><i class="bi bi-trash"></i></span>
        <div>
            <div class="md-alert__title">Berhasil</div>
            <div class="md-alert__text">Laporan berhasil dihapus.</div>
        </div>
    </div>
<?php elseif (isset($_GET['error']) && $_GET['error'] == 2): ?>
    <div class="md-alert md-alert--danger md-sp-6" role="alert">
        <span class="md-alert__icon"><i class="bi bi-exclamation-triangle"></i></span>
        <div>
            <div class="md-alert__title">Gagal</div>
            <div class="md-alert__text">
                Terjadi kesalahan saat menyimpan laporan.
                <?php if ($err_msg): ?>
                    <div class="md-mt-2 small text-break">Detail: <?= htmlspecialchars($err_msg) ?></div>
                <?php endif; ?>
                <?php if ($up_warn && $up_msg): ?>
                    <div class="md-alert md-alert--info md-alert--sm md-mt-3 md-mb-0" style="box-shadow:none;">
                        <span class="md-alert__icon"><i class="bi bi-info-circle"></i></span>
                        <div>
                            <div class="md-alert__title">Info Upload</div>
                            <div class="md-alert__text"><?= htmlspecialchars($up_msg) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="md-filter-bar">
    <form method="GET" action="" id="filterForm" class="w-100 md-filter-form">
        <div class="md-field md-filter-form__field">
            <label class="md-label" for="filter-tanggal">Tanggal</label>
            <div class="md-label-group">
                <span class="md-label-group__icon"><i class="bi bi-calendar3"></i></span>
                <input type="date" name="tanggal" id="filter-tanggal" class="md-input md-label-group__text"
                       value="<?= htmlspecialchars($filterMode === 'tanggal' ? $tanggalDefault : '') ?>">
            </div>
        </div>
        <div class="text-muted d-none d-md-inline-flex align-items-center md-filter-form__sep">atau</div>
        <div class="md-field md-filter-form__field">
            <label class="md-label" for="filter-bulan">Bulan</label>
            <div class="md-label-group">
                <span class="md-label-group__icon"><i class="bi bi-calendar-month"></i></span>
                <select name="bulan" id="filter-bulan" class="md-select md-label-group__text">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $filterMode !== 'tanggal' && $m == $filter_bulan ? 'selected' : '' ?>>
                            <?= $namaBulan[$m] ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="md-field md-filter-form__field md-filter-form__field--tahun">
            <label class="md-label" for="filter-tahun">Tahun</label>
            <div class="md-label-group">
                <span class="md-label-group__icon"><i class="bi bi-calendar-check"></i></span>
                <select name="tahun" id="filter-tahun" class="md-select md-label-group__text">
                    <?php foreach ($tahunOptions as $th): ?>
                        <option value="<?= $th ?>" <?= $filterMode !== 'tanggal' && $th == $filter_tahun ? 'selected' : '' ?><?= $th == (int)date('Y') && $filterMode === 'tanggal' ? '' : '' ?>><?= $th ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="md-field md-filter-form__field md-filter-form__field--search flex-grow-1">
            <label class="md-label">&nbsp;</label>
            <div class="md-label-group">
                <span class="md-label-group__icon"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="md-input md-label-group__text"
                       placeholder="Cari kegiatan, rencana aksi, atau kata kunci..."
                       value="<?= htmlspecialchars($filter_q) ?>">
            </div>
        </div>
        <div class="md-filter-form__actions align-self-end">
            <noscript>
                <button type="submit" class="md-btn md-btn--primary md-btn--sm">
                    <i class="bi bi-search"></i>Cari
                </button>
            </noscript>
            <a href="laporan_harian.php" class="md-btn md-btn--ghost md-btn--sm">
                Reset
            </a>
            <button type="button" id="btnCetakHarian" class="md-btn md-btn--secondary md-btn--sm"
                    title="Cetak laporan per hari yang dipilih (PDF)">
                <i class="bi bi-file-earmark-pdf"></i>Cetak
            </button>
        </div>
    </form>
</div>
<script>
(function () {
    var fTanggal = document.getElementById('filter-tanggal');
    var fBulan   = document.getElementById('filter-bulan');
    var fTahun   = document.getElementById('filter-tahun');
    var fQ      = document.querySelector('input[name="q"]');
    var fForm   = document.getElementById('filterForm');
    var btnCetak = document.getElementById('btnCetakHarian');
    var submitTimer = null;

    function autoSubmitFilter(delay) {
        if (submitTimer) clearTimeout(submitTimer);
        submitTimer = setTimeout(function () {
            if (fForm) fForm.submit();
        }, typeof delay === 'number' ? delay : 0);
    }
    function syncOnTanggalChange() {
        if (fTanggal && fTanggal.value) {
            var d = new Date(fTanggal.value);
            if (!isNaN(d.getTime())) {
                if (fBulan)  fBulan.value  = (d.getMonth() + 1);
                if (fTahun)  fTahun.value  = d.getFullYear();
            }
        }
        autoSubmitFilter(120);
    }
    function syncOnBulanTahunChange() {
        if (fTanggal) {
            if (!document.activeElement || (document.activeElement !== fTanggal)) {
                fTanggal.value = '';
            }
        }
        autoSubmitFilter(120);
    }
    if (fTanggal) fTanggal.addEventListener('change', syncOnTanggalChange);
    if (fBulan)   fBulan.addEventListener('change', syncOnBulanTahunChange);
    if (fTahun)   fTahun.addEventListener('change', syncOnBulanTahunChange);
    if (fQ) {
        fQ.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                autoSubmitFilter(0);
            }
        });
        fQ.addEventListener('input', function () {
            autoSubmitFilter(500);
        });
    }

    if (btnCetak) {
        btnCetak.addEventListener('click', function (e) {
            e.preventDefault();
            var url = 'cetak_laporan_harian.php?';
            var tgl = fTanggal && fTanggal.value;
            var subtitle = '';
            if (tgl) {
                url += 'tanggal=' + encodeURIComponent(tgl);
                subtitle = 'Tanggal Laporan: ' + tgl;
            } else {
                var bl = fBulan.value || (new Date().getMonth() + 1);
                var th = fTahun.value || (new Date().getFullYear());
                url += 'bulan=' + encodeURIComponent(bl) + '&tahun=' + encodeURIComponent(th);
                var bulanLabel = fBulan && fBulan.options && fBulan.selectedIndex >= 0
                    ? fBulan.options[fBulan.selectedIndex].text
                    : ('Bulan ' + bl);
                subtitle = 'Periode: ' + bulanLabel + ' ' + th;
            }
            openPreviewLaporan(url, subtitle);
        });
    }
})();
</script>

<div class="md-section-header">
    <div>
        <h5 class="md-section-header__title">
            <i class="bi bi-table me-2"></i>
            <?php if ($filterMode === 'tanggal'): ?>
                Data Laporan Tanggal
                <span style="color: var(--md-primary);"><?= nama_hari_id($tanggalDefault) ?>,</span>
                <?= (int)DateTime::createFromFormat('Y-m-d', $tanggalDefault)->format('d') ?>
                <?= $namaBulan[(int)DateTime::createFromFormat('Y-m-d', $tanggalDefault)->format('m')] ?>
                <?= DateTime::createFromFormat('Y-m-d', $tanggalDefault)->format('Y') ?>
            <?php else: ?>
                Data Laporan Bulan <?= $namaBulan[(int)$filter_bulan] ?> <?= $filter_tahun ?>
            <?php endif; ?>
            <span class="md-badge md-badge--info ms-2">
                <i class="bi bi-clock-history me-1"></i><?= htmlspecialchars($labelDurasi) ?>
            </span>
        </h5>
        <p class="md-section-header__sub">
            <?php
            $partSub = [];
            if ($totalLaporan > 0) {
                $partSub[] = (int)$totalLaporan . ' laporan';
                $partSub[] = (int)$totalKegiatan . ' kegiatan';
            }
            $partSub[] = 'Total durasi: ' . htmlspecialchars($labelDurasi);
            echo htmlspecialchars(implode(' · ', $partSub));
            ?>
        </p>
    </div>
    <?php if ($result->num_rows > 0): ?>
        <?php
        $hdrSubtitle = '';
        $hdrUrl = '';
        $hdrText = '';
        if ($filterMode === 'tanggal') {
            $hdrUrl = 'cetak_laporan_harian.php?tanggal=' . $tanggalDefault;
            $dtLap = DateTime::createFromFormat('Y-m-d', $tanggalDefault);
            $tglId = (int)$dtLap->format('d') . ' ' . $namaBulan[(int)$dtLap->format('m')] . ' ' . $dtLap->format('Y');
            $hdrSubtitle = 'Tanggal Laporan: ' . $tglId;
            $hdrText = 'Cetak Harian';
        } else {
            $hdrUrl = 'cetak_pdf.php?bulan=' . $filter_bulan . '&tahun=' . $filter_tahun;
            $hdrSubtitle = 'Periode: ' . $namaBulan[$filter_bulan] . ' ' . $filter_tahun;
            $hdrText = 'Cetak Bulanan';
        }
        $_onclickHdr = 'return openPreviewLaporan(' . json_encode($hdrUrl, JSON_UNESCAPED_UNICODE) . ',' . json_encode($hdrSubtitle, JSON_UNESCAPED_UNICODE) . ')';
        ?>
        <div class="md-filter-bar__group">
            <a href="#" class="md-btn md-btn--secondary md-btn--sm"
               onclick='<?= htmlspecialchars($_onclickHdr, ENT_QUOTES, 'UTF-8') ?>'>
                <i class="bi bi-printer"></i><?= $hdrText ?>
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if ($result->num_rows === 0): ?>
    <div class="md-empty">
        <div class="md-empty__icon"><i class="bi bi-inbox"></i></div>
        <h5 class="md-empty__title">Belum ada laporan pada periode ini</h5>
        <p class="md-empty__desc">Silakan tambahkan laporan kegiatan harian pertama Anda pada periode yang dipilih.</p>
        <button type="button" class="md-btn md-btn--primary" data-bs-toggle="modal" data-bs-target="#tambahLaporanModal">
            <i class="bi bi-plus-lg"></i>Tambah Laporan
        </button>
    </div>
<?php else: ?>
    <ul class="md-list md-sp-6">
        <?php $no = 0; while ($row = $result->fetch_assoc()): $no++;
            $tglObj = DateTime::createFromFormat('Y-m-d', $row['tanggal']);
            $tglHari = nama_hari_id($row['tanggal']);
            $tglTgl = (int)$tglObj->format('d');
            $tglBulan = $namaBulan[(int)$tglObj->format('m')];
            $tglTahun = $tglObj->format('Y');
            $tglSingkatBulan = substr($tglBulan, 0, 3);
            $uraianFull = $row['uraian_kegiatan'];
            $uraianSingkat = strlen($uraianFull) > 180 ? substr($uraianFull, 0, 180) . '...' : $uraianFull;
            $labelRencanaAksi = trim((string)($row['label_rencana_aksi'] ?? ''));
            if ($labelRencanaAksi === '') {
                $labelRencanaAksi = trim((string)($row['indikator'] ?? ''));
            }
            if ($labelRencanaAksi === '') {
                $labelRencanaAksi = trim((string)($row['nama_rhk'] ?? ''));
            }
            if ($labelRencanaAksi === '') {
                $labelRencanaAksi = '-';
            }
        ?>
        <li class="md-list__item">
            <div class="md-list__trailing">
                <div class="md-list__trailing-date">
                    <span class="md-list__trailing-day"><?= $tglTgl ?></span>
                    <span class="md-list__trailing-month"><?= $tglSingkatBulan ?></span>
                </div>
            </div>
            <div class="md-list__icon"><i class="bi bi-journal-check"></i></div>
            <div class="md-list__main">
                <h6 class="md-list__title">
                    #<?= $no ?> · <?= htmlspecialchars($labelRencanaAksi) ?>
                </h6>
                <p class="md-list__desc">
                    <?= htmlspecialchars($uraianSingkat) ?>
                </p>
                <div class="md-list__meta">
                    <span class="md-badge md-badge--primary">
                        <i class="bi bi-clock"></i>
                        <?= substr($row['jam_mulai'], 0, 5) ?> - <?= substr($row['jam_selesai'], 0, 5) ?>
                    </span>
                    <span class="md-badge md-badge--info">
                        <i class="bi bi-hash"></i>
                        <?= $row['jumlah_kegiatan'] ?> kegiatan
                    </span>
                    <?php if ($row['jml_bukti'] > 0): ?>
                        <a href="get_bukti.php?id=<?= $row['id'] ?>" data-bs-toggle="modal" data-bs-target="#buktiModal" onclick="loadBukti(<?= $row['id'] ?>); return false;" class="md-badge md-badge--success" style="text-decoration:none;">
                            <i class="bi bi-image"></i><?= $row['jml_bukti'] ?> bukti
                        </a>
                    <?php else: ?>
                        <span class="md-badge md-badge--warning">
                            <i class="bi bi-image"></i>Tanpa bukti
                        </span>
                    <?php endif; ?>
                    <span class="md-badge md-badge--violet">
                        <i class="bi bi-calendar-week"></i>
                        <?= $tglHari ?>, <?= $tglTgl ?> <?= substr($tglBulan, 0, 3) ?>
                    </span>
                </div>
                <div class="md-list__actions md-mt-3">
                    <a href="edit_laporan.php?id=<?= $row['id'] ?>" class="md-btn md-btn--ghost md-btn--sm">
                        <i class="bi bi-pencil-square"></i>Edit
                    </a>
                    <?php
                    $_urlC = 'cetak_laporan_harian.php?id=' . $row['id'];
                    $_subC = 'Laporan ID #' . $row['id'] . ' — Tanggal ' . tanggal_id($row['tanggal']);
                    $_onclick = 'return openPreviewLaporan(' . json_encode($_urlC, JSON_UNESCAPED_UNICODE) . ',' . json_encode($_subC, JSON_UNESCAPED_UNICODE) . ')';
                    ?>
                    <a href="#" class="md-btn md-btn--ghost md-btn--sm"
                       onclick='<?= htmlspecialchars($_onclick, ENT_QUOTES, 'UTF-8') ?>'>
                        <i class="bi bi-printer"></i>Print
                    </a>
                    <button type="button" class="md-btn md-btn--ghost md-btn--sm md-btn--danger-text"
                            onclick="confirmDelete('hapus_laporan.php?id=<?= $row['id'] ?>')">
                        <i class="bi bi-trash"></i>Delete
                    </button>
                </div>
            </div>
        </li>
        <?php endwhile; ?>
    </ul>
    <div class="md-hint md-mt-3">
        Menampilkan <?= $result->num_rows ?> laporan.
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../Views/partial/modal_tambah_laporan.php'; ?>

<div class="modal fade md-modal" id="buktiModal" tabindex="-1">
    <div class="modal-dialog modal-lg md-modal__dialog">
        <div class="modal-content">
            <div class="modal-header">
                <span class="md-icon-tile md-icon-tile--primary"><i class="bi bi-images"></i></span>
                <div>
                    <h5 class="md-modal__title">Bukti Kegiatan</h5>
                    <p class="md-modal__subtitle">Dokumentasi foto pendukung laporan</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="buktiModalBody">
                <div class="text-center text-muted py-5">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Memuat...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="md-btn md-btn--ghost" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php $stmt->close(); require_once __DIR__ . '/../Views/layouts/footer.php'; ?>
<?php if (!$isPreviewMode): ?>
<script>
(function () {
    /* Modal inline laporan_harian list: sync RHK by tanggal & shown.bs.modal */
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
            setTimeout(function () { mTrigger().then(function () {
                if (mR) {
                    mR.classList.remove('is-loading');
                    mR.disabled = false;
                    var firstInput = mdlEl.querySelector('input[name="tanggal"]');
                    if (firstInput) { try { firstInput.focus(); } catch (e) {} }
                }
            }); }, 60);
        });
        mdlEl.addEventListener('hide.bs.modal', function () {
            /* Reset input ke hari ini + clear select agar modal dibuka ulang tidak sisa value lama */
            var todayInput = document.getElementById('mdTanggal');
            if (todayInput && todayInput.getAttribute && todayInput.getAttribute('data-default')) {
                todayInput.value = todayInput.getAttribute('data-default');
            } else if (todayInput) {
                var t = new Date();
                var mm = String(t.getMonth() + 1).padStart(2, '0');
                var dd = String(t.getDate()).padStart(2, '0');
                todayInput.value = t.getFullYear() + '-' + mm + '-' + dd;
            }
            if (mR) { mR.selectedIndex = 0; }
        });
    }

    /* Buka modal otomatis saat pengguna dialihkan dari halaman lain (tombol "Buat Laporan Baru") */
    if (mdlEl && typeof bootstrap !== 'undefined' && bootstrap && bootstrap.Modal
        && /[?&]tambah=1(&|$)/.test(window.location.search)) {
        bootstrap.Modal.getOrCreateInstance(mdlEl).show();
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
