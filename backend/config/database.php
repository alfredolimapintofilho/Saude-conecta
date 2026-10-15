<?php

class Database
{
    private static $host = '127.0.0.1';
    private static $port = '3306';
    private static $db_name = 'saude_conecta';
    private static $username = 'root';
    private static $password = '';

    private static $conn = null;

    /**
     * Retorna uma conexão PDO com o banco de dados.
     *
     * @return PDO
     * @throws RuntimeException Se não for possível conectar.
     */
    public static function getConnection(): PDO
    {
        // Reutiliza a conexão se ela já estiver disponível.
        if (self::$conn instanceof PDO) {
            return self::$conn;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            self::$host,
            self::$port,
            self::$db_name
        );

        try {
            self::$conn = new PDO(
                $dsn,
                self::$username,
                self::$password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false
                ]
            );

            return self::$conn;

        } catch (PDOException $e) {
            // Registra o erro detalhado no log do PHP.
            error_log(
                '[Saúde-Conecta] Erro ao conectar ao MySQL: '
                . $e->getMessage()
            );

            // Evita mostrar detalhes internos da conexão ao usuário.
            throw new RuntimeException(
                'Não foi possível conectar ao banco de dados. '
                . 'Verifique se o MySQL está iniciado e confira '
                . 'o nome do banco, a porta e as credenciais.',
                0,
                $e
            );
        }
    }

    /**
     * Fecha a conexão atual, se necessário.
     */
    public static function closeConnection(): void
    {
        self::$conn = null;
    }
}