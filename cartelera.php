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
require_once __DIR__ . '/includes/funciones.php';

// ─────────────────────────────────────────────────────────────
//  Obtener cartelera usando la clase Pelicula (OOP)
// ─────────────────────────────────────────────────────────────
$cartelera_completa = Pelicula::ObtenerCartelera();

// Filtro de género desde GET
$filtro_genero = isset($_GET['genero'])
    ? htmlspecialchars(trim($_GET['genero']), ENT_QUOTES, 'UTF-8')
    : '';

// Aplicar filtro si corresponde
if ($filtro_genero !== '') {
    $cartelera_completa = array_filter(
        $cartelera_completa,
        fn($item) => stripos($item['pelicula']->genero, $filtro_genero) !== false
    );
}

// Géneros únicos para el selector de filtros
$generos_disponibles = array_unique(array_map(
    fn($item) => $item['pelicula']->genero,
    Pelicula::ObtenerCartelera()
));
sort($generos_disponibles);

$titulo_pagina = 'Cartelera';
$nav_activo    = 'cartelera';
require_once __DIR__ . '/includes/header.php';
?>

<style>
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
</style>

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

    <?php if (empty($cartelera_completa)): ?>
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

        <?php foreach ($cartelera_completa as $item):
            /** @var Pelicula $pelicula */
            $pelicula    = $item['pelicula'];
            $funciones   = $item['funciones_por_fecha'];
            $peli_id     = $pelicula->id;
            $clase_badge = claseBadgeClasificacion($pelicula->clasificacion);
        ?>

        <!-- ── Tarjeta ──────────────────────────────────────── -->
        <article class="pelicula-card" id="pelicula-<?= $peli_id ?>">

            <!-- Poster -->
            <div class="poster-wrap">
                <img
                    src="<?= esc($pelicula->poster ?: 'assets/img/sin-poster.jpg') ?>"
                    alt="Poster de <?= esc($pelicula->titulo) ?>"
                    loading="lazy"
                    onerror="this.src='assets/img/sin-poster.jpg'">

                <div class="badges">
                    <span class="badge <?= $clase_badge ?>">
                        <?= esc($pelicula->clasificacion) ?>
                    </span>
                    <span class="badge badge-gris">
                        <?= esc($pelicula->genero) ?>
                    </span>
                </div>
            </div>

            <!-- Datos -->
            <div class="card-body">
                <h2 class="titulo-pelicula">
                    <?= esc($pelicula->titulo) ?>
                </h2>

                <div class="meta-pelicula">
                    <span>⏱ <?= formatearDuracion($pelicula->duracion) ?></span>
                </div>

                <?php if ($pelicula->sinopsis !== ''): ?>
                    <p class="sinopsis">
                        <?= esc($pelicula->sinopsis) ?>
                    </p>
                <?php endif; ?>

                <!-- ── Horarios ──────────────────────────────── -->
                <?php if (!empty($funciones)): ?>

                    <p class="horarios-titulo">Próximas funciones</p>

                    <?php
                    $fechas       = array_keys($funciones);
                    $fecha_activa = $fechas[0];
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

                    <!-- Contenedor de horarios -->
                    <div id="horarios-<?= $peli_id ?>" class="horarios-lista">

                        <?php foreach ($funciones[$fecha_activa] as $f):
                            $hora       = date('H:i', strtotime($f['fecha_hora']));
                            $precio_fmt = '$' . number_format($f['precio'], 0, ',', '.');
                            $libre      = (int)$f['asientos_libres'];
                            $agotado    = $libre === 0;
                        ?>
                            <a
                                href="<?= $agotado ? '#' : 'reserva.php?funcion=' . (int)$f['id'] ?>"
                                class="btn-horario <?= $agotado ? 'agotado' : '' ?>"
                                title="<?= esc($f['sala_nombre']) ?> · <?= esc(ucfirst($f['idioma'])) ?>"
                                <?= $agotado ? 'aria-disabled="true"' : '' ?>>
                                <span class="hora"><?= $hora ?></span>
                                <span class="precio"><?= $precio_fmt ?></span>
                                <span class="sala"><?= esc($f['sala_nombre']) ?></span>
                                <span class="disp" style="color: <?= $libre > 10 ? '#2ecc71' : ($libre > 0 ? '#f39c12' : '#e74c3c') ?>">
                                    <?= $agotado ? 'Agotado' : "{$libre} lugares" ?>
                                </span>
                            </a>
                        <?php endforeach; ?>

                    </div><!-- /horarios -->

                    <!-- Datos JSON para el switcher de pestañas JS -->
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
        const libre = parseInt(f.asientos_libres, 10);
        const agotado = libre === 0;
        const precio = '$' + parseInt(f.precio, 10).toLocaleString('es-CL');
        const colorDisp = libre > 10 ? '#2ecc71' : (libre > 0 ? '#f39c12' : '#e74c3c');
        const href = agotado ? '#' : 'reserva.php?funcion=' + f.id;

        return `
            <a href="${href}"
               class="btn-horario ${agotado ? 'agotado' : ''}"
               title="${f.sala} · ${f.idioma}"
               ${agotado ? 'aria-disabled="true"' : ''}>
                <span class="hora">${hora}</span>
                <span class="precio">${precio}</span>
                <span class="sala">${f.sala_nombre}</span>
                <span class="disp" style="color:${colorDisp}">
                    ${agotado ? 'Agotado' : libre + ' lugares'}
                </span>
            </a>`;
    }).join('');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>