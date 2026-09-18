<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading"><div><span class="eyebrow">Analitica institucional</span><h1>Reportes de tutorias</h1><p>Consulta actividad por tutor, estudiante, materia, fecha y estado.</p></div><a class="button" href="<?= e($exportUrl) ?>">Descargar CSV</a></div>
    <section class="card filter-card">
        <form method="get" action="<?= e(app_url('reportes/tutorias.php')) ?>">
            <div class="form-grid">
                <div><label for="id_tutor">Tutor</label><select id="id_tutor" name="id_tutor"><option value="">Todos</option><?php foreach ($options['tutors'] as $option): ?><option value="<?= (int) $option['id_tutor'] ?>" <?= (string) $filters['id_tutor'] === (string) $option['id_tutor'] ? 'selected' : '' ?>><?= e($option['nombre']) ?></option><?php endforeach; ?></select></div>
                <div><label for="id_estudiante">Estudiante</label><select id="id_estudiante" name="id_estudiante"><option value="">Todos</option><?php foreach ($options['students'] as $option): ?><option value="<?= (int) $option['id_estudiante'] ?>" <?= (string) $filters['id_estudiante'] === (string) $option['id_estudiante'] ? 'selected' : '' ?>><?= e($option['nombre']) ?></option><?php endforeach; ?></select></div>
                <div><label for="id_materia">Materia</label><select id="id_materia" name="id_materia"><option value="">Todas</option><?php foreach ($options['subjects'] as $option): ?><option value="<?= (int) $option['id_materia'] ?>" <?= (string) $filters['id_materia'] === (string) $option['id_materia'] ? 'selected' : '' ?>><?= e($option['nombre']) ?></option><?php endforeach; ?></select></div>
                <div><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option><?php foreach (['pendiente', 'confirmada', 'realizada', 'cancelada'] as $status): ?><option value="<?= e($status) ?>" <?= $filters['estado'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
                <div><label for="fecha_desde">Desde</label><input id="fecha_desde" name="fecha_desde" type="date" value="<?= e($filters['fecha_desde']) ?>"></div>
                <div><label for="fecha_hasta">Hasta</label><input id="fecha_hasta" name="fecha_hasta" type="date" value="<?= e($filters['fecha_hasta']) ?>"></div>
            </div>
            <button type="submit">Aplicar filtros</button><a class="button secondary" href="<?= e(app_url('reportes/tutorias.php')) ?>">Limpiar</a>
        </form>
    </section>

    <section class="report-stat-grid" aria-label="Resumen del reporte">
        <article class="stat-card"><span class="stat-label">Total</span><strong class="stat-value"><?= $summary['total'] ?></strong></article>
        <article class="stat-card"><span class="stat-label">Pendientes</span><strong class="stat-value"><?= $summary['pendiente'] ?></strong></article>
        <article class="stat-card"><span class="stat-label">Realizadas</span><strong class="stat-value"><?= $summary['realizada'] ?></strong></article>
        <article class="stat-card"><span class="stat-label">Canceladas</span><strong class="stat-value"><?= $summary['cancelada'] ?></strong></article>
        <article class="stat-card"><span class="stat-label">Asistieron</span><strong class="stat-value"><?= $summary['asistio'] ?></strong></article>
    </section>

    <div class="table-wrapper card"><table><thead><tr><th>Fecha</th><th>Materia</th><th>Estudiante</th><th>Tutor</th><th>Estado</th><th>Asistencia</th><th>Modalidad</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr><td><?= e($row['fecha']) ?></td><td><?= e($row['nombre_materia']) ?></td><td><?= e($row['estudiante']) ?></td><td><?= e($row['tutor']) ?></td><td><span class="status status-<?= e($row['estado']) ?>"><?= e($row['estado']) ?></span></td><td><?= e(str_replace('_', ' ', $row['asistencia'])) ?></td><td><?= e($row['modalidad']) ?></td></tr><?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7">No hay registros con los filtros seleccionados.</td></tr><?php endif; ?>
    </tbody></table></div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
