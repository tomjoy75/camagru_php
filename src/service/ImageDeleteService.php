<?php

class ImageDeleteService
{
    public function delete(int $imageId, int $userId): array
    {
        require_once __DIR__ . '/../repository/ImageRepository.php';
        $repo = new ImageRepository();
        $image = $repo->findById($imageId);
        if ($image === null) {
            return ['success' => false, 'error' => 'Image not found'];
        }
        if ((int) $image['user_id'] !== $userId) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        $deleted = $repo->deleteById($imageId);
        if (!$deleted) {
            return ['success' => false, 'error' => 'Failed to delete image'];
        }
        $relativePath = $image['image_path'];
        if ($relativePath === null || trim($relativePath) === '') {
            return ['success' => false, 'error' => 'Image path is required'];
        }
        else if (strpos($relativePath, '..') !== false) {
            return ['success' => false, 'error' => 'Invalid image path'];
        }
        $absolutePath = __DIR__ . '/../../public/' . $relativePath;
        if (file_exists($absolutePath)) {
            $deleted = unlink($absolutePath);
            if (!$deleted) {
                return ['success' => false, 'error' => 'Failed to delete image file'];
            }
        }
        return ['success' => true, 'message' => 'Image deleted successfully'];
    }
}