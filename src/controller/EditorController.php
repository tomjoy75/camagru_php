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

        require_once __DIR__ . '/../service/StickerService.php';
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

        require_once __DIR__ . '/../service/StickerService.php';
        $stickers = StickerService::getStickers();
        $errors = ['upload' => $result['errors'][0] ?? 'Upload failed.'];
        header('Content-Type: text/html; charset=utf-8');
        $view = 'editor.php';
        require __DIR__ . '/../views/layout.php';
    }

    public static function compose(): void
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
            header('Location: /login');
            exit;
        }

        $editorTempImage = $_SESSION['editor_temp_image'] ?? null;
        if ($editorTempImage === null || $editorTempImage === '') {
            require_once __DIR__ . '/../service/StickerService.php';
            $stickers = StickerService::getStickers();
            $errors = ['compose' => 'No base image available for composition.'];
            header('Content-Type: text/html; charset=utf-8');
            $view = 'editor.php';
            require __DIR__ . '/../views/layout.php';
            return;
        }

        $sticker = $_POST['sticker'] ?? '';
        $x = $_POST['x'] ?? null;
        $y = $_POST['y'] ?? null;

        if ($sticker === '' || !is_numeric($x) || !is_numeric($y)) {
            require_once __DIR__ . '/../service/StickerService.php';
            $stickers = StickerService::getStickers();
            $errors = ['compose' => 'Invalid composition parameters.'];
            header('Content-Type: text/html; charset=utf-8');
            $view = 'editor.php';
            require __DIR__ . '/../views/layout.php';
            return;
        }

        require __DIR__ . '/../service/ImageComposeService.php';
        $result = ImageComposeService::compose($editorTempImage, $sticker, (int) $x, (int) $y);

        if (isset($result['filename'])) {
            $_SESSION['editor_temp_image'] = $result['filename'];
            header('Location: /editor');
            exit;
        }

        require_once __DIR__ . '/../service/StickerService.php';
        $stickers = StickerService::getStickers();
        $errors = ['compose' => $result['errors'][0] ?? 'Composition failed.'];
        header('Content-Type: text/html; charset=utf-8');
        $view = 'editor.php';
        require __DIR__ . '/../views/layout.php';
    }
}
