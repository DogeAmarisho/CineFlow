<?php
/**
 * CineFlow - Admin Validar Ticket (RF-10)
 * Archivo: admin/validar-ticket.php
 * Permite al admin buscar una reserva por código y marcarla como 'utilizada'.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';

if (!esAdmin()) { header('Location: login.php'); exit; }

$pdo       = obtenerConexion();
$codigo    = strtoupper(trim($_GET['codigo'] ?? $_POST['codigo'] ?? ''));
$resultado = null;
$accion_ok = false;
$error_msg = '';

// Handle validation action (mark all seats of the reservation as 'utilizada')
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validar_codigo'])) {
    verificarTokenCsrf($_POST['csrf_token'] ?? '');
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));

    try {
        $pdo->beginTransaction();

        // Check current state of any row for this codigo_reserva
        $stmt = $pdo->prepare("SELECT estado FROM reservas WHERE codigo_reserva=:codigo LIMIT 1 FOR UPDATE");
        $stmt->execute([':codigo' => $codigo]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            $pdo->rollBack();
            $error_msg = '❌ Reserva no encontrada con código ' . esc($codigo) . '.';
        } elseif ($reserva['estado'] === 'utilizada') {
            $pdo->rollBack();
            $error_msg = '⚠️ Este ticket ya fue utilizado anteriormente.';
        } elseif ($reserva['estado'] !== 'confirmada') {
            $pdo->rollBack();
            $error_msg = '❌ Solo se pueden validar reservas confirmadas. Estado actual: ' . etiquetaEstadoReserva($reserva['estado']);
        } else {
            // Mark ALL seats of this reservation as utilizada
            $pdo->prepare("UPDATE reservas SET estado='utilizada' WHERE codigo_reserva=:codigo AND estado='confirmada'")
                ->execute([':codigo' => $codigo]);
            $pdo->commit();
            $accion_ok = true;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        registrarError('admin-validar-ticket', $e->getMessage());
        $error_msg = '❌ Error: ' . $e->getMessage();
    }
}

// Search by code (after possible update, re-read fresh state)
if ($codigo !== '') {
    try {
        $stmt = $pdo->prepare("
            SELECT r.id, r.codigo_reserva, r.nombre_cliente, r.email_cliente,
                   r.estado, r.fecha_reserva, r.fecha_expiracion,
                   a.fila, a.numero AS asiento_numero, a.tipo AS tipo_asiento,
                   f.fecha_hora, f.precio, f.idioma,
                   p.titulo AS pelicula, p.clasificacion,
                   s.nombre AS sala, s.tipo AS tipo_sala
            FROM   reservas r
            JOIN   asientos  a ON a.id = r.asiento_id
            JOIN   funciones f ON f.id = r.funcion_id
            JOIN   peliculas p ON p.id = f.pelicula_id
            JOIN   salas     s ON s.id = f.sala_id
            WHERE  r.codigo_reserva = :codigo
            ORDER  BY a.fila, a.numero
        ");
        $stmt->execute([':codigo' => $codigo]);
        $resultado = $stmt->fetchAll();
        if (empty($resultado) && $error_msg === '') {
            $error_msg = '❌ No se encontró ninguna reserva con el código <strong>' . esc($codigo) . '</strong>.';
        }
    } catch (PDOException $e) {
        registrarError('admin-validar-ticket-search', $e->getMessage());
        $error_msg = 'Error al buscar la reserva.';
    }
}

$titulo_pagina    = 'Validar Ticket';
$admin_nav_activo = 'validar';
require_once __DIR__ . '/includes/header.php';

$primer = !empty($resultado) ? $resultado[0] : null;
$estado_actual = $primer['estado'] ?? '';
?>

<div class="admin-topbar">
    <h1>✅ Validar Ticket</h1>
</div>

<!-- Search form -->
<form method="GET" action="validar-ticket.php" style="margin-bottom:1.5rem;">
    <div style="display:flex;gap:.75rem;max-width:500px;">
        <input type="text" name="codigo" placeholder="Ej: CF-A3X9KW"
               value="<?= esc($codigo) ?>"
               style="flex:1;text-transform:uppercase;letter-spacing:.1em;font-family:var(--fuente-mono);"
               autofocus autocomplete="off" maxlength="10">
        <button type="submit" class="btn btn-primario">🔍 Buscar</button>
    </div>
</form>

<?php if ($accion_ok): ?>
<div class="alerta alerta-exito" role="alert">
    ✅ <strong>¡Ticket validado exitosamente!</strong> Todos los asientos fueron marcados como utilizados.
</div>
<?php elseif ($error_msg !== ''): ?>
<div class="alerta alerta-error" role="alert"><?= $error_msg ?></div>
<?php endif; ?>

<?php if (!empty($resultado) && $primer): ?>
<?php
$clase_ticket = match($estado_actual) {
    'confirmada' => 'ticket-ok',
    'utilizada'  => 'ticket-usada',
    default      => 'ticket-error',
};
$funcion_pasada = strtotime($primer['fecha_hora']) < time();
?>
<div class="ticket-resultado <?= $clase_ticket ?>">

    <!-- Header info -->
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
        <div>
            <div style="font-size:1.4rem;font-weight:700;margin-bottom:.25rem;"><?= esc($primer['pelicula']) ?></div>
            <div style="color:var(--texto-suave);font-size:.9rem;">
                📍 <?= esc($primer['sala']) ?> (<?= esc($primer['tipo_sala']) ?>) &nbsp;|&nbsp;
                🗓️ <?= date('d/m/Y H:i', strtotime($primer['fecha_hora'])) ?> &nbsp;|&nbsp;
                🌐 <?= esc($primer['idioma']) ?>
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:1.8rem;font-family:var(--fuente-mono);color:var(--primario);font-weight:700;"><?= esc($primer['codigo_reserva']) ?></div>
            <span class="badge <?= claseBadgeEstado($estado_actual) ?>" style="font-size:.9rem;"><?= etiquetaEstadoReserva($estado_actual) ?></span>
        </div>
    </div>

    <!-- Cliente -->
    <div style="background:var(--superficie-alt);border-radius:var(--radio);padding:1rem;margin-bottom:1rem;">
        <div style="font-weight:600;margin-bottom:.5rem;">👤 Datos del cliente</div>
        <div>Nombre: <strong><?= esc($primer['nombre_cliente']) ?></strong></div>
        <div>Email: <strong><?= esc($primer['email_cliente']) ?></strong></div>
        <div style="font-size:.8rem;color:var(--texto-suave);margin-top:.25rem;">
            Reserva realizada: <?= date('d/m/Y H:i', strtotime($primer['fecha_reserva'])) ?>
        </div>
    </div>

    <!-- Asientos -->
    <div style="margin-bottom:1.5rem;">
        <div style="font-weight:600;margin-bottom:.5rem;">🎟️ Asientos (<?= count($resultado) ?>)</div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <?php foreach ($resultado as $r): ?>
                <span class="badge badge-azul" style="font-family:var(--fuente-mono);font-size:.9rem;padding:.35rem .75rem;">
                    <?= esc($r['fila']) ?><?= $r['asiento_numero'] ?>
                    <?php if ($r['tipo_asiento'] !== 'normal'): ?>
                        <small>(<?= esc($r['tipo_asiento']) ?>)</small>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Total -->
    <div style="font-size:1.1rem;margin-bottom:1.5rem;">
        💰 Total: <strong><?= formatearPrecio($primer['precio'] * count($resultado)) ?></strong>
        <span style="color:var(--texto-suave);font-size:.85rem;">(<?= count($resultado) ?> × <?= formatearPrecio($primer['precio']) ?>)</span>
    </div>

    <!-- Action button -->
    <?php if ($estado_actual === 'confirmada'): ?>
        <?php if ($funcion_pasada): ?>
            <div class="alerta alerta-aviso">⚠️ La función ya pasó.</div>
        <?php endif; ?>
        <form method="POST" onsubmit="return confirm('¿Confirmar validación del ticket? Se marcarán todos los asientos como utilizados.');">
            <?= campoCsrf() ?>
            <input type="hidden" name="codigo" value="<?= esc($primer['codigo_reserva']) ?>">
            <input type="hidden" name="validar_codigo" value="1">
            <button type="submit" class="btn btn-primario" style="font-size:1rem;padding:.75rem 2rem;">
                ✅ Marcar como UTILIZADO
            </button>
        </form>
    <?php elseif ($estado_actual === 'utilizada'): ?>
        <div class="alerta alerta-exito">✅ Este ticket ya fue utilizado. No se puede validar dos veces.</div>
    <?php else: ?>
        <div class="alerta alerta-error">❌ Estado inválido para validar: <?= etiquetaEstadoReserva($estado_actual) ?></div>
    <?php endif; ?>

</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
