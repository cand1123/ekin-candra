<?php
require_once __DIR__ . '/../helpers/session.php';
require_login();
require_once dirname(__DIR__, 2) . '/app/Config/database.php';

$rhkCols = [];
$rhkColsRes = $mysqli->query('DESCRIBE rencana_hasil_kerja');
if ($rhkColsRes) {
    while ($r = $rhkColsRes->fetch_assoc()) $rhkCols[$r['Field']] = true;
}
if (!isset($rhkCols['bulan'])) {
    $mysqli->query("ALTER TABLE rencana_hasil_kerja ADD COLUMN bulan TINYINT NOT NULL DEFAULT 0 COMMENT '0=Semua Bulan, 1-12=Januari-Desember' AFTER tahun");
    $mysqli->query("ALTER TABLE rencana_hasil_kerja ADD INDEX idx_rhk_bulan_tahun (user_id, bulan, tahun)");
}

$raTableExists = false;
$raColsRes = $mysqli->query('DESCRIBE rencana_aksi');
if ($raColsRes) {
    $raTableExists = true;
    $raCols = [];
    while ($r = $raColsRes->fetch_assoc()) $raCols[$r['Field']] = true;
    $raColsRes->free();
}
if (!$raTableExists) {
    $mysqli->query("CREATE TABLE rencana_aksi (
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

$uid = current_user_id();
$msg = '';
$msgType = '';

$aksi = $_GET['aksi'] ?? 'daftar';
$idEdit = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$form = [
    'id' => '',
    'nama_rhk' => '',
    'indikator' => '',
    'target' => '',
    'output' => '',
    'bulan' => 0,
    'tahun' => (int)date('Y'),
];

/* Query string filter aktif (tahun + bulan) agar tetap tersimpan saat redirect */
function rhk_filter_qs() {
    $qs = [];
    if (isset($_GET['tahun']) && $_GET['tahun'] !== '') {
        $t = (int)$_GET['tahun'];
        if ($t >= 2000 && $t <= 2100) $qs['tahun'] = $t;
    }
    if (isset($_GET['bulan']) && $_GET['bulan'] !== '') {
        $b = (int)$_GET['bulan'];
        if ($b >= 1 && $b <= 12) $qs['bulan'] = $b;
    }
    return $qs;
}

function rhk_redirect($msg, $type) {
    $_SESSION['flash_rhk'] = ['msg' => $msg, 'type' => $type];

    $customFrom = isset($_POST['_from']) ? trim((string)$_POST['_from']) : '';
    if ($customFrom === 'pengaturan') {
        $qs = [];
        if ($type === 'success') $qs['status'] = 'sukses';
        else $qs['status'] = 'gagal';

        $t = null;
        if (isset($_POST['_redirect_thn']) && $_POST['_redirect_thn'] !== '') {
            $test = (int)$_POST['_redirect_thn'];
            if ($test >= 2000 && $test <= 2100) $t = $test;
        }
        if ($t === null && isset($_POST['tahun']) && $_POST['tahun'] !== '') {
            $test = (int)$_POST['tahun'];
            if ($test >= 2000 && $test <= 2100) $t = $test;
        }
        if ($t !== null) $qs['thn'] = $t;
        $url = 'pengaturan.php' . (!empty($qs) ? '?' . http_build_query($qs) : '');
        header('Location: ' . $url);
        exit;
    }

    $qs = rhk_filter_qs();
    $url = 'rhk.php' . (!empty($qs) ? '?' . http_build_query($qs) : '');
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['simpan']) || isset($_POST['aksi_simpan'])) {
        $form['id'] = trim($_POST['id'] ?? '');
        $form['nama_rhk'] = trim($_POST['nama_rhk'] ?? '');
        $form['target'] = trim($_POST['target'] ?? '');
        $form['output'] = trim($_POST['output'] ?? '');
        $form['bulan'] = (int)($_POST['bulan'] ?? 0);
        $form['tahun'] = (int)($_POST['tahun'] ?? (int)date('Y'));

        $aksi = !empty($form['id']) ? 'edit' : 'tambah';
        $idEdit = (int)$form['id'];

        /* RHK dikelola per tahun: bulan 0 = berlaku sepanjang tahun */
        if ($form['bulan'] < 0 || $form['bulan'] > 12) {
            $form['bulan'] = 0;
        }
        if ($form['tahun'] < 2000 || $form['tahun'] > 2100) {
            $form['tahun'] = (int)date('Y');
        }

        $isQuickFromPengaturan = (isset($_POST['_from']) && trim((string)$_POST['_from']) === 'pengaturan');

        $wajib = ($form['nama_rhk'] === '' || $form['target'] === '' || $form['output'] === '');

        if ($wajib) {
            $msg = 'Nama RHK, Target, dan Output wajib diisi.';
            $msgType = 'danger';

            $saveFlash = $form;
            $saveFlash['msg'] = $msg;
            $saveFlash['msgType'] = $msgType;
            $saveFlash['_mode'] = ($idEdit > 0) ? 'edit' : 'tambah';
            $saveFlash['id'] = $idEdit > 0 ? $idEdit : '';

            $qsBack = [];
            if (isset($_POST['tahun']) && $_POST['tahun'] !== '' && (int)$_POST['tahun'] >= 2000 && (int)$_POST['tahun'] <= 2100) {
                $qsBack['tahun'] = (int)$_POST['tahun'];
            } elseif (isset($_GET['tahun']) && $_GET['tahun'] !== '' && (int)$_GET['tahun'] >= 2000 && (int)$_GET['tahun'] <= 2100) {
                $qsBack['tahun'] = (int)$_GET['tahun'];
            }
            if (isset($_GET['bulan']) && $_GET['bulan'] !== '' && (int)$_GET['bulan'] >= 1 && (int)$_GET['bulan'] <= 12) {
                $qsBack['bulan'] = (int)$_GET['bulan'];
            }
            $saveFlash['_redirect_qs'] = !empty($qsBack) ? http_build_query($qsBack) : '';
            if ($isQuickFromPengaturan) {
                $pqs = [];
                $pqs['status'] = 'gagal';
                if (isset($_POST['_redirect_thn']) && $_POST['_redirect_thn'] !== '' && (int)$_POST['_redirect_thn'] >= 2000 && (int)$_POST['_redirect_thn'] <= 2100) {
                    $pqs['thn'] = (int)$_POST['_redirect_thn'];
                } elseif (isset($qsBack['tahun'])) {
                    $pqs['thn'] = $qsBack['tahun'];
                }
                $_SESSION['flash_rhk_form'] = $saveFlash;
                header('Location: pengaturan.php?' . http_build_query($pqs));
                exit;
            } else {
                $_SESSION['flash_rhk_form'] = $saveFlash;
                header('Location: rhk.php' . (!empty($qsBack) ? '?' . http_build_query($qsBack) : ''));
                exit;
            }
        } else {
            $indikatorKosong = '';
            if ($idEdit > 0) {
                $stmt = $mysqli->prepare('UPDATE rencana_hasil_kerja SET nama_rhk=?, indikator=?, target=?, output=?, bulan=?, tahun=? WHERE id=? AND user_id=?');
                $stmt->bind_param('ssssiiii', $form['nama_rhk'], $indikatorKosong, $form['target'], $form['output'], $form['bulan'], $form['tahun'], $idEdit, $uid);
                $ok = $stmt->execute();
                $stmt->close();
                rhk_redirect(
                    $ok ? 'RHK berhasil diperbarui.' : 'Gagal memperbarui RHK.',
                    $ok ? 'success' : 'danger'
                );
            } else {
                $stmt = $mysqli->prepare('INSERT INTO rencana_hasil_kerja (user_id, nama_rhk, indikator, target, output, bulan, tahun) VALUES (?,?,?,?,?,?,?)');
                $stmt->bind_param('issssii', $uid, $form['nama_rhk'], $indikatorKosong, $form['target'], $form['output'], $form['bulan'], $form['tahun']);
                $ok = $stmt->execute();
                $stmt->close();
                rhk_redirect(
                    $ok ? 'RHK berhasil ditambahkan.' : 'Gagal menambahkan RHK.',
                    $ok ? 'success' : 'danger'
                );
            }
        }
    } elseif (isset($_POST['hapus_id'])) {
        $id = (int)$_POST['hapus_id'];
        $stmt = $mysqli->prepare('DELETE FROM rencana_hasil_kerja WHERE id=? AND user_id=?');
        $stmt->bind_param('ii', $id, $uid);
        $ok = $stmt->execute();
        $stmt->close();
        rhk_redirect(
            $ok ? 'Rencana Aksi berhasil dihapus.' : 'Gagal menghapus Rencana Aksi.',
            $ok ? 'success' : 'danger'
        );
    } elseif (isset($_POST['simpan_rencana_aksi'])) {
        $rhkId = (int)($_POST['rhk_id'] ?? 0);
        $indikator = trim((string)($_POST['indikator'] ?? ''));
        $bulanIni = (int)date('n');
        $tahunIni = (int)date('Y');

        if ($rhkId <= 0) {
            rhk_redirect('RHK tidak valid.', 'danger');
        }

        $stmtCek = $mysqli->prepare('SELECT id FROM rencana_hasil_kerja WHERE id=? AND user_id=? LIMIT 1');
        $stmtCek->bind_param('ii', $rhkId, $uid);
        $stmtCek->execute();
        $adaRHK = $stmtCek->get_result()->fetch_assoc();
        $stmtCek->close();

        if (!$adaRHK) {
            rhk_redirect('RHK tidak ditemukan.', 'danger');
        }

        if ($indikator === '') {
            $indikator = '-';
        }

        $stmt = $mysqli->prepare(
            'INSERT INTO rencana_aksi (rhk_id, bulan, tahun, indikator, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE indikator = VALUES(indikator), updated_at = NOW()'
        );
        $stmt->bind_param('iiis', $rhkId, $bulanIni, $tahunIni, $indikator);
        $ok = $stmt->execute();
        $stmt->close();

        rhk_redirect(
            $ok ? 'Rencana Aksi bulan ini berhasil disimpan.' : 'Gagal menyimpan Rencana Aksi.',
            $ok ? 'success' : 'danger'
        );
    }
}

$rhkFlashForm = null;
$autoOpenModal = false;
$autoOpenMode = 'tambah';
$autoOpenId = 0;
$redirectFiltersQS = '';

if (!empty($_SESSION['flash_rhk'])) {
    $msg = $_SESSION['flash_rhk']['msg'] ?? '';
    $msgType = $_SESSION['flash_rhk']['type'] ?? '';
    unset($_SESSION['flash_rhk']);
}

if (!empty($_SESSION['flash_rhk_form']) && is_array($_SESSION['flash_rhk_form'])) {
    $rhkFlashForm = $_SESSION['flash_rhk_form'];
    $msg = $rhkFlashForm['msg'] ?? $msg;
    $msgType = $rhkFlashForm['msgType'] ?? $msgType;
    $autoOpenModal = true;
    $autoOpenMode = ($rhkFlashForm['_mode'] === 'edit') ? 'edit' : 'tambah';
    $autoOpenId = (int)($rhkFlashForm['id'] ?? 0);
    if ($autoOpenMode === 'edit' && $autoOpenId > 0) {
        $form = [
            'id' => (string)$autoOpenId,
            'nama_rhk' => trim($rhkFlashForm['nama_rhk'] ?? ''),
            'target' => trim($rhkFlashForm['target'] ?? ''),
            'output' => trim($rhkFlashForm['output'] ?? ''),
            'bulan' => (int)($rhkFlashForm['bulan'] ?? (int)date('n')),
            'tahun' => (int)($rhkFlashForm['tahun'] ?? (int)date('Y')),
        ];
    } else {
        $form = [
            'id' => '',
            'nama_rhk' => trim($rhkFlashForm['nama_rhk'] ?? ''),
            'target' => trim($rhkFlashForm['target'] ?? ''),
            'output' => trim($rhkFlashForm['output'] ?? ''),
            'bulan' => (int)($rhkFlashForm['bulan'] ?? (int)date('n')),
            'tahun' => (int)($rhkFlashForm['tahun'] ?? (int)date('Y')),
        ];
    }
    if (isset($rhkFlashForm['_redirect_qs']) && is_string($rhkFlashForm['_redirect_qs'])) {
        $redirectFiltersQS = $rhkFlashForm['_redirect_qs'];
    }
    unset($_SESSION['flash_rhk_form']);
}

if ($aksi === 'edit' && $idEdit > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST' && $rhkFlashForm === null) {
    $stmt = $mysqli->prepare('SELECT * FROM rencana_hasil_kerja WHERE id=? AND user_id=? LIMIT 1');
    $stmt->bind_param('ii', $idEdit, $uid);
    $stmt->execute();
    $dataEdit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$dataEdit) {
        $qsR = rhk_filter_qs();
        $_SESSION['flash_rhk'] = ['msg' => 'Data Rencana Aksi tidak ditemukan.', 'type' => 'danger'];
        header('Location: rhk.php' . (!empty($qsR) ? '?' . http_build_query($qsR) : ''));
        exit;
    }
    $form['id'] = $dataEdit['id'];
    $form['nama_rhk'] = $dataEdit['nama_rhk'];
    $form['target'] = $dataEdit['target'];
    $form['output'] = $dataEdit['output'];
    $form['bulan']  = isset($dataEdit['bulan']) ? (int)$dataEdit['bulan'] : 0;
    $form['tahun']  = (int)$dataEdit['tahun'];
    $autoOpenModal = true;
    $autoOpenMode = 'edit';
    $autoOpenId = (int)$dataEdit['id'];
} elseif ($aksi === 'edit' && $idEdit > 0 && !$autoOpenModal) {
    $form['id'] = $idEdit;
}

if ($aksi === 'edit' && $idEdit <= 0 && !$autoOpenModal) {
    $qsR = rhk_filter_qs();
    header('Location: rhk.php?aksi=tambah' . (!empty($qsR) ? '&' . http_build_query($qsR) : ''));
    exit;
}

if (($aksi === 'tambah') && $_SERVER['REQUEST_METHOD'] !== 'POST' && $rhkFlashForm === null) {
    if (isset($_GET['tahun']) && $_GET['tahun'] !== '') {
        $t = (int)$_GET['tahun'];
        if ($t >= 2000 && $t <= 2100) $form['tahun'] = $t;
    }
    $autoOpenModal = true;
    $autoOpenMode = 'tambah';
    $autoOpenId = 0;
}

$pageTitle = 'Data Rencana Aksi - Sistem Laporan ASN';
require_once __DIR__ . '/../Views/layouts/header.php';

$namaBulanRHK = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

/* RHK dikelola per tahun (bulan = 0). Rencana aksi ditampilkan sesuai bulan yang dipilih. */
$f_tahun = (int)($_GET['tahun'] ?? (int)date('Y'));
if ($f_tahun < 2000 || $f_tahun > 2100) $f_tahun = (int)date('Y');

$bulanBerjalan = (int)date('n');
$tahunBerjalan = (int)date('Y');

$f_bulan = (int)($_GET['bulan'] ?? $bulanBerjalan);
if ($f_bulan < 1 || $f_bulan > 12) $f_bulan = $bulanBerjalan;

/* Rencana Aksi hanya dapat diisi/diedit pada bulan berjalan */
$bulanAktif = ($f_bulan === $bulanBerjalan && $f_tahun === $tahunBerjalan);

$stmt = $mysqli->prepare("SELECT r.*, ra.indikator AS aksi_bulan_ini, ra.id AS ra_id
                          FROM rencana_hasil_kerja r
                          LEFT JOIN rencana_aksi ra ON ra.rhk_id = r.id AND ra.bulan = ? AND ra.tahun = r.tahun
                          WHERE r.user_id = ? AND r.tahun = ?
                          ORDER BY r.tahun DESC, r.nama_rhk ASC");
$stmt->bind_param('iii', $f_bulan, $uid, $f_tahun);
$stmt->execute();
$listRHK = $stmt->get_result();

$raHistory = [];
$stmtRa = $mysqli->prepare(
    "SELECT ra.rhk_id, ra.bulan, ra.tahun, ra.indikator
     FROM rencana_aksi ra
     INNER JOIN rencana_hasil_kerja r ON r.id = ra.rhk_id
     WHERE r.user_id = ?
     ORDER BY ra.tahun DESC, ra.bulan DESC"
);
$stmtRa->bind_param('i', $uid);
$stmtRa->execute();
$resRa = $stmtRa->get_result();
while ($raRow = $resRa->fetch_assoc()) {
    $rhkId = (int)$raRow['rhk_id'];
    if (!isset($raHistory[$rhkId])) $raHistory[$rhkId] = [];
    $raHistory[$rhkId][] = [
        'bulan' => (int)$raRow['bulan'],
        'tahun' => (int)$raRow['tahun'],
        'indikator' => $raRow['indikator'],
    ];
}
$stmtRa->close();
$raHistoryJSON = json_encode($raHistory);

$tahunSekarang = (int)date('Y');
$tahunAwal = $tahunSekarang - 3;
$tahunAkhir = $tahunSekarang + 3;
$tahunForm = (int)$form['tahun'];
?>

    <!-- ============== PAGE HEADER ============== -->
    <div class="md-page-header">
        <div>
            <h2 class="md-page-header__title"><i class="bi bi-list-check"></i>Data Rencana Aksi</h2>
            <p class="md-page-header__subtitle">Kelola Rencana Hasil Kerja (RHK) per tahun beserta detail Rencana Aksi</p>
        </div>
    </div>

    <!-- ============== TOP ACTION BAR ============== -->
    <div class="md-filter-bar md-sp-6">
        <div class="md-filter-bar__group">
            <div class="md-filter-bar__item">
                <div class="md-label-group">
                    <i class="bi bi-calendar-check md-label-group__icon"></i>
                    <select name="tahun" id="tahun" class="md-select md-label-group__text"
                            onchange="window.location.href='rhk.php?tahun='+this.value+'&bulan=<?= (int)$f_bulan ?>'">
                        <?php for ($t = $tahunAwal; $t <= $tahunAkhir; $t++): ?>
                            <option value="<?= $t ?>" <?= $f_tahun === $t ? 'selected' : '' ?>>
                                Tahun <?= $t ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="md-filter-bar__item">
                <div class="md-label-group">
                    <i class="bi bi-calendar3 md-label-group__icon"></i>
                    <select name="bulan" id="bulan" class="md-select md-label-group__text"
                            onchange="window.location.href='rhk.php?tahun=<?= (int)$f_tahun ?>&bulan='+this.value">
                        <?php for ($b = 1; $b <= 12; $b++): ?>
                            <option value="<?= $b ?>" <?= $f_bulan === $b ? 'selected' : '' ?>>
                                <?= $namaBulanRHK[$b] ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="md-filter-bar__group">
            <button type="button" class="md-btn md-btn--primary"
                    data-default-tahun="<?= (int)$f_tahun ?>"
                    onclick="return openRHKTambah(this)">
                <i class="bi bi-plus-lg me-2"></i>Tambah RHK
            </button>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="md-alert md-alert--<?= htmlspecialchars($msgType === 'success' ? 'success' : 'danger') ?> md-sp-6" role="alert">
            <div class="md-alert__icon">
                <i class="bi bi-<?= $msgType === 'success' ? 'check2-circle' : 'exclamation-triangle' ?>"></i>
            </div>
            <div class="md-alert__body flex-grow-1">
                <div class="md-alert__text"><?= htmlspecialchars($msg) ?></div>
            </div>
            <button type="button" class="md-alert__close" data-bs-dismiss="alert" aria-label="Tutup">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    <?php endif; ?>

    <?php if ($listRHK->num_rows > 0): ?>
        <div class="md-sp-8 table-responsive">
            <table class="rhk-table rhk-table--compact rhk-table--aksi-list">
                <thead>
                    <tr>
                        <th class="rhk-table__no">NO.</th>
                        <th class="rhk-table__rhk">RENCANA HASIL KERJA</th>
                        <th class="rhk-table__aksi">
                            RENCANA AKSI
                            <div class="text-muted" style="text-transform:none;"><?= htmlspecialchars($namaBulanRHK[$f_bulan] . ' ' . $f_tahun) ?></div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php $noTbl = 1; while ($row = $listRHK->fetch_assoc()):
                        $rowBln = $f_bulan;
                        $targetTeks = trim((string)($row['target'] ?? ''));
                        $indikatorShort = trim((string)($row['aksi_bulan_ini'] ?? ''));
                        $adaAksi = !empty($indikatorShort);
                    ?>
                        <tr>
                            <td class="rhk-table__no"><?= $noTbl ?></td>
                            <td class="rhk-table__rhk">
                                <div class="rhk-table__rhk-text"><?= htmlspecialchars($row['nama_rhk']) ?></div>
                                <div class="rhk-table__badge-row">
                                    <span class="rhk-badge--individu">
                                        <i class="bi bi-person-badge"></i> Individu
                                    </span>
                                </div>
                                <button type="button"
                                        class="rhk-btn rhk-btn--green"
                                        data-id="<?= (int)$row['id'] ?>"
                                        data-nama="<?= htmlspecialchars($row['nama_rhk']) ?>"
                                        data-bulan="<?= $rowBln ?>"
                                        data-tahun="<?= (int)$row['tahun'] ?>"
                                        data-aksi="<?= htmlspecialchars($indikatorShort) ?>"
                                        onclick="return openRencanaAksiModal(this)">
                                    <?php if ($bulanAktif): ?>
                                        <i class="bi bi-plus-lg"></i> Tambah Rencana Aksi
                                    <?php else: ?>
                                        <i class="bi bi-eye"></i> Lihat Rencana Aksi
                                    <?php endif; ?>
                                </button>
                            </td>
                            <td class="rhk-table__aksi">
                                <?php if ($adaAksi): ?>
                                    <div class="rhk-aksi-item"><strong>1.</strong> <?= htmlspecialchars($indikatorShort) ?></div>
                                    <div class="rhk-table__badge-row">
                                        <span class="rhk-badge--trajectory">
                                            <i class="bi bi-graph-up-arrow"></i> Trajectory
                                        </span>
                                    </div>
                                    <div class="rhk-aksi-target"><strong>Target :</strong> <?= htmlspecialchars($targetTeks) ?></div>
                                    <div class="rhk-btn-group">
                                        <button type="button"
                                                class="rhk-btn rhk-btn--orange"
                                                data-id="<?= (int)$row['id'] ?>"
                                                data-nama="<?= htmlspecialchars($row['nama_rhk']) ?>"
                                                data-target="<?= htmlspecialchars($row['target']) ?>"
                                                data-output="<?= htmlspecialchars($row['output']) ?>"
                                                data-bulan="<?= $rowBln ?>"
                                                data-tahun="<?= (int)$row['tahun'] ?>"
                                                onclick="return openRHKEditFromList(this)">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <button type="button"
                                                class="rhk-btn rhk-btn--red"
                                                onclick="confirmDeletePost(<?= (int)$row['id'] ?>)">
                                            <i class="bi bi-trash3"></i> Hapus
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="rhk-aksi-empty">Belum ada rencana aksi pada bulan ini.</div>
                                    <button type="button"
                                            class="rhk-btn rhk-btn--green mt-2"
                                            data-id="<?= (int)$row['id'] ?>"
                                            data-nama="<?= htmlspecialchars($row['nama_rhk']) ?>"
                                            data-bulan="<?= $rowBln ?>"
                                            data-tahun="<?= (int)$row['tahun'] ?>"
                                            data-aksi=""
                                            onclick="return openRencanaAksiModal(this)">
                                        <?php if ($bulanAktif): ?>
                                            <i class="bi bi-plus-lg"></i> Tambah
                                        <?php else: ?>
                                            <i class="bi bi-eye"></i> Lihat
                                        <?php endif; ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php $noTbl++; endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="md-empty md-sp-8">
            <div class="md-empty__icon mb-4"><i class="bi bi-folder-x"></i></div>
            <div class="md-empty__title mb-2">Belum ada RHK untuk tahun ini</div>
            <div class="md-empty__desc text-muted mx-auto" style="max-width: 520px;">
                Tahun <strong><?= (int)$f_tahun ?></strong>
                belum memiliki Rencana Hasil Kerja.
            </div>
        </div>
    <?php endif; ?>

        <form method="POST" action="rhk.php" id="formHapusRHK" class="d-none">
            <input type="hidden" name="hapus_id" id="hapus_id">
        </form>
        <script>
            function confirmDeletePost(id) {
                if (confirm('Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.')) {
                    document.getElementById('hapus_id').value = id;
                    document.getElementById('formHapusRHK').submit();
                }
            }
        </script>

<?php
$mF = '';
$mRT = null;
$mHrefBack = 'rhk.php';
if ($mRT !== null) {
    $mHrefBack .= '?tahun='.(int)$mRT;
}
?>
    <!-- ============== MODAL TAMBAH / EDIT RHK ============== -->
    <div class="modal fade md-modal" id="rhkFormModal" tabindex="-1" aria-labelledby="rhkFormModalLabel" aria-hidden="true"
         data-backdrop-filter-periode-tahun="<?= (int)$f_tahun ?>">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable md-modal__dialog md-modal__dialog--md">
            <div class="modal-content md-modal__content">
                <div class="modal-header md-modal__header">
                    <div class="d-flex align-items-start gap-3 flex-grow-1">
                        <div class="md-icon-tile md-icon-tile--primary" id="rhkModalIconTile">
                            <i class="bi bi-journal-plus"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h5 class="md-modal__title" id="rhkFormModalLabel">Tambah Rencana Aksi</h5>
                            <div class="md-modal__subtitle" id="rhkFormModalSubtitle">
                                Lengkapi data RHK dan Rencana Aksi periode aktif
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="POST" action="rhk.php" id="rhkModalForm" novalidate data-guard-submit>
                    <div class="modal-body md-modal__body">
                        <input type="hidden" name="aksi_simpan" value="1">
                        <input type="hidden" name="id" id="rhkModalId" value="">
                        <input type="hidden" name="bulan" value="0">

                        <?php if ($mF !== ''): ?>
                            <input type="hidden" name="_from" id="rhkModalFrom" value="<?= htmlspecialchars($mF) ?>">
                            <?php if ($mRT !== null): ?>
                                <input type="hidden" name="_redirect_thn" id="rhkModalRedirectThn" value="<?= (int)$mRT ?>">
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($redirectFiltersQS !== ''): ?>
                            <div id="rhkModalQSData" class="d-none" data-value="<?= htmlspecialchars($redirectFiltersQS) ?>"></div>
                        <?php endif; ?>

                        <!-- Section 1: Informasi RHK -->
                        <div class="md-form-section">
                            <div class="md-form-section__title"><i class="bi bi-journal-text" aria-hidden="true"></i> Informasi RHK</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="md-field">
                                        <label class="md-label md-label--req" for="rhkModalNama">Nama Rencana Hasil Kerja (RHK)</label>
                                        <div class="md-label-group">
                                            <i class="bi bi-journal-text md-label-group__icon" aria-hidden="true"></i>
                                            <input type="text" name="nama_rhk" id="rhkModalNama" class="md-input md-label-group__text" required maxlength="255"
                                                   placeholder="Contoh: Pelaksanaan Pembelajaran Tatap Muka">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="md-field">
                                        <label class="md-label md-label--req" for="rhkModalTahun">Tahun</label>
                                        <div class="md-label-group">
                                            <i class="bi bi-calendar-check md-label-group__icon" aria-hidden="true"></i>
                                            <select name="tahun" id="rhkModalTahun" class="md-select md-label-group__text" required>
                                                <?php for ($t = $tahunAwal; $t <= $tahunAkhir; $t++): ?>
                                                    <option value="<?= $t ?>"><?= $t ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Target & Output -->
                        <div class="md-form-section">
                            <div class="md-form-section__title"><i class="bi bi-bullseye" aria-hidden="true"></i> Target &amp; Output</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="md-field">
                                        <label class="md-label md-label--req" for="rhkModalTarget">Target</label>
                                        <div class="md-label-group">
                                            <i class="bi bi-bullseye md-label-group__icon" aria-hidden="true"></i>
                                            <input type="text" name="target" id="rhkModalTarget" class="md-input md-label-group__text" required maxlength="255"
                                                   placeholder="Contoh: 34 jam tatap muka / bulan">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="md-field">
                                        <label class="md-label md-label--req" for="rhkModalOutput">Output</label>
                                        <div class="md-label-group">
                                            <i class="bi bi-graph-up-arrow md-label-group__icon" aria-hidden="true"></i>
                                            <input type="text" name="output" id="rhkModalOutput" class="md-input md-label-group__text" required maxlength="255"
                                                   placeholder="Contoh: RPP, jurnal, dan nilai hasil belajar">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer md-modal__footer">
                        <button type="button" class="md-btn md-btn--ghost" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-2"></i>Batal
                        </button>
                        <button type="submit" name="simpan" value="1" class="md-btn md-btn--primary" id="rhkModalSubmitBtn">
                            <i class="bi bi-save2 me-2"></i><span id="rhkModalSubmitLabel">Simpan Data</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============== MODAL KELOLA RENCANA AKSI ============== -->
    <div class="modal fade md-modal" id="rencanaAksiModal" tabindex="-1" aria-labelledby="rencanaAksiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable md-modal__dialog md-modal__dialog--md">
            <div class="modal-content md-modal__content">
                <div class="modal-header md-modal__header">
                    <div class="d-flex align-items-start gap-3 flex-grow-1">
                        <div class="md-icon-tile md-icon-tile--primary">
                            <i class="bi bi-list-check"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h5 class="md-modal__title" id="rencanaAksiModalLabel">Kelola Rencana Aksi</h5>
                            <div class="md-modal__subtitle" id="rencanaAksiModalSubtitle"></div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="POST" action="rhk.php" id="rencanaAksiForm" novalidate data-guard-submit>
                    <div class="modal-body md-modal__body">
                        <input type="hidden" name="simpan_rencana_aksi" value="1">
                        <input type="hidden" name="rhk_id" id="raRHKId" value="">

                        <div class="md-callout md-callout--info md-sp-4" id="raInfoCallout">
                            <i class="bi bi-info-circle"></i>
                            <div>
                                Rencana Aksi hanya dapat diisi/diedit pada <strong>bulan berjalan</strong>. Riwayat bulan sebelumnya bersifat <strong>read-only</strong>.
                            </div>
                        </div>

                        <!-- Section 1: Rencana Aksi Bulan Ini -->
                        <div class="md-form-section">
                            <div class="md-form-section__title">
                                <i class="bi bi-list-check" aria-hidden="true"></i> Rencana Aksi
                                <span class="md-badge md-badge--primary md-badge--pill md-badge--sm" id="raBulanLabel"></span>
                            </div>
                            <div class="md-field">
                                <label class="md-label" for="raIndikator">Uraian Rencana Aksi</label>
                                <textarea name="indikator" id="raIndikator" class="md-textarea" rows="5"
                                          placeholder="Tuliskan rencana aksi untuk bulan ini..."></textarea>
                            </div>
                        </div>

                        <!-- Section 2: Riwayat -->
                        <div class="md-form-section">
                            <div class="md-form-section__title"><i class="bi bi-clock-history" aria-hidden="true"></i> Riwayat Rencana Aksi</div>
                            <div id="raHistoryList">
                                <div class="md-empty md-empty--sm">
                                    <div class="md-empty__desc text-muted">Belum ada riwayat rencana aksi.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer md-modal__footer">
                        <button type="button" class="md-btn md-btn--ghost" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-2"></i>Tutup
                        </button>
                        <button type="submit" class="md-btn md-btn--primary" id="raSubmitBtn">
                            <i class="bi bi-save2 me-2"></i>Simpan Rencana Aksi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============== MODAL CONTROLLER JS ============== -->
    <script>
    (function () {
        var MONTHS_LABELS = <?= json_encode(array_values($namaBulanRHK)) ?>;
        var MONTHS_VALUES = <?= json_encode(array_keys($namaBulanRHK)) ?>;

        var modalWrap = document.getElementById('rhkFormModal');
        var modalEl = modalWrap;
        var modalIdInput = document.getElementById('rhkModalId');
        var fNama = document.getElementById('rhkModalNama');
        var fTahun = document.getElementById('rhkModalTahun');
        var fTarget = document.getElementById('rhkModalTarget');
        var fOutput = document.getElementById('rhkModalOutput');
        var titleEl = document.getElementById('rhkFormModalLabel');
        var subEl = document.getElementById('rhkFormModalSubtitle');
        var submitLabel = document.getElementById('rhkModalSubmitLabel');
        var iconTile = document.getElementById('rhkModalIconTile');
        var iconTileIcon = iconTile ? iconTile.querySelector('i') : null;
        var customFrom = '<?= $mF !== '' ? htmlspecialchars($mF) : '' ?>';
        var RA_HISTORY = <?= $raHistoryJSON ?: '{}' ?>;
        var RA_BULAN_INI = <?= (int)date('n') ?>;
        var RA_TAHUN_INI = <?= (int)date('Y') ?>;
        var RA_NAMA_BULAN = <?= json_encode($namaBulanRHK) ?>;

        function setMode(mode) {
            var isEdit = (mode === 'edit');
            if (titleEl) titleEl.textContent = isEdit ? 'Edit RHK' : 'Tambah RHK';
            if (subEl) subEl.textContent = isEdit ? 'Perbarui detail RHK tahun aktif' : 'Lengkapi data RHK untuk tahun aktif';
            if (submitLabel) submitLabel.textContent = isEdit ? 'Perbarui Data' : 'Simpan Data';
            if (iconTileIcon) iconTileIcon.className = isEdit ? 'bi bi-journal-check' : 'bi bi-journal-plus';
            if (iconTile) iconTile.setAttribute('aria-label', isEdit ? 'Edit RHK' : 'Tambah RHK');
        }

        function clearInvalid() {
            [fNama, fTahun, fTarget, fOutput].forEach(function (inp) {
                if (inp) inp.classList.remove('is-invalid', 'md-input--error');
            });
        }

        window.openRHKTambah = function (btn) {
            if (!modalEl) return false;
            clearInvalid();
            var tahunDefault = btn && btn.getAttribute('data-default-tahun') !== null ? parseInt(btn.getAttribute('data-default-tahun'), 10) : (modalWrap ? parseInt(modalWrap.getAttribute('data-backdrop-filter-periode-tahun'), 10) : <?= (int)date('Y') ?>);
            if (isNaN(tahunDefault) || tahunDefault < 2000 || tahunDefault > 2100) tahunDefault = <?= (int)date('Y') ?>;
            if (modalIdInput) modalIdInput.value = '';
            if (fNama) fNama.value = '';
            if (fTahun) fTahun.value = String(tahunDefault);
            if (fTarget) fTarget.value = '';
            if (fOutput) fOutput.value = '';
            setMode('tambah');
            (new bootstrap.Modal(modalEl)).show();
            if (fNama) setTimeout(function () { fNama.focus(); }, 250);
            return false;
        };

        window.openRHKEditFromList = function (btn) {
            if (!modalEl || !btn) return false;
            clearInvalid();
            var id = btn.getAttribute('data-id') || '';
            var nama = btn.getAttribute('data-nama') || '';
            var target = btn.getAttribute('data-target') || '';
            var output = btn.getAttribute('data-output') || '';
            var thn = parseInt(btn.getAttribute('data-tahun') || '<?= (int)date('Y') ?>', 10);
            if (isNaN(thn) || thn < 2000 || thn > 2100) thn = <?= (int)date('Y') ?>;
            if (modalIdInput) modalIdInput.value = id ? String(parseInt(id, 10) || 0) : '';
            if (fNama) fNama.value = nama;
            if (fTahun) fTahun.value = String(thn);
            if (fTarget) fTarget.value = target;
            if (fOutput) fOutput.value = output;
            setMode('edit');
            (new bootstrap.Modal(modalEl)).show();
            if (fNama) setTimeout(function () { fNama.focus(); }, 250);
            return false;
        };

        window.openRencanaAksiModal = function (btn) {
            var raModal = document.getElementById('rencanaAksiModal');
            if (!raModal || !btn) return false;
            var rhkId = parseInt(btn.getAttribute('data-id') || '0', 10);
            var nama = btn.getAttribute('data-nama') || '';
            var bln = parseInt(btn.getAttribute('data-bulan') || '0', 10);
            var thn = parseInt(btn.getAttribute('data-tahun') || '0', 10);
            var aksi = btn.getAttribute('data-aksi') || '';

            var subEl = document.getElementById('rencanaAksiModalSubtitle');
            var rhkIdInput = document.getElementById('raRHKId');
            var indInput = document.getElementById('raIndikator');
            var bulanLabel = document.getElementById('raBulanLabel');
            var historyList = document.getElementById('raHistoryList');
            var submitBtn = document.getElementById('raSubmitBtn');
            var infoCallout = document.getElementById('raInfoCallout');

            if (subEl) subEl.textContent = nama;
            if (rhkIdInput) rhkIdInput.value = String(rhkId);
            if (bulanLabel) {
                bulanLabel.textContent = (RA_NAMA_BULAN[bln] || '') + ' ' + thn;
            }

            var isBulanAktif = (bln === RA_BULAN_INI && thn === RA_TAHUN_INI);
            if (indInput) {
                indInput.value = aksi || '';
                indInput.disabled = !isBulanAktif;
                if (isBulanAktif) {
                    indInput.setAttribute('placeholder', 'Tuliskan rencana aksi untuk bulan ini...');
                } else {
                    indInput.setAttribute('placeholder', 'Rencana aksi hanya dapat diedit pada bulan berjalan.');
                }
            }
            if (submitBtn) submitBtn.disabled = !isBulanAktif;
            if (infoCallout) {
                if (isBulanAktif) {
                    infoCallout.classList.remove('md-callout--warning');
                    infoCallout.classList.add('md-callout--info');
                    infoCallout.querySelector('div').innerHTML = 'Rencana Aksi hanya dapat diisi/diedit pada <strong>bulan berjalan</strong>. Riwayat bulan sebelumnya bersifat <strong>read-only</strong>.';
                } else {
                    infoCallout.classList.remove('md-callout--info');
                    infoCallout.classList.add('md-callout--warning');
                    infoCallout.querySelector('div').innerHTML = 'RHK ini untuk periode <strong>' + (RA_NAMA_BULAN[bln] || '') + ' ' + thn + '</strong>. Rencana Aksi hanya dapat diedit pada bulan berjalan (' + (RA_NAMA_BULAN[RA_BULAN_INI] || '') + ' ' + RA_TAHUN_INI + ').';
                }
            }

            var entries = RA_HISTORY[rhkId] || [];
            if (historyList) {
                if (entries.length === 0) {
                    historyList.innerHTML = '<div class="md-empty md-empty--sm"><div class="md-empty__desc text-muted">Belum ada riwayat rencana aksi.</div></div>';
                } else {
                    var html = '';
                    entries.forEach(function (e) {
                        var isCurrent = (e.bulan === RA_BULAN_INI && e.tahun === RA_TAHUN_INI);
                        var badgeClass = isCurrent ? 'md-badge--success' : 'md-badge--subtle';
                        var icon = isCurrent ? 'bi bi-pencil-square' : 'bi bi-lock';
                        html += '<div class="md-list md-list--bordered md-sp-2">' +
                            '<div class="d-flex align-items-center justify-content-between gap-2 mb-2 flex-wrap">' +
                            '<span class="md-badge ' + badgeClass + ' md-badge--pill md-badge--sm"><i class="' + icon + '"></i> ' + (RA_NAMA_BULAN[e.bulan] || '') + ' ' + e.tahun + '</span>' +
                            (isCurrent ? '<span class="md-badge md-badge--primary md-badge--pill md-badge--sm"><i class="bi bi-geo-alt"></i> Bulan Ini</span>' : '') +
                            '</div>' +
                            '<div class="text-break small">' + (e.indikator || '-') + '</div>' +
                            '</div>';
                    });
                    historyList.innerHTML = html;
                }
            }

            (new bootstrap.Modal(raModal)).show();
            if (indInput && isBulanAktif) setTimeout(function () { indInput.focus(); }, 250);
            return false;
        };

        <?php if ($autoOpenModal): ?>
            var $preMode = <?= json_encode($autoOpenMode === 'edit' ? 'edit' : 'tambah') ?>;
            if ($preMode === 'edit') {
                var $preBtn = document.createElement('button');
                $preBtn.setAttribute('data-id', <?= (int)$autoOpenId ?>);
                $preBtn.setAttribute('data-nama', <?= json_encode($form['nama_rhk']) ?>);
                $preBtn.setAttribute('data-target', <?= json_encode($form['target']) ?>);
                $preBtn.setAttribute('data-output', <?= json_encode($form['output']) ?>);
                $preBtn.setAttribute('data-tahun', <?= (int)$form['tahun'] ?>);
                window.addEventListener('load', function () {
                    window.openRHKEditFromList($preBtn);
                });
            } else {
                var $preBtnT = document.createElement('button');
                $preBtnT.setAttribute('data-default-tahun', <?= (int)$form['tahun'] ?>);
                window.addEventListener('load', function () {
                    window.openRHKTambah($preBtnT);
                });
            }
        <?php endif; ?>
    })();
    </script>

<?php $stmt->close(); require_once __DIR__ . '/../Views/layouts/footer.php'; ?>
