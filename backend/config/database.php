<?php

class Database
{
    private static $connection = null;

    public static function getConnection()
    {
        if (self::$connection === null) {

            $host = 'localhost';
            $dbname = 'saude_conecta';
            $username = 'root';
            $password = '';

            try {

                self::$connection = new PDO(
                    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                    $username,
                    $password
                );

                self::$connection->setAttribute(
                    PDO::ATTR_ERRMODE,
                    PDO::ERRMODE_EXCEPTION
                );

                self::$connection->setAttribute(
                    PDO::ATTR_DEFAULT_FETCH_MODE,
                    PDO::FETCH_ASSOC
                );

            } catch (PDOException $e) {

                die(
                    'Erro na conexão com o banco de dados: ' .
                    $e->getMessage()
                );
            }
        }

        return self::$connection;
    }
}