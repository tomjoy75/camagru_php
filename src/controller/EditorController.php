<?php
/**
 * Handles editor page. Requires user to be logged in.
 */
class EditorController
{
    private const EDITOR_SAVED_LIMIT = 12;
    private const EDITOR_STATE_SESSION_KEY = 'editor_workspace_state';
    private const EDITOR_STATE_EMPTY = 'EMPTY';
    private const EDITOR_STATE_BASE_READY = 'BASE_READY';
    private const EDITOR_STATE_COMPOSED_READY = 'COMPOSED_READY';

    /** Session key: validated sticker filename chosen before base image exists. */
    private const PENDING_STICKER_SESSION_KEY = 'editor_pending_sticker';

    /**
     * Basename under public/tmp/ for which a successful POST /editor/compose has run for the current workspace.
     * Cleared whenever the workspace temp is replaced or removed.
     */
    private const COMPOSE_AUTHORIZED_BASENAME_SESSION_KEY = 'editor_workspace_compose_ok_basename';

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

        $hasWorkspaceBase = (string) ($_SESSION['editor_temp_image'] ?? '') !== '';
        if (!$hasWorkspaceBase) {
            require_once __DIR__ . '/../service/StickerService.php';
            $sticker = trim((string) ($_POST['sticker'] ?? ''));
            if ($sticker === '' || !StickerService::isAllowedStickerFilename($sticker)) {
                $_SESSION['editor_error'] = 'Select a valid sticker before uploading a new base image.';
                header('Location: /editor');
                exit;
            }
        }

        require __DIR__ . '/../service/ImageUploadService.php';
        $file = $_FILES['base_image'] ?? [];
        $result = ImageUploadService::processUpload($file);

        if (isset($result['filename'])) {
            $newFilename = $result['filename'];
            self::replaceEditorWorkspaceTemp($newFilename);
            self::setEditorWorkspaceState(self::EDITOR_STATE_BASE_READY);
            self::syncPendingStickerFromPost();
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
            $newFilename = $result['filename'];
            self::replaceEditorWorkspaceTemp($newFilename);
            self::setEditorWorkspaceState(self::EDITOR_STATE_BASE_READY);
            self::syncPendingStickerFromPost();
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
            self::renderEditorWithComposeError('No base image available for composition.');
            return;
        }

        $sticker = $_POST['sticker'] ?? '';
        $x = $_POST['x'] ?? null;
        $y = $_POST['y'] ?? null;
        $scaleRaw = $_POST['scale'] ?? null;
        $angleRaw = $_POST['angle'] ?? null;

        $scale = 1.0;
        if ($scaleRaw !== null && $scaleRaw !== '') {
            if (!is_numeric($scaleRaw)) {
                self::renderEditorWithComposeError('Invalid composition parameters.');
                return;
            }
            $scale = (float) $scaleRaw;
            if ($scale < 0.05 || $scale > 1.0) {
                self::renderEditorWithComposeError('Invalid composition parameters.');
                return;
            }
        }

        $angle = 0.0;
        if ($angleRaw !== null && $angleRaw !== '') {
            if (!is_numeric($angleRaw)) {
                self::renderEditorWithComposeError('Invalid composition parameters.');
                return;
            }
            $angle = (float) $angleRaw;
            if ($angle < -180.0 || $angle > 180.0) {
                self::renderEditorWithComposeError('Invalid composition parameters.');
                return;
            }
        }

        if ($sticker === '' || !is_numeric($x) || !is_numeric($y)) {
            self::renderEditorWithComposeError('Invalid composition parameters.');
            return;
        }

        require __DIR__ . '/../service/ImageComposeService.php';
        $result = ImageComposeService::compose($editorTempImage, $sticker, (int) $x, (int) $y, $scale, $angle);

        if (isset($result['filename'])) {
            self::replaceEditorWorkspaceTemp($result['filename']);
            $composedBase = basename((string) $result['filename']);
            if ($composedBase === (string) $result['filename'] && self::isValidEditorTempFilename($composedBase)) {
                $_SESSION[self::COMPOSE_AUTHORIZED_BASENAME_SESSION_KEY] = $composedBase;
            }
            self::setEditorWorkspaceState(self::EDITOR_STATE_COMPOSED_READY);
            header('Location: /editor');
            exit;
        }

        self::renderEditorWithComposeError($result['errors'][0] ?? 'Composition failed.');
    }

    public static function save(): void
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
            header('Location: /login');
            exit;
        }

        $editorState = self::getNormalizedEditorState($_SESSION['editor_temp_image'] ?? null);
        if ($editorState !== self::EDITOR_STATE_COMPOSED_READY) {
            $_SESSION['editor_error'] = 'Apply a sticker before saving the image.';
            header('Location: /editor');
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

        if (!self::isEditorWorkspaceComposeAuthorizedForSessionTemp()) {
            extract(array_merge(self::editorViewContext(), [
                'errors' => ['save' => 'To save to your gallery, apply a sticker with Compose on the server first.'],
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

        unset($_SESSION['editor_temp_image']);
        unset($_SESSION[self::PENDING_STICKER_SESSION_KEY]);
        unset($_SESSION[self::COMPOSE_AUTHORIZED_BASENAME_SESSION_KEY]);
        self::setEditorWorkspaceState(self::EDITOR_STATE_EMPTY);

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
        $deleteService = new ImageDeleteService();
        $deleteResult = $deleteService->delete((int) $imageId, (int) $_SESSION['user_id']);
        if (!$deleteResult['success']) {
            $_SESSION['editor_error'] = $deleteResult['error'];
            header('Location: /editor');
            exit;
        }

        $_SESSION['editor_success'] = $deleteResult['message'];
        header('Location: /editor');
        exit;
    }

    public static function reset(): void
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
            header('Location: /login');
            exit;
        }

        $raw = (string) ($_SESSION['editor_temp_image'] ?? '');
        unset($_SESSION['editor_temp_image']);
        self::setEditorWorkspaceState(self::EDITOR_STATE_EMPTY);
        unset($_SESSION[self::PENDING_STICKER_SESSION_KEY]);
        unset($_SESSION[self::COMPOSE_AUTHORIZED_BASENAME_SESSION_KEY]);

        self::unlinkEditorTempFileIfValid($raw);

        $_SESSION['editor_success'] = 'Workspace cleared. You can capture or upload a new image.';
        header('Location: /editor');
        exit;
    }

    /**
     * Assign a new workspace temp basename in session and remove the previous tmp file when it differs.
     */
    private static function replaceEditorWorkspaceTemp(string $newFilename): void
    {
        unset($_SESSION[self::COMPOSE_AUTHORIZED_BASENAME_SESSION_KEY]);
        $previous = (string) ($_SESSION['editor_temp_image'] ?? '');
        $_SESSION['editor_temp_image'] = $newFilename;
        $newBase = basename((string) $newFilename);
        if ($previous !== '' && basename($previous) !== $newBase) {
            self::unlinkEditorTempFileIfValid($previous);
        }
    }

    private static function renderEditorWithComposeError(string $message): void
    {
        extract(array_merge(self::editorViewContext(), [
            'errors' => ['compose' => $message],
        ]), EXTR_SKIP);
        header('Content-Type: text/html; charset=utf-8');
        $view = 'editor.php';
        require __DIR__ . '/../views/layout.php';
    }

    /**
     * @return array{
     *   stickers: list<array<string, mixed>>,
     *   editorTempImage: string|null,
     *   editorState: string,
     *   savedImages: list<array<string, mixed>>,
     *   editorPreviewSrc: string|null,
     *   canSaveEditorImage: bool,
     *   editorBaseNaturalW: int|null,
     *   editorBaseNaturalH: int|null,
     *   editorError: string|null,
     *   editorSuccess: string|null,
     *   editorStickerDefault: string
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
        $editorState = self::getNormalizedEditorState($editorTempImage);

        $repo = new ImageRepository();
        $savedImages = $repo->findRecentByUserId((int) $_SESSION['user_id'], self::EDITOR_SAVED_LIMIT);

        $editorPreviewSrc = null;
        $canSaveEditorImage = false;
        $editorBaseNaturalW = null;
        $editorBaseNaturalH = null;
        if ($editorTempImage !== null && $editorTempImage !== '') {
            $base = basename((string) $editorTempImage);
            if ($base === (string) $editorTempImage && self::isValidEditorTempFilename($base)) {
                $tmpPath = __DIR__ . '/../../public/tmp/' . $base;
                if (is_file($tmpPath)) {
                    $editorPreviewSrc = '/tmp/' . $base;
                    $info = @getimagesize($tmpPath);
                    if ($info !== false) {
                        $editorBaseNaturalW = (int) $info[0];
                        $editorBaseNaturalH = (int) $info[1];
                    }
                }
            }
        }
        $canSaveEditorImage = $editorState === self::EDITOR_STATE_COMPOSED_READY;
        $editorError = $_SESSION['editor_error'] ?? null;
        $editorSuccess = $_SESSION['editor_success'] ?? null;
        unset($_SESSION['editor_error']);
        unset($_SESSION['editor_success']);

        $editorStickerDefault = '';
        $pending = $_SESSION[self::PENDING_STICKER_SESSION_KEY] ?? null;
        if (is_string($pending) && $pending !== '' && StickerService::isAllowedStickerFilename($pending)) {
            $editorStickerDefault = basename($pending);
        }

        return [
            'stickers' => $stickers,
            'editorTempImage' => $editorTempImage,
            'editorState' => $editorState,
            'savedImages' => $savedImages,
            'editorPreviewSrc' => $editorPreviewSrc,
            'canSaveEditorImage' => $canSaveEditorImage,
            'editorBaseNaturalW' => $editorBaseNaturalW,
            'editorBaseNaturalH' => $editorBaseNaturalH,
            'editorError' => $editorError,
            'editorSuccess' => $editorSuccess,
            'editorStickerDefault' => $editorStickerDefault,
        ];
    }

    /**
     * After successful capture/upload: optional POST sticker sets, clears, or leaves pending (if key absent).
     */
    private static function syncPendingStickerFromPost(): void
    {
        require_once __DIR__ . '/../service/StickerService.php';
        if (!array_key_exists('sticker', $_POST)) {
            return;
        }
        $raw = trim((string) ($_POST['sticker'] ?? ''));
        if ($raw === '') {
            unset($_SESSION[self::PENDING_STICKER_SESSION_KEY]);
            return;
        }
        if (StickerService::isAllowedStickerFilename($raw)) {
            $_SESSION[self::PENDING_STICKER_SESSION_KEY] = basename($raw);
            return;
        }
        unset($_SESSION[self::PENDING_STICKER_SESSION_KEY]);
    }

    private static function isEditorWorkspaceComposeAuthorizedForSessionTemp(): bool
    {
        $raw = $_SESSION['editor_temp_image'] ?? '';
        if ($raw === null || $raw === '') {
            return false;
        }
        $base = basename((string) $raw);
        if ($base !== (string) $raw || !self::isValidEditorTempFilename($base)) {
            return false;
        }
        $authorized = $_SESSION[self::COMPOSE_AUTHORIZED_BASENAME_SESSION_KEY] ?? null;
        return is_string($authorized) && $authorized === $base;
    }

    private static function getNormalizedEditorState(?string $editorTempImage): string
    {
        if (!self::hasValidWorkspaceTempImage($editorTempImage)) {
            return self::EDITOR_STATE_EMPTY;
        }

        $raw = $_SESSION[self::EDITOR_STATE_SESSION_KEY] ?? null;
        if ($raw === self::EDITOR_STATE_COMPOSED_READY) {
            return self::EDITOR_STATE_COMPOSED_READY;
        }

        return self::EDITOR_STATE_BASE_READY;
    }

    private static function setEditorWorkspaceState(string $state): void
    {
        if ($state === self::EDITOR_STATE_EMPTY) {
            unset($_SESSION[self::EDITOR_STATE_SESSION_KEY]);
            return;
        }

        if ($state === self::EDITOR_STATE_BASE_READY || $state === self::EDITOR_STATE_COMPOSED_READY) {
            $_SESSION[self::EDITOR_STATE_SESSION_KEY] = $state;
            return;
        }

        unset($_SESSION[self::EDITOR_STATE_SESSION_KEY]);
    }

    private static function hasValidWorkspaceTempImage(?string $editorTempImage): bool
    {
        if (!is_string($editorTempImage) || $editorTempImage === '') {
            return false;
        }

        $base = basename($editorTempImage);
        if ($base !== $editorTempImage || !self::isValidEditorTempFilename($base)) {
            return false;
        }

        $tmpPath = __DIR__ . '/../../public/tmp/' . $base;
        return is_file($tmpPath);
    }

    private static function unlinkEditorTempFileIfValid(string $raw): void
    {
        $raw = (string) $raw;
        if ($raw === '') {
            return;
        }
        $base = basename($raw);
        if ($base !== $raw || !self::isValidEditorTempFilename($base)) {
            return;
        }
        $tmpPath = __DIR__ . '/../../public/tmp/' . $base;
        if (is_file($tmpPath)) {
            @unlink($tmpPath);
        }
    }

    private static function isValidEditorTempFilename(string $name): bool
    {
        return (bool) preg_match('/\Aimg_[a-f0-9]{16}\.(png|jpg)\z/', $name);
    }
}
