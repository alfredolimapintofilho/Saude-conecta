<?php

class Database {
    private static $host = 'localhost';
    private static $db_name = 'saude_conecta';   // ← nome do banco
    private static $username = 'root';
    private static $password = '';               // senha vazia (padrão do XAMPP)
    private static $conn = null;

    public static function getConnection() {
        if (self::$conn === null) {
            try {
                self::$conn = new PDO(
                    "mysql:host=" . self::$host . ";dbname=" . self::$db_name . ";charset=utf8mb4",
                    self::$username,
                    self::$password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            } catch (PDOException $exception) {
                die(json_encode(["error" => "Erro na conexão com o banco: " . $exception->getMessage()]));
            }
        }
        return self::$conn;
    }
}