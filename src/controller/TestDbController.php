<?php
/**
 * Handles GET /test-db: reads HTTP request, calls TestDbService,
 * passes formatted message to the view.
 */

class TestDbController
{
    public static function handle(): void
    {
        require __DIR__ . '/../service/TestDbService.php';
        $rawEmail = $_GET['email'] ?? null;
        $message = TestDbService::getMessage($rawEmail);

        header('Content-Type: text/html; charset=utf-8');
        $view = 'test-db.php';
        require __DIR__ . '/../views/layout.php';
    }
}


