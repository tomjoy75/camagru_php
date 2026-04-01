<?php
/**
 * Handles editor page. Requires user to be logged in.
 */
class EditorController
{
    private const EDITOR_SAVED_LIMIT = 12;

    public static function show(): void
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
            header('Location: /login');
            exit;
        }

        extract(self::editorViewContext(), EXTR_SKIP);

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
            $_SESSION['editor_success'] = 'Image loaded into editor workspace.';
            header('Location: /editor');
            exit;
        }

        $_SESSION['editor_error'] = $result['errors'][0] ?? 'Upload failed.';
        header('Location: /editor');
        exit;
    }

    public static function capture(): void
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
            header('Location: /login');
            exit;
        }

        require __DIR__ . '/../service/ImageUploadService.php';
        $dataUrl = (string) ($_POST['base_image_data'] ?? '');
        $result = ImageUploadService::processCaptureData($dataUrl);

        if (isset($result['filename'])) {
            $_SESSION['editor_temp_image'] = $result['filename'];
            $_SESSION['editor_success'] = 'Capture loaded into editor workspace.';
            header('Location: /editor');
            exit;
        }

        $_SESSION['editor_error'] = $result['errors'][0] ?? 'Capture failed.';
        header('Location: /editor');
        exit;
    }

    public static function compose(): void
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
            header('Location: /login');
            exit;
        }

        $editorTempImage = $_SESSION['editor_temp_image'] ?? null;
        if ($editorTempImage === null || $editorTempImage === '') {
            extract(array_merge(self::editorViewContext(), [
                'errors' => ['compose' => 'No base image available for composition.'],
            ]), EXTR_SKIP);
            header('Content-Type: text/html; charset=utf-8');
            $view = 'editor.php';
            require __DIR__ . '/../views/layout.php';
            return;
        }

        $sticker = $_POST['sticker'] ?? '';
        $x = $_POST['x'] ?? null;
        $y = $_POST['y'] ?? null;

        if ($sticker === '' || !is_numeric($x) || !is_numeric($y)) {
            extract(array_merge(self::editorViewContext(), [
                'errors' => ['compose' => 'Invalid composition parameters.'],
            ]), EXTR_SKIP);
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

        extract(array_merge(self::editorViewContext(), [
            'errors' => ['compose' => $result['errors'][0] ?? 'Composition failed.'],
        ]), EXTR_SKIP);

        header('Content-Type: text/html; charset=utf-8');
        $view = 'editor.php';
        require __DIR__ . '/../views/layout.php';
    }

    public static function save(): void
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
            header('Location: /login');
            exit;
        }

        $raw = $_SESSION['editor_temp_image'] ?? '';
        if ($raw === '') {
            extract(array_merge(self::editorViewContext(), [
                'errors' => ['save' => 'No image to save.'],
            ]), EXTR_SKIP);
            header('Content-Type: text/html; charset=utf-8');
            $view = 'editor.php';
            require __DIR__ . '/../views/layout.php';
            return;
        }

        $base = basename($raw);
        if ($base !== $raw || !self::isValidEditorTempFilename($base)) {
            extract(array_merge(self::editorViewContext(), [
                'errors' => ['save' => 'Invalid image reference.'],
            ]), EXTR_SKIP);
            header('Content-Type: text/html; charset=utf-8');
            $view = 'editor.php';
            require __DIR__ . '/../views/layout.php';
            return;
        }

        $tmpPath = __DIR__ . '/../../public/tmp/' . $base;
        $uploadPath = __DIR__ . '/../../public/uploads/' . $base;

        if (!is_file($tmpPath)) {
            extract(array_merge(self::editorViewContext(), [
                'errors' => ['save' => 'Nothing to save (image is not in the editor workspace).'],
            ]), EXTR_SKIP);
            header('Content-Type: text/html; charset=utf-8');
            $view = 'editor.php';
            require __DIR__ . '/../views/layout.php';
            return;
        }

        $uploadDir = __DIR__ . '/../../public/uploads';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                extract(array_merge(self::editorViewContext(), [
                    'errors' => ['save' => 'Could not prepare storage.'],
                ]), EXTR_SKIP);
                header('Content-Type: text/html; charset=utf-8');
                $view = 'editor.php';
                require __DIR__ . '/../views/layout.php';
                return;
            }
        }

        if (is_file($uploadPath)) {
            extract(array_merge(self::editorViewContext(), [
                'errors' => ['save' => 'Save failed (file already exists).'],
            ]), EXTR_SKIP);
            header('Content-Type: text/html; charset=utf-8');
            $view = 'editor.php';
            require __DIR__ . '/../views/layout.php';
            return;
        }

        if (!rename($tmpPath, $uploadPath)) {
            extract(array_merge(self::editorViewContext(), [
                'errors' => ['save' => 'Failed to move image to storage.'],
            ]), EXTR_SKIP);
            header('Content-Type: text/html; charset=utf-8');
            $view = 'editor.php';
            require __DIR__ . '/../views/layout.php';
            return;
        }

        require_once __DIR__ . '/../repository/ImageRepository.php';
        $repo = new ImageRepository();
        $relativePath = 'uploads/' . $base;
        $inserted = $repo->insert((int) $_SESSION['user_id'], $relativePath);

        if (!$inserted) {
            @rename($uploadPath, $tmpPath);
            extract(array_merge(self::editorViewContext(), [
                'errors' => ['save' => 'Failed to record image.'],
            ]), EXTR_SKIP);
            header('Content-Type: text/html; charset=utf-8');
            $view = 'editor.php';
            require __DIR__ . '/../views/layout.php';
            return;
        }

        header('Location: /editor');
        exit;
    }

    public static function delete(): void
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
            header('Location: /login');
            exit;
        }
        $imageId = $_POST['image_id'] ?? null;
        if (!is_numeric($imageId) || $imageId <= 0 || $imageId === '' || $imageId === null) {
            $_SESSION['editor_error'] = 'Invalid image ID.';
            header('Location: /editor');
            exit;
        }
        require_once __DIR__ . '/../service/ImageDeleteService.php';
        $result = new ImageDeleteService();
        $result = $result->delete((int) $imageId, (int) $_SESSION['user_id']);
        if (!$result['success']) {
            $_SESSION['editor_error'] = $result['error'];
            header('Location: /editor');
            exit;
        }

        $_SESSION['editor_success'] = $result['message'];
        header('Location: /editor');
        exit;
    }

    /**
     * @return array{
     *   stickers: list<array<string, mixed>>,
     *   editorTempImage: string|null,
     *   savedImages: list<array<string, mixed>>,
     *   editorPreviewSrc: string|null,
     *   canSaveEditorImage: bool
     * }
     */
    private static function editorViewContext(): array
    {
        require_once __DIR__ . '/../service/StickerService.php';
        require_once __DIR__ . '/../repository/ImageRepository.php';

        $stickers = StickerService::getStickers();
        $editorTempImage = $_SESSION['editor_temp_image'] ?? null;
        if ($editorTempImage === '') {
            $editorTempImage = null;
        }

        $repo = new ImageRepository();
        $savedImages = $repo->findRecentByUserId((int) $_SESSION['user_id'], self::EDITOR_SAVED_LIMIT);

        $editorPreviewSrc = null;
        $canSaveEditorImage = false;
        if ($editorTempImage !== null && $editorTempImage !== '') {
            $base = basename((string) $editorTempImage);
            if ($base === (string) $editorTempImage && self::isValidEditorTempFilename($base)) {
                $tmpPath = __DIR__ . '/../../public/tmp/' . $base;
                $upPath = __DIR__ . '/../../public/uploads/' . $base;
                if (is_file($tmpPath)) {
                    $editorPreviewSrc = '/tmp/' . $base;
                    $canSaveEditorImage = true;
                } elseif (is_file($upPath)) {
                    $editorPreviewSrc = '/uploads/' . $base;
                }
            }
        }
        $editorError = $_SESSION['editor_error'] ?? null;
        $editorSuccess = $_SESSION['editor_success'] ?? null;
        unset($_SESSION['editor_error']);
        unset($_SESSION['editor_success']);

        return [
            'stickers' => $stickers,
            'editorTempImage' => $editorTempImage,
            'savedImages' => $savedImages,
            'editorPreviewSrc' => $editorPreviewSrc,
            'canSaveEditorImage' => $canSaveEditorImage,
            'editorError' => $editorError,
            'editorSuccess' => $editorSuccess,
        ];
    }

    private static function isValidEditorTempFilename(string $name): bool
    {
        return (bool) preg_match('/\Aimg_[a-f0-9]{16}\.(png|jpg)\z/', $name);
    }
}
