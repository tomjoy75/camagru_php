<?php
/**
 * Service for composing a base image with a single sticker at given coordinates.
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
        $errors = [];

        if ($baseFilename === '') {
            return ['errors' => ['Base image is missing.']];
        }

        $basePath = __DIR__ . '/../../public/tmp/' . $baseFilename;
        if (!is_file($basePath)) {
            return ['errors' => ['Base image not found.']];
        }

        // Validate sticker against known stickers
        require_once __DIR__ . '/StickerService.php';
        $stickers = StickerService::getStickers();
        $validSticker = null;
        foreach ($stickers as $sticker) {
            if ($sticker['filename'] === $stickerName) {
                $validSticker = $sticker;
                break;
            }
        }
        if ($validSticker === null) {
            return ['errors' => ['Sticker not found.']];
        }

        $stickerPath = __DIR__ . '/../../public/stickers/' . $stickerName;
        if (!is_file($stickerPath)) {
            return ['errors' => ['Sticker file is missing on server.']];
        }

        $baseInfo = @getimagesize($basePath);
        if ($baseInfo === false) {
            return ['errors' => ['Base image is not a valid image.']];
        }

        $baseType = $baseInfo[2];
        if ($baseType === IMAGETYPE_PNG) {
            $baseImage = @imagecreatefrompng($basePath);
        } elseif ($baseType === IMAGETYPE_JPEG) {
            $baseImage = @imagecreatefromjpeg($basePath);
        } else {
            return ['errors' => ['Unsupported base image type.']];
        }

        if (!$baseImage) {
            return ['errors' => ['Failed to load base image.']];
        }

        $stickerImage = @imagecreatefrompng($stickerPath);
        if (!$stickerImage) {
            imagedestroy($baseImage);
            return ['errors' => ['Failed to load sticker image.']];
        }

        // Preserve PNG transparency on sticker and allow alpha on base
        imagealphablending($stickerImage, true);
        imagealphablending($baseImage, true);
        imagesavealpha($baseImage, true);

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

        if ($x < 0) {
            $x = 0;
        } elseif ($x > $maxX) {
            $x = $maxX;
        }

        if ($y < 0) {
            $y = 0;
        } elseif ($y > $maxY) {
            $y = $maxY;
        }

        if (!imagecopy($baseImage, $stickerImage, $x, $y, 0, 0, $stickerWidth, $stickerHeight)) {
            imagedestroy($baseImage);
            imagedestroy($stickerImage);
            return ['errors' => ['Failed to compose images.']];
        }

        imagedestroy($stickerImage);

        $ext = 'png';
        $newFilename = sprintf('img_%s.%s', bin2hex(random_bytes(8)), $ext);
        $tmpDir = __DIR__ . '/../../public/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $destination = $tmpDir . '/' . $newFilename;

        if (!imagepng($baseImage, $destination)) {
            imagedestroy($baseImage);
            return ['errors' => ['Failed to save composed image.']];
        }

        imagedestroy($baseImage);

        return ['filename' => $newFilename];
    }
}

