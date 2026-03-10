<?php
/**
 * Service for sticker-related logic: filesystem access and metadata building.
 */
class StickerService
{
    /**
     * Returns a list of stickers as arrays with:
     * - filename: string (e.g. "mustache.png")
     * - slug: string (e.g. "mustache")
     */
    public static function getStickers(): array
    {
        $baseDir = __DIR__ . '/../../public/stickers';
        if (!is_dir($baseDir)) {
            return [];
        }

        $paths = glob($baseDir . '/*.png') ?: [];
        sort($paths);

        $stickers = [];
        foreach ($paths as $path) {
            $filename = basename($path);
            $slug = pathinfo($filename, PATHINFO_FILENAME);
            $stickers[] = [
                'filename' => $filename,
                'slug' => $slug,
            ];
        }

        return $stickers;
    }
}

