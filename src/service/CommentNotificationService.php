<?php
/**
 * Comment-on-image notification orchestration: when to notify the image owner (no email until #37).
 */
class CommentNotificationService
{
    /**
     * Whether the owner should receive a notification attempt for this comment event.
     *
     * @param  ?int $notificationsEnabled null if user missing; 0 = off; 1 = on
     */
    public static function shouldNotify(int $ownerUserId, int $commenterUserId, ?int $notificationsEnabled): bool
    {
        if ($ownerUserId === $commenterUserId) {
            return false;
        }
        if ($notificationsEnabled === null) {
            return false;
        }

        return $notificationsEnabled === 1;
    }

    /**
     * Delivery entry point for #37; today logs ids only (observable hook for tests).
     */
    public static function notifyImageOwnerOfComment(
        int $recipientUserId,
        int $imageId,
        int $commentId,
        int $commenterUserId
    ): void {
        error_log(
            'camagru_comment_notification recipient='
            . $recipientUserId
            . ' image='
            . $imageId
            . ' comment='
            . $commentId
            . ' commenter='
            . $commenterUserId
        );
    }

    /**
     * After a successful comment insert: load owner preference, optionally invoke {@see notifyImageOwnerOfComment}.
     * Never throws to the caller.
     */
    public static function tryNotifyOnNewComment(
        int $ownerUserId,
        int $commenterUserId,
        int $imageId,
        int $commentId
    ): void {
        try {
            require_once __DIR__ . '/../repository/UserRepository.php';
            $users = new UserRepository();
            $enabled = $users->getNotificationsEnabledByUserId($ownerUserId);
            if (!self::shouldNotify($ownerUserId, $commenterUserId, $enabled)) {
                return;
            }
            self::notifyImageOwnerOfComment($ownerUserId, $imageId, $commentId, $commenterUserId);
        } catch (Throwable $e) {
            // Comment success path must not depend on notifications
        }
    }
}
