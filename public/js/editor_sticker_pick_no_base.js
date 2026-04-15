(function () {
    'use strict';

    var root = document.getElementById('editor-no-base-sticker-picks');
    var captureHidden = document.getElementById('editor-capture-sticker');
    var uploadHidden = document.getElementById('editor-upload-sticker');
    var guidance = document.getElementById('editor-guidance-message');
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

    function notifyStickerChanged() {
        syncGuidanceMessage();
        document.dispatchEvent(new CustomEvent('editor-capture-sticker-changed'));
        document.dispatchEvent(new CustomEvent('editor-entry-sticker-changed'));
    }

    function syncGuidanceMessage() {
        if (!guidance) {
            return;
        }
        var state = (guidance.getAttribute('data-editor-state') || '').trim();
        if (state !== 'EMPTY') {
            return;
        }
        var hasSticker = (captureHidden.value || '').trim() !== '';
        var messageAttr = hasSticker ? 'data-empty-with-sticker-message' : 'data-empty-message';
        var text = guidance.getAttribute(messageAttr) || '';
        if (text !== '') {
            guidance.textContent = text;
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
        notifyStickerChanged();
    }

    var picks = root.querySelectorAll('.editor-sticker-pick');
    for (var j = 0; j < picks.length; j++) {
        picks[j].addEventListener('click', onPickClick);
    }

    var initial = (captureHidden.value || '').trim();
    if (initial !== '' && initial === (uploadHidden.value || '').trim()) {
        setPressed(initial);
    }
    notifyStickerChanged();
})();
