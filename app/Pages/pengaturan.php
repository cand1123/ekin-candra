<?php
require_once __DIR__ . '/../helpers/session.php';
require_login();
require_once dirname(__DIR__, 2) . '/app/Config/database.php';

$uid = current_user_id();

$tahunSekarang = (int)date('Y');
$tahunAwal = $tahunSekarang - 3;
$tahunAkhir = $tahunSekarang + 3;

/* ---- Filter periode (RHK dikelola per tahun, tanpa opsi per bulan) ---- */
$filter = [
    'tahun' => $tahunSekarang,
];
if (isset($_GET['thn']) && $_GET['thn'] !== '') {
    $t = (int)$_GET['thn'];
    if ($t >= 2000 && $t <= 2100) $filter['tahun'] = $t;
}

$form = [
    'nama_rhk' => '',
    'target'   => '',
    'output'   => '',
    'tahun'    => $filter['tahun'],
];

/* ---- Query list RHK untuk tahun terpilih ---- */
$errorQueryRHK = '';
$listRHK = [];
$jmlRHK = 0;
$jmlRHKTotalUser = 0;

$sqlList = "SELECT r.id, r.nama_rhk, r.target, r.output, r.bulan, r.tahun
            FROM rencana_hasil_kerja r
            WHERE r.user_id = ?
              AND r.tahun = ?
            ORDER BY r.nama_rhk ASC, r.id ASC";

$stmtList = $mysqli->prepare($sqlList);
if (!$stmtList) {
    $errorQueryRHK = 'Gagal menyiapkan query list RHK: ' . trim($mysqli->error);
} else {
    $stmtList->bind_param('ii', $uid, $filter['tahun']);
    if (!$stmtList->execute()) {
        $errorQueryRHK = 'Gagal mengeksekusi query list RHK: ' . trim($stmtList->error);
    } else {
        $resList = $stmtList->get_result();
        while ($row = $resList->fetch_assoc()) {
            $listRHK[] = $row;
        }
        $stmtList->close();
    }
}
$jmlRHK = count($listRHK);

/* Total RHK user keseluruhan */
if ($errorQueryRHK === '') {
    $sqlTotal = "SELECT COUNT(*) FROM rencana_hasil_kerja WHERE user_id = ?";
    $stmtTotal = $mysqli->prepare($sqlTotal);
    if (!$stmtTotal) {
        $resTotalFallback = $mysqli->query("SELECT id FROM rencana_hasil_kerja WHERE user_id = " . (int)$uid);
        if ($resTotalFallback) {
            $jmlRHKTotalUser = $resTotalFallback->num_rows;
            $resTotalFallback->free();
        }
    } else {
        $stmtTotal->bind_param('i', $uid);
        if ($stmtTotal->execute()) {
            $stmtTotal->bind_result($jmlRHKTotalUser);
            $stmtTotal->fetch();
        }
        $stmtTotal->close();
    }
}

$pageTitle = 'Setting RHK - Sistem Laporan ASN';
require_once __DIR__ . '/../Views/layouts/header.php';
?>

    <!-- ============== PAGE HEADER ============== -->
    <div class="md-page-header">
        <div>
            <h2 class="md-page-header__title"><i class="bi bi-gear"></i>Rencana Hasil Kerja (RHK)</h2>
            <p class="md-page-header__subtitle">
                Total <strong><?= (int)$jmlRHKTotalUser ?> RHK</strong> tersimpan &middot;
                Tahun aktif: <strong><?= (int)$filter['tahun'] ?></strong>
            </p>
        </div>
    </div>

    <!-- ============== FILTER BAR ============== -->
    <div class="md-filter-bar md-sp-6">
        <form method="GET" id="formFilterRHK" action="pengaturan.php" class="md-filter-bar__group">
            <div class="md-filter-bar__item">
                <div class="md-label-group">
                    <i class="bi bi-calendar-check md-label-group__icon"></i>
                    <select name="thn" id="filterTahun" class="md-select md-label-group__text"
                            onchange="document.getElementById('formFilterRHK').submit();">
                        <?php for ($t = $tahunAwal; $t <= $tahunAkhir; $t++): ?>
                            <option value="<?= $t ?>" <?= $filter['tahun'] === $t ? 'selected' : '' ?>>
                                Tahun <?= $t ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </form>
        <button type="button" class="md-btn md-btn--primary ms-auto" data-bs-toggle="modal" data-bs-target="#modalFormRHK">
            <i class="bi bi-plus-lg me-1"></i>Tambah RHK
        </button>
    </div>

    <!-- ============== MODAL FORM TAMBAH RHK ============== -->
    <div class="modal fade md-modal" id="modalFormRHK" tabindex="-1" aria-labelledby="modalFormRHKLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable md-modal__dialog md-modal__dialog--lg">
            <div class="modal-content md-modal__content">
                <form method="POST" action="rhk.php?aksi=tambah" id="formTambahRHK" novalidate data-guard-submit>
                    <div class="modal-header md-modal__header">
                        <span class="md-icon-tile md-icon-tile--primary" aria-hidden="true">
                            <i class="bi bi-journal-plus"></i>
                        </span>
                        <div>
                            <h5 class="md-modal__title" id="modalFormRHKLabel">Tambah Rencana Hasil Kerja</h5>
                            <p class="md-modal__subtitle">Lengkapi data RHK untuk periode yang dipilih</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body md-modal__body">
                        <input type="hidden" name="aksi_simpan" value="1">
                        <input type="hidden" name="id" value="">
                        <input type="hidden" name="bulan" value="0">
                        <input type="hidden" name="_from" value="pengaturan">
                        <input type="hidden" name="_redirect_thn" value="<?= (int)$filter['tahun'] ?>">

                        <!-- Section 1: Informasi RHK -->
                        <div class="md-form-section">
                            <div class="md-form-section__title"><i class="bi bi-journal-text" aria-hidden="true"></i> Informasi RHK</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="md-field">
                                        <label class="md-label md-label--req" for="rhk_nama">Nama Rencana Hasil Kerja (RHK)</label>
                                        <div class="md-label-group">
                                            <i class="bi bi-journal-text md-label-group__icon" aria-hidden="true"></i>
                                            <input type="text" name="nama_rhk" id="rhk_nama" class="md-input md-label-group__text"
                                                   required maxlength="255"
                                                   placeholder="Contoh: Pelaksanaan Pembelajaran Tatap Muka"
                                                   value="<?= htmlspecialchars($form['nama_rhk']) ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="md-field">
                                        <label class="md-label md-label--req" for="rhk_tahun">Tahun</label>
                                        <div class="md-label-group">
                                            <i class="bi bi-calendar-check md-label-group__icon" aria-hidden="true"></i>
                                            <select name="tahun" id="rhk_tahun" class="md-select md-label-group__text" required>
                                                <?php for ($t = $tahunAwal; $t <= $tahunAkhir; $t++): ?>
                                                    <option value="<?= $t ?>" <?= $form['tahun'] === $t ? 'selected' : '' ?>>
                                                        Tahun <?= $t ?>
                                                    </option>
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
                                <div class="col-12 col-md-6">
                                    <div class="md-field">
                                        <label class="md-label md-label--req" for="rhk_target">Target Pencapaian</label>
                                        <div class="md-label-group">
                                            <i class="bi bi-bullseye md-label-group__icon" aria-hidden="true"></i>
                                            <input type="text" name="target" id="rhk_target" class="md-input md-label-group__text"
                                                   required maxlength="255"
                                                   placeholder="Contoh: 34 jam tatap muka / bulan"
                                                   value="<?= htmlspecialchars($form['target']) ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="md-field">
                                        <label class="md-label md-label--req" for="rhk_output">Output / Hasil Akhir</label>
                                        <div class="md-label-group">
                                            <i class="bi bi-graph-up-arrow md-label-group__icon" aria-hidden="true"></i>
                                            <input type="text" name="output" id="rhk_output" class="md-input md-label-group__text"
                                                   required maxlength="255"
                                                   placeholder="Contoh: 34 JPK tercatat di LKH BKD ASN setiap bulan"
                                                   value="<?= htmlspecialchars($form['output']) ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="md-alert md-alert--danger md-alert--sm md-mt-4" id="validasiRHKAlert" role="alert" style="display:none;">
                            <span class="md-alert__icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
                            <div>
                                <div class="md-alert__text">
                                    <strong>Lengkapi data:</strong> Semua kolom bertanda * wajib diisi sebelum menyimpan RHK.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer md-modal__footer">
                        <button type="button" id="resetFormRHK" class="md-btn md-btn--ghost">
                            <i class="bi bi-eraser" aria-hidden="true"></i> Reset
                        </button>
                        <button type="submit" id="btnSimpanRHK" class="md-btn md-btn--primary">
                            <i class="bi bi-save" aria-hidden="true"></i> Simpan RHK
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============== DAFTAR RHK ============== -->
    <?php if ($jmlRHK > 0 && $errorQueryRHK === ''): ?>
        <div class="md-section-head md-section-head--with-action md-sp-3">
            <div>
                <div class="md-section-head__title">Daftar RHK Tahun Ini</div>
                <div class="md-section-head__sub text-muted">
                    <?= (int)$jmlRHK ?> data &middot;
                    Tahun <?= (int)$filter['tahun'] ?>
                </div>
            </div>
            <span class="md-badge md-badge--info md-badge--pill">
                <i class="bi bi-collection"></i> <?= (int)$jmlRHK ?>
            </span>
        </div>

        <div class="md-sp-8 table-responsive">
            <table class="rhk-table rhk-table--compact">
                <thead>
                    <tr>
                        <th class="rhk-table__no">NO.</th>
                        <th class="rhk-table__rhk">NAMA RHK</th>
                        <th class="rhk-table__target">TARGET</th>
                        <th class="rhk-table__output">OUTPUT</th>
                        <th class="rhk-table__aksi">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $noTbl = 1; foreach ($listRHK as $rhk):
                        $_hrefKelola = 'rhk.php?tahun=' . (int)$rhk['tahun'];
                    ?>
                        <tr>
                            <td class="rhk-table__no"><?= $noTbl ?></td>
                            <td class="rhk-table__rhk">
                                <div class="rhk-table__rhk-text mb-2"><?= htmlspecialchars($rhk['nama_rhk']) ?></div>
                                <div class="rhk-table__badge-row mb-0">
                                    <span class="rhk-badge--individu">
                                        <i class="bi bi-person-badge"></i> Individu
                                    </span>
                                </div>
                            </td>
                            <td class="rhk-table__target">
                                <div class="rhk-aksi-target mb-0"><?= htmlspecialchars(trim((string)$rhk['target']) !== '' ? $rhk['target'] : '-') ?></div>
                            </td>
                            <td class="rhk-table__output">
                                <div class="rhk-aksi-item mb-0"><?= htmlspecialchars(trim((string)$rhk['output']) !== '' ? $rhk['output'] : '-') ?></div>
                            </td>
                            <td class="rhk-table__aksi">
                                <div class="rhk-btn-group">
                                    <a href="<?= htmlspecialchars($_hrefKelola) ?>" class="rhk-btn rhk-btn--green">
                                        <i class="bi bi-plus-lg"></i> Rencana Aksi
                                    </a>
                                    <button type="button"
                                            class="rhk-btn rhk-btn--red"
                                            onclick="konfirmasiHapusRHK(<?= (int)$rhk['id'] ?>, '<?= htmlspecialchars(addslashes($rhk['nama_rhk'])) ?>');">
                                        <i class="bi bi-trash3"></i> Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php $noTbl++; endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($errorQueryRHK === ''): ?>
        <div class="md-empty md-sp-8">
            <div class="md-empty__icon mb-4"><i class="bi bi-folder-x"></i></div>
            <div class="md-empty__title mb-2">Belum ada RHK untuk tahun ini</div>
            <div class="md-empty__desc text-muted mx-auto" style="max-width: 520px;">
                Tahun <strong><?= (int)$filter['tahun'] ?></strong>
                belum memiliki Rencana Hasil Kerja. Silakan tambahkan melalui tombol <strong>Tambah RHK</strong>.
            </div>
        </div>
    <?php else: ?>
        <div class="md-alert md-alert--danger md-sp-6" role="alert">
            <div class="md-alert__icon"><i class="bi bi-database-x"></i></div>
            <div class="md-alert__body flex-grow-1">
                <div class="md-alert__title mb-1">Kesalahan Koneksi Database</div>
                <div class="md-alert__text text-muted"><?= htmlspecialchars($errorQueryRHK) ?></div>
            </div>
        </div>
    <?php endif; ?>

<!-- ============== FORM HIDDEN HAPUS RHK ============== -->
<form method="POST" id="formHapusRHK" action="rhk.php?aksi=hapus" class="d-none">
    <input type="hidden" name="hapus_id" id="hapusRHK_id" value="">
    <input type="hidden" name="_from" value="pengaturan">
    <input type="hidden" name="_redirect_thn" value="<?= (int)$filter['tahun'] ?>">
</form>

<!-- ============== JAVASCRIPT ============== -->
<script>
(function() {
    const form = document.getElementById('formTambahRHK');
    const modalEl = document.getElementById('modalFormRHK');

    /* Validasi sebelum submit: tampilkan alert merah jika ada field kosong */
    if (form) {
        form.addEventListener('submit', function(e) {
            const alertEl = document.getElementById('validasiRHKAlert');
            if (!form.checkValidity()) {
                e.preventDefault();
                form.classList.add('was-validated');
                if (alertEl) alertEl.style.display = 'flex';
                const firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) { firstInvalid.focus(); }
                return false;
            }
            form.classList.add('was-validated');
            if (alertEl) alertEl.style.display = 'none';
        });
    }

    /* Sembunyikan alert validasi saat user mulai mengoreksi input */
    if (form) {
        form.addEventListener('input', function() {
            const alertEl = document.getElementById('validasiRHKAlert');
            if (alertEl && alertEl.style.display !== 'none') {
                alertEl.style.display = 'none';
            }
        });
    }

    /* Reset form di modal */
    const btnReset = document.getElementById('resetFormRHK');
    if (btnReset && form) {
        btnReset.addEventListener('click', function() {
            const adaIsi = ['rhk_nama','rhk_target','rhk_output']
                .map(id => document.getElementById(id))
                .some(el => el && (el.value || '').trim().length > 0);
            if (adaIsi && !confirm('Yakin ingin mengosongkan semua kolom form?')) {
                return false;
            }
            form.reset();
            form.classList.remove('was-validated');
            const alertEl = document.getElementById('validasiRHKAlert');
            if (alertEl) alertEl.style.display = 'none';
            const inp = document.getElementById('rhk_nama');
            if (inp) inp.focus();
        });
    }

    /* Saat modal ditutup, reset form dan alert */
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function() {
            if (form) {
                form.reset();
                form.classList.remove('was-validated');
            }
            const alertEl = document.getElementById('validasiRHKAlert');
            if (alertEl) alertEl.style.display = 'none';
        });
    }

    window.konfirmasiHapusRHK = function(idRHK, namaRHK) {
        if (!idRHK) return false;
        const tanya = 'HAPUS RHK:\n\n"' + String(namaRHK || '(tanpa nama)') + '"\n\nData RHK yang sudah dihapus TIDAK BISA dikembalikan. Lanjutkan?';
        if (!confirm(tanya)) return false;
        const f = document.getElementById('formHapusRHK');
        const hid = document.getElementById('hapusRHK_id');
        if (!f || !hid) return false;
        hid.value = String(idRHK);
        f.submit();
        return true;
    };
})();
</script>

<?php require_once __DIR__ . '/../Views/layouts/footer.php'; ?>
