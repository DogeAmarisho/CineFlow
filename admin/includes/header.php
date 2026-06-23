<?php
/**
 * CineFlow - Admin Header
 * Archivo: admin/includes/header.php
 * Requiere: config.php, funciones.php
 * Variables: $titulo_pagina, $admin_nav_activo
 */
if (!defined('CINEFLOW_CONFIG')) {
    require_once __DIR__ . '/../../config.php';
}
if (!defined('FUNCIONES_CARGADAS')) {
    require_once __DIR__ . '/../../includes/funciones.php';
}
// Protect: only admins can access admin pages
if (!esAdmin()) {
    header('Location: ../admin/login.php');
    exit;
}
$titulo_pagina    ??= 'Admin';
$admin_nav_activo ??= '';
$admin = obtenerUsuario((int)$_SESSION['usuario_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo_pagina) ?> — Admin CineFlow</title>
    <link rel="stylesheet" href="../assets/css/estilo.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚙️</text></svg>">
</head>
<body>
<div class="admin-layout">

<!-- ══ SIDEBAR ══════════════════════════════════════════════════ -->
<aside class="admin-sidebar">
    <a href="index.php" class="logo">Cine<span>Flow</span> <small style="font-size:.6em;color:var(--texto-muy-suave);">Admin</small></a>

    <nav class="admin-nav">
        <span class="nav-seccion">General</span>
        <a href="index.php"         class="<?= $admin_nav_activo === 'dashboard'  ? 'activo' : '' ?>">📊 Dashboard</a>

        <span class="nav-seccion">Contenido</span>
        <a href="peliculas.php"     class="<?= $admin_nav_activo === 'peliculas'  ? 'activo' : '' ?>">🎬 Películas</a>
        <a href="funciones.php"     class="<?= $admin_nav_activo === 'funciones'  ? 'activo' : '' ?>">🗓️ Funciones</a>

        <span class="nav-seccion">Operaciones</span>
        <a href="validar-ticket.php" class="<?= $admin_nav_activo === 'validar'   ? 'activo' : '' ?>">✅ Validar ticket</a>
        <a href="reportes.php"      class="<?= $admin_nav_activo === 'reportes'   ? 'activo' : '' ?>">📈 Reportes</a>

        <span class="nav-seccion">Sistema</span>
        <a href="../index.php" target="_blank">🌐 Ver sitio</a>
        <a href="logout.php">🚪 Cerrar sesión</a>
    </nav>

    <?php if ($admin): ?>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--borde);margin-top:auto;font-size:.8rem;color:var(--texto-suave);">
        👤 <?= esc($admin['nombre']) ?>
    </div>
    <?php endif; ?>
</aside>

<!-- ══ CONTENIDO PRINCIPAL ══════════════════════════════════════ -->
<main class="admin-content">
