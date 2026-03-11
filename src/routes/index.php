<?php
/**
 * Router: matches request path and calls the right controller.
 * Does not send the response; controllers do.
 */
// var_dump($_SERVER['REQUEST_METHOD']);
// exit;
// var_dump($_SERVER['REQUEST_URI']);
// exit;
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

if ($path === '/test') {
    require __DIR__ . '/../controller/TestController.php';
    TestController::handle();
} else if ($path === '/test-db') {
    require __DIR__ . '/../controller/TestDbController.php';
    TestDbController::handle();
} else if ($path === '/register' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::showRegisterForm();
} else if ($path === '/register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::register();
} else if ($path === '/login' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::showLoginForm();
} else if ($path === '/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::login();
} else if ($path === '/logout') {
    require __DIR__ . '/../controller/AuthController.php';
    AuthController::logout();
} else if ($path === '/editor' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/../controller/EditorController.php';
    EditorController::show();
} else if ($path === '/editor/upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../controller/EditorController.php';
    EditorController::upload();
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
} else {
    require __DIR__ . '/../controller/NotFoundController.php';
    NotFoundController::handle();
}
