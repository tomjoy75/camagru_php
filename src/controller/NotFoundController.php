<?php
/**
 * Handles unmatched routes: sends 404 response.
 * Logic here; display is in views/not_found.php.
 */

class NotFoundController
{
    public static function handle(): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        require __DIR__ . '/../views/not_found.php';
    }
}
