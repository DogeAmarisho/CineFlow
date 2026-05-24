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

// Sin sistema de login — acceso libre a todas las páginas
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

    <!-- Navegación principal -->
    <nav class="nav-principal" aria-label="Navegación principal">
        <a href="index.php"
           class="<?= $nav_activo === 'inicio' ? 'activo' : '' ?>">
            🏠 Inicio
        </a>
        <a href="cartelera.php"
           class="<?= $nav_activo === 'cartelera' ? 'activo' : '' ?>">
            🎬 Cartelera
        </a>
        <a href="consultar-reserva.php"
           class="<?= $nav_activo === 'consultar' ? 'activo' : '' ?>">
            🔍 Mis reservas
        </a>
    </nav>

</header>
<!-- /site-header -->
