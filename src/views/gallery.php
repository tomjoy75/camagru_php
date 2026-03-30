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

            <?php
            $currentPage = isset($currentPage) ? (int) $currentPage : 1;
            $totalPages = isset($totalPages) ? (int) $totalPages : 0;
            ?>
            <?php if ($totalPages > 1): ?>
                <nav class="flex flex-wrap items-center gap-4 pt-4 text-sm" aria-label="Gallery pagination">
                    <?php if ($currentPage > 1): ?>
                        <a
                            href="<?php echo htmlspecialchars('/gallery?page=' . ($currentPage - 1), ENT_QUOTES, 'UTF-8'); ?>"
                            class="text-slate-700 underline hover:text-slate-900"
                        >Previous</a>
                    <?php else: ?>
                        <span class="text-slate-400">Previous</span>
                    <?php endif; ?>

                    <span class="text-slate-600">Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>

                    <?php if ($currentPage < $totalPages): ?>
                        <a
                            href="<?php echo htmlspecialchars('/gallery?page=' . ($currentPage + 1), ENT_QUOTES, 'UTF-8'); ?>"
                            class="text-slate-700 underline hover:text-slate-900"
                        >Next</a>
                    <?php else: ?>
                        <span class="text-slate-400">Next</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
