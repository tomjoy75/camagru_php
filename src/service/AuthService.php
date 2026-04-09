<?php
/**
 * Service for auth-related business logic: validation and registration rules.
 */
class AuthService
{
    private const MIN_PASSWORD_LENGTH = 8;
    private const USERNAME_MIN_LENGTH = 3;
    private const USERNAME_MAX_LENGTH = 50;

    /**
     * Validates registration input. Returns an array of field => error message.
     * Empty array means all valid.
     */
    public static function register(
        string $email,
        string $username,
        string $password,
        string $passwordConfirmation
    ): array {
        $errors = [];

        $email = trim($email);
        $emailError = self::validateEmailFormat($email);
        if ($emailError !== null) {
            $errors['email'] = $emailError;
        } else {
            require_once __DIR__ . '/../repository/UserRepository.php';
            $repo = new UserRepository();
            if ($repo->findByEmail($email) !== null) {
                $errors['email'] = 'Email is already in use.';
            }
        }

        $username = trim($username);
        $usernameError = self::validateUsername($username);
        if ($usernameError !== null) {
            $errors['username'] = $usernameError;
        } else {
            require_once __DIR__ . '/../repository/UserRepository.php';
            $repo = new UserRepository();
            if ($repo->findByUsername($username) !== null) {
                $errors['username'] = 'Username is already in use.';
            }
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors['password'] = 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
        }

        if ($password !== $passwordConfirmation) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if ($errors === []) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $confirmationToken = bin2hex(random_bytes(32));
            require_once __DIR__ . '/../repository/UserRepository.php';
            require_once __DIR__ . '/RegistrationConfirmationMailService.php';
            $repo = new UserRepository();
            try {
                $repo->createUser($email, $username, $passwordHash, $confirmationToken);
            } catch (PDOException $e) {
                $errors['form'] = 'Registration failed. Please try again.';
            }
            if ($errors === []) {
                RegistrationConfirmationMailService::trySendRegistrationConfirmation($email, $confirmationToken);
            }
        }

        return $errors;
    }

    /**
     * Validates username update for an authenticated user.
     * Returns ['errors' => array, 'username' => normalized username].
     */
    public static function updateUsername(int $userId, string $username): array
    {
        $errors = [];
        $username = trim($username);
        $usernameError = self::validateUsername($username);
        if ($usernameError !== null) {
            $errors['username'] = $usernameError;

            return ['errors' => $errors, 'username' => $username];
        }

        require_once __DIR__ . '/../repository/UserRepository.php';
        $repo = new UserRepository();
        if ($repo->isUsernameUsedByOtherUser($username, $userId)) {
            $errors['username'] = 'Username is already in use.';

            return ['errors' => $errors, 'username' => $username];
        }
        if (!$repo->updateUsernameByUserId($userId, $username)) {
            $errors['form'] = 'Could not update username. Please try again.';
        }

        return ['errors' => $errors, 'username' => $username];
    }

    /**
     * Validates email update for an authenticated user.
     * Returns ['errors' => array, 'email' => normalized email].
     */
    public static function updateEmail(int $userId, string $email): array
    {
        $errors = [];
        $email = trim($email);
        $emailError = self::validateEmailFormat($email);
        if ($emailError !== null) {
            $errors['email'] = $emailError;

            return ['errors' => $errors, 'email' => $email];
        }

        require_once __DIR__ . '/../repository/UserRepository.php';
        $repo = new UserRepository();
        if ($repo->isEmailUsedByOtherUser($email, $userId)) {
            $errors['email'] = 'Email is already in use.';

            return ['errors' => $errors, 'email' => $email];
        }

        try {
            $confirmationToken = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $errors['form'] = 'Could not update email. Please try again.';

            return ['errors' => $errors, 'email' => $email];
        }

        if (!$repo->updateEmailAndResetVerificationByUserId($userId, $email, $confirmationToken)) {
            $errors['form'] = 'Could not update email. Please try again.';
        }

        return ['errors' => $errors, 'email' => $email];
    }

    /**
     * Validates credentials. Returns ['errors' => array, 'user' => array|null].
     * On success: errors empty, user set. On failure: errors set, user null.
     */
    public static function login(string $email, string $password): array
    {
        $errors = [];
        $email = trim($email);
        if ($email === '') {
            $errors['form'] = 'Email is required.';
            return ['errors' => $errors, 'user' => null];
        }
        require_once __DIR__ . '/../repository/UserRepository.php';
        $repo = new UserRepository();
        $user = $repo->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            $errors['form'] = 'Invalid email or password.';
            return ['errors' => $errors, 'user' => null];
        }
        if ((int) ($user['email_verified'] ?? 0) !== 1) {
            $errors['email_verification'] = 'Please verify your email before signing in. Use the link from your confirmation message.';

            return ['errors' => $errors, 'user' => null];
        }

        return ['errors' => [], 'user' => $user];
    }

    private static function validateUsername(string $username): ?string
    {
        if ($username === '') {
            return 'Username is required.';
        }
        if (strlen($username) < self::USERNAME_MIN_LENGTH) {
            return 'Username must be at least ' . self::USERNAME_MIN_LENGTH . ' characters.';
        }
        if (strlen($username) > self::USERNAME_MAX_LENGTH) {
            return 'Username must be at most ' . self::USERNAME_MAX_LENGTH . ' characters.';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return 'Username may only contain letters, numbers and underscore.';
        }

        return null;
    }

    private static function validateEmailFormat(string $email): ?string
    {
        if ($email === '') {
            return 'Email is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Email is invalid.';
        }

        return null;
    }
}
