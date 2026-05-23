<?php

class Pelicula {
    // Conexión a la base de datos y nombre de la tabla
    private $conn;
    private $table_name = "peliculas";

    // Atributos del objeto (Iguales a los del Diagrama de Clases)
    public $id_pelicula;
    public $titulo;
    public $sinopsis;
    public $duracion;
    public $clasificacion;
    public $poster;

    // El constructor recibe la conexión a la base de datos
    public function __construct($db) {
        $this->conn = $db;
    }

    // Método para obtener todas las películas para el index.php
    public function listarCartelera() {
        // Consulta SQL limpia
        $query = "SELECT id_pelicula, titulo, sinopsis, duracion, clasificacion, poster FROM " . $this->table_name;

        // Se prepara la sentencia (Evita Inyección SQL)
        $stmt = $this->conn->prepare($query);

        // Se ejecuta la consulta
        $stmt->execute();

        // Retorna el resultado
        return $stmt;
    }
    
    // Método para obtener los detalles de una sola película (para cuando el usuario hace clic)
    public function obtenerDetalle($id) {
        $query = "SELECT titulo, sinopsis, duracion, poster FROM " . $this->table_name . " WHERE id_pelicula = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        // Si encuentra la película, asigna los valores a los atributos del objeto
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $this->titulo = $row['titulo'];
            $this->sinopsis = $row['sinopsis'];
            $this->duracion = $row['duracion'];
            $this->poster = $row['poster'];
            return true;
        }
        return false;
    }
}
?>