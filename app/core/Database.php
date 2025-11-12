<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'reportes';
    private $username = 'root'; // Usuario por defecto de XAMPP
    private $password = '';     // Password por defecto de XAMPP
    private $conn;

    public function connect() {
        $this->conn = null;

        try {
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8';
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo 'Connection Error: ' . $e->getMessage();
            exit; // Detener la aplicación si no se puede conectar
        }

        return $this->conn;
    }
}
?>