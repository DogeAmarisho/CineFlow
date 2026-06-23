<?php
/**
 * Busca las reservas de un cliente por su correo (RF-09).
 */

require_once __DIR__ . '/config.php';

$email_busqueda = '';
$reservas_email = [];
$error_msg      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_busqueda = strtolower(trim($_POST['email'] ?? ''));
    if ($email_busqueda !== '') {
        if (!filter_var($email_busqueda, FILTER_VALIDATE_EMAIL)) {
            $error_msg = 'Ingresa un correo electrónico válido.';
        } else {
            $reservas_email = Reserva::ObtenerPorEmail($email_busqueda);
        }
    }
}

// Agrupar filas por codigo_reserva
$agrupadas = [];
foreach ($reservas_email as $fila) {
    $cod = $fila['codigo_reserva'];
    if (!isset($agrupadas[$cod])) {
        $agrupadas[$cod] = ['info' => $fila, 'asientos' => []];
    }
    $agrupadas[$cod]['asientos'][] = $fila['fila'] . $fila['asiento_numero'];
}

$titulo_pagina = 'Consultar reserva';
$nav_activo    = 'consultar';
render_header();
?>

<div class="consulta-wrap">

    <nav class="breadcrumb" style="margin-bottom:24px;">
        <a href="index.php">Inicio</a>
        <span class="breadcrumb-sep">›</span>
        <span>Consultar reserva</span>
    </nav>

    <h1 style="font-size:1.5rem;margin-bottom:24px;">🔍 Consultar reserva</h1>

    <?= renderFlash() ?>

    <div class="busqueda-card">
        <h2>Busca tus reservas por correo electrónico</h2>

        <form method="POST" action="consultar-reserva.php">
            <div class="input-busqueda">
                <input type="email"
                       name="email"
                       placeholder="tu@correo.com"
                       value="<?= esc($email_busqueda) ?>"
                       autocomplete="email"
                       required>
                <button type="submit" class="btn btn-primario">Buscar</button>
            </div>
        </form>
    </div>

    <?php if ($error_msg !== ''): ?>
        <div class="alerta alerta-error"><?= esc($error_msg) ?></div>
    <?php endif; ?>

    <?php if ($email_busqueda !== '' && $error_msg === ''): ?>

        <p style="color:var(--texto-suave);font-size:.9rem;margin-bottom:16px;">
            <?= count($agrupadas) ?> reserva<?= count($agrupadas) !== 1 ? 's' : '' ?>
            encontrada<?= count($agrupadas) !== 1 ? 's' : '' ?>
            para <strong><?= esc($email_busqueda) ?></strong>
        </p>

        <?php if (empty($agrupadas)): ?>
            <div class="sin-resultados-busqueda">
                <p style="font-size:2rem;">📭</p>
                <p>No se encontraron reservas para ese correo.</p>
                <p style="margin-top:8px;font-size:.85rem;">
                    ¿Usaste otro correo al reservar?
                </p>
            </div>
        <?php else: ?>
            <?php
            $ahora    = new DateTime();
            foreach ($agrupadas as $cod => $grupo):
                $info      = $grupo['info'];
                $asientos  = $grupo['asientos'];
                $n         = count($asientos);
                $total     = (float)$info['precio'] * $n;
                $estado    = $info['estado'];
                $fecha_fn  = new DateTime($info['fecha_hora']);
                $es_futura = $fecha_fn > $ahora;
            ?>
            <article class="res-card">
                <div class="res-card-inner">
                    <img class="res-poster"
                         src="<?= esc($info['poster'] ?? 'assets/img/sin-poster.svg') ?>"
                         alt="<?= esc($info['pelicula']) ?>"
                         onerror="this.src='assets/img/sin-poster.svg'"
                         <?= !$es_futura ? 'style="filter:grayscale(.6)"' : '' ?>>

                    <div class="res-cuerpo">
                        <p class="res-titulo"><?= esc($info['pelicula']) ?></p>
                        <div class="res-meta">
                            <span>📅 <?= $fecha_fn->format('d/m/Y H:i') ?> hrs</span>
                            <span>🎭 <?= esc($info['sala']) ?></span>
                            <span>🌐 <?= esc(ucfirst($info['idioma'])) ?></span>
                        </div>
                        <div class="chips">
                            <?php foreach ($asientos as $a): ?>
                                <span class="chip"><?= esc($a) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <a href="confirmacion.php?codigo=<?= urlencode($cod) ?>"
                           class="btn btn-secundario btn-sm">
                            Ver detalle
                        </a>
                    </div>

                    <div class="res-lateral">
                        <span class="badge badge-estado <?= claseBadgeEstado($estado) ?>">
                            <?= esc(etiquetaEstadoReserva($estado)) ?>
                        </span>
                        <span class="codigo-mono"><?= esc($cod) ?></span>
                        <span style="font-weight:700;"><?= formatearPrecio($total) ?></span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php render_footer(); ?>
