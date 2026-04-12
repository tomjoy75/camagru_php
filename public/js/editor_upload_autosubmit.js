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
