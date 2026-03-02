<?php
/**
 * Handles unmatched routes: sends 404 response.
 */

class NotFoundController
{
    public static function handle(): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>404</title></head>';
        echo '<body style="background:#fff;color:#000;"><p>Not found</p></body></html>';
    }
}
