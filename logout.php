<?php
/**
 * ============================================================
 *  CineFlow - Plataforma de Gestión y Venta de Entradas
 * ============================================================
 *  Archivo   : logout.php
 *  Propósito : Cierre de sesión seguro.
 *
 *  FLUJO:
 *    1. Verificar token CSRF (evita cerrar sesión por un link
 *       externo malicioso — CSRF en petición GET).
 *    2. Vaciar el array $_SESSION.
 *    3. Destruir la cookie de sesión en el navegador.
 *    4. Destruir la sesión en el servidor.
 *    5. Redirigir a index.php con mensaje flash.
 *
 *  Acceso   : GET con ?token=CSRF_TOKEN
 *  Depende de: config.php, includes/funciones.php
 *  Autores  : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/funciones.php';

// ─────────────────────────────────────────────────────────────
//  Solo actuar si hay sesión activa
// ─────────────────────────────────────────────────────────────
if (!estaAutenticado()) {
    redirigir('index.php');
}

// ─────────────────────────────────────────────────────────────
//  Verificar token CSRF enviado en la URL (parámetro GET).
//  El enlace de logout en header.php debe incluirlo:
//    href="logout.php?token=<?= generarTokenCsrf() ?>"
//
//  Si el token no coincide, ignoramos la petición y
//  redirigimos sin cerrar sesión.
// ─────────────────────────────────────────────────────────────
$token_recibido = $_GET['token'] ?? '';
if (!isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token_recibido)) {

    registrarError(
        'logout - CSRF',
        'Token inválido. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida')
    );
    redirigir('index.php');
}

// ─────────────────────────────────────────────────────────────
//  Cerrar sesión en 3 pasos
// ─────────────────────────────────────────────────────────────

// 1. Vaciar todas las variables de sesión
$_SESSION = [];

// 2. Eliminar la cookie de sesión del navegador del usuario
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 86400,          // Fecha en el pasado → el navegador la borra
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Destruir los datos de sesión en el servidor
session_destroy();

// ─────────────────────────────────────────────────────────────
//  Abrir una sesión nueva y limpia para poder mostrar el flash
// ─────────────────────────────────────────────────────────────
session_start();
session_regenerate_id(true);

flashMensaje('info', '✓ Has cerrado sesión correctamente. ¡Hasta pronto!');
redirigir('index.php');
