<?php
/**
 * Repository for the users table. DB access only; no business logic.
 */

class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, email, username, password_hash, notifications_enabled, email_verified, confirmation_token, created_at FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, email, username, password_hash, notifications_enabled, email_verified, confirmation_token, created_at FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function isUsernameUsedByOtherUser(string $username, int $userId): bool
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = :username AND id != :id LIMIT 1');
        $stmt->execute([':username' => $username, ':id' => $userId]);

        return $stmt->fetch() !== false;
    }

    public function updateUsernameByUserId(int $userId, string $username): bool
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE users SET username = :username WHERE id = :id');

        return $stmt->execute([':username' => $username, ':id' => $userId]);
    }

    /**
     * @param non-empty-string $confirmationToken plaintext token stored for email confirmation (#47)
     */
    public function createUser(string $email, string $username, string $passwordHash, string $confirmationToken): int
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO users (email, username, password_hash, email_verified, confirmation_token) VALUES (:email, :username, :password_hash, 0, :confirmation_token)'
        );
        $stmt->execute([
            ':email' => $email,
            ':username' => $username,
            ':password_hash' => $passwordHash,
            ':confirmation_token' => $confirmationToken,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * @return array{id: int, email_verified: int}|null
     */
    public function findIdAndVerifiedByConfirmationToken(string $token): ?array
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, email_verified FROM users WHERE confirmation_token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return ['id' => (int) $row['id'], 'email_verified' => (int) $row['email_verified']];
    }

    public function markEmailVerifiedAndClearToken(int $userId): bool
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE users SET email_verified = 1, confirmation_token = NULL WHERE id = :id AND email_verified = 0'
        );

        return $stmt->execute([':id' => $userId]) && $stmt->rowCount() > 0;
    }

    public function clearConfirmationToken(int $userId): void
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE users SET confirmation_token = NULL WHERE id = :id');
        $stmt->execute([':id' => $userId]);
    }

    /**
     * @return int|null 0 or 1 if the user exists, null if no row
     */
    public function getNotificationsEnabledByUserId(int $userId): ?int
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT notifications_enabled FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return (int) $row['notifications_enabled'] ? 1 : 0;
    }

    /**
     * @param 0|1 $enabled
     */
    public function setNotificationsEnabledByUserId(int $userId, int $enabled): bool
    {
        if ($enabled !== 0 && $enabled !== 1) {
            return false;
        }
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $check = $pdo->prepare('SELECT 1 FROM users WHERE id = :id LIMIT 1');
        $check->execute([':id' => $userId]);
        if ($check->fetch() === false) {
            return false;
        }
        $stmt = $pdo->prepare('UPDATE users SET notifications_enabled = :n WHERE id = :id');

        return $stmt->execute([':n' => $enabled, ':id' => $userId]);
    }

    /**
     * @return non-empty-string|null null if missing row or empty email
     */
    public function getEmailByUserId(int $userId): ?string
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $email = trim((string) $row['email']);

        return $email !== '' ? $email : null;
    }

    /**
     * @return non-empty-string|null null if missing row or empty username
     */
    public function getUsernameByUserId(int $userId): ?string
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT username FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $username = trim((string) $row['username']);

        return $username !== '' ? $username : null;
    }
}
