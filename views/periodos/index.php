<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Períodos de tutoría</h1>
            <p>Períodos académicos de apoyo (por ejemplo Enero y Julio). Solo uno puede estar activo.</p>
        </div>
        <a class="button" href="<?= e(app_url('periodos/create.php')) ?>">Nuevo período</a>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="table-toolbar">
        <div class="search-field">
            <span class="search-icon" aria-hidden="true">/</span>
            <label class="sr-only" for="periodo-search">Buscar periodos</label>
            <input id="periodo-search" type="search" placeholder="Buscar periodo..." data-table-search>
        </div>
        <span class="table-meta" data-table-count><?= count($periodos) ?> resultado<?= count($periodos) === 1 ? '' : 's' ?></span>
    </div>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>ID</th><th>Periodo</th><th>Inicio</th><th>Fin</th><th>Cupos (min/max)</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($periodos as $periodo): ?>
                    <tr data-row>
                        <td><?= (int) $periodo['id_periodo'] ?></td>
                        <td><?= e($periodo['nombre']) ?></td>
                        <td><?= e($periodo['fecha_inicio']) ?></td>
                        <td><?= e($periodo['fecha_fin']) ?></td>
                        <td><?= (int) $periodo['cupo_min_grupo'] ?> / <?= (int) $periodo['cupo_max_default'] ?></td>
                        <td><span class="badge badge-<?= e($periodo['estado']) ?>"><?= e(ucfirst($periodo['estado'])) ?></span></td>
                        <td class="actions">
                            <a href="<?= e(app_url('periodos/edit.php?id=' . (int) $periodo['id_periodo'])) ?>">Editar</a>
                            <?php if ($periodo['estado'] !== 'activa'): ?>
                                <form method="post" action="<?= e(app_url('periodos/activate.php')) ?>" onsubmit="return confirm('Activar este período? Los demás pasarán a borrador.');">
                                    <input type="hidden" name="id" value="<?= (int) $periodo['id_periodo'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button class="link-button" type="submit">Activar</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(app_url('periodos/delete.php')) ?>" onsubmit="return confirm('Eliminar este período?');">
                                <input type="hidden" name="id" value="<?= (int) $periodo['id_periodo'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="link-button" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr data-search-empty hidden><td colspan="7" class="empty-state">No se encontraron periodos.</td></tr>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
