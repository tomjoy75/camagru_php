(function () {
    'use strict';

    var form = document.getElementById('editor-compose-form');
    var baseImg = document.getElementById('editor-base-preview-img');
    var stage = document.getElementById('editor-sticker-stage');
    var overlay = document.getElementById('editor-sticker-overlay');
    if (!form || !baseImg || !stage || !overlay) {
        return;
    }

    var scaleRange = document.getElementById('editor-scale-range');
    var angleRange = document.getElementById('editor-angle-range');
    var hiddenSticker = document.getElementById('editor-compose-sticker');
    var hiddenX = document.getElementById('editor-compose-x');
    var hiddenY = document.getElementById('editor-compose-y');
    var hiddenScale = document.getElementById('editor-compose-scale');
    var hiddenAngle = document.getElementById('editor-compose-angle');
    var applyButton = document.getElementById('editor-compose-submit');

    var baseW = baseImg.naturalWidth;
    var baseH = baseImg.naturalHeight;
    if (baseW <= 0 || baseH <= 0) {
        return;
    }

    var stickerNw = 0;
    var stickerNh = 0;
    var dragging = false;
    var dragStartClientX = 0;
    var dragStartClientY = 0;
    var dragStartX = 0;
    var dragStartY = 0;

    function targetStickerDimensions(bw, bh, sw, sh) {
        var s = Math.min(1, bw / sw, bh / sh);
        var w = Math.max(1, Math.floor(sw * s));
        var h = Math.max(1, Math.floor(sh * s));
        w = Math.min(w, bw);
        h = Math.min(h, bh);
        return { w: w, h: h };
    }

    function rotatedAabbSize(w, h, angleDeg) {
        var rad = (angleDeg * Math.PI) / 180;
        var bw = Math.abs(w * Math.cos(rad)) + Math.abs(h * Math.sin(rad));
        var bh = Math.abs(w * Math.sin(rad)) + Math.abs(h * Math.cos(rad));
        return [Math.max(1, Math.ceil(bw)), Math.max(1, Math.ceil(bh))];
    }

    function shrinkForRotation(uw, uh, angleDeg) {
        var w = uw;
        var h = uh;
        var ab = rotatedAabbSize(w, h, angleDeg);
        if (ab[0] <= baseW && ab[1] <= baseH) {
            return [w, h];
        }
        var f = Math.min(baseW / ab[0], baseH / ab[1]) * 0.999;
        return [Math.max(1, Math.floor(w * f)), Math.max(1, Math.floor(h * f))];
    }

    /**
     * Matches server: uw×uh = sticker before imagerotate; rotW×rotH = GD output canvas (AABB).
     */
    function placementDimensions() {
        if (stickerNw <= 0 || stickerNh <= 0) {
            return { uw: 1, uh: 1, rotW: 1, rotH: 1 };
        }
        var fit = targetStickerDimensions(baseW, baseH, stickerNw, stickerNh);
        var us = readUserScale();
        var uw0 = Math.max(1, Math.floor(fit.w * us));
        var uh0 = Math.max(1, Math.floor(fit.h * us));
        var angle = readAngle();
        var shrunk = shrinkForRotation(uw0, uh0, angle);
        var uw = shrunk[0];
        var uh = shrunk[1];
        var ab = rotatedAabbSize(uw, uh, angle);
        return { uw: uw, uh: uh, rotW: ab[0], rotH: ab[1] };
    }

    function getContentOffsets(img) {
        var nw = img.naturalWidth;
        var nh = img.naturalHeight;
        var cw = img.clientWidth;
        var ch = img.clientHeight;
        var scale = Math.min(cw / nw, ch / nh);
        var dw = nw * scale;
        var dh = nh * scale;
        var ox = (cw - dw) / 2;
        var oy = (ch - dh) / 2;
        return { scale: scale, ox: ox, oy: oy, nw: nw, nh: nh };
    }

    function readUserScale() {
        var v = parseInt(scaleRange.value, 10);
        if (isNaN(v)) {
            v = 100;
        }
        return Math.max(5, Math.min(100, v)) / 100;
    }

    function readAngle() {
        var v = parseInt(angleRange.value, 10);
        if (isNaN(v)) {
            v = 0;
        }
        return Math.max(-180, Math.min(180, v));
    }

    function clampPosition(x, y) {
        var pl = placementDimensions();
        var maxX = Math.max(0, baseW - pl.rotW);
        var maxY = Math.max(0, baseH - pl.rotH);
        return {
            x: Math.max(0, Math.min(Math.round(x), maxX)),
            y: Math.max(0, Math.min(Math.round(y), maxY)),
        };
    }

    function syncHiddens() {
        var pos = clampPosition(parseInt(hiddenX.value, 10) || 0, parseInt(hiddenY.value, 10) || 0);
        hiddenX.value = String(pos.x);
        hiddenY.value = String(pos.y);
        hiddenScale.value = String(readUserScale());
        hiddenAngle.value = String(readAngle());
    }

    function syncApplyDisabled() {
        if (!applyButton) {
            return;
        }
        var disabled = !hiddenSticker || hiddenSticker.value.trim() === '';
        applyButton.disabled = disabled;
        applyButton.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        applyButton.classList.toggle('opacity-50', disabled);
        applyButton.classList.toggle('cursor-not-allowed', disabled);
    }

    function syncOverlayVisual() {
        if (!overlay.src || stickerNw <= 0) {
            stage.classList.add('hidden');
            return;
        }
        stage.classList.remove('hidden');

        var off = getContentOffsets(baseImg);
        var angle = readAngle();
        var pl = placementDimensions();
        var px = parseInt(hiddenX.value, 10) || 0;
        var py = parseInt(hiddenY.value, 10) || 0;
        var p = clampPosition(px, py);
        hiddenX.value = String(p.x);
        hiddenY.value = String(p.y);

        var s = off.scale;
        stage.style.width = pl.rotW * s + 'px';
        stage.style.height = pl.rotH * s + 'px';
        stage.style.left = baseImg.offsetLeft + off.ox + p.x * s + 'px';
        stage.style.top = baseImg.offsetTop + off.oy + p.y * s + 'px';

        overlay.style.width = pl.uw * s + 'px';
        overlay.style.height = pl.uh * s + 'px';
        overlay.style.left = ((pl.rotW - pl.uw) / 2) * s + 'px';
        overlay.style.top = ((pl.rotH - pl.uh) / 2) * s + 'px';
        overlay.style.transform = 'rotate(' + angle + 'deg)';
        overlay.style.transformOrigin = 'center center';
    }

    function centerSticker() {
        var pl = placementDimensions();
        var x = Math.floor((baseW - pl.rotW) / 2);
        var y = Math.floor((baseH - pl.rotH) / 2);
        var p = clampPosition(x, y);
        hiddenX.value = String(p.x);
        hiddenY.value = String(p.y);
    }

    function selectSticker(btn) {
        var url = btn.getAttribute('data-sticker-url');
        var name = btn.getAttribute('data-sticker');
        if (!url || !name) {
            return;
        }
        hiddenSticker.value = name;
        var picks = document.querySelectorAll('.editor-sticker-pick');
        for (var i = 0; i < picks.length; i++) {
            picks[i].setAttribute('aria-pressed', picks[i] === btn ? 'true' : 'false');
        }
        syncApplyDisabled();
        overlay.onload = function () {
            overlay.onload = null;
            stickerNw = overlay.naturalWidth;
            stickerNh = overlay.naturalHeight;
            centerSticker();
            syncHiddens();
            syncOverlayVisual();
        };
        overlay.src = url;
    }

    function onPickClick(ev) {
        var btn = ev.currentTarget;
        selectSticker(btn);
    }

    var picks = document.querySelectorAll('.editor-sticker-pick');
    for (var j = 0; j < picks.length; j++) {
        picks[j].addEventListener('click', onPickClick);
    }

    var pre = (hiddenSticker && hiddenSticker.value) ? hiddenSticker.value.trim() : '';
    if (pre) {
        for (var k = 0; k < picks.length; k++) {
            if ((picks[k].getAttribute('data-sticker') || '') === pre) {
                selectSticker(picks[k]);
                break;
            }
        }
    }
    syncApplyDisabled();

    scaleRange.addEventListener('input', function () {
        syncHiddens();
        syncOverlayVisual();
    });
    angleRange.addEventListener('input', function () {
        var p = clampPosition(parseInt(hiddenX.value, 10) || 0, parseInt(hiddenY.value, 10) || 0);
        hiddenX.value = String(p.x);
        hiddenY.value = String(p.y);
        syncHiddens();
        syncOverlayVisual();
    });

    stage.addEventListener('mousedown', function (ev) {
        if (!overlay.src) {
            return;
        }
        ev.preventDefault();
        dragging = true;
        dragStartClientX = ev.clientX;
        dragStartClientY = ev.clientY;
        dragStartX = parseInt(hiddenX.value, 10) || 0;
        dragStartY = parseInt(hiddenY.value, 10) || 0;
        stage.style.cursor = 'grabbing';
    });

    window.addEventListener('mousemove', function (ev) {
        if (!dragging) {
            return;
        }
        var off = getContentOffsets(baseImg);
        if (off.scale <= 0) {
            return;
        }
        var dx = (ev.clientX - dragStartClientX) / off.scale;
        var dy = (ev.clientY - dragStartClientY) / off.scale;
        var p = clampPosition(dragStartX + dx, dragStartY + dy);
        hiddenX.value = String(p.x);
        hiddenY.value = String(p.y);
        syncHiddens();
        syncOverlayVisual();
    });

    window.addEventListener('mouseup', function () {
        if (dragging) {
            dragging = false;
            stage.style.cursor = 'move';
        }
    });

    baseImg.addEventListener('load', function () {
        baseW = baseImg.naturalWidth;
        baseH = baseImg.naturalHeight;
        syncOverlayVisual();
    });
    window.addEventListener('resize', function () {
        syncOverlayVisual();
    });

    form.addEventListener('submit', function () {
        syncHiddens();
    });
})();
