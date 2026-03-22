<?php
/**
 * Repository for the images table. DB access only; no business logic.
 */
class ImageRepository
{
    public function insert(int $userId, string $imagePath): bool
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO images (user_id, image_path) VALUES (:user_id, :image_path)');
        return $stmt->execute([
            ':user_id' => $userId,
            ':image_path' => $imagePath,
        ]);
    }

    /**
     * @return list<array{id: int|string, image_path: string, created_at: string}>
     */
    public function findRecentByUserId(int $userId, int $limit): array
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, image_path, created_at FROM images WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }
}
