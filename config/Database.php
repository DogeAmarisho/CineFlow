<?php

class Database {
    private $host = "localhost";
    private $db_name = "sertralina_db"; // Asegúrate de crearla con este nombre en phpMyAdmin
    private $username = "root";
    private $password = "";
    public $conn;

    // Método para obtener la conexión
    public function getConnection() {
        $this->conn = null;

        try {
            // Se crea la instancia PDO
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8", $this->username, $this->password);
            
            // Configuración para que PDO lance excepciones si hay errores SQL
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
?>