(function () {
    'use strict';

    var form = document.getElementById('editor-upload-form');
    var fileInput = document.getElementById('editor-upload-input');
    var stickerInput = document.getElementById('editor-upload-sticker');
    if (!form || !fileInput || !stickerInput) {
        return;
    }

    var isSubmitting = false;

    function hasSelectedFile() {
        return !!(fileInput.files && fileInput.files.length > 0);
    }

    function isEmptyEditorState() {
        return (form.getAttribute('data-editor-state') || '') === 'EMPTY';
    }

    function hasSelectedSticker() {
        return stickerInput.value.trim() !== '';
    }

    function syncUploadInputEnabled() {
        var label = fileInput.closest('label');
        var gated = isEmptyEditorState() && !hasSelectedSticker();
        fileInput.disabled = gated;
        if (!label) {
            return;
        }
        if (gated) {
            label.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
            label.classList.remove('hover:bg-slate-50', 'cursor-pointer');
        } else {
            label.classList.remove('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
            label.classList.add('hover:bg-slate-50', 'cursor-pointer');
        }
    }

    document.addEventListener('editor-entry-sticker-changed', syncUploadInputEnabled);
    syncUploadInputEnabled();

    fileInput.addEventListener('change', function () {
        if (isSubmitting || !hasSelectedFile()) {
            return;
        }

        if (isEmptyEditorState() && !hasSelectedSticker()) {
            return;
        }

        isSubmitting = true;
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }
        form.submit();
    });
})();
