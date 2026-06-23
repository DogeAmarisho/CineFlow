<?php
/**
 * Funciones de header/footer, para el sitio publico y para el admin.
 * Antes eran 4 archivos separados, los junte aca para no repetir
 * el mismo HTML en cada vista.
 *
 * Uso (publico):
 *   $titulo_pagina = 'Inicio'; $nav_activo = 'inicio';
 *   render_header();
 *   ...contenido...
 *   render_footer();
 *
 * Uso (admin):
 *   $titulo_pagina = 'Peliculas'; $admin_nav_activo = 'peliculas';
 *   render_admin_header();
 *   ...contenido...
 *   render_admin_footer();
 */

if (defined('CINEFLOW_LAYOUT')) return;
define('CINEFLOW_LAYOUT', true);


// ---------- header / footer del sitio publico ----------

function render_header(): void
{
    global $titulo_pagina, $css_extra, $nav_activo;

    $titulo_pagina ??= 'CineFlow';
    $css_extra     ??= '';
    $nav_activo    ??= '';
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="CineFlow – Compra tus entradas de cine de forma fácil y rápida.">
    <meta name="robots"      content="index, follow">

    <title><?= esc($titulo_pagina) ?> – CineFlow</title>

    <link rel="stylesheet" href="assets/css/estilo.css">

    <?php if ($css_extra !== ''): ?>
        <link rel="stylesheet" href="<?= esc($css_extra) ?>">
    <?php endif; ?>

    <!-- favicon en SVG inline, asi no dependemos de un archivo aparte -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
</head>
<body>

<header class="site-header">

    <a href="index.php" class="logo" aria-label="CineFlow – inicio">
        <img src="assets/img/logo.png" alt="CineFlow" class="logo-img">
        Cine<span>Flow</span>
    </a>

    <nav class="nav-principal" aria-label="Navegación principal">
        <a href="index.php"
           class="<?= $nav_activo === 'inicio' ? 'activo' : '' ?>">
            🏠 Inicio
        </a>
    </nav>

</header>
    <?php
}

function render_footer(): void
{
    global $js_extra;
    $js_extra ??= '';
    ?>

<footer class="site-footer">

    <p>© <?= date('Y') ?> CineFlow &middot; Todos los derechos reservados.</p>
    <p class="mt-8" style="font-size:.75rem;">
        Desarrollado por Cristóbal Yáñez y Álvaro Hormazabal
        &nbsp;&middot;&nbsp;
        <a href="admin/login.php" style="color:var(--texto-muy-suave);">Administración</a>
    </p>

</footer>

<?php if ($js_extra !== ''): ?>
    <script src="<?= esc($js_extra) ?>"></script>
<?php endif; ?>

</body>
</html>
    <?php
}


// ---------- header / footer del panel admin ----------

function render_admin_header(): void
{
    global $titulo_pagina, $admin_nav_activo;

    // solo admins pueden entrar, si no esta logueado lo manda al login
    if (!esAdmin()) {
        header('Location: login.php');
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

<aside class="admin-sidebar">
    <a href="peliculas.php" class="logo">Cine<span>Flow</span> <small style="font-size:.6em;color:var(--texto-muy-suave);">Admin</small></a>

    <nav class="admin-nav">
        <span class="nav-seccion">Contenido</span>
        <a href="peliculas.php"     class="<?= $admin_nav_activo === 'peliculas'  ? 'activo' : '' ?>">🎬 Películas</a>
        <a href="funciones.php"     class="<?= $admin_nav_activo === 'funciones'  ? 'activo' : '' ?>">🗓️ Funciones</a>

        <span class="nav-seccion">Operaciones</span>
        <a href="validar-ticket.php" class="<?= $admin_nav_activo === 'validar'   ? 'activo' : '' ?>">✅ Validar ticket</a>
        <a href="reportes.php"      class="<?= $admin_nav_activo === 'reportes'   ? 'activo' : '' ?>">📈 Reportes</a>

        <span class="nav-seccion">Sistema</span>
        <a href="../index.php" target="_blank">🌐 Ver sitio</a>
        <a href="login.php?salir=1">🚪 Cerrar sesión</a>
    </nav>

    <?php if ($admin): ?>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--borde);margin-top:auto;font-size:.8rem;color:var(--texto-suave);">
        👤 <?= esc($admin['nombre']) ?>
    </div>
    <?php endif; ?>
</aside>

<main class="admin-content">
    <?php
}

function render_admin_footer(): void
{
    global $admin_js_extra;
    $admin_js_extra ??= '';
    ?>
</main>
</div>

<?php if ($admin_js_extra !== ''): ?>
    <script src="<?= esc($admin_js_extra) ?>"></script>
<?php endif; ?>

</body>
</html>
    <?php
}
