<?php
/**
 * ============================================================
 *  CineFlow - Plataforma de Gestión y Venta de Entradas
 * ============================================================
 *  Archivo   : registro.php
 *  Propósito : Registro de nuevos usuarios.
 *
 *  FLUJO GET  → Muestra el formulario de registro.
 *  FLUJO POST → Valida CSRF → valida campos → verifica email
 *               único → hashea password con bcrypt →
 *               inserta en BD → abre sesión → redirige.
 *
 *  VALIDACIONES:
 *    · Nombre:    requerido, 2–100 caracteres.
 *    · Email:     formato válido, no registrado previamente.
 *    · Password:  mínimo 8 caracteres, al menos 1 mayúscula,
 *                 1 número.
 *    · Confirmar: coincide con password.
 *
 *  PROTECCIONES:
 *    · Token CSRF.
 *    · password_hash() con PASSWORD_BCRYPT.
 *    · session_regenerate_id() tras registro exitoso.
 *    · Mensaje genérico si el email ya existe (no lo revela).
 *
 *  Depende de: config.php, includes/funciones.php,
 *              includes/header.php, includes/footer.php
 *  Autores   : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/funciones.php';

// Si ya está autenticado, redirigir
if (estaAutenticado()) {
    redirigir('cartelera.php');
}

// ═════════════════════════════════════════════════════════════
//  PROCESAMIENTO POST
// ═════════════════════════════════════════════════════════════
$errores = [];           // Array de errores de validación
$valores = [];           // Para repoblar el formulario si hay errores

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$errores, $valores] = procesarRegistro();

    // Si no hay errores, procesarRegistro() ya redirigió.
}

/**
 * Procesa el formulario de registro.
 * Devuelve [errores[], valores[]] para repoblar el formulario.
 * Si el registro fue exitoso, redirige antes de retornar.
 *
 * @return array{0: string[], 1: array<string,string>}
 */
function procesarRegistro(): array
{
    // ── 1. Verificar CSRF ─────────────────────────────────────
    verificarTokenCsrf($_POST['csrf_token'] ?? '');

    // ── 2. Leer y sanear inputs ───────────────────────────────
    $nombre   = trim($_POST['nombre']            ?? '');
    $email    = trim(strtolower($_POST['email']  ?? ''));
    $password = $_POST['password']               ?? '';
    $confirmar= $_POST['confirmar_password']     ?? '';

    $valores = compact('nombre', 'email');   // No devolvemos passwords
    $errores = [];

    // ── 3. Validar nombre ─────────────────────────────────────
    if ($nombre === '') {
        $errores['nombre'] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($nombre) < 2) {
        $errores['nombre'] = 'El nombre debe tener al menos 2 caracteres.';
    } elseif (mb_strlen($nombre) > 100) {
        $errores['nombre'] = 'El nombre no puede superar los 100 caracteres.';
    }

    // ── 4. Validar email ──────────────────────────────────────
    if ($email === '') {
        $errores['email'] = 'El correo electrónico es obligatorio.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = 'El formato del correo no es válido.';
    }

    // ── 5. Validar contraseña ─────────────────────────────────
    if ($password === '') {
        $errores['password'] = 'La contraseña es obligatoria.';
    } elseif (strlen($password) < 8) {
        $errores['password'] = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errores['password'] = 'La contraseña debe incluir al menos una letra mayúscula.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errores['password'] = 'La contraseña debe incluir al menos un número.';
    }

    // ── 6. Verificar confirmación ─────────────────────────────
    if (empty($errores['password']) && $password !== $confirmar) {
        $errores['confirmar_password'] = 'Las contraseñas no coinciden.';
    }

    // ── 7. Si hay errores de formato, no consultar la BD ──────
    if (!empty($errores)) {
        return [$errores, $valores];
    }

    // ── 8. Verificar que el email no esté registrado ──────────
    $pdo = obtenerConexion();

    try {
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
        $stmtCheck->execute([':email' => $email]);

        if ($stmtCheck->fetch()) {
            // Mensaje genérico: no confirmamos si el email existe
            $errores['email'] = 'Este correo ya está registrado. ¿Quieres iniciar sesión?';
            return [$errores, $valores];
        }

        // ── 9. Hashear contraseña e insertar ──────────────────
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmtInsert = $pdo->prepare("
            INSERT INTO usuarios (nombre, email, password_hash, rol)
            VALUES (:nombre, :email, :hash, 'cliente')
        ");
        $stmtInsert->execute([
            ':nombre' => $nombre,
            ':email'  => $email,
            ':hash'   => $hash,
        ]);

        $nuevo_id = (int)$pdo->lastInsertId();

    } catch (PDOException $e) {
        registrarError('registro - insertar usuario', $e->getMessage());
        $errores['_global'] = 'Error interno al crear la cuenta. Por favor, inténtalo de nuevo.';
        return [$errores, $valores];
    }

    // ── 10. Abrir sesión para el nuevo usuario ────────────────
    session_regenerate_id(true);

    $_SESSION['usuario_id'] = $nuevo_id;
    $_SESSION['nombre']     = $nombre;
    $_SESSION['email']      = $email;
    $_SESSION['rol']        = 'cliente';

    generarTokenCsrf();   // Generar token fresco para la nueva sesión

    flashMensaje('exito', "¡Bienvenido/a, {$nombre}! Tu cuenta fue creada correctamente.");
    redirigir('cartelera.php');

    return [[], []];   // Nunca se ejecuta
}


// ═════════════════════════════════════════════════════════════
//  RENDERIZADO
// ═════════════════════════════════════════════════════════════
$titulo_pagina = 'Crear cuenta';
$nav_activo    = '';
require_once __DIR__ . '/includes/header.php';
?>

<main class="contenedor" style="
    display:         flex;
    align-items:     flex-start;
    justify-content: center;
    padding:         48px 20px 60px;
    min-height:      calc(100vh - 130px);
">

    <div class="form-card" style="max-width:480px;">

        <p style="font-size:2rem;text-align:center;margin-bottom:8px;">🎬</p>
        <h1 style="text-align:center;">Crear cuenta</h1>
        <p class="subtitulo" style="text-align:center;">
            Regístrate gratis y empieza a reservar tus entradas.
        </p>

        <!-- Error global (BD u otro error inesperado) -->
        <?php if (!empty($errores['_global'])): ?>
            <div class="alerta alerta-error" role="alert">
                <?= esc($errores['_global']) ?>
            </div>
        <?php endif; ?>

        <!-- ── Formulario ───────────────────────────────── -->
        <form method="POST" action="registro.php" novalidate>

            <?= campoCsrf() ?>

            <!-- Nombre -->
            <div class="form-group">
                <label for="nombre">
                    Nombre completo <span class="requerido">*</span>
                </label>
                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    class="form-control <?= isset($errores['nombre']) ? 'campo-invalido' : '' ?>"
                    value="<?= esc($valores['nombre'] ?? '') ?>"
                    placeholder="Ej: María González"
                    autocomplete="name"
                    required
                    autofocus>
                <?php if (isset($errores['nombre'])): ?>
                    <span class="campo-error" role="alert"><?= esc($errores['nombre']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email">
                    Correo electrónico <span class="requerido">*</span>
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control <?= isset($errores['email']) ? 'campo-invalido' : '' ?>"
                    value="<?= esc($valores['email'] ?? '') ?>"
                    placeholder="tucorreo@ejemplo.com"
                    autocomplete="email"
                    required>
                <?php if (isset($errores['email'])): ?>
                    <span class="campo-error" role="alert">
                        <?= esc($errores['email']) ?>
                        <?php if (str_contains($errores['email'], 'iniciar sesión')): ?>
                            <a href="login.php?email=<?= urlencode($valores['email'] ?? '') ?>">→ Ir al login</a>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Contraseña -->
            <div class="form-group">
                <label for="password">
                    Contraseña <span class="requerido">*</span>
                </label>
                <div style="position:relative;">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control <?= isset($errores['password']) ? 'campo-invalido' : '' ?>"
                        placeholder="Mínimo 8 caracteres"
                        autocomplete="new-password"
                        required
                        style="padding-right:44px;"
                        oninput="evaluarPassword(this.value)">
                    <button
                        type="button"
                        onclick="togglePassword('password')"
                        aria-label="Mostrar u ocultar contraseña"
                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                               background:none;border:none;color:var(--texto-suave);cursor:pointer;font-size:1.1rem;">
                        👁
                    </button>
                </div>
                <?php if (isset($errores['password'])): ?>
                    <span class="campo-error" role="alert"><?= esc($errores['password']) ?></span>
                <?php endif; ?>
                <!-- Indicador de fortaleza -->
                <div id="fuerza-wrap" style="margin-top:6px;display:none;">
                    <div style="height:4px;border-radius:2px;background:var(--borde);overflow:hidden;">
                        <div id="barra-fuerza" style="height:100%;width:0;transition:width .3s,background .3s;border-radius:2px;"></div>
                    </div>
                    <small id="texto-fuerza" style="font-size:.75rem;color:var(--texto-suave);"></small>
                </div>
            </div>

            <!-- Confirmar contraseña -->
            <div class="form-group">
                <label for="confirmar_password">
                    Confirmar contraseña <span class="requerido">*</span>
                </label>
                <div style="position:relative;">
                    <input
                        type="password"
                        id="confirmar_password"
                        name="confirmar_password"
                        class="form-control <?= isset($errores['confirmar_password']) ? 'campo-invalido' : '' ?>"
                        placeholder="Repite tu contraseña"
                        autocomplete="new-password"
                        required
                        style="padding-right:44px;">
                    <button
                        type="button"
                        onclick="togglePassword('confirmar_password')"
                        aria-label="Mostrar u ocultar confirmación"
                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                               background:none;border:none;color:var(--texto-suave);cursor:pointer;font-size:1.1rem;">
                        👁
                    </button>
                </div>
                <?php if (isset($errores['confirmar_password'])): ?>
                    <span class="campo-error" role="alert"><?= esc($errores['confirmar_password']) ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primario btn-bloque btn-lg" style="margin-top:4px;">
                Crear cuenta
            </button>

        </form>

        <p style="text-align:center;margin-top:20px;font-size:.85rem;color:var(--texto-suave);">
            ¿Ya tienes cuenta?
            <a href="login.php">Iniciar sesión</a>
        </p>

        <p style="text-align:center;margin-top:10px;font-size:.75rem;color:var(--texto-suave);">
            Al registrarte aceptas nuestros
            <a href="#">Términos y condiciones</a>.
        </p>

    </div>

</main>

<style>
    /* Borde rojo en campos inválidos */
    .campo-invalido {
        border-color: var(--rojo) !important;
    }
    .campo-invalido:focus {
        box-shadow: 0 0 0 3px rgba(231, 76, 60, .2) !important;
    }
</style>

<script>
/**
 * Alterna visibilidad de un campo password.
 * @param {string} id  ID del input
 */
function togglePassword(id) {
    const c = document.getElementById(id);
    c.type  = c.type === 'password' ? 'text' : 'password';
}

/**
 * Evalúa la fortaleza de la contraseña en tiempo real
 * y actualiza la barra visual.
 * @param {string} val
 */
function evaluarPassword(val) {
    const wrap  = document.getElementById('fuerza-wrap');
    const barra = document.getElementById('barra-fuerza');
    const texto = document.getElementById('texto-fuerza');

    if (!val) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';

    let puntaje = 0;
    if (val.length >= 8)              puntaje++;
    if (val.length >= 12)             puntaje++;
    if (/[A-Z]/.test(val))            puntaje++;
    if (/[0-9]/.test(val))            puntaje++;
    if (/[^A-Za-z0-9]/.test(val))    puntaje++;

    const niveles = [
        { color: '#e74c3c', label: 'Muy débil',  pct: '20%' },
        { color: '#e67e22', label: 'Débil',       pct: '40%' },
        { color: '#f39c12', label: 'Regular',     pct: '60%' },
        { color: '#2ecc71', label: 'Fuerte',      pct: '80%' },
        { color: '#27ae60', label: 'Muy fuerte',  pct: '100%'},
    ];
    const nivel = niveles[Math.min(puntaje - 1, 4)] ?? niveles[0];
    barra.style.width      = nivel.pct;
    barra.style.background = nivel.color;
    texto.textContent      = nivel.label;
    texto.style.color      = nivel.color;
}

// Deshabilitar botón al enviar
document.querySelector('form').addEventListener('submit', function () {
    const btn = this.querySelector('[type="submit"]');
    btn.disabled    = true;
    btn.textContent = 'Creando cuenta...';
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
