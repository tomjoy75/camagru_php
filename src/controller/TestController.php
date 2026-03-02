<?php
/**
 * Handles GET /test: reads HTTP request, calls TestService,
 * passes formatted message to the view.
 */

class TestController
{
    public static function handle(): void
    {
        require __DIR__ . '/../service/TestService.php';
        $rawMessage = $_GET['message'] ?? null;
        $message = TestService::getMessage($rawMessage);

        header('Content-Type: text/html; charset=utf-8');
        require __DIR__ . '/../views/test.php';
    }
}
