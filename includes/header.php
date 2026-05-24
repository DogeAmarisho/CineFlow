<?php
/**
 * ============================================================
 *  CineFlow - Plataforma de Gestión y Venta de Entradas
 * ============================================================
 *  Archivo   : includes/header.php
 *  Propósito : Cabecera HTML reutilizable para todas las vistas.
 *
 *  VARIABLES QUE PUEDE DEFINIR LA VISTA ANTES DE INCLUIRLO:
 *    $titulo_pagina   (string) → Texto del <title>. Default: 'CineFlow'
 *    $css_extra       (string) → Ruta de un CSS adicional de la vista.
 *                               Ej: 'assets/css/admin.css'
 *    $nav_activo      (string) → Slug del enlace activo en el nav.
 *                               Valores: 'cartelera' | 'mis-reservas' | ''
 *
 *  Depende de: config.php, includes/funciones.php
 *  Autores   : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

// ─────────────────────────────────────────────────────────────
//  Guardia: config.php y funciones.php deben estar cargados.
//  Si la vista olvidó incluirlos, los cargamos aquí como
//  salvaguarda (la ruta relativa asume que la vista está en
//  la raíz del proyecto).
// ─────────────────────────────────────────────────────────────
if (!defined('CINEFLOW_CONFIG')) {
    require_once __DIR__ . '/../config.php';
}
if (!defined('FUNCIONES_CARGADAS')) {
    require_once __DIR__ . '/funciones.php';
}

// ─────────────────────────────────────────────────────────────
//  Valores por defecto si la vista no los definió
// ─────────────────────────────────────────────────────────────
$titulo_pagina ??= 'CineFlow';
$css_extra     ??= '';
$nav_activo    ??= '';

// Nombre del usuario para saludar en el header
$nombre_sesion = '';
if (estaAutenticado()) {
    $nombre_sesion = htmlspecialchars(
        $_SESSION['nombre'] ?? 'Usuario',
        ENT_QUOTES, 'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO básico -->
    <meta name="description" content="CineFlow – Compra tus entradas de cine de forma fácil y rápida.">
    <meta name="robots"      content="index, follow">

    <title><?= esc($titulo_pagina) ?> – CineFlow</title>

    <!-- Estilo global -->
    <link rel="stylesheet" href="assets/css/estilo.css">

    <!-- CSS adicional específico de la vista (opcional) -->
    <?php if ($css_extra !== ''): ?>
        <link rel="stylesheet" href="<?= esc($css_extra) ?>">
    <?php endif; ?>

    <!-- Favicon (inline SVG como data URI para no depender de un archivo externo) -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
</head>
<body>

<!-- ══ CABECERA ══════════════════════════════════════════════ -->
<header class="site-header">

    <!-- Logo -->
    <a href="index.php" class="logo" aria-label="CineFlow – inicio">
        Cine<span>Flow</span>
    </a>

    <!-- Navegación principal (centro) -->
    <nav class="nav-principal" aria-label="Navegación principal">
        <a href="cartelera.php"
           class="<?= $nav_activo === 'cartelera' ? 'activo' : '' ?>">
            🎬 Cartelera
        </a>

        <?php if (estaAutenticado()): ?>
            <a href="mis-reservas.php"
               class="<?= $nav_activo === 'mis-reservas' ? 'activo' : '' ?>">
                🎟 Mis reservas
            </a>
        <?php endif; ?>

        <?php if (esAdmin()): ?>
            <a href="admin/index.php"
               class="<?= $nav_activo === 'admin' ? 'activo' : '' ?> texto-primario">
                ⚙ Admin
            </a>
        <?php endif; ?>
    </nav>

    <!-- Área de usuario (derecha) -->
    <div class="nav-usuario" aria-label="Cuenta de usuario">
        <?php if (estaAutenticado()): ?>
            <span class="bienvenida ocultar-movil">
                Hola, <strong><?= $nombre_sesion ?></strong>
            </span>
            <a href="logout.php?token=<?= generarTokenCsrf() ?>" class="btn-logout">
                Salir
            </a>
        <?php else: ?>
            <a href="login.php" class="btn btn-primario btn-sm">
                Iniciar sesión
            </a>
        <?php endif; ?>
    </div>

</header>
<!-- /site-header -->
