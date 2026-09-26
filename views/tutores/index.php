<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Tutores</h1>
            <p>Perfiles de los usuarios que brindan apoyo academico.</p>
        </div>
        <div class="page-actions">
            <a class="button secondary" href="<?= e(app_url('tutores/export.php')) ?>">Exportar Excel</a>
            <a class="button" href="<?= e(app_url('usuarios/create.php?rol=tutor')) ?>">Nuevo tutor</a>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="table-toolbar">
        <div class="search-field">
            <span class="search-icon" aria-hidden="true">/</span>
            <label class="sr-only" for="tutor-search">Buscar tutores</label>
            <input id="tutor-search" type="search" placeholder="Buscar tutor..." data-table-search>
        </div>
        <span class="table-meta" data-table-count><?= count($tutors) ?> resultado<?= count($tutors) === 1 ? '' : 's' ?></span>
    </div>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Tutor</th><th>Correo</th><th>Especialidad</th><th>Cuenta</th><th>Habilitación</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($tutors as $tutor): ?>
                    <tr data-row>
                        <td><?= e($tutor['nombre'] . ' ' . $tutor['apellido']) ?></td>
                        <td><?= e($tutor['correo']) ?></td>
                        <td><?= e($tutor['especialidad'] ?: 'Sin especialidad') ?></td>
                        <td><span class="status status-<?= e($tutor['estado']) ?>"><?= e($tutor['estado']) ?></span></td>
                        <?php $docente = (string) ($tutor['estado_docente'] ?? 'aprobado'); ?>
                        <td><span class="badge badge-<?= e(['aprobado' => 'success', 'pendiente' => 'warning', 'rechazado' => 'danger', 'suspendido' => 'danger'][$docente] ?? 'info') ?>"><?= e(ucfirst($docente)) ?></span><?php if ($docente === 'pendiente' || $docente === 'rechazado'): ?> <a href="<?= e(app_url('tutores/pendientes/')) ?>">Revisar</a><?php endif; ?></td>
                        <td class="actions">
                            <a href="<?= e(app_url('tutores/edit.php?id=' . (int) $tutor['id_tutor'])) ?>">Editar</a>
                            <?php $activo = $tutor['estado'] === 'activo'; ?>
                            <form method="post" action="<?= e(app_url('tutores/estado.php')) ?>" onsubmit="return confirm(<?= $activo ? "'Desactivar la cuenta de este tutor? Dejara de recibir asignaciones y conservara su historial.'" : "'Reactivar la cuenta de este tutor?'" ?>);">
                                <input type="hidden" name="id" value="<?= (int) $tutor['id_tutor'] ?>">
                                <input type="hidden" name="estado" value="<?= $activo ? 'inactivo' : 'activo' ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="link-button" type="submit"><?= $activo ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$tutors): ?><tr><td colspan="6" class="empty-state">No hay tutores registrados. Use <strong>Nuevo tutor</strong>: la cuenta de acceso y el perfil se crean en un solo paso.</td></tr><?php endif; ?>
                <tr data-search-empty hidden><td colspan="6" class="empty-state">No se encontraron tutores.</td></tr>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
