<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Mis tutorías</h1>
            <p>Grupos de apoyo a los que fuiste asignado en el período activo.</p>
        </div>
        <a class="button" href="<?= e(app_url('tutorias/create.php')) ?>">Solicitar apoyo</a>
    </div>

    <?php if (!$periodo): ?>
        <p class="alert" role="alert">No hay un período de tutoría activo.</p>
    <?php else: ?>
        <p class="panel-note">Período: <strong><?= e($periodo['nombre']) ?></strong></p>
        <div class="table-wrapper card">
            <table>
                <thead><tr><th>Materia</th><th>Tutor</th><th>Día</th><th>Horario</th><th>Modalidad</th><th>Aula / Enlace</th><th>Estado</th></tr></thead>
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
                        <tr><td colspan="7" class="empty-state">Aún no tienes tutorías. Usa "Solicitar apoyo" para empezar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($enEspera): ?>
            <h2 style="margin-top:2rem;">⏳ En espera</h2>
            <p class="panel-note">Materias que solicitaste y aún no tienen grupo para ti. No necesitas volver a solicitarlas: cuando un tutor agregue horarios o se abra un grupo, el sistema te asignará automáticamente y te avisará.</p>
            <div class="table-wrapper card">
                <table>
                    <thead><tr><th>Materia</th><th>Solicitado</th><th>Motivo</th><th>Oferta actual</th></tr></thead>
                    <tbody>
                        <?php foreach ($enEspera as $m): ?>
                            <tr>
                                <td><?= e($m['nombre_materia']) ?><?php if ($m['nombre_carrera']): ?> <small><?= e($m['nombre_carrera']) ?></small><?php endif; ?></td>
                                <td><?= e(date('d/m/Y', strtotime((string) $m['espera_desde']))) ?></td>
                                <td><?= e(Demanda::MOTIVOS[$m['motivo_espera']] ?? 'En espera') ?></td>
                                <td>
                                    <?php if ($m['hay_oferta']): ?>
                                        <?= (int) $m['tutores_habilitados'] ?> tutor(es) habilitado(s)<?php if ($m['grupos']): ?>, <?= count($m['grupos']) ?> grupo(s) con cupo<?php endif; ?>
                                        · <a href="<?= e(app_url('tutorias/create.php?carrera=todas')) ?>">Reintentar</a>
                                    <?php else: ?>
                                        Sin tutor · <?= (int) $m['interesados'] ?> interesado(s)
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
