<?php
/**
 * Handles editor page. Requires user to be logged in.
 */
class EditorController
{
    public static function show(): void
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
            header('Location: /login');
            exit;
        }

        require __DIR__ . '/../service/StickerService.php';
        $stickers = StickerService::getStickers();
        $editorTempImage = $_SESSION['editor_temp_image'] ?? null;
        if ($editorTempImage === '') {
            $editorTempImage = null;
        }

        header('Content-Type: text/html; charset=utf-8');
        $view = 'editor.php';
        require __DIR__ . '/../views/layout.php';
    }

    public static function upload(): void
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
            header('Location: /login');
            exit;
        }

        require __DIR__ . '/../service/ImageUploadService.php';
        $file = $_FILES['base_image'] ?? [];
        $result = ImageUploadService::processUpload($file);

        if (isset($result['filename'])) {
            $_SESSION['editor_temp_image'] = $result['filename'];
            header('Location: /editor');
            exit;
        }

        require __DIR__ . '/../service/StickerService.php';
        $stickers = StickerService::getStickers();
        $errors = ['upload' => $result['errors'][0] ?? 'Upload failed.'];
        header('Content-Type: text/html; charset=utf-8');
        $view = 'editor.php';
        require __DIR__ . '/../views/layout.php';
    }
}
