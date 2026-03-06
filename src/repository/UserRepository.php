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
}
