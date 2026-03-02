<?php
/**
 * Handles GET /test: reads request, sends OK response.
 * No service called for now.
 */

class TestController
{
    public static function handle(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<html><body style="background:#fff;color:#000;">OK</body></html>';
    }
}
