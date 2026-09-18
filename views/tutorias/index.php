<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading"><div><h1>Tutorias</h1><p>Solicitudes y sesiones de apoyo academico.</p></div><div class="page-heading-actions"><?php if ($role === 'estudiante'): ?><a class="button" href="<?= e(app_url('tutorias/create.php')) ?>">Solicitar tutoria</a><a class="button secondary" href="<?= e(app_url('tutorias/especial.php')) ?>">Horario especial</a><?php elseif (in_array($role, ['administrador', 'tutor'], true)): ?><a class="button" href="<?= e(app_url('tutorias/especiales.php')) ?>">Solicitudes especiales</a><?php endif; ?></div></div>
    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
    <section class="card filter-card">
        <form method="get" action="<?= e(app_url('tutorias/')) ?>">
            <div class="form-grid">
                <div><label for="filter_materia">Materia</label><select id="filter_materia" name="id_materia"><option value="">Todas</option><?php foreach ($filterOptions as $option): ?><option value="<?= (int) $option['id_materia'] ?>" <?= (string) $filters['id_materia'] === (string) $option['id_materia'] ? 'selected' : '' ?>><?= e($option['nombre_materia']) ?></option><?php endforeach; ?></select></div>
                <div><label for="filter_estado">Estado</label><select id="filter_estado" name="estado"><option value="">Todos</option><?php foreach (['pendiente', 'confirmada', 'realizada', 'cancelada'] as $status): ?><option value="<?= e($status) ?>" <?= $filters['estado'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
                <div><label for="fecha_desde">Desde</label><input id="fecha_desde" name="fecha_desde" type="date" value="<?= e($filters['fecha_desde']) ?>"></div>
                <div><label for="fecha_hasta">Hasta</label><input id="fecha_hasta" name="fecha_hasta" type="date" value="<?= e($filters['fecha_hasta']) ?>"></div>
            </div>
            <button type="submit">Filtrar</button> <a class="button secondary" href="<?= e(app_url('tutorias/')) ?>">Limpiar</a>
        </form>
    </section>
    <div class="table-wrapper card"><table><thead><tr><th>Fecha</th><th>Materia</th><th>Estudiante</th><th>Tutor</th><th>Horario</th><th>Modalidad</th><th>Estado</th><th>Asistencia</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($tutorias as $tutoria): ?><tr><td><?= e($tutoria['fecha']) ?></td><td><?= e($tutoria['nombre_materia']) ?></td><td><?= e($tutoria['estudiante']) ?></td><td><?= e($tutoria['tutor']) ?></td><td><?= e(substr($tutoria['hora_inicio'], 0, 5) . ' - ' . substr($tutoria['hora_fin'], 0, 5)) ?></td><td><?= e($tutoria['modalidad']) ?></td><td><span class="status status-<?= e($tutoria['estado']) ?>"><?= e($tutoria['estado']) ?></span><?php if ($tutoria['estado'] === 'cancelada' && !empty($tutoria['motivo_cancelacion'])): ?><small class="table-note"><?= e($tutoria['motivo_cancelacion']) ?></small><?php endif; ?></td><td><span class="status status-<?= e($tutoria['asistencia']) ?>"><?= e(str_replace('_', ' ', $tutoria['asistencia'])) ?></span></td><td class="actions">
            <a href="<?= e(app_url('tutorias/history.php?id=' . (int) $tutoria['id_tutoria'])) ?>">Historial</a>
            <?php if (in_array($tutoria['estado'], ['pendiente', 'confirmada'], true)): ?><a href="<?= e(app_url('tutorias/reschedule.php?id=' . (int) $tutoria['id_tutoria'])) ?>">Reprogramar</a><?php endif; ?>
            <?php if ($role === 'estudiante' && $tutoria['estado'] === 'pendiente'): ?><form method="post" action="<?= e(app_url('tutorias/status.php')) ?>" data-cancel-form><input type="hidden" name="id" value="<?= (int) $tutoria['id_tutoria'] ?>"><input type="hidden" name="estado" value="cancelada"><input type="hidden" name="motivo" data-cancel-reason><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="link-button" type="submit">Cancelar</button></form><?php endif; ?>
            <?php if (in_array($role, ['administrador', 'tutor'], true) && $tutoria['estado'] === 'pendiente'): ?><form method="post" action="<?= e(app_url('tutorias/status.php')) ?>"><input type="hidden" name="id" value="<?= (int) $tutoria['id_tutoria'] ?>"><input type="hidden" name="estado" value="confirmada"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="link-button" type="submit">Confirmar</button></form><form method="post" action="<?= e(app_url('tutorias/status.php')) ?>" data-cancel-form><input type="hidden" name="id" value="<?= (int) $tutoria['id_tutoria'] ?>"><input type="hidden" name="estado" value="cancelada"><input type="hidden" name="motivo" data-cancel-reason><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="link-button" type="submit">Cancelar</button></form><?php elseif (in_array($role, ['administrador', 'tutor'], true) && $tutoria['estado'] === 'confirmada'): ?><form method="post" action="<?= e(app_url('tutorias/status.php')) ?>"><input type="hidden" name="id" value="<?= (int) $tutoria['id_tutoria'] ?>"><input type="hidden" name="estado" value="realizada"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="link-button" type="submit">Marcar realizada</button></form><?php endif; ?>
            <?php if (in_array($role, ['administrador', 'tutor'], true) && $tutoria['estado'] === 'realizada'): ?><a href="<?= e(app_url('tutorias/attendance.php?id=' . (int) $tutoria['id_tutoria'])) ?>">Asistencia</a><?php endif; ?>
        </td></tr><?php endforeach; ?>
        <?php if (!$tutorias): ?><tr><td colspan="9">No hay tutorias para mostrar.</td></tr><?php endif; ?>
    </tbody></table></div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
