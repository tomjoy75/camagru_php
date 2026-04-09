<?php
/**
 * Transactional email after registration: confirmation link (#48). Never throws.
 */
require_once __DIR__ . '/MailEnv.php';

class RegistrationConfirmationMailService
{
    /**
     * Send one plain-text email with absolute confirm URL. Registration success must not depend on this.
     */
    public static function trySendRegistrationConfirmation(string $recipientEmail, string $plainToken): void
    {
        try {
            $baseUrl = MailEnv::validatedAppBaseUrl();
            if ($baseUrl === null) {
                return;
            }

            $from = MailEnv::validatedMailFrom();
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
            $replyTo = MailEnv::validatedMailReplyTo();
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
}
