<?php
/**
 * CineFlow - Admin Películas
 * Archivo: admin/peliculas.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';

if (!esAdmin()) { header('Location: login.php'); exit; }

$pdo = obtenerConexion();

// Handle toggle activa (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    verificarTokenCsrf($_POST['csrf_token'] ?? '');
    $id = (int)$_POST['toggle_id'];
    try {
        $pdo->prepare("UPDATE peliculas SET activa = 1 - activa WHERE id = :id")->execute([':id' => $id]);
        flashMensaje('exito', 'Estado de la película actualizado.');
    } catch (PDOException $e) {
        registrarError('admin-peliculas-toggle', $e->getMessage());
        flashMensaje('error', 'No se pudo actualizar el estado.');
    }
    header('Location: peliculas.php'); exit;
}

// Handle delete (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    verificarTokenCsrf($_POST['csrf_token'] ?? '');
    $id = (int)$_POST['eliminar_id'];
    try {
        // Check if has active funciones
        $tiene = $pdo->prepare("SELECT COUNT(*) FROM funciones WHERE pelicula_id=:id AND activa=1 AND fecha_hora>NOW()");
        $tiene->execute([':id' => $id]);
        if ($tiene->fetchColumn() > 0) {
            flashMensaje('error', 'No se puede eliminar: la película tiene funciones futuras activas.');
        } else {
            $pdo->prepare("DELETE FROM peliculas WHERE id = :id")->execute([':id' => $id]);
            flashMensaje('exito', 'Película eliminada correctamente.');
        }
    } catch (PDOException $e) {
        registrarError('admin-peliculas-delete', $e->getMessage());
        flashMensaje('error', 'Error al eliminar la película: ' . $e->getMessage());
    }
    header('Location: peliculas.php'); exit;
}

// Fetch all movies
try {
    $peliculas = $pdo->query("
        SELECT p.*,
               COUNT(DISTINCT f.id) AS total_funciones
        FROM   peliculas p
        LEFT   JOIN funciones f ON f.pelicula_id = p.id AND f.activa = 1
        GROUP  BY p.id
        ORDER  BY p.activa DESC, p.titulo ASC
    ")->fetchAll();
} catch (PDOException $e) {
    registrarError('admin-peliculas', $e->getMessage());
    $peliculas = [];
}

$titulo_pagina    = 'Gestión de Películas';
$admin_nav_activo = 'peliculas';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-topbar">
    <h1>🎬 Películas</h1>
    <a href="nueva-pelicula.php" class="btn btn-primario">+ Nueva película</a>
</div>

<?= renderFlash() ?>

<?php if (empty($peliculas)): ?>
    <p style="color:var(--texto-suave);">No hay películas registradas.</p>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="admin-tabla">
    <thead>
        <tr>
            <th>Póster</th>
            <th>Título</th>
            <th>Género</th>
            <th>Clasif.</th>
            <th>Duración</th>
            <th>Funciones</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($peliculas as $p): ?>
        <tr style="<?= !$p['activa'] ? 'opacity:.5;' : '' ?>">
            <td>
                <?php if ($p['imagen']): ?>
                    <img src="../<?= esc($p['imagen']) ?>" alt="" style="width:40px;height:56px;object-fit:cover;border-radius:4px;">
                <?php else: ?>
                    <div style="width:40px;height:56px;background:var(--borde);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:.9rem;">🎬</div>
                <?php endif; ?>
            </td>
            <td>
                <div style="font-weight:600;"><?= esc($p['titulo']) ?></div>
                <?php if ($p['fecha_estreno']): ?>
                    <div style="font-size:.75rem;color:var(--texto-muy-suave);">Estreno: <?= date('d/m/Y', strtotime($p['fecha_estreno'])) ?></div>
                <?php endif; ?>
            </td>
            <td style="font-size:.85rem;"><?= esc($p['genero']) ?></td>
            <td><span class="badge <?= claseBadgeClasificacion($p['clasificacion']) ?>"><?= esc($p['clasificacion']) ?></span></td>
            <td style="font-size:.85rem;"><?= formatearDuracion($p['duracion_min']) ?></td>
            <td style="text-align:center;"><?= $p['total_funciones'] ?></td>
            <td>
                <span class="badge <?= $p['activa'] ? 'badge-verde' : 'badge-gris' ?>">
                    <?= $p['activa'] ? 'Activa' : 'Inactiva' ?>
                </span>
            </td>
            <td>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                    <a href="editar-pelicula.php?id=<?= $p['id'] ?>" class="btn-accion btn-editar">✏️ Editar</a>
                    <!-- Toggle activa -->
                    <form method="POST" style="display:inline;">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="toggle_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn-accion btn-activar">
                            <?= $p['activa'] ? '🚫 Desactivar' : '✅ Activar' ?>
                        </button>
                    </form>
                    <!-- Delete -->
                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar «<?= esc($p['titulo']) ?>»? Esta acción no se puede deshacer.')">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="eliminar_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn-accion btn-eliminar">🗑️ Eliminar</button>
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
