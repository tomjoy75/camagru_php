(function () {
    var preview = document.getElementById('editor-webcam-preview');
    var fallback = document.getElementById('editor-webcam-fallback');
    var captureForm = document.getElementById('editor-capture-form');
    var captureInput = document.getElementById('editor-capture-input');
    var captureButton = document.getElementById('editor-capture-button');

    if (!captureForm || !captureInput || !captureButton) {
        return;
    }

    var webcamCaptureAllowed = false;

    function showFallback(message) {
        if (fallback && preview) {
            fallback.textContent = message;
            fallback.classList.remove('hidden');
            preview.classList.add('hidden');
        }
    }

    function syncCaptureEnabled() {
        var stickerGated = !!document.getElementById('editor-no-base-sticker-picks');
        var hidden = document.getElementById('editor-capture-sticker');
        var stickerVal = hidden ? (hidden.value || '').trim() : '';
        var stickerOk = stickerVal !== '';
        var wantEnable = webcamCaptureAllowed && (!stickerGated || stickerOk);
        captureButton.disabled = !wantEnable;
        if (wantEnable) {
            captureButton.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            captureButton.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    function disableCapture(message) {
        webcamCaptureAllowed = false;
        if (message) {
            showFallback(message);
        }
        syncCaptureEnabled();
    }

    if (!preview || !fallback) {
        disableCapture('Capture is unavailable while an uploaded image is shown.');
        return;
    }

    document.addEventListener('editor-capture-sticker-changed', syncCaptureEnabled);
    syncCaptureEnabled();

    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        disableCapture('Webcam preview is not supported by this browser.');
        return;
    }

    navigator.mediaDevices
        .getUserMedia({ video: true })
        .then(function (stream) {
            preview.srcObject = stream;
            preview.classList.remove('hidden');
            fallback.classList.add('hidden');
            webcamCaptureAllowed = true;
            syncCaptureEnabled();
        })
        .catch(function () {
            disableCapture('Unable to access webcam preview. Check camera permissions.');
        });

    captureForm.addEventListener('submit', function (event) {
        if (!preview.srcObject || preview.videoWidth === 0 || preview.videoHeight === 0) {
            event.preventDefault();
            showFallback('Capture is unavailable right now. Upload remains available.');
            return;
        }

        var canvas = document.createElement('canvas');
        canvas.width = preview.videoWidth;
        canvas.height = preview.videoHeight;
        var context = canvas.getContext('2d');
        if (!context) {
            event.preventDefault();
            showFallback('Capture is unavailable right now. Upload remains available.');
            return;
        }

        context.drawImage(preview, 0, 0, canvas.width, canvas.height);
        captureInput.value = canvas.toDataURL('image/png');
    });
})();
