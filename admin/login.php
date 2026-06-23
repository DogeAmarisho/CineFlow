<?php
/**
 * Login del admin. El logout esta aca mismo (con ?salir=1).
 */
require_once __DIR__ . '/../config.php';

if (isset($_GET['salir'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

// si ya esta logueado no tiene sentido mostrarle el login otra vez
if (esAdmin()) {
    header('Location: peliculas.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCsrf($_POST['csrf_token'] ?? '');

    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Por favor completa todos los campos.';
    } else {
        $pdo = obtenerConexion();
        try {
            $stmt = $pdo->prepare("
                SELECT id, nombre, email, password_hash, rol
                FROM   usuarios
                WHERE  email  = :email
                  AND  activo = 1
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($password, $usuario['password_hash'])) {
                session_regenerate_id(true); // cambia el id de sesion al loguearse

                $_SESSION['usuario_id'] = (int)$usuario['id'];
                $_SESSION['nombre']     = $usuario['nombre'];
                $_SESSION['email']      = $usuario['email'];
                $_SESSION['rol']        = $usuario['rol'];

                flashMensaje('exito', '¡Bienvenido, ' . $usuario['nombre'] . '!');
                header('Location: peliculas.php');
                exit;
            } else {
                $error = 'Email o contraseña incorrectos.';
                sleep(1); // para no hacer tan facil probar contraseñas a lo loco
            }
        } catch (PDOException $e) {
            registrarError('admin-login', $e->getMessage());
            $error = 'Error del servidor. Inténtalo más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Admin — CineFlow</title>
    <link rel="stylesheet" href="../assets/css/estilo.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚙️</text></svg>">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-box { background: var(--superficie); border: 1px solid var(--borde); border-radius: var(--radio-lg); padding: 2.5rem; width: 100%; max-width: 420px; }
        .login-box h1 { font-size: 1.5rem; margin-bottom: .25rem; }
        .login-box .subtitulo { color: var(--texto-suave); font-size: .9rem; margin-bottom: 2rem; }
    </style>
</head>
<body>
<div class="login-box">
    <h1>Cine<span style="color:var(--primario)">Flow</span> <small style="color:var(--texto-suave);font-size:.5em;">Admin</small></h1>
    <p class="subtitulo">Acceso exclusivo para administradores del cine.</p>

    <?php if ($error !== ''): ?>
        <div class="alerta alerta-error" role="alert"><?= esc($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
        <?= campoCsrf() ?>
        <div class="campo">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" required autocomplete="username"
                   value="<?= esc($_POST['email'] ?? '') ?>" autofocus>
        </div>
        <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primario" style="width:100%;margin-top:.5rem;">
            Ingresar al panel
        </button>
    </form>

    <p style="text-align:center;margin-top:1.5rem;font-size:.8rem;color:var(--texto-muy-suave);">
        <a href="../index.php" style="color:var(--texto-suave);">← Volver al sitio</a>
    </p>
</div>
</body>
</html>
