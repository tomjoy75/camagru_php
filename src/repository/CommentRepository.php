<?php
/**
 * Repository for the comments table. DB access only; no business logic.
 */
class CommentRepository
{
    public function imageExists(int $imageId): bool
    {
        if ($imageId <= 0) {
            return false;
        }
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM images WHERE id = ? LIMIT 1');
        $stmt->execute([$imageId]);
        return (bool) $stmt->fetchColumn();
    }

    public function userExists(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return positive int new comment id, or null on failure (invalid refs, DB error)
     */
    public function insert(int $userId, int $imageId, string $content): ?int
    {
        if (!$this->imageExists($imageId) || !$this->userExists($userId)) {
            return null;
        }
        require_once __DIR__ . '/../db/Database.php';
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO comments (user_id, image_id, content) VALUES (?, ?, ?)'
            );
            if (!$stmt->execute([$userId, $imageId, $content])) {
                return null;
            }
            $id = $pdo->lastInsertId();
            if ($id === false) {
                return null;
            }
            $n = (int) $id;
            return $n > 0 ? $n : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
