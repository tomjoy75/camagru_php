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
        $errors = [];
        $email = '';
        $view = 'login.php';
        require __DIR__ . '/../views/layout.php';
    }

    public static function login(): void
    {
        require __DIR__ . '/../service/AuthService.php';

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = AuthService::login($email, $password);

        if ($result['errors'] === []) {
            $_SESSION['user_id'] = $result['user']['id'];
            header('Location: /');
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        $errors = $result['errors'];
        $view = 'login.php';
        require __DIR__ . '/../views/layout.php';
    }

    public static function logout(): void
    {
        session_destroy();
        header('Location: /login');
        exit;
    }

    public static function showPasswordResetForm(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $email = '';
        $view = 'password_reset_request.php';
        require __DIR__ . '/../views/layout.php';
    }

    public static function requestPasswordReset(): void
    {
        require __DIR__ . '/../service/AuthService.php';

        $email = $_POST['email'] ?? '';
        AuthService::requestPasswordReset(is_string($email) ? $email : '');

        header('Location: /password-reset/sent', true, 302);
        exit;
    }

    public static function showPasswordResetSent(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $view = 'password_reset_sent.php';
        require __DIR__ . '/../views/layout.php';
    }

    /**
     * GET /password-reset/confirm?token= (#54).
     */
    public static function showPasswordResetConfirm(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        require_once __DIR__ . '/../service/AuthService.php';

        $token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
        $userId = AuthService::resolvePasswordResetUserIdFromRawToken($token);
        if ($userId === null) {
            $view = 'password_reset_confirm_invalid.php';
            require __DIR__ . '/../views/layout.php';

            return;
        }

        $errors = [];
        $resetToken = $token;
        $view = 'password_reset_confirm_form.php';
        require __DIR__ . '/../views/layout.php';
    }

    /**
     * POST /password-reset/confirm (#54).
     */
    public static function submitPasswordResetConfirm(): void
    {
        require_once __DIR__ . '/../service/AuthService.php';

        $token = isset($_POST['token']) ? trim((string) $_POST['token']) : '';
        $userId = AuthService::resolvePasswordResetUserIdFromRawToken($token);
        if ($userId === null) {
            header('Content-Type: text/html; charset=utf-8');
            $view = 'password_reset_confirm_invalid.php';
            require __DIR__ . '/../views/layout.php';

            return;
        }

        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? (string) $_POST['confirm_password'] : '';

        $errors = AuthService::completePasswordResetForUserId($userId, $password, $confirmPassword);
        if ($errors === []) {
            header('Location: /password-reset/complete', true, 302);
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        $resetToken = $token;
        $view = 'password_reset_confirm_form.php';
        require __DIR__ . '/../views/layout.php';
    }

    /**
     * GET /password-reset/complete — PRG success (#54).
     */
    public static function showPasswordResetComplete(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $view = 'password_reset_confirm_success.php';
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

        header('Location: /login');
        exit;
    }

    /**
     * Public GET: confirm email via ?token= (64 hex chars). No session required (#47).
     */
    public static function confirmRegister(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $token = isset($_GET['token']) ? (string) $_GET['token'] : '';
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            $confirmResult = 'invalid';
            $view = 'register_confirm.php';
            require __DIR__ . '/../views/layout.php';

            return;
        }

        require_once __DIR__ . '/../repository/UserRepository.php';
        $repo = new UserRepository();
        $row = $repo->findIdAndVerifiedByConfirmationToken($token);
        if ($row === null) {
            $confirmResult = 'invalid';
            $view = 'register_confirm.php';
            require __DIR__ . '/../views/layout.php';

            return;
        }

        if ($row['email_verified'] !== 0) {
            $repo->clearConfirmationToken($row['id']);
            $confirmResult = 'already';
            $view = 'register_confirm.php';
            require __DIR__ . '/../views/layout.php';

            return;
        }

        if ($repo->markEmailVerifiedAndClearToken($row['id'])) {
            $confirmResult = 'success';
        } else {
            $confirmResult = 'invalid';
        }
        $view = 'register_confirm.php';
        require __DIR__ . '/../views/layout.php';
    }
}
