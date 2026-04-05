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
        $sessionUserId = $_SESSION['user_id'] ?? null;
        $canInteract = $sessionUserId !== null && $sessionUserId !== '';
        $imgId = (int) ($detailImageId ?? 0);
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
            <?php if ($canInteract && $imgId >= 1): ?>
                <div class="pt-2">
                    <form method="post" action="<?php echo htmlspecialchars('/gallery/like', ENT_QUOTES, 'UTF-8'); ?>" class="inline">
                        <input type="hidden" name="image_id" value="<?php echo $imgId; ?>">
                        <button
                            type="submit"
                            class="text-sm px-3 py-1.5 rounded border border-slate-300 bg-white text-slate-800 hover:bg-slate-50"
                        ><?php echo !empty($hasLiked) ? 'Unlike' : 'Like'; ?></button>
                    </form>
                </div>
            <?php endif; ?>
            <div><dt class="inline font-medium text-slate-800">Comments:</dt>
                <dd class="inline ml-1">
                    <?php
                    $cc = (int) ($commentCount ?? 0);
                    echo $cc;
                    ?>
                </dd></div>
        </dl>
        <?php if (!empty($galleryCommentError)): ?>
            <p class="text-sm text-red-600 mt-2" role="alert"><?php echo htmlspecialchars((string) $galleryCommentError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (!empty($galleryCommentSuccess)): ?>
            <p class="text-sm text-green-700 mt-2"><?php echo htmlspecialchars((string) $galleryCommentSuccess, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($canInteract && $imgId >= 1): ?>
            <form method="post" action="<?php echo htmlspecialchars('/gallery/comment', ENT_QUOTES, 'UTF-8'); ?>" class="mt-4 space-y-2">
                <input type="hidden" name="image_id" value="<?php echo $imgId; ?>">
                <label for="comment-content" class="block text-sm font-medium text-slate-800">Add a comment</label>
                <textarea
                    id="comment-content"
                    name="content"
                    rows="3"
                    class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm text-slate-800"
                ></textarea>
                <button
                    type="submit"
                    class="text-sm px-3 py-1.5 rounded border border-slate-300 bg-white text-slate-800 hover:bg-slate-50"
                >Post comment</button>
            </form>
        <?php endif; ?>
        <?php
        $commentRows = $comments ?? [];
        ?>
        <section class="mt-6 border-t border-slate-200 pt-4" aria-label="Comments">
            <h2 class="text-lg font-semibold text-slate-800 mb-3">Comments</h2>
            <?php if (count($commentRows) === 0): ?>
                <p class="text-sm text-slate-500">No comments yet</p>
            <?php else: ?>
                <ul class="space-y-4 text-sm text-slate-700 list-none pl-0">
                    <?php foreach ($commentRows as $cRow): ?>
                        <?php
                        $cUser = (string) ($cRow['username'] ?? '');
                        $cBody = (string) ($cRow['content'] ?? '');
                        $cRaw = (string) ($cRow['created_at'] ?? '');
                        $cLabel = $cRaw;
                        if ($cRaw !== '') {
                            $cts = strtotime($cRaw);
                            if ($cts !== false) {
                                $cLabel = date('M j, Y \a\t g:i A', $cts);
                            }
                        }
                        ?>
                        <li class="border-b border-slate-100 pb-3 last:border-0">
                            <div class="font-medium text-slate-800">
                                <?php echo htmlspecialchars($cUser, ENT_QUOTES, 'UTF-8'); ?>
                                <span class="text-slate-500 font-normal"> · <?php echo htmlspecialchars($cLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="mt-1 whitespace-pre-wrap break-words">
                                <?php echo htmlspecialchars($cBody, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
