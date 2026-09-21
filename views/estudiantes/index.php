<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Estudiantes</h1>
            <p>Perfiles academicos vinculados a usuarios con rol estudiante.</p>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <div class="table-toolbar">
        <div class="search-field">
            <span class="search-icon" aria-hidden="true">/</span>
            <label class="sr-only" for="student-search">Buscar estudiantes</label>
            <input id="student-search" type="search" placeholder="Buscar estudiante..." data-table-search>
        </div>
        <span class="table-meta" data-table-count><?= count($students) ?> resultado<?= count($students) === 1 ? '' : 's' ?></span>
    </div>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Estudiante</th><th>Correo</th><th>Carrera</th><th>Semestre</th><th>Registro</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr data-row>
                        <td><?= e($student['nombre'] . ' ' . $student['apellido']) ?></td>
                        <td><?= e($student['correo']) ?></td>
                        <td><?= e($student['nombre_carrera']) ?></td>
                        <td><?= (int) $student['semestre'] ?></td>
                        <td><?= e($student['registro_universitario'] ?: 'Sin registro') ?></td>
                        <td><span class="status status-<?= e($student['estado']) ?>"><?= e($student['estado']) ?></span></td>
                        <td class="actions">
                            <a href="<?= e(app_url('estudiantes/edit.php?id=' . (int) $student['id_estudiante'])) ?>">Editar</a>
                            <form method="post" action="<?= e(app_url('estudiantes/delete.php')) ?>" onsubmit="return confirm('Eliminar este perfil?');">
                                <input type="hidden" name="id" value="<?= (int) $student['id_estudiante'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="link-button" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$students): ?><tr><td colspan="7">No hay estudiantes registrados. Cree primero un usuario con rol estudiante.</td></tr><?php endif; ?>
                <tr data-search-empty hidden><td colspan="7" class="empty-state">No se encontraron estudiantes.</td></tr>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
