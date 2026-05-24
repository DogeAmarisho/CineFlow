<?php
/**
 * ============================================================
 *  CineFlow - Plataforma de Gestión y Venta de Entradas
 * ============================================================
 *  Archivo   : config.php
 *  Propósito : Núcleo de la aplicación. Debe ser el primer
 *              archivo incluido en cualquier vista.
 *
 *  RESPONSABILIDADES:
 *    1. Definir constantes globales (entorno, BD, negocio).
 *    2. Configurar manejo de errores según el entorno.
 *    3. Iniciar sesión PHP de forma segura.
 *    4. Proveer la conexión PDO (patrón Singleton).
 *    5. Proveer el registro centralizado de errores.
 *
 *  Autores : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

// Guardia: evitar doble inclusión
if (defined('CINEFLOW_CONFIG')) return;
define('CINEFLOW_CONFIG', true);


// ═════════════════════════════════════════════════════════════
//  1. CONSTANTES GLOBALES
// ═════════════════════════════════════════════════════════════

/**
 * ENTORNO: controla el nivel de detalle de los errores.
 *   'desarrollo'  → errores visibles en pantalla (XAMPP local)
 *   'produccion'  → errores solo en log, nunca en pantalla
 */
define('ENTORNO', 'desarrollo');   // ← Cambiar a 'produccion' en el servidor real

// ── Base de datos (XAMPP por defecto) ────────────────────────
define('DB_HOST',    'localhost');
define('DB_PUERTO',  '3306');
define('DB_NOMBRE',  'cineflow');
define('DB_USUARIO', 'root');
define('DB_CLAVE',   '');          // XAMPP local no usa contraseña por defecto

// ── Negocio ──────────────────────────────────────────────────
/** Minutos que tiene el usuario para pagar desde que confirma la reserva */
define('RESERVA_TIEMPO_LIMITE', 15);

/** ID de usuario de prueba cuando no hay sesión activa (solo desarrollo) */
define('USUARIO_PRUEBA_ID', 2);

/** Ruta base del proyecto (sin trailing slash) */
define('BASE_PATH', __DIR__);

/** Ruta donde se guardan los logs de error */
define('LOG_PATH', BASE_PATH . '/logs/errores.log');


// ═════════════════════════════════════════════════════════════
//  2. CONFIGURACIÓN DE ERRORES
// ═════════════════════════════════════════════════════════════
if (ENTORNO === 'desarrollo') {
    // Mostrar todos los errores en pantalla → facilita el debugging local
    ini_set('display_errors',         '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    // En producción: silenciar errores en pantalla, solo log
    ini_set('display_errors',         '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    ini_set('error_log',  LOG_PATH);
}


// ═════════════════════════════════════════════════════════════
//  3. SESIÓN SEGURA
//  Configuramos las opciones ANTES de session_start() para que
//  tengan efecto. Si la sesión ya está activa (doble include),
//  las saltamos.
// ═════════════════════════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE) {

    // La cookie de sesión solo se envía por HTTP (no accesible desde JS)
    ini_set('session.cookie_httponly', '1');

    // Solo enviar la cookie sobre HTTPS en producción
    ini_set('session.cookie_secure',   ENTORNO === 'produccion' ? '1' : '0');

    // Evitar que la sesión se pase en la URL (?PHPSESSID=...)
    ini_set('session.use_only_cookies', '1');

    // Usar SameSite=Lax para mitigar CSRF en peticiones cross-site
    ini_set('session.cookie_samesite', 'Lax');

    // Nombre de sesión personalizado (oscurece la tecnología usada)
    session_name('cf_session');

    session_start();

    // Regenerar ID en cada sesión nueva para prevenir Session Fixation
    // Solo si es una sesión recién creada (no tiene datos todavía)
    if (empty($_SESSION['_iniciada'])) {
        session_regenerate_id(true);
        $_SESSION['_iniciada'] = true;
    }
}


// ═════════════════════════════════════════════════════════════
//  4. CONEXIÓN PDO — PATRÓN SINGLETON
//  Una sola instancia de PDO por request → eficiente y seguro.
//  Se crea la primera vez que se llama a obtenerConexion().
// ═════════════════════════════════════════════════════════════

/** @var PDO|null $_pdo_instancia  Instancia única (acceso solo vía obtenerConexion) */
$_pdo_instancia = null;

/**
 * Devuelve la conexión PDO activa, creándola si no existe.
 *
 * OPCIONES PDO:
 *   ERRMODE_EXCEPTION     → los errores de BD lanzan PDOException
 *   FETCH_ASSOC           → fetchAll() devuelve arrays asociativos
 *   EMULATE_PREPARES=false → usa prepared statements reales (más seguro)
 *   CHARSET utf8mb4       → soporta tildes, ñ y emojis
 *
 * @return PDO
 * @throws RuntimeException Si la conexión falla en producción
 */
function obtenerConexion(): PDO
{
    global $_pdo_instancia;

    if ($_pdo_instancia !== null) {
        return $_pdo_instancia;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PUERTO,
        DB_NOMBRE
    );

    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,   // Prepared statements reales
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $_pdo_instancia = new PDO($dsn, DB_USUARIO, DB_CLAVE, $opciones);
        return $_pdo_instancia;

    } catch (PDOException $e) {
        // Registrar el error con todos los detalles
        registrarError('config - obtenerConexion', $e->getMessage());

        if (ENTORNO === 'desarrollo') {
            // En desarrollo: mensaje técnico para facilitar el diagnóstico
            die(
                '<div style="font-family:monospace;background:#2e0d0d;color:#e74c3c;' .
                'padding:20px;margin:20px;border-radius:8px;border:1px solid #e74c3c;">' .
                '<strong>Error de conexión a la base de datos:</strong><br>' .
                htmlspecialchars($e->getMessage()) .
                '<br><br><small>Verifica que XAMPP (MySQL) esté corriendo y que la BD ' .
                '<strong>' . DB_NOMBRE . '</strong> exista.</small>' .
                '</div>'
            );
        }

        // En producción: mensaje genérico, nunca revelar detalles
        http_response_code(503);
        die('El servicio no está disponible en este momento. Por favor, inténtalo más tarde.');
    }
}


// ═════════════════════════════════════════════════════════════
//  5. REGISTRO DE ERRORES
//  Escribe en logs/errores.log con timestamp, tipo y mensaje.
//  Se llama desde cualquier catch(PDOException) o error inesperado.
// ═════════════════════════════════════════════════════════════

/**
 * Registra un error en el archivo de log del proyecto.
 *
 * Formato de cada línea:
 *   [2025-05-23 14:32:01] [cartelera - obtenerPeliculas] Error: SQLSTATE...
 *
 * @param string $tipo     Identificador del origen del error. Ej: 'reserva - procesarReserva'
 * @param string $mensaje  Mensaje de error (normalmente $e->getMessage())
 */
function registrarError(string $tipo, string $mensaje): void
{
    // Asegurarse de que la carpeta logs/ existe
    $directorio = dirname(LOG_PATH);
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true);
    }

    $linea = sprintf(
        "[%s] [%s] %s\n",
        date('Y-m-d H:i:s'),
        $tipo,
        $mensaje
    );

    // FILE_APPEND → acumula entradas; LOCK_EX → evita escrituras concurrentes
    file_put_contents(LOG_PATH, $linea, FILE_APPEND | LOCK_EX);
}
