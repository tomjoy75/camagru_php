<?php
/**
 * Handles auth-related actions: register form display and registration.
 */

class AuthController
{
    public static function showRegisterForm(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $errors = [];
        $email = '';
        $username = '';
        $view = 'register.php';
        require __DIR__ . '/../views/layout.php';
    }

    public static function showLoginForm(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $view = 'login.php';
        require __DIR__ . '/../views/layout.php';
    }

    public static function register(): void
    {
        require __DIR__ . '/../service/AuthService.php';

        $email = $_POST['email'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['confirm_password'] ?? '';

        $errors = AuthService::register(
            $email,
            $username,
            $password,
            $passwordConfirmation
        );

        if ($errors !== []) {
            header('Content-Type: text/html; charset=utf-8');
            $view = 'register.php';
            require __DIR__ . '/../views/layout.php';
            return;
        }

        header('Location: /');
        exit;
    }
}
