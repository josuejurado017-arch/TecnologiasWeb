<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container"><div class="page-heading"><div><h1>Solicitudes de horario especial</h1><p>Revisa propuestas fuera de la disponibilidad publicada.</p></div></div>
    <?php if (!empty($_GET['error'])): ?><p class="alert" role="alert"><?= e($_GET['error']) ?></p><?php endif; ?>
    <?php if (($_GET['message'] ?? '') === 'updated'): ?><p class="success" role="status">Solicitud actualizada correctamente.</p><?php endif; ?>
    <div class="table-wrapper card"><table><thead><tr><th>Fecha propuesta</th><th>Materia</th><th>Estudiante</th><th>Tutor</th><th>Horario</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($requests as $request): ?><tr><td><?= e($request['fecha_propuesta']) ?></td><td><?= e($request['nombre_materia']) ?></td><td><?= e($request['estudiante']) ?></td><td><?= e($request['tutor']) ?></td><td><?= e(substr($request['hora_inicio'], 0, 5) . ' - ' . substr($request['hora_fin'], 0, 5)) ?></td><td><span class="status status-<?= e($request['estado'] === 'convertida' ? 'confirmada' : $request['estado']) ?>"><?= e($request['estado']) ?></span></td><td class="actions">
            <?php if ($request['estado'] === 'pendiente'): ?><form method="post" action="<?= e(app_url('tutorias/special_status.php')) ?>"><input type="hidden" name="id" value="<?= (int) $request['id_solicitud'] ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="respuesta" value="Aprobada por el tutor."><button class="link-button" type="submit">Aprobar</button></form><form method="post" action="<?= e(app_url('tutorias/special_status.php')) ?>"><input type="hidden" name="id" value="<?= (int) $request['id_solicitud'] ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input class="inline-response" name="respuesta" placeholder="Motivo del rechazo" required maxlength="500"><button class="link-button" type="submit">Rechazar</button></form><?php else: ?><?= e($request['respuesta_tutor'] ?: 'Sin respuesta') ?><?php endif; ?>
        </td></tr><?php endforeach; ?>
        <?php if (!$requests): ?><tr><td colspan="7">No hay solicitudes especiales.</td></tr><?php endif; ?>
    </tbody></table></div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
