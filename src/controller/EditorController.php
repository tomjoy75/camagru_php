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

        header('Content-Type: text/html; charset=utf-8');
        $view = 'editor.php';
        require __DIR__ . '/../views/layout.php';
    }

    public static function upload(): void
    {
        // Step 1: route only; logic in later steps.
    }
}
