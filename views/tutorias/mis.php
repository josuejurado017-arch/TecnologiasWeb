<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Mis tutorias</h1>
            <p>Grupos de apoyo a los que fuiste asignado en la campana activa.</p>
        </div>
        <a class="button" href="<?= e(app_url('tutorias/create.php')) ?>">Solicitar apoyo</a>
    </div>

    <?php if (!$periodo): ?>
        <p class="alert" role="alert">No hay una campana de tutorias activa.</p>
    <?php else: ?>
        <p class="panel-note">Campana: <strong><?= e($periodo['nombre']) ?></strong></p>
        <div class="table-wrapper card">
            <table>
                <thead><tr><th>Materia</th><th>Tutor</th><th>Dia</th><th>Horario</th><th>Modalidad</th><th>Aula / Enlace</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($inscripciones as $i): ?>
                        <tr>
                            <td><?= e($i['nombre_materia']) ?></td>
                            <td><?= e($i['tutor']) ?></td>
                            <td><?= e($i['dia_semana']) ?></td>
                            <td><?= e(substr((string) $i['hora_inicio'], 0, 5)) ?> - <?= e(substr((string) $i['hora_fin'], 0, 5)) ?></td>
                            <td><?= e(ucfirst((string) $i['modalidad'])) ?></td>
                            <td>
                                <?php if ($i['modalidad'] === 'virtual' && !empty($i['enlace'])): ?>
                                    <a href="<?= e($i['enlace']) ?>" target="_blank" rel="noopener">Unirse</a>
                                <?php else: ?>
                                    <?= e($i['aula']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= e($i['estado_inscripcion']) ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $i['estado_inscripcion']))) ?></span>
                                <small>Grupo: <?= e(ucfirst((string) $i['estado_grupo'])) ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$inscripciones): ?>
                        <tr><td colspan="7" class="empty-state">Aun no tienes tutorias. Usa "Solicitar apoyo" para empezar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
