<?php
/**
 * Handles GET /test: reads request, sends OK response.
 * Logic here; display is in views/test.php.
 */

class TestController
{
    public static function handle(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        require __DIR__ . '/../views/test.php';
    }
}
