<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

if ($path === '/test') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><body style="background:#fff;color:#000;">OK</body></html>';
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>404</title></head>';
    echo '<body style="background:#fff;color:#000;"><p>Not found</p></body></html>';
    http_response_code(404);
}
