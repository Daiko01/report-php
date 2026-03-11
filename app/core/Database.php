<?php
class Database
{
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct()
    {
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? 'db_transreport_sol';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';
    }



    public function connect()
    {
        // Singleton: reutilizar conexión dentro del mismo request
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            // utf8mb4 para soporte Unicode completo; EMULATE_PREPARES=false para prepared statements reales en MySQL
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8mb4';
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            die('Lo sentimos, estamos experimentando problemas técnicos. Por favor, intente más tarde.');
        }

        return $this->conn;
    }
}
