<?php
/**
 * Shared getenv validation for APP_BASE_URL and mail envelope headers.
 * Used by mail-related services only.
 */
class MailEnv
{
    public static function validatedAppBaseUrl(): ?string
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

    public static function validatedMailFrom(): ?string
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

    public static function validatedMailReplyTo(): ?string
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
