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
        require_once __DIR__ . '/../helpers/sqlite_datetime_display.php';
        $src = $imageSrc ?? '';
        $createdRaw = $createdAt ?? '';
        $createdLabel = format_sqlite_utc_datetime_for_display((string) $createdRaw);
        $sessionUserId = $_SESSION['user_id'] ?? null;
        $canInteract = $sessionUserId !== null && $sessionUserId !== '';
        $imgId = (int) ($detailImageId ?? 0);
        $shareUrl = (string) ($shareTargetUrl ?? '');
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
                <dd id="gallery-like-count" class="inline ml-1" aria-live="polite" aria-atomic="true"><?php echo (int) ($likeCount ?? 0); ?></dd></div>
            <?php if ($canInteract && $imgId >= 1): ?>
                <div class="pt-2">
                    <form id="gallery-like-form" method="post" action="<?php echo htmlspecialchars('/gallery/like', ENT_QUOTES, 'UTF-8'); ?>" class="inline">
                        <input type="hidden" name="image_id" value="<?php echo $imgId; ?>">
                        <button
                            type="submit"
                            id="gallery-like-submit"
                            class="text-sm px-3 py-1.5 rounded border border-slate-300 bg-white text-slate-800 hover:bg-slate-50"
                            aria-pressed="<?php echo !empty($hasLiked) ? 'true' : 'false'; ?>"
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
        <section class="mt-3" aria-label="Share this image">
            <h2 class="text-sm font-medium text-slate-800">Share</h2>
            <div class="mt-1 flex flex-wrap items-center gap-3 text-sm">
                <a
                    href="<?php echo htmlspecialchars((string) ($twitterShareHref ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-slate-700 underline hover:text-slate-900"
                >Twitter/X</a>
                <a
                    href="<?php echo htmlspecialchars((string) ($facebookShareHref ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-slate-700 underline hover:text-slate-900"
                >Facebook</a>
                <a
                    href="<?php echo htmlspecialchars((string) ($linkedinShareHref ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-slate-700 underline hover:text-slate-900"
                >LinkedIn</a>
                <button
                    type="button"
                    id="discord-share-button"
                    data-share-url="<?php echo htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    class="text-slate-700 underline hover:text-slate-900"
                    title="Copy the image link and open Discord"
                >Discord</button>
            </div>
            <?php if (!empty($shareLocalOnlyWarning)): ?>
                <p class="mt-2 text-xs text-amber-700">
                    <?php echo htmlspecialchars((string) $shareLocalOnlyWarning, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endif; ?>
            <p id="discord-share-status" class="mt-1 text-xs text-slate-500" aria-live="polite"></p>
        </section>
        <?php if (!empty($galleryCommentError)): ?>
            <p class="text-sm text-red-600 mt-2" role="alert"><?php echo htmlspecialchars((string) $galleryCommentError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (!empty($galleryCommentSuccess)): ?>
            <p class="text-sm text-green-700 mt-2"><?php echo htmlspecialchars((string) $galleryCommentSuccess, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($canInteract && $imgId >= 1): ?>
            <form
                id="comment-form"
                method="post"
                action="<?php echo htmlspecialchars('/gallery/comment', ENT_QUOTES, 'UTF-8'); ?>"
                class="mt-4 space-y-2 scroll-mt-24"
            >
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
                        $cLabel = format_sqlite_utc_datetime_for_display($cRaw);
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
        <?php if ($canInteract && $imgId >= 1): ?>
            <script src="/js/gallery_image_like.js" defer></script>
        <?php endif; ?>
        <script>
            (function () {
                const button = document.getElementById('discord-share-button');
                const status = document.getElementById('discord-share-status');
                if (!button) {
                    return;
                }

                button.addEventListener('click', async function () {
                    const url = button.getAttribute('data-share-url') || '';
                    if (!url) {
                        if (status) status.textContent = 'Share link unavailable.';
                        return;
                    }

                    try {
                        await navigator.clipboard.writeText(url);
                        if (status) status.textContent = 'Link copied. Paste it in Discord.';
                    } catch (e) {
                        if (status) status.textContent = 'Could not copy link automatically.';
                    }

                    window.open('https://discord.com/app', '_blank', 'noopener,noreferrer');
                });
            }());
        </script>
    <?php endif; ?>
</div>
