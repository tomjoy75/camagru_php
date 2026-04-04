<div class="space-y-4">
    <p>
        <a
            href="<?php echo htmlspecialchars('/gallery', ENT_QUOTES, 'UTF-8'); ?>"
            class="text-slate-700 underline hover:text-slate-900 text-sm"
        >← Back to gallery</a>
    </p>

    <?php if (!empty($detailLoadError)): ?>
        <p class="text-slate-600">This image could not be loaded. Please try again later.</p>
    <?php else: ?>
        <?php
        $src = $imageSrc ?? '';
        $createdRaw = $createdAt ?? '';
        $createdLabel = $createdRaw;
        if ($createdRaw !== '') {
            $ts = strtotime($createdRaw);
            if ($ts !== false) {
                $createdLabel = date('M j, Y \a\t g:i A', $ts);
            }
        }
        ?>
        <h1 class="text-xl font-semibold text-slate-800">Image</h1>
        <div class="rounded-lg overflow-hidden border border-slate-200 bg-slate-100">
            <img
                src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>"
                alt="Published image"
                class="w-full max-h-[70vh] object-contain mx-auto"
            >
        </div>
        <dl class="text-sm text-slate-700 space-y-1">
            <div><dt class="inline font-medium text-slate-800">Author:</dt>
                <dd class="inline ml-1"><?php echo htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8'); ?></dd></div>
            <div><dt class="inline font-medium text-slate-800">Published:</dt>
                <dd class="inline ml-1"><?php echo htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8'); ?></dd></div>
            <div><dt class="inline font-medium text-slate-800">Likes:</dt>
                <dd class="inline ml-1"><?php echo (int) ($likeCount ?? 0); ?></dd></div>
            <div><dt class="inline font-medium text-slate-800">Comments:</dt>
                <dd class="inline ml-1">
                    <?php
                    $cc = (int) ($commentCount ?? 0);
                    echo $cc;
                    if ($cc === 0) {
                        echo ' <span class="text-slate-500">(none yet)</span>';
                    }
                    ?>
                </dd></div>
        </dl>
    <?php endif; ?>
</div>
