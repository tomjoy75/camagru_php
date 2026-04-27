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
    /** Cache alpha bbox in rotated-AABB space (same uw, uh, rotW, rotH, angle as placementDimensions). */
    var visibleBoundsCache = { key: '', bounds: null };
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

    function invalidateVisibleBoundsCache() {
        visibleBoundsCache.key = '';
        visibleBoundsCache.bounds = null;
    }

    /**
     * Rasterize the sticker like the on-screen preview: uw×uh bitmap centered in a rotW×rotH box, then rotate
     * around the box center (same layout as syncOverlayVisual + CSS rotate). Scan alpha to get the tight bbox
     * of non-transparent pixels in that box's coordinate system (0..rotW-1, 0..rotH-1).
     *
     * Rotation note: we use the same signed degrees as the UI (CSS transform). PHP uses imagerotate(..., -angle);
     * both aim for the same clockwise user angle; anti-aliasing and ±1 canvas size vs GD can still cause tiny
     * differences at Apply time compared to this scan.
     */
    function computeVisibleBoundsInRotCanvas(overlayImg, uw, uh, rotW, rotH, angleDeg) {
        if (!overlayImg || !overlayImg.complete || overlayImg.naturalWidth <= 0 || overlayImg.naturalHeight <= 0) {
            return null;
        }
        if (uw <= 0 || uh <= 0 || rotW <= 0 || rotH <= 0) {
            return null;
        }
        try {
            var canvas = document.createElement('canvas');
            canvas.width = rotW;
            canvas.height = rotH;
            var ctx = canvas.getContext('2d', { willReadFrequently: true });
            if (!ctx) {
                return null;
            }
            ctx.clearRect(0, 0, rotW, rotH);
            ctx.save();
            ctx.translate(rotW / 2, rotH / 2);
            ctx.rotate((angleDeg * Math.PI) / 180);
            ctx.drawImage(
                overlayImg,
                0,
                0,
                overlayImg.naturalWidth,
                overlayImg.naturalHeight,
                -uw / 2,
                -uh / 2,
                uw,
                uh
            );
            ctx.restore();

            var data = ctx.getImageData(0, 0, rotW, rotH).data;
            var minX = rotW;
            var minY = rotH;
            var maxX = -1;
            var maxY = -1;
            var i = 0;
            for (var y = 0; y < rotH; y++) {
                for (var x = 0; x < rotW; x++) {
                    if (data[i + 3] > 0) {
                        if (x < minX) {
                            minX = x;
                        }
                        if (y < minY) {
                            minY = y;
                        }
                        if (x > maxX) {
                            maxX = x;
                        }
                        if (y > maxY) {
                            maxY = y;
                        }
                    }
                    i += 4;
                }
            }
            if (maxX < 0 || maxY < 0) {
                return null;
            }
            return { minX: minX, minY: minY, maxX: maxX, maxY: maxY };
        } catch (e) {
            return null;
        }
    }

    function getVisibleBoundsForPlacement(overlayImg, pl, angleDeg) {
        var key = [pl.uw, pl.uh, pl.rotW, pl.rotH, angleDeg, overlayImg.src || ''].join('|');
        if (visibleBoundsCache.key === key) {
            return visibleBoundsCache.bounds;
        }
        var b = computeVisibleBoundsInRotCanvas(overlayImg, pl.uw, pl.uh, pl.rotW, pl.rotH, angleDeg);
        visibleBoundsCache.key = key;
        visibleBoundsCache.bounds = b;
        return b;
    }

    /**
     * (x, y) is the top-left of the full transformed sticker bitmap (rotW×rotH) on the base image, same as server imagecopy.
     * Allowed range uses visible pixel extents: opaque bbox [minX,maxX]×[minY,maxY] in rot space →
     * minXPlace = -minX so transparent margin can leave the base upward/left; maxXPlace = baseW - 1 - maxX (inclusive), matching ImageComposeService.
     */
    function clampPosition(x, y) {
        var pl = placementDimensions();
        var angleDeg = readAngle();
        var vb = getVisibleBoundsForPlacement(overlay, pl, angleDeg);
        var rx = Math.round(x);
        var ry = Math.round(y);

        if (!vb) {
            var maxXF = Math.max(0, baseW - pl.rotW);
            var maxYF = Math.max(0, baseH - pl.rotH);
            return {
                x: Math.max(0, Math.min(rx, maxXF)),
                y: Math.max(0, Math.min(ry, maxYF)),
            };
        }

        var minX = -vb.minX;
        var maxX = baseW - 1 - vb.maxX;
        var minY = -vb.minY;
        var maxY = baseH - 1 - vb.maxY;
        if (minX > maxX || minY > maxY) {
            var maxXD = Math.max(0, baseW - pl.rotW);
            var maxYD = Math.max(0, baseH - pl.rotH);
            return {
                x: Math.max(0, Math.min(rx, maxXD)),
                y: Math.max(0, Math.min(ry, maxYD)),
            };
        }

        return {
            x: Math.max(minX, Math.min(maxX, rx)),
            y: Math.max(minY, Math.min(maxY, ry)),
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

    function findPickByName(name) {
        var normalized = (name || '').trim();
        if (!normalized) {
            return null;
        }
        for (var i = 0; i < picks.length; i++) {
            if ((picks[i].getAttribute('data-sticker') || '') === normalized) {
                return picks[i];
            }
        }
        return null;
    }

    function syncPressedState(activeBtn) {
        for (var i = 0; i < picks.length; i++) {
            picks[i].setAttribute('aria-pressed', picks[i] === activeBtn ? 'true' : 'false');
        }
    }

    function selectSticker(btn) {
        var url = btn.getAttribute('data-sticker-url');
        var name = btn.getAttribute('data-sticker');
        if (!url || !name) {
            return;
        }
        invalidateVisibleBoundsCache();
        hiddenSticker.value = name;
        syncPressedState(btn);
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

    var entryAutoShowEnabled = stage.getAttribute('data-entry-autoshow') === '1';
    var entryStickerName = (stage.getAttribute('data-entry-sticker') || '').trim();
    var entryStickerUrl = (stage.getAttribute('data-entry-sticker-url') || '').trim();
    var didRunEntryAutoShow = false;
    if (entryAutoShowEnabled && entryStickerName && entryStickerUrl) {
        var entryPick = findPickByName(entryStickerName);
        if (entryPick) {
            selectSticker(entryPick);
            didRunEntryAutoShow = true;
        } else {
            invalidateVisibleBoundsCache();
            hiddenSticker.value = entryStickerName;
            syncApplyDisabled();
            overlay.onload = function () {
                overlay.onload = null;
                stickerNw = overlay.naturalWidth;
                stickerNh = overlay.naturalHeight;
                centerSticker();
                syncHiddens();
                syncOverlayVisual();
            };
            overlay.src = entryStickerUrl;
            didRunEntryAutoShow = true;
        }
        stage.setAttribute('data-entry-autoshow', '0');
    }

    var pre = (hiddenSticker && hiddenSticker.value) ? hiddenSticker.value.trim() : '';
    if (!didRunEntryAutoShow && pre) {
        var prePick = findPickByName(pre);
        if (prePick) {
            selectSticker(prePick);
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
