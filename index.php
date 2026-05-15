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
                // Aquí más adelante llamaremos a la Clase Pelicula
                // Por ahora, simulamos una película manual para ver la estructura
            ?>
            
            <article class="pelicula">
                <img src="assets/img/peli-ejemplo.jpg" alt="Poster de Película" width="150">
                <h3>Título de la Película</h3>
                <p>Duración: 120 min</p>
                <p>Género: Acción / Ciencia Ficción</p>
                <a href="reserva.php?id=1">Ver horarios y reservar</a>
            </article>
            
            <article class="pelicula">
                <img src="assets/img/peli-ejemplo-2.jpg" alt="Poster de Película" width="150">
                <h3>Título de la Película 2</h3>
                <p>Duración: 105 min</p>
                <p>Género: Comedia</p>
                <a href="reserva.php?id=2">Ver horarios y reservar</a>
            </article>
        </section>
    </main>

    <footer>
        <p>&copy; 2026 Sertralina Cine - Proyecto Cristobal y Alvaro</p>
    </footer>

</body>
</html>