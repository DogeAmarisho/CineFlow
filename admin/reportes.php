<?php
/**
 * CineFlow - Admin Reportes (RF-13)
 * Archivo: admin/reportes.php
 * Muestra la ocupación por función y otras métricas.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';

if (!esAdmin()) { header('Location: login.php'); exit; }

$pdo = obtenerConexion();

// Filters
$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-7 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$filtro_pelicula = (int)($_GET['pelicula_id'] ?? 0);

try {
    // Occupancy per function
    $q = "
        SELECT f.id, f.fecha_hora, f.precio, f.idioma,
               p.titulo AS pelicula,
               s.nombre AS sala, s.capacidad,
               COUNT(CASE WHEN r.estado IN ('confirmada','utilizada') THEN 1 END) AS confirmadas,
               COUNT(CASE WHEN r.estado = 'utilizada'  THEN 1 END) AS utilizadas,
               COUNT(CASE WHEN r.estado = 'cancelada'  THEN 1 END) AS canceladas,
               COUNT(CASE WHEN r.estado = 'pendiente'  THEN 1 END) AS pendientes,
               COALESCE(SUM(CASE WHEN r.estado IN ('confirmada','utilizada') THEN f.precio END),0) AS ingresos
        FROM   funciones f
        JOIN   peliculas p ON p.id = f.pelicula_id
        JOIN   salas     s ON s.id = f.sala_id
        LEFT   JOIN reservas r ON r.funcion_id = f.id
        WHERE  DATE(f.fecha_hora) BETWEEN :desde AND :hasta
    ";
    $params = [':desde'=>$desde, ':hasta'=>$hasta];
    if ($filtro_pelicula > 0) {
        $q .= " AND f.pelicula_id = :pid";
        $params[':pid'] = $filtro_pelicula;
    }
    $q .= " GROUP BY f.id ORDER BY f.fecha_hora DESC";

    $stmt = $pdo->prepare($q);
    $stmt->execute($params);
    $reporte = $stmt->fetchAll();

    // Totals
    $total_ingresos    = array_sum(array_column($reporte, 'ingresos'));
    $total_confirmadas = array_sum(array_column($reporte, 'confirmadas'));
    $total_utilizadas  = array_sum(array_column($reporte, 'utilizadas'));

    // Top películas in period
    $top_q = "
        SELECT p.titulo, COUNT(r.id) AS reservas, COALESCE(SUM(f.precio),0) AS ingresos
        FROM   reservas r
        JOIN   funciones f ON f.id = r.funcion_id
        JOIN   peliculas p ON p.id = f.pelicula_id
        WHERE  r.estado IN ('confirmada','utilizada')
          AND  DATE(r.fecha_reserva) BETWEEN :desde AND :hasta
        GROUP  BY p.id
        ORDER  BY reservas DESC
        LIMIT  5
    ";
    $top_stmt = $pdo->prepare($top_q);
    $top_stmt->execute([':desde'=>$desde, ':hasta'=>$hasta]);
    $top_peliculas = $top_stmt->fetchAll();

    $peliculas_select = $pdo->query("SELECT id, titulo FROM peliculas ORDER BY titulo")->fetchAll();

} catch (PDOException $e) {
    registrarError('admin-reportes', $e->getMessage());
    $reporte = $top_peliculas = $peliculas_select = [];
    $total_ingresos = $total_confirmadas = $total_utilizadas = 0;
}

$titulo_pagina    = 'Reportes';
$admin_nav_activo = 'reportes';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-topbar">
    <h1>📈 Reportes</h1>
</div>

<!-- Filters -->
<form method="GET" style="margin-bottom:1.5rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
    <div>
        <label style="font-size:.8rem;color:var(--texto-suave);display:block;margin-bottom:.25rem;">Desde</label>
        <input type="date" name="desde" value="<?= esc($desde) ?>">
    </div>
    <div>
        <label style="font-size:.8rem;color:var(--texto-suave);display:block;margin-bottom:.25rem;">Hasta</label>
        <input type="date" name="hasta" value="<?= esc($hasta) ?>">
    </div>
    <div>
        <label style="font-size:.8rem;color:var(--texto-suave);display:block;margin-bottom:.25rem;">Película</label>
        <select name="pelicula_id">
            <option value="0">— Todas —</option>
            <?php foreach ($peliculas_select as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $filtro_pelicula==$p['id']?'selected':''?>><?= esc($p['titulo']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primario">🔍 Filtrar</button>
    <a href="reportes.php" class="btn btn-secundario">↺ Resetear</a>
</form>

<!-- Summary stats -->
<div class="stats-grid" style="margin-bottom:1.5rem;">
    <div class="stat-card">
        <span class="stat-numero"><?= count($reporte) ?></span>
        <div class="stat-label">Funciones en el período</div>
    </div>
    <div class="stat-card">
        <span class="stat-numero"><?= $total_confirmadas ?></span>
        <div class="stat-label">Entradas vendidas</div>
    </div>
    <div class="stat-card">
        <span class="stat-numero"><?= $total_utilizadas ?></span>
        <div class="stat-label">Tickets utilizados</div>
    </div>
    <div class="stat-card">
        <span class="stat-numero"><?= formatearPrecio($total_ingresos) ?></span>
        <div class="stat-label">💰 Ingresos totales</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">

<!-- Función por función -->
<div>
    <h2 style="font-size:1rem;margin-bottom:1rem;color:var(--texto-suave);">📊 Ocupación por función</h2>
    <?php if (empty($reporte)): ?>
        <p style="color:var(--texto-muy-suave);">Sin datos para el período seleccionado.</p>
    <?php else: ?>
    <table class="admin-tabla">
        <thead>
            <tr>
                <th>Película / Sala</th>
                <th>Fecha</th>
                <th>Vendidas</th>
                <th>Utilizadas</th>
                <th>Ocupación</th>
                <th>Ingresos</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reporte as $r): ?>
            <?php $pct = $r['capacidad'] > 0 ? round($r['confirmadas'] / $r['capacidad'] * 100) : 0; ?>
            <tr>
                <td>
                    <div style="font-weight:600;font-size:.9rem;"><?= esc($r['pelicula']) ?></div>
                    <div style="font-size:.75rem;color:var(--texto-suave);"><?= esc($r['sala']) ?> · <?= esc($r['idioma']) ?></div>
                </td>
                <td style="white-space:nowrap;font-size:.85rem;"><?= date('d/m/Y H:i', strtotime($r['fecha_hora'])) ?></td>
                <td style="text-align:center;"><?= $r['confirmadas'] ?>/<?= $r['capacidad'] ?></td>
                <td style="text-align:center;">
                    <span class="badge <?= $r['utilizadas'] > 0 ? 'badge-azul' : 'badge-gris' ?>"><?= $r['utilizadas'] ?></span>
                </td>
                <td style="min-width:100px;">
                    <div style="font-size:.8rem;margin-bottom:.2rem;"><?= $pct ?>%</div>
                    <div class="reporte-barra-wrap">
                        <div class="reporte-barra" style="width:<?= $pct ?>%;background:<?= $pct >= 80 ? 'var(--verde)' : ($pct >= 50 ? 'var(--amarillo)' : 'var(--primario)') ?>;"></div>
                    </div>
                </td>
                <td style="font-weight:600;color:var(--verde);font-size:.9rem;"><?= formatearPrecio($r['ingresos']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Top películas -->
<div>
    <h2 style="font-size:1rem;margin-bottom:1rem;color:var(--texto-suave);">🏆 Top películas</h2>
    <?php if (empty($top_peliculas)): ?>
        <p style="color:var(--texto-muy-suave);">Sin datos.</p>
    <?php else: ?>
    <?php $max = max(array_column($top_peliculas, 'reservas') ?: [1]); ?>
    <div style="display:flex;flex-direction:column;gap:1rem;">
        <?php foreach ($top_peliculas as $i => $tp): ?>
        <?php $pct = round($tp['reservas'] / $max * 100); ?>
        <div style="background:var(--superficie);border:1px solid var(--borde);border-radius:var(--radio);padding:1rem;">
            <div style="display:flex;justify-content:space-between;margin-bottom:.5rem;">
                <div>
                    <span style="color:var(--primario);font-weight:700;margin-right:.5rem;">#<?= $i+1 ?></span>
                    <span style="font-weight:600;font-size:.9rem;"><?= esc($tp['titulo']) ?></span>
                </div>
                <div style="font-size:.85rem;color:var(--verde);font-weight:600;"><?= formatearPrecio($tp['ingresos']) ?></div>
            </div>
            <div style="font-size:.8rem;color:var(--texto-suave);margin-bottom:.4rem;"><?= $tp['reservas'] ?> reservas</div>
            <div class="reporte-barra-wrap"><div class="reporte-barra" style="width:<?= $pct ?>%"></div></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</div><!-- /grid -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
