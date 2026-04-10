<?php
/**
 * Router: matches request path and calls the right controller.
 * Does not send the response; controllers do.
 *
 * Dev-only: GET /test and /test-db are registered only when CAMAGRU_DEV_ENDPOINTS=1
 * is set in the process environment (omit for evaluation / production).
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

if ($path === '/') {
    header('Location: /gallery', true, 302);
    exit;
} else if ($path === '/test' && getenv('CAMAGRU_DEV_ENDPOINTS') === '1') {
    require __DIR__ . '/../controller/TestController.php';
    TestController::handle();
} else if ($path === '/test-db' && getenv('CAMAGRU_DEV_ENDPOINTS') === '1') {
    require __DIR__ . '/../controller/TestDbController.php';
    TestDbController::handle();
} else if ($path === '/register' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::showRegisterForm();
} else if ($path === '/register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::register();
} else if ($path === '/register/confirm' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::confirmRegister();
} else if ($path === '/login' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::showLoginForm();
} else if ($path === '/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::login();
} else if ($path === '/logout') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::logout();
} else if ($path === '/password-reset' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::showPasswordResetForm();
} else if ($path === '/password-reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::requestPasswordReset();
} else if ($path === '/password-reset/sent' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::showPasswordResetSent();
} else if ($path === '/password-reset/confirm' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::showPasswordResetConfirm();
} else if ($path === '/password-reset/confirm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::submitPasswordResetConfirm();
} else if ($path === '/password-reset/complete' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::showPasswordResetComplete();
} else if ($path === '/settings/notifications' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/SettingsController.php';
    SettingsController::showNotifications();
} else if ($path === '/settings/notifications' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/SettingsController.php';
    SettingsController::updateNotifications();
} else if ($path === '/settings/profile' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/SettingsController.php';
    SettingsController::showProfile();
} else if ($path === '/settings/profile/username' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/SettingsController.php';
    SettingsController::updateProfileUsername();
} else if ($path === '/settings/profile/email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/SettingsController.php';
    SettingsController::updateProfileEmail();
} else if ($path === '/settings/profile/password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/SettingsController.php';
    SettingsController::updateProfilePassword();
} else if ($path === '/gallery' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/GalleryController.php';
    GalleryController::show();
} else if ($path === '/gallery/image' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/GalleryController.php';
    GalleryController::showImage();
} else if ($path === '/gallery/like' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/GalleryController.php';
    GalleryController::toggleLike();
} else if ($path === '/gallery/comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/GalleryController.php';
    GalleryController::addComment();
} else if ($path === '/editor' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/EditorController.php';
    EditorController::show();
} else if ($path === '/editor/upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/EditorController.php';
    EditorController::upload();
} else if ($path === '/editor/capture' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/EditorController.php';
    EditorController::capture();
} else if ($path === '/editor/compose' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/EditorController.php';
    EditorController::compose();
} else if ($path === '/editor/save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/EditorController.php';
    EditorController::save();
} else if ($path === '/editor/delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/EditorController.php';
    EditorController::delete();
} else if ($path === '/editor/reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/EditorController.php';
    EditorController::reset();
} else if (strpos($path, '/tmp/') === 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $name = basename(substr($path, strlen('/tmp/')));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg'];
    if ($name === '' || !in_array($ext, $allowed, true)) {
        require __DIR__ . '/../controller/NotFoundController.php';
        NotFoundController::handle();
        exit;
    }
    $file = __DIR__ . '/../../public/tmp/' . $name;
    if (!is_file($file)) {
        require __DIR__ . '/../controller/NotFoundController.php';
        NotFoundController::handle();
        exit;
    }
    header('Content-Type: ' . ($ext === 'png' ? 'image/png' : 'image/jpeg'));
    readfile($file);
    exit;
} else if (strpos($path, '/uploads/') === 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $name = basename(substr($path, strlen('/uploads/')));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg'];
    if ($name === '' || !in_array($ext, $allowed, true)) {
        require __DIR__ . '/../controller/NotFoundController.php';
        NotFoundController::handle();
        exit;
    }
    $file = __DIR__ . '/../../public/uploads/' . $name;
    if (!is_file($file)) {
        require __DIR__ . '/../controller/NotFoundController.php';
        NotFoundController::handle();
        exit;
    }
    header('Content-Type: ' . ($ext === 'png' ? 'image/png' : 'image/jpeg'));
    readfile($file);
    exit;
} else if (strpos($path, '/stickers/') === 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $name = basename(substr($path, strlen('/stickers/')));
    if ($name === '' || pathinfo($name, PATHINFO_EXTENSION) !== 'png') {
        require __DIR__ . '/../controller/NotFoundController.php';
        NotFoundController::handle();
        exit;
    }
    $file = __DIR__ . '/../../public/stickers/' . $name;
    if (!is_file($file)) {
        require __DIR__ . '/../controller/NotFoundController.php';
        NotFoundController::handle();
        exit;
    }
    header('Content-Type: image/png');
    readfile($file);
    exit;
} else if (strpos($path, '/js/') === 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $name = basename(substr($path, strlen('/js/')));
    if ($name === '' || pathinfo($name, PATHINFO_EXTENSION) !== 'js') {
        require __DIR__ . '/../controller/NotFoundController.php';
        NotFoundController::handle();
        exit;
    }
    $file = __DIR__ . '/../../public/js/' . $name;
    if (!is_file($file)) {
        require __DIR__ . '/../controller/NotFoundController.php';
        NotFoundController::handle();
        exit;
    }
    header('Content-Type: application/javascript; charset=UTF-8');
    readfile($file);
    exit;
} else {
    require __DIR__ . '/../controller/NotFoundController.php';
    NotFoundController::handle();
}
