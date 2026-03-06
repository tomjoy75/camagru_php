<?php
/**
 * Handles auth-related actions: register form display and registration.
 */

class AuthController
{
    public static function showRegisterForm(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $view = 'register.php';
        require __DIR__ . '/../views/layout.php';
    }

    public static function register(): void
    {
    }
}
