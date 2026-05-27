<?php
/**
 * ============================================================
 *  CineFlow - Plataforma de Gestión y Venta de Entradas
 * ============================================================
 *  Archivo   : index.php
 *  Propósito : Landing page principal del sitio.
 *              Muestra un hero con CTA, las películas en
 *              cartelera como destacadas y la sección de
 *              "cómo funciona".
 *  Depende de: config.php, includes/funciones.php,
 *              includes/header.php, includes/footer.php
 *  Autores   : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/funciones.php';

// ─────────────────────────────────────────────────────────────
//  Obtener las películas destacadas para el hero
//  (máximo 4, las que tengan funciones próximas)
// ─────────────────────────────────────────────────────────────
function obtenerPeliculasDestacadas(int $limite = 4): array
{
    $pdo = obtenerConexion();
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                p.id,
                p.titulo,
                p.genero,
                p.clasificacion,
                p.duracion_min,
                p.imagen,
                p.sinopsis,
                -- Precio mínimo de las funciones activas
                (
                    SELECT MIN(f2.precio)
                    FROM   funciones f2
                    WHERE  f2.pelicula_id = p.id
                      AND  f2.activa      = 1
                      AND  f2.fecha_hora  >= NOW()
                ) AS precio_desde
            FROM peliculas p
            INNER JOIN funciones f ON f.pelicula_id = p.id
            WHERE p.activa  = 1
              AND f.activa  = 1
              AND f.fecha_hora >= NOW()
            ORDER BY p.fecha_estreno DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        registrarError('index - obtenerPeliculasDestacadas', $e->getMessage());
        return [];
    }
}

$peliculas_destacadas = obtenerPeliculasDestacadas(4);

// ─────────────────────────────────────────────────────────────
//  Renderizado
// ─────────────────────────────────────────────────────────────
$titulo_pagina = 'Inicio';
$nav_activo    = '';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ══ ESTILOS ESPECÍFICOS DE ESTA PÁGINA ════════════════════ -->
<style>
    /* ── Hero ────────────────────────────────────────────────── */
    .hero {
        position:        relative;
        height:          calc(100vh - 72px);
        display:         grid;
        grid-template-columns: 1fr auto 1fr;
        align-items:     center;
        padding:         40px 48px;
        overflow:        hidden;
        background:      linear-gradient(135deg, var(--oscuro) 0%, #0a1628 60%, #0d1b2e 100%);
        gap:             32px;
    }

    /* Destellos de fondo */
    .hero::before,
    .hero::after {
        content:        '';
        position:       absolute;
        border-radius:  50%;
        filter:         blur(100px);
        opacity:        .18;
        pointer-events: none;
    }
    .hero::before {
        width:      600px;
        height:     600px;
        background: var(--primario);
        top:        -200px;
        left:       -100px;
    }
    .hero::after {
        width:      500px;
        height:     500px;
        background: #7b52d9;
        bottom:     -150px;
        right:      -100px;
    }

    /* Contenido central */
    .hero-contenido {
        position:   relative;
        z-index:    1;
        text-align: center;
        max-width:  600px;
        justify-self: center;
    }

    /* Paneles laterales decorativos */
    .hero-lateral {
        position:       relative;
        z-index:        1;
        height:         100%;
        display:        flex;
        flex-direction: column;
        justify-content: center;
        gap:            16px;
    }

    .hero-lateral-der {
        align-items: flex-start;
    }

    .hero-lateral-izq {
        align-items: flex-end;
    }

    /* Tarjetas flotantes decorativas */
    .hero-card {
        background:    rgba(13, 27, 46, .85);
        border:        1px solid var(--borde);
        border-radius: var(--radio-lg);
        padding:       14px 18px;
        backdrop-filter: blur(10px);
        display:       flex;
        align-items:   center;
        gap:           12px;
        font-size:     .85rem;
        color:         var(--texto-suave);
        max-width:     220px;
        box-shadow:    0 4px 20px rgba(0,0,0,.4);
        animation:     flotar 4s ease-in-out infinite;
    }

    .hero-card:nth-child(2) { animation-delay: 1s; }
    .hero-card:nth-child(3) { animation-delay: 2s; }

    .hero-card .icono {
        font-size:   1.5rem;
        flex-shrink: 0;
    }

    .hero-card strong {
        display:    block;
        color:      var(--texto);
        font-size:  .88rem;
    }

    @keyframes flotar {
        0%, 100% { transform: translateY(0px); }
        50%       { transform: translateY(-8px); }
    }

    @media (max-width: 900px) {
        .hero {
            grid-template-columns: 1fr;
            justify-items: center;
            padding: 40px 24px;
        }
        .hero-lateral { display: none; }
    }

    .hero-chip {
        display:        inline-block;
        background:     var(--primario-suave);
        color:          var(--primario);
        border:         1px solid var(--primario);
        border-radius:  50px;
        padding:        4px 16px;
        font-size:      .78rem;
        font-weight:    700;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom:  20px;
    }

    .hero h1 {
        font-size:     3rem;
        line-height:   1.15;
        margin-bottom: 18px;
        letter-spacing: -1px;
    }

    .hero h1 .acento {
        color: var(--primario);
    }

    .hero p {
        font-size:     1.1rem;
        color:         var(--texto-suave);
        max-width:     520px;
        margin:        0 auto 32px;
        line-height:   1.7;
    }

    .hero-acciones {
        display:     flex;
        gap:         14px;
        justify-content: center;
        flex-wrap:   wrap;
    }

    @media (max-width: 600px) {
        .hero h1         { font-size: 2rem; }
        .hero-acciones   { flex-direction: column; align-items: center; }
    }

    /* ── Sección de películas destacadas ─────────────────────── */
    .seccion {
        padding: 60px 0;
    }

    .seccion-header {
        display:         flex;
        align-items:     baseline;
        justify-content: space-between;
        gap:             16px;
        margin-bottom:   28px;
        flex-wrap:       wrap;
    }

    .seccion-titulo {
        font-size: 1.4rem;
    }

    /* Grid horizontal con scroll en móvil */
    .peliculas-scroll {
        display:               grid;
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        gap:                   20px;
    }

    /* Tarjeta compacta de película */
    .mini-card {
        background:    var(--superficie);
        border:        1px solid var(--borde);
        border-radius: var(--radio-lg);
        overflow:      hidden;
        transition:    transform var(--transicion), box-shadow var(--transicion);
        text-decoration: none;
        color:         var(--texto);
        display:       flex;
        flex-direction: column;
    }

    .mini-card:hover {
        transform:  translateY(-5px);
        box-shadow: var(--sombra-lg);
        color:      var(--texto);
    }

    .mini-poster {
        aspect-ratio: 2/3;
        overflow:     hidden;
        position:     relative;
        background:   #111;
    }

    .mini-poster img {
        width:      100%;
        height:     100%;
        object-fit: cover;
        transition: transform .35s;
    }

    .mini-card:hover .mini-poster img {
        transform: scale(1.06);
    }

    .mini-badge-wrap {
        position: absolute;
        top:      8px;
        left:     8px;
    }

    .mini-info {
        padding: 12px 14px;
        flex:    1;
        display: flex;
        flex-direction: column;
        gap:     4px;
    }

    .mini-titulo {
        font-size:   .95rem;
        font-weight: 700;
        line-height: 1.3;
    }

    .mini-meta {
        font-size: .78rem;
        color:     var(--texto-suave);
        display:   flex;
        gap:       10px;
        flex-wrap: wrap;
    }

    .mini-precio {
        font-size:  .82rem;
        color:      var(--verde);
        font-weight: 600;
        margin-top: 4px;
    }

    /* ── Banner CTA inferior ─────────────────────────────────── */
    .banner-cta {
        padding:    70px 24px;
        text-align: center;
    }

    .banner-cta h2 {
        font-size:     2rem;
        margin-bottom: 12px;
    }

    .banner-cta p {
        color:         var(--texto-suave);
        margin-bottom: 28px;
        font-size:     1rem;
    }
</style>

<main>

    <!-- ══ HERO ══════════════════════════════════════════════ -->
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
                <a href="mis-reservas.php" class="btn btn-secundario btn-lg">
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


    <!-- ══ PELÍCULAS DESTACADAS ══════════════════════════════ -->
    <?php if (!empty($peliculas_destacadas)): ?>
    <section class="seccion">
        <div class="contenedor">

            <div class="seccion-header">
                <h2 class="seccion-titulo">🔥 En cartelera ahora</h2>
                <a href="cartelera.php" style="font-size:.88rem;">
                    Ver todas →
                </a>
            </div>

            <div class="peliculas-scroll">
                <?php foreach ($peliculas_destacadas as $peli):
                    $clase_badge = claseBadgeClasificacion($peli['clasificacion']);
                ?>
                    <a href="cartelera.php#pelicula-<?= (int)$peli['id'] ?>"
                       class="mini-card"
                       title="<?= esc($peli['titulo']) ?>">

                        <div class="mini-poster">
                            <img
                                src="<?= esc($peli['imagen'] ?? 'assets/img/sin-poster.jpg') ?>"
                                alt="Poster de <?= esc($peli['titulo']) ?>"
                                loading="lazy"
                                onerror="this.src='assets/img/sin-poster.jpg'">
                            <div class="mini-badge-wrap">
                                <span class="badge <?= $clase_badge ?>">
                                    <?= esc($peli['clasificacion']) ?>
                                </span>
                            </div>
                        </div>

                        <div class="mini-info">
                            <span class="mini-titulo">
                                <?= esc($peli['titulo']) ?>
                            </span>
                            <div class="mini-meta">
                                <span><?= esc($peli['genero']) ?></span>
                                <span><?= formatearDuracion($peli['duracion_min']) ?></span>
                            </div>
                            <?php if ($peli['precio_desde']): ?>
                                <span class="mini-precio">
                                    Desde <?= formatearPrecio($peli['precio_desde']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                    </a>
                <?php endforeach; ?>
            </div>

        </div>
    </section>
    <?php endif; ?>




    <!-- ══ BANNER CTA INFERIOR ═══════════════════════════════ -->
    <section class="banner-cta" aria-label="Llamado a la acción">
        <h2>¿Listo para tu próxima película?</h2>
        <p>Reserva tu asiento antes de que se agoten.</p>
        <a href="cartelera.php" class="btn btn-primario btn-lg">
            Ver cartelera completa
        </a>
    </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
