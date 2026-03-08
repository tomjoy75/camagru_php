<?php
/**
 * Front controller: single entry point for every request.
 * Only delegates to the router (src/routes/index.php).
 * Server (PHP built-in now, Nginx later) sends all requests here.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../src/routes/index.php';
