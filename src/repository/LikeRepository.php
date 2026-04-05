<?php
/**
 * Repository for the likes table. DB access only; no business logic.
 */
class LikeRepository
{
    public function hasLiked(int $userId, int $imageId): bool
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM likes WHERE user_id = ? AND image_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $imageId]);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(int $userId, int $imageId): bool
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO likes (user_id, image_id) VALUES (?, ?)'
        );
        return $stmt->execute([$userId, $imageId]);
    }

    public function deleteByUserAndImage(int $userId, int $imageId): bool
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'DELETE FROM likes WHERE user_id = ? AND image_id = ?'
        );
        $stmt->execute([$userId, $imageId]);
        return $stmt->rowCount() > 0;
    }
}
