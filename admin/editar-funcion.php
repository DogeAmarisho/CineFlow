<?php
/**
 * CineFlow - Admin Editar Función
 * Archivo: admin/editar-funcion.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';

if (!esAdmin()) { header('Location: login.php'); exit; }

$id  = (int)($_GET['id'] ?? 0);
$pdo = obtenerConexion();

if ($id < 1) { flashMensaje('error','ID inválido.'); header('Location: funciones.php'); exit; }

// Load función
try {
    $stmt = $pdo->prepare("SELECT * FROM funciones WHERE id=:id LIMIT 1");
    $stmt->execute([':id'=>$id]);
    $datos = $stmt->fetch();
} catch (PDOException $e) {
    registrarError('admin-editar-funcion-load', $e->getMessage());
    $datos = null;
}
if (!$datos) { flashMensaje('error','Función no encontrada.'); header('Location: funciones.php'); exit; }

// Format fecha_hora for datetime-local input
$datos['fecha_hora_input'] = date('Y-m-d\TH:i', strtotime($datos['fecha_hora']));

// Load selects
try {
    $peliculas_select = $pdo->query("SELECT id, titulo FROM peliculas ORDER BY titulo")->fetchAll();
    $salas_select     = $pdo->query("SELECT id, nombre, tipo, capacidad FROM salas ORDER BY nombre")->fetchAll();
} catch (PDOException $e) {
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
    if ($datos['precio'] <= 0)      $errores[] = 'El precio debe ser mayor a 0.';

    // Check sala overlap (excluding self)
    if (empty($errores)) {
        try {
            $overlap = $pdo->prepare("SELECT COUNT(*) FROM funciones WHERE sala_id=:sala AND fecha_hora=:fh AND id != :id");
            $overlap->execute([':sala'=>$datos['sala_id'], ':fh'=>$datos['fecha_hora'], ':id'=>$id]);
            if ($overlap->fetchColumn() > 0) {
                $errores[] = 'Ya existe una función programada en esa sala a esa hora.';
            }
        } catch (PDOException $e) {
            registrarError('admin-editar-funcion-overlap', $e->getMessage());
        }
    }

    if (empty($errores)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE funciones SET
                    pelicula_id=:pid, sala_id=:sid, fecha_hora=:fh,
                    precio=:precio, idioma=:idioma, activa=:activa
                WHERE id=:id
            ");
            $stmt->execute([
                ':pid'    => $datos['pelicula_id'],
                ':sid'    => $datos['sala_id'],
                ':fh'     => $datos['fecha_hora'],
                ':precio' => $datos['precio'],
                ':idioma' => $datos['idioma'],
                ':activa' => $datos['activa'],
                ':id'     => $id,
            ]);
            flashMensaje('exito', '¡Función actualizada correctamente!');
            header('Location: funciones.php'); exit;
        } catch (PDOException $e) {
            registrarError('admin-editar-funcion', $e->getMessage());
            $errores[] = 'Error al actualizar: ' . $e->getMessage();
        }
    }
}

$titulo_pagina    = 'Editar Función';
$admin_nav_activo = 'funciones';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-topbar">
    <h1>✏️ Editar Función #<?= $id ?></h1>
    <a href="funciones.php" class="btn btn-secundario">← Volver</a>
</div>

<?php if (!empty($errores)): ?>
<div class="alerta alerta-error">
    <ul style="margin:.3rem 0 0 1.2rem;">
        <?php foreach ($errores as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" novalidate>
<?= campoCsrf() ?>
<div class="admin-form">
    <div class="form-grid">
        <div class="campo form-full">
            <label for="pelicula_id">Película *</label>
            <select id="pelicula_id" name="pelicula_id" required>
                <?php foreach ($peliculas_select as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $datos['pelicula_id']==$p['id']?'selected':''?>><?= esc($p['titulo']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="sala_id">Sala *</label>
            <select id="sala_id" name="sala_id" required>
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
            <input type="datetime-local" id="fecha_hora" name="fecha_hora" required value="<?= esc($datos['fecha_hora_input']) ?>">
        </div>
        <div class="campo">
            <label for="precio">Precio (CLP) *</label>
            <input type="number" id="precio" name="precio" min="1" step="100" required value="<?= esc($datos['precio']) ?>">
        </div>
        <div class="campo" style="display:flex;align-items:center;gap:.6rem;padding-top:1.5rem;">
            <input type="checkbox" id="activa" name="activa" value="1" <?= $datos['activa'] ? 'checked' : '' ?> style="width:auto;">
            <label for="activa" style="margin:0;">Función activa</label>
        </div>
    </div>
    <div style="margin-top:1.5rem;display:flex;gap:1rem;">
        <button type="submit" class="btn btn-primario">💾 Guardar cambios</button>
        <a href="funciones.php" class="btn btn-secundario">Cancelar</a>
    </div>
</div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
