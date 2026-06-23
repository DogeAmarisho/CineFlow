<?php
/**
 * Cartelera: lista de peliculas con sus funciones disponibles.
 */

require_once __DIR__ . '/config.php';

$cartelera_completa = Pelicula::ObtenerCartelera();

$titulo_pagina = 'Cartelera';
$nav_activo    = 'cartelera';
render_header();
?>

<main class="cartelera-grid">

    <?php if (empty($cartelera_completa)): ?>
        <!-- Sin resultados -->
        <div class="sin-resultados">
            <h2>🎬 No hay películas disponibles</h2>
            <p>No hay funciones programadas por el momento. ¡Vuelve pronto!</p>
        </div>

    <?php else: ?>

        <?php foreach ($cartelera_completa as $item):
            /** @var Pelicula $pelicula */
            $pelicula    = $item['pelicula'];
            $funciones   = $item['funciones_por_fecha'];
            $peli_id     = $pelicula->id;
            $clase_badge = claseBadgeClasificacion($pelicula->clasificacion);
        ?>

        <article class="pelicula-card" id="pelicula-<?= $peli_id ?>">

            <!-- Poster -->
            <div class="poster-wrap">
                <img
                    src="<?= esc($pelicula->poster ?: 'assets/img/sin-poster.svg') ?>"
                    alt="Poster de <?= esc($pelicula->titulo) ?>"
                    loading="lazy"
                    onerror="this.src='assets/img/sin-poster.svg'">

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

                <?php if (!empty($funciones)): ?>

                    <p class="horarios-titulo">Próximas funciones</p>

                    <?php
                    $fechas       = array_keys($funciones);
                    $fecha_activa = $fechas[0];
                    ?>

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

                    <!-- esto se usa para cambiar de fecha sin recargar la pagina -->
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

</main>

<script>
// cambia los horarios mostrados cuando el usuario clickea otra pestaña de fecha
function mostrarHorarios(peliId, fecha, tabEl) {
    const todasLasTabs = tabEl.closest('.tabs-fechas').querySelectorAll('.tab-fecha');
    todasLasTabs.forEach(t => t.classList.remove('activa'));
    tabEl.classList.add('activa');

    const funciones  = (window.cineflowFunciones[peliId] || {})[fecha] || [];
    const contenedor = document.getElementById('horarios-' + peliId);
    if (!contenedor) return;

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
               title="${f.sala_nombre} · ${f.idioma}"
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

<?php render_footer(); ?>