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
                            <td><?= e($g['dia_semana']) ?></td>
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
        <p class="panel-note">Materias solicitadas sin grupo disponible por falta de tutores u horarios compatibles.</p>
        <div class="table-wrapper card">
            <table>
                <thead><tr><th>Materia</th><th>Solicitudes en espera</th></tr></thead>
                <tbody>
                    <?php foreach ($demanda as $d): ?>
                        <tr><td><?= e($d['nombre_materia']) ?></td><td><?= (int) $d['solicitudes'] ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$demanda): ?>
                        <tr><td colspan="2" class="empty-state">Sin demanda insatisfecha.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
