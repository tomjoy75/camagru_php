<div class="space-y-4">
    <h1 class="text-xl font-semibold text-slate-800">Gallery</h1>

    <?php
    $galleryFilterActive = !empty($galleryFilterActive);
    $galleryHasAnyImages = !empty($galleryHasAnyImages);
    $galleryFilterUserId = isset($galleryFilterUserId) ? (int) $galleryFilterUserId : 0;
    $gallerySort = (isset($gallerySort) && is_string($gallerySort)) ? $gallerySort : 'newest';
    if (!in_array($gallerySort, ['newest', 'oldest', 'likes', 'comments'], true)) {
        $gallerySort = 'newest';
    }

    $galleryQueryParts = [];
    if ($galleryFilterActive && $galleryFilterUserId > 0) {
        $galleryQueryParts[] = 'user_id=' . $galleryFilterUserId;
    }
    if ($gallerySort !== 'newest') {
        $galleryQueryParts[] = 'sort=' . rawurlencode($gallerySort);
    }
    $galleryPageQuery = $galleryQueryParts === [] ? '' : implode('&', $galleryQueryParts) . '&';

    $galleryHrefForSort = static function (string $mode) use ($galleryFilterActive, $galleryFilterUserId): string {
        $p = [];
        if ($galleryFilterActive && $galleryFilterUserId > 0) {
            $p['user_id'] = $galleryFilterUserId;
        }
        if ($mode !== 'newest') {
            $p['sort'] = $mode;
        }
        $q = http_build_query($p);

        return $q === '' ? '/gallery' : '/gallery?' . $q;
    };

    $galleryClearUserParts = [];
    if ($gallerySort !== 'newest') {
        $galleryClearUserParts['sort'] = $gallerySort;
    }
    $galleryClearUserQuery = http_build_query($galleryClearUserParts);
    $galleryClearUserHref = $galleryClearUserQuery === '' ? '/gallery' : '/gallery?' . $galleryClearUserQuery;
    ?>

    <?php if ($galleryFilterActive && $galleryFilterUserId > 0): ?>
        <p class="text-sm">
            <a href="<?php echo htmlspecialchars($galleryClearUserHref, ENT_QUOTES, 'UTF-8'); ?>" class="text-slate-700 underline hover:text-slate-900">All users</a>
        </p>
    <?php endif; ?>

    <?php if (empty($galleryLoadError)): ?>
        <nav class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-700" aria-label="Gallery sort">
            <?php
            $sortModes = [
                'newest' => 'Newest',
                'oldest' => 'Oldest',
                'likes' => 'Most likes',
                'comments' => 'Most comments',
            ];
            $sep = '';
            foreach ($sortModes as $mode => $label) {
                echo $sep;
                $sep = ' <span class="text-slate-400" aria-hidden="true">·</span> ';
                if ($gallerySort === $mode) {
                    echo '<span class="font-semibold text-slate-900">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
                } else {
                    $h = $galleryHrefForSort($mode);
                    echo '<a href="' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '" class="underline hover:text-slate-900">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
                }
            }
            ?>
        </nav>
    <?php endif; ?>

    <?php if (!empty($galleryLoadError)): ?>
        <p class="text-slate-600">The gallery could not be loaded. Please try again later.</p>
    <?php else: ?>
        <?php $images = $images ?? []; ?>
        <?php if (count($images) === 0): ?>
            <?php if ($galleryFilterActive && $galleryHasAnyImages): ?>
                <p class="text-slate-600">No published images for this user.</p>
            <?php else: ?>
                <p class="text-slate-600">No images published yet.</p>
            <?php endif; ?>
        <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <?php foreach ($images as $item): ?>
                    <?php
                    $path = $item['image_path'] ?? '';
                    $src = ($path !== '' && ($path[0] ?? '') !== '/') ? '/' . $path : $path;
                    $imageId = (int) ($item['id'] ?? 0);
                    $likeCount = (int) ($item['like_count'] ?? 0);
                    $detailHref = '/gallery/image?id=' . $imageId;
                    ?>
                    <a
                        href="<?php echo htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8'); ?>"
                        class="flex flex-col rounded overflow-hidden border border-slate-200 bg-slate-200 block hover:ring-2 hover:ring-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-500"
                        data-image-id="<?php echo $imageId; ?>"
                        data-like-count="<?php echo $likeCount; ?>"
                    >
                        <div class="aspect-square w-full min-h-0 shrink-0 overflow-hidden">
                            <img
                                src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="Published image"
                                class="w-full h-full object-cover"
                            >
                        </div>
                        <span class="text-xs text-slate-700 px-2 py-1 bg-slate-100 border-t border-slate-200">Likes: <?php echo $likeCount; ?></span>
                    </a>
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
                            href="<?php echo htmlspecialchars('/gallery?' . $galleryPageQuery . 'page=' . ($currentPage - 1), ENT_QUOTES, 'UTF-8'); ?>"
                            class="text-slate-700 underline hover:text-slate-900"
                        >Previous</a>
                    <?php else: ?>
                        <span class="text-slate-400">Previous</span>
                    <?php endif; ?>

                    <span class="text-slate-600">Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>

                    <?php if ($currentPage < $totalPages): ?>
                        <a
                            href="<?php echo htmlspecialchars('/gallery?' . $galleryPageQuery . 'page=' . ($currentPage + 1), ENT_QUOTES, 'UTF-8'); ?>"
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
