<?php
/**
 * Lista de peliculas + su formulario de crear/editar en un solo archivo.
 * Sin parametros => lista. ?form=1 => crear. ?id=N => editar.
 */
require_once __DIR__ . '/../config.php';

if (!esAdmin()) { header('Location: login.php'); exit; }

$pdo = obtenerConexion();
$id  = (int)($_GET['id'] ?? 0);
$es_edicion = $id > 0;
$mostrar_formulario = $es_edicion || isset($_GET['form']);

// activar/desactivar y eliminar se hacen desde la misma lista
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    verificarTokenCsrf($_POST['csrf_token'] ?? '');
    $tid = (int)$_POST['toggle_id'];
    try {
        $pdo->prepare("UPDATE peliculas SET activa = 1 - activa WHERE id = :id")->execute([':id' => $tid]);
        flashMensaje('exito', 'Estado de la película actualizado.');
    } catch (PDOException $e) {
        registrarError('admin-peliculas-toggle', $e->getMessage());
        flashMensaje('error', 'No se pudo actualizar el estado.');
    }
    header('Location: peliculas.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    verificarTokenCsrf($_POST['csrf_token'] ?? '');
    $did = (int)$_POST['eliminar_id'];
    try {
        $tiene = $pdo->prepare("SELECT COUNT(*) FROM funciones WHERE pelicula_id=:id AND activa=1 AND fecha_hora>NOW()");
        $tiene->execute([':id' => $did]);
        if ($tiene->fetchColumn() > 0) {
            flashMensaje('error', 'No se puede eliminar: la película tiene funciones futuras activas.');
        } else {
            $pdo->prepare("DELETE FROM peliculas WHERE id = :id")->execute([':id' => $did]);
            flashMensaje('exito', 'Película eliminada correctamente.');
        }
    } catch (PDOException $e) {
        registrarError('admin-peliculas-delete', $e->getMessage());
        flashMensaje('error', 'Error al eliminar la película: ' . $e->getMessage());
    }
    header('Location: peliculas.php'); exit;
}

// modo formulario (crear o editar)
if ($mostrar_formulario) {
    $datos = ['titulo'=>'','genero'=>'','sinopsis'=>'','clasificacion'=>'TE','duracion_min'=>'','imagen'=>'','fecha_estreno'=>'','activa'=>1];

    if ($es_edicion) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM peliculas WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $fila = $stmt->fetch();
        } catch (PDOException $e) {
            registrarError('admin-peliculas-form-load', $e->getMessage());
            $fila = null;
        }
        if (!$fila) { flashMensaje('error', 'Película no encontrada.'); header('Location: peliculas.php'); exit; }
        $datos = $fila;
    }

    $errores = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verificarTokenCsrf($_POST['csrf_token'] ?? '');

        $datos['titulo']        = trim($_POST['titulo']        ?? '');
        $datos['genero']        = trim($_POST['genero']        ?? '');
        $datos['sinopsis']      = trim($_POST['sinopsis']      ?? '');
        $datos['clasificacion'] = $_POST['clasificacion']     ?? 'TE';
        $datos['duracion_min']  = (int)($_POST['duracion_min'] ?? 0);
        $datos['fecha_estreno'] = trim($_POST['fecha_estreno'] ?? '');
        $datos['activa']        = isset($_POST['activa']) ? 1 : 0;

        if ($datos['titulo'] === '')              $errores[] = 'El título es obligatorio.';
        if ($datos['genero'] === '')              $errores[] = 'El género es obligatorio.';
        if (!in_array($datos['clasificacion'], ['TE','TE+7','MA+14','MA+18'])) $errores[] = 'Clasificación inválida.';
        if ($datos['duracion_min'] < 1)           $errores[] = 'La duración debe ser mayor a 0.';

        if (!empty($_FILES['imagen']['name'])) {
            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $allowed)) {
                $errores[] = 'Solo se permiten imágenes JPG, PNG o WebP.';
            } elseif ($_FILES['imagen']['size'] > 2 * 1024 * 1024) {
                $errores[] = 'La imagen no puede superar los 2 MB.';
            } else {
                $dir = __DIR__ . '/../uploads/peliculas/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $nombre = uniqid('pelicula_') . '.' . $ext;
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $dir . $nombre)) {
                    if ($es_edicion && !empty($datos['imagen']) && file_exists(__DIR__ . '/../' . $datos['imagen'])) {
                        @unlink(__DIR__ . '/../' . $datos['imagen']);
                    }
                    $datos['imagen'] = 'uploads/peliculas/' . $nombre;
                } else {
                    $errores[] = 'No se pudo guardar la imagen. Verifica permisos.';
                }
            }
        }

        if (empty($errores)) {
            try {
                $params = [
                    ':titulo'        => $datos['titulo'],
                    ':genero'        => $datos['genero'],
                    ':sinopsis'      => $datos['sinopsis'] ?: null,
                    ':clasificacion' => $datos['clasificacion'],
                    ':duracion_min'  => $datos['duracion_min'] ?: null,
                    ':imagen'        => $datos['imagen'] ?: null,
                    ':fecha_estreno' => $datos['fecha_estreno'] ?: null,
                    ':activa'        => $datos['activa'],
                ];
                if ($es_edicion) {
                    $params[':id'] = $id;
                    $stmt = $pdo->prepare("
                        UPDATE peliculas SET titulo=:titulo, genero=:genero, sinopsis=:sinopsis,
                            clasificacion=:clasificacion, duracion_min=:duracion_min,
                            imagen=:imagen, fecha_estreno=:fecha_estreno, activa=:activa
                        WHERE id=:id
                    ");
                    $mensaje = '¡Película «' . $datos['titulo'] . '» actualizada!';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO peliculas (titulo, genero, sinopsis, clasificacion, duracion_min, imagen, fecha_estreno, activa)
                        VALUES (:titulo, :genero, :sinopsis, :clasificacion, :duracion_min, :imagen, :fecha_estreno, :activa)
                    ");
                    $mensaje = '¡Película «' . $datos['titulo'] . '» creada correctamente!';
                }
                $stmt->execute($params);
                flashMensaje('exito', $mensaje);
                header('Location: peliculas.php'); exit;
            } catch (PDOException $e) {
                registrarError('admin-peliculas-form-guardar', $e->getMessage());
                $errores[] = 'Error al guardar en la base de datos.';
            }
        }
    }

    $titulo_pagina    = $es_edicion ? 'Editar Película' : 'Nueva Película';
    $admin_nav_activo = 'peliculas';
    render_admin_header();
    ?>

    <div class="admin-topbar">
        <h1><?= $es_edicion ? '✏️ Editar: ' . esc($datos['titulo']) : '🎬 Nueva Película' ?></h1>
        <a href="peliculas.php" class="btn btn-secundario">← Volver</a>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="alerta alerta-error">
        <strong>Corrige los siguientes errores:</strong>
        <ul style="margin:.5rem 0 0 1.2rem;">
            <?php foreach ($errores as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="peliculas.php<?= $es_edicion ? '?id=' . $id : '?form=1' ?>" enctype="multipart/form-data" novalidate>
    <?= campoCsrf() ?>
    <div class="admin-form">
        <div class="form-grid">
            <div class="campo form-full">
                <label for="titulo">Título *</label>
                <input type="text" id="titulo" name="titulo" required maxlength="200" value="<?= esc($datos['titulo']) ?>">
            </div>
            <div class="campo">
                <label for="genero">Género *</label>
                <input type="text" id="genero" name="genero" required maxlength="80" value="<?= esc($datos['genero']) ?>">
            </div>
            <div class="campo">
                <label for="clasificacion">Clasificación *</label>
                <select id="clasificacion" name="clasificacion">
                    <?php foreach (['TE','TE+7','MA+14','MA+18'] as $c): ?>
                        <option value="<?= $c ?>" <?= $datos['clasificacion']===$c?'selected':'' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="duracion_min">Duración (minutos) *</label>
                <input type="number" id="duracion_min" name="duracion_min" min="1" max="999" value="<?= esc($datos['duracion_min']) ?>">
            </div>
            <div class="campo">
                <label for="fecha_estreno">Fecha de estreno</label>
                <input type="date" id="fecha_estreno" name="fecha_estreno" value="<?= esc($datos['fecha_estreno'] ?? '') ?>">
            </div>
            <div class="campo form-full">
                <label for="sinopsis">Sinopsis</label>
                <textarea id="sinopsis" name="sinopsis" rows="4" maxlength="2000"><?= esc($datos['sinopsis'] ?? '') ?></textarea>
            </div>
            <div class="campo">
                <?php if ($es_edicion): ?>
                    <label>Póster actual</label>
                    <?php if (!empty($datos['imagen'])): ?>
                        <img src="../<?= esc($datos['imagen']) ?>" alt="Póster" style="max-height:100px;border-radius:4px;display:block;margin-bottom:.5rem;">
                    <?php else: ?>
                        <p style="color:var(--texto-muy-suave);font-size:.85rem;">Sin imagen</p>
                    <?php endif; ?>
                    <label for="imagen">Reemplazar imagen (JPG/PNG/WebP, máx 2 MB)</label>
                <?php else: ?>
                    <label for="imagen">Póster (JPG/PNG/WebP, máx 2 MB)</label>
                <?php endif; ?>
                <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="campo" style="display:flex;align-items:center;gap:.6rem;padding-top:1.5rem;">
                <input type="checkbox" id="activa" name="activa" value="1" <?= $datos['activa'] ? 'checked' : '' ?> style="width:auto;">
                <label for="activa" style="margin:0;">Película activa</label>
            </div>
        </div>
        <div style="margin-top:1.5rem;display:flex;gap:1rem;">
            <button type="submit" class="btn btn-primario"><?= $es_edicion ? '💾 Guardar cambios' : '💾 Guardar película' ?></button>
            <a href="peliculas.php" class="btn btn-secundario">Cancelar</a>
        </div>
    </div>
    </form>

    <?php
    render_admin_footer();
    exit;
}

// modo lista
try {
    $peliculas = $pdo->query("
        SELECT p.*, COUNT(DISTINCT f.id) AS total_funciones
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
render_admin_header();
?>

<div class="admin-topbar">
    <h1>🎬 Películas</h1>
    <a href="peliculas.php?form=1" class="btn btn-primario">+ Nueva película</a>
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
                    <a href="peliculas.php?id=<?= $p['id'] ?>" class="btn-accion btn-editar">✏️ Editar</a>
                    <form method="POST" style="display:inline;">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="toggle_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn-accion btn-activar">
                            <?= $p['activa'] ? '🚫 Desactivar' : '✅ Activar' ?>
                        </button>
                    </form>
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

<?php render_admin_footer(); ?>
