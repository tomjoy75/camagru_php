(function () {
    'use strict';

    var root = document.getElementById('editor-no-base-sticker-picks');
    var captureHidden = document.getElementById('editor-capture-sticker');
    var uploadHidden = document.getElementById('editor-upload-sticker');
    if (!root || !captureHidden || !uploadHidden) {
        return;
    }

    function setPressed(filename) {
        var picks = root.querySelectorAll('.editor-sticker-pick');
        for (var i = 0; i < picks.length; i++) {
            var n = picks[i].getAttribute('data-sticker') || '';
            picks[i].setAttribute('aria-pressed', n === filename ? 'true' : 'false');
        }
    }

    function onPickClick(ev) {
        var btn = ev.currentTarget;
        var name = btn.getAttribute('data-sticker');
        if (!name) {
            return;
        }
        captureHidden.value = name;
        uploadHidden.value = name;
        setPressed(name);
    }

    var picks = root.querySelectorAll('.editor-sticker-pick');
    for (var j = 0; j < picks.length; j++) {
        picks[j].addEventListener('click', onPickClick);
    }

    var initial = (captureHidden.value || '').trim();
    if (initial !== '' && initial === (uploadHidden.value || '').trim()) {
        setPressed(initial);
    }
})();
