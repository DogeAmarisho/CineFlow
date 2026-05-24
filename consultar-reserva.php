<?php
/**
 * ============================================================
 *  CineFlow — consultar-reserva.php
 *  Propósito : Permite al cliente buscar sus reservas por email
 *              o consultar una reserva específica por código.
 *  Autores   : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/funciones.php';

$email_busqueda  = '';
$codigo_busqueda = '';
$reservas_email  = [];
$error_msg       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo_busqueda'] ?? 'email';

    if ($tipo === 'codigo') {
        $codigo_busqueda = strtoupper(trim($_POST['codigo'] ?? ''));
        if ($codigo_busqueda !== '') {
            redirigir('confirmacion.php?codigo=' . urlencode($codigo_busqueda));
        }
    } else {
        $email_busqueda = strtolower(trim($_POST['email'] ?? ''));
        if ($email_busqueda !== '') {
            if (!filter_var($email_busqueda, FILTER_VALIDATE_EMAIL)) {
                $error_msg = 'Ingresa un correo electrónico válido.';
            } else {
                $reservas_email = obtenerReservasPorEmail($email_busqueda);
            }
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
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .consulta-wrap { max-width: 760px; margin: 36px auto; padding: 0 20px 60px; }
    .busqueda-card { background: var(--superficie); border: 1px solid var(--borde); border-radius: var(--radio-lg); padding: 28px; margin-bottom: 32px; }
    .busqueda-card h2 { font-size: 1.1rem; margin-bottom: 20px; }
    .tabs-busqueda { display: flex; gap: 0; margin-bottom: 20px; border: 1px solid var(--borde); border-radius: var(--radio); overflow: hidden; }
    .tab-btn { flex: 1; padding: 10px; background: transparent; border: none; color: var(--texto-suave); font-size: .88rem; cursor: pointer; transition: background .2s, color .2s; }
    .tab-btn.activo { background: var(--primario); color: #fff; font-weight: 700; }
    .tab-panel { display: none; }
    .tab-panel.activo { display: block; }
    .input-busqueda { display: flex; gap: 10px; }
    .input-busqueda input { flex: 1; padding: 11px 14px; background: var(--superficie-alt, #2a2a2a); border: 1px solid var(--borde); border-radius: var(--radio); color: var(--texto); font-size: .95rem; outline: none; }
    .input-busqueda input:focus { border-color: var(--primario); }
    .res-card { background: var(--superficie); border: 1px solid var(--borde); border-radius: var(--radio-lg); overflow: hidden; margin-bottom: 16px; transition: border-color .2s; }
    .res-card:hover { border-color: var(--primario); }
    .res-card-inner { display: flex; gap: 16px; padding: 16px; align-items: flex-start; }
    .res-poster { width: 55px; height: 82px; object-fit: cover; border-radius: var(--radio); flex-shrink: 0; background: #111; }
    .res-cuerpo { flex: 1; min-width: 0; }
    .res-titulo { font-size: .95rem; font-weight: 700; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .res-meta { font-size: .82rem; color: var(--texto-suave); display: flex; flex-wrap: wrap; gap: 6px 14px; margin-bottom: 8px; }
    .chips { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 8px; }
    .chip { background: var(--borde-suave, #2a2a2a); border: 1px solid var(--borde); border-radius: 4px; padding: 2px 8px; font-size: .75rem; font-family: monospace; font-weight: 700; color: var(--texto-suave); }
    .res-lateral { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0; min-width: 110px; }
    .codigo-mono { font-family: monospace; font-size: .8rem; color: var(--amarillo, #f39c12); letter-spacing: 1px; }
    .sin-resultados { text-align: center; padding: 40px 20px; background: var(--superficie); border: 1px dashed var(--borde); border-radius: var(--radio-lg); color: var(--texto-suave); }
</style>

<div class="consulta-wrap">

    <nav class="breadcrumb" style="margin-bottom:24px;">
        <a href="index.php">Inicio</a>
        <span class="breadcrumb-sep">›</span>
        <span>Consultar reserva</span>
    </nav>

    <h1 style="font-size:1.5rem;margin-bottom:24px;">🔍 Consultar reserva</h1>

    <?= renderFlash() ?>

    <!-- ── Formulario de búsqueda ───────────────────────────── -->
    <div class="busqueda-card">
        <h2>Busca por correo o código de reserva</h2>

        <div class="tabs-busqueda">
            <button class="tab-btn activo" onclick="cambiarTab('email', this)">📧 Por correo</button>
            <button class="tab-btn"       onclick="cambiarTab('codigo', this)">🎫 Por código</button>
        </div>

        <!-- Tab email -->
        <div id="tab-email" class="tab-panel activo">
            <form method="POST" action="consultar-reserva.php">
                <input type="hidden" name="tipo_busqueda" value="email">
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

        <!-- Tab código -->
        <div id="tab-codigo" class="tab-panel">
            <form method="POST" action="consultar-reserva.php">
                <input type="hidden" name="tipo_busqueda" value="codigo">
                <div class="input-busqueda">
                    <input type="text"
                           name="codigo"
                           placeholder="CF-XXXXXX"
                           maxlength="10"
                           style="text-transform:uppercase;font-family:monospace;letter-spacing:2px;"
                           required>
                    <button type="submit" class="btn btn-primario">Ver detalle</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Resultados ────────────────────────────────────────── -->
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
            <div class="sin-resultados">
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
                         src="<?= esc($info['poster'] ?? 'assets/img/sin-poster.jpg') ?>"
                         alt="<?= esc($info['pelicula']) ?>"
                         onerror="this.src='assets/img/sin-poster.jpg'"
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

<script>
function cambiarTab(tab, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('activo'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('activo'));
    document.getElementById('tab-' + tab).classList.add('activo');
    btn.classList.add('activo');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
