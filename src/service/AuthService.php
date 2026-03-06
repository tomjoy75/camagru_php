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
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email is invalid.';
        } else {
            require_once __DIR__ . '/../repository/UserRepository.php';
            $repo = new UserRepository();
            if ($repo->findByEmail($email) !== null) {
                $errors['email'] = 'Email is already in use.';
            }
        }

        $username = trim($username);
        if ($username === '') {
            $errors['username'] = 'Username is required.';
        } elseif (strlen($username) < self::USERNAME_MIN_LENGTH) {
            $errors['username'] = 'Username must be at least ' . self::USERNAME_MIN_LENGTH . ' characters.';
        } elseif (strlen($username) > self::USERNAME_MAX_LENGTH) {
            $errors['username'] = 'Username must be at most ' . self::USERNAME_MAX_LENGTH . ' characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors['username'] = 'Username may only contain letters, numbers and underscore.';
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors['password'] = 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
        }

        if ($password !== $passwordConfirmation) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if ($errors === []) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            require_once __DIR__ . '/../repository/UserRepository.php';
            $repo = new UserRepository();
            try {
                $repo->createUser($email, $username, $passwordHash);
            } catch (PDOException $e) {
                $errors['form'] = 'Registration failed. Please try again.';
            }
        }

        return $errors;
    }
}
