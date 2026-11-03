<?php
$pageTitle = 'Dashboard - Sistem Laporan ASN';
require_once __DIR__ . '/../Views/layouts/header.php';

$uid = current_user_id();
$now = new DateTime();
$currMonth = $now->format('m');
$currYear = $now->format('Y');
$currMonthStart = $currYear . '-' . $currMonth . '-01';
$currMonthEnd = $currYear . '-' . $currMonth . '-31';

// --- Statistik ---
$totalLaporan = 0;
$stmt = $mysqli->prepare('SELECT COUNT(*) FROM laporan_harian WHERE user_id = ?');
$stmt->bind_param('i', $uid); $stmt->execute(); $stmt->bind_result($totalLaporan); $stmt->fetch(); $stmt->close();

$totalLaporanBulan = 0;
$stmt = $mysqli->prepare('SELECT COUNT(*) FROM laporan_harian WHERE user_id = ? AND tanggal BETWEEN ? AND ?');
$stmt->bind_param('iss', $uid, $currMonthStart, $currMonthEnd); $stmt->execute(); $stmt->bind_result($totalLaporanBulan); $stmt->fetch(); $stmt->close();

$totalKegiatan = 0;
$stmt = $mysqli->prepare('SELECT COALESCE(SUM(jumlah_kegiatan), 0) FROM laporan_harian WHERE user_id = ?');
$stmt->bind_param('i', $uid); $stmt->execute(); $stmt->bind_result($totalKegiatan); $stmt->fetch(); $stmt->close();

$totalDokumen = 0;
$stmt = $mysqli->prepare('SELECT COUNT(*) FROM bukti_kegiatan bk JOIN laporan_harian lh ON bk.laporan_id = lh.id WHERE lh.user_id = ?');
$stmt->bind_param('i', $uid); $stmt->execute(); $stmt->bind_result($totalDokumen); $stmt->fetch(); $stmt->close();

// --- Recent reports ---
$stmt = $mysqli->prepare('SELECT lh.id, lh.tanggal, lh.uraian_kegiatan, r.nama_rhk FROM laporan_harian lh LEFT JOIN rencana_hasil_kerja r ON lh.rhk_id = r.id WHERE lh.user_id = ? ORDER BY lh.tanggal DESC LIMIT 5');
$stmt->bind_param('i', $uid); $stmt->execute(); $recent = $stmt->get_result();

// --- Progress calculations ---
$_jmlHariIniBulan = (int)$now->format('t');
$_persenBulan = $_jmlHariIniBulan > 0 ? min(100, (int)round(($totalLaporanBulan / max(1, $_jmlHariIniBulan)) * 100)) : 0;
$_persenKeg = $totalKegiatan > 0 ? min(100, (int)round(($totalKegiatan / max(1, $totalKegiatan + 5)) * 100)) : 0;
$_persenDok = $totalLaporan > 0 ? min(100, (int)round(($totalDokumen / max(1, $totalLaporan * 2)) * 100)) : 0;
$_persenAll = $totalLaporan > 0 ? min(100, (int)round(($totalLaporan / max(1, $totalLaporan + 5)) * 100)) : 0;

$_tglLengkap = tanggal_lengkap($now->format('Y-m-d'));
$_arrBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// --- Kalender dashboard (bulanan) ---
$calendarWeekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
$calendarMonth = isset($_GET['cal_bulan']) ? (int)$_GET['cal_bulan'] : (int)$now->format('n');
$calendarYear = isset($_GET['cal_tahun']) ? (int)($_GET['cal_tahun']) : (int)$now->format('Y');
if ($calendarMonth < 1 || $calendarMonth > 12) $calendarMonth = (int)$now->format('n');
if ($calendarYear < 2000 || $calendarYear > 2100) $calendarYear = (int)$now->format('Y');

$calendarRef = DateTime::createFromFormat('Y-n-j', $calendarYear . '-' . $calendarMonth . '-1');
if (!$calendarRef) {
    $calendarRef = new DateTime('first day of this month');
}
$calendarMonth = (int)$calendarRef->format('n');
$calendarYear = (int)$calendarRef->format('Y');
$calendarToday = $now->format('Y-m-d');
$calendarStart = $calendarRef->format('Y-m-01');
$calendarEnd = $calendarRef->format('Y-m-t');
$calendarFirstWeekday = (int)$calendarRef->format('N') - 1; // 0 = Senin ... 6 = Minggu
$calendarDaysInMonth = (int)$calendarRef->format('t');
$calendarTitle = $_arrBulan[$calendarMonth] . ' ' . $calendarYear;

$calendarPrevRef = clone $calendarRef;
$calendarPrevRef->modify('-1 month');
$calendarNextRef = clone $calendarRef;
$calendarNextRef->modify('+1 month');

$calendarGridStart = clone $calendarRef;
if ($calendarFirstWeekday > 0) {
    $calendarGridStart->modify('-' . $calendarFirstWeekday . ' day');
}
$calendarTotalCells = (int)(ceil(($calendarFirstWeekday + $calendarDaysInMonth) / 7) * 7);

$calendarMap = [];
$stmtCalendar = $mysqli->prepare("SELECT lh.tanggal,
                                         COUNT(*) AS jumlah_laporan,
                                         COALESCE(SUM(lh.jumlah_kegiatan), 0) AS jumlah_kegiatan,
                                         MIN(COALESCE(NULLIF(ra.indikator, ''), NULLIF(r.indikator, ''), r.nama_rhk)) AS label_rencana
                                  FROM laporan_harian lh
                                  LEFT JOIN rencana_hasil_kerja r ON lh.rhk_id = r.id
                                  LEFT JOIN rencana_aksi ra ON ra.rhk_id = r.id
                                         AND ra.bulan = MONTH(lh.tanggal)
                                         AND ra.tahun = YEAR(lh.tanggal)
                                  WHERE lh.user_id = ? AND lh.tanggal BETWEEN ? AND ?
                                  GROUP BY lh.tanggal
                                  ORDER BY lh.tanggal ASC");
if ($stmtCalendar) {
    $stmtCalendar->bind_param('iss', $uid, $calendarStart, $calendarEnd);
    if ($stmtCalendar->execute()) {
        $resCalendar = $stmtCalendar->get_result();
        while ($rowCalendar = $resCalendar->fetch_assoc()) {
            $tglKey = (string)$rowCalendar['tanggal'];
            $labelKal = trim((string)($rowCalendar['label_rencana'] ?? ''));
            if ($labelKal === '') $labelKal = 'Ada laporan kegiatan';
            if (function_exists('mb_substr')) {
                $labelKal = mb_substr($labelKal, 0, 30);
            } else {
                $labelKal = substr($labelKal, 0, 30);
            }
            $calendarMap[$tglKey] = [
                'jumlah_laporan' => (int)($rowCalendar['jumlah_laporan'] ?? 0),
                'jumlah_kegiatan' => (int)($rowCalendar['jumlah_kegiatan'] ?? 0),
                'label' => $labelKal,
            ];
        }
    }
    $stmtCalendar->close();
}

function dashboard_calendar_url($bulan, $tahun) {
    return 'dashboard.php?' . http_build_query([
        'cal_bulan' => (int)$bulan,
        'cal_tahun' => (int)$tahun,
    ]);
}
?>

<!-- Page Header -->
<div class="md-page-header">
    <div>
        <h2 class="md-page-header__title"><i class="bi bi-grid-1x2"></i>Dashboard</h2>
        <p class="md-page-header__subtitle">Ringkasan laporan kinerja harian, kalender kegiatan, dan aktivitas terbaru Anda.</p>
    </div>
</div>

<!-- Profile / Welcome Card -->
<div class="profile-card">
    <div class="profile-card__avatar"><?= $_inisialUser ?></div>
    <div class="profile-card__info">
        <div class="profile-card__name"><?= e($currUser['nama']) ?></div>
        <div class="profile-card__meta">
            <i class="bi bi-person-vcard"></i>
            <span>NIP. <?= e($currUser['nip']) ?></span>
        </div>
        <div class="profile-card__meta">
            <i class="bi bi-briefcase"></i>
            <span><?= e($currUser['jabatan']) ?></span>
        </div>
        <div class="profile-card__date">
            <span class="badge badge--primary">
                <i class="bi bi-calendar2-check"></i>
                <?= e($_tglLengkap) ?>
            </span>
        </div>
    </div>
    <a href="profil.php" class="profile-card__chev" aria-label="Detail Profil">
        <i class="bi bi-chevron-right"></i>
    </a>
</div>

<!-- Kalender Kegiatan -->
<div class="section-head section-head--with-action">
    <div>
        <div class="section-head__title">Kalender Kegiatan</div>
        <div class="section-head__sub">Pantau sebaran laporan harian per tanggal</div>
    </div>
    <div class="calendar-nav">
        <a href="<?= e(dashboard_calendar_url((int)$calendarPrevRef->format('n'), (int)$calendarPrevRef->format('Y'))) ?>" class="calendar-nav__btn" aria-label="Bulan sebelumnya">
            <i class="bi bi-chevron-left"></i>
        </a>
        <div class="calendar-nav__label"><?= e($calendarTitle) ?></div>
        <a href="<?= e(dashboard_calendar_url((int)$calendarNextRef->format('n'), (int)$calendarNextRef->format('Y'))) ?>" class="calendar-nav__btn" aria-label="Bulan berikutnya">
            <i class="bi bi-chevron-right"></i>
        </a>
    </div>
</div>
<div class="card calendar-card">
    <div class="calendar-grid calendar-grid--weekdays">
        <?php foreach ($calendarWeekdays as $wi => $weekday): ?>
            <div class="calendar-weekday<?= $wi >= 5 ? ' calendar-weekday--weekend' : '' ?>"><?= e($weekday) ?></div>
        <?php endforeach; ?>
    </div>
    <div class="calendar-grid">
        <?php for ($cell = 0; $cell < $calendarTotalCells; $cell++):
            $cellDate = clone $calendarGridStart;
            if ($cell > 0) $cellDate->modify('+' . $cell . ' day');
            $cellDateKey = $cellDate->format('Y-m-d');
            $cellDay = (int)$cellDate->format('j');
            $isCurrentMonth = ((int)$cellDate->format('n') === $calendarMonth && (int)$cellDate->format('Y') === $calendarYear);
            $isToday = ($cellDateKey === $calendarToday);
            $hasReport = isset($calendarMap[$cellDateKey]);
            $isWeekend = ((int)$cellDate->format('N') >= 6); // Sabtu & Minggu
            $canAdd = $isCurrentMonth && !$isWeekend;
            $cellClass = 'calendar-cell';
            if (!$isCurrentMonth) $cellClass .= ' calendar-cell--muted';
            if ($isWeekend) $cellClass .= ' calendar-cell--weekend';
            if ($isToday) $cellClass .= ' calendar-cell--today';
            if ($hasReport) $cellClass .= ' calendar-cell--has-report';
            if ($canAdd) $cellClass .= ' calendar-cell--clickable';
            ?>
            <div class="<?= $cellClass ?>"<?= $canAdd ? ' data-add-tanggal="' . e($cellDateKey) . '" role="button" tabindex="0" aria-label="Tambah laporan tanggal ' . e($cellDateKey) . '"' : '' ?>>
                <div class="calendar-cell__head">
                    <span class="calendar-cell__day"><?= $cellDay ?></span>
                    <?php if ($hasReport): ?>
                        <span class="calendar-cell__count"><?= (int)$calendarMap[$cellDateKey]['jumlah_laporan'] ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($hasReport): ?>
                    <a href="laporan_harian.php?tanggal=<?= e($cellDateKey) ?>" class="calendar-cell__event">
                        <div class="calendar-cell__event-title"><?= e($calendarMap[$cellDateKey]['label']) ?></div>
                        <div class="calendar-cell__event-meta"><?= (int)$calendarMap[$cellDateKey]['jumlah_kegiatan'] ?> kegiatan</div>
                    </a>
                <?php elseif ($canAdd): ?>
                    <span class="calendar-cell__empty">Belum ada laporan</span>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
    <div class="calendar-legend">
        <span class="calendar-legend__item"><span class="calendar-legend__dot calendar-legend__dot--today"></span>Hari ini</span>
        <span class="calendar-legend__item"><span class="calendar-legend__dot calendar-legend__dot--report"></span>Ada laporan</span>
        <span class="calendar-legend__item"><span class="calendar-legend__dot calendar-legend__dot--muted"></span>Bulan lain</span>
    </div>
</div>

<!-- Statistik Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card__top">
            <div class="stat-card__icon"><i class="bi bi-journal-text"></i></div>
            <span class="badge badge--info"><?= $_persenAll ?>%</span>
        </div>
        <div class="stat-card__num"><?= $totalLaporan ?></div>
        <div class="stat-card__label">Total Laporan</div>
        <div class="stat-card__bar">
            <div class="stat-card__bar-fill" style="width: <?= max(4, $_persenAll) ?>%;"></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__top">
            <div class="stat-card__icon"><i class="bi bi-check2-circle"></i></div>
            <span class="badge badge--success"><?= $_persenKeg ?>%</span>
        </div>
        <div class="stat-card__num"><?= $totalKegiatan ?></div>
        <div class="stat-card__label">Kegiatan Selesai</div>
        <div class="stat-card__bar">
            <div class="stat-card__bar-fill stat-card__bar-fill--success" style="width: <?= max(4, $_persenKeg) ?>%;"></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__top">
            <div class="stat-card__icon"><i class="bi bi-files"></i></div>
            <span class="badge badge--warning"><?= $_persenDok ?>%</span>
        </div>
        <div class="stat-card__num"><?= $totalDokumen ?></div>
        <div class="stat-card__label">Dokumen Bukti</div>
        <div class="stat-card__bar">
            <div class="stat-card__bar-fill stat-card__bar-fill--warning" style="width: <?= max(4, $_persenDok) ?>%;"></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__top">
            <div class="stat-card__icon"><i class="bi bi-calendar-check"></i></div>
            <span class="badge badge--primary"><?= $_persenBulan ?>%</span>
        </div>
        <div class="stat-card__num"><?= $totalLaporanBulan ?></div>
        <div class="stat-card__label">Bulan Ini</div>
        <div class="stat-card__bar">
            <div class="stat-card__bar-fill" style="width: <?= max(4, $_persenBulan) ?>%;"></div>
        </div>
    </div>
</div>

<!-- Progress Section -->
<div class="progress-section">
    <div class="section-head">
        <div class="section-head__title">Progress Kegiatan</div>
    </div>
    <div class="progress-item">
        <div class="progress-item__head">
            <span class="progress-item__label">Kegiatan selesai</span>
            <span class="progress-item__value"><?= $totalKegiatan ?> / <?= max(22, $totalKegiatan + 5) ?></span>
        </div>
        <div class="progress-bar">
            <div class="progress-bar__fill progress-bar__fill--success" style="width: <?= max(4, $_persenKeg) ?>%;"></div>
        </div>
    </div>
    <div class="progress-item">
        <div class="progress-item__head">
            <span class="progress-item__label">Dokumen bukti</span>
            <span class="progress-item__value"><?= $totalDokumen ?> / <?= max(30, $totalDokumen + 4) ?></span>
        </div>
        <div class="progress-bar">
            <div class="progress-bar__fill" style="width: <?= max(4, $_persenDok) ?>%;"></div>
        </div>
    </div>
</div>

<!-- Quick Action CTA -->
<div class="mb-4">
    <button type="button" class="cta cta--ghost" data-bs-toggle="modal" data-bs-target="#tambahLaporanModal">
        <div class="cta__icon"><i class="bi bi-plus-lg"></i></div>
        <div class="cta__body">
            <div class="cta__title">Tambah Laporan Baru</div>
            <div class="cta__sub">Isi kegiatan hari ini dalam kurang dari 30 detik</div>
        </div>
        <div class="cta__chev"><i class="bi bi-arrow-right"></i></div>
    </button>
</div>

<!-- Aktivitas Terbaru + Laporan Terbaru (2 kolom desktop) -->
<div class="row g-4 mb-5">
    <div class="col-lg-6">
        <div class="section-head">
            <div class="section-head__title">Aktivitas Terbaru</div>
        </div>
        <div class="card">
            <div class="timeline">
                <?php
                $recent->data_seek(0);
                $no = 0;
                while ($row = $recent->fetch_assoc()):
                    $no++;
                    $dt = DateTime::createFromFormat('Y-m-d', $row['tanggal']);
                    $tglShort = (int)$dt->format('d') . ' ' . $_arrBulan[(int)$dt->format('m')];
                    $dotClass = $no <= 2 ? 'timeline__dot--success' : 'timeline__dot--primary';
                ?>
                <div class="timeline__item">
                    <div class="timeline__dot <?= $dotClass ?>"></div>
                    <div class="timeline__content"><?= e(mb_substr($row['uraian_kegiatan'], 0, 60)) ?><?= strlen($row['uraian_kegiatan']) > 60 ? '...' : '' ?></div>
                    <div class="timeline__time"><i class="bi bi-clock"></i> <?= e($tglShort) ?> · <?= e($row['nama_rhk'] ?? 'Umum') ?></div>
                </div>
                <?php endwhile; ?>
                <?php if ($no === 0): ?>
                <div class="timeline__item">
                    <div class="timeline__dot timeline__dot--primary"></div>
                    <div class="timeline__content">Belum ada aktivitas</div>
                    <div class="timeline__time">Tambahkan laporan pertama Anda</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="section-head section-head--with-action">
            <div class="section-head__title">Laporan Terbaru</div>
            <a href="laporan_harian.php" class="btn btn--ghost btn--sm">Lihat Semua <i class="bi bi-chevron-right"></i></a>
        </div>
        <div class="list">
            <?php
            $recent->data_seek(0);
            $no2 = 0;
            while ($row = $recent->fetch_assoc()):
                $no2++;
                $dt = DateTime::createFromFormat('Y-m-d', $row['tanggal']);
                $tglShort = (int)$dt->format('d') . ' ' . $_arrBulan[(int)$dt->format('m')];
                $uraian = trim($row['uraian_kegiatan']);
                if (strlen($uraian) > 60) $uraian = rtrim(substr($uraian, 0, 60)) . '...';
                $status = $no2 <= 2 ? 'success' : 'warning';
                $statusTxt = $no2 <= 2 ? 'Selesai' : 'Proses';
            ?>
            <div class="list__item">
                <div class="list__icon list__icon--<?= $status ?>"><i class="bi bi-file-earmark-text"></i></div>
                <div class="list__body">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                        <div class="list__title flex-grow-1"><?= e($uraian) ?></div>
                        <span class="badge badge--<?= $status ?> badge--sm"><?= $statusTxt ?></span>
                    </div>
                    <div class="list__desc"><i class="bi bi-calendar3"></i> <?= e($tglShort) ?> · <?= e($row['nama_rhk'] ?? 'Umum') ?></div>
                </div>
            </div>
            <?php endwhile; ?>
            <?php if ($no2 === 0): ?>
            <div class="list__item">
                <div class="list__body text-center py-4">
                    <div class="list__title">Belum ada laporan</div>
                    <div class="list__desc">Mulai dengan menambahkan laporan harian pertama Anda.</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Menu Cepat -->
<div class="section-head mb-3">
    <div class="section-head__title">Menu Cepat</div>
</div>
<div class="list mb-5">
    <a href="rhk.php" class="list__item">
        <div class="list__icon list__icon--primary"><i class="bi bi-list-check"></i></div>
        <div class="list__body">
            <div class="list__title">Data Rencana Aksi</div>
            <div class="list__desc">Kelola master RHK & indikator</div>
        </div>
        <div class="list__trail"><i class="bi bi-chevron-right text-muted"></i></div>
    </a>
    <a href="rekap_bulanan.php" class="list__item">
        <div class="list__icon list__icon--info"><i class="bi bi-calendar-month"></i></div>
        <div class="list__body">
            <div class="list__title">Rekap Bulanan</div>
            <div class="list__desc">Lihat ringkasan capaian bulanan</div>
        </div>
        <div class="list__trail"><i class="bi bi-chevron-right text-muted"></i></div>
    </a>
    <a href="cetak_pdf.php?bulan=<?= (int)$now->format('n') ?>&tahun=<?= (int)$now->format('Y') ?>" class="list__item">
        <div class="list__icon list__icon--success"><i class="bi bi-printer"></i></div>
        <div class="list__body">
            <div class="list__title">Cetak Dokumen</div>
            <div class="list__desc">Cetak laporan format PDF</div>
        </div>
        <div class="list__trail"><i class="bi bi-chevron-right text-muted"></i></div>
    </a>
</div>

<!-- FAB (Mobile) -->
<button type="button" class="fab" data-bs-toggle="modal" data-bs-target="#tambahLaporanModal" aria-label="Tambah Laporan Baru">
    <i class="bi bi-plus-lg"></i> Tambah Laporan
</button>

<!-- Bottom Navigation (Mobile) -->
<nav class="bottom-nav">
    <a href="dashboard.php" class="bottom-nav__item bottom-nav__item--active">
        <i class="bi bi-house-door"></i><span>Beranda</span>
    </a>
    <a href="laporan_harian.php" class="bottom-nav__item">
        <i class="bi bi-journal-text"></i><span>Laporan</span>
    </a>
    <a href="rekap_bulanan.php" class="bottom-nav__item">
        <i class="bi bi-calendar3"></i><span>Kalender</span>
    </a>
    <a href="rekap_bulanan.php#analityk" class="bottom-nav__item">
        <i class="bi bi-bar-chart-line"></i><span>Analitik</span>
    </a>
    <a href="profil.php" class="bottom-nav__item">
        <i class="bi bi-person-circle"></i><span>Profil</span>
    </a>
</nav>

<?php
// --- Modal Tambah Laporan ---
$today = date('Y-m-d');
$bulanD = (int)date('n');
$tahunD = (int)date('Y');
$stmtTambah = $mysqli->prepare('SELECT DISTINCT r.id, r.nama_rhk, r.indikator, r.bulan,
                                        TRIM(ra.indikator) AS label
                                 FROM rencana_hasil_kerja r
                                 INNER JOIN rencana_aksi ra ON ra.rhk_id = r.id AND ra.bulan = ? AND ra.tahun = ?
                                 WHERE r.user_id = ? AND (r.bulan = ? OR r.bulan = 0) AND r.tahun = ?
                                   AND NULLIF(TRIM(ra.indikator), \'\') IS NOT NULL
                                 ORDER BY label ASC, r.nama_rhk ASC, r.id DESC');
if ($stmtTambah) {
    $stmtTambah->bind_param('iiiii', $bulanD, $tahunD, $uid, $bulanD, $tahunD);
    $stmtTambah->execute();
    $resultTambah = $stmtTambah->get_result();
} else {
    $resultTambah = false;
}
$hasRHK = ($resultTambah && $resultTambah->num_rows > 0);
?>
<div class="modal fade md-modal" id="tambahLaporanModal" tabindex="-1" aria-labelledby="tambahLaporanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable md-modal__dialog md-modal__dialog--lg">
        <div class="modal-content">
            <form action="simpan_laporan.php" method="POST" enctype="multipart/form-data" id="formTambahLaporan" data-guard-submit>
                <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-header">
                    <span class="md-icon-tile md-icon-tile--primary" aria-hidden="true"><i class="bi bi-plus-circle"></i></span>
                    <div>
                        <h5 class="md-modal__title" id="tambahLaporanModalLabel">Tambah Data Laporan Harian</h5>
                        <p class="md-modal__subtitle">Isi data kegiatan harian dengan lengkap</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <!-- Validasi Alert -->
                    <div class="md-alert md-alert--danger md-alert--sm md-sp-6" id="formAlert" role="alert" style="display:none;">
                        <span class="md-alert__icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
                        <div>
                            <div class="md-alert__title">Periksa kembali</div>
                            <div class="md-alert__text" id="formAlertText">Mohon lengkapi semua field wajib.</div>
                        </div>
                    </div>

                    <!-- Section 1: Informasi Kegiatan -->
                    <div class="md-form-section">
                        <div class="md-form-section__title"><i class="bi bi-info-circle" aria-hidden="true"></i> Informasi Kegiatan</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="md-field">
                                    <label class="md-label md-label--req" for="dashTanggal">Tanggal</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
                                        <input type="date" name="tanggal" id="dashTanggal" class="md-input md-label-group__text" required value="<?= $today ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="md-field">
                                    <label class="md-label md-label--req" for="dashRHK">Rencana Aksi</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-list-check"></i></span>
                                        <select name="rhk_id" id="dashRHK" class="md-select md-label-group__text" required data-empty-href="rhk.php?aksi=tambah">
                                            <option value="">-- Pilih Rencana Aksi --</option>
                                            <?php
                                            if ($hasRHK) {
                                                $resultTambah->data_seek(0);
                                                while ($r = $resultTambah->fetch_assoc()):
                                                    $lbl = trim((string)($r['label'] ?? ''));
                                                    if ($lbl === '') $lbl = '[RHK #' . (int)$r['id'] . ']';
                                            ?>
                                            <option value="<?= (int)$r['id'] ?>"><?= e($lbl) ?></option>
                                            <?php endwhile; } ?>
                                        </select>
                                    </div>
                                    <div class="md-hint md-hint--danger" id="dashRHKEmpty" style="<?= $hasRHK ? 'display:none;' : '' ?>">
                                        <i class="bi bi-info-circle me-1"></i>Belum ada Rencana Aksi. <a href="rhk.php?aksi=tambah" id="dashRHKEmptyLink" target="_blank" rel="noopener">Tambah Rencana Aksi</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Detail Aktivitas -->
                    <div class="md-form-section">
                        <div class="md-form-section__title"><i class="bi bi-journal-text" aria-hidden="true"></i> Detail Aktivitas</div>
                        <div class="md-field">
                            <label class="md-label md-label--req" for="dashUraian">Kegiatan Harian</label>
                            <textarea name="uraian_kegiatan" id="dashUraian" class="md-textarea" rows="4" required placeholder="Jelaskan secara rinci kegiatan yang dilakukan..."></textarea>
                        </div>
                    </div>

                    <!-- Section 3: Waktu Pelaksanaan -->
                    <div class="md-form-section">
                        <div class="md-form-section__title"><i class="bi bi-clock-history" aria-hidden="true"></i> Waktu Pelaksanaan</div>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="md-field">
                                    <label class="md-label md-label--req" for="dashJamMulai">Jam Mulai</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-clock"></i></span>
                                        <input type="time" name="jam_mulai" id="dashJamMulai" class="md-input md-label-group__text" required value="07:30">
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="md-field">
                                    <label class="md-label md-label--req" for="dashJamSelesai">Jam Selesai</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-clock"></i></span>
                                        <input type="time" name="jam_selesai" id="dashJamSelesai" class="md-input md-label-group__text" required value="16:00">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Dokumentasi -->
                    <div class="md-form-section">
                        <div class="md-form-section__title"><i class="bi bi-images" aria-hidden="true"></i> Dokumentasi</div>
                        <div class="md-field">
                            <label class="md-label" for="bukti_input_dash">Foto Bukti</label>
                            <div class="md-upload" id="uploadAreaDash" role="button" tabindex="0" data-upload-target="bukti_input_dash" aria-label="Pilih atau seret foto bukti">
                                <i class="bi bi-cloud-upload md-upload__icon" aria-hidden="true"></i>
                                <div class="md-upload__text">Seret file ke sini atau <strong>klik untuk memilih</strong></div>
                                <div class="md-upload__hint">Format: JPG/PNG · Maks. 5 MB per file · Maks. 5 foto</div>
                            </div>
                            <input type="file" name="bukti[]" id="bukti_input_dash" class="md-input" accept="image/jpeg,image/png" multiple style="display:none;">
                            <div id="bukti_preview_dash" class="md-upload-preview"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="md-btn md-btn--ghost" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle" aria-hidden="true"></i> Batal
                    </button>
                    <button type="submit" class="md-btn md-btn--primary" id="dashSubmitBtn">
                        <i class="bi bi-save" aria-hidden="true"></i> Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    /* Area unggah (.md-upload) ditangani bersama oleh initUploadDropzones() di app.js
       melalui atribut data-upload-target pada markup. */

    // RHK dropdown dinamis berdasarkan tanggal
    var updater = window.__updateRHKSelectByTanggal || null;
    var dT = document.getElementById('dashTanggal');
    var dR = document.getElementById('dashRHK');
    var dREmpty = document.getElementById('dashRHKEmpty');
    var dREmptyLink = document.getElementById('dashRHKEmptyLink');
    function dTrigger() { if (updater) return updater(dT, dR, dREmpty, dREmptyLink); return Promise.resolve(false); }
    if (dT && dR) {
        dT.addEventListener('change', dTrigger);
        dT.addEventListener('blur', dTrigger);
    }

    // Klik tanggal pada kalender -> buka modal tambah laporan dengan tanggal terisi
    var modalEl = document.getElementById('tambahLaporanModal');
    function openTambahForTanggal(tanggal) {
        if (dT && tanggal) {
            dT.value = tanggal;
            if (updater) dTrigger();
        }
        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }
    document.querySelectorAll('.calendar-cell[data-add-tanggal]').forEach(function (cell) {
        function onActivate(e) {
            // Biarkan tautan "lihat laporan" pada sel berisi laporan tetap berfungsi
            if (e && e.target && e.target.closest && e.target.closest('a[href]')) return;
            if (e) e.preventDefault();
            openTambahForTanggal(cell.getAttribute('data-add-tanggal'));
        }
        cell.addEventListener('click', onActivate);
        cell.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') onActivate(e);
        });
    });

    // Validasi form sebelum submit
    var form = document.getElementById('formTambahLaporan');
    var alertBox = document.getElementById('formAlert');
    var alertText = document.getElementById('formAlertText');
    if (form) {
        form.addEventListener('submit', function(e) {
            var tgl = form.querySelector('[name="tanggal"]');
            var rhk = form.querySelector('[name="rhk_id"]');
            var uraian = form.querySelector('[name="uraian_kegiatan"]');
            var jamM = form.querySelector('[name="jam_mulai"]');
            var jamS = form.querySelector('[name="jam_selesai"]');
            var missing = [];
            if (!tgl.value) missing.push('Tanggal');
            if (!rhk.value) missing.push('Rencana Aksi');
            if (!uraian.value.trim()) missing.push('Kegiatan Harian');
            if (!jamM.value) missing.push('Jam Mulai');
            if (!jamS.value) missing.push('Jam Selesai');
            if (missing.length > 0) {
                e.preventDefault();
                alertText.textContent = 'Mohon lengkapi: ' + missing.join(', ');
                alertBox.style.display = 'flex';
                try { alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (err) {}
                return false;
            }
            alertBox.style.display = 'none';
        });
        // Sembunyikan alert saat user mulai mengisi
        form.addEventListener('input', function() {
            if (alertBox.style.display === 'flex') alertBox.style.display = 'none';
        });
    }

    // Modal shown: trigger RHK update
    var mdld = document.getElementById('tambahLaporanModal');
    if (mdld && typeof bootstrap !== 'undefined' && bootstrap && bootstrap.Modal) {
        mdld.addEventListener('shown.bs.modal', function () {
            setTimeout(function () { dTrigger().then(function () {
                if (dR) { dR.disabled = false; }
                if (dT) { try { dT.focus(); } catch (e) {} }
            }); }, 60);
        });
    }
})();
</script>
<script>
    // app.js dimuat di footer, jadi inisialisasi pratinjau foto menunggu DOM siap.
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof setupImagePreview === 'function') {
            setupImagePreview('bukti_input_dash', 'bukti_preview_dash', 5);
        }
    });
</script>
<?php if ($stmtTambah) { $stmtTambah->close(); } ?>

<?php require_once __DIR__ . '/../Views/layouts/footer.php'; ?>
