<?php
/**
 * Cuantos asientos se han vendido por cada funcion (RF-13).
 */
require_once __DIR__ . '/../config.php';

if (!esAdmin()) { header('Location: login.php'); exit; }

$pdo = obtenerConexion();

try {
    $reporte = $pdo->query("
        SELECT f.id, f.fecha_hora, f.idioma,
               p.titulo AS pelicula,
               s.nombre AS sala, s.capacidad,
               COUNT(CASE WHEN r.estado IN ('confirmada','utilizada') THEN 1 END) AS vendidas
        FROM   funciones f
        JOIN   peliculas p ON p.id = f.pelicula_id
        JOIN   salas     s ON s.id = f.sala_id
        LEFT   JOIN reservas r ON r.funcion_id = f.id
        GROUP  BY f.id
        ORDER  BY f.fecha_hora DESC
    ")->fetchAll();
} catch (PDOException $e) {
    registrarError('admin-reportes', $e->getMessage());
    $reporte = [];
}

$titulo_pagina    = 'Reportes';
$admin_nav_activo = 'reportes';
render_admin_header();
?>

<div class="admin-topbar">
    <h1>📈 Reportes — Ocupación por función</h1>
</div>

<?php if (empty($reporte)): ?>
    <p style="color:var(--texto-muy-suave);">No hay funciones registradas.</p>
<?php else: ?>
<table class="admin-tabla">
    <thead>
        <tr>
            <th>Película / Sala</th>
            <th>Fecha</th>
            <th>Asientos vendidos</th>
            <th>Ocupación</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($reporte as $r): ?>
        <?php $pct = $r['capacidad'] > 0 ? round($r['vendidas'] / $r['capacidad'] * 100) : 0; ?>
        <tr>
            <td>
                <div style="font-weight:600;font-size:.9rem;"><?= esc($r['pelicula']) ?></div>
                <div style="font-size:.75rem;color:var(--texto-suave);"><?= esc($r['sala']) ?> · <?= esc($r['idioma']) ?></div>
            </td>
            <td style="white-space:nowrap;font-size:.85rem;"><?= date('d/m/Y H:i', strtotime($r['fecha_hora'])) ?></td>
            <td style="text-align:center;"><?= $r['vendidas'] ?>/<?= $r['capacidad'] ?></td>
            <td style="min-width:100px;">
                <div style="font-size:.8rem;margin-bottom:.2rem;"><?= $pct ?>%</div>
                <div class="reporte-barra-wrap">
                    <div class="reporte-barra" style="width:<?= $pct ?>%;background:<?= $pct >= 80 ? 'var(--verde)' : ($pct >= 50 ? 'var(--amarillo)' : 'var(--primario)') ?>;"></div>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php render_admin_footer(); ?>
