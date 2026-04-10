<?php
/**
 * Service for composing a base image with a single sticker at given coordinates.
 * Uses GD alpha blending and imagecopy so sticker PNG transparency is preserved.
 */
class ImageComposeService
{
    private const USER_SCALE_MIN = 0.05;
    private const USER_SCALE_MAX = 1.0;

    /**
     * Compose the given temporary base image with the sticker at (x, y).
     * User scale is applied after auto-fit-to-base (relative 0.05–1.0). Angle is degrees (clockwise, −180…180).
     *
     * @return array{filename: string}|array{errors: string[]}
     */
    public static function compose(
        string $baseFilename,
        string $stickerName,
        int $x,
        int $y,
        float $userScale = 1.0,
        float $angleDegrees = 0.0
    ): array {
        if ($baseFilename === '') {
            return ['errors' => ['Base image is missing.']];
        }

        $userScale = max(self::USER_SCALE_MIN, min(self::USER_SCALE_MAX, $userScale));
        $angleDegrees = max(-180.0, min(180.0, $angleDegrees));

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

        $target = self::targetStickerDimensions($baseWidth, $baseHeight, $stickerWidth, $stickerHeight);
        $drawW = $target['w'];
        $drawH = $target['h'];

        if ($drawW !== $stickerWidth || $drawH !== $stickerHeight) {
            $scaled = self::resampleStickerImage($stickerImage, $drawW, $drawH);
            imagedestroy($stickerImage);
            if (is_array($scaled)) {
                imagedestroy($baseImage);
                return $scaled;
            }
            $stickerImage = $scaled;
        }

        $uW = max(1, (int) floor($drawW * $userScale));
        $uH = max(1, (int) floor($drawH * $userScale));
        if ($uW !== $drawW || $uH !== $drawH) {
            $scaled = self::resampleStickerImage($stickerImage, $uW, $uH);
            imagedestroy($stickerImage);
            if (is_array($scaled)) {
                imagedestroy($baseImage);
                return $scaled;
            }
            $stickerImage = $scaled;
        }

        $stickerImage = self::shrinkStickerIfRotatedAabbExceedsBase(
            $stickerImage,
            $baseWidth,
            $baseHeight,
            $angleDegrees
        );
        if (is_array($stickerImage)) {
            imagedestroy($baseImage);
            return $stickerImage;
        }

        if (abs($angleDegrees) > 0.0001) {
            $rotated = self::rotateStickerPreservingAlpha($stickerImage, -$angleDegrees);
            imagedestroy($stickerImage);
            if (is_array($rotated)) {
                imagedestroy($baseImage);
                return $rotated;
            }
            $stickerImage = $rotated;
        }

        $rotW = imagesx($stickerImage);
        $rotH = imagesy($stickerImage);
        if ($rotW <= 0 || $rotH <= 0) {
            imagedestroy($baseImage);
            imagedestroy($stickerImage);
            return ['errors' => ['Invalid sticker dimensions after transform.']];
        }

        $maxX = max(0, $baseWidth - $rotW);
        $maxY = max(0, $baseHeight - $rotH);
        $x = max(0, min($x, $maxX));
        $y = max(0, min($y, $maxY));

        imagealphablending($baseImage, true);
        imagesavealpha($baseImage, true);
        imagealphablending($stickerImage, true);
        imagesavealpha($stickerImage, true);
        imagecopy($baseImage, $stickerImage, $x, $y, 0, 0, $rotW, $rotH);
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
     * Axis-aligned bounding box size for a w×h rectangle rotated by angle (degrees).
     *
     * @return array{0: int, 1: int}
     */
    private static function rotatedAabbSize(int $w, int $h, float $angleDegrees): array
    {
        $rad = deg2rad($angleDegrees);
        $bw = abs($w * cos($rad)) + abs($h * sin($rad));
        $bh = abs($w * sin($rad)) + abs($h * cos($rad));

        return [max(1, (int) ceil($bw)), max(1, (int) ceil($bh))];
    }

    /**
     * @return GdImage|array{errors: string[]}
     */
    private static function shrinkStickerIfRotatedAabbExceedsBase(
        GdImage $stickerImage,
        int $baseWidth,
        int $baseHeight,
        float $angleDegrees
    ): GdImage|array {
        $w0 = imagesx($stickerImage);
        $h0 = imagesy($stickerImage);
        [$bboxW, $bboxH] = self::rotatedAabbSize($w0, $h0, $angleDegrees);
        if ($bboxW <= $baseWidth && $bboxH <= $baseHeight) {
            return $stickerImage;
        }
        $f = min($baseWidth / $bboxW, $baseHeight / $bboxH) * 0.999;
        $nw = max(1, (int) floor($w0 * $f));
        $nh = max(1, (int) floor($h0 * $f));
        $scaled = self::resampleStickerImage($stickerImage, $nw, $nh);
        imagedestroy($stickerImage);
        if (is_array($scaled)) {
            return $scaled;
        }

        return $scaled;
    }

    /**
     * @return GdImage|array{errors: string[]}
     */
    private static function rotateStickerPreservingAlpha(GdImage $stickerImage, float $phpImagerotateAngle): GdImage|array
    {
        $alphaBg = imagecolorallocatealpha($stickerImage, 0, 0, 0, 127);
        if ($alphaBg === false) {
            return ['errors' => ['Failed to allocate rotation background.']];
        }
        $rotated = @imagerotate($stickerImage, $phpImagerotateAngle, $alphaBg);
        if ($rotated === false) {
            return ['errors' => ['Failed to rotate sticker.']];
        }
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        return $rotated;
    }

    /**
     * Resolve sticker name against StickerService. Returns ['path' => ..., 'filename' => ...] or ['errors' => [...]].
     */
    private static function validateSticker(string $stickerName): array
    {
        require_once __DIR__ . '/StickerService.php';
        if (!StickerService::isAllowedStickerFilename($stickerName)) {
            return ['errors' => ['Sticker not found.']];
        }
        $filename = basename($stickerName);
        $path = __DIR__ . '/../../public/stickers/' . $filename;
        return ['path' => $path, 'filename' => $filename];
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

    /**
     * Uniform scale capped at 1 so the sticker fits inside the base (integer size, min side ≥ 1).
     *
     * @return array{w: int, h: int}
     */
    private static function targetStickerDimensions(int $baseW, int $baseH, int $sw, int $sh): array
    {
        $scale = min(1.0, (float) $baseW / $sw, (float) $baseH / $sh);
        $w = max(1, (int) floor($sw * $scale));
        $h = max(1, (int) floor($sh * $scale));
        if ($w > $baseW) {
            $w = $baseW;
        }
        if ($h > $baseH) {
            $h = $baseH;
        }

        return ['w' => $w, 'h' => $h];
    }

    /**
     * Resize sticker for drawing; preserves alpha. Returns GdImage or error array.
     */
    private static function resampleStickerImage(GdImage $src, int $dstW, int $dstH): GdImage|array
    {
        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($dstW <= 0 || $dstH <= 0) {
            return ['errors' => ['Invalid target dimensions.']];
        }
        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($dst === false) {
            return ['errors' => ['Failed to allocate scaled sticker.']];
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);
        imagealphablending($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        return $dst;
    }

    private static function savePng(GdImage $img, string $path): bool
    {
        return imagepng($img, $path);
    }
}
