<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div><h1>Registro de accesos</h1><p>Auditoria de los intentos de inicio de sesion.</p></div>
        <div class="page-heading-actions"><a class="button" href="<?= e($exportUrl) ?>">Descargar CSV</a></div>
    </div>
    <?php if ($filterError !== ''): ?><p class="alert" role="alert"><?= e($filterError) ?></p><?php endif; ?>
    <form class="access-filter card" method="get" action="<?= e(app_url('accesos/')) ?>">
        <label>Desde<input type="date" name="fecha_desde" value="<?= e($fechaDesde) ?>"></label>
        <label>Hasta<input type="date" name="fecha_hasta" value="<?= e($fechaHasta) ?>"></label>
        <div class="filter-actions"><button type="submit">Filtrar</button><a class="button secondary" href="<?= e(app_url('accesos/')) ?>">Limpiar</a></div>
    </form>
    <div class="table-wrapper card"><table><thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Nombre</th><th>IP de origen</th><th>Resultado</th></tr></thead><tbody>
        <?php foreach ($accesses as $access): ?><tr><td><?= e($access['fecha_hora']) ?></td><td><?= e($access['usuario']) ?></td><td><?= e($access['nombre'] . ' ' . $access['apellido']) ?></td><td><?= e($access['ip_origen'] ?: 'No disponible') ?></td><td><span class="status status-<?= $access['resultado'] === 'exitoso' ? 'activo' : 'inactivo' ?>"><?= e($access['resultado']) ?></span></td></tr><?php endforeach; ?>
        <?php if (!$accesses): ?><tr><td colspan="5">No hay accesos registrados.</td></tr><?php endif; ?>
    </tbody></table></div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
