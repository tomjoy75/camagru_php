<?php
/**
 * Minimal PDO connection helper (singleton).
 * Connection params: DB_HOST, DB_NAME, DB_USER, DB_PASS (e.g. from .env).
 */

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $name = getenv('DB_NAME') ?: 'camagru';
            $user = getenv('DB_USER') ?: '';
            $pass = getenv('DB_PASS') ?: '';
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            self::$connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$connection;
    }
}
/**
 * PDO fournit une interface standard pour les bases de données
 * et permet l'utilisation de prepared statements
 * qui protègent contre les SQL injections.
 */
