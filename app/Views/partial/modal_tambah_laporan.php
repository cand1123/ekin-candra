<?php
/* ============================================================
   Partial: Modal "Tambah Data Laporan Harian"
   Dipakai oleh: laporan_harian.php (daftar) & rekap_bulanan.php
   Variabel (opsional, di-set pemanggil):
     $listRHK  = array of ['id' => int, 'label' => string]
     $today    = tanggal default format Y-m-d

   Pola visual mengikuti modal acuan di dashboard.php (sistem .md-*).
   ============================================================ */
if (!isset($today) || !$today) { $today = (new DateTime())->format('Y-m-d'); }
if (!isset($listRHK) || !is_array($listRHK)) { $listRHK = []; }
$__errTambah = isset($_GET['error']) ? (int)$_GET['error'] : 0;
?>
<div class="modal fade md-modal" id="tambahLaporanModal" tabindex="-1" aria-labelledby="tambahLaporanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable md-modal__dialog md-modal__dialog--lg">
        <div class="modal-content">
            <form method="POST" action="simpan_laporan.php" enctype="multipart/form-data" data-guard-submit>
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
                    <?php if ($__errTambah === 1): ?>
                        <div class="md-alert md-alert--danger md-alert--sm md-sp-6" role="alert">
                            <span class="md-alert__icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
                            <div>
                                <div class="md-alert__title">Error</div>
                                <div class="md-alert__text">Mohon lengkapi semua field wajib (Tanggal, Rencana Aksi, dan Uraian Kegiatan).</div>
                            </div>
                        </div>
                    <?php elseif ($__errTambah === 2): ?>
                        <div class="md-alert md-alert--danger md-alert--sm md-sp-6" role="alert">
                            <span class="md-alert__icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
                            <div>
                                <div class="md-alert__title">Error</div>
                                <div class="md-alert__text">Terjadi kesalahan saat menyimpan laporan. Silakan coba lagi.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Section 1: Informasi Kegiatan -->
                    <div class="md-form-section">
                        <div class="md-form-section__title"><i class="bi bi-info-circle" aria-hidden="true"></i> Informasi Kegiatan</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="md-field">
                                    <label class="md-label md-label--req" for="mdTanggal">Tanggal</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
                                        <input type="date" name="tanggal" id="mdTanggal" class="md-input md-label-group__text" required
                                               data-default="<?= htmlspecialchars($today, ENT_QUOTES) ?>" value="<?= $today ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="md-field">
                                    <label class="md-label md-label--req" for="mdRHK">Rencana Aksi</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-list-check"></i></span>
                                        <select name="rhk_id" id="mdRHK" class="md-select md-label-group__text" required
                                                data-empty-href="rhk.php?aksi=tambah">
                                            <option value="">-- Pilih Rencana Aksi --</option>
                                            <?php foreach ($listRHK as $r):
                                                $lbl = trim((string)($r['label'] ?? ''));
                                                if ($lbl === '') $lbl = '[RHK #' . (int)$r['id'] . ']';
                                                ?>
                                                <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($lbl) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="md-hint md-hint--danger" id="mdRHKEmpty" style="<?= count($listRHK) > 0 ? 'display:none;' : '' ?>">
                                        <i class="bi bi-info-circle me-1"></i>Belum ada data Rencana Aksi. <a href="rhk.php?aksi=tambah" id="mdRHKEmptyLink" target="_blank" rel="noopener">Tambah Rencana Aksi terlebih dahulu</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Detail Aktivitas -->
                    <div class="md-form-section">
                        <div class="md-form-section__title"><i class="bi bi-journal-text" aria-hidden="true"></i> Detail Aktivitas</div>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <div class="md-field">
                                    <label class="md-label md-label--req" for="mdUraian">Kegiatan Harian</label>
                                    <textarea name="uraian_kegiatan" id="mdUraian" class="md-textarea" rows="4" required placeholder="Jelaskan secara rinci kegiatan yang dilakukan..."></textarea>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="md-field">
                                    <label class="md-label" for="mdJumlah">Jumlah Kegiatan</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-hash"></i></span>
                                        <input type="number" name="jumlah_kegiatan" id="mdJumlah" class="md-input md-label-group__text" min="1" value="1">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Waktu Pelaksanaan -->
                    <div class="md-form-section">
                        <div class="md-form-section__title"><i class="bi bi-clock-history" aria-hidden="true"></i> Waktu Pelaksanaan</div>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="md-field">
                                    <label class="md-label" for="mdJamMulai">Jam Mulai</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-clock"></i></span>
                                        <input type="time" name="jam_mulai" id="mdJamMulai" class="md-input md-label-group__text" value="07:30">
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="md-field">
                                    <label class="md-label" for="mdJamSelesai">Jam Selesai</label>
                                    <div class="md-label-group">
                                        <span class="md-label-group__icon" aria-hidden="true"><i class="bi bi-clock"></i></span>
                                        <input type="time" name="jam_selesai" id="mdJamSelesai" class="md-input md-label-group__text" value="16:00">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Dokumentasi -->
                    <div class="md-form-section">
                        <div class="md-form-section__title"><i class="bi bi-images" aria-hidden="true"></i> Dokumentasi</div>
                        <div class="md-field">
                            <label class="md-label" for="bukti_input_modal">Bukti Pendukung (Foto)</label>
                            <div class="md-upload" role="button" tabindex="0" data-upload-target="bukti_input_modal" aria-label="Pilih atau seret foto bukti">
                                <i class="bi bi-cloud-upload md-upload__icon" aria-hidden="true"></i>
                                <div class="md-upload__text">Seret file ke sini atau <strong>klik untuk memilih</strong></div>
                                <div class="md-upload__hint">Format: JPG/PNG · Maks. 5 MB per file · Maks. 5 foto</div>
                            </div>
                            <input type="file" name="bukti[]" id="bukti_input_modal" class="md-input" accept="image/jpeg,image/png,image/jpg" multiple style="display:none;">
                            <div id="bukti_preview_modal" class="md-upload-preview"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="md-btn md-btn--ghost" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle" aria-hidden="true"></i> Batal
                    </button>
                    <button type="submit" name="simpan" class="md-btn md-btn--primary">
                        <i class="bi bi-save" aria-hidden="true"></i> Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
