<div class="space-y-4">
    <h1 class="text-xl font-semibold text-slate-800">Gallery</h1>

    <?php if (!empty($galleryLoadError)): ?>
        <p class="text-slate-600">The gallery could not be loaded. Please try again later.</p>
    <?php else: ?>
        <?php $images = $images ?? []; ?>
        <?php if (count($images) === 0): ?>
            <p class="text-slate-600">No images published yet.</p>
        <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <?php foreach ($images as $item): ?>
                    <?php
                    $path = $item['image_path'] ?? '';
                    $src = ($path !== '' && ($path[0] ?? '') !== '/') ? '/' . $path : $path;
                    ?>
                    <div class="aspect-square bg-slate-200 rounded overflow-hidden border border-slate-200">
                        <img
                            src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>"
                            alt="Published image"
                            class="w-full h-full object-cover"
                        >
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
