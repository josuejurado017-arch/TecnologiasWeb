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
                <thead><tr><th>Materia</th><th>Tutor</th><th>Día</th><th>Horario</th><th>Modalidad</th><th>Lugar / Enlace</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($inscripciones as $i): ?>
                        <?php $grupoVisual = ['estado' => $i['estado_grupo'], 'cupo_ocupado' => $i['cupo_ocupado'], 'fecha_aprobacion' => $i['fecha_aprobacion']]; ?>
                        <tr>
                            <td><?= e($i['nombre_materia']) ?></td>
                            <td><?= e($i['tutor']) ?></td>
                            <td><?= e($i['dias'] ?: $i['dia_semana']) ?></td>
                            <td><?= e(substr((string) $i['hora_inicio'], 0, 5)) ?> - <?= e(substr((string) $i['hora_fin'], 0, 5)) ?></td>
                            <td><?= e(ucfirst((string) $i['modalidad'])) ?></td>
                            <td>
                                <?php if ($i['estado_grupo'] === 'por_aprobar' || ubicacion_pendiente($i)): ?>
                                    <?= e($i['espacio']) ?> · <small>Ubicación pendiente de confirmación</small>
                                <?php elseif ($i['modalidad'] === 'virtual'): ?>
                                    <a href="<?= e($i['enlace']) ?>" target="_blank" rel="noopener noreferrer">Unirse</a> <small><?= e($i['espacio']) ?></small>
                                <?php else: ?>
                                    <?= e($i['ubicacion']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= estado_grupo_badge($grupoVisual, 'estudiante') ?>
                                <span class="estado-grupo-texto"><?= e(estado_grupo_visual($grupoVisual, 'estudiante')['texto']) ?></span>
                                <?php if ($i['estado_inscripcion'] !== 'inscrito'): ?>
                                    <span class="badge badge-<?= e($i['estado_inscripcion']) ?>">Tu inscripción: <?= e(str_replace('_', ' ', (string) $i['estado_inscripcion'])) ?></span>
                                <?php endif; ?>
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
            <h2 style="margin-top:2rem;"><span aria-hidden="true">📌</span> Interés registrado</h2>
            <p class="panel-note">Materias que solicitaste y aún no tienen grupo. Un grupo se forma cuando se reúnen <strong><?= (int) $periodo['cupo_min_grupo'] ?> estudiantes</strong> en un mismo turno. No necesitas volver a solicitarlas: el sistema te asignará automáticamente y te avisará.</p>
            <div class="table-wrapper card">
                <table>
                    <thead><tr><th>Materia</th><th>Solicitado</th><th>Estado</th><th>Oferta actual</th></tr></thead>
                    <tbody>
                        <?php foreach ($enEspera as $m): ?>
                            <tr>
                                <td><?= e($m['nombre_materia']) ?><?php if ($m['nombre_carrera']): ?> <small><?= e($m['nombre_carrera']) ?></small><?php endif; ?></td>
                                <td><?= e(date('d/m/Y', strtotime((string) $m['espera_desde']))) ?></td>
                                <td>
                                    <?php if ($m['reunidos'] !== null): ?>
                                        <?= interes_badge((int) $m['reunidos'], (int) $periodo['cupo_min_grupo']) ?>
                                        <span class="estado-grupo-texto">Aún no hay suficientes estudiantes en tu turno para formar un grupo.</span>
                                    <?php else: ?>
                                        <?= interes_badge() ?>
                                        <span class="estado-grupo-texto"><?= e(Demanda::MOTIVOS[$m['motivo_espera']] ?? 'En espera') ?></span>
                                    <?php endif; ?>
                                </td>
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
