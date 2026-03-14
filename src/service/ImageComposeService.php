<?php
/**
 * Service for composing a base image with a single sticker at given coordinates.
 * Uses GD alpha blending and imagecopy so sticker PNG transparency is preserved.
 */
class ImageComposeService
{
    /**
     * Compose the given temporary base image filename with the given sticker PNG name
     * at coordinates (x, y) in pixels, clamped so the sticker stays fully inside.
     *
     * Returns ['filename' => string] on success or ['errors' => string[]] on failure.
     */
    public static function compose(string $baseFilename, string $stickerName, int $x, int $y): array
    {
        if ($baseFilename === '') {
            return ['errors' => ['Base image is missing.']];
        }

        $basePath = __DIR__ . '/../../public/tmp/' . $baseFilename;
        if (!is_file($basePath)) {
            return ['errors' => ['Base image not found.']];
        }

        $stickerResult = self::validateSticker($stickerName);
        if (isset($stickerResult['errors'])) {
            return $stickerResult;
        }
        $stickerPath = $stickerResult['path'];

        $baseImage = self::loadBaseImage($basePath);
        if (is_array($baseImage)) {
            return $baseImage;
        }

        $stickerImage = self::loadStickerImage($stickerPath);
        if (is_array($stickerImage)) {
            imagedestroy($baseImage);
            return $stickerImage;
        }

        $baseWidth = imagesx($baseImage);
        $baseHeight = imagesy($baseImage);
        $stickerWidth = imagesx($stickerImage);
        $stickerHeight = imagesy($stickerImage);

        if ($stickerWidth <= 0 || $stickerHeight <= 0) {
            imagedestroy($baseImage);
            imagedestroy($stickerImage);
            return ['errors' => ['Invalid sticker dimensions.']];
        }

        $maxX = $baseWidth - $stickerWidth;
        $maxY = $baseHeight - $stickerHeight;
        if ($maxX < 0 || $maxY < 0) {
            imagedestroy($baseImage);
            imagedestroy($stickerImage);
            return ['errors' => ['Sticker is larger than base image.']];
        }

        $x = max(0, min($x, $maxX));
        $y = max(0, min($y, $maxY));

        imagealphablending($baseImage, true);
        imagecopy($baseImage, $stickerImage, $x, $y, 0, 0, $stickerWidth, $stickerHeight);
        imagedestroy($stickerImage);

        $tmpDir = __DIR__ . '/../../public/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $newFilename = sprintf('img_%s.png', bin2hex(random_bytes(8)));
        $destination = $tmpDir . '/' . $newFilename;

        imagesavealpha($baseImage, true);
        if (!self::savePng($baseImage, $destination)) {
            imagedestroy($baseImage);
            return ['errors' => ['Failed to save composed image.']];
        }
        imagedestroy($baseImage);

        return ['filename' => $newFilename];
    }

    /**
     * Resolve sticker name against StickerService. Returns ['path' => ..., 'filename' => ...] or ['errors' => [...]].
     */
    private static function validateSticker(string $stickerName): array
    {
        require_once __DIR__ . '/StickerService.php';
        $stickers = StickerService::getStickers();
        foreach ($stickers as $sticker) {
            if ($sticker['filename'] === $stickerName) {
                $path = __DIR__ . '/../../public/stickers/' . $stickerName;
                if (!is_file($path)) {
                    return ['errors' => ['Sticker file is missing on server.']];
                }
                return ['path' => $path, 'filename' => $stickerName];
            }
        }
        return ['errors' => ['Sticker not found.']];
    }

    /**
     * Load base image (PNG or JPEG). Returns GdImage or ['errors' => [...]].
     */
    private static function loadBaseImage(string $path): GdImage|array
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return ['errors' => ['Base image is not a valid image.']];
        }
        $type = $info[2];
        if ($type === IMAGETYPE_PNG) {
            $img = @imagecreatefrompng($path);
        } elseif ($type === IMAGETYPE_JPEG) {
            $img = @imagecreatefromjpeg($path);
        } else {
            return ['errors' => ['Unsupported base image type.']];
        }
        if (!$img) {
            return ['errors' => ['Failed to load base image.']];
        }
        return $img;
    }

    /**
     * Load sticker PNG with alpha preserved. Returns GdImage or ['errors' => [...]].
     */
    private static function loadStickerImage(string $path): GdImage|array
    {
        $img = @imagecreatefrompng($path);
        if (!$img) {
            return ['errors' => ['Failed to load sticker image.']];
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);
        return $img;
    }

    private static function savePng(GdImage $img, string $path): bool
    {
        return imagepng($img, $path);
    }
}
