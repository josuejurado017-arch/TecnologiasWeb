<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Mis grupos</h1>
            <p>Grupos de tutoria que impartes en la campana activa.</p>
        </div>
    </div>

    <?php if (!$periodo): ?>
        <p class="alert" role="alert">No hay una campana de tutorias activa.</p>
    <?php elseif (!$grupos): ?>
        <p class="empty-state">Aun no tienes grupos asignados. Se crearan cuando los estudiantes soliciten tus materias.</p>
    <?php else: ?>
        <p class="panel-note">Campana: <strong><?= e($periodo['nombre']) ?></strong></p>
        <?php foreach ($grupos as $grupo): ?>
            <section class="card" style="margin-bottom:1rem;">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow"><?= e($grupo['dia_semana']) ?> · <?= e(substr((string) $grupo['hora_inicio'], 0, 5)) ?>-<?= e(substr((string) $grupo['hora_fin'], 0, 5)) ?> · <?= e(ucfirst((string) $grupo['modalidad'])) ?></span>
                        <h2><?= e($grupo['nombre_materia']) ?></h2>
                    </div>
                    <span class="badge badge-<?= e($grupo['estado']) ?>"><?= e(ucfirst((string) $grupo['estado'])) ?></span>
                </div>
                <p class="panel-note">Aula: <?= e($grupo['aula']) ?> · Cupo: <?= (int) $grupo['cupo_ocupado'] ?>/<?= (int) $grupo['cupo_max'] ?></p>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>#</th><th>Estudiante</th><th>Correo</th><th>Estado</th></tr></thead>
                        <tbody>
                            <?php $n = 0; foreach (($inscritosPorGrupo[$grupo['id_grupo']] ?? []) as $ins): $n++; ?>
                                <tr>
                                    <td><?= $n ?></td>
                                    <td><?= e($ins['estudiante']) ?></td>
                                    <td><?= e($ins['correo']) ?></td>
                                    <td><span class="badge badge-<?= e($ins['estado']) ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $ins['estado']))) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($inscritosPorGrupo[$grupo['id_grupo']])): ?>
                                <tr><td colspan="4" class="empty-state">Sin inscritos todavia.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <h3 style="margin-top:1rem;">Sesiones</h3>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Fecha</th><th>Estado</th><th>Accion</th></tr></thead>
                        <tbody>
                            <?php foreach (($sesionesPorGrupo[$grupo['id_grupo']] ?? []) as $sesion): ?>
                                <tr>
                                    <td><?= e($sesion['fecha']) ?></td>
                                    <td><span class="badge badge-<?= e($sesion['estado']) ?>"><?= e(ucfirst((string) $sesion['estado'])) ?></span></td>
                                    <td><a href="<?= e(app_url('tutor/asistencia.php?sesion=' . (int) $sesion['id_sesion'])) ?>"><?= $sesion['estado'] === 'realizada' ? 'Ver / editar asistencia' : 'Tomar asistencia' ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sesionesPorGrupo[$grupo['id_grupo']])): ?>
                                <tr><td colspan="3" class="empty-state">Sin sesiones generadas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
