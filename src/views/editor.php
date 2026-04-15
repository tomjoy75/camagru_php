<?php
$stickers = $stickers ?? [];
$editorState = is_string($editorState ?? null) ? $editorState : 'EMPTY';
if (!in_array($editorState, ['EMPTY', 'BASE_READY', 'COMPOSED_READY'], true)) {
    $editorState = 'EMPTY';
}
$isEmptyState = ($editorState === 'EMPTY');
$isWorkspaceState = ($editorState === 'BASE_READY' || $editorState === 'COMPOSED_READY');
$isComposedReadyState = ($editorState === 'COMPOSED_READY');
$canRenderWorkspaceImage = !empty($editorPreviewSrc);
$canRenderComposeForm = $isWorkspaceState
    && !empty($editorBaseNaturalW)
    && !empty($editorBaseNaturalH)
    && count($stickers) > 0;
$editorEntryStickerGateActive = $isEmptyState
    && count($stickers) > 0
    && trim((string) ($editorStickerDefault ?? '')) === '';
$editorGuidanceMessages = [
    'EMPTY' => 'Select a sticker to start',
    'EMPTY_WITH_STICKER' => 'Capture or upload a base image',
    'BASE_READY' => 'Position your sticker, then apply it',
    'COMPOSED_READY' => 'You can now save or add another sticker',
];
$editorHasEntrySticker = trim((string) ($editorStickerDefault ?? '')) !== '';
$editorGuidanceMessage = $editorGuidanceMessages['EMPTY'];
if ($editorState === 'EMPTY') {
    $editorGuidanceMessage = $editorHasEntrySticker
        ? $editorGuidanceMessages['EMPTY_WITH_STICKER']
        : $editorGuidanceMessages['EMPTY'];
} elseif ($editorState === 'BASE_READY') {
    $editorGuidanceMessage = $editorGuidanceMessages['BASE_READY'];
} elseif ($editorState === 'COMPOSED_READY') {
    $editorGuidanceMessage = $editorGuidanceMessages['COMPOSED_READY'];
}
?>
<div class="w-full grid grid-cols-1 lg:grid-cols-5 gap-6">
    <section class="lg:col-span-4 space-y-4">
        <?php if (!empty($editorSuccess)): ?>
            <div class="rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                <?php echo htmlspecialchars($editorSuccess, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($editorError)): ?>
            <div class="rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                <?php echo htmlspecialchars($editorError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <p
            id="editor-guidance-message"
            class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
            data-editor-state="<?php echo htmlspecialchars($editorState, ENT_QUOTES, 'UTF-8'); ?>"
            data-empty-message="<?php echo htmlspecialchars($editorGuidanceMessages['EMPTY'], ENT_QUOTES, 'UTF-8'); ?>"
            data-empty-with-sticker-message="<?php echo htmlspecialchars($editorGuidanceMessages['EMPTY_WITH_STICKER'], ENT_QUOTES, 'UTF-8'); ?>"
            data-base-ready-message="<?php echo htmlspecialchars($editorGuidanceMessages['BASE_READY'], ENT_QUOTES, 'UTF-8'); ?>"
            data-composed-ready-message="<?php echo htmlspecialchars($editorGuidanceMessages['COMPOSED_READY'], ENT_QUOTES, 'UTF-8'); ?>"
        >
            <?php echo htmlspecialchars($editorGuidanceMessage, ENT_QUOTES, 'UTF-8'); ?>
        </p>

        <!-- Preview: uploaded temp image or webcam placeholder -->
        <?php if ($isWorkspaceState): ?>
            <div id="editor-preview-host" class="bg-slate-200 rounded-lg aspect-video flex items-center justify-center text-slate-500 overflow-hidden">
                <?php if ($canRenderWorkspaceImage): ?>
                    <div id="editor-image-wrap" class="relative h-full w-full min-h-0">
                        <img
                            id="editor-base-preview-img"
                            src="<?php echo htmlspecialchars((string) ($editorPreviewSrc ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            alt="Workspace preview"
                            <?php if (!empty($editorBaseNaturalW) && !empty($editorBaseNaturalH)): ?>
                                width="<?php echo (int) $editorBaseNaturalW; ?>"
                                height="<?php echo (int) $editorBaseNaturalH; ?>"
                            <?php endif; ?>
                            class="block h-full w-full object-contain"
                        >
                        <div
                            id="editor-sticker-stage"
                            class="absolute top-0 left-0 z-10 hidden cursor-move pointer-events-auto"
                            aria-hidden="true"
                        >
                            <img
                                id="editor-sticker-overlay"
                                src=""
                                alt=""
                                class="absolute top-0 left-0 block max-w-none pointer-events-none"
                                draggable="false"
                            >
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-slate-500">Workspace image unavailable.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div id="editor-preview-host" class="bg-slate-200 rounded-lg aspect-video flex items-center justify-center text-slate-500 overflow-hidden">
                <video
                    id="editor-webcam-preview"
                    class="hidden h-full w-full object-contain bg-slate-900"
                    autoplay
                    playsinline
                    muted
                    aria-label="Live webcam preview"
                ></video>
                <p id="editor-webcam-fallback" class="text-slate-500">Webcam preview</p>
            </div>
        <?php endif; ?>

        <!-- Stickers -->
        <div class="bg-slate-100 rounded-lg border border-slate-200 p-4">
            <p class="text-sm font-medium text-slate-600 mb-2">Stickers</p>
            <?php if ($canRenderComposeForm): ?>
                <form id="editor-compose-form" method="post" action="/editor/compose" class="space-y-3">
                    <input type="hidden" name="sticker" id="editor-compose-sticker" value="<?php echo htmlspecialchars($editorStickerDefault ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="x" id="editor-compose-x" value="0">
                    <input type="hidden" name="y" id="editor-compose-y" value="0">
                    <input type="hidden" name="scale" id="editor-compose-scale" value="1">
                    <input type="hidden" name="angle" id="editor-compose-angle" value="0">
                    <div class="flex flex-wrap gap-3">
                        <?php foreach ($stickers as $sticker): ?>
                            <button
                                type="button"
                                class="editor-sticker-pick inline-flex items-center justify-center w-16 h-16 rounded border border-slate-200 bg-white p-1 shrink-0 hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-500"
                                data-sticker="<?php echo htmlspecialchars($sticker['filename'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-sticker-url="/stickers/<?php echo htmlspecialchars($sticker['filename'], ENT_QUOTES, 'UTF-8'); ?>"
                                aria-pressed="false"
                            >
                                <img
                                    src="/stickers/<?php echo htmlspecialchars($sticker['filename'], ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars($sticker['slug'], ENT_QUOTES, 'UTF-8'); ?>"
                                    title="<?php echo htmlspecialchars($sticker['slug'], ENT_QUOTES, 'UTF-8'); ?>"
                                    class="max-w-full max-h-full w-auto h-auto object-contain pointer-events-none"
                                >
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 sm:items-center text-sm text-slate-700">
                        <label class="flex items-center gap-2 min-w-0">
                            <span class="shrink-0 w-20">Scale</span>
                            <input type="range" id="editor-scale-range" class="flex-1 min-w-0" min="5" max="100" value="50" step="1">
                        </label>
                        <label class="flex items-center gap-2 min-w-0">
                            <span class="shrink-0 w-20">Rotate</span>
                            <input type="range" id="editor-angle-range" class="flex-1 min-w-0" min="-180" max="180" value="0" step="1">
                        </label>
                    </div>
                    <?php $composeSubmitDisabled = trim((string) ($editorStickerDefault ?? '')) === ''; ?>
                    <button
                        type="submit"
                        id="editor-compose-submit"
                        class="rounded bg-slate-800 px-4 py-2 text-white font-medium hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2<?php echo $composeSubmitDisabled ? ' opacity-50 cursor-not-allowed' : ''; ?>"
                        <?php echo $composeSubmitDisabled ? ' disabled aria-disabled="true"' : ''; ?>
                    >
                        Apply sticker
                    </button>
                </form>
            <?php elseif (count($stickers) === 0): ?>
                <p class="text-sm text-slate-500">No stickers available.</p>
            <?php elseif ($isEmptyState): ?>
                <div id="editor-no-base-sticker-picks" class="flex flex-wrap gap-3">
                    <?php foreach ($stickers as $sticker): ?>
                        <button
                            type="button"
                            class="editor-sticker-pick inline-flex items-center justify-center w-16 h-16 rounded border border-slate-200 bg-white p-1 shrink-0 hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-500"
                            data-sticker="<?php echo htmlspecialchars($sticker['filename'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-sticker-url="/stickers/<?php echo htmlspecialchars($sticker['filename'], ENT_QUOTES, 'UTF-8'); ?>"
                            aria-pressed="false"
                        >
                            <img
                                src="/stickers/<?php echo htmlspecialchars($sticker['filename'], ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars($sticker['slug'], ENT_QUOTES, 'UTF-8'); ?>"
                                title="<?php echo htmlspecialchars($sticker['slug'], ENT_QUOTES, 'UTF-8'); ?>"
                                class="max-w-full max-h-full w-auto h-auto object-contain pointer-events-none"
                            >
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-500">Sticker placement is unavailable for this workspace image.</p>
            <?php endif; ?>
            <?php if (isset($errors['compose'])): ?>
                <p class="mt-2 text-red-600 text-sm"><?php echo htmlspecialchars($errors['compose'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Capture and upload -->
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center flex-wrap">
            <form method="post" action="/editor/capture" id="editor-capture-form" class="flex flex-col gap-2">
                <input type="hidden" name="base_image_data" id="editor-capture-input" value="">
                <input type="hidden" name="sticker" id="editor-capture-sticker" value="<?php echo htmlspecialchars($editorStickerDefault ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <?php
                $editorCaptureDisabledNoSticker = $editorEntryStickerGateActive && empty($editorPreviewSrc);
                ?>
                <button type="submit" id="editor-capture-button" class="rounded bg-slate-800 px-4 py-2 text-white font-medium hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2<?php echo $editorCaptureDisabledNoSticker ? ' opacity-50 cursor-not-allowed' : ''; ?>"<?php echo $editorCaptureDisabledNoSticker ? ' disabled' : ''; ?>>
                    Capture
                </button>
            </form>
            <form method="post" action="/editor/upload" id="editor-upload-form" enctype="multipart/form-data" class="flex flex-col gap-2" data-editor-state="<?php echo htmlspecialchars($editorState, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="sticker" id="editor-upload-sticker" value="<?php echo htmlspecialchars($editorStickerDefault ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (isset($errors['upload'])): ?>
                    <p class="text-red-600 text-sm"><?php echo htmlspecialchars($errors['upload'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <div class="flex items-center">
                    <?php
                    $uploadLabelClasses = 'rounded border border-slate-300 bg-white px-4 py-2 text-slate-700 font-medium text-center';
                    $uploadLabelClasses .= $editorEntryStickerGateActive
                        ? ' opacity-50 cursor-not-allowed pointer-events-none'
                        : ' hover:bg-slate-50 cursor-pointer';
                    ?>
                    <label class="<?php echo $uploadLabelClasses; ?>">
                        <input
                            type="file"
                            name="base_image"
                            id="editor-upload-input"
                            accept="image/*"
                            class="sr-only"
                            <?php echo $editorEntryStickerGateActive ? 'disabled' : ''; ?>
                        >
                        Upload image
                    </label>
                </div>
            </form>
            <?php if ($isComposedReadyState): ?>
                <form method="post" action="/editor/save" class="flex flex-col gap-2">
                    <?php if (isset($errors['save'])): ?>
                        <p class="text-red-600 text-sm"><?php echo htmlspecialchars($errors['save'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-white font-medium hover:bg-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Save image</button>
                </form>
            <?php elseif (isset($errors['save'])): ?>
                <p class="text-red-600 text-sm self-center"><?php echo htmlspecialchars($errors['save'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($isWorkspaceState): ?>
                <form method="post" action="/editor/reset" class="flex flex-col gap-2">
                    <button type="submit" class="rounded border border-slate-300 bg-white px-4 py-2 text-slate-700 font-medium hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                        <?php echo htmlspecialchars('Reset workspace', ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <aside class="lg:col-span-1">
        <div class="bg-white border border-slate-200 rounded-lg p-4 sticky top-4">
            <p class="text-sm font-medium text-slate-600 mb-3">Previous images</p>
            <div class="grid grid-cols-2 lg:grid-cols-1 gap-2">
                <?php $savedImages = $savedImages ?? []; ?>
                <?php if (count($savedImages) === 0): ?>
                    <p class="text-slate-400 text-sm col-span-2 lg:col-span-1">No saved images yet.</p>
                <?php else: ?>
                    <?php foreach ($savedImages as $saved): ?>
                        <?php
                        $path = $saved['image_path'] ?? '';
                        $src = ($path !== '' && $path[0] !== '/') ? '/' . $path : $path;
                        $imageId = $saved['id'] ?? null;
                        ?>
                        <div class="space-y-2">
                            <a href="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>" class="aspect-square bg-slate-200 rounded overflow-hidden block border border-slate-200 hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-500">
                                <img src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="w-full h-full object-cover">
                            </a>
                            <?php if ($imageId !== null): ?>
                                <form method="post" action="/editor/delete">
                                    <input type="hidden" name="image_id" value="<?php echo htmlspecialchars($imageId, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="rounded bg-red-600 px-2 py-1 text-white text-xs hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                        Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<script src="/js/editor_webcam_preview.js"></script>
<script src="/js/editor_upload_autosubmit.js"></script>
<?php if ($isEmptyState && count($stickers) > 0): ?>
    <script src="/js/editor_sticker_pick_no_base.js"></script>
<?php endif; ?>
<?php if ($canRenderComposeForm): ?>
    <script src="/js/editor_sticker_placement.js"></script>
<?php endif; ?>
