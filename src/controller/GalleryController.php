<?php
/**
 * Public read-only gallery listing (no auth).
 */
class GalleryController
{
    public static function show(): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $galleryLoadError = false;
        $images = [];

        try {
            require_once __DIR__ . '/../repository/ImageRepository.php';
            $repo = new ImageRepository();
            $rows = $repo->findAllForPublicGallery();
            $images = self::filterRowsWithExistingFiles($rows);
        } catch (Throwable $e) {
            $galleryLoadError = true;
        }

        extract(compact('images', 'galleryLoadError'), EXTR_SKIP);
        $view = 'gallery.php';
        require __DIR__ . '/../views/layout.php';
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
