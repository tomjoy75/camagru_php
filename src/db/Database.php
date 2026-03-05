<?php
/**
 * Minimal PDO connection helper (singleton). SQLite; DB file at database/camagru.db.
 */

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $path = __DIR__ . '/../../database/camagru.db';
            $dsn = 'sqlite:' . $path;
            self::$connection = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$connection;
    }
}
