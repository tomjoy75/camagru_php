<?php
/**
 * Public read-only gallery listing (no auth).
 */
class GalleryController
{
    private const PAGE_SIZE = 6;

    public static function show(): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $galleryLoadError = false;
        $images = [];
        $currentPage = 1;
        $totalPages = 0;
        $pageSize = self::PAGE_SIZE;

        try {
            require_once __DIR__ . '/../repository/ImageRepository.php';
            $repo = new ImageRepository();
            $currentPage = self::parseGalleryPage();
            $totalCount = $repo->countForPublicGallery();

            if ($totalCount === 0) {
                $totalPages = 0;
                $currentPage = 1;
                $images = [];
            } else {
                $totalPages = (int) ceil($totalCount / self::PAGE_SIZE);
                if ($currentPage > $totalPages) {
                    $currentPage = $totalPages;
                }
                $offset = ($currentPage - 1) * self::PAGE_SIZE;
                $rows = $repo->findPageForPublicGallery(self::PAGE_SIZE, $offset);
                $images = self::filterRowsWithExistingFiles($rows);
            }
        } catch (Throwable $e) {
            $galleryLoadError = true;
        }

        extract(compact('images', 'galleryLoadError', 'currentPage', 'totalPages', 'pageSize'), EXTR_SKIP);
        $view = 'gallery.php';
        require __DIR__ . '/../views/layout.php';
    }

    public static function showImage(): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $rawId = $_GET['id'] ?? null;
        if ($rawId === null || $rawId === '') {
            require_once __DIR__ . '/NotFoundController.php';
            NotFoundController::handle();
            return;
        }
        $imageId = filter_var($rawId, FILTER_VALIDATE_INT);
        if ($imageId === false || $imageId < 1) {
            require_once __DIR__ . '/NotFoundController.php';
            NotFoundController::handle();
            return;
        }

        $detailLoadError = false;
        $imageSrc = null;
        $username = '';
        $createdAt = '';
        $likeCount = 0;
        $commentCount = 0;

        try {
            require_once __DIR__ . '/../repository/ImageRepository.php';
            $repo = new ImageRepository();
            $row = $repo->findPublicDetailById($imageId);
            if ($row === null) {
                require_once __DIR__ . '/NotFoundController.php';
                NotFoundController::handle();
                return;
            }
            $imageSrc = self::resolvePublicImageWebPath((string) ($row['image_path'] ?? ''));
            if ($imageSrc === null) {
                require_once __DIR__ . '/NotFoundController.php';
                NotFoundController::handle();
                return;
            }
            $username = (string) ($row['username'] ?? '');
            $createdAt = (string) ($row['created_at'] ?? '');
            $likeCount = (int) ($row['like_count'] ?? 0);
            $commentCount = (int) ($row['comment_count'] ?? 0);
        } catch (Throwable $e) {
            $detailLoadError = true;
        }

        extract(
            compact('detailLoadError', 'imageSrc', 'username', 'createdAt', 'likeCount', 'commentCount'),
            EXTR_SKIP
        );
        $view = 'gallery_image.php';
        require __DIR__ . '/../views/layout.php';
    }

    /**
     * @return non-empty-string|null Web path for <img src>, or null if unsafe or missing.
     */
    private static function resolvePublicImageWebPath(string $imagePath): ?string
    {
        $publicDir = realpath(__DIR__ . '/../../public');
        if ($publicDir === false) {
            return null;
        }

        $rel = str_replace('\\', '/', $imagePath);
        if ($rel === '' || strpos($rel, '..') !== false) {
            return null;
        }
        $rel = ltrim($rel, '/');
        $candidate = $publicDir . '/' . $rel;
        $resolved = realpath($candidate);
        $prefix = $publicDir . '/';
        if ($resolved === false || strpos($resolved, $prefix) !== 0) {
            return null;
        }
        if (!is_file($resolved)) {
            return null;
        }

        return '/' . $rel;
    }

    private static function parseGalleryPage(): int
    {
        $raw = $_GET['page'] ?? null;
        if ($raw === null || $raw === '') {
            return 1;
        }
        $parsed = filter_var($raw, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1) {
            return 1;
        }

        return $parsed;
    }

    /**
     * Keep only rows whose image_path resolves to a file under public/ (skip missing files and unsafe paths).
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function filterRowsWithExistingFiles(array $rows): array
    {
        $publicDir = realpath(__DIR__ . '/../../public');
        if ($publicDir === false) {
            return [];
        }

        $prefix = $publicDir . '/';
        $out = [];

        foreach ($rows as $row) {
            $rel = str_replace('\\', '/', (string) ($row['image_path'] ?? ''));
            if ($rel === '' || strpos($rel, '..') !== false) {
                continue;
            }
            $rel = ltrim($rel, '/');
            $candidate = $publicDir . '/' . $rel;
            $resolved = realpath($candidate);
            if ($resolved === false || strpos($resolved, $prefix) !== 0) {
                continue;
            }
            if (!is_file($resolved)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }
}
