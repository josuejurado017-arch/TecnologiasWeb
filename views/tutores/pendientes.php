<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Tutores pendientes</h1>
            <p>Habilitación docente: un tutor pendiente puede iniciar sesión y configurar sus materias, pero el sistema no le propone grupos hasta que lo apruebes.</p>
        </div>
        <div class="page-actions">
            <a class="button secondary" href="<?= e(app_url('tutores/')) ?>">Todos los tutores</a>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Tutor</th><th>Especialidad</th><th>Materias declaradas</th><th>Registro</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($tutores as $t): ?>
                    <?php $pendiente = $t['estado_docente'] === 'pendiente'; ?>
                    <tr>
                        <td>
                            <strong><?= e($t['nombre'] . ' ' . $t['apellido']) ?></strong><br>
                            <small><?= e($t['correo']) ?><?= $t['telefono'] ? ' · ' . e($t['telefono']) : '' ?></small>
                            <?php if ($t['biografia']): ?><br><small class="muted"><?= e(mb_strimwidth((string) $t['biografia'], 0, 160, '…')) ?></small><?php endif; ?>
                        </td>
                        <td><?= e($t['especialidad'] ?: 'Sin especialidad') ?></td>
                        <td>
                            <?php if ((int) $t['materias'] === 0): ?>
                                <span class="badge badge-warning">Sin materias</span>
                            <?php else: ?>
                                <?= e($t['nombres_materias']) ?><br>
                                <small><?= (int) $t['materias_configuradas'] ?> de <?= (int) $t['materias'] ?> con horarios configurados</small>
                            <?php endif; ?>
                        </td>
                        <td><?= e(date('d/m/Y', strtotime((string) $t['fecha_registro']))) ?></td>
                        <td>
                            <span class="badge badge-<?= $pendiente ? 'warning' : 'danger' ?>"><?= $pendiente ? 'Pendiente' : 'Rechazado' ?></span>
                            <?php if (!$pendiente && $t['motivo_rechazo']): ?><br><small><?= e($t['motivo_rechazo']) ?></small><?php endif; ?>
                        </td>
                        <td class="actions">
                            <form method="post" action="<?= e(app_url('tutores/habilitacion.php')) ?>" onsubmit="return confirm('¿Aprobar a este tutor? El sistema podrá proponerle grupos en sus materias configuradas.');">
                                <input type="hidden" name="id" value="<?= (int) $t['id_tutor'] ?>">
                                <input type="hidden" name="accion" value="aprobar">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="link-button" type="submit"><?= $pendiente ? 'Aprobar' : 'Reconsiderar y aprobar' ?></button>
                            </form>
                            <?php if ($pendiente): ?>
                                <form method="post" action="<?= e(app_url('tutores/habilitacion.php')) ?>" onsubmit="return confirm('¿Rechazar a este tutor? Se le notificará el motivo.');">
                                    <input type="hidden" name="id" value="<?= (int) $t['id_tutor'] ?>">
                                    <input type="hidden" name="accion" value="rechazar">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input class="inline-response" name="motivo" placeholder="Motivo del rechazo" required minlength="10" maxlength="500">
                                    <button class="link-button" type="submit">Rechazar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$tutores): ?>
                    <tr><td colspan="6" class="empty-state">No hay tutores pendientes de habilitación.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
