<?php
/**
 * Comment-on-image notification: email image owner when #36 hook fires (preference on, not self-comment).
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
            $baseUrl = self::validatedAppBaseUrl();
            if ($baseUrl === null) {
                return;
            }

            $from = self::validatedMailFrom();
            if ($from === null) {
                return;
            }

            require_once __DIR__ . '/../repository/UserRepository.php';
            $users = new UserRepository();
            $to = $users->getEmailByUserId($recipientUserId);
            if ($to === null || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
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
            $replyTo = self::validatedMailReplyTo();
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

    private static function validatedAppBaseUrl(): ?string
    {
        $raw = getenv('APP_BASE_URL');
        if ($raw === false || $raw === '') {
            return null;
        }
        $base = rtrim(trim($raw), '/');
        if ($base === '' || filter_var($base, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $scheme = parse_url($base, PHP_URL_SCHEME);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
        if (!parse_url($base, PHP_URL_HOST)) {
            return null;
        }

        return $base;
    }

    private static function validatedMailFrom(): ?string
    {
        $raw = getenv('APP_MAIL_FROM');
        if ($raw === false || $raw === '') {
            return null;
        }
        $from = trim($raw);
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $from;
    }

    private static function validatedMailReplyTo(): ?string
    {
        $raw = getenv('APP_MAIL_REPLY_TO');
        if ($raw === false || $raw === '') {
            return null;
        }
        $addr = trim($raw);
        if (filter_var($addr, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $addr;
    }
}
