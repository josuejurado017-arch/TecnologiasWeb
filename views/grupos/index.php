<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Grupos de tutoría</h1>
            <p>Supervisión de los grupos generados y la demanda del período activo.</p>
        </div>
    </div>

    <?php
    $gruposMessages = ['cancelled' => 'Grupo cancelado correctamente.'];
    $gmsg = $gruposMessages[$_GET['message'] ?? ''] ?? null;
    $gerr = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
    ?>
    <?php if (!empty($gmsg)): ?><p class="success" role="status"><?= e($gmsg) ?></p><?php endif; ?>
    <?php if (!empty($gerr)): ?><p class="alert" role="alert"><?= e($gerr) ?></p><?php endif; ?>

    <?php if (!$periodo): ?>
        <p class="alert" role="alert">No hay un período de tutoría activo. Activa uno en Períodos de tutoría.</p>
    <?php else: ?>
        <p class="panel-note">Período: <strong><?= e($periodo['nombre']) ?></strong></p>

        <div class="table-wrapper card">
            <table>
                <thead><tr><th>Materia</th><th>Tutor</th><th>Día</th><th>Horario</th><th>Modalidad</th><th>Aula</th><th>Cupo</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ($grupos as $g): ?>
                        <tr>
                            <td><?= e($g['nombre_materia']) ?></td>
                            <td><?= e($g['tutor']) ?></td>
                            <td><?= e($g['dias'] ?: $g['dia_semana']) ?></td>
                            <td><?= e(substr((string) $g['hora_inicio'], 0, 5)) ?> - <?= e(substr((string) $g['hora_fin'], 0, 5)) ?></td>
                            <td><?= e(ucfirst((string) $g['modalidad'])) ?></td>
                            <td><?= e($g['aula']) ?></td>
                            <td><?= (int) $g['cupo_ocupado'] ?>/<?= (int) $g['cupo_max'] ?></td>
                            <td><span class="badge badge-<?= e($g['estado']) ?>"><?= e(ucfirst((string) $g['estado'])) ?></span></td>
                            <td class="actions">
                                <a href="<?= e(app_url('grupos/historial.php?grupo=' . (int) $g['id_grupo'])) ?>">Historial</a>
                                <?php if ($g['estado'] !== 'cancelado' && $g['estado'] !== 'finalizado'): ?>
                                    <form method="post" action="<?= e(app_url('grupos/cancel.php')) ?>" onsubmit="return confirm('¿Cancelar este grupo? Se avisará a los inscritos.');">
                                        <input type="hidden" name="id" value="<?= (int) $g['id_grupo'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input class="inline-response" name="motivo" placeholder="Motivo" required maxlength="300">
                                        <button class="link-button" type="submit">Cancelar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$grupos): ?>
                        <tr><td colspan="9" class="empty-state">Aún no se han generado grupos en este período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h2 style="margin-top:2rem;">Demanda insatisfecha</h2>
        <p class="panel-note">Estudiantes en espera por materia y por qué. <strong>Sin tutor</strong>: nadie habilitado con horarios configurados para la materia (habilitar o reclutar tutor). <strong>Sin horario</strong>: hay tutor pero ningún turno/aula compatible (el tutor puede ampliar los turnos de la materia en Mis materias). <strong>Grupo cancelado</strong>: esperan reasignación. El sistema reintenta la asignación automáticamente cuando cambia la oferta.</p>
        <div class="table-wrapper card">
            <table>
                <thead><tr><th>Materia</th><th>Carrera</th><th>En espera</th><th>Sin tutor</th><th>Sin horario</th><th>Grupo cancelado</th><th>Esperan desde</th></tr></thead>
                <tbody>
                    <?php foreach ($demanda as $d): ?>
                        <tr>
                            <td><?= e($d['nombre_materia']) ?></td>
                            <td><?= e($d['nombre_carrera'] ?? '—') ?></td>
                            <td><strong><?= (int) $d['solicitudes'] ?></strong></td>
                            <td><?php if ((int) $d['sin_tutor'] > 0): ?><span class="badge badge-danger"><?= (int) $d['sin_tutor'] ?></span><?php else: ?>—<?php endif; ?></td>
                            <td><?php if ((int) $d['sin_horario'] > 0): ?><span class="badge badge-warning"><?= (int) $d['sin_horario'] ?></span><?php else: ?>—<?php endif; ?></td>
                            <td><?php if ((int) $d['grupo_cancelado'] > 0): ?><span class="badge badge-info"><?= (int) $d['grupo_cancelado'] ?></span><?php else: ?>—<?php endif; ?></td>
                            <td><?= e(date('d/m/Y', strtotime((string) $d['espera_desde']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$demanda): ?>
                        <tr><td colspan="7" class="empty-state">Sin demanda insatisfecha.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
