<?php
/**
 * ============================================================
 *  CineFlow - Plataforma de Gestión y Venta de Entradas
 * ============================================================
 *  Archivo   : includes/footer.php
 *  Propósito : Pie de página HTML reutilizable para todas las
 *              vistas. Cierra el <body> y el <html>.
 *
 *  VARIABLES QUE PUEDE DEFINIR LA VISTA ANTES DE INCLUIRLO:
 *    $js_extra  (string) → Ruta de un JS adicional de la vista.
 *                          Ej: 'assets/js/reserva.js'
 *                          Se inyecta justo antes de </body>
 *                          para no bloquear el renderizado.
 *
 *  Depende de: (nada, es el cierre del documento)
 *  Autores   : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

$js_extra ??= '';
?>

<!-- ══ PIE DE PÁGINA ══════════════════════════════════════════ -->
<footer class="site-footer">

    <nav class="footer-links" aria-label="Navegación secundaria">
        <a href="cartelera.php">Cartelera</a>
        <a href="#">Cómo funciona</a>
        <a href="#">Contacto</a>
        <a href="#">Términos y condiciones</a>
        <a href="#">Política de privacidad</a>
    </nav>

    <p>© <?= date('Y') ?> CineFlow &middot; Todos los derechos reservados.</p>
    <p class="mt-8" style="font-size:.75rem;">
        Desarrollado por Cristóbal Yáñez y Álvaro Hormazabal
        &nbsp;&middot;&nbsp;
        <a href="admin/login.php" style="color:var(--texto-muy-suave);">Administración</a>
    </p>

</footer>
<!-- /site-footer -->

<!-- ══ JS ADICIONAL DE LA VISTA (carga diferida) ════════════ -->
<?php if ($js_extra !== ''): ?>
    <script src="<?= esc($js_extra) ?>"></script>
<?php endif; ?>

</body>
</html>
