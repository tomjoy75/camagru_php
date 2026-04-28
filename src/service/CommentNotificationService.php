<?php
/**
 * Comment-on-image notification: email image owner when #36 hook fires (preference on, not self-comment).
 */
require_once __DIR__ . '/MailEnv.php';

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
     * Send plain-text email to the image owner with a link to the gallery detail page.
     * Never throws. Does not log recipient addresses.
     */
    public static function notifyImageOwnerOfComment(
        int $recipientUserId,
        int $imageId,
        int $commentId,
        int $commenterUserId
    ): void {
        try {
            $baseUrl = MailEnv::validatedAppBaseUrl();
            if ($baseUrl === null) {
                error_log('camagru_notify_mail_skipped reason=missing_app_base_url image_id=' . $imageId . ' comment_id=' . $commentId);
                return;
            }

            $from = MailEnv::validatedMailFrom();
            if ($from === null) {
                error_log('camagru_notify_mail_skipped reason=missing_mail_from image_id=' . $imageId . ' comment_id=' . $commentId);
                return;
            }

            require_once __DIR__ . '/../repository/UserRepository.php';
            $users = new UserRepository();
            $to = $users->getEmailByUserId($recipientUserId);
            if ($to === null || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
                error_log('camagru_notify_mail_skipped reason=invalid_recipient image_id=' . $imageId . ' comment_id=' . $commentId);
                return;
            }

            $commenterName = $users->getUsernameByUserId($commenterUserId);
            if ($commenterName === null) {
                $commenterName = 'Someone';
            } else {
                $commenterName = str_replace(["\r", "\n"], ' ', $commenterName);
            }

            $imageUrl = $baseUrl . '/gallery/image?id=' . $imageId;
            $subject = 'Camagru: new comment on your image #' . $imageId;
            $body = "Hello,\n\n"
                . $commenterName
                . " commented on your image.\n\n"
                . 'View it: '
                . $imageUrl
                . "\n";

            $headerLines = ['From: ' . $from];
            $replyTo = MailEnv::validatedMailReplyTo();
            if ($replyTo !== null) {
                $headerLines[] = 'Reply-To: ' . $replyTo;
            }
            $headers = implode("\r\n", $headerLines);

            $mailOk = @mail($to, $subject, $body, $headers);
            if ($mailOk) {
                error_log('camagru_notify_mail_ok image_id=' . $imageId . ' comment_id=' . $commentId);
            } else {
                error_log('camagru_notify_mail_failed image_id=' . $imageId . ' comment_id=' . $commentId);
            }
        } catch (Throwable $e) {
            error_log('camagru_notify_mail_exception image_id=' . $imageId);
        }
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
            if ($ownerUserId < 1) {
                error_log('camagru_notify_mail_skipped reason=invalid_owner image_id=' . $imageId . ' comment_id=' . $commentId);
                return;
            }
            require_once __DIR__ . '/../repository/UserRepository.php';
            $users = new UserRepository();
            $enabled = $users->getNotificationsEnabledByUserId($ownerUserId);
            if (!self::shouldNotify($ownerUserId, $commenterUserId, $enabled)) {
                if ($ownerUserId === $commenterUserId) {
                    error_log('camagru_notify_mail_skipped reason=self_comment image_id=' . $imageId . ' comment_id=' . $commentId);
                } else if ($enabled === 0) {
                    error_log('camagru_notify_mail_skipped reason=preference_off image_id=' . $imageId . ' comment_id=' . $commentId);
                } else {
                    error_log('camagru_notify_mail_skipped reason=owner_missing_or_unknown_pref image_id=' . $imageId . ' comment_id=' . $commentId);
                }
                return;
            }
            error_log('camagru_notify_mail_attempt image_id=' . $imageId . ' comment_id=' . $commentId);
            self::notifyImageOwnerOfComment($ownerUserId, $imageId, $commentId, $commenterUserId);
        } catch (Throwable $e) {
            // Comment success path must not depend on notifications
            error_log('camagru_notify_mail_exception image_id=' . $imageId . ' comment_id=' . $commentId . ' stage=try_notify');
        }
    }
}
