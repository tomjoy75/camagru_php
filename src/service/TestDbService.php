<?php
/**
 * Service for testing the database connection.
 */
class TestDbService
{
    public static function getMessage(?string $email): string
    {
        if ($email === null || trim($email) === '') {
            return 'No email provided. Use ?email=...';
        }
        require_once __DIR__ . '/../repository/UserRepository.php';
        $repo = new UserRepository();
        $user = $repo->findByEmail($email);
        if ($user === null) {
            return 'User not found.';
        }
        return 'Database connection test: ' . $user['username'];
    }
}