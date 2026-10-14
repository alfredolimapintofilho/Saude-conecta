<?php
declare(strict_types=1);

class Database
{
    private static string $host = 'localhost';
    private static string $db_name = 'saude_conecta';
    private static string $username = 'root';
    private static string $password = '';

    private static ?PDO $conn = null;

    public static function getConnection(): PDO
    {
        if (self::$conn === null) {
            $dsn = 'mysql:host=' . self::$host
                . ';dbname=' . self::$db_name
                . ';charset=utf8mb4';

            self::$conn = new PDO(
                $dsn,
                self::$username,
                self::$password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }

        return self::$conn;
    }
}