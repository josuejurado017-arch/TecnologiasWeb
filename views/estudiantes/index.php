<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Estudiantes</h1>
            <p>Perfiles academicos vinculados a usuarios con rol estudiante.</p>
        </div>
        <div class="page-actions">
            <a class="button secondary" href="<?= e($exportUrl) ?>" title="Descarga lo que muestra la tabla, con los filtros aplicados">Exportar Excel<?= $queryFiltros !== '' ? ' (filtrado)' : '' ?></a>
            <a class="button" href="<?= e(app_url('usuarios/create.php?rol=estudiante')) ?>">Nuevo estudiante</a>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <form class="list-filter card" method="get" action="<?= e(app_url('estudiantes/')) ?>">
        <label>Buscar
            <input type="search" name="q" maxlength="100" value="<?= e($filtros['q']) ?>" placeholder="Nombre, carnet, correo, usuario, teléfono o registro">
        </label>
        <label>Carrera
            <select name="carrera">
                <option value="">Todas</option>
                <?php foreach ($careers as $career): ?>
                    <option value="<?= (int) $career['id_carrera'] ?>" <?= $filtros['carrera'] === (int) $career['id_carrera'] ? 'selected' : '' ?>><?= e($career['nombre_carrera']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Semestre
            <select name="semestre">
                <option value="">Todos</option>
                <?php for ($s = 1; $s <= 12; $s++): ?>
                    <option value="<?= $s ?>" <?= $filtros['semestre'] === $s ? 'selected' : '' ?>><?= $s ?>.º</option>
                <?php endfor; ?>
            </select>
        </label>
        <label>Estado
            <select name="estado">
                <option value="">Todos</option>
                <option value="activo" <?= $filtros['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                <option value="inactivo" <?= $filtros['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </label>
        <label>Tutoría (período activo)
            <select name="tutoria">
                <option value="">Todas</option>
                <?php foreach (Estudiante::FILTROS_TUTORIA as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filtros['tutoria'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="filter-actions">
            <button type="submit">Filtrar</button>
            <?php if ($queryFiltros !== ''): ?><a class="button secondary" href="<?= e(app_url('estudiantes/')) ?>">Limpiar</a><?php endif; ?>
            <span class="table-meta"><?= count($students) ?> resultado<?= count($students) === 1 ? '' : 's' ?></span>
        </div>
    </form>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Estudiante</th><th>Carnet</th><th>Correo</th><th>Carrera</th><th>Semestre</th><th>Registro</th><th>Tutoría</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr data-row>
                        <td><?= e($student['nombre'] . ' ' . $student['apellido']) ?></td>
                        <td><?= e($student['carnet_identidad'] ?? '—') ?></td>
                        <td><?= e($student['correo']) ?></td>
                        <td><?= e($student['nombre_carrera']) ?></td>
                        <td><?= (int) $student['semestre'] ?></td>
                        <td><?= e($student['registro_universitario'] ?: 'Sin registro') ?></td>
                        <td>
                            <?php if ($student['tutoria_grupo'] !== null): ?><?= e($student['tutoria_grupo']) ?>
                            <?php elseif ($student['tutoria_espera'] !== null): ?><?= e($student['tutoria_espera']) ?> <small>(en espera)</small>
                            <?php else: ?><small>—</small><?php endif; ?>
                        </td>
                        <td><span class="status status-<?= e($student['estado']) ?>"><?= e($student['estado']) ?></span></td>
                        <td class="actions">
                            <a href="<?= e(app_url('estudiantes/edit.php?id=' . (int) $student['id_estudiante'])) ?>">Editar</a>
                            <?php $activo = $student['estado'] === 'activo'; ?>
                            <form method="post" action="<?= e(app_url('estudiantes/estado.php')) ?>" onsubmit="return confirm(<?= $activo ? "'Desactivar la cuenta de este estudiante? Conservara su historial academico.'" : "'Reactivar la cuenta de este estudiante?'" ?>);">
                                <input type="hidden" name="id" value="<?= (int) $student['id_estudiante'] ?>">
                                <input type="hidden" name="estado" value="<?= $activo ? 'inactivo' : 'activo' ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="link-button" type="submit"><?= $activo ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                            <form method="post" action="<?= e(app_url('estudiantes/delete.php')) ?>" onsubmit="return confirm('Eliminar este perfil?');">
                                <input type="hidden" name="id" value="<?= (int) $student['id_estudiante'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="link-button" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$students && $queryFiltros !== ""): ?><tr><td colspan="9" class="empty-state">Ningún estudiante coincide con los filtros.</td></tr><?php elseif (!$students): ?><tr><td colspan="9" class="empty-state">No hay estudiantes registrados. Use <strong>Nuevo estudiante</strong>: la cuenta de acceso y el perfil se crean en un solo paso.</td></tr><?php endif; ?>
                
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
