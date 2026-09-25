<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Materias</h1>
            <p>Materias disponibles para las tutorias.</p>
        </div>
        <a class="button" href="<?= e(app_url('materias/create.php')) ?>">Nueva materia</a>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="table-toolbar">
        <div class="search-field">
            <span class="search-icon" aria-hidden="true">/</span>
            <label class="sr-only" for="subject-search">Buscar materias</label>
            <input id="subject-search" type="search" placeholder="Buscar materia..." data-table-search>
        </div>
        <span class="table-meta" data-table-count><?= count($materias) ?> resultado<?= count($materias) === 1 ? '' : 's' ?></span>
    </div>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>ID</th><th>Materia</th><th>Carrera</th><th>Modalidad</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($materias as $subject): ?>
                    <tr data-row>
                        <td><?= (int) $subject['id_materia'] ?></td>
                        <td><?= e($subject['nombre_materia']) ?></td>
                        <td><?= e($subject['nombre_carrera'] ?? 'Sin carrera') ?></td>
                        <td><?= e(['libre' => 'Libre', 'presencial' => 'Presencial', 'virtual' => 'Virtual'][$subject['modalidad_requerida']] ?? 'Libre') ?></td>
                        <td class="actions">
                            <a href="<?= e(app_url('materias/edit.php?id=' . (int) $subject['id_materia'])) ?>">Editar</a>
                            <form method="post" action="<?= e(app_url('materias/delete.php')) ?>" onsubmit="return confirm('Eliminar esta materia?');">
                                <input type="hidden" name="id" value="<?= (int) $subject['id_materia'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="link-button" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr data-search-empty hidden><td colspan="5" class="empty-state">No se encontraron materias.</td></tr>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
