<?php
$pageTitle = 'Edit Laporan - Sistem Laporan ASN';
require_once __DIR__ . '/../Views/layouts/header.php';

$uid = current_user_id();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: laporan_harian.php'); exit; }

$stmt = $mysqli->prepare('SELECT * FROM laporan_harian WHERE id=? AND user_id=? LIMIT 1');
$stmt->bind_param('ii', $id, $uid); $stmt->execute();
$lap = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$lap) { header('Location: laporan_harian.php'); exit; }

$dtLap = DateTime::createFromFormat('Y-m-d', $lap['tanggal']) ?: new DateTime();
$bulanLap = (int)$dtLap->format('n');
$tahunLap = (int)$dtLap->format('Y');
$rhkAsliId = (int)$lap['rhk_id'];
$stmtR = $mysqli->prepare('SELECT DISTINCT r.id,
                                   CASE
                                       WHEN ra.id IS NOT NULL AND NULLIF(TRIM(ra.indikator), \'\') IS NOT NULL THEN TRIM(ra.indikator)
                                       ELSE COALESCE(NULLIF(r.indikator, \'\'), r.nama_rhk)
                                   END AS label
                            FROM rencana_hasil_kerja r
                            LEFT JOIN rencana_aksi ra ON ra.rhk_id = r.id AND ra.bulan = ? AND ra.tahun = ?
                            WHERE r.user_id=? AND (
                                r.id = ?
                                OR (
                                    (r.bulan = ? OR r.bulan = 0) AND r.tahun = ?
                                    AND ra.id IS NOT NULL
                                    AND NULLIF(TRIM(ra.indikator), \'\') IS NOT NULL
                                )
                            )
                            ORDER BY label ASC');
$stmtR->bind_param('iiiiii', $bulanLap, $tahunLap, $uid, $rhkAsliId, $bulanLap, $tahunLap);
$stmtR->execute();
$listRHK = $stmtR->get_result();

$stmtB = $mysqli->prepare('SELECT * FROM bukti_kegiatan WHERE laporan_id=?');
$stmtB->bind_param('i', $id); $stmtB->execute();
$listBukti = $stmtB->get_result();
?>

<div class="md-page-header">
    <div>
        <h2 class="md-page-header__title"><i class="bi bi-pencil-square"></i>Edit Laporan Harian</h2>
        <p class="md-page-header__subtitle">Perbarui data laporan kegiatan</p>
    </div>
    <div class="md-filter-bar__group">
        <a href="laporan_harian.php" class="md-btn md-btn--ghost"><i class="bi bi-arrow-left"></i>Kembali</a>
    </div>
</div>

<?php if (isset($_GET['hapus_bukti']) && $_GET['hapus_bukti'] == 'sukses'): ?>
    <div class="md-alert md-alert--success md-sp-6" role="alert">
        <span class="md-alert__icon"><i class="bi bi-check-circle"></i></span>
        <div>
            <div class="md-alert__title">Berhasil</div>
            <div class="md-alert__text">Data bukti berhasil dihapus.</div>
        </div>
    </div>
<?php elseif (isset($_GET['error']) && $_GET['error'] == 1): ?>
    <div class="md-alert md-alert--danger md-sp-6" role="alert">
        <span class="md-alert__icon"><i class="bi bi-exclamation-triangle"></i></span>
        <div>
            <div class="md-alert__title">Error</div>
            <div class="md-alert__text">Mohon lengkapi semua field wajib (Tanggal, Rencana Aksi, dan Uraian Kegiatan).</div>
        </div>
    </div>
<?php elseif (isset($_GET['error']) && $_GET['error'] == 2): ?>
    <div class="md-alert md-alert--danger md-sp-6" role="alert">
        <span class="md-alert__icon"><i class="bi bi-exclamation-triangle"></i></span>
        <div>
            <div class="md-alert__title">Error</div>
            <div class="md-alert__text">
                Terjadi kesalahan saat menyimpan laporan. Silakan coba lagi.
                <?php if (!empty($_GET['msg'])): ?>
                    <div class="md-mt-2 small text-break">Detail: <?= htmlspecialchars(trim($_GET['msg'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="md-card md-sp-6">
    <div class="md-card__accent"></div>
    <div class="md-card__header">
        <div>
            <h5 class="md-card__title"><i class="bi bi-pencil-square me-2"></i>Form Edit Laporan</h5>
            <p class="md-card__subtitle">Laporan ID #<?= $id ?> · Perbarui informasi di bawah ini</p>
        </div>
    </div>
    <div class="md-card__body">
        <form method="POST" action="simpan_laporan.php" enctype="multipart/form-data" data-guard-submit>
            <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="laporan_id" value="<?= $id ?>">

            <!-- Section 1: Informasi Kegiatan -->
            <div class="md-form-section">
                <div class="md-form-section__title"><i class="bi bi-info-circle" aria-hidden="true"></i> Informasi Kegiatan</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="md-field">
                            <label class="md-label md-label--req" for="editTanggal">Tanggal</label>
                            <div class="md-label-group">
                                <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
                                <input type="date" name="tanggal" id="editTanggal" class="md-input md-label-group__text" required value="<?= htmlspecialchars($lap['tanggal']) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="md-field">
                            <label class="md-label md-label--req" for="editRHK">Rencana Aksi</label>
                            <div class="md-label-group">
                                <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-list-check"></i></span>
                                <select name="rhk_id" id="editRHK" class="md-select md-label-group__text" required>
                                    <option value="">-- Pilih Rencana Aksi --</option>
                                    <?php while ($r = $listRHK->fetch_assoc()): ?>
                                        <option value="<?= $r['id'] ?>" <?= $lap['rhk_id'] == $r['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($r['label']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Detail Aktivitas -->
            <div class="md-form-section">
                <div class="md-form-section__title"><i class="bi bi-journal-text" aria-hidden="true"></i> Detail Aktivitas</div>
                <div class="md-field">
                    <label class="md-label md-label--req" for="editUraian">Kegiatan Harian</label>
                    <textarea name="uraian_kegiatan" id="editUraian" class="md-textarea" rows="4" required placeholder="Jelaskan secara rinci kegiatan yang dilakukan..."><?= htmlspecialchars($lap['uraian_kegiatan']) ?></textarea>
                </div>
                <div class="md-field">
                    <label class="md-label" for="editJumlah">Jumlah Kegiatan</label>
                    <div class="md-label-group">
                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-hash"></i></span>
                        <input type="number" name="jumlah_kegiatan" id="editJumlah" class="md-input md-label-group__text" min="1" value="<?= $lap['jumlah_kegiatan'] ?>">
                    </div>
                </div>
            </div>

            <!-- Section 3: Waktu Pelaksanaan -->
            <div class="md-form-section">
                <div class="md-form-section__title"><i class="bi bi-clock-history" aria-hidden="true"></i> Waktu Pelaksanaan</div>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="md-field">
                            <label class="md-label" for="editJamMulai">Jam Mulai</label>
                            <div class="md-label-group">
                                <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-clock"></i></span>
                                <input type="time" name="jam_mulai" id="editJamMulai" class="md-input md-label-group__text" value="<?= substr($lap['jam_mulai'], 0, 5) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="md-field">
                            <label class="md-label" for="editJamSelesai">Jam Selesai</label>
                            <div class="md-label-group">
                                <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-clock"></i></span>
                                <input type="time" name="jam_selesai" id="editJamSelesai" class="md-input md-label-group__text" value="<?= substr($lap['jam_selesai'], 0, 5) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Dokumentasi -->
            <div class="md-form-section">
                <div class="md-form-section__title"><i class="bi bi-images" aria-hidden="true"></i> Dokumentasi</div>

                <div class="md-field">
                    <label class="md-label">Bukti Pendukung Saat Ini</label>
                    <div class="row g-2 mb-2">
                        <?php if ($listBukti->num_rows === 0): ?>
                            <div class="col-12">
                                <div class="md-hint">
                                    <i class="bi bi-info-circle me-1"></i>Belum ada data bukti foto.
                                </div>
                            </div>
                        <?php else: while ($b = $listBukti->fetch_assoc()): ?>
                            <div class="col-6 col-md-3 position-relative">
                                <img src="<?= htmlspecialchars($b['path_file']) ?>" alt="<?= htmlspecialchars($b['nama_file']) ?>" class="w-100" style="height:120px;object-fit:cover; border:1px solid var(--md-border);">
                                <a href="hapus_bukti.php?id=<?= $b['id'] ?>&laporan_id=<?= $id ?>"
                                   class="remove-img text-decoration-none"
                                   aria-label="Hapus bukti <?= htmlspecialchars($b['nama_file']) ?>"
                                   onclick="return confirm('Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.')">&times;</a>
                                <div class="md-hint md-mt-1" style="text-align:center;"><?= htmlspecialchars($b['nama_file']) ?></div>
                            </div>
                        <?php endwhile; endif; ?>
                    </div>
                </div>

                <div class="md-field">
                    <label class="md-label" for="bukti_input">Tambah Foto Bukti Baru</label>
                    <div class="md-upload" id="uploadAreaEdit" role="button" tabindex="0" data-upload-target="bukti_input" aria-label="Pilih atau seret foto bukti baru">
                        <i class="bi bi-cloud-upload md-upload__icon" aria-hidden="true"></i>
                        <div class="md-upload__text">Seret file ke sini atau <strong>klik untuk memilih</strong></div>
                        <div class="md-upload__hint">Format: JPG/PNG · Maks. 5 MB per file · Maks. 5 foto</div>
                    </div>
                    <input type="file" name="bukti[]" id="bukti_input" class="md-input" accept="image/jpeg,image/png,image/jpg" multiple style="display:none;">
                    <div id="bukti_preview" class="md-upload-preview"></div>
                </div>
            </div>

            <div class="md-form-section d-flex flex-wrap gap-2">
                <button type="submit" name="simpan" class="md-btn md-btn--primary"><i class="bi bi-save" aria-hidden="true"></i> Perbarui Data</button>
                <a href="laporan_harian.php" class="md-btn md-btn--ghost"><i class="bi bi-x-circle" aria-hidden="true"></i> Batal</a>
            </div>
        </form>
    </div>
</div>

<?php $stmtR->close(); $stmtB->close(); require_once __DIR__ . '/../Views/layouts/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof setupImagePreview === 'function') {
        setupImagePreview('bukti_input', 'bukti_preview', 5);
    }
});
</script>
