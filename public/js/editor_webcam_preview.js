(function () {
    var preview = document.getElementById('editor-webcam-preview');
    var fallback = document.getElementById('editor-webcam-fallback');

    if (!preview || !fallback) {
        return;
    }

    function showFallback(message) {
        fallback.textContent = message;
        fallback.classList.remove('hidden');
        preview.classList.add('hidden');
    }

    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        showFallback('Webcam preview is not supported by this browser.');
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
            showFallback('Unable to access webcam preview. Check camera permissions.');
        });
})();
