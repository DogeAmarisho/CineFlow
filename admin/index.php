<?php
/**
 * CineFlow - Admin Dashboard
 * Archivo: admin/index.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';

$titulo_pagina    = 'Dashboard';
$admin_nav_activo = 'dashboard';

// Guard: only admins
if (!esAdmin()) { header('Location: login.php'); exit; }

$pdo = obtenerConexion();

// Stats
try {
    $stats = [];

    $stats['peliculas'] = $pdo->query("SELECT COUNT(*) FROM peliculas WHERE activa=1")->fetchColumn();
    $stats['funciones_hoy'] = $pdo->query("SELECT COUNT(*) FROM funciones WHERE DATE(fecha_hora)=CURDATE() AND activa=1")->fetchColumn();
    $stats['reservas_hoy'] = $pdo->query("SELECT COUNT(*) FROM reservas WHERE DATE(fecha_reserva)=CURDATE() AND estado IN ('confirmada','utilizada')")->fetchColumn();
    $stats['ingresos_hoy'] = $pdo->query("SELECT COALESCE(SUM(f.precio),0) FROM reservas r JOIN funciones f ON f.id=r.funcion_id WHERE DATE(r.fecha_reserva)=CURDATE() AND r.estado IN ('confirmada','utilizada')")->fetchColumn();
    $stats['reservas_total'] = $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado IN ('confirmada','utilizada')")->fetchColumn();

    // Últimas 10 reservas
    $ultimas = $pdo->query("
        SELECT r.codigo_reserva, r.nombre_cliente, r.email_cliente,
               r.estado, r.fecha_reserva,
               p.titulo AS pelicula, f.fecha_hora, f.precio
        FROM   reservas r
        JOIN   funciones f ON f.id = r.funcion_id
        JOIN   peliculas p ON p.id = f.pelicula_id
        ORDER  BY r.fecha_reserva DESC
        LIMIT  10
    ")->fetchAll();

    // Próximas funciones (hoy + mañana)
    $proximas = $pdo->query("
        SELECT f.id, f.fecha_hora, f.precio, f.idioma,
               p.titulo AS pelicula,
               s.nombre AS sala,
               COUNT(CASE WHEN r.estado IN ('confirmada','utilizada') THEN 1 END) AS ocupados,
               s.capacidad
        FROM   funciones f
        JOIN   peliculas p ON p.id = f.pelicula_id
        JOIN   salas     s ON s.id = f.sala_id
        LEFT   JOIN reservas r ON r.funcion_id = f.id
        WHERE  f.fecha_hora >= NOW()
          AND  f.fecha_hora <= DATE_ADD(NOW(), INTERVAL 2 DAY)
          AND  f.activa = 1
        GROUP  BY f.id
        ORDER  BY f.fecha_hora ASC
        LIMIT  8
    ")->fetchAll();

} catch (PDOException $e) {
    registrarError('admin-index', $e->getMessage());
    $stats = array_fill_keys(['peliculas','funciones_hoy','reservas_hoy','ingresos_hoy','reservas_total'], 0);
    $ultimas = $proximas = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-topbar">
    <h1>📊 Dashboard</h1>
    <span style="color:var(--texto-suave);font-size:.85rem;"><?= date('l d \d\e F Y') ?></span>
</div>

<?= renderFlash() ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-numero"><?= $stats['peliculas'] ?></span>
        <div class="stat-label">🎬 Películas activas</div>
    </div>
    <div class="stat-card">
        <span class="stat-numero"><?= $stats['funciones_hoy'] ?></span>
        <div class="stat-label">🗓️ Funciones hoy</div>
    </div>
    <div class="stat-card">
        <span class="stat-numero"><?= $stats['reservas_hoy'] ?></span>
        <div class="stat-label">🎟️ Reservas hoy</div>
    </div>
    <div class="stat-card">
        <span class="stat-numero"><?= formatearPrecio($stats['ingresos_hoy']) ?></span>
        <div class="stat-label">💰 Ingresos hoy</div>
    </div>
    <div class="stat-card">
        <span class="stat-numero"><?= $stats['reservas_total'] ?></span>
        <div class="stat-label">✅ Reservas totales</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-top:1rem;">

<!-- Próximas funciones -->
<div>
    <h2 style="font-size:1rem;margin-bottom:1rem;color:var(--texto-suave);">🗓️ Próximas funciones (48h)</h2>
    <?php if (empty($proximas)): ?>
        <p style="color:var(--texto-muy-suave);">No hay funciones programadas.</p>
    <?php else: ?>
    <table class="admin-tabla">
        <thead><tr><th>Película / Sala</th><th>Hora</th><th>Ocupación</th></tr></thead>
        <tbody>
        <?php foreach ($proximas as $f): ?>
            <?php $pct = $f['capacidad'] > 0 ? round($f['ocupados']/$f['capacidad']*100) : 0; ?>
            <tr>
                <td>
                    <div style="font-weight:600;"><?= esc($f['pelicula']) ?></div>
                    <div style="font-size:.8rem;color:var(--texto-suave);"><?= esc($f['sala']) ?></div>
                </td>
                <td style="white-space:nowrap;font-size:.85rem;"><?= date('d/m H:i', strtotime($f['fecha_hora'])) ?></td>
                <td>
                    <div style="font-size:.8rem;"><?= $f['ocupados'] ?>/<?= $f['capacidad'] ?> (<?= $pct ?>%)</div>
                    <div class="reporte-barra-wrap"><div class="reporte-barra" style="width:<?= $pct ?>%"></div></div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Últimas reservas -->
<div>
    <h2 style="font-size:1rem;margin-bottom:1rem;color:var(--texto-suave);">🎟️ Últimas 10 reservas</h2>
    <?php if (empty($ultimas)): ?>
        <p style="color:var(--texto-muy-suave);">No hay reservas aún.</p>
    <?php else: ?>
    <table class="admin-tabla">
        <thead><tr><th>Código</th><th>Cliente</th><th>Estado</th><th>Fecha</th></tr></thead>
        <tbody>
        <?php foreach ($ultimas as $r): ?>
            <tr>
                <td><code style="font-size:.8rem;color:var(--primario);"><?= esc($r['codigo_reserva']) ?></code></td>
                <td style="font-size:.85rem;"><?= esc($r['nombre_cliente']) ?></td>
                <td><span class="badge <?= claseBadgeEstado($r['estado']) ?>"><?= etiquetaEstadoReserva($r['estado']) ?></span></td>
                <td style="font-size:.8rem;white-space:nowrap;"><?= date('d/m H:i', strtotime($r['fecha_reserva'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</div><!-- /grid -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
