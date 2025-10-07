<?php

declare(strict_types=1);


final class Database
{
    private function __construct()
    {
    }

    public static function connect(): PDO
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $dbName = getenv('DB_NAME') ?: 'reports_sample';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') ?: '';
        $port = getenv('DB_PORT') ?: '3306';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        return new PDO($dsn, $username, $password, $options);
    }

    public static function tryConnect(): ?PDO
    {
        try {
            return self::connect();
        } catch (PDOException $exception) {
            return null;
        }
    }
}
