<?php
/**
 * Partial: panel pratinjau PDF terintegrasi (tanpa modal pop-up).
 *
 * Viewer  : PDF.js (lokal di public/assets/vendor/pdfjs) — menyediakan kontrol
 *           navigasi halaman dan zoom yang benar-benar terhubung ke dokumen.
 * Fallback: bila PDF.js tidak tersedia, panel memakai viewer PDF bawaan browser
 *           melalui <iframe> dan menyembunyikan kontrol yang tidak dapat dikendalikan.
 *
 * Parameter: $pdfPreview = [
 *   'active' => bool, 'title' => string, 'subtitle' => string,
 *   'viewer_url' => string, 'download_url' => string, 'print_url' => string
 * ]
 */
$pdfPreview = array_merge([
    'active'       => false,
    'title'        => 'Preview Dokumen',
    'subtitle'     => '',
    'viewer_url'   => '',
    'download_url' => '',
    'print_url'    => '',
], is_array($pdfPreview ?? null) ? $pdfPreview : []);

if (empty($pdfPreview['active'])) {
    return;
}

$__pdfViewerUrl   = trim((string)$pdfPreview['viewer_url']);
$__pdfDownloadUrl = trim((string)$pdfPreview['download_url']);
$__pdfPrintUrl    = trim((string)$pdfPreview['print_url']);
if ($__pdfDownloadUrl === '') {
    $__pdfDownloadUrl = $__pdfViewerUrl;
}
if ($__pdfPrintUrl === '') {
    $__pdfPrintUrl = $__pdfViewerUrl;
}
?>
<section class="pdf-preview-page"
         data-pdf-preview
         data-pdf-url="<?= htmlspecialchars($__pdfViewerUrl, ENT_QUOTES, 'UTF-8') ?>"
         data-pdf-print-url="<?= htmlspecialchars($__pdfPrintUrl, ENT_QUOTES, 'UTF-8') ?>">
    <div class="pdf-preview-toolbar" role="toolbar" aria-label="Toolbar pratinjau dokumen">
        <div class="pdf-preview-toolbar__meta">
            <span class="pdf-preview-toolbar__icon" aria-hidden="true">
                <i class="bi bi-file-earmark-pdf"></i>
            </span>
            <div class="pdf-preview-toolbar__text">
                <h1 class="pdf-preview-toolbar__title"><?= htmlspecialchars($pdfPreview['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if ($pdfPreview['subtitle'] !== ''): ?>
                    <p class="pdf-preview-toolbar__subtitle"><?= htmlspecialchars($pdfPreview['subtitle'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="pdf-preview-toolbar__actions" data-pdf-controls hidden>
            <div class="pdf-preview-cluster" role="group" aria-label="Navigasi halaman">
                <button type="button" class="pdf-preview-btn pdf-preview-btn--icon"
                        data-pdf-action="prev" title="Halaman sebelumnya" aria-label="Halaman sebelumnya" disabled>
                    <i class="bi bi-chevron-left"></i>
                </button>
                <span class="pdf-preview-pager">
                    <span class="pdf-preview-pager__current" data-pdf-page-label>1</span>
                    <span class="pdf-preview-pager__sep">/</span>
                    <span class="pdf-preview-pager__total" data-pdf-page-total>&ndash;</span>
                </span>
                <button type="button" class="pdf-preview-btn pdf-preview-btn--icon"
                        data-pdf-action="next" title="Halaman berikutnya" aria-label="Halaman berikutnya" disabled>
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>

            <div class="pdf-preview-cluster" role="group" aria-label="Zoom">
                <button type="button" class="pdf-preview-btn pdf-preview-btn--icon"
                        data-pdf-action="zoom-out" title="Perkecil" aria-label="Perkecil">
                    <i class="bi bi-dash-lg"></i>
                </button>
                <select class="pdf-preview-zoom" data-pdf-zoom aria-label="Tingkat zoom">
                    <option value="fit" selected>Sesuaikan Lebar</option>
                    <option value="50">50%</option>
                    <option value="75">75%</option>
                    <option value="100">100%</option>
                    <option value="125">125%</option>
                    <option value="150">150%</option>
                    <option value="200">200%</option>
                    <option value="300">300%</option>
                </select>
                <button type="button" class="pdf-preview-btn pdf-preview-btn--icon"
                        data-pdf-action="zoom-in" title="Perbesar" aria-label="Perbesar">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>

            <div class="pdf-preview-cluster">
                <button type="button" class="pdf-preview-btn pdf-preview-btn--icon"
                        data-pdf-action="reload" title="Muat ulang dokumen" aria-label="Muat ulang dokumen">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
                <a class="pdf-preview-btn pdf-preview-btn--icon"
                   href="<?= htmlspecialchars($__pdfDownloadUrl, ENT_QUOTES, 'UTF-8') ?>"
                   title="Unduh PDF" aria-label="Unduh PDF">
                    <i class="bi bi-download"></i>
                </a>
                <button type="button" class="pdf-preview-btn pdf-preview-btn--primary"
                        data-pdf-action="print" title="Cetak dokumen" aria-label="Cetak dokumen">
                    <i class="bi bi-printer"></i><span>Cetak</span>
                </button>
            </div>
        </div>
    </div>

    <div class="pdf-preview-canvas" data-pdf-canvas>
        <?php if ($__pdfViewerUrl !== ''): ?>
            <div class="pdf-preview-loading" data-pdf-loading>
                <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                <div class="pdf-preview-loading__text">Memuat dokumen...</div>
            </div>
            <div class="pdf-preview-pages" data-pdf-pages></div>
            <div class="pdf-preview-message" data-pdf-error hidden>
                <span class="pdf-preview-message__icon"><i class="bi bi-exclamation-triangle"></i></span>
                <div>
                    <div class="pdf-preview-message__title">Dokumen gagal dimuat</div>
                    <div class="pdf-preview-message__text">Silakan gunakan tombol Muat Ulang, atau tombol Unduh untuk membuka dokumen secara langsung.</div>
                </div>
            </div>
        <?php else: ?>
            <div class="pdf-preview-message">
                <span class="pdf-preview-message__icon"><i class="bi bi-exclamation-triangle"></i></span>
                <div>
                    <div class="pdf-preview-message__title">Preview tidak tersedia</div>
                    <div class="pdf-preview-message__text">Tautan dokumen tidak valid atau sudah tidak dapat dibuka.</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php if ($__pdfViewerUrl !== ''): ?>
<script defer src="assets/vendor/pdfjs/pdf.min.js?v=<?= time() ?>"></script>
<?php endif; ?>
