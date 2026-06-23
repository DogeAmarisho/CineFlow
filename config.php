<?php
/**
 * CineFlow - config.php
 * Núcleo de la app: constantes, errores, sesión, conexión PDO,
 * registro de errores y funciones de utilidad compartidas.
 * Debe ser el primer archivo incluido en cualquier vista.
 */

if (defined('CINEFLOW_CONFIG')) return;
define('CINEFLOW_CONFIG', true);

require_once __DIR__ . '/includes/clases.php';
require_once __DIR__ . '/includes/layout.php';


// constantes generales

// 'desarrollo' muestra errores en pantalla; 'produccion' solo los registra en log.
define('ENTORNO', 'desarrollo');

define('DB_HOST',    'localhost');
define('DB_PUERTO',  '3307');
define('DB_NOMBRE',  'cineflow');
define('DB_USUARIO', 'root');
define('DB_CLAVE',   '');

define('RESERVA_TIEMPO_LIMITE', 15); // minutos de validez de una reserva
define('BASE_PATH', __DIR__);
define('LOG_PATH', BASE_PATH . '/logs/errores.log');


// como se muestran los errores de PHP

if (ENTORNO === 'desarrollo') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH);
}


// configuracion de la sesion

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', ENTORNO === 'produccion' ? '1' : '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_name('cf_session');
    session_start();

    // cambia el id de sesion solo si es nueva (evita session fixation)
    if (empty($_SESSION['_iniciada'])) {
        session_regenerate_id(true);
        $_SESSION['_iniciada'] = true;
    }
}


// conexion a la base de datos (una sola instancia por request)

$_pdo_instancia = null;

function obtenerConexion(): PDO
{
    global $_pdo_instancia;
    if ($_pdo_instancia !== null) return $_pdo_instancia;

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PUERTO, DB_NOMBRE);
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $_pdo_instancia = new PDO($dsn, DB_USUARIO, DB_CLAVE, $opciones);
        return $_pdo_instancia;
    } catch (PDOException $e) {
        registrarError('config - obtenerConexion', $e->getMessage());

        if (ENTORNO === 'desarrollo') {
            die(
                '<div style="font-family:monospace;background:#2e0d0d;color:#e74c3c;' .
                'padding:20px;margin:20px;border-radius:8px;border:1px solid #e74c3c;">' .
                '<strong>Error de conexión a la base de datos:</strong><br>' .
                htmlspecialchars($e->getMessage()) .
                '<br><br><small>Verifica que XAMPP (MySQL) esté corriendo y que la BD ' .
                '<strong>' . DB_NOMBRE . '</strong> exista.</small></div>'
            );
        }
        http_response_code(503);
        die('El servicio no está disponible en este momento. Por favor, inténtalo más tarde.');
    }
}


// guarda errores en logs/errores.log

function registrarError(string $tipo, string $mensaje): void
{
    $directorio = dirname(LOG_PATH);
    if (!is_dir($directorio)) mkdir($directorio, 0755, true);

    $linea = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $tipo, $mensaje);
    file_put_contents(LOG_PATH, $linea, FILE_APPEND | LOCK_EX);
}


// funciones que se usan en varias paginas

// Formateo y presentación
function formatearDuracion(?int $minutos): string
{
    if ($minutos === null || $minutos <= 0) return 'N/D';
    $horas = intdiv($minutos, 60);
    $min   = $minutos % 60;
    return $horas > 0 ? "{$horas}h {$min}m" : "{$min}m";
}

function formatearPrecio(float|int|string $precio): string
{
    return '$' . number_format((float)$precio, 0, ',', '.');
}

function claseBadgeClasificacion(string $clasificacion): string
{
    return match($clasificacion) {
        'TE'    => 'badge-verde',
        'TE+7'  => 'badge-azul',
        'MA+14' => 'badge-amarillo',
        'MA+18' => 'badge-rojo',
        default => 'badge-gris',
    };
}

function etiquetaEstadoReserva(string $estado): string
{
    return match($estado) {
        'pendiente'  => 'Pendiente',
        'confirmada' => 'Confirmada',
        'cancelada'  => 'Cancelada',
        'expirada'   => 'Expirada',
        'utilizada'  => 'Utilizada ✓',
        default      => ucfirst($estado),
    };
}

function claseBadgeEstado(string $estado): string
{
    return match($estado) {
        'confirmada' => 'badge-verde',
        'pendiente'  => 'badge-amarillo',
        'utilizada'  => 'badge-azul',
        'cancelada', 'expirada' => 'badge-rojo',
        default      => 'badge-gris',
    };
}

// Seguridad y sanitización
function esc(?string $texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function generarTokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verificarTokenCsrf(string $token): void
{
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        registrarError('seguridad - CSRF', 'Token inválido. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'));
        http_response_code(403);
        die('Solicitud no válida. Por favor, recarga la página e inténtalo de nuevo.');
    }
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf_token" value="' . esc(generarTokenCsrf()) . '">';
}

// Sesión y autenticación
function estaAutenticado(): bool
{
    return !empty($_SESSION['usuario_id']) && is_int($_SESSION['usuario_id']);
}

function esAdmin(): bool
{
    return estaAutenticado() && ($_SESSION['rol'] ?? '') === 'admin';
}

function redirigir(string $url, int $codigo = 302): void
{
    header("Location: {$url}", true, $codigo);
    exit;
}

// Consultas de soporte
function obtenerUsuario(int $usuario_id): ?array
{
    $pdo = obtenerConexion();
    try {
        $stmt = $pdo->prepare("SELECT id, nombre, email, rol FROM usuarios WHERE id = :id AND activo = 1 LIMIT 1");
        $stmt->execute([':id' => $usuario_id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        registrarError('funciones - obtenerUsuario', $e->getMessage());
        return null;
    }
}

// Notificaciones flash
function flashMensaje(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function obtenerFlash(): ?array
{
    if (!isset($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

// Fila clave/valor reutilizable (usada en confirmacion.php, validar-ticket.php)
function filaDato(string $clave, string $valorHtml, string $estiloValor = ''): string
{
    $style = $estiloValor !== '' ? " style=\"{$estiloValor}\"" : '';
    return "<div class=\"dato-fila\"><span class=\"dato-clave\">{$clave}</span><span class=\"dato-valor\"{$style}>{$valorHtml}</span></div>";
}

function renderFlash(): string
{
    $flash = obtenerFlash();
    if ($flash === null) return '';
    $clase   = 'alerta alerta-' . esc($flash['tipo']);
    $mensaje = esc($flash['mensaje']);
    return "<div class=\"{$clase}\" role=\"alert\">{$mensaje}</div>";
}
