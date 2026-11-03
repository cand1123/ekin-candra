(function () {
    'use strict';

    function setupFormValidation(formSelector) {
        var forms = document.querySelectorAll(formSelector);
        if (!forms.length) return;

        forms.forEach(function (form) {
            form.setAttribute('novalidate', '');

            form.addEventListener('submit', function (e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    highlightInvalidFields(form);
                    scrollToFirstInvalid(form);
                }
                form.classList.add('was-validated');
            }, false);

            form.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.addEventListener('blur', function () {
                    if (form.classList.contains('was-validated')) {
                        validateSingleField(field);
                    }
                });
                field.addEventListener('input', function () {
                    if (field.classList.contains('is-invalid')) {
                        validateSingleField(field);
                    }
                });
                field.addEventListener('change', function () {
                    if (form.classList.contains('was-validated')) {
                        validateSingleField(field);
                    }
                });
            });
        });
    }

    function validateSingleField(field) {
        if (field.checkValidity()) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            removeInlineError(field);
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            showInlineError(field);
        }
    }

    function highlightInvalidFields(form) {
        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            if (!field.checkValidity()) {
                field.classList.add('is-invalid');
                field.classList.remove('is-valid');
                showInlineError(field);
            } else {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
                removeInlineError(field);
            }
        });
    }

    function showInlineError(field) {
        removeInlineError(field);
        var msg = field.validationMessage || 'Mohon periksa kembali isian ini.';
        var err = document.createElement('div');
        err.className = 'invalid-feedback small fw-semibold mt-1';
        err.setAttribute('data-auto-error', '1');
        err.textContent = msg;
        if (field.parentNode && field.parentNode.classList.contains('input-group')) {
            field.parentNode.after(err);
        } else {
            field.after(err);
        }
    }

    function removeInlineError(field) {
        var scope = field.parentNode && field.parentNode.classList.contains('input-group')
            ? field.parentNode.parentNode
            : field.parentNode;
        if (!scope) return;
        scope.querySelectorAll('[data-auto-error="1"]').forEach(function (el) {
            el.remove();
        });
    }

    function scrollToFirstInvalid(form) {
        var first = form.querySelector('.is-invalid');
        if (first) {
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            try { first.focus(); } catch (e) {}
        }
    }

    function validateFileSize(inputSelector, maxMB) {
        var inputs = document.querySelectorAll(inputSelector);
        var maxBytes = (maxMB || 5) * 1024 * 1024;
        inputs.forEach(function (input) {
            input.addEventListener('change', function () {
                var files = Array.from(this.files || []);
                var oversize = files.filter(function (f) { return f.size > maxBytes; });
                if (oversize.length) {
                    var names = oversize.map(function (f) { return f.name; }).join(', ');
                    alert('File berikut melebihi batas ukuran maksimal (' + maxMB + 'MB):\n' + names);
                    this.value = '';
                }
            });
        });
    }

    function validateTimeRange(startSelector, endSelector) {
        var starts = document.querySelectorAll(startSelector);
        var ends = document.querySelectorAll(endSelector);
        starts.forEach(function (s, i) {
            var e = ends[i];
            if (!e) return;
            function check() {
                if (s.value && e.value && s.value > e.value) {
                    s.setCustomValidity('Jam mulai harus lebih awal dari jam selesai.');
                    e.setCustomValidity('Jam selesai harus lebih akhir dari jam mulai.');
                } else {
                    s.setCustomValidity('');
                    e.setCustomValidity('');
                }
            }
            s.addEventListener('change', check);
            e.addEventListener('change', check);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupFormValidation('form[method="POST"]');
        validateFileSize('input[type="file"][accept*="image/"]', 5);
        validateTimeRange('input[name="jam_mulai"]', 'input[name="jam_selesai"]');
    });

    window.ASNValidation = {
        setupFormValidation: setupFormValidation,
        validateFileSize: validateFileSize,
        validateTimeRange: validateTimeRange
    };
})();
