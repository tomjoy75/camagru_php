<?php
/**
 * Transactional email after registration: confirmation link (#48). Never throws.
 */
class RegistrationConfirmationMailService
{
    /**
     * Send one plain-text email with absolute confirm URL. Registration success must not depend on this.
     */
    public static function trySendRegistrationConfirmation(string $recipientEmail, string $plainToken): void
    {
        try {
            $baseUrl = self::validatedAppBaseUrl();
            if ($baseUrl === null) {
                return;
            }

            $from = self::validatedMailFrom();
            if ($from === null) {
                return;
            }

            $to = trim($recipientEmail);
            if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
                return;
            }

            if ($plainToken === '' || !preg_match('/^[a-f0-9]{64}$/', $plainToken)) {
                return;
            }

            $confirmUrl = $baseUrl . '/register/confirm?token=' . rawurlencode($plainToken);
            $subject = 'Camagru: confirm your email';
            $body = "Hello,\n\n"
                . 'Confirm your email address by opening this link:'
                . "\n\n"
                . $confirmUrl
                . "\n\n"
                . "If you did not register, you can ignore this message.\n";

            $headerLines = ['From: ' . $from];
            $replyTo = self::validatedMailReplyTo();
            if ($replyTo !== null) {
                $headerLines[] = 'Reply-To: ' . $replyTo;
            }
            $headers = implode("\r\n", $headerLines);

            $mailOk = @mail($to, $subject, $body, $headers);
            if ($mailOk) {
                error_log('camagru_confirm_mail_ok');
            } else {
                error_log('camagru_confirm_mail_failed');
            }
        } catch (Throwable $e) {
            error_log('camagru_confirm_mail_exception');
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
