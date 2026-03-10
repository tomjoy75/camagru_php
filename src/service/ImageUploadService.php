<?php
/**
 * Service for validating and storing uploaded images (editor base image).
 * Validates file presence, size, MIME, and getimagesize; moves to public/tmp/ with unique filename.
 */
class ImageUploadService
{
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MiB
    private const ALLOWED_TYPES = [IMAGETYPE_PNG, IMAGETYPE_JPEG];

    /**
     * Process uploaded file: validate, move to public/tmp/, return filename or errors.
     * $file is the $_FILES['base_image'] entry (tmp_name, size, type, error).
     * Returns ['filename' => string] on success, ['errors' => string[]] on failure.
     */
    public static function processUpload(array $file): array
    {
        $errors = [];

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'No file uploaded.';
            return ['errors' => $errors];
        }

        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed.';
            return ['errors' => $errors];
        }

        if (empty($file['size']) || $file['size'] > self::MAX_SIZE_BYTES) {
            $errors[] = 'File must be between 1 byte and 5 MB.';
            return ['errors' => $errors];
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            $errors[] = 'File is not a valid image.';
            return ['errors' => $errors];
        }

        if (!in_array($info[2], self::ALLOWED_TYPES, true)) {
            $errors[] = 'Only PNG and JPEG images are allowed.';
            return ['errors' => $errors];
        }

        $ext = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        $filename = sprintf('img_%s.%s', bin2hex(random_bytes(8)), $ext);

        $tmpDir = __DIR__ . '/../../public/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $destination = $tmpDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $errors[] = 'Failed to save upload.';
            return ['errors' => $errors];
        }

        return ['filename' => $filename];
    }
}
