<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Mis grupos</h1>
            <p>Grupos de tutoría que impartes en el período activo.</p>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <?php if (!empty($divisiones)): ?>
        <section class="card" id="divisiones" style="margin-bottom:1rem;">
            <h2>Propuestas de la coordinación</h2>
            <?php foreach ($divisiones as $d): ?>
                <?php $g = $d['grupo']; $plan = $d['plan']; $nuevos = count($plan['pasan']) + count($plan['desde_espera']); ?>
                <article class="tarjeta-sin-tutor" style="margin-bottom:.75rem;">
                    <strong>Tomar la mitad del grupo de <?= e($g['nombre_materia']) ?></strong>
                    <small><?= e(implode('/', $d['dias'])) ?> · <?= e(substr((string) $g['hora_inicio'], 0, 5)) ?>-<?= e(substr((string) $g['hora_fin'], 0, 5)) ?> · <?= e(ucfirst((string) $g['modalidad'])) ?> · hoy lo dicta <?= e($g['tutor']) ?></small>
                    <?php if ($plan['error'] !== null): ?>
                        <small class="materia-aviso"><?= e($plan['error']) ?></small>
                    <?php else: ?>
                        <small>Tendrías <strong><?= $nuevos ?></strong> estudiante(s): <?= count($plan['pasan']) ?> que pasan del grupo actual y <?= count($plan['desde_espera']) ?> que estaban en espera. Se confirma al aceptar.</small>
                    <?php endif; ?>
                    <form method="post" action="<?= e(app_url('tutor/division.php')) ?>" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id_division" value="<?= (int) $d['id_division'] ?>">
                        <input type="hidden" name="respuesta" value="aceptar">
                        <button type="submit" onclick="return confirm('¿Aceptar? Se creará tu grupo y se trasladarán los estudiantes.');">Aceptar</button>
                    </form>
                    <details>
                        <summary>Rechazar</summary>
                        <form method="post" action="<?= e(app_url('tutor/division.php')) ?>" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id_division" value="<?= (int) $d['id_division'] ?>">
                            <input type="hidden" name="respuesta" value="rechazar">
                            <input name="motivo" required minlength="10" maxlength="300" placeholder="Motivo (ej. no tengo disponibilidad ese turno)" aria-label="Motivo del rechazo">
                            <button type="submit" class="secondary">Rechazar</button>
                        </form>
                    </details>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if (!$periodo): ?>
        <p class="alert" role="alert">No hay un período de tutoría activo.</p>
    <?php elseif (!$grupos): ?>
        <p class="empty-state">Aún no tienes grupos asignados. Se crearán cuando los estudiantes soliciten tus materias.</p>
    <?php else: ?>
        <p class="panel-note">Período: <strong><?= e($periodo['nombre']) ?></strong></p>
        <?php foreach ($grupos as $grupo): ?>
            <section class="card" style="margin-bottom:1rem;" id="grupo-<?= (int) $grupo['id_grupo'] ?>">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow"><?= e($grupo['dias'] ?: $grupo['dia_semana']) ?> · <?= e(substr((string) $grupo['hora_inicio'], 0, 5)) ?>-<?= e(substr((string) $grupo['hora_fin'], 0, 5)) ?> · <?= e(ucfirst((string) $grupo['modalidad'])) ?></span>
                        <h2><?= e($grupo['nombre_materia']) ?></h2>
                    </div>
                    <?= estado_grupo_badge($grupo, 'tutor') ?>
                </div>
                <p class="estado-grupo-texto"><?= e(estado_grupo_visual($grupo, 'tutor')['texto']) ?></p>
                <?php $vigente = in_array($grupo['estado'], Grupo::ESTADOS_VIGENTES, true); ?>
                <p class="panel-note">
                    <?= e($grupo['espacio']) ?> ·
                    <?php if ($vigente && ubicacion_pendiente($grupo)): ?>
                        <span class="badge badge-warning">Ubicación pendiente de confirmación</span>
                    <?php elseif ($grupo['modalidad'] === 'virtual' && $grupo['enlace']): ?>
                        <a href="<?= e($grupo['enlace']) ?>" target="_blank" rel="noopener noreferrer">Enlace de la reunión</a>
                    <?php else: ?>
                        <?= e($grupo['ubicacion'] ?? '—') ?>
                    <?php endif; ?>
                    · Cupo: <?= (int) $grupo['cupo_ocupado'] ?>/<?= (int) $grupo['cupo_max'] ?>
                </p>
                <?php if ($vigente && $grupo['modalidad'] === 'virtual'): ?>
                    <?php if ($grupo['enlace_propuesto'] !== null): ?>
                        <p class="form-hint">Propusiste: <?= e($grupo['espacio_propuesto'] ?? '') ?> · <?= e($grupo['enlace_propuesto']) ?> — pendiente de revisión de la coordinación.</p>
                    <?php endif; ?>
                    <details>
                        <summary><?= $grupo['enlace_propuesto'] !== null ? 'Cambiar mi propuesta' : ($grupo['enlace'] ? 'Proponer otro enlace' : 'Proponer enlace de la reunión') ?></summary>
                        <form method="post" action="<?= e(app_url('tutor/proponer_enlace.php')) ?>" class="config-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id_grupo" value="<?= (int) $grupo['id_grupo'] ?>">
                            <label for="enlace_<?= (int) $grupo['id_grupo'] ?>">Enlace de la reunión</label>
                            <input id="enlace_<?= (int) $grupo['id_grupo'] ?>" name="enlace" type="url" required maxlength="300" placeholder="https://meet.google.com/…">
                            <p class="form-hint">La plataforma (Meet, Zoom o Teams) se reconoce por el enlace. La coordinación lo aprueba, corrige o reemplaza antes de que lo vean los estudiantes.</p>
                            <button type="submit">Enviar propuesta</button>
                        </form>
                    </details>
                <?php endif; ?>
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
                                <tr><td colspan="3" class="empty-state"><?= $grupo['estado'] === 'por_aprobar' ? 'El grupo espera el visto bueno de la coordinación. Las sesiones se generan al aprobarlo.' : 'Sin sesiones generadas.' ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
