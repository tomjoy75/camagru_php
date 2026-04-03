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
