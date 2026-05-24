<?php
/**
 * ============================================================
 *  CineFlow - Plataforma de Gestión y Venta de Entradas
 * ============================================================
 *  Archivo   : cartelera.php
 *  Propósito : Muestra las películas en cartelera y sus
 *              funciones disponibles. Punto de entrada
 *              principal para el usuario.
 *  Depende de: config.php
 *  Autores   : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

require_once __DIR__ . '/config.php';

// ─────────────────────────────────────────────────────────────
//  1. LÓGICA DE NEGOCIO (PHP puro, sin HTML)
//     Separamos la consulta del HTML para mantener el código
//     limpio y testeable.
// ─────────────────────────────────────────────────────────────

/**
 * Obtiene todas las películas activas que tienen al menos
 * una función programada desde ahora en adelante.
 *
 * @return array  Lista de películas con sus datos básicos.
 */
function obtenerPeliculasEnCartelera(): array
{
    $pdo = obtenerConexion();

    $sql = "
        SELECT DISTINCT
            p.id,
            p.titulo,
            p.genero,
            p.sinopsis,
            p.clasificacion,
            p.duracion_min,
            p.imagen,
            p.fecha_estreno
        FROM peliculas p
        INNER JOIN funciones f ON f.pelicula_id = p.id
        WHERE p.activa  = 1
          AND f.activa  = 1
          AND f.fecha_hora >= NOW()         -- Solo funciones futuras
        ORDER BY p.titulo ASC
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        registrarError('cartelera - obtenerPeliculasEnCartelera', $e->getMessage());
        return [];  // Devolvemos array vacío para que el frontend muestre "sin películas"
    }
}

/**
 * Obtiene todas las funciones futuras de una película,
 * agrupadas por fecha para mostrarlas organizadas en el
 * selector de horarios.
 *
 * @param  int   $pelicula_id  ID de la película.
 * @return array Funciones ordenadas por fecha y hora.
 */
function obtenerFuncionesPorPelicula(int $pelicula_id): array
{
    $pdo = obtenerConexion();

    $sql = "
        SELECT
            f.id            AS funcion_id,
            f.fecha_hora,
            f.precio,
            f.idioma,
            s.nombre        AS sala,
            s.tipo          AS tipo_sala,
            -- Contar asientos libres para esta función
            (
                SELECT COUNT(*)
                FROM asientos a
                WHERE a.sala_id = f.sala_id
                  AND a.id NOT IN (
                      SELECT r.asiento_id
                      FROM   reservas r
                      WHERE  r.funcion_id = f.id
                        AND  r.estado IN ('pendiente', 'confirmada')
                  )
            ) AS asientos_disponibles
        FROM funciones f
        INNER JOIN salas s ON s.id = f.sala_id
        WHERE f.pelicula_id = :pelicula_id
          AND f.activa      = 1
          AND f.fecha_hora  >= NOW()
        ORDER BY f.fecha_hora ASC
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':pelicula_id' => $pelicula_id]);
        $funciones = $stmt->fetchAll();

        // Agrupar por fecha (YYYY-MM-DD) para mostrar pestañas por día
        $agrupadas = [];
        foreach ($funciones as $f) {
            $fecha = date('Y-m-d', strtotime($f['fecha_hora']));
            $agrupadas[$fecha][] = $f;
        }
        return $agrupadas;

    } catch (PDOException $e) {
        registrarError('cartelera - obtenerFuncionesPorPelicula', $e->getMessage());
        return [];
    }
}

/**
 * Convierte minutos a formato legible "Xh Ym".
 * Ej: 166 → "2h 46m"
 *
 * @param  int|null $minutos
 * @return string
 */
function formatearDuracion(?int $minutos): string
{
    if ($minutos === null || $minutos <= 0) return 'N/D';
    $horas = intdiv($minutos, 60);
    $min   = $minutos % 60;
    return $horas > 0 ? "{$horas}h {$min}m" : "{$min}m";
}

/**
 * Devuelve la clase CSS asociada a la clasificación etaria
 * para mostrar la etiqueta con el color correcto.
 *
 * @param  string $clasificacion
 * @return string Clase CSS
 */
function claseBadgeClasificacion(string $clasificacion): string
{
    return match($clasificacion) {
        'TE'    => 'badge-verde',
        'TE+7'  => 'badge-azul',
        'MA+14' => 'badge-amarillo',
        'MA+18' => 'badge-rojo',
        default => 'badge-gris',
    };
}

// ─────────────────────────────────────────────────────────────
//  2. EJECUCIÓN: obtenemos los datos antes de renderizar HTML
// ─────────────────────────────────────────────────────────────
$peliculas = obtenerPeliculasEnCartelera();

// Generamos las funciones de cada película en un array indexado
// para no repetir consultas dentro del HTML
$funciones_por_pelicula = [];
foreach ($peliculas as $pelicula) {
    $funciones_por_pelicula[$pelicula['id']] =
        obtenerFuncionesPorPelicula((int)$pelicula['id']);
}

// Filtro de género desde GET (opcional, sin afectar la seguridad)
$filtro_genero = isset($_GET['genero'])
    ? htmlspecialchars(trim($_GET['genero']), ENT_QUOTES, 'UTF-8')
    : '';

// Si hay filtro, lo aplicamos sobre el array ya consultado
if ($filtro_genero !== '') {
    $peliculas = array_filter(
        $peliculas,
        fn($p) => stripos($p['genero'], $filtro_genero) !== false
    );
}

// Géneros únicos para el selector de filtros
$generos_disponibles = array_unique(
    array_column(obtenerPeliculasEnCartelera(), 'genero')
);
sort($generos_disponibles);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartelera – CineFlow</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <style>
        /* ── Variables y Reset básico ────────────────────────── */
        :root {
            --primario:    #e50914;
            --oscuro:      #141414;
            --superficie:  #1f1f1f;
            --borde:       #333;
            --texto:       #e5e5e5;
            --texto-suave: #aaa;
            --verde:       #2ecc71;
            --azul:        #3498db;
            --amarillo:    #f39c12;
            --rojo:        #e74c3c;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--oscuro);
            color: var(--texto);
            font-family: 'Segoe UI', Arial, sans-serif;
            min-height: 100vh;
        }

        /* ── Header ─────────────────────────────────────────── */
        .site-header {
            background-color: rgba(20,20,20,.95);
            border-bottom: 2px solid var(--primario);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo { font-size: 1.6rem; font-weight: 700; color: var(--primario); letter-spacing: 1px; }
        .logo span { color: var(--texto); }

        /* ── Filtros ─────────────────────────────────────────── */
        .filtros {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px 24px 0;
            flex-wrap: wrap;
        }
        .filtros label { color: var(--texto-suave); font-size: .9rem; }
        .filtros select {
            background: var(--superficie);
            color: var(--texto);
            border: 1px solid var(--borde);
            border-radius: 6px;
            padding: 6px 12px;
            cursor: pointer;
        }

        /* ── Grilla de películas ─────────────────────────────── */
        .cartelera-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            padding: 24px;
        }

        /* ── Tarjeta de película ─────────────────────────────── */
        .pelicula-card {
            background: var(--superficie);
            border: 1px solid var(--borde);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform .2s, box-shadow .2s;
        }
        .pelicula-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,.5);
        }

        /* Imagen / poster */
        .poster-wrap {
            position: relative;
            aspect-ratio: 2/3;
            overflow: hidden;
            background: #111;
        }
        .poster-wrap img {
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
            transition: transform .3s;
        }
        .pelicula-card:hover .poster-wrap img { transform: scale(1.04); }

        /* Badges sobre la imagen */
        .badges {
            position: absolute;
            top: 10px; left: 10px;
            display: flex; gap: 6px; flex-wrap: wrap;
        }
        .badge {
            font-size: .7rem; font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .badge-verde  { background: var(--verde);   color: #0a2e1a; }
        .badge-azul   { background: var(--azul);    color: #fff;    }
        .badge-amarillo { background: var(--amarillo); color: #1a0e00; }
        .badge-rojo   { background: var(--rojo);    color: #fff;    }
        .badge-gris   { background: #555;           color: #eee;    }

        /* Cuerpo de la tarjeta */
        .card-body { padding: 16px; flex: 1; display: flex; flex-direction: column; gap: 8px; }

        .titulo-pelicula {
            font-size: 1.05rem; font-weight: 700;
            color: var(--texto);
            line-height: 1.3;
        }
        .meta-pelicula {
            font-size: .82rem; color: var(--texto-suave);
            display: flex; gap: 12px; flex-wrap: wrap;
        }
        .sinopsis {
            font-size: .84rem; color: var(--texto-suave);
            line-height: 1.5;
            /* Limitar a 3 líneas */
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ── Selector de horarios ────────────────────────────── */
        .horarios-titulo {
            font-size: .85rem; font-weight: 600;
            color: var(--texto-suave);
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        /* Pestañas de fecha */
        .tabs-fechas { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 4px; }
        .tab-fecha {
            background: transparent;
            border: 1px solid var(--borde);
            color: var(--texto-suave);
            border-radius: 6px;
            padding: 4px 10px;
            font-size: .78rem;
            cursor: pointer;
            transition: all .15s;
        }
        .tab-fecha.activa,
        .tab-fecha:hover {
            background: var(--primario);
            border-color: var(--primario);
            color: #fff;
        }

        /* Botones de horario */
        .horarios-lista { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
        .btn-horario {
            background: #2a2a2a;
            border: 1px solid var(--borde);
            color: var(--texto);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: .82rem;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background .15s, border-color .15s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }
        .btn-horario:hover:not(.agotado) {
            background: var(--primario);
            border-color: var(--primario);
        }
        .btn-horario .hora   { font-weight: 700; font-size: .95rem; }
        .btn-horario .precio { font-size: .75rem; color: var(--texto-suave); }
        .btn-horario .sala   { font-size: .72rem; color: var(--texto-suave); }
        .btn-horario .disp   { font-size: .7rem; }

        .btn-horario.agotado {
            opacity: .4;
            cursor: not-allowed;
            border-style: dashed;
        }

        /* ── Sin películas ───────────────────────────────────── */
        .sin-resultados {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: var(--texto-suave);
        }
        .sin-resultados h2 { font-size: 1.4rem; margin-bottom: 8px; }

        /* ── Footer ─────────────────────────────────────────── */
        .site-footer {
            text-align: center;
            padding: 24px;
            color: var(--texto-suave);
            font-size: .82rem;
            border-top: 1px solid var(--borde);
            margin-top: 40px;
        }
    </style>
</head>
<body>

<!-- ══ CABECERA ══════════════════════════════════════════════ -->
<header class="site-header">
    <div class="logo">Cine<span>Flow</span></div>
    <nav>
        <?php if (isset($_SESSION['usuario_id'])): ?>
            <span style="color:var(--texto-suave);font-size:.9rem;margin-right:14px;">
                Hola, <?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?>
            </span>
            <a href="logout.php" style="color:var(--primario);font-size:.9rem;">Cerrar sesión</a>
        <?php else: ?>
            <a href="login.php" style="color:var(--primario);font-size:.9rem;">Iniciar sesión</a>
        <?php endif; ?>
    </nav>
</header>

<!-- ══ FILTROS ═══════════════════════════════════════════════ -->
<section class="filtros">
    <label for="filtro-genero">Filtrar por género:</label>
    <select id="filtro-genero"
            onchange="location.href='cartelera.php?genero='+encodeURIComponent(this.value)">
        <option value="">Todos los géneros</option>
        <?php foreach ($generos_disponibles as $genero): ?>
            <option value="<?= htmlspecialchars($genero) ?>"
                <?= ($filtro_genero === $genero) ? 'selected' : '' ?>>
                <?= htmlspecialchars($genero) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if ($filtro_genero !== ''): ?>
        <a href="cartelera.php" style="color:var(--primario);font-size:.85rem;">
            ✕ Limpiar filtro
        </a>
    <?php endif; ?>
</section>

<!-- ══ GRILLA DE PELÍCULAS ═══════════════════════════════════ -->
<main class="cartelera-grid">

    <?php if (empty($peliculas)): ?>
        <!-- Sin resultados -->
        <div class="sin-resultados">
            <h2>🎬 No hay películas disponibles</h2>
            <p>
                <?= $filtro_genero !== ''
                    ? "No hay películas del género <strong>" . htmlspecialchars($filtro_genero) . "</strong> en cartelera."
                    : "No hay funciones programadas por el momento. ¡Vuelve pronto!" ?>
            </p>
        </div>

    <?php else: ?>

        <?php foreach ($peliculas as $pelicula):
            $peli_id   = (int)$pelicula['id'];
            $funciones = $funciones_por_pelicula[$peli_id] ?? [];
            $clase_badge = claseBadgeClasificacion($pelicula['clasificacion']);
        ?>

        <!-- ── Tarjeta ──────────────────────────────────────── -->
        <article class="pelicula-card">

            <!-- Poster -->
            <div class="poster-wrap">
                <img
                    src="<?= htmlspecialchars($pelicula['imagen'] ?? 'assets/img/sin-poster.jpg') ?>"
                    alt="Poster de <?= htmlspecialchars($pelicula['titulo']) ?>"
                    loading="lazy"
                    onerror="this.src='assets/img/sin-poster.jpg'">

                <div class="badges">
                    <span class="badge <?= $clase_badge ?>">
                        <?= htmlspecialchars($pelicula['clasificacion']) ?>
                    </span>
                    <span class="badge badge-gris">
                        <?= htmlspecialchars($pelicula['genero']) ?>
                    </span>
                </div>
            </div>

            <!-- Datos -->
            <div class="card-body">
                <h2 class="titulo-pelicula">
                    <?= htmlspecialchars($pelicula['titulo']) ?>
                </h2>

                <div class="meta-pelicula">
                    <span>⏱ <?= formatearDuracion($pelicula['duracion_min']) ?></span>
                    <?php if ($pelicula['fecha_estreno']): ?>
                        <span>📅 <?= date('d/m/Y', strtotime($pelicula['fecha_estreno'])) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($pelicula['sinopsis'])): ?>
                    <p class="sinopsis">
                        <?= htmlspecialchars($pelicula['sinopsis']) ?>
                    </p>
                <?php endif; ?>

                <!-- ── Horarios ──────────────────────────────── -->
                <?php if (!empty($funciones)): ?>

                    <p class="horarios-titulo">Próximas funciones</p>

                    <?php
                    // Fechas disponibles para las pestañas
                    $fechas = array_keys($funciones);
                    $fecha_activa = $fechas[0]; // Por defecto la más próxima
                    ?>

                    <!-- Pestañas de fecha -->
                    <div class="tabs-fechas">
                        <?php foreach ($fechas as $fecha):
                            $dt      = new DateTime($fecha);
                            $hoy     = new DateTime('today');
                            $manana  = new DateTime('tomorrow');
                            $etiqueta = match(true) {
                                $dt == $hoy    => 'Hoy',
                                $dt == $manana => 'Mañana',
                                default        => $dt->format('d/m'),
                            };
                        ?>
                            <button
                                class="tab-fecha <?= $fecha === $fecha_activa ? 'activa' : '' ?>"
                                data-peli="<?= $peli_id ?>"
                                data-fecha="<?= $fecha ?>"
                                onclick="mostrarHorarios(<?= $peli_id ?>, '<?= $fecha ?>', this)">
                                <?= $etiqueta ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Contenedor de horarios (se actualiza con JS) -->
                    <div id="horarios-<?= $peli_id ?>" class="horarios-lista">

                        <?php
                        // Renderizamos TODOS los horarios como JSON en un atributo data
                        // El JS los mostrará según la pestaña activa.
                        // Solo renderizamos los del día inicial para no requerir JS si no está disponible.
                        foreach ($funciones[$fecha_activa] as $f):
                            $hora  = date('H:i', strtotime($f['fecha_hora']));
                            $precio_fmt = '$' . number_format($f['precio'], 0, ',', '.');
                            $libre = (int)$f['asientos_disponibles'];
                            $agotado = $libre === 0;
                        ?>
                            <a
                                href="<?= $agotado ? '#' : 'reserva.php?funcion=' . (int)$f['funcion_id'] ?>"
                                class="btn-horario <?= $agotado ? 'agotado' : '' ?>"
                                title="<?= htmlspecialchars($f['sala']) ?> · <?= htmlspecialchars(ucfirst($f['idioma'])) ?>"
                                <?= $agotado ? 'aria-disabled="true"' : '' ?>>
                                <span class="hora"><?= $hora ?></span>
                                <span class="precio"><?= $precio_fmt ?></span>
                                <span class="sala"><?= htmlspecialchars($f['sala']) ?></span>
                                <span class="disp" style="color: <?= $libre > 10 ? '#2ecc71' : ($libre > 0 ? '#f39c12' : '#e74c3c') ?>">
                                    <?= $agotado ? 'Agotado' : "{$libre} lugares" ?>
                                </span>
                            </a>
                        <?php endforeach; ?>

                    </div><!-- /horarios -->

                    <!-- Datos JSON para el switcher de pestañas -->
                    <script>
                    window.cineflowFunciones = window.cineflowFunciones || {};
                    window.cineflowFunciones[<?= $peli_id ?>] = <?= json_encode($funciones, JSON_HEX_TAG) ?>;
                    </script>

                <?php else: ?>
                    <p style="color:var(--texto-suave);font-size:.82rem;margin-top:8px;">
                        Sin funciones disponibles próximamente.
                    </p>
                <?php endif; ?>

            </div><!-- /card-body -->
        </article><!-- /pelicula-card -->

        <?php endforeach; ?>

    <?php endif; ?>

</main><!-- /cartelera-grid -->

<!-- ══ PIE DE PÁGINA ══════════════════════════════════════════ -->
<footer class="site-footer">
    <p>© <?= date('Y') ?> CineFlow · Todos los derechos reservados.</p>
</footer>

<!-- ══ JAVASCRIPT: Switcher de pestañas por fecha ════════════ -->
<script>
/**
 * Renderiza los botones de horario cuando el usuario cambia
 * de pestaña de fecha, sin necesidad de recargar la página.
 *
 * @param {number} peliId    - ID de la película
 * @param {string} fecha     - Fecha en formato 'YYYY-MM-DD'
 * @param {HTMLElement} tabEl - Botón de pestaña pulsado
 */
function mostrarHorarios(peliId, fecha, tabEl) {
    // Marcar la pestaña activa
    const todasLasTabs = tabEl.closest('.tabs-fechas').querySelectorAll('.tab-fecha');
    todasLasTabs.forEach(t => t.classList.remove('activa'));
    tabEl.classList.add('activa');

    // Obtener las funciones del día seleccionado
    const funciones  = (window.cineflowFunciones[peliId] || {})[fecha] || [];
    const contenedor = document.getElementById('horarios-' + peliId);
    if (!contenedor) return;

    // Construir el HTML de los botones
    if (funciones.length === 0) {
        contenedor.innerHTML = '<p style="color:#aaa;font-size:.82rem;">Sin funciones este día.</p>';
        return;
    }

    contenedor.innerHTML = funciones.map(f => {
        const hora  = f.fecha_hora.substring(11, 16);
        const libre = parseInt(f.asientos_disponibles, 10);
        const agotado = libre === 0;
        const precio = '$' + parseInt(f.precio, 10).toLocaleString('es-CL');
        const colorDisp = libre > 10 ? '#2ecc71' : (libre > 0 ? '#f39c12' : '#e74c3c');
        const href = agotado ? '#' : 'reserva.php?funcion=' + f.funcion_id;

        return `
            <a href="${href}"
               class="btn-horario ${agotado ? 'agotado' : ''}"
               title="${f.sala} · ${f.idioma}"
               ${agotado ? 'aria-disabled="true"' : ''}>
                <span class="hora">${hora}</span>
                <span class="precio">${precio}</span>
                <span class="sala">${f.sala}</span>
                <span class="disp" style="color:${colorDisp}">
                    ${agotado ? 'Agotado' : libre + ' lugares'}
                </span>
            </a>`;
    }).join('');
}
</script>

</body>
</html>