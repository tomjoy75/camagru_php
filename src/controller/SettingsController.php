<?php
/**
 * Authenticated settings (e.g. notification preferences). No guest access.
 */
class SettingsController
{
    public static function showProfile(): void
    {
        $userId = self::currentUserId();
        if ($userId === null) {
            header('Location: /login');
            exit;
        }

        require_once __DIR__ . '/../repository/UserRepository.php';
        $repo = new UserRepository();
        $username = $repo->getUsernameByUserId($userId);
        $email = $repo->getEmailByUserId($userId);
        if ($username === null || $email === null) {
            header('Location: /login');
            exit;
        }

        $profileSettingsSuccess = $_SESSION['profile_settings_success'] ?? null;
        $profileSettingsError = $_SESSION['profile_settings_error'] ?? null;
        unset($_SESSION['profile_settings_success'], $_SESSION['profile_settings_error']);

        header('Content-Type: text/html; charset=utf-8');
        $view = 'settings_profile.php';
        require __DIR__ . '/../views/layout.php';
    }

    public static function updateProfileUsername(): void
    {
        $userId = self::currentUserId();
        if ($userId === null) {
            header('Location: /login');
            exit;
        }

        require_once __DIR__ . '/../service/AuthService.php';
        $rawUsername = (string) ($_POST['username'] ?? '');
        $result = AuthService::updateUsername($userId, $rawUsername);
        if ($result['errors'] !== []) {
            $_SESSION['profile_settings_error'] = (string) ($result['errors']['username'] ?? $result['errors']['form'] ?? 'Invalid username.');
            header('Location: /settings/profile');
            exit;
        }

        $_SESSION['profile_settings_success'] = 'Username updated.';
        header('Location: /settings/profile');
        exit;
    }

    public static function updateProfileEmail(): void
    {
        $userId = self::currentUserId();
        if ($userId === null) {
            header('Location: /login');
            exit;
        }

        require_once __DIR__ . '/../service/AuthService.php';
        $rawEmail = (string) ($_POST['email'] ?? '');
        $result = AuthService::updateEmail($userId, $rawEmail);
        if ($result['errors'] !== []) {
            $_SESSION['profile_settings_error'] = (string) ($result['errors']['email'] ?? $result['errors']['form'] ?? 'Invalid email.');
            header('Location: /settings/profile');
            exit;
        }

        $_SESSION['profile_settings_success'] = 'Email updated. Please confirm your new email before your next sign in.';
        header('Location: /settings/profile');
        exit;
    }

    public static function updateProfilePassword(): void
    {
        $userId = self::currentUserId();
        if ($userId === null) {
            header('Location: /login');
            exit;
        }

        require_once __DIR__ . '/../service/AuthService.php';
        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $errors = AuthService::changePassword($userId, $current, $password, $confirm);
        if ($errors !== []) {
            $_SESSION['profile_settings_error'] = (string) (
                $errors['current_password']
                ?? $errors['password']
                ?? $errors['confirm_password']
                ?? $errors['form']
                ?? 'Could not update password.'
            );
            header('Location: /settings/profile');
            exit;
        }

        $_SESSION['profile_settings_success'] = 'Password updated.';
        header('Location: /settings/profile');
        exit;
    }

    public static function showNotifications(): void
    {
        $userId = self::currentUserId();
        if ($userId === null) {
            header('Location: /login');
            exit;
        }

        require_once __DIR__ . '/../repository/UserRepository.php';
        $repo = new UserRepository();
        $raw = $repo->getNotificationsEnabledByUserId($userId);
        if ($raw === null) {
            header('Location: /login');
            exit;
        }
        $notificationsEnabled = $raw;

        $notificationsSettingsSuccess = $_SESSION['notifications_settings_success'] ?? null;
        $notificationsSettingsError = $_SESSION['notifications_settings_error'] ?? null;
        unset($_SESSION['notifications_settings_success'], $_SESSION['notifications_settings_error']);

        header('Content-Type: text/html; charset=utf-8');
        $view = 'settings_notifications.php';
        require __DIR__ . '/../views/layout.php';
    }

    public static function updateNotifications(): void
    {
        $userId = self::currentUserId();
        if ($userId === null) {
            header('Location: /login');
            exit;
        }

        $raw = $_POST['notifications_enabled'] ?? null;
        if ($raw !== '0' && $raw !== '1') {
            $_SESSION['notifications_settings_error'] = 'Invalid choice.';
            header('Location: /settings/notifications');
            exit;
        }
        $enabled = (int) $raw;

        require_once __DIR__ . '/../repository/UserRepository.php';
        $repo = new UserRepository();
        if (!$repo->setNotificationsEnabledByUserId($userId, $enabled)) {
            $_SESSION['notifications_settings_error'] = 'Could not save preference. Please try again.';
            header('Location: /settings/notifications');
            exit;
        }

        $_SESSION['notifications_settings_success'] = 'Notification preference saved.';
        header('Location: /settings/notifications');
        exit;
    }

    private static function currentUserId(): ?int
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] === '') {
            return null;
        }

        return (int) $_SESSION['user_id'];
    }
}
