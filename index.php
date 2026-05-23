<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertralina Cine - Cartelera</title>
    </head>
<body>

    <header>
        <h1>Sertralina Cine</h1>
        <nav>
            <ul>
                <li><a href="index.php">Cartelera</a></li>
                <li><a href="admin/index.php">Administración</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h2>Cartelera de Películas</h2>
        
        <section id="cartelera">
            <?php
            // 1. Incluyes las clases
            require_once 'config/Database.php';
            require_once 'classes/Pelicula.php';

            // 2. Instancias la base de datos y obtienes la conexión
            $database = new Database();
            $db = $database->getConnection();

            // 3. Instancias el objeto Película pasándole la conexión
            $pelicula = new Pelicula($db);

            // 4. Llamas al método
            $stmt = $pelicula->listarCartelera();

            // 5. Dibujas los resultados con un ciclo while
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                echo "<article class='pelicula'>";
                echo "<img src='{$poster}' alt='{$titulo}' width='150'>";
                echo "<h3>{$titulo}</h3>";
                echo "<p>Duración: {$duracion} min</p>";
                echo "<a href='reserva.php?id={$id_pelicula}'>Ver horarios y reservar</a>";
                echo "</article>";
            }
            ?>
            
            
        </section>
    </main>

    <footer>
        <p>&copy; 2026 Sertralina Cine - Proyecto Cristobal y Alvaro</p>
    </footer>

</body>
</html>