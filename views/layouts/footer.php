<?php if (!empty($isAuthenticated)): ?>
        </div>
    </div>
<?php endif; ?>
<script src="<?= e(app_url('Front/assets/js/app.js') . '?v=' . @filemtime(dirname(__DIR__, 2) . '/Front/assets/js/app.js')) ?>" defer></script>
</body>
</html>
