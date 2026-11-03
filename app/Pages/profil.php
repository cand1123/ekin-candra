<?php
$pageTitle = 'Profil - Sistem Laporan ASN';
require_once __DIR__ . '/../Views/layouts/header.php';

$uid = current_user_id();
$msg = '';
$msgType = '';

/* Helper: cek keberadaan tabel (skema dapat berbeda antar instalasi) */
if (!function_exists('profil_tabel_ada')) {
    function profil_tabel_ada($mysqli, $namaTabel) {
        $res = @$mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($namaTabel) . "'");
        $ada = $res && $res->num_rows > 0;
        if ($res) { $res->free(); }
        return $ada;
    }
}
if (!function_exists('profil_hitung')) {
    function profil_hitung($mysqli, $sql, $uid) {
        $stmt = @$mysqli->prepare($sql);
        if (!$stmt) { return 0; }
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $total = 0;
        $res = $stmt->get_result();
        if ($res) { $row = $res->fetch_row(); $total = $row ? (int)$row[0] : 0; $res->free(); }
        $stmt->close();
        return $total;
    }
}

$adaTabelRencanaAksi = profil_tabel_ada($mysqli, 'rencana_aksi');
$adaTabelCapaian     = profil_tabel_ada($mysqli, 'rhk_bulanan_capaian');

$stmt = $mysqli->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
$stmt->bind_param('i', $uid); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['simpan_profil'])) {
        $nama = trim($_POST['nama'] ?? '');
        $nip = trim($_POST['nip'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $unit_kerja = trim($_POST['unit_kerja'] ?? '');
        $instansi = trim($_POST['instansi'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if (empty($nama) || empty($username)) {
            $msg = 'Nama dan Username tidak boleh kosong.'; $msgType = 'danger';
        } else {
            $fotoPath = $user['foto'];
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['foto']['tmp_name'];
                $type = mime_content_type($tmp);
                $size = $_FILES['foto']['size'];
                $allowed = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png'];
                if (isset($allowed[$type]) && $size <= 2 * 1024 * 1024) {
                    $ext = $allowed[$type];
                    $uploadsDir = dirname(__DIR__, 2) . '/public/assets/images/';
                    if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);
                    $namaBaru = 'profil_' . $uid . '_' . time() . '.' . $ext;
                    $dest = $uploadsDir . $namaBaru;
                    if (move_uploaded_file($tmp, $dest)) {
                        if ($fotoPath && file_exists(dirname(__DIR__, 2) . '/public/' . $fotoPath) && basename($fotoPath) !== $namaBaru) {
                            @unlink(dirname(__DIR__, 2) . '/public/' . $fotoPath);
                        }
                        $fotoPath = 'assets/images/' . $namaBaru;
                    }
                }
            }

            $stmtUpd = $mysqli->prepare('UPDATE users SET nama=?, nip=?, jabatan=?, unit_kerja=?, instansi=?, username=?, foto=? WHERE id=?');
            $stmtUpd->bind_param('sssssssi', $nama, $nip, $jabatan, $unit_kerja, $instansi, $username, $fotoPath, $uid);
            $ok = $stmtUpd->execute();
            $stmtUpd->close();
            $msg = $ok ? 'Profil berhasil diperbarui.' : 'Gagal memperbarui profil. Username mungkin sudah digunakan.';
            $msgType = $ok ? 'success' : 'danger';

            $stmt = $mysqli->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
            $stmt->bind_param('i', $uid); $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    } elseif (isset($_POST['ubah_password'])) {
        $old = $_POST['password_lama'] ?? '';
        $baru = $_POST['password_baru'] ?? '';
        $konf = $_POST['password_konfirmasi'] ?? '';
        if (!password_verify($old, $user['password'])) {
            $msg = 'Password lama salah.'; $msgType = 'danger';
        } elseif (strlen($baru) < 6) {
            $msg = 'Password baru minimal 6 karakter.'; $msgType = 'danger';
        } elseif ($baru !== $konf) {
            $msg = 'Konfirmasi password tidak sesuai.'; $msgType = 'danger';
        } else {
            $hash = password_hash($baru, PASSWORD_DEFAULT);
            $stmtP = $mysqli->prepare('UPDATE users SET password=? WHERE id=?');
            $stmtP->bind_param('si', $hash, $uid);
            $ok = $stmtP->execute(); $stmtP->close();
            $msg = $ok ? 'Password berhasil diubah.' : 'Gagal mengubah password.';
            $msgType = $ok ? 'success' : 'danger';
        }
    } elseif (isset($_POST['hapus_semua'])) {
        $sukses = true;

        /* 1) Hapus berkas bukti kegiatan dari disk */
        $stmtB = $mysqli->prepare('SELECT path_file FROM bukti_kegiatan WHERE user_id = ?');
        if ($stmtB) {
            $stmtB->bind_param('i', $uid); $stmtB->execute();
            $resB = $stmtB->get_result();
            while ($rB = $resB->fetch_assoc()) {
                $pathBerkas = dirname(__DIR__, 2) . '/public/' . $rB['path_file'];
                if (!empty($rB['path_file']) && file_exists($pathBerkas)) { @unlink($pathBerkas); }
            }
            $stmtB->close();
        }

        /* 2) Hapus data anak terlebih dahulu agar tidak terganjal foreign key */
        $stmt = $mysqli->prepare('DELETE FROM bukti_kegiatan WHERE user_id = ?');
        if ($stmt) { $stmt->bind_param('i', $uid); if (!$stmt->execute()) { $sukses = false; } $stmt->close(); }

        $stmt = $mysqli->prepare('DELETE FROM laporan_harian WHERE user_id = ?');
        if ($stmt) { $stmt->bind_param('i', $uid); if (!$stmt->execute()) { $sukses = false; } $stmt->close(); }

        if ($adaTabelRencanaAksi) {
            $stmt = $mysqli->prepare('DELETE ra FROM rencana_aksi ra INNER JOIN rencana_hasil_kerja r ON ra.rhk_id = r.id WHERE r.user_id = ?');
            if ($stmt) { $stmt->bind_param('i', $uid); if (!$stmt->execute()) { $sukses = false; } $stmt->close(); }
        }

        if ($adaTabelCapaian) {
            $stmt = $mysqli->prepare('DELETE FROM rhk_bulanan_capaian WHERE user_id = ?');
            if ($stmt) { $stmt->bind_param('i', $uid); if (!$stmt->execute()) { $sukses = false; } $stmt->close(); }
        }

        $stmt = $mysqli->prepare('DELETE FROM rencana_hasil_kerja WHERE user_id = ?');
        if ($stmt) { $stmt->bind_param('i', $uid); if (!$stmt->execute()) { $sukses = false; } $stmt->close(); }

        $msg = $sukses
            ? 'Semua data berhasil dihapus. Sistem kembali ke kondisi awal.'
            : 'Sebagian data gagal dihapus. Silakan coba lagi.';
        $msgType = $sukses ? 'success' : 'danger';
    }
}

/* Ringkasan jumlah data untuk modal konfirmasi (dihitung setelah proses POST) */
$totalLaporan = profil_hitung($mysqli, 'SELECT COUNT(*) FROM laporan_harian WHERE user_id = ?', $uid);
$totalBukti   = profil_hitung($mysqli, 'SELECT COUNT(*) FROM bukti_kegiatan WHERE user_id = ?', $uid);
$totalRHK     = profil_hitung($mysqli, 'SELECT COUNT(*) FROM rencana_hasil_kerja WHERE user_id = ?', $uid);
$totalAksi    = $adaTabelRencanaAksi
    ? profil_hitung($mysqli, 'SELECT COUNT(*) FROM rencana_aksi ra INNER JOIN rencana_hasil_kerja r ON ra.rhk_id = r.id WHERE r.user_id = ?', $uid)
    : 0;
$totalCapaian = $adaTabelCapaian
    ? profil_hitung($mysqli, 'SELECT COUNT(*) FROM rhk_bulanan_capaian WHERE user_id = ?', $uid)
    : 0;
$totalSemuaData = $totalLaporan + $totalBukti + $totalRHK + $totalAksi + $totalCapaian;
?>

<div class="md-page-header">
    <div>
        <h2 class="md-page-header__title"><i class="bi bi-person-badge"></i>Profil Pengguna</h2>
        <p class="md-page-header__subtitle">Kelola data diri dan keamanan akun</p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="md-alert md-alert--<?= $msgType === 'success' ? 'success' : 'danger' ?> md-sp-6" role="alert">
        <span class="md-alert__icon" aria-hidden="true">
            <i class="bi bi-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
        </span>
        <div>
            <div class="md-alert__title"><?= $msgType === 'success' ? 'Berhasil' : 'Periksa kembali' ?></div>
            <div class="md-alert__text"><?= htmlspecialchars($msg) ?></div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="md-card h-100">
            <div class="md-card__accent"></div>
            <div class="md-card__header">
                <div>
                    <h5 class="md-card__title"><i class="bi bi-person-vcard me-2"></i>Data Diri</h5>
                    <p class="md-card__subtitle">Informasi identitas dan foto profil</p>
                </div>
            </div>
            <div class="md-card__body">
                <form method="POST" action="" enctype="multipart/form-data" data-guard-submit>
                    <div class="md-form-section">
                        <div class="md-form-section__title"><i class="bi bi-image" aria-hidden="true"></i> Foto Profil</div>
                        <div class="d-flex align-items-center gap-3 md-mb-3">
                            <?php if (!empty($user['foto']) && file_exists(dirname(__DIR__, 2) . '/public/' . $user['foto'])): ?>
                                <img src="<?= htmlspecialchars($user['foto']) ?>" alt="Foto Profil" class="rounded-circle"
                                     style="width: 72px; height: 72px; object-fit: cover; border:1px solid var(--md-border);">
                            <?php else: ?>
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                     style="width: 72px; height: 72px; font-size: var(--md-fs-3xl); background:var(--md-secondary); color:var(--md-primary); border:1px solid var(--md-border);">
                                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                                </div>
                            <?php endif; ?>
                            <div class="md-hint">Foto saat ini. Unggah foto baru untuk menggantinya.</div>
                        </div>
                        <div class="md-upload" id="uploadAreaProfil" role="button" tabindex="0" data-upload-target="foto_profil" aria-label="Pilih atau seret foto profil">
                            <i class="bi bi-cloud-upload md-upload__icon" aria-hidden="true"></i>
                            <div class="md-upload__text">Seret file ke sini atau <strong>klik untuk memilih</strong></div>
                            <div class="md-upload__hint">Format: JPG/PNG · Maks. 2 MB</div>
                        </div>
                        <input type="file" name="foto" id="foto_profil" class="md-input" accept="image/jpeg,image/png,image/jpg" style="display:none;">
                        <div id="foto_preview" class="md-upload-preview"></div>
                    </div>

                    <div class="md-form-section">
                        <div class="md-form-section__title"><i class="bi bi-person-lines" aria-hidden="true"></i> Identitas</div>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="md-field">
                                    <label class="md-label md-label--req" for="profilNama">Nama Lengkap</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-person"></i></span>
                                        <input type="text" name="nama" id="profilNama" class="md-input md-label-group__text" required value="<?= htmlspecialchars($user['nama']) ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="md-field">
                                    <label class="md-label" for="profilNip">NIP</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-credit-card-2-front"></i></span>
                                        <input type="text" name="nip" id="profilNip" class="md-input md-label-group__text" value="<?= htmlspecialchars($user['nip']) ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="md-field">
                                    <label class="md-label" for="profilJabatan">Jabatan</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-briefcase"></i></span>
                                        <input type="text" name="jabatan" id="profilJabatan" class="md-input md-label-group__text" value="<?= htmlspecialchars($user['jabatan']) ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="md-field">
                                    <label class="md-label" for="profilUnit">Unit Kerja</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-building"></i></span>
                                        <input type="text" name="unit_kerja" id="profilUnit" class="md-input md-label-group__text" value="<?= htmlspecialchars($user['unit_kerja']) ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="md-field">
                                    <label class="md-label" for="profilInstansi">Instansi</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-bank"></i></span>
                                        <input type="text" name="instansi" id="profilInstansi" class="md-input md-label-group__text" value="<?= htmlspecialchars($user['instansi']) ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="md-field">
                                    <label class="md-label md-label--req" for="profilUsername">Username</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-at"></i></span>
                                        <input type="text" name="username" id="profilUsername" class="md-input md-label-group__text" required value="<?= htmlspecialchars($user['username']) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="md-form-section d-flex flex-wrap gap-2">
                        <button type="submit" name="simpan_profil" value="1" class="md-btn md-btn--primary">
                            <i class="bi bi-save" aria-hidden="true"></i> Perbarui Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="md-card md-sp-4">
            <div class="md-card__accent"></div>
            <div class="md-card__header">
                <div>
                    <h5 class="md-card__title"><i class="bi bi-shield-lock me-2"></i>Ubah Password</h5>
                    <p class="md-card__subtitle">Gunakan kombinasi yang kuat dan mudah diingat</p>
                </div>
            </div>
            <div class="md-card__body">
                <form method="POST" action="" data-guard-submit>
                    <div class="md-form-section">
                        <div class="md-form-section__title"><i class="bi bi-key" aria-hidden="true"></i> Keamanan Akun</div>
                        <div class="md-field">
                            <label class="md-label md-label--req" for="profilPassLama">Password Lama</label>
                            <div class="md-label-group">
                                <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password_lama" id="profilPassLama" class="md-input md-label-group__text" required>
                            </div>
                        </div>
                        <div class="md-field">
                            <label class="md-label md-label--req" for="profilPassBaru">Password Baru</label>
                            <div class="md-label-group">
                                <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-key"></i></span>
                                <input type="password" name="password_baru" id="profilPassBaru" class="md-input md-label-group__text" required minlength="6">
                            </div>
                            <div class="md-hint"><i class="bi bi-info-circle me-1"></i>Minimal 6 karakter.</div>
                        </div>
                        <div class="md-field">
                            <label class="md-label md-label--req" for="profilPassKonfirmasi">Konfirmasi Password Baru</label>
                            <div class="md-label-group">
                                <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                                <input type="password" name="password_konfirmasi" id="profilPassKonfirmasi" class="md-input md-label-group__text" required>
                            </div>
                        </div>
                    </div>
                    <div class="md-form-section d-flex flex-wrap gap-2">
                        <button type="submit" name="ubah_password" value="1" class="md-btn md-btn--primary">
                            <i class="bi bi-key" aria-hidden="true"></i> Perbarui Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="md-card">
            <div class="md-card__accent"></div>
            <div class="md-card__header">
                <div>
                    <h5 class="md-card__title"><i class="bi bi-info-circle me-2"></i>Informasi Akun</h5>
                    <p class="md-card__subtitle">Ringkasan status akun Anda</p>
                </div>
            </div>
            <div class="md-card__body">
                <table class="table table-sm mb-0">
                    <tr><td width="150" class="text-muted">Tanggal Dibuat</td>
                        <td><?= tanggal_id($user['created_at']) . ' ' . (new DateTime($user['created_at']))->format('Y H:i') ?></td></tr>
                    <tr><td class="text-muted">Password</td>
                        <td><span class="md-badge md-badge--primary md-badge--pill md-badge--sm">Terenkripsi (BCRYPT)</span></td></tr>
                    <tr><td class="text-muted">Akses Sistem</td>
                        <td><span class="md-badge md-badge--success md-badge--pill md-badge--sm">Aktif</span></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="md-card md-mt-4">
    <div class="md-card__accent md-card__accent--danger"></div>
    <div class="md-card__header">
        <div>
            <h5 class="md-card__title"><i class="bi bi-exclamation-octagon me-2"></i>Zona Berbahaya</h5>
            <p class="md-card__subtitle">Hapus seluruh data laporan dan rencana kerja secara permanen</p>
        </div>
    </div>
    <div class="md-card__body">
        <div class="md-alert md-alert--danger md-alert--sm md-sp-6" role="alert">
            <span class="md-alert__icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
            <div>
                <div class="md-alert__title">Tindakan ini tidak dapat dibatalkan</div>
                <div class="md-alert__text">Menu <strong>Hapus Semuanya</strong> akan menghapus semua laporan harian, dokumentasi bukti, Rencana Hasil Kerja, Rencana Aksi, dan capaian bulanan milik akun Anda. Akun dan profil tetap tersimpan.</div>
            </div>
        </div>
        <div class="md-confirm-list md-mb-3">
            <div class="md-confirm-list__row">
                <div class="md-confirm-list__label">Laporan Harian</div>
                <div class="md-confirm-list__value"><?= (int)$totalLaporan ?> data</div>
            </div>
            <div class="md-confirm-list__row">
                <div class="md-confirm-list__label">Bukti Kegiatan</div>
                <div class="md-confirm-list__value"><?= (int)$totalBukti ?> berkas</div>
            </div>
            <div class="md-confirm-list__row">
                <div class="md-confirm-list__label">Rencana Hasil Kerja</div>
                <div class="md-confirm-list__value"><?= (int)$totalRHK ?> data</div>
            </div>
            <div class="md-confirm-list__row">
                <div class="md-confirm-list__label">Rencana Aksi</div>
                <div class="md-confirm-list__value"><?= (int)$totalAksi ?> data</div>
            </div>
            <div class="md-confirm-list__row">
                <div class="md-confirm-list__label">Capaian Bulanan</div>
                <div class="md-confirm-list__value"><?= (int)$totalCapaian ?> data</div>
            </div>
        </div>
        <button type="button" class="md-btn md-btn--danger" data-bs-toggle="modal" data-bs-target="#modalHapusSemua" <?= $totalSemuaData === 0 ? 'disabled' : '' ?>>
            <i class="bi bi-trash3" aria-hidden="true"></i> Hapus Semuanya
        </button>
    </div>
</div>

<!-- ============== MODAL KONFIRMASI HAPUS SEMUA ============== -->
<div class="modal fade md-modal" id="modalHapusSemua" tabindex="-1" aria-labelledby="modalHapusSemuaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered md-modal__dialog md-modal__dialog--md">
        <div class="modal-content">
            <form method="POST" action="" data-guard-submit>
                <input type="hidden" name="hapus_semua" value="1">
                <div class="modal-header">
                    <span class="md-icon-tile md-icon-tile--danger" aria-hidden="true"><i class="bi bi-exclamation-octagon"></i></span>
                    <div>
                        <h5 class="md-modal__title" id="modalHapusSemuaLabel">Konfirmasi Hapus Semuanya</h5>
                        <p class="md-modal__subtitle">Periksa ringkasan data sebelum melanjutkan</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="md-alert md-alert--danger md-alert--sm md-sp-6" role="alert">
                        <span class="md-alert__icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
                        <div>
                            <div class="md-alert__title">Data akan hilang permanen</div>
                            <div class="md-alert__text">Seluruh data di bawah ini akan dihapus dan tidak dapat dikembalikan.</div>
                        </div>
                    </div>
                    <div class="md-confirm-list">
                        <div class="md-confirm-list__row">
                            <div class="md-confirm-list__label">Laporan Harian</div>
                            <div class="md-confirm-list__value"><?= (int)$totalLaporan ?> data</div>
                        </div>
                        <div class="md-confirm-list__row">
                            <div class="md-confirm-list__label">Bukti Kegiatan</div>
                            <div class="md-confirm-list__value"><?= (int)$totalBukti ?> berkas</div>
                        </div>
                        <div class="md-confirm-list__row">
                            <div class="md-confirm-list__label">Rencana Hasil Kerja</div>
                            <div class="md-confirm-list__value"><?= (int)$totalRHK ?> data</div>
                        </div>
                        <div class="md-confirm-list__row">
                            <div class="md-confirm-list__label">Rencana Aksi</div>
                            <div class="md-confirm-list__value"><?= (int)$totalAksi ?> data</div>
                        </div>
                        <div class="md-confirm-list__row">
                            <div class="md-confirm-list__label">Capaian Bulanan</div>
                            <div class="md-confirm-list__value"><?= (int)$totalCapaian ?> data</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="md-btn md-btn--ghost" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle" aria-hidden="true"></i> Batal
                    </button>
                    <button type="submit" class="md-btn md-btn--danger">
                        <i class="bi bi-trash3" aria-hidden="true"></i> Hapus Semuanya
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../Views/layouts/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof setupImagePreview === 'function') {
        setupImagePreview('foto_profil', 'foto_preview', 1);
    }
});
</script>
