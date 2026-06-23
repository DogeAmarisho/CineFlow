<?php
/**
 * Pagina de inicio. Solo el hero con los botones principales,
 * no consulta la base de datos.
 */

require_once __DIR__ . '/config.php';

$titulo_pagina = 'Inicio';
$nav_activo    = '';
render_header();
?>

<main>

    <section class="hero" aria-label="Bienvenida">

        <!-- Panel izquierdo -->
        <div class="hero-lateral hero-lateral-izq">
            <div class="hero-card">
                <span class="icono">🎬</span>
                <div>
                    <strong>4 películas</strong>
                    en cartelera hoy
                </div>
            </div>
            <div class="hero-card">
                <span class="icono">🏆</span>
                <div>
                    <strong>Sala VIP</strong>
                    disponible
                </div>
            </div>
            <div class="hero-card">
                <span class="icono">⚡</span>
                <div>
                    <strong>Reserva en segundos</strong>
                    sin registro
                </div>
            </div>
        </div>

        <!-- Centro -->
        <div class="hero-contenido">
            <span class="hero-chip">🎬 Tu cine en línea</span>

            <h1>
                La mejor forma de<br>
                vivir el <span class="acento">cine</span>
            </h1>

            <p>
                Elige tu película, selecciona tu asiento y compra tu entrada
                en segundos. Sin filas, sin esperas.
            </p>

            <div class="hero-acciones">
                <a href="cartelera.php" class="btn btn-primario btn-lg">
                    Ver cartelera
                </a>
                <a href="consultar-reserva.php" class="btn btn-secundario btn-lg">
                    Mis reservas
                </a>
            </div>
        </div>

        <!-- Panel derecho -->
        <div class="hero-lateral hero-lateral-der">
            <div class="hero-card">
                <span class="icono">🎟️</span>
                <div>
                    <strong>Código único</strong>
                    por cada reserva
                </div>
            </div>
            <div class="hero-card">
                <span class="icono">🪑</span>
                <div>
                    <strong>Hasta 6 asientos</strong>
                    por reserva
                </div>
            </div>
            <div class="hero-card">
                <span class="icono">📍</span>
                <div>
                    <strong>4 salas</strong>
                    incluyendo 4DX y VIP
                </div>
            </div>
        </div>

    </section>
</main>

<?php render_footer(); ?>
