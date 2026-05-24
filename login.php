<?php
/**
 * ============================================================
 *  CineFlow - Plataforma de Gestión y Venta de Entradas
 * ============================================================
 *  Archivo   : login.php
 *  Propósito : Autenticación de usuarios.
 *
 *  FLUJO GET  → Muestra el formulario de login.
 *  FLUJO POST → Valida CSRF → busca usuario por email →
 *               verifica password con bcrypt →
 *               abre sesión segura → redirige.
 *
 *  PROTECCIONES:
 *    · Token CSRF en el formulario.
 *    · password_verify() con hash bcrypt (nunca texto plano).
 *    · session_regenerate_id() tras login exitoso.
 *    · Mensaje de error genérico (no revela si el email existe).
 *    · Redirección post-login a URL guardada en sesión.
 *
 *  Depende de: config.php, includes/funciones.php,
 *              includes/header.php, includes/footer.php
 *  Autores   : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/funciones.php';

// Si ya está autenticado, no tiene sentido mostrar el login
if (estaAutenticado()) {
    redirigir('cartelera.php');
}

// ═════════════════════════════════════════════════════════════
//  PROCESAMIENTO POST
// ═════════════════════════════════════════════════════════════
$error_login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error_login = procesarLogin();

    // Si no hay error, procesarLogin() ya redirigió → nunca llegamos aquí.
    // Si llegamos aquí, hubo un fallo y mostramos el formulario con el error.
}

/**
 * Valida credenciales y abre la sesión del usuario.
 *
 * @return string  Mensaje de error (vacío si el login fue exitoso,
 *                 pero en ese caso ya habremos redirigido).
 */
function procesarLogin(): string
{
    // ── 1. Verificar token CSRF ───────────────────────────────
    $token = $_POST['csrf_token'] ?? '';
    verificarTokenCsrf($token);   // Muere con 403 si el token es inválido

    // ── 2. Leer y sanear inputs ───────────────────────────────
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        return 'Por favor, completa todos los campos.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'El formato del correo electrónico no es válido.';
    }

    // ── 3. Buscar usuario en la BD ────────────────────────────
    $pdo = obtenerConexion();

    try {
        $stmt = $pdo->prepare("
            SELECT id, nombre, email, password_hash, rol, activo
            FROM   usuarios
            WHERE  email = :email
            LIMIT  1
        ");
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

    } catch (PDOException $e) {
        registrarError('login - consulta BD', $e->getMessage());
        return 'Error interno. Por favor, inténtalo de nuevo más tarde.';
    }

    // ── 4. Verificar contraseña ───────────────────────────────
    // Usamos un mensaje genérico para no revelar si el email existe.
    $credenciales_validas = $usuario &&
                            (int)$usuario['activo'] === 1 &&
                            password_verify($password, $usuario['password_hash']);

    if (!$credenciales_validas) {
        // Pequeño retardo artificial: dificulta ataques de fuerza bruta
        // registrando el tiempo mínimo de la verificación bcrypt.
        if (!$usuario) {
            // Si no existe el usuario, simulamos la verificación para
            // que el tiempo de respuesta sea constante (anti-timing attack).
            password_verify($password, '$2y$12$invalido.hash.de.relleno.para.timing');
        }
        registrarError(
            'login - credenciales',
            "Intento fallido para el email: {$email}. IP: " . ($_SERVER['REMOTE_ADDR'] ?? '?')
        );
        return 'Correo o contraseña incorrectos.';
    }

    // ── 5. Abrir sesión segura ────────────────────────────────
    // Regenerar el ID de sesión previene Session Fixation.
    session_regenerate_id(true);

    $_SESSION['usuario_id'] = (int)$usuario['id'];
    $_SESSION['nombre']     = $usuario['nombre'];
    $_SESSION['email']      = $usuario['email'];
    $_SESSION['rol']        = $usuario['rol'];

    // Renovar el token CSRF para la nueva sesión autenticada
    unset($_SESSION['csrf_token']);
    generarTokenCsrf();

    // ── 6. Redirigir ──────────────────────────────────────────
    // Si venía de una página protegida, volver a ella.
    $destino = $_SESSION['redirigir_a'] ?? 'cartelera.php';
    unset($_SESSION['redirigir_a']);

    // Validar que el destino sea una URL relativa del mismo sitio
    // (evitar Open Redirect a dominios externos).
    if (!preg_match('/^[a-zA-Z0-9_\-\/\.\?=&%]+$/', $destino)) {
        $destino = 'cartelera.php';
    }

    redirigir($destino);
    return '';  // Nunca se ejecuta, pero satisface el tipo de retorno
}


// ═════════════════════════════════════════════════════════════
//  RENDERIZADO HTML
// ═════════════════════════════════════════════════════════════
$titulo_pagina = 'Iniciar sesión';
$nav_activo    = '';
require_once __DIR__ . '/includes/header.php';
?>

<main class="contenedor" style="
    display:         flex;
    align-items:     center;
    justify-content: center;
    min-height:      calc(100vh - 130px);
    padding:         40px 20px;
">

    <div class="form-card">

        <!-- Logo compacto dentro de la card -->
        <p style="font-size:2rem;text-align:center;margin-bottom:8px;">🎬</p>
        <h1 style="text-align:center;">Iniciar sesión</h1>
        <p class="subtitulo" style="text-align:center;">
            Accede para comprar y gestionar tus entradas.
        </p>

        <!-- Mensaje flash (ej: "Has cerrado sesión") -->
        <?= renderFlash() ?>

        <!-- Error de login -->
        <?php if ($error_login !== ''): ?>
            <div class="alerta alerta-error" role="alert">
                <?= esc($error_login) ?>
            </div>
        <?php endif; ?>

        <!-- ── Formulario ───────────────────────────────── -->
        <form method="POST" action="login.php" novalidate>

            <?= campoCsrf() ?>

            <div class="form-group">
                <label for="email">
                    Correo electrónico <span class="requerido">*</span>
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    value="<?= esc($_POST['email'] ?? '') ?>"
                    placeholder="tucorreo@ejemplo.com"
                    autocomplete="email"
                    required
                    autofocus>
            </div>

            <div class="form-group">
                <label for="password">
                    Contraseña <span class="requerido">*</span>
                </label>
                <div style="position:relative;">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                        style="padding-right:44px;">
                    <!-- Botón mostrar/ocultar contraseña -->
                    <button
                        type="button"
                        onclick="togglePassword()"
                        aria-label="Mostrar u ocultar contraseña"
                        style="
                            position:absolute; right:10px; top:50%;
                            transform:translateY(-50%);
                            background:none; border:none;
                            color:var(--texto-suave); cursor:pointer;
                            font-size:1.1rem; padding:4px;
                        ">
                        👁
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primario btn-bloque btn-lg" style="margin-top:8px;">
                Entrar
            </button>

        </form>
        <!-- /form -->

        <!-- Datos de prueba visibles solo en desarrollo -->
        <?php if (defined('ENTORNO') && ENTORNO === 'desarrollo'): ?>
            <div style="
                margin-top:24px; padding:12px; border-radius:var(--radio);
                background:rgba(52,152,219,.1); border:1px solid var(--azul);
                font-size:.8rem; color:var(--texto-suave);
            ">
                <strong style="color:var(--azul);">🔧 Modo desarrollo — cuentas de prueba:</strong><br>
                Admin: <code>admin@cineflow.cl</code> / <code>Admin123!</code><br>
                Cliente: <code>cristobal@test.cl</code> / <code>Test123!</code>
            </div>
        <?php endif; ?>

        <p style="text-align:center;margin-top:20px;font-size:.85rem;color:var(--texto-suave);">
            ¿Aún no tienes cuenta?
            <a href="registro.php">Regístrate gratis</a>
        </p>

    </div>

</main>

<script>
/**
 * Alterna la visibilidad del campo contraseña.
 */
function togglePassword() {
    const campo = document.getElementById('password');
    campo.type  = campo.type === 'password' ? 'text' : 'password';
}

// Deshabilitar el botón de submit mientras se procesa
document.querySelector('form').addEventListener('submit', function () {
    const btn = this.querySelector('[type="submit"]');
    btn.disabled    = true;
    btn.textContent = 'Verificando...';
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
