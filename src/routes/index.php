<?php
/**
 * Router: matches request path and calls the right controller.
 * Does not send the response; controllers do.
 */

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
} else {
    require __DIR__ . '/../controller/NotFoundController.php';
    NotFoundController::handle();
}
