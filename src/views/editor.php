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

        <!-- Preview: uploaded temp image or webcam placeholder -->
        <div class="bg-slate-200 rounded-lg aspect-video flex items-center justify-center text-slate-500 overflow-hidden">
            <?php if (!empty($editorPreviewSrc)): ?>
                <img src="<?php echo htmlspecialchars($editorPreviewSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Uploaded preview" class="max-w-full max-h-full w-auto h-auto object-contain">
            <?php else: ?>
                <span>Webcam preview</span>
            <?php endif; ?>
        </div>

        <!-- Stickers -->
        <div class="bg-slate-100 rounded-lg border border-slate-200 p-4">
            <p class="text-sm font-medium text-slate-600 mb-2">Stickers</p>
            <div class="flex flex-wrap gap-3">
                <?php $stickers = $stickers ?? []; ?>
                <?php foreach ($stickers as $sticker): ?>
                    <form method="post" action="/editor/compose" class="inline-flex items-center justify-center w-16 h-16 rounded border border-slate-200 bg-white p-1 shrink-0">
                        <input type="hidden" name="sticker" value="<?php echo htmlspecialchars($sticker['filename'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="x" value="50">
                        <input type="hidden" name="y" value="50">
                        <button type="submit" class="w-full h-full flex items-center justify-center">
                            <img
                                src="/stickers/<?php echo htmlspecialchars($sticker['filename'], ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars($sticker['slug'], ENT_QUOTES, 'UTF-8'); ?>"
                                title="<?php echo htmlspecialchars($sticker['slug'], ENT_QUOTES, 'UTF-8'); ?>"
                                class="max-w-full max-h-full w-auto h-auto object-contain cursor-pointer"
                            >
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
            <?php if (isset($errors['compose'])): ?>
                <p class="mt-2 text-red-600 text-sm"><?php echo htmlspecialchars($errors['compose'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Capture and upload -->
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center flex-wrap">
            <button type="button" class="rounded bg-slate-800 px-4 py-2 text-white font-medium hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                Capture
            </button>
            <form method="post" action="/editor/upload" enctype="multipart/form-data" class="flex flex-col gap-2">
                <?php if (isset($errors['upload'])): ?>
                    <p class="text-red-600 text-sm"><?php echo htmlspecialchars($errors['upload'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <div class="flex items-center">
                    <label class="rounded border border-slate-300 bg-white px-4 py-2 text-slate-700 font-medium hover:bg-slate-50 cursor-pointer text-center">
                        <input type="file" name="base_image" accept="image/*" class="sr-only">
                        Upload image
                    </label>
                    <button type="submit" class="ml-2 rounded bg-slate-800 px-4 py-2 text-white font-medium hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">Upload</button>
                </div>
            </form>
            <?php if (!empty($canSaveEditorImage)): ?>
                <form method="post" action="/editor/save" class="flex flex-col gap-2">
                    <?php if (isset($errors['save'])): ?>
                        <p class="text-red-600 text-sm"><?php echo htmlspecialchars($errors['save'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-white font-medium hover:bg-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Save image</button>
                </form>
            <?php elseif (isset($errors['save'])): ?>
                <p class="text-red-600 text-sm self-center"><?php echo htmlspecialchars($errors['save'], ENT_QUOTES, 'UTF-8'); ?></p>
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
