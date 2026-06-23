<?php
/**
 * Lista de funciones + su formulario de crear/editar en un solo archivo.
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
        $pdo->prepare("UPDATE funciones SET activa = 1-activa WHERE id=:id")->execute([':id'=>$tid]);
        flashMensaje('exito','Estado de función actualizado.');
    } catch (PDOException $e) {
        registrarError('admin-funciones-toggle', $e->getMessage());
        flashMensaje('error','No se pudo actualizar.');
    }
    header('Location: funciones.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    verificarTokenCsrf($_POST['csrf_token'] ?? '');
    $did = (int)$_POST['eliminar_id'];
    try {
        $tiene = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE funcion_id=:id AND estado IN ('confirmada','pendiente')");
        $tiene->execute([':id'=>$did]);
        if ($tiene->fetchColumn() > 0) {
            flashMensaje('error','No se puede eliminar: tiene reservas activas.');
        } else {
            $pdo->prepare("DELETE FROM funciones WHERE id=:id")->execute([':id'=>$did]);
            flashMensaje('exito','Función eliminada.');
        }
    } catch (PDOException $e) {
        registrarError('admin-funciones-delete', $e->getMessage());
        flashMensaje('error','Error al eliminar: ' . $e->getMessage());
    }
    header('Location: funciones.php'); exit;
}

// modo formulario (crear o editar)
if ($mostrar_formulario) {
    $datos = ['pelicula_id'=>'','sala_id'=>'','fecha_hora'=>'','fecha_hora_input'=>'','precio'=>'','idioma'=>'subtitulada','activa'=>1];

    if ($es_edicion) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM funciones WHERE id=:id LIMIT 1");
            $stmt->execute([':id'=>$id]);
            $fila = $stmt->fetch();
        } catch (PDOException $e) {
            registrarError('admin-funciones-form-load', $e->getMessage());
            $fila = null;
        }
        if (!$fila) { flashMensaje('error','Función no encontrada.'); header('Location: funciones.php'); exit; }
        $datos = $fila;
        $datos['fecha_hora_input'] = date('Y-m-d\TH:i', strtotime($datos['fecha_hora']));
    }

    try {
        $peliculas_select = $es_edicion
            ? $pdo->query("SELECT id, titulo FROM peliculas ORDER BY titulo")->fetchAll()
            : $pdo->query("SELECT id, titulo FROM peliculas WHERE activa=1 ORDER BY titulo")->fetchAll();
        $salas_select = $es_edicion
            ? $pdo->query("SELECT id, nombre, tipo, capacidad FROM salas ORDER BY nombre")->fetchAll()
            : $pdo->query("SELECT id, nombre, tipo, capacidad FROM salas WHERE activa=1 ORDER BY nombre")->fetchAll();
    } catch (PDOException $e) {
        registrarError('admin-funciones-form-selects', $e->getMessage());
        $peliculas_select = $salas_select = [];
    }

    $errores = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verificarTokenCsrf($_POST['csrf_token'] ?? '');

        $datos['pelicula_id'] = (int)($_POST['pelicula_id'] ?? 0);
        $datos['sala_id']     = (int)($_POST['sala_id']     ?? 0);
        $datos['fecha_hora']  = trim($_POST['fecha_hora']   ?? '');
        $datos['fecha_hora_input'] = $datos['fecha_hora'];
        $datos['precio']      = (float)str_replace(',','.', $_POST['precio'] ?? '0');
        $datos['idioma']      = $_POST['idioma'] ?? 'subtitulada';
        $datos['activa']      = isset($_POST['activa']) ? 1 : 0;

        if ($datos['pelicula_id'] < 1)  $errores[] = 'Selecciona una película.';
        if ($datos['sala_id'] < 1)      $errores[] = 'Selecciona una sala.';
        if ($datos['fecha_hora'] === '') $errores[] = 'La fecha y hora son obligatorias.';
        elseif (!$es_edicion && strtotime($datos['fecha_hora']) < time()) $errores[] = 'La fecha/hora debe ser en el futuro.';
        if ($datos['precio'] <= 0)      $errores[] = 'El precio debe ser mayor a 0.';
        if (!in_array($datos['idioma'], ['subtitulada','doblada','original'])) $errores[] = 'Idioma inválido.';

        if (empty($errores)) {
            try {
                $sql = "SELECT COUNT(*) FROM funciones WHERE sala_id=:sala AND fecha_hora=:fh";
                $params = [':sala'=>$datos['sala_id'], ':fh'=>$datos['fecha_hora']];
                if ($es_edicion) { $sql .= " AND id != :id"; $params[':id'] = $id; }
                $overlap = $pdo->prepare($sql);
                $overlap->execute($params);
                if ($overlap->fetchColumn() > 0) {
                    $errores[] = 'Ya existe una función programada en esa sala a esa hora.';
                }
            } catch (PDOException $e) {
                registrarError('admin-funciones-form-overlap', $e->getMessage());
            }
        }

        if (empty($errores)) {
            try {
                $params = [
                    ':pid'    => $datos['pelicula_id'],
                    ':sid'    => $datos['sala_id'],
                    ':fh'     => $datos['fecha_hora'],
                    ':precio' => $datos['precio'],
                    ':idioma' => $datos['idioma'],
                    ':activa' => $datos['activa'],
                ];
                if ($es_edicion) {
                    $params[':id'] = $id;
                    $stmt = $pdo->prepare("
                        UPDATE funciones SET pelicula_id=:pid, sala_id=:sid, fecha_hora=:fh,
                            precio=:precio, idioma=:idioma, activa=:activa
                        WHERE id=:id
                    ");
                    $mensaje = '¡Función actualizada correctamente!';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO funciones (pelicula_id, sala_id, fecha_hora, precio, idioma, activa)
                        VALUES (:pid, :sid, :fh, :precio, :idioma, :activa)
                    ");
                    $mensaje = '¡Función creada correctamente!';
                }
                $stmt->execute($params);
                flashMensaje('exito', $mensaje);
                header('Location: funciones.php'); exit;
            } catch (PDOException $e) {
                registrarError('admin-funciones-form-guardar', $e->getMessage());
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    $errores[] = 'Ya existe una función en esa sala a esa hora exacta.';
                } else {
                    $errores[] = 'Error al guardar: ' . $e->getMessage();
                }
            }
        }
    }

    $titulo_pagina    = $es_edicion ? 'Editar Función' : 'Nueva Función';
    $admin_nav_activo = 'funciones';
    render_admin_header();
    ?>

    <div class="admin-topbar">
        <h1><?= $es_edicion ? '✏️ Editar Función #' . $id : '🗓️ Nueva Función' ?></h1>
        <a href="funciones.php" class="btn btn-secundario">← Volver</a>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="alerta alerta-error">
        <ul style="margin:.3rem 0 0 1.2rem;">
            <?php foreach ($errores as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="funciones.php<?= $es_edicion ? '?id=' . $id : '?form=1' ?>" novalidate>
    <?= campoCsrf() ?>
    <div class="admin-form">
        <div class="form-grid">
            <div class="campo form-full">
                <label for="pelicula_id">Película *</label>
                <select id="pelicula_id" name="pelicula_id" required>
                    <?php if (!$es_edicion): ?><option value="">— Seleccionar —</option><?php endif; ?>
                    <?php foreach ($peliculas_select as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $datos['pelicula_id']==$p['id']?'selected':''?>><?= esc($p['titulo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="sala_id">Sala *</label>
                <select id="sala_id" name="sala_id" required>
                    <?php if (!$es_edicion): ?><option value="">— Seleccionar —</option><?php endif; ?>
                    <?php foreach ($salas_select as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $datos['sala_id']==$s['id']?'selected':''?>>
                            <?= esc($s['nombre']) ?> (<?= esc($s['tipo']) ?>, <?= $s['capacidad'] ?> asientos)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="idioma">Idioma *</label>
                <select id="idioma" name="idioma">
                    <?php foreach (['subtitulada'=>'Subtitulada','doblada'=>'Doblada','original'=>'Original'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $datos['idioma']===$v?'selected':''?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="fecha_hora">Fecha y hora *</label>
                <input type="datetime-local" id="fecha_hora" name="fecha_hora" required
                       value="<?= esc($datos['fecha_hora_input']) ?>"
                       <?= $es_edicion ? '' : 'min="' . date('Y-m-d\TH:i') . '"' ?>>
            </div>
            <div class="campo">
                <label for="precio">Precio (CLP) *</label>
                <input type="number" id="precio" name="precio" min="1" step="100" required value="<?= esc($datos['precio'] > 0 ? $datos['precio'] : '') ?>">
            </div>
            <div class="campo" style="display:flex;align-items:center;gap:.6rem;padding-top:1.5rem;">
                <input type="checkbox" id="activa" name="activa" value="1" <?= $datos['activa'] ? 'checked' : '' ?> style="width:auto;">
                <label for="activa" style="margin:0;">Función activa</label>
            </div>
        </div>
        <div style="margin-top:1.5rem;display:flex;gap:1rem;">
            <button type="submit" class="btn btn-primario"><?= $es_edicion ? '💾 Guardar cambios' : '💾 Crear función' ?></button>
            <a href="funciones.php" class="btn btn-secundario">Cancelar</a>
        </div>
    </div>
    </form>

    <?php
    render_admin_footer();
    exit;
}

// modo lista
try {
    $funciones_list = $pdo->query("
        SELECT f.*, p.titulo AS pelicula, p.imagen AS poster, s.nombre AS sala, s.tipo AS tipo_sala, s.capacidad
        FROM   funciones f
        JOIN   peliculas p ON p.id = f.pelicula_id
        JOIN   salas     s ON s.id = f.sala_id
        ORDER  BY f.fecha_hora DESC
        LIMIT  100
    ")->fetchAll();
} catch (PDOException $e) {
    registrarError('admin-funciones', $e->getMessage());
    $funciones_list = [];
}

$titulo_pagina    = 'Gestión de Funciones';
$admin_nav_activo = 'funciones';
render_admin_header();
?>

<div class="admin-topbar">
    <h1>🗓️ Funciones</h1>
    <a href="funciones.php?form=1" class="btn btn-primario">+ Nueva función</a>
</div>

<?= renderFlash() ?>

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
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($funciones_list as $f): ?>
        <?php $pasada = strtotime($f['fecha_hora']) < time(); ?>
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
            <td><span class="badge <?= $f['activa'] ? 'badge-verde' : 'badge-gris' ?>"><?= $f['activa'] ? 'Activa' : 'Inactiva' ?></span></td>
            <td>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                    <a href="funciones.php?id=<?= $f['id'] ?>" class="btn-accion btn-editar">✏️ Editar</a>
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

<?php render_admin_footer(); ?>
