<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Grupos de tutoría</h1>
            <p>El sistema forma un grupo cuando reúne <?= (int) ($periodo['cupo_min_grupo'] ?? 3) ?> estudiantes en un mismo turno; ninguno empieza sin tu aprobación. Revisa primero los <strong>listos para revisión</strong> (<?= Grupo::UMBRAL_GRUPO_NORMAL ?> o más estudiantes) y luego los que están <strong>en formación</strong>.</p>
        </div>
    </div>

    <?php
    $gruposMessages = [
        'cancelled' => 'Grupo cancelado correctamente.',
        'propuesta_enviada' => 'Propuesta enviada. El tutor debe aceptarla en "Mis materias"; te avisaremos cuando responda.',
    ];
    $gmsg = $gruposMessages[$_GET['message'] ?? ''] ?? null;
    $gerr = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
    ?>
    <?php if (!empty($gmsg)): ?><p class="success" role="status"><?= e($gmsg) ?></p><?php endif; ?>
    <?php if (!empty($gerr)): ?><p class="alert" role="alert"><?= e($gerr) ?></p><?php endif; ?>

    <?php if (!$periodo): ?>
        <p class="alert" role="alert">No hay un período de tutoría activo. Activa uno en Períodos de tutoría.</p>
    <?php else: ?>
        <p class="panel-note">Período: <strong><?= e($periodo['nombre']) ?></strong></p>

        <?php
        // Etapas en el orden del flujo (db/035): interes -> formacion -> listo -> confirmado -> en curso -> finalizado.
        $pasos = [
            ['interes', '📌', 'Interés registrado', $interesTotal, 'Estudiantes sin grupo todavía', '#demanda'],
            ['formacion', '🟡', 'En formación', $etapas['formacion'] ?? 0, 'Reuniendo estudiantes', app_url('grupos/?estado=formacion')],
            ['listo', '🟣', 'Listo para revisión', $etapas['listo'] ?? 0, Grupo::UMBRAL_GRUPO_NORMAL . ' o más estudiantes', app_url('grupos/?estado=listo')],
            ['confirmado', '✅', 'Confirmados', $etapas['confirmado'] ?? 0, 'Aprobados, sin iniciar', app_url('grupos/?estado=confirmado')],
            ['en_curso', '▶️', 'En curso', $etapas['en_curso'] ?? 0, 'Sesiones iniciadas', app_url('grupos/?estado=en_curso')],
            ['finalizado', '🔵', 'Finalizados', $etapas['finalizado'] ?? 0, 'Histórico', app_url('grupos/?estado=finalizado')],
        ];
        ?>
        <div class="embudo-grupos" aria-label="Etapas de formación de grupos">
            <?php foreach ($pasos as [$clave, $icono, $nombre, $cantidad, $ayuda, $url]): ?>
                <a href="<?= e($url) ?>" class="<?= $filtro === $clave ? 'is-active' : '' ?>">
                    <span class="estado-grupo estado-grupo--<?= e($clave) ?>"><span aria-hidden="true"><?= $icono ?></span> <?= e($nombre) ?></span>
                    <strong><?= (int) $cantidad ?></strong>
                    <small><?= e($ayuda) ?></small>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($sinTutor): ?>
            <section class="card" id="sin-tutor" style="margin-top:1rem;">
                <div class="section-heading"><div><span class="eyebrow">Antes de formar grupo</span><h2><span aria-hidden="true">📌</span> Materias con demanda y sin tutor</h2></div></div>
                <p class="panel-note">Aún no son grupos: sin tutor no hay turno ni horario. Asígnales un tutor habilitado; él debe aceptar la propuesta y, al aceptarla, el sistema forma los grupos con quienes esperan.</p>
                <div class="embudo-grupos">
                    <?php foreach ($sinTutor as $d): ?>
                        <?php $enviadas = $propuestas[(int) $d['id_materia']] ?? []; ?>
                        <div class="tarjeta-sin-tutor">
                            <span class="estado-grupo estado-grupo--interes"><span aria-hidden="true">📌</span> Sin tutor</span>
                            <strong><?= e($d['nombre_materia']) ?></strong>
                            <small><?= (int) $d['sin_tutor'] ?> estudiante(s) esperando<?= $d['nombre_carrera'] ? ' · ' . e($d['nombre_carrera']) : '' ?></small>
                            <?php if ($enviadas): ?>
                                <small>⏳ Propuesta enviada a <?= e(implode(', ', array_column($enviadas, 'tutor'))) ?></small>
                            <?php endif; ?>
                            <a class="button small <?= $enviadas ? 'secondary' : '' ?>" href="<?= e(app_url('grupos/asignar_tutor.php?materia=' . (int) $d['id_materia'])) ?>"><?= $enviadas ? 'Proponer a otro tutor' : 'Asignar tutor' ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($completos): ?>
            <section class="card" id="cupos-completos" style="margin-top:1rem;">
                <h2>Grupos llenos con demanda pendiente</h2>
                <p class="panel-note">Revisa el turno de los estudiantes en espera. Si necesitan más capacidad, propón otro tutor; deberá aceptar antes de abrirse un segundo grupo.</p>
                <ul class="plain-list">
                    <?php foreach ($completos as $grupoLleno): ?>
                        <li><strong><?= e($grupoLleno['nombre_materia']) ?></strong> · <?= e(TutorMateriaConfig::TURNOS[TutorMateriaConfig::turnoDeHora((string) $grupoLleno['hora_inicio'])]['label'] ?? substr((string) $grupoLleno['hora_inicio'], 0, 5)) ?> · <?= (int) $grupoLleno['cupo_ocupado'] ?>/<?= (int) $grupoLleno['cupo_max'] ?> · <?= (int) $grupoLleno['esperando'] ?> esperando.
                            <a href="<?= e(app_url('grupos/asignar_tutor.php?materia=' . (int) $grupoLleno['id_materia'])) ?>">Proponer otro tutor</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <nav class="filter-tabs" aria-label="Filtrar grupos por etapa" style="margin-top:1rem;">
            <a class="<?= $filtro === null ? 'is-active' : '' ?>" href="<?= e(app_url('grupos/')) ?>">Todos</a>
            <a class="<?= $filtro === 'listo' ? 'is-active' : '' ?>" href="<?= e(app_url('grupos/?estado=listo')) ?>">🟣 Listos para revisión (<?= (int) ($etapas['listo'] ?? 0) ?>)</a>
            <a class="<?= $filtro === 'formacion' ? 'is-active' : '' ?>" href="<?= e(app_url('grupos/?estado=formacion')) ?>">🟡 En formación (<?= (int) ($etapas['formacion'] ?? 0) ?>)</a>
            <a class="<?= $filtro === 'confirmado' ? 'is-active' : '' ?>" href="<?= e(app_url('grupos/?estado=confirmado')) ?>">✅ Confirmados (<?= (int) ($etapas['confirmado'] ?? 0) ?>)</a>
            <a class="<?= $filtro === 'en_curso' ? 'is-active' : '' ?>" href="<?= e(app_url('grupos/?estado=en_curso')) ?>">▶️ En curso (<?= (int) ($etapas['en_curso'] ?? 0) ?>)</a>
            <a class="<?= $filtro === 'finalizado' ? 'is-active' : '' ?>" href="<?= e(app_url('grupos/?estado=finalizado')) ?>">🔵 Finalizados (<?= (int) ($etapas['finalizado'] ?? 0) ?>)</a>
            <a class="<?= $filtro === 'cancelado' ? 'is-active' : '' ?>" href="<?= e(app_url('grupos/?estado=cancelado')) ?>">⚪ No abiertos / cancelados (<?= (int) ($etapas['cancelado'] ?? 0) ?>)</a>
            <a class="<?= $filtro === 'sin_ubicacion' ? 'is-active' : '' ?>" href="<?= e(app_url('grupos/?estado=sin_ubicacion')) ?>">Ubicación pendiente (<?= (int) $sinUbicacion ?>)</a>
        </nav>

        <div class="table-wrapper card">
            <table>
                <thead><tr><th>Materia</th><th>Tutor</th><th>Día</th><th>Horario</th><th>Modalidad</th><th>Espacio / Ubicación</th><th>Cupo</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ($grupos as $g): ?>
                        <tr>
                            <td><?= e($g['nombre_materia']) ?></td>
                            <td><?= e($g['tutor']) ?></td>
                            <td><?= e($g['dias'] ?: $g['dia_semana']) ?></td>
                            <td><?= e(substr((string) $g['hora_inicio'], 0, 5)) ?> - <?= e(substr((string) $g['hora_fin'], 0, 5)) ?></td>
                            <td><?= e(ucfirst((string) $g['modalidad'])) ?></td>
                            <td>
                                <?= e($g['espacio']) ?>
                                <?php if (in_array($g['estado'], Grupo::ESTADOS_VIGENTES, true) && ubicacion_pendiente($g)): ?>
                                    <br><span class="badge badge-warning">Ubicación pendiente</span>
                                <?php elseif ($g['modalidad'] === 'virtual' && $g['enlace']): ?>
                                    <br><a href="<?= e($g['enlace']) ?>" target="_blank" rel="noopener noreferrer">Enlace</a>
                                <?php elseif ($g['ubicacion']): ?>
                                    <br><small><?= e($g['ubicacion']) ?></small>
                                <?php endif; ?>
                                <?php if ($g['enlace_propuesto'] !== null && in_array($g['estado'], Grupo::ESTADOS_VIGENTES, true)): ?>
                                    <br><span class="badge badge-info">Enlace propuesto</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $g['cupo_ocupado'] ?>/<?= (int) $g['cupo_max'] ?></td>
                            <td><?= estado_grupo_badge($g) ?></td>
                            <td class="actions">
                                <a href="<?= e(app_url('grupos/historial.php?grupo=' . (int) $g['id_grupo'])) ?>">Historial</a>
                                <?php if ($g['estado'] === 'por_aprobar'): ?>
                                    <a href="<?= e(app_url('grupos/ubicacion.php?grupo=' . (int) $g['id_grupo'])) ?>"><strong>Revisar</strong></a>
                                <?php else: ?>
                                    <a href="<?= e(app_url('grupos/ubicacion.php?grupo=' . (int) $g['id_grupo'])) ?>">Ubicación</a>
                                <?php endif; ?>
                                <?php if ($g['estado'] !== 'por_aprobar' && $g['estado'] !== 'cancelado' && $g['estado'] !== 'finalizado'): ?>
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
                        <tr><td colspan="9" class="empty-state"><?= in_array($filtro, ['por_aprobar', 'listo', 'formacion'], true) ? 'No hay grupos esperando tu aprobación.' : ($filtro === 'sin_ubicacion' ? 'Todos los grupos vigentes tienen aula o enlace definido.' : 'No hay grupos en este período en esa etapa.') ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h2 style="margin-top:2rem;" id="demanda"><span aria-hidden="true">📌</span> Interés registrado (sin grupo todavía)</h2>
        <p class="panel-note">Estudiantes que pidieron apoyo y aún no tienen grupo, por materia y por qué. <strong>Esperando compañeros</strong>: hay tutor y turno, pero todavía no se reúnen <?= (int) ($periodo['cupo_min_grupo'] ?? 3) ?> estudiantes en ese turno; el grupo se forma solo al llegar al mínimo. <strong>Sin tutor</strong>: nadie habilitado con oferta aprobada para la materia. <strong>Sin horario</strong>: hay tutor, pero ningún turno libre compatible con el estudiante. <strong>Grupo cancelado</strong>: esperan reasignación.</p>
        <div class="table-wrapper card">
            <table>
                <thead><tr><th>Materia</th><th>Carrera</th><th>Interesados</th><th>Esperando compañeros</th><th>Sin tutor</th><th>Sin horario</th><th>Grupo cancelado</th><th>Esperan desde</th></tr></thead>
                <tbody>
                    <?php foreach ($demanda as $d): ?>
                        <tr>
                            <td><?= e($d['nombre_materia']) ?></td>
                            <td><?= e($d['nombre_carrera'] ?? '—') ?></td>
                            <td><strong><?= (int) $d['solicitudes'] ?></strong></td>
                            <td><?php if ((int) $d['esperando'] > 0): ?><?= interes_badge((int) $d['esperando']) ?><?php else: ?>—<?php endif; ?></td>
                            <td><?php if ((int) $d['sin_tutor'] > 0): ?><span class="badge badge-danger"><?= (int) $d['sin_tutor'] ?></span><?php else: ?>—<?php endif; ?></td>
                            <td><?php if ((int) $d['sin_horario'] > 0): ?><span class="badge badge-warning"><?= (int) $d['sin_horario'] ?></span><?php else: ?>—<?php endif; ?></td>
                            <td><?php if ((int) $d['grupo_cancelado'] > 0): ?><span class="badge badge-info"><?= (int) $d['grupo_cancelado'] ?></span><?php else: ?>—<?php endif; ?></td>
                            <td><?= e(date('d/m/Y', strtotime((string) $d['espera_desde']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$demanda): ?>
                        <tr><td colspan="8" class="empty-state">No hay estudiantes esperando grupo.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
