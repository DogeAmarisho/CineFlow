<?php
/**
 * ============================================================
 *  CineFlow - Plataforma de Gestión y Venta de Entradas
 * ============================================================
 *  Archivo   : confirmacion.php
 *  Propósito : Muestra el resumen completo de una reserva
 *              exitosa con todos los detalles de la función,
 *              los asientos reservados y el código de retiro.
 *
 *  MODOS DE ACCESO:
 *    A) Desde reserva.php (POST-Redirect-GET):
 *       $_SESSION['reserva_confirmada'] contiene el resultado.
 *       URL: confirmacion.php?codigo=CF-XXXXXX
 *
 *    B) Consulta directa por código:
 *       URL: confirmacion.php?codigo=CF-XXXXXX
 *       Cualquier usuario puede consultar el estado de una
 *       reserva si conoce el código.
 *
 *  NOTAS:
 *    · No requiere login para consultar (el código ya es
 *      un secreto compartido suficiente).
 *    · Si la reserva está 'pendiente', muestra un contador
 *      regresivo hasta la expiración.
 *
 *  Depende de: config.php, includes/funciones.php,
 *              includes/header.php, includes/footer.php
 *  Autores   : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/funciones.php';

// ─────────────────────────────────────────────────────────────
//  Obtener el código de reserva
//  Prioridad: GET > sesión flash de reserva.php
// ─────────────────────────────────────────────────────────────
$codigo = '';

// Modo B: código en la URL
if (!empty($_GET['codigo'])) {
    $codigo = strtoupper(trim($_GET['codigo']));
}

// Modo A: venimos del redirect de reserva.php
// reserva.php guarda el código en la sesión antes de redirigir
if ($codigo === '' && !empty($_SESSION['ultimo_codigo_reserva'])) {
    $codigo = $_SESSION['ultimo_codigo_reserva'];
    unset($_SESSION['ultimo_codigo_reserva']);
}

// Sin código → volver a cartelera
if ($codigo === '' || !preg_match('/^CF-[A-Z0-9]{6}$/', $codigo)) {
    flashMensaje('aviso', 'No se especificó un código de reserva válido.');
    redirigir('cartelera.php');
}

// ─────────────────────────────────────────────────────────────
//  Consultar la reserva en la BD
// ─────────────────────────────────────────────────────────────
$filas_reserva = obtenerReservaPorCodigo($codigo);

if ($filas_reserva === null || empty($filas_reserva)) {
    flashMensaje('error', "No se encontró ninguna reserva con el código <strong>{$codigo}</strong>.");
    redirigir('cartelera.php');
}

// ─────────────────────────────────────────────────────────────
//  Construir la estructura de datos para la vista
//  Todas las filas comparten película, función y estado;
//  difieren solo en asiento_id, fila y numero.
// ─────────────────────────────────────────────────────────────
$primera         = $filas_reserva[0];   // Datos comunes en cualquier fila
$estado          = $primera['estado'];
$fecha_expiracion = $primera['fecha_expiracion'];

// Precio total: precio unitario × número de asientos
$total_asientos  = count($filas_reserva);
$precio_unitario = (float)$primera['precio'];
$total_precio    = $precio_unitario * $total_asientos;

// Lista de asientos ordenados
$asientos_lista = array_map(
    fn($f) => $f['fila'] . $f['asiento_numero'],
    $filas_reserva
);


// ─────────────────────────────────────────────────────────────
//  Renderizado
// ─────────────────────────────────────────────────────────────
$titulo_pagina = 'Confirmación de reserva';
$nav_activo    = '';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ══ ESTILOS ESPECÍFICOS ═══════════════════════════════════ -->
<style>
    .confirmacion-wrap {
        max-width: 720px;
        margin:    40px auto;
        padding:   0 20px 60px;
    }

    /* ── Cabecera de estado ──────────────────────────────────── */
    .estado-banner {
        border-radius: var(--radio-lg);
        padding:       28px 24px;
        text-align:    center;
        margin-bottom: 28px;
        border:        1px solid;
    }

    .estado-banner.pendiente {
        background:   var(--amarillo-oscuro);
        border-color: var(--amarillo);
        color:        var(--amarillo);
    }

    .estado-banner.confirmada {
        background:   var(--verde-oscuro);
        border-color: var(--verde);
        color:        var(--verde);
    }

    .estado-banner.cancelada,
    .estado-banner.expirada {
        background:   var(--rojo-oscuro);
        border-color: var(--rojo);
        color:        var(--rojo);
    }

    .estado-banner.utilizada {
        background:   rgba(52,152,219,.1);
        border-color: #3498db;
        color:        #3498db;
    }

    .estado-icono {
        font-size:     3.5rem;
        display:       block;
        margin-bottom: 10px;
    }

    .estado-titulo {
        font-size:     1.5rem;
        font-weight:   700;
        margin-bottom: 6px;
    }

    .estado-descripcion {
        font-size:  .9rem;
        opacity:    .85;
    }

    /* ── Código de reserva ───────────────────────────────────── */
    .codigo-grande {
        display:         flex;
        align-items:     center;
        justify-content: center;
        gap:             12px;
        margin:          20px 0 0;
        flex-wrap:       wrap;
    }

    .codigo-tag {
        font-family:    var(--fuente-mono);
        font-size:      2rem;
        font-weight:    700;
        letter-spacing: 4px;
        background:     rgba(0,0,0,.3);
        padding:        10px 24px;
        border-radius:  var(--radio);
        border:         1px solid currentColor;
    }

    /* ── Secciones de detalle ────────────────────────────────── */
    .detalle-card {
        background:    var(--superficie);
        border:        1px solid var(--borde);
        border-radius: var(--radio-lg);
        overflow:      hidden;
        margin-bottom: 20px;
    }

    .detalle-card-titulo {
        padding:       14px 20px;
        font-size:     .82rem;
        font-weight:   700;
        text-transform: uppercase;
        letter-spacing: .8px;
        color:         var(--texto-suave);
        background:    var(--superficie-alt);
        border-bottom: 1px solid var(--borde);
    }

    .detalle-card-body {
        padding: 20px;
    }

    /* Fila de dato clave/valor */
    .dato-fila {
        display:         flex;
        justify-content: space-between;
        align-items:     baseline;
        gap:             16px;
        padding:         8px 0;
        border-bottom:   1px solid var(--borde-suave);
        font-size:       .9rem;
    }

    .dato-fila:last-child {
        border-bottom: none;
    }

    .dato-clave {
        color:      var(--texto-suave);
        flex-shrink: 0;
    }

    .dato-valor {
        text-align: right;
        font-weight: 600;
    }

    /* Película con poster */
    .pelicula-row {
        display:     flex;
        gap:         16px;
        align-items: flex-start;
    }

    .conf-poster {
        width:         70px;
        height:        105px;
        object-fit:    cover;
        border-radius: var(--radio);
        flex-shrink:   0;
        background:    #111;
    }

    .conf-pelicula-datos {
        flex: 1;
    }

    .conf-pelicula-datos h2 {
        font-size:     1.1rem;
        margin-bottom: 8px;
    }

    /* Lista de asientos */
    .asientos-chips {
        display:   flex;
        flex-wrap: wrap;
        gap:       8px;
        padding:   16px 20px;
    }

    .chip-asiento {
        background:    var(--seleccionado);
        border:        1px solid var(--seleccionado-borde);
        color:         var(--azul);
        border-radius: var(--radio);
        padding:       6px 14px;
        font-weight:   700;
        font-size:     .9rem;
        font-family:   var(--fuente-mono);
    }

    /* Total */
    .total-row {
        display:         flex;
        justify-content: space-between;
        align-items:     center;
        padding:         16px 20px;
        border-top:      2px solid var(--borde);
        font-size:       1.1rem;
        font-weight:     700;
    }

    .total-row .monto {
        color: var(--primario);
        font-size: 1.3rem;
    }

    /* Contador regresivo */
    .contador-wrap {
        background:    rgba(243,156,18,.1);
        border:        1px solid var(--amarillo);
        border-radius: var(--radio);
        padding:       14px 20px;
        text-align:    center;
        margin-bottom: 20px;
        font-size:     .9rem;
        color:         var(--amarillo);
    }

    .contador-wrap #contador {
        font-size:   1.4rem;
        font-weight: 700;
        font-family: var(--fuente-mono);
        display:     block;
        margin-top:  4px;
    }

    /* Acciones */
    .acciones-finales {
        display:   flex;
        gap:       12px;
        flex-wrap: wrap;
        margin-top: 28px;
    }

    @media print {
        .site-header,
        .site-footer,
        .acciones-finales,
        .contador-wrap { display: none !important; }

        body { background: #fff; color: #000; }

        .estado-banner,
        .detalle-card  { border: 1px solid #999; }

        .estado-banner { color: #000 !important; background: #f5f5f5 !important; }
    }
</style>


<div class="confirmacion-wrap">

    <!-- ══ BREADCRUMB ═════════════════════════════════════════ -->
    <nav class="breadcrumb" style="margin-bottom:24px;" aria-label="Ruta de navegación">
        <a href="cartelera.php">Cartelera</a>
        <span class="breadcrumb-sep">›</span>
        <span>Confirmación de reserva</span>
    </nav>


    <!-- ══ BANNER DE ESTADO ══════════════════════════════════ -->
    <div class="estado-banner <?= esc($estado) ?>" role="status">

        <span class="estado-icono">
            <?= match($estado) {
                'confirmada' => '✅',
                'pendiente'  => '⏳',
                'cancelada'  => '❌',
                'expirada'   => '⌛',
                'utilizada'  => '✅',
                default      => '🎟'
            } ?>
        </span>

        <p class="estado-titulo">
            <?= match($estado) {
                'confirmada' => '¡Reserva confirmada!',
                'pendiente'  => 'Reserva pendiente de pago',
                'cancelada'  => 'Reserva cancelada',
                'expirada'   => 'Reserva expirada',
                'utilizada'  => '¡Ticket utilizado!',
                default      => 'Estado desconocido'
            } ?>
        </p>

        <p class="estado-descripcion">
            <?= match($estado) {
                'confirmada' => 'Tu asiento está asegurado. Presenta el código en taquilla.',
                'pendiente'  => 'Tienes tiempo limitado para completar el pago.',
                'cancelada'  => 'Esta reserva fue cancelada. El asiento está disponible nuevamente.',
                'expirada'   => 'El tiempo de pago expiró. El asiento fue liberado.',
                'utilizada'  => 'Este ticket ya fue escaneado en taquilla.',
                default      => ''
            } ?>
        </p>

        <!-- Código grande y visible -->
        <div class="codigo-grande">
            <span class="codigo-tag"><?= esc($codigo) ?></span>
        </div>

    </div>


    <!-- ══ CONTADOR REGRESIVO (solo si pendiente) ════════════ -->
    <?php if ($estado === 'pendiente' && $fecha_expiracion): ?>
        <div class="contador-wrap" id="contador-contenedor">
            ⏰ Tienes hasta las
            <?= date('H:i', strtotime($fecha_expiracion)) ?> hrs
            para completar el pago.
            <span id="contador">--:--</span>
        </div>

        <script>
        (function () {
            const expiracion = new Date('<?= $fecha_expiracion ?>').getTime();
            const el = document.getElementById('contador');

            function actualizar() {
                const diff = expiracion - Date.now();
                if (diff <= 0) {
                    el.textContent = '00:00';
                    document.getElementById('contador-contenedor').style.display = 'none';
                    location.reload();   // Recargar para mostrar estado expirado
                    return;
                }
                const min = String(Math.floor(diff / 60000)).padStart(2, '0');
                const seg = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
                el.textContent = min + ':' + seg;
            }

            actualizar();
            setInterval(actualizar, 1000);
        })();
        </script>
    <?php endif; ?>


    <!-- ══ DETALLES DE LA PELÍCULA ═══════════════════════════ -->
    <div class="detalle-card">
        <p class="detalle-card-titulo">🎬 Película</p>
        <div class="detalle-card-body">
            <div class="pelicula-row">
                <img
                    class="conf-poster"
                    src="<?= esc($primera['poster'] ?? 'assets/img/sin-poster.jpg') ?>"
                    alt="Poster de <?= esc($primera['pelicula']) ?>"
                    onerror="this.src='assets/img/sin-poster.jpg'">

                <div class="conf-pelicula-datos">
                    <h2><?= esc($primera['pelicula']) ?></h2>

                    <div class="dato-fila">
                        <span class="dato-clave">Clasificación</span>
                        <span class="dato-valor">
                            <span class="badge <?= claseBadgeClasificacion($primera['clasificacion']) ?>">
                                <?= esc($primera['clasificacion']) ?>
                            </span>
                        </span>
                    </div>

                    <div class="dato-fila">
                        <span class="dato-clave">Fecha y hora</span>
                        <span class="dato-valor">
                            <?= date('l d \d\e F', strtotime($primera['fecha_hora'])) ?>
                            a las <?= date('H:i', strtotime($primera['fecha_hora'])) ?> hrs
                        </span>
                    </div>

                    <div class="dato-fila">
                        <span class="dato-clave">Sala</span>
                        <span class="dato-valor">
                            <?= esc($primera['sala']) ?>
                            <span style="color:var(--texto-suave);font-weight:400;">
                                (<?= esc(ucfirst($primera['tipo_sala'])) ?>)
                            </span>
                        </span>
                    </div>

                    <div class="dato-fila">
                        <span class="dato-clave">Idioma</span>
                        <span class="dato-valor"><?= esc(ucfirst($primera['idioma'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- ══ ASIENTOS RESERVADOS ═══════════════════════════════ -->
    <div class="detalle-card">
        <p class="detalle-card-titulo">
            🪑 Asientos reservados (<?= $total_asientos ?>)
        </p>

        <div class="asientos-chips">
            <?php foreach ($filas_reserva as $fila): ?>
                <span class="chip-asiento">
                    <?= esc($fila['fila'] . $fila['asiento_numero']) ?>
                    <?php if ($fila['tipo_asiento'] !== 'normal'): ?>
                        <span style="font-size:.7rem;font-weight:400;opacity:.8;">
                            (<?= esc($fila['tipo_asiento']) ?>)
                        </span>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>

        <div class="total-row">
            <span>
                <?= $total_asientos ?> asiento<?= $total_asientos > 1 ? 's' : '' ?>
                × <?= formatearPrecio($precio_unitario) ?>
            </span>
            <span class="monto"><?= formatearPrecio($total_precio) ?></span>
        </div>
    </div>


    <!-- ══ DETALLES DE LA RESERVA ════════════════════════════ -->
    <div class="detalle-card">
        <p class="detalle-card-titulo">📋 Detalle de la reserva</p>
        <div class="detalle-card-body">

            <div class="dato-fila">
                <span class="dato-clave">Código de reserva</span>
                <span class="dato-valor" style="font-family:var(--fuente-mono);color:var(--amarillo);font-size:1.05rem;">
                    <?= esc($codigo) ?>
                </span>
            </div>

            <div class="dato-fila">
                <span class="dato-clave">Estado</span>
                <span class="dato-valor">
                    <span class="badge badge-estado <?= claseBadgeEstado($estado) ?>">
                        <?= esc(etiquetaEstadoReserva($estado)) ?>
                    </span>
                </span>
            </div>

            <div class="dato-fila">
                <span class="dato-clave">Fecha de reserva</span>
                <span class="dato-valor">
                    <?= date('d/m/Y H:i', strtotime($primera['fecha_reserva'])) ?> hrs
                </span>
            </div>

            <?php if (!empty($primera['nombre_cliente'])): ?>
                <div class="dato-fila">
                    <span class="dato-clave">Nombre</span>
                    <span class="dato-valor"><?= esc($primera['nombre_cliente']) ?></span>
                </div>
                <div class="dato-fila">
                    <span class="dato-clave">Correo</span>
                    <span class="dato-valor" style="font-size:.88rem;"><?= esc($primera['email_cliente']) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($estado === 'pendiente' && $fecha_expiracion): ?>
                <div class="dato-fila">
                    <span class="dato-clave">Expira a las</span>
                    <span class="dato-valor" style="color:var(--amarillo);">
                        <?= date('H:i', strtotime($fecha_expiracion)) ?> hrs
                        (<?= date('d/m/Y', strtotime($fecha_expiracion)) ?>)
                    </span>
                </div>
            <?php endif; ?>

        </div>
    </div>


    <!-- ══ INSTRUCCIONES ══════════════════════════════════════ -->
    <?php if ($estado === 'pendiente' || $estado === 'confirmada'): ?>
        <div class="alerta alerta-info">
            <strong>📌 ¿Cómo retirar tus entradas?</strong><br>
            Preséntate en la taquilla del cine con el código
            <strong><?= esc($codigo) ?></strong> y un documento de identidad.
            <?php if ($estado === 'pendiente'): ?>
                <br><br>⚠️ Recuerda que debes completar el pago antes de que
                expire el tiempo de reserva.
            <?php endif; ?>
        </div>
    <?php endif; ?>


    <!-- ══ ACCIONES ══════════════════════════════════════════ -->
    <div class="acciones-finales">
        <button onclick="window.print()" class="btn btn-secundario">
            🖨 Imprimir comprobante
        </button>

        <a href="cartelera.php" class="btn btn-primario">
            🎬 Ver cartelera
        </a>

        <a href="consultar-reserva.php" class="btn btn-secundario">
            📋 Mis reservas
        </a>
    </div>

</div><!-- /confirmacion-wrap -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
