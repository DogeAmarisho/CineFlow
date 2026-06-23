<?php
/**
 * CineFlow - Admin Footer
 * Archivo: admin/includes/footer.php
 */
$admin_js_extra ??= '';
?>
</main><!-- /admin-content -->
</div><!-- /admin-layout -->

<?php if ($admin_js_extra !== ''): ?>
    <script src="<?= esc($admin_js_extra) ?>"></script>
<?php endif; ?>

</body>
</html>
