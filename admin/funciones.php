<?php
/**
 * CineFlow - Admin Funciones
 * Archivo: admin/funciones.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';

if (!esAdmin()) { header('Location: login.php'); exit; }

$pdo = obtenerConexion();

// Toggle activa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    verificarTokenCsrf($_POST['csrf_token'] ?? '');
    $id = (int)$_POST['toggle_id'];
    try {
        $pdo->prepare("UPDATE funciones SET activa = 1-activa WHERE id=:id")->execute([':id'=>$id]);
        flashMensaje('exito','Estado de función actualizado.');
    } catch (PDOException $e) {
        registrarError('admin-funciones-toggle', $e->getMessage());
        flashMensaje('error','No se pudo actualizar.');
    }
    header('Location: funciones.php'); exit;
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    verificarTokenCsrf($_POST['csrf_token'] ?? '');
    $id = (int)$_POST['eliminar_id'];
    try {
        $tiene = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE funcion_id=:id AND estado IN ('confirmada','pendiente')");
        $tiene->execute([':id'=>$id]);
        if ($tiene->fetchColumn() > 0) {
            flashMensaje('error','No se puede eliminar: tiene reservas activas.');
        } else {
            $pdo->prepare("DELETE FROM funciones WHERE id=:id")->execute([':id'=>$id]);
            flashMensaje('exito','Función eliminada.');
        }
    } catch (PDOException $e) {
        registrarError('admin-funciones-delete', $e->getMessage());
        flashMensaje('error','Error al eliminar: ' . $e->getMessage());
    }
    header('Location: funciones.php'); exit;
}

// Filter
$filtro_pelicula = (int)($_GET['pelicula_id'] ?? 0);

try {
    $query = "
        SELECT f.*, p.titulo AS pelicula, p.imagen AS poster, s.nombre AS sala, s.tipo AS tipo_sala, s.capacidad,
               COUNT(CASE WHEN r.estado IN ('confirmada','utilizada') THEN 1 END) AS reservas_ok,
               COUNT(CASE WHEN r.estado = 'pendiente' THEN 1 END) AS reservas_pendientes
        FROM   funciones f
        JOIN   peliculas p ON p.id = f.pelicula_id
        JOIN   salas     s ON s.id = f.sala_id
        LEFT   JOIN reservas r ON r.funcion_id = f.id
    ";
    $params = [];
    if ($filtro_pelicula > 0) {
        $query  .= " WHERE f.pelicula_id = :pid";
        $params[':pid'] = $filtro_pelicula;
    }
    $query .= " GROUP BY f.id ORDER BY f.fecha_hora DESC LIMIT 100";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $funciones_list = $stmt->fetchAll();

    $peliculas_select = $pdo->query("SELECT id, titulo FROM peliculas ORDER BY titulo")->fetchAll();

} catch (PDOException $e) {
    registrarError('admin-funciones', $e->getMessage());
    $funciones_list = $peliculas_select = [];
}

$titulo_pagina    = 'Gestión de Funciones';
$admin_nav_activo = 'funciones';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-topbar">
    <h1>🗓️ Funciones</h1>
    <a href="nueva-funcion.php" class="btn btn-primario">+ Nueva función</a>
</div>

<?= renderFlash() ?>

<!-- Filter -->
<form method="GET" style="margin-bottom:1rem;display:flex;gap:.75rem;align-items:center;">
    <select name="pelicula_id" onchange="this.form.submit()" style="max-width:280px;">
        <option value="0">— Todas las películas —</option>
        <?php foreach ($peliculas_select as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $filtro_pelicula==$p['id']?'selected':''?>><?= esc($p['titulo']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<?php if (empty($funciones_list)): ?>
    <p style="color:var(--texto-suave);">No hay funciones.</p>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="admin-tabla">
    <thead>
        <tr>
            <th>Película</th>
            <th>Sala</th>
            <th>Fecha / Hora</th>
            <th>Precio</th>
            <th>Idioma</th>
            <th>Ocupación</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($funciones_list as $f): ?>
        <?php
        $pct = $f['capacidad'] > 0 ? round(($f['reservas_ok'] + $f['reservas_pendientes']) / $f['capacidad'] * 100) : 0;
        $pasada = strtotime($f['fecha_hora']) < time();
        ?>
        <tr style="<?= !$f['activa'] ? 'opacity:.5;' : '' ?><?= $pasada ? 'background:rgba(0,0,0,.2);' : '' ?>">
            <td style="font-weight:600;font-size:.9rem;"><?= esc($f['pelicula']) ?></td>
            <td style="font-size:.85rem;"><?= esc($f['sala']) ?> <small style="color:var(--texto-muy-suave);">(<?= esc($f['tipo_sala']) ?>)</small></td>
            <td style="white-space:nowrap;font-size:.85rem;">
                <?= date('d/m/Y', strtotime($f['fecha_hora'])) ?><br>
                <span style="color:var(--primario);font-weight:600;"><?= date('H:i', strtotime($f['fecha_hora'])) ?></span>
                <?php if ($pasada): ?> <span style="color:var(--texto-muy-suave);font-size:.75rem;">(pasada)</span><?php endif; ?>
            </td>
            <td><?= formatearPrecio($f['precio']) ?></td>
            <td style="font-size:.85rem;"><?= esc($f['idioma']) ?></td>
            <td>
                <div style="font-size:.8rem;min-width:90px;">
                    <?= $f['reservas_ok'] ?>/<?= $f['capacidad'] ?> (<?= $pct ?>%)
                    <?php if ($f['reservas_pendientes'] > 0): ?>
                        <span style="color:var(--amarillo);"> +<?= $f['reservas_pendientes'] ?> pend.</span>
                    <?php endif; ?>
                </div>
                <div class="reporte-barra-wrap"><div class="reporte-barra" style="width:<?= $pct ?>%"></div></div>
            </td>
            <td><span class="badge <?= $f['activa'] ? 'badge-verde' : 'badge-gris' ?>"><?= $f['activa'] ? 'Activa' : 'Inactiva' ?></span></td>
            <td>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                    <a href="editar-funcion.php?id=<?= $f['id'] ?>" class="btn-accion btn-editar">✏️ Editar</a>
                    <form method="POST" style="display:inline;">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="toggle_id" value="<?= $f['id'] ?>">
                        <button class="btn-accion btn-activar"><?= $f['activa'] ? '🚫 Desactivar' : '✅ Activar' ?></button>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar esta función?')">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="eliminar_id" value="<?= $f['id'] ?>">
                        <button class="btn-accion btn-eliminar">🗑️ Eliminar</button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
