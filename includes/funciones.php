<?php
/**
 * ============================================================
 *  CineFlow - Plataforma de Gestión y Venta de Entradas
 * ============================================================
 *  Archivo   : includes/funciones.php
 *  Propósito : Biblioteca de funciones de utilidad compartidas
 *              por todas las vistas del proyecto.
 *
 *  ORGANIZACIÓN:
 *    A) Formateo y presentación
 *    B) Seguridad y sanitización
 *    C) Sesión y autenticación
 *    D) Consultas de soporte (lecturas simples reutilizables)
 *    E) Notificaciones flash
 *
 *  Depende de: config.php  (debe incluirse antes que este archivo)
 *  Autores   : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

// Guardia: evitar doble inclusión
if (defined('FUNCIONES_CARGADAS')) return;
define('FUNCIONES_CARGADAS', true);


// ═════════════════════════════════════════════════════════════
//  A) FORMATEO Y PRESENTACIÓN
// ═════════════════════════════════════════════════════════════

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

function etiquetaFecha(string $fecha_iso): string
{
    $dt     = new DateTime($fecha_iso);
    $hoy    = new DateTime('today');
    $manana = new DateTime('tomorrow');

    if ($dt == $hoy)    return 'Hoy';
    if ($dt == $manana) return 'Mañana';

    $dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    return $dias[(int)$dt->format('w')] . ' ' . $dt->format('d/m');
}

function etiquetaEstadoReserva(string $estado): string
{
    return match($estado) {
        'pendiente'  => 'Pendiente de pago',
        'confirmada' => 'Confirmada',
        'cancelada'  => 'Cancelada',
        'expirada'   => 'Expirada',
        default      => ucfirst($estado),
    };
}

function claseBadgeEstado(string $estado): string
{
    return match($estado) {
        'confirmada' => 'badge-verde',
        'pendiente'  => 'badge-amarillo',
        'cancelada',
        'expirada'   => 'badge-rojo',
        default      => 'badge-gris',
    };
}


// ═════════════════════════════════════════════════════════════
//  B) SEGURIDAD Y SANITIZACIÓN
// ═════════════════════════════════════════════════════════════

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
    if (!isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        registrarError('seguridad - CSRF', 'Token inválido. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida'));
        http_response_code(403);
        die('Solicitud no válida. Por favor, recarga la página e inténtalo de nuevo.');
    }
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf_token" value="' . esc(generarTokenCsrf()) . '">';
}


// ═════════════════════════════════════════════════════════════
//  C) SESIÓN Y AUTENTICACIÓN
// ═════════════════════════════════════════════════════════════

function estaAutenticado(): bool
{
    return !empty($_SESSION['usuario_id']) && is_int($_SESSION['usuario_id']);
}

function esAdmin(): bool
{
    return estaAutenticado() && ($_SESSION['rol'] ?? '') === 'admin';
}

function requerirLogin(string $redireccion_post_login = ''): void
{
    if (!estaAutenticado()) {
        if ($redireccion_post_login !== '') {
            $_SESSION['redirigir_a'] = $redireccion_post_login;
        }
        header('Location: login.php');
        exit;
    }
}

function redirigir(string $url, int $codigo = 302): void
{
    header("Location: {$url}", true, $codigo);
    exit;
}


// ═════════════════════════════════════════════════════════════
//  D) CONSULTAS DE SOPORTE
// ═════════════════════════════════════════════════════════════

function obtenerUsuario(int $usuario_id): ?array
{
    $pdo = obtenerConexion();
    try {
        $stmt = $pdo->prepare("
            SELECT id, nombre, email, rol
            FROM   usuarios
            WHERE  id     = :id
              AND  activo = 1
            LIMIT 1
        ");
        $stmt->execute([':id' => $usuario_id]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    } catch (PDOException $e) {
        registrarError('funciones - obtenerUsuario', $e->getMessage());
        return null;
    }
}

function obtenerReservasUsuario(int $usuario_id): array
{
    $pdo = obtenerConexion();
    try {
        $stmt = $pdo->prepare("
            SELECT
                r.id               AS reserva_id,
                r.codigo_reserva,
                r.estado,
                r.fecha_reserva,
                r.fecha_expiracion,
                a.fila,
                a.numero           AS asiento_numero,
                a.tipo             AS tipo_asiento,
                f.fecha_hora,
                f.precio,
                f.idioma,
                p.titulo           AS pelicula,
                p.imagen           AS poster,
                s.nombre           AS sala
            FROM   reservas r
            INNER JOIN asientos  a ON a.id = r.asiento_id
            INNER JOIN funciones f ON f.id = r.funcion_id
            INNER JOIN peliculas p ON p.id = f.pelicula_id
            INNER JOIN salas     s ON s.id = f.sala_id
            WHERE  r.usuario_id = :usuario_id
            ORDER  BY r.fecha_reserva DESC
        ");
        $stmt->execute([':usuario_id' => $usuario_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        registrarError('funciones - obtenerReservasUsuario', $e->getMessage());
        return [];
    }
}

function obtenerReservaPorCodigo(string $codigo): ?array
{
    $pdo = obtenerConexion();
    try {
        $stmt = $pdo->prepare("
            SELECT
                r.id               AS reserva_id,
                r.codigo_reserva,
                r.estado,
                r.fecha_reserva,
                r.fecha_expiracion,
                r.usuario_id,
                a.fila,
                a.numero           AS asiento_numero,
                a.tipo             AS tipo_asiento,
                f.id               AS funcion_id,
                f.fecha_hora,
                f.precio,
                f.idioma,
                p.titulo           AS pelicula,
                p.clasificacion,
                p.imagen           AS poster,
                s.nombre           AS sala,
                s.tipo             AS tipo_sala
            FROM   reservas r
            INNER JOIN asientos  a ON a.id = r.asiento_id
            INNER JOIN funciones f ON f.id = r.funcion_id
            INNER JOIN peliculas p ON p.id = f.pelicula_id
            INNER JOIN salas     s ON s.id = f.sala_id
            WHERE  r.codigo_reserva = :codigo
            ORDER  BY a.fila ASC, a.numero ASC
        ");
        $stmt->execute([':codigo' => strtoupper(trim($codigo))]);
        $filas = $stmt->fetchAll();
        return empty($filas) ? null : $filas;
    } catch (PDOException $e) {
        registrarError('funciones - obtenerReservaPorCodigo', $e->getMessage());
        return null;
    }
}


// ═════════════════════════════════════════════════════════════
//  E) NOTIFICACIONES FLASH
// ═════════════════════════════════════════════════════════════

function flashMensaje(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = [
        'tipo'    => $tipo,
        'mensaje' => $mensaje,
    ];
}

function obtenerFlash(): ?array
{
    if (!isset($_SESSION['flash'])) return null;

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function renderFlash(): string
{
    $flash = obtenerFlash();
    if ($flash === null) return '';

    $clase   = 'alerta alerta-' . esc($flash['tipo']);
    $mensaje = esc($flash['mensaje']);

    return "<div class=\"{$clase}\" role=\"alert\">{$mensaje}</div>";
}
