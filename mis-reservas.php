<?php
/**
 * ============================================================
 *  CineFlow - Plataforma de Gestión y Venta de Entradas
 * ============================================================
 *  Archivo   : mis-reservas.php
 *  Propósito : Historial de reservas del usuario autenticado.
 *
 *  SECCIONES:
 *    1. Próximas  → funciones futuras con estado pendiente/confirmada.
 *    2. Historial → funciones pasadas o reservas canceladas/expiradas.
 *
 *  ACCIONES DISPONIBLES:
 *    · Ver detalle → enlaza a confirmacion.php?codigo=XX
 *    · Cancelar    → POST a cancelar-reserva.php (futuro)
 *
 *  Requiere: sesión activa (redirige a login si no hay sesión).
 *  Depende de: config.php, includes/funciones.php,
 *              includes/header.php, includes/footer.php
 *  Autores   : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/funciones.php';

// ─────────────────────────────────────────────────────────────
//  Proteger la ruta: solo usuarios autenticados
// ─────────────────────────────────────────────────────────────
requerirLogin('mis-reservas.php');

// ─────────────────────────────────────────────────────────────
//  Obtener todas las reservas del usuario
// ─────────────────────────────────────────────────────────────
$todas = obtenerReservasUsuario((int)$_SESSION['usuario_id']);

// ─────────────────────────────────────────────────────────────
//  Agrupar filas por código de reserva
//  (un código puede tener varios asientos → varias filas)
// ─────────────────────────────────────────────────────────────
$agrupadas = [];
foreach ($todas as $fila) {
    $cod = $fila['codigo_reserva'];
    if (!isset($agrupadas[$cod])) {
        $agrupadas[$cod] = [
            'info'     => $fila,                          // Datos comunes
            'asientos' => [],                             // Lista de asientos
        ];
    }
    $agrupadas[$cod]['asientos'][] = $fila['fila'] . $fila['asiento_numero'];
}

// ─────────────────────────────────────────────────────────────
//  Separar en próximas y pasadas/canceladas
// ─────────────────────────────────────────────────────────────
$ahora    = new DateTime();
$proximas = [];
$pasadas  = [];

foreach ($agrupadas as $cod => $grupo) {
    $fecha_fn = new DateTime($grupo['info']['fecha_hora']);
    $estado   = $grupo['info']['estado'];
    $es_futura   = $fecha_fn > $ahora;
    $es_activa   = in_array($estado, ['pendiente', 'confirmada']);

    if ($es_futura && $es_activa) {
        $proximas[$cod] = $grupo;
    } else {
        $pasadas[$cod]  = $grupo;
    }
}

// ─────────────────────────────────────────────────────────────
//  Renderizado
// ─────────────────────────────────────────────────────────────
$titulo_pagina = 'Mis reservas';
$nav_activo    = 'mis-reservas';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ══ ESTILOS ESPECÍFICOS ═══════════════════════════════════ -->
<style>
    .reservas-wrap {
        max-width: 900px;
        margin:    32px auto;
        padding:   0 20px 60px;
    }

    /* ── Cabecera de sección ─────────────────────────────────── */
    .seccion-reservas {
        margin-bottom: 40px;
    }

    .seccion-reservas h2 {
        font-size:     1.2rem;
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--borde);
        display:       flex;
        align-items:   center;
        gap:           10px;
    }

    .seccion-reservas h2 .conteo {
        background:    var(--superficie-alt);
        color:         var(--texto-suave);
        border:        1px solid var(--borde);
        font-size:     .75rem;
        font-weight:   600;
        padding:       2px 10px;
        border-radius: 50px;
    }

    /* ── Tarjeta de reserva ──────────────────────────────────── */
    .reserva-card {
        background:    var(--superficie);
        border:        1px solid var(--borde);
        border-radius: var(--radio-lg);
        overflow:      hidden;
        margin-bottom: 16px;
        transition:    border-color var(--transicion);
    }

    .reserva-card:hover {
        border-color: var(--primario);
    }

    .reserva-card-inner {
        display:     flex;
        gap:         16px;
        padding:     16px;
        align-items: flex-start;
    }

    /* Poster pequeño */
    .res-poster {
        width:         60px;
        height:        90px;
        object-fit:    cover;
        border-radius: var(--radio);
        flex-shrink:   0;
        background:    #111;
    }

    /* Cuerpo */
    .res-cuerpo {
        flex: 1;
        min-width: 0;   /* evita overflow en flex */
    }

    .res-titulo {
        font-size:   1rem;
        font-weight: 700;
        margin-bottom: 6px;
        white-space: nowrap;
        overflow:    hidden;
        text-overflow: ellipsis;
    }

    .res-meta {
        display:   flex;
        flex-wrap: wrap;
        gap:       8px 16px;
        font-size: .82rem;
        color:     var(--texto-suave);
        margin-bottom: 8px;
    }

    /* Chips de asientos */
    .res-asientos {
        display:   flex;
        flex-wrap: wrap;
        gap:       6px;
        margin-bottom: 10px;
    }

    .chip-mini {
        background:    var(--borde-suave);
        border:        1px solid var(--borde);
        border-radius: 4px;
        padding:       2px 8px;
        font-size:     .75rem;
        font-family:   var(--fuente-mono);
        font-weight:   700;
        color:         var(--texto-suave);
    }

    /* Acciones */
    .res-acciones {
        display:   flex;
        gap:       8px;
        flex-wrap: wrap;
        align-items: center;
    }

    /* Panel derecho: código y estado */
    .res-lateral {
        display:       flex;
        flex-direction: column;
        align-items:   flex-end;
        gap:           8px;
        flex-shrink:   0;
        min-width:     110px;
    }

    .codigo-mono {
        font-family: var(--fuente-mono);
        font-size:   .8rem;
        color:       var(--amarillo);
        letter-spacing: 1px;
        text-align:  right;
    }

    .precio-total {
        font-size:   1rem;
        font-weight: 700;
        color:       var(--texto);
    }

    /* Estado vacío */
    .vacio {
        text-align: center;
        padding:    40px 20px;
        color:      var(--texto-suave);
        background: var(--superficie);
        border:     1px dashed var(--borde);
        border-radius: var(--radio-lg);
    }

    .vacio p { margin-top: 8px; font-size: .9rem; }

    /* Responsive */
    @media (max-width: 550px) {
        .reserva-card-inner { flex-wrap: wrap; }
        .res-lateral        { align-items: flex-start; flex-direction: row; flex-wrap: wrap; }
    }
</style>


<div class="reservas-wrap">

    <!-- Breadcrumb -->
    <nav class="breadcrumb" style="margin-bottom:24px;" aria-label="Ruta">
        <a href="index.php">Inicio</a>
        <span class="breadcrumb-sep">›</span>
        <span>Mis reservas</span>
    </nav>

    <h1 style="font-size:1.5rem;margin-bottom:28px;">
        🎟 Mis reservas
    </h1>

    <!-- Flash messages -->
    <?= renderFlash() ?>


    <!-- ══ SECCIÓN: PRÓXIMAS ════════════════════════════════ -->
    <section class="seccion-reservas" aria-labelledby="titulo-proximas">
        <h2 id="titulo-proximas">
            📅 Próximas funciones
            <span class="conteo"><?= count($proximas) ?></span>
        </h2>

        <?php if (empty($proximas)): ?>
            <div class="vacio">
                <p style="font-size:2rem;">🎬</p>
                <p>No tienes reservas para funciones próximas.</p>
                <a href="cartelera.php" class="btn btn-primario btn-sm" style="margin-top:14px;">
                    Ver cartelera
                </a>
            </div>

        <?php else: ?>
            <?php foreach ($proximas as $cod => $grupo):
                $info         = $grupo['info'];
                $asientos     = $grupo['asientos'];
                $n_asientos   = count($asientos);
                $precio_total = (float)$info['precio'] * $n_asientos;
                $estado       = $info['estado'];
                $fecha_fn     = new DateTime($info['fecha_hora']);
            ?>

                <article class="reserva-card" aria-label="Reserva <?= esc($cod) ?>">
                    <div class="reserva-card-inner">

                        <!-- Poster -->
                        <img
                            class="res-poster"
                            src="<?= esc($info['poster'] ?? 'assets/img/sin-poster.jpg') ?>"
                            alt="Poster de <?= esc($info['pelicula']) ?>"
                            onerror="this.src='assets/img/sin-poster.jpg'">

                        <!-- Datos principales -->
                        <div class="res-cuerpo">
                            <p class="res-titulo" title="<?= esc($info['pelicula']) ?>">
                                <?= esc($info['pelicula']) ?>
                            </p>

                            <div class="res-meta">
                                <span>
                                    📅 <?= $fecha_fn->format('d/m/Y') ?>
                                    a las <?= $fecha_fn->format('H:i') ?> hrs
                                </span>
                                <span>🎭 <?= esc($info['sala']) ?></span>
                                <span>🌐 <?= esc(ucfirst($info['idioma'])) ?></span>
                            </div>

                            <!-- Chips de asientos -->
                            <div class="res-asientos">
                                <?php foreach ($asientos as $a): ?>
                                    <span class="chip-mini"><?= esc($a) ?></span>
                                <?php endforeach; ?>
                            </div>

                            <!-- Acciones -->
                            <div class="res-acciones">
                                <a href="confirmacion.php?codigo=<?= urlencode($cod) ?>"
                                   class="btn btn-secundario btn-sm">
                                    Ver detalle
                                </a>

                                <?php if ($estado === 'pendiente'): ?>
                                    <span style="font-size:.78rem;color:var(--amarillo);">
                                        ⏳ Pendiente de pago
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Lateral: código y total -->
                        <div class="res-lateral">
                            <span class="badge badge-estado <?= claseBadgeEstado($estado) ?>">
                                <?= esc(etiquetaEstadoReserva($estado)) ?>
                            </span>
                            <span class="codigo-mono"><?= esc($cod) ?></span>
                            <span class="precio-total"><?= formatearPrecio($precio_total) ?></span>
                        </div>

                    </div><!-- /reserva-card-inner -->
                </article>

            <?php endforeach; ?>
        <?php endif; ?>
    </section>


    <!-- ══ SECCIÓN: HISTORIAL ═══════════════════════════════ -->
    <section class="seccion-reservas" aria-labelledby="titulo-historial">
        <h2 id="titulo-historial">
            🗂 Historial
            <span class="conteo"><?= count($pasadas) ?></span>
        </h2>

        <?php if (empty($pasadas)): ?>
            <div class="vacio">
                <p>Aquí aparecerán tus reservas pasadas.</p>
            </div>

        <?php else: ?>
            <?php foreach ($pasadas as $cod => $grupo):
                $info         = $grupo['info'];
                $asientos     = $grupo['asientos'];
                $n_asientos   = count($asientos);
                $precio_total = (float)$info['precio'] * $n_asientos;
                $estado       = $info['estado'];
                $fecha_fn     = new DateTime($info['fecha_hora']);
            ?>

                <article class="reserva-card" style="opacity:.7;" aria-label="Reserva pasada <?= esc($cod) ?>">
                    <div class="reserva-card-inner">

                        <img
                            class="res-poster"
                            src="<?= esc($info['poster'] ?? 'assets/img/sin-poster.jpg') ?>"
                            alt="Poster de <?= esc($info['pelicula']) ?>"
                            onerror="this.src='assets/img/sin-poster.jpg'"
                            style="filter:grayscale(.6);">

                        <div class="res-cuerpo">
                            <p class="res-titulo" title="<?= esc($info['pelicula']) ?>">
                                <?= esc($info['pelicula']) ?>
                            </p>

                            <div class="res-meta">
                                <span>
                                    📅 <?= $fecha_fn->format('d/m/Y') ?>
                                    a las <?= $fecha_fn->format('H:i') ?> hrs
                                </span>
                                <span>🎭 <?= esc($info['sala']) ?></span>
                            </div>

                            <div class="res-asientos">
                                <?php foreach ($asientos as $a): ?>
                                    <span class="chip-mini"><?= esc($a) ?></span>
                                <?php endforeach; ?>
                            </div>

                            <div class="res-acciones">
                                <a href="confirmacion.php?codigo=<?= urlencode($cod) ?>"
                                   class="btn btn-secundario btn-sm">
                                    Ver detalle
                                </a>
                            </div>
                        </div>

                        <div class="res-lateral">
                            <span class="badge badge-estado <?= claseBadgeEstado($estado) ?>">
                                <?= esc(etiquetaEstadoReserva($estado)) ?>
                            </span>
                            <span class="codigo-mono"><?= esc($cod) ?></span>
                            <span class="precio-total" style="color:var(--texto-suave);">
                                <?= formatearPrecio($precio_total) ?>
                            </span>
                        </div>

                    </div>
                </article>

            <?php endforeach; ?>
        <?php endif; ?>
    </section>

</div><!-- /reservas-wrap -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
