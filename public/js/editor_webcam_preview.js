(function () {
    var preview = document.getElementById('editor-webcam-preview');
    var fallback = document.getElementById('editor-webcam-fallback');
    var captureForm = document.getElementById('editor-capture-form');
    var captureInput = document.getElementById('editor-capture-input');
    var captureButton = document.getElementById('editor-capture-button');

    if (!captureForm || !captureInput || !captureButton) {
        return;
    }

    function showFallback(message) {
        if (fallback && preview) {
            fallback.textContent = message;
            fallback.classList.remove('hidden');
            preview.classList.add('hidden');
        }
    }

    function disableCapture(message) {
        captureButton.disabled = true;
        captureButton.classList.add('opacity-50', 'cursor-not-allowed');
        if (message) {
            showFallback(message);
        }
    }

    if (!preview || !fallback) {
        disableCapture('Capture is unavailable while an uploaded image is shown.');
        return;
    }

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
