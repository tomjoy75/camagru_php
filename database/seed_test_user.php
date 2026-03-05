<?php
/**
 * One-off script: insert test user. Run from project root: php database/seed_test_user.php
 * Requires DB_* env vars (e.g. from .env) and existing users table.
 */

require __DIR__ . '/../src/db/Database.php';

$pdo = Database::getConnection();
$hash = password_hash('test', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO users (email, username, password_hash, notifications_enabled) VALUES (:email, :username, :password_hash, :notifications_enabled)');
$stmt->execute([
    ':email' => 'test@mail.com',
    ':username' => 'testuser',
    ':password_hash' => $hash,
    ':notifications_enabled' => 1,
]);
echo "Test user created: test@mail.com (password: test)\n";
