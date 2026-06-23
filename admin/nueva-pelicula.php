<?php
/**
 * CineFlow - Admin Nueva Película
 * Archivo: admin/nueva-pelicula.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/funciones.php';

if (!esAdmin()) { header('Location: login.php'); exit; }

$errores = [];
$datos   = ['titulo'=>'','genero'=>'','sinopsis'=>'','clasificacion'=>'TE','duracion_min'=>'','imagen'=>'','fecha_estreno'=>'','activa'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCsrf($_POST['csrf_token'] ?? '');

    $datos['titulo']       = trim($_POST['titulo']       ?? '');
    $datos['genero']       = trim($_POST['genero']       ?? '');
    $datos['sinopsis']     = trim($_POST['sinopsis']     ?? '');
    $datos['clasificacion']= $_POST['clasificacion']    ?? 'TE';
    $datos['duracion_min'] = (int)($_POST['duracion_min'] ?? 0);
    $datos['fecha_estreno']= trim($_POST['fecha_estreno'] ?? '');
    $datos['activa']       = isset($_POST['activa']) ? 1 : 0;

    if ($datos['titulo'] === '')              $errores[] = 'El título es obligatorio.';
    if ($datos['genero'] === '')              $errores[] = 'El género es obligatorio.';
    if (!in_array($datos['clasificacion'], ['TE','TE+7','MA+14','MA+18'])) $errores[] = 'Clasificación inválida.';
    if ($datos['duracion_min'] < 1)           $errores[] = 'La duración debe ser mayor a 0.';

    // Handle image upload
    $imagen_path = '';
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
                $imagen_path = 'uploads/peliculas/' . $nombre;
            } else {
                $errores[] = 'No se pudo guardar la imagen. Verifica permisos.';
            }
        }
    }

    if (empty($errores)) {
        $pdo = obtenerConexion();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO peliculas (titulo, genero, sinopsis, clasificacion, duracion_min, imagen, fecha_estreno, activa)
                VALUES (:titulo, :genero, :sinopsis, :clasificacion, :duracion_min, :imagen, :fecha_estreno, :activa)
            ");
            $stmt->execute([
                ':titulo'        => $datos['titulo'],
                ':genero'        => $datos['genero'],
                ':sinopsis'      => $datos['sinopsis'] ?: null,
                ':clasificacion' => $datos['clasificacion'],
                ':duracion_min'  => $datos['duracion_min'] ?: null,
                ':imagen'        => $imagen_path ?: null,
                ':fecha_estreno' => $datos['fecha_estreno'] ?: null,
                ':activa'        => $datos['activa'],
            ]);
            flashMensaje('exito', '¡Película «' . $datos['titulo'] . '» creada correctamente!');
            header('Location: peliculas.php'); exit;
        } catch (PDOException $e) {
            registrarError('admin-nueva-pelicula', $e->getMessage());
            $errores[] = 'Error al guardar en la base de datos.';
        }
    }
}

$titulo_pagina    = 'Nueva Película';
$admin_nav_activo = 'peliculas';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-topbar">
    <h1>🎬 Nueva Película</h1>
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

<form method="POST" enctype="multipart/form-data" novalidate>
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
            <input type="date" id="fecha_estreno" name="fecha_estreno" value="<?= esc($datos['fecha_estreno']) ?>">
        </div>
        <div class="campo form-full">
            <label for="sinopsis">Sinopsis</label>
            <textarea id="sinopsis" name="sinopsis" rows="4" maxlength="2000"><?= esc($datos['sinopsis']) ?></textarea>
        </div>
        <div class="campo">
            <label for="imagen">Póster (JPG/PNG/WebP, máx 2 MB)</label>
            <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="campo" style="display:flex;align-items:center;gap:.6rem;padding-top:1.5rem;">
            <input type="checkbox" id="activa" name="activa" value="1" <?= $datos['activa'] ? 'checked' : '' ?> style="width:auto;">
            <label for="activa" style="margin:0;">Película activa</label>
        </div>
    </div>
    <div style="margin-top:1.5rem;display:flex;gap:1rem;">
        <button type="submit" class="btn btn-primario">💾 Guardar película</button>
        <a href="peliculas.php" class="btn btn-secundario">Cancelar</a>
    </div>
</div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
