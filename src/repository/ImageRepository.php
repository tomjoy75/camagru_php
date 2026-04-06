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

    /**
     * @param int|null $filterUserId positive user id restricts to that author (must exist in users); null = all images
     */
    public function countForPublicGallery(?int $filterUserId = null): int
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        if ($filterUserId === null) {
            $stmt = $pdo->query('SELECT COUNT(*) FROM images');
        } else {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM images i '
                . 'INNER JOIN users u ON u.id = i.user_id WHERE i.user_id = :user_id'
            );
            $stmt->bindValue(':user_id', $filterUserId, PDO::PARAM_INT);
            $stmt->execute();
        }
        $n = $stmt->fetchColumn();
        return (int) $n;
    }

    /**
     * One page of images for public gallery.
     *
     * @param int|null $filterUserId same semantics as countForPublicGallery()
     * @param string $gallerySort one of: newest, oldest, likes, comments
     * @return list<array{id: int|string, image_path: string, created_at: string, like_count: int|string}>
     */
    public function findPageForPublicGallery(int $limit, int $offset, ?int $filterUserId = null, string $gallerySort = 'newest'): array
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $likeExpr = '(SELECT COUNT(*) FROM likes l WHERE l.image_id = i.id)';
        $commentExpr = '(SELECT COUNT(*) FROM comments c WHERE c.image_id = i.id)';
        $base = 'SELECT i.id, i.image_path, i.created_at, ' . $likeExpr . ' AS like_count FROM images i ';

        switch ($gallerySort) {
            case 'oldest':
                $orderBy = 'ORDER BY i.created_at ASC, i.id ASC';
                break;
            case 'likes':
                $orderBy = 'ORDER BY like_count DESC, i.created_at DESC, i.id DESC';
                break;
            case 'comments':
                $orderBy = 'ORDER BY ' . $commentExpr . ' DESC, i.created_at DESC, i.id DESC';
                break;
            case 'newest':
            default:
                $orderBy = 'ORDER BY i.created_at DESC, i.id DESC';
                break;
        }

        if ($filterUserId === null) {
            $sql = $base . $orderBy . ' LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
        } else {
            $sql = $base . 'INNER JOIN users u ON u.id = i.user_id WHERE i.user_id = :user_id '
                . $orderBy . ' LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':user_id', $filterUserId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    public function findById(int $imageId): ?array
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, image_path, user_id FROM images WHERE id = ?'
        );
        $stmt->execute([$imageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * One public image with author and engagement counts (read-only gallery detail).
     *
     * @return array{id: int|string, image_path: string, created_at: string, username: string, like_count: int|string, comment_count: int|string}|null
     */
    public function findPublicDetailById(int $imageId): ?array
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $sql = 'SELECT i.id, i.image_path, i.created_at, u.username AS username, '
            . '(SELECT COUNT(*) FROM likes l WHERE l.image_id = i.id) AS like_count, '
            . '(SELECT COUNT(*) FROM comments c WHERE c.image_id = i.id) AS comment_count '
            . 'FROM images i INNER JOIN users u ON u.id = i.user_id WHERE i.id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$imageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    public function deleteById(int $imageId): bool
    {
        require_once __DIR__ . '/../db/Database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'DELETE FROM images WHERE id = ?'
        );
        return $stmt->execute([$imageId]) ? true : false;
    }
}
