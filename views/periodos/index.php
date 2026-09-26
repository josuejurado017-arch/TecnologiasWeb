<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Períodos de tutoría</h1>
            <p>Cada período pasa por <strong>borrador → activo → cerrado</strong>. Puede haber <strong>un período activo por <a href="<?= e(app_url('tipos-tutoria/')) ?>">tipo de tutoría</a></strong>; para activar otro del mismo tipo, cierra primero el vigente. Un período cerrado es historial: se consulta y admite observaciones, pero no se edita, reactiva ni elimina.</p>
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
            <thead><tr><th>ID</th><th>Período</th><th>Tipo</th><th>Inicio</th><th>Fin</th><th>Cupos (mín./máx.)</th><th>Si el tutor acepta ambas</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($periodos as $periodo): ?>
                    <tr data-row>
                        <td><?= (int) $periodo['id_periodo'] ?></td>
                        <td><?= e($periodo['nombre']) ?></td>
                        <td><?= e($periodo['tipo_nombre']) ?></td>
                        <td><?= e($periodo['fecha_inicio']) ?></td>
                        <td><?= e($periodo['fecha_fin']) ?></td>
                        <td><?= (int) $periodo['cupo_min_grupo'] ?> / <?= (int) $periodo['cupo_max_default'] ?></td>
                        <td><?= $periodo['modalidad_ambas'] === 'presencial' ? 'Presencial' : 'Virtual' ?></td>
                        <td>
                            <span class="badge badge-<?= e($periodo['estado']) ?>"><?= e(PeriodosController::ESTADOS[$periodo['estado']] ?? $periodo['estado']) ?></span>
                            <?php if ($periodo['estado'] === 'cerrada' && $periodo['fecha_cierre']): ?>
                                <br><small>el <?= e(date('d/m/Y', strtotime((string) $periodo['fecha_cierre']))) ?><?= $periodo['cerrado_por'] ? ' por ' . e($periodo['cerrado_por']) : '' ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <a href="<?= e(app_url('periodos/ver.php?id=' . (int) $periodo['id_periodo'])) ?>">Ver</a>
                            <?php if ($periodo['estado'] !== 'cerrada'): ?>
                                <a href="<?= e(app_url('periodos/edit.php?id=' . (int) $periodo['id_periodo'])) ?>">Editar</a>
                            <?php endif; ?>
                            <?php if ($periodo['estado'] !== 'borrador'): ?>
                                <a href="<?= e(app_url('reportes/campania.php?periodo=' . (int) $periodo['id_periodo'])) ?>">Reportes</a>
                            <?php endif; ?>
                            <?php if ($periodo['estado'] === 'borrador'): ?>
                                <form method="post" action="<?= e(app_url('periodos/activate.php')) ?>" onsubmit="return confirm('¿Activar este período? Empezará a recibir solicitudes de apoyo.');">
                                    <input type="hidden" name="id" value="<?= (int) $periodo['id_periodo'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button class="link-button" type="submit">Activar</button>
                                </form>
                                <form method="post" action="<?= e(app_url('periodos/delete.php')) ?>" onsubmit="return confirm('¿Eliminar este período en borrador?');">
                                    <input type="hidden" name="id" value="<?= (int) $periodo['id_periodo'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button class="link-button" type="submit">Eliminar</button>
                                </form>
                            <?php elseif ($periodo['estado'] === 'activa'): ?>
                                <a href="<?= e(app_url('periodos/cerrar.php?id=' . (int) $periodo['id_periodo'])) ?>">Cerrar período</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr data-search-empty hidden><td colspan="9" class="empty-state">No se encontraron periodos.</td></tr>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
