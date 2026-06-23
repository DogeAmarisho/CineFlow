<?php
/**
 * Comprobante de una reserva. Se llega aca de dos formas:
 *  - recien hecha la reserva, reserva.php redirige con el codigo en sesion
 *  - consultando directo con confirmacion.php?codigo=CF-XXXXXX
 * No pide login, el codigo solo ya alcanza para ver el comprobante.
 */

require_once __DIR__ . '/config.php';

$codigo = '';

// el codigo puede venir por la URL...
if (!empty($_GET['codigo'])) {
    $codigo = strtoupper(trim($_GET['codigo']));
}

// ...o quedo guardado en la sesion si venimos del redirect de reserva.php
if ($codigo === '' && !empty($_SESSION['ultimo_codigo_reserva'])) {
    $codigo = $_SESSION['ultimo_codigo_reserva'];
    unset($_SESSION['ultimo_codigo_reserva']);
}

if ($codigo === '' || !preg_match('/^CF-[A-Z0-9]{6}$/', $codigo)) {
    flashMensaje('aviso', 'No se especificó un código de reserva válido.');
    redirigir('cartelera.php');
}

$filas_reserva = Reserva::ObtenerPorCodigo($codigo);

if ($filas_reserva === null || empty($filas_reserva)) {
    flashMensaje('error', "No se encontró ninguna reserva con el código <strong>{$codigo}</strong>.");
    redirigir('cartelera.php');
}

// todas las filas son la misma reserva, solo cambia el asiento
$primera         = $filas_reserva[0];
$estado          = $primera['estado'];
$fecha_expiracion = $primera['fecha_expiracion'];

$total_asientos  = count($filas_reserva);
$precio_unitario = (float)$primera['precio'];
$total_precio    = $precio_unitario * $total_asientos;

$asientos_lista = array_map(
    fn($f) => $f['fila'] . $f['asiento_numero'],
    $filas_reserva
);

// icono/titulo/descripcion del banner segun el estado de la reserva
$ESTADOS_INFO = [
    'confirmada' => ['✅', '¡Reserva confirmada!',       'Tu asiento está asegurado. Presenta el código en taquilla.'],
    'pendiente'  => ['⏳', 'Reserva pendiente de pago',  'Tienes tiempo limitado para completar el pago.'],
    'cancelada'  => ['❌', 'Reserva cancelada',           'Esta reserva fue cancelada. El asiento está disponible nuevamente.'],
    'expirada'   => ['⌛', 'Reserva expirada',            'El tiempo de pago expiró. El asiento fue liberado.'],
    'utilizada'  => ['✅', '¡Ticket utilizado!',          'Este ticket ya fue escaneado en taquilla.'],
];
[$estado_icono, $estado_titulo, $estado_descripcion] = $ESTADOS_INFO[$estado] ?? ['🎟', 'Estado desconocido', ''];

$titulo_pagina = 'Confirmación de reserva';
$nav_activo    = '';
render_header();
?>

<div class="confirmacion-wrap">

    <nav class="breadcrumb" style="margin-bottom:24px;" aria-label="Ruta de navegación">
        <a href="cartelera.php">Cartelera</a>
        <span class="breadcrumb-sep">›</span>
        <span>Confirmación de reserva</span>
    </nav>


    <div class="estado-banner <?= esc($estado) ?>" role="status">
        <span class="estado-icono"><?= $estado_icono ?></span>
        <p class="estado-titulo"><?= $estado_titulo ?></p>
        <p class="estado-descripcion"><?= $estado_descripcion ?></p>
        <div class="codigo-grande">
            <span class="codigo-tag"><?= esc($codigo) ?></span>
        </div>
    </div>


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


    <div class="detalle-card">
        <p class="detalle-card-titulo">🎬 Película</p>
        <div class="detalle-card-body">
            <div class="pelicula-row">
                <img
                    class="conf-poster"
                    src="<?= esc($primera['poster'] ?? 'assets/img/sin-poster.svg') ?>"
                    alt="Poster de <?= esc($primera['pelicula']) ?>"
                    onerror="this.src='assets/img/sin-poster.svg'">

                <div class="conf-pelicula-datos">
                    <h2><?= esc($primera['pelicula']) ?></h2>

                    <?= filaDato('Clasificación', '<span class="badge ' . claseBadgeClasificacion($primera['clasificacion']) . '">' . esc($primera['clasificacion']) . '</span>') ?>
                    <?= filaDato('Fecha y hora', date('l d \d\e F', strtotime($primera['fecha_hora'])) . ' a las ' . date('H:i', strtotime($primera['fecha_hora'])) . ' hrs') ?>
                    <?= filaDato('Sala', esc($primera['sala']) . ' <span style="color:var(--texto-suave);font-weight:400;">(' . esc(ucfirst($primera['tipo_sala'])) . ')</span>') ?>
                    <?= filaDato('Idioma', esc(ucfirst($primera['idioma']))) ?>
                </div>
            </div>
        </div>
    </div>


    <div class="detalle-card">
        <p class="detalle-card-titulo">
            🪑 Asientos reservados (<?= $total_asientos ?>)
        </p>

        <div class="asientos-chips">
            <?php foreach ($filas_reserva as $fila): ?>
                <span class="chip-asiento"><?= esc($fila['fila'] . $fila['asiento_numero']) ?></span>
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


    <div class="detalle-card">
        <p class="detalle-card-titulo">📋 Detalle de la reserva</p>
        <div class="detalle-card-body">

            <?= filaDato('Código de reserva', esc($codigo), 'font-family:var(--fuente-mono);color:var(--amarillo);font-size:1.05rem;') ?>
            <?= filaDato('Estado', '<span class="badge badge-estado ' . claseBadgeEstado($estado) . '">' . esc(etiquetaEstadoReserva($estado)) . '</span>') ?>
            <?= filaDato('Fecha de reserva', date('d/m/Y H:i', strtotime($primera['fecha_reserva'])) . ' hrs') ?>

            <?php if (!empty($primera['nombre_cliente'])): ?>
                <?= filaDato('Nombre', esc($primera['nombre_cliente'])) ?>
                <?= filaDato('Correo', esc($primera['email_cliente']), 'font-size:.88rem;') ?>
            <?php endif; ?>

            <?php if ($estado === 'pendiente' && $fecha_expiracion): ?>
                <?= filaDato('Expira a las', date('H:i', strtotime($fecha_expiracion)) . ' hrs (' . date('d/m/Y', strtotime($fecha_expiracion)) . ')', 'color:var(--amarillo);') ?>
            <?php endif; ?>

        </div>
    </div>


    <div class="acciones-finales">
        <a href="cartelera.php" class="btn btn-primario">
            🎬 Ver cartelera
        </a>

        <a href="consultar-reserva.php" class="btn btn-secundario">
            📋 Mis reservas
        </a>
    </div>

</div>

<?php render_footer(); ?>
