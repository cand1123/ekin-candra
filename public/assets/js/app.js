(function () {
    const offcanvasBtn = document.querySelector('[data-bs-toggle="collapse"][data-bs-target="#mainNav"]');
    const sidebar = document.querySelector('.sidebar');
    document.querySelectorAll('[data-bs-toggle="sidebar"]').forEach(btn => {
        btn.addEventListener('click', () => sidebar.classList.toggle('show'));
    });

    document.addEventListener('click', e => {
        if (window.innerWidth < 992 && sidebar && sidebar.classList.contains('show') &&
            !sidebar.contains(e.target) && !e.target.closest('[data-bs-toggle="sidebar"]') &&
            !e.target.closest('.navbar-toggler')) {
            sidebar.classList.remove('show');
        }
    });

    if (window.bootstrap && bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(t => new bootstrap.Tooltip(t));
    }
})();

function confirmDelete(url, msg) {
    const defaultMsg = 'Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.';
    const message = (typeof msg === 'string' && msg.length > 0) ? msg : defaultMsg;
    if (confirm(message)) {
        window.location.href = url;
    }
}

function preparePrint(btnEl) {
    if (!btnEl) return true;
    try {
        btnEl.style.pointerEvents = 'none';
        btnEl.style.opacity = '0.75';
        const originalCursor = btnEl.style.cursor || '';
        btnEl.style.cursor = 'progress';
        const oldTitle = btnEl.getAttribute('title') || '';
        btnEl.setAttribute('title', 'Menyiapkan preview cetak...');
        const targetUrl = btnEl.getAttribute('href') || '';
        setTimeout(function () {
            btnEl.style.pointerEvents = '';
            btnEl.style.opacity = '';
            btnEl.style.cursor = originalCursor;
            if (oldTitle) { btnEl.setAttribute('title', oldTitle); } else { btnEl.removeAttribute('title'); }
        }, 1800);
        if (targetUrl) {
            setTimeout(function () {
                window.location.href = targetUrl;
            }, 90);
            return false;
        }
    } catch (e) { /* ignore */ }
    return true;
}

function setupImagePreview(inputId, previewId, max) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    if (!input || !preview) return null;
    const maxFiles = (typeof max === 'number' && max > 0) ? max : 5;
    let selFiles = [];

    input.addEventListener('change', function () {
        const newFiles = Array.from(this.files || []);
        this.value = '';
        if (selFiles.length + newFiles.length > maxFiles) {
            alert('Maksimal ' + maxFiles + ' foto. Anda sudah memilih ' + selFiles.length + ' foto.');
            return;
        }
        const skipped = [];
        newFiles.forEach(f => {
            if (!f.type.match(/^image\/(jpe?g|png)$/i)) {
                skipped.push(f.name + ' (format bukan JPG/PNG)');
                return;
            }
            if (f.size > 5 * 1024 * 1024) {
                skipped.push(f.name + ' (ukuran > 5MB)');
                return;
            }
            selFiles.push(f);
        });
        if (skipped.length > 0) {
            alert('File berikut tidak ditambahkan:\n- ' + skipped.join('\n- '));
        }
        renderPreviews();
        updateFileList();
    });

    function renderPreviews() {
        preview.innerHTML = '';
        selFiles.forEach((f, idx) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const box = document.createElement('div');
                box.className = 'img-preview-box';
                box.innerHTML = `<img src="${e.target.result}" alt="preview">
                    <button type="button" class="remove-img" data-idx="${idx}" aria-label="Hapus foto">&times;</button>`;
                box.querySelector('.remove-img').addEventListener('click', () => {
                    selFiles.splice(idx, 1);
                    renderPreviews();
                    updateFileList();
                });
                preview.appendChild(box);
            };
            reader.readAsDataURL(f);
        });
    }

    function updateFileList() {
        try {
            const dt = new DataTransfer();
            selFiles.forEach(f => dt.items.add(f));
            input.files = dt.files;
        } catch (err) {
            /* DataTransfer tidak didukung browser lama; abaikan */
        }
    }

    window['__clearPreview_' + inputId] = function () {
        selFiles = [];
        renderPreviews();
        updateFileList();
    };

    return {
        getFiles: () => selFiles,
        clear: () => { selFiles = []; renderPreviews(); updateFileList(); }
    };
}

function openPreviewLaporan(url, subtitle) {
    if (!url) return false;
    try {
        var nextUrl = new URL(window.location.href);
        nextUrl.searchParams.set('preview', url);
        if (subtitle) {
            nextUrl.searchParams.set('preview_subtitle', subtitle);
        } else {
            nextUrl.searchParams.delete('preview_subtitle');
        }
        window.location.href = nextUrl.toString();
    } catch (e) {
        window.open(url, '_blank');
    }
    return false;
}

/* ==========================================================
   PDF PREVIEW PANEL — viewer terintegrasi berbasis PDF.js
   Menggantikan modal pratinjau lama: dokumen dirender ke kanvas
   sehingga kontrol halaman & zoom benar-benar terhubung ke dokumen.
   Bila PDF.js tidak tersedia, panel memakai viewer PDF bawaan
   browser (iframe) dan menyembunyikan kontrol yang tidak bisa dipakai.
   ========================================================== */
var PDF_PREVIEW_ZOOM_STEPS = [50, 75, 100, 125, 150, 200, 300];
var PDF_PREVIEW_WORKER_SRC = 'assets/vendor/pdfjs/pdf.worker.min.js';

function initPdfPreviewPanel() {
    var panel = document.querySelector('[data-pdf-preview]');
    if (!panel) return;

    var pdfUrl = panel.getAttribute('data-pdf-url') || '';
    var printUrl = panel.getAttribute('data-pdf-print-url') || pdfUrl;
    var canvas = panel.querySelector('[data-pdf-canvas]');
    var pagesRoot = panel.querySelector('[data-pdf-pages]');
    var loadingEl = panel.querySelector('[data-pdf-loading]');
    var errorEl = panel.querySelector('[data-pdf-error]');
    var controls = panel.querySelector('[data-pdf-controls]');
    var pageLabel = panel.querySelector('[data-pdf-page-label]');
    var totalLabel = panel.querySelector('[data-pdf-page-total]');
    var zoomSelect = panel.querySelector('[data-pdf-zoom]');
    var prevBtn = panel.querySelector('[data-pdf-action="prev"]');
    var nextBtn = panel.querySelector('[data-pdf-action="next"]');

    if (!canvas || !pagesRoot || !pdfUrl) return;

    var state = {
        doc: null,
        pageCount: 0,
        current: 1,
        zoom: 'fit',
        effectiveScale: 1,
        pages: [],
        token: 0
    };
    var scrollTimer = null;
    var resizeTimer = null;

    function hideLoading() { if (loadingEl) loadingEl.hidden = true; }
    function showLoading() { if (loadingEl) loadingEl.hidden = false; }

    function showError() {
        hideLoading();
        if (errorEl) errorEl.hidden = false;
    }

    /* Viewer bawaan browser: PDF asli tetap tampil, kontrol kustom disembunyikan. */
    function useNativeViewer() {
        hideLoading();
        if (controls) controls.hidden = true;
        pagesRoot.innerHTML = '';
        var wrap = document.createElement('div');
        wrap.className = 'pdf-preview-native';
        var frame = document.createElement('iframe');
        frame.className = 'pdf-preview-frame';
        frame.src = pdfUrl;
        frame.title = 'Pratinjau dokumen PDF';
        wrap.appendChild(frame);
        pagesRoot.appendChild(wrap);
    }

    function setCurrentPage(page, doScroll) {
        page = Math.max(1, Math.min(state.pageCount || 1, page));
        state.current = page;
        if (pageLabel) pageLabel.textContent = String(page);
        if (prevBtn) prevBtn.disabled = page <= 1;
        if (nextBtn) nextBtn.disabled = page >= state.pageCount;
        if (doScroll && state.pages[page]) {
            var el = state.pages[page];
            var offset = el.getBoundingClientRect().top - canvas.getBoundingClientRect().top;
            canvas.scrollTo({ top: Math.max(0, canvas.scrollTop + offset - 16), behavior: 'smooth' });
        }
    }

    function fitScale(page) {
        var base = page.getViewport({ scale: 1 });
        var available = canvas.clientWidth - 32; /* padding kiri/kanan kanvas */
        if (available <= 0) available = base.width;
        return Math.max(0.2, Math.min(available / base.width, 3));
    }

    function resolveScale(page) {
        if (state.zoom === 'fit') return fitScale(page);
        return Math.max(0.1, Math.min(state.zoom / 100, 5));
    }

    function renderPage(num, token) {
        return state.doc.getPage(num).then(function (page) {
            if (token !== state.token) return null;
            var scale = resolveScale(page);
            if (num === 1) state.effectiveScale = scale;

            var viewport = page.getViewport({ scale: scale });
            var width = Math.floor(viewport.width);
            var height = Math.floor(viewport.height);
            var ratio = Math.min(window.devicePixelRatio || 1, 2);

            var pageEl = document.createElement('div');
            pageEl.className = 'pdf-preview-doc-page';
            pageEl.setAttribute('data-page-number', String(num));
            pageEl.style.width = width + 'px';
            pageEl.style.height = height + 'px';

            var surface = document.createElement('canvas');
            surface.width = Math.floor(width * ratio);
            surface.height = Math.floor(height * ratio);
            surface.style.width = width + 'px';
            surface.style.height = height + 'px';
            pageEl.appendChild(surface);
            pagesRoot.appendChild(pageEl);
            state.pages[num] = pageEl;

            return page.render({
                canvasContext: surface.getContext('2d'),
                viewport: viewport,
                transform: ratio !== 1 ? [ratio, 0, 0, ratio, 0, 0] : null
            }).promise;
        });
    }

    function renderAll(keepPosition) {
        var token = ++state.token;
        var anchor = keepPosition ? state.current : 1;
        pagesRoot.innerHTML = '';
        state.pages = [];

        var chain = Promise.resolve();
        for (var i = 1; i <= state.pageCount; i++) {
            chain = chain.then(makeRenderStep(i, token));
        }

        return chain.then(function () {
            if (token !== state.token) return;
            hideLoading();
            setCurrentPage(anchor, keepPosition);
        }).catch(function () {
            if (token === state.token) showError();
        });
    }

    function makeRenderStep(num, token) {
        return function () {
            if (token !== state.token) return null;
            return renderPage(num, token);
        };
    }

    function loadPdf() {
        state.token++;
        pagesRoot.innerHTML = '';
        state.pages = [];
        state.pageCount = 0;
        if (errorEl) errorEl.hidden = true;
        showLoading();

        var previous = state.doc;
        state.doc = null;
        if (previous && typeof previous.destroy === 'function') {
            try { previous.destroy(); } catch (e) { /* dokumen lama sudah selesai dipakai */ }
        }

        return window.pdfjsLib.getDocument({ url: pdfUrl, withCredentials: true }).promise.then(function (doc) {
            state.doc = doc;
            state.pageCount = doc.numPages;
            state.current = 1;
            if (totalLabel) totalLabel.textContent = String(doc.numPages);
            if (controls) controls.hidden = false;
            return renderAll(false);
        }).catch(function () {
            showError();
        });
    }

    function applyZoom(value) {
        state.zoom = value;
        if (zoomSelect) zoomSelect.value = (value === 'fit') ? 'fit' : String(value);
        showLoading();
        renderAll(true);
    }

    function stepZoom(direction) {
        var current = (state.zoom === 'fit')
            ? Math.round(state.effectiveScale * 100)
            : state.zoom;
        var target = null;
        var i;
        if (direction > 0) {
            for (i = 0; i < PDF_PREVIEW_ZOOM_STEPS.length; i++) {
                if (PDF_PREVIEW_ZOOM_STEPS[i] > current + 1) { target = PDF_PREVIEW_ZOOM_STEPS[i]; break; }
            }
            if (target === null) target = PDF_PREVIEW_ZOOM_STEPS[PDF_PREVIEW_ZOOM_STEPS.length - 1];
        } else {
            for (i = PDF_PREVIEW_ZOOM_STEPS.length - 1; i >= 0; i--) {
                if (PDF_PREVIEW_ZOOM_STEPS[i] < current - 1) { target = PDF_PREVIEW_ZOOM_STEPS[i]; break; }
            }
            if (target === null) target = PDF_PREVIEW_ZOOM_STEPS[0];
        }
        if (target !== current) applyZoom(target);
    }

    /* Cetak memakai halaman laporan HTML (tanpa auto-print) di iframe tersembunyi. */
    function printDocument() {
        var existing = document.getElementById('pdfPreviewPrintFrame');
        if (existing && existing.parentNode) existing.parentNode.removeChild(existing);

        var frame = document.createElement('iframe');
        frame.id = 'pdfPreviewPrintFrame';
        frame.className = 'pdf-preview-print-frame';
        frame.setAttribute('aria-hidden', 'true');
        frame.setAttribute('tabindex', '-1');
        frame.onload = function () {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                window.open(printUrl, '_blank', 'noopener');
            }
        };
        frame.src = printUrl;
        document.body.appendChild(frame);
    }

    function syncPageFromScroll() {
        if (!state.pageCount || !pagesRoot.children.length) return;
        var canvasTop = canvas.getBoundingClientRect().top;
        var line = Math.min(canvas.getBoundingClientRect().height * 0.35, 220);
        var found = 1;
        for (var i = 1; i <= state.pageCount; i++) {
            var el = state.pages[i];
            if (!el) continue;
            if (el.getBoundingClientRect().top - canvasTop <= line) { found = i; } else { break; }
        }
        if (found !== state.current) setCurrentPage(found, false);
    }

    panel.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('[data-pdf-action]') : null;
        if (!btn) return;
        var action = btn.getAttribute('data-pdf-action');
        if (action === 'prev') {
            ev.preventDefault();
            setCurrentPage(state.current - 1, true);
        } else if (action === 'next') {
            ev.preventDefault();
            setCurrentPage(state.current + 1, true);
        } else if (action === 'zoom-in') {
            ev.preventDefault();
            stepZoom(1);
        } else if (action === 'zoom-out') {
            ev.preventDefault();
            stepZoom(-1);
        } else if (action === 'reload') {
            ev.preventDefault();
            loadPdf();
        } else if (action === 'print') {
            ev.preventDefault();
            printDocument();
        }
    });

    if (zoomSelect) {
        zoomSelect.addEventListener('change', function () {
            applyZoom(this.value === 'fit' ? 'fit' : (parseInt(this.value, 10) || 100));
        });
    }

    canvas.addEventListener('scroll', function () {
        if (scrollTimer) window.clearTimeout(scrollTimer);
        scrollTimer = window.setTimeout(syncPageFromScroll, 80);
    }, { passive: true });

    window.addEventListener('resize', function () {
        if (state.zoom !== 'fit' || !state.doc) return;
        if (resizeTimer) window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(function () { renderAll(true); }, 200);
    });

    if (!window.pdfjsLib || typeof window.pdfjsLib.getDocument !== 'function') {
        useNativeViewer();
        return;
    }

    try {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDF_PREVIEW_WORKER_SRC;
    } catch (e) {
        /* biarkan PDF.js memakai worker default */
    }

    loadPdf();
}

/* ==========================================================
   ANTI DOUBLE-SUBMIT — opt-in lewat atribut [data-guard-submit]
   Menonaktifkan tombol simpan saat proses berjalan, lalu
   dipulihkan ketika modal ditutup / halaman dikembalikan.
   ========================================================== */
function guardSubmitButton(btn) {
    if (!btn || btn.disabled) return;
    /* Pertahankan nilai tombol bila tombol membawa name (mis. name="simpan"),
       karena field yang disabled tidak ikut terkirim saat form disubmit. */
    if (btn.name) {
        var form = btn.form || btn.closest('form');
        if (form && !form.querySelector('input[type="hidden"][data-guard-clone="' + btn.name + '"]')) {
            var clone = document.createElement('input');
            clone.type = 'hidden';
            clone.name = btn.name;
            clone.value = (typeof btn.value === 'string' && btn.value !== '') ? btn.value : '1';
            clone.setAttribute('data-guard-clone', btn.name);
            form.appendChild(clone);
        }
    }
    var ico = btn.querySelector('i');
    if (ico) {
        btn.setAttribute('data-icon-original', ico.className);
        ico.className = 'bi bi-arrow-repeat';
    }
    btn.classList.add('is-submitting');
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
}

function releaseSubmitButtons(root) {
    (root || document).querySelectorAll('.is-submitting').forEach(function (btn) {
        btn.classList.remove('is-submitting');
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        var orig = btn.getAttribute('data-icon-original');
        if (orig) {
            var ico = btn.querySelector('i');
            if (ico) ico.className = orig;
            btn.removeAttribute('data-icon-original');
        }
        var form = btn.form || btn.closest('form');
        if (form) {
            form.querySelectorAll('input[type="hidden"][data-guard-clone]').forEach(function (el) { el.remove(); });
        }
    });
}

window.ASNSubmitGuard = {
    guard: guardSubmitButton,
    release: releaseSubmitButtons
};

document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.tagName !== 'FORM' || !form.hasAttribute('data-guard-submit')) return;
    var submitter = e.submitter || form.querySelector('[type="submit"]');
    /* Jalankan setelah seluruh handler submit lain (mis. validation.js),
       supaya tombol hanya dikunci bila pengiriman benar-benar berlanjut. */
    window.setTimeout(function () {
        if (e.defaultPrevented) return;
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;
        guardSubmitButton(submitter);
    }, 0);
}, true);

window.addEventListener('pageshow', function () {
    releaseSubmitButtons(document);
});

/* ==========================================================
   DROPZONE UNGGAH FOTO — opt-in lewat [data-upload-target]
   Area .md-upload menyalurkan klik / drag-drop ke <input type=file>.
   ========================================================== */
function initUploadDropzones(root) {
    (root || document).querySelectorAll('[data-upload-target]').forEach(function (area) {
        if (area.getAttribute('data-upload-ready') === '1') return;
        var input = document.getElementById(area.getAttribute('data-upload-target'));
        if (!input) return;
        area.setAttribute('data-upload-ready', '1');

        function openPicker() { input.click(); }
        area.addEventListener('click', function (e) { e.preventDefault(); openPicker(); });
        area.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPicker(); }
        });
        ['dragenter', 'dragover'].forEach(function (ev) {
            area.addEventListener(ev, function (e) {
                e.preventDefault(); e.stopPropagation(); area.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            area.addEventListener(ev, function (e) {
                e.preventDefault(); e.stopPropagation(); area.classList.remove('dragover');
            });
        });
        area.addEventListener('drop', function (e) {
            var files = e.dataTransfer && e.dataTransfer.files;
            if (!files || !files.length) return;
            try {
                var dt = new DataTransfer();
                for (var i = 0; i < files.length; i++) dt.items.add(files[i]);
                input.files = dt.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (err) { /* browser tanpa DataTransfer: pengguna memakai tombol pilih file */ }
        });
    });
}
window.initUploadDropzones = initUploadDropzones;

document.addEventListener('DOMContentLoaded', function () {
    initPdfPreviewPanel();
    initUploadDropzones();

    var tambahLaporanModalShown = false;
    var tambahLaporanModal = document.getElementById('tambahLaporanModal');
    if (tambahLaporanModal) {
        tambahLaporanModal.addEventListener('shown.bs.modal', function () {
            tambahLaporanModalShown = true;
        });
        tambahLaporanModal.addEventListener('hidden.bs.modal', function () {
            if (tambahLaporanModalShown) {
                var form = document.querySelector('#tambahLaporanModal form');
                if (form) {
                    form.reset();
                    var tglInput = form.querySelector('input[name="tanggal"]');
                    if (tglInput) {
                        var d = new Date();
                        var yyyy = d.getFullYear();
                        var mm = String(d.getMonth() + 1).padStart(2, '0');
                        var dd = String(d.getDate()).padStart(2, '0');
                        tglInput.value = yyyy + '-' + mm + '-' + dd;
                    }
                    var jamMulai = form.querySelector('input[name="jam_mulai"]');
                    if (jamMulai) jamMulai.value = '07:30';
                    var jamSelesai = form.querySelector('input[name="jam_selesai"]');
                    if (jamSelesai) jamSelesai.value = '16:00';
                    var jumlah = form.querySelector('input[name="jumlah_kegiatan"]');
                    if (jumlah) jumlah.value = '1';
                }
                var previewDiv = document.getElementById('bukti_preview_modal');
                if (previewDiv) previewDiv.innerHTML = '';
                var fileInput = document.getElementById('bukti_input_modal');
                if (fileInput) fileInput.value = '';
                var clearFn = window['__clearPreview_bukti_input_modal'];
                if (typeof clearFn === 'function') clearFn();
                /* Modal Dashboard memakai id preview/input tersendiri */
                var previewDash = document.getElementById('bukti_preview_dash');
                if (previewDash) previewDash.innerHTML = '';
                var fileInputDash = document.getElementById('bukti_input_dash');
                if (fileInputDash) fileInputDash.value = '';
                var clearDash = window['__clearPreview_bukti_input_dash'];
                if (typeof clearDash === 'function') clearDash();
                /* Kembalikan tombol simpan ke keadaan normal (anti double-submit) */
                releaseSubmitButtons(tambahLaporanModal);
            }
            tambahLaporanModalShown = false;
        });
    }
});

window.confirmDelete = confirmDelete;
window.preparePrint = preparePrint;
window.setupImagePreview = setupImagePreview;
window.openPreviewLaporan = openPreviewLaporan;

window.loadBukti = function loadBukti(laporanId) {
    var body = document.getElementById('buktiModalBody');
    if (!body) return;
    body.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div><div class="small">Memuat bukti...</div></div>';
    fetch('get_bukti.php?id=' + encodeURIComponent(laporanId))
        .then(function (r) { return r.text(); })
        .then(function (html) {
            if (body) body.innerHTML = html;
        })
        .catch(function () {
            if (body) body.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat bukti. Silakan muat ulang halaman.</div>';
        });
};

/* ==========================================================
   SIDEBAR TOGGLE — Mobile Hamburger & Desktop Collapse
   ========================================================== */
(function setupSidebarToggle() {
    var toggleBtn = document.getElementById('sidebarToggleBtn');
    var backdrop = document.getElementById('sidebarBackdrop');
    var body = document.body;
    var MOBILE_BREAKPOINT = 992;

    function isMobile() {
        return window.innerWidth < MOBILE_BREAKPOINT;
    }

    function closeSidebar() {
        body.classList.remove('sidebar-open');
        body.classList.remove('sidebar-collapsed');
    }

    function toggleSidebar() {
        if (isMobile()) {
            // Mobile: off-canvas slide-in
            body.classList.toggle('sidebar-open');
            body.classList.remove('sidebar-collapsed');
        } else {
            // Desktop / Tablet: compact ↔ expanded
            body.classList.remove('sidebar-open');
            body.classList.toggle('sidebar-collapsed');
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function (e) {
            if (body.classList.contains('sidebar-open')) {
                closeSidebar();
            }
        });
    }

    // Keyboard: ESC to close
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (body.classList.contains('sidebar-open')) {
                closeSidebar();
            }
        }
    });

    // Resize: bersihkan state yang tidak relevan lintas breakpoint
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (!isMobile() && body.classList.contains('sidebar-open')) {
                // Masuk desktop — tutup off-canvas mobile
                body.classList.remove('sidebar-open');
            }
            if (isMobile() && body.classList.contains('sidebar-collapsed')) {
                // Masuk mobile — nonaktifkan collapsed desktop
                body.classList.remove('sidebar-collapsed');
            }
        }, 150);
    });
})();

/* ==========================================================
   SIDEBAR HIDE — Sembunyikan/tampilkan sidebar (desktop)
   ========================================================== */
(function setupSidebarHideToggle() {
    var btn = document.getElementById('sidebarHideBtn');
    if (!btn) return;

    var body = document.body;
    var icon = btn.querySelector('i');
    var STORAGE_KEY = 'asnSidebarHidden';

    function render() {
        var hidden = body.classList.contains('sidebar-hidden');
        var label = hidden ? 'Tampilkan Sidebar' : 'Sembunyikan Sidebar';
        btn.setAttribute('aria-label', label);
        btn.setAttribute('title', label);
        if (icon) {
            icon.className = hidden ? 'bi bi-layout-sidebar' : 'bi bi-layout-sidebar-inset';
        }
    }

    btn.addEventListener('click', function (e) {
        e.preventDefault();
        body.classList.toggle('sidebar-hidden');
        try {
            localStorage.setItem(STORAGE_KEY, body.classList.contains('sidebar-hidden') ? '1' : '0');
        } catch (err) {}
        render();
    });

    render();
})();


/* =========================================================
   MD DESIGN SYSTEM — Helper reusable: Update RHK Dropdown
   By selected date (bulan & tahun tanggal ybs) via AJAX
   DIPAKAI DI: full page aksi=tambah, modal inline laporan,
               modal dashboard, dll. Global scope.
   ========================================================= */
(function () {
    function htmlesc(s) {
        var t = document.createElement('textarea');
        t.value = (s == null ? '' : String(s));
        return t.innerHTML;
    }
    function updateRHKSelectByTanggal(tanggalInputEl, selectEl, emptyBoxEl, emptyLinkEl, baseUrl) {
        if (!tanggalInputEl || !tanggalInputEl.value || !selectEl) return Promise.resolve(false);
        var v = String(tanggalInputEl.value);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) return Promise.resolve(false);
        var d = new Date(v + 'T00:00:00');
        if (isNaN(d.getTime())) return Promise.resolve(false);
        var bulan = d.getMonth() + 1;
        var tahun = d.getFullYear();
        var wasDisabled = !!selectEl.disabled;
        selectEl.classList.add('is-loading');
        selectEl.disabled = true;
        var root = (typeof baseUrl === 'string' && baseUrl !== '') ? baseUrl : '';
        if (root && root.charAt(root.length - 1) !== '/') root += '/';
        var url = root + 'ajax_rhk_options.php?bulan=' + encodeURIComponent(bulan) + '&tahun=' + encodeURIComponent(tahun) + '&_=' + encodeURIComponent(Date.now());
        return fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (json) {
                /* Bila AJAX gagal / jawaban tidak valid (mis. 403 karena sesi berakhir),
                   JANGAN timpa opsi hasil render PHP supaya dropdown tidak tampil kosong. */
                if (!json || json.ok !== true || !Array.isArray(json.data)) return false;
                var arr = json.data;
                var prevSel = selectEl.value;
                while (selectEl.options.length > 0) {
                    selectEl.remove(0);
                }
                var firstLabel = arr.length === 0 ? '-- Belum ada Rencana Aksi bulan ini --' : '-- Pilih Rencana Aksi --';
                selectEl.add(new Option(firstLabel, ''));
                for (var i = 0; i < arr.length; i++) {
                    var it = arr[i];
                    var lbl = (it && it.label) ? String(it.label) : ('[RHK #' + (it && it.id ? it.id : '') + ']');
                    var suffix = (it && it.semua_bulan === true) ? '  - Semua Bulan' : '';
                    selectEl.add(new Option(lbl + suffix, String(parseInt(it && it.id ? it.id : 0, 10))));
                }
                if (prevSel) {
                    for (var j = 0; j < selectEl.options.length; j++) {
                        if (String(selectEl.options[j].value) === String(prevSel)) {
                            selectEl.selectedIndex = j; break;
                        }
                    }
                }
                if (emptyBoxEl) emptyBoxEl.style.display = (arr.length === 0) ? '' : 'none';
                if (emptyLinkEl) {
                    var href = root + 'rhk.php?aksi=tambah&bulan=' + encodeURIComponent(bulan) + '&tahun=' + encodeURIComponent(tahun);
                    emptyLinkEl.href = href;
                    if (selectEl.getAttribute && selectEl.getAttribute('data-empty-href')) {
                        selectEl.setAttribute('data-empty-href', href);
                    }
                }
                return true;
            })
            .catch(function () { return false; })
            .then(function (ok) {
                try { selectEl.classList.remove('is-loading'); } catch (e) {}
                selectEl.disabled = wasDisabled;
                return ok;
            });
    }
    window.__updateRHKSelectByTanggal = updateRHKSelectByTanggal;
})();
