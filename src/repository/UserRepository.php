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
        $stmt = $pdo->prepare('SELECT id, email, username, password_hash, notifications_enabled, created_at FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createUser(string $email, string $username, string $passwordHash): int
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO users (email, username, password_hash) VALUES (:email, :username, :password_hash)');
        $stmt->execute([
            ':email' => $email,
            ':username' => $username,
            ':password_hash' => $passwordHash,
        ]);
        return (int) $pdo->lastInsertId();
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
