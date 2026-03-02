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
} else {
    require __DIR__ . '/../controller/NotFoundController.php';
    NotFoundController::handle();
}
