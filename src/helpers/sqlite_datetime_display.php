<?php
/**
 * SQLite stores CURRENT_TIMESTAMP in UTC. Format for wall-clock display in a chosen timezone.
 *
 * Set APP_TIMEZONE (e.g. Europe/Paris, America/New_York) in the environment; defaults to Europe/Paris.
 */
declare(strict_types=1);

function format_sqlite_utc_datetime_for_display(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    $displayTz = getenv('APP_TIMEZONE');
    if (!is_string($displayTz) || $displayTz === '') {
        $displayTz = 'Europe/Paris';
    }
    try {
        $dt = new DateTimeImmutable($raw, new DateTimeZone('UTC'));

        return $dt->setTimezone(new DateTimeZone($displayTz))->format('M j, Y \a\t g:i A');
    } catch (Throwable $e) {
        return $raw;
    }
}
