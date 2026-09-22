<?php require __DIR__ . '/../layouts/header.php'; ?>

<?php
$resultLabels = [
    'asignado' => ['Asignado a un grupo', 'success'],
    'grupo_creado' => ['Grupo creado y asignado', 'success'],
    'ya_solicitada' => ['Ya solicitada', 'info'],
    'lista_espera' => ['En lista de espera', 'warning'],
    'interes_registrado' => ['Interés registrado', 'info'],
    'espera_retirada' => ['Solicitud retirada', 'info'],
];

// Estado de oferta -> [icono, etiqueta, tono visual]
$stateMeta = [
    OfertaMateria::ESTADO_DISPONIBLE => ['✅', 'Grupo disponible', 'ok'],
    OfertaMateria::ESTADO_FORMACION => ['✅', 'Grupo en formación', 'forming'],
    OfertaMateria::ESTADO_POR_ABRIR => ['⚠', 'Grupo por abrir', 'pending'],
    OfertaMateria::ESTADO_EN_ESPERA => ['⏳', 'En espera', 'waiting'],
    OfertaMateria::ESTADO_SIN_TUTOR => ['❌', 'Sin tutor asignado', 'none'],
];

$turnoLabels = array_map(static fn (array $t): string => $t['label'], TutorMateriaConfig::TURNOS);
$motivoLabels = [
    Demanda::MOTIVO_SIN_TUTOR => 'aún no hay tutor habilitado',
    Demanda::MOTIVO_SIN_HORARIO => 'no hubo horario compatible con tu agenda ni aula libre',
    Demanda::MOTIVO_GRUPO_CANCELADO => 'tu grupo fue cancelado; esperas reasignación',
];
$hora = static fn ($h): string => substr((string) $h, 0, 5);
$plural = static fn (int $n, string $one, string $many): string => $n . ' ' . ($n === 1 ? $one : $many);
$selectable = array_filter($subjects, static fn (array $s): bool => in_array($s['estado'], [OfertaMateria::ESTADO_DISPONIBLE, OfertaMateria::ESTADO_FORMACION, OfertaMateria::ESTADO_POR_ABRIR], true));
$carreraParam = $showAll ? 'todas' : 'mia';
?>
<main class="container narrow-wide">
    <section class="card">
        <h1>Solicitar apoyo académico</h1>
        <p class="form-intro">Aquí ves qué materias tienen apoyo, en qué estado está cada grupo y qué pasará al solicitar. El sistema asigna automáticamente el grupo compatible con tu horario: <strong>no eliges tutor</strong>.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if (!$periodo): ?>
            <p class="alert" role="alert">No hay un período de tutoría activo. Vuelve cuando el administrador active uno.</p>
        <?php else: ?>
            <p class="panel-note">Período activo: <strong><?= e($periodo['nombre']) ?></strong> (<?= e($periodo['fecha_inicio']) ?> al <?= e($periodo['fecha_fin']) ?>) · Un grupo se confirma al alcanzar <?= (int) $periodo['cupo_min_grupo'] ?> estudiantes.</p>
        <?php endif; ?>

        <?php if ($results !== null): ?>
            <div class="result-panel" role="status">
                <h2>Resultado</h2>
                <ul class="result-list">
                    <?php foreach ($results as $r): ?>
                        <?php [$label, $tone] = $resultLabels[$r['resultado']] ?? ['Procesado', 'info']; ?>
                        <li>
                            <span class="badge badge-<?= e($tone) ?>"><?= e($label) ?></span>
                            <strong><?= e($r['nombre']) ?></strong>
                            <small><?= e($r['detalle']) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a class="button" href="<?= e(app_url('mis-tutorias/')) ?>">Ver mis tutorías</a>
            </div>
        <?php endif; ?>

        <?php if ($periodo && $studentId): ?>
            <div class="offer-toolbar">
                <nav class="segmented" aria-label="Filtro de carrera">
                    <a class="<?= $showAll ? '' : 'is-active' ?>" href="<?= e(app_url('tutorias/create.php')) ?>" <?= $career === null ? 'aria-disabled="true"' : '' ?>>Mi carrera<?php if ($career): ?> <small>(<?= e($career['nombre_carrera']) ?>)</small><?php endif; ?></a>
                    <a class="<?= $showAll ? 'is-active' : '' ?>" href="<?= e(app_url('tutorias/create.php?carrera=todas')) ?>">Todas</a>
                </nav>
                <p class="offer-summary">
                    <span><b><?= (int) ($summary[OfertaMateria::ESTADO_DISPONIBLE] + $summary[OfertaMateria::ESTADO_FORMACION]) ?></b> con grupo</span>
                    <span><b><?= (int) $summary[OfertaMateria::ESTADO_POR_ABRIR] ?></b> por abrir</span>
                    <span><b><?= (int) $summary[OfertaMateria::ESTADO_SIN_TUTOR] ?></b> sin tutor</span>
                    <span><b><?= (int) $summary[OfertaMateria::ESTADO_EN_ESPERA] ?></b> en espera</span>
                </p>
            </div>

            <?php if (!$subjects): ?>
                <p class="empty-state">No hay materias para mostrar<?= $showAll ? '' : ' en tu carrera' ?>. <?php if (!$showAll): ?><a href="<?= e(app_url('tutorias/create.php?carrera=todas')) ?>">Ver todas las materias</a>.<?php endif; ?></p>
            <?php else: ?>
                <form method="post" action="<?= e(app_url('tutorias/create.php')) ?>" id="form-solicitar">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="carrera" value="<?= e($carreraParam) ?>">
                    <input type="hidden" name="accion" value="solicitar">

                    <ul class="offer-list">
                        <?php foreach ($subjects as $s): ?>
                            <?php [$icon, $stateLabel, $tone] = $stateMeta[$s['estado']]; ?>
                            <?php $isSelectable = in_array($s['estado'], [OfertaMateria::ESTADO_DISPONIBLE, OfertaMateria::ESTADO_FORMACION, OfertaMateria::ESTADO_POR_ABRIR], true); ?>
                            <?php $pref = $s['preferencias']; $firstGroup = $s['grupos'][0] ?? null; ?>
                            <li class="offer-card offer-<?= e($tone) ?>">
                                <div class="offer-head">
                                    <span class="offer-state"><span aria-hidden="true"><?= $icon ?></span> <?= e($stateLabel) ?><?php if ($s['estado'] === OfertaMateria::ESTADO_FORMACION && $s['faltan_confirmar'] !== null): ?> · faltan <?= (int) $s['faltan_confirmar'] ?> para confirmar<?php endif; ?></span>
                                    <?php if ($isSelectable): ?>
                                        <label class="offer-select">
                                            <input type="checkbox" name="materias[]" value="<?= (int) $s['id_materia'] ?>">
                                            <span>Solicitar</span>
                                        </label>
                                    <?php endif; ?>
                                </div>

                                <h3 class="offer-title"><?= e($s['nombre_materia']) ?><?php if ($s['nombre_carrera']): ?> <small><?= e($s['nombre_carrera']) ?></small><?php endif; ?></h3>

                                <?php if ($s['estado'] === OfertaMateria::ESTADO_DISPONIBLE || $s['estado'] === OfertaMateria::ESTADO_FORMACION): ?>
                                    <p class="offer-key">
                                        <?= e($firstGroup['dia_semana']) ?> <?= e($hora($firstGroup['hora_inicio'])) ?>–<?= e($hora($firstGroup['hora_fin'])) ?>
                                        · <?= e(ucfirst((string) $firstGroup['modalidad'])) ?>
                                        · <?= e($firstGroup['aula']) ?>
                                        · <span class="offer-seats <?= $s['cupos_libres'] <= 3 ? 'is-low' : '' ?>"><?= e($plural($s['cupos_libres'], 'cupo', 'cupos')) ?><?= $s['cupos_libres'] <= 3 ? ' · últimos' : '' ?></span>
                                        <?php if (count($s['grupos']) > 1): ?> · <?= count($s['grupos']) ?> grupos<?php endif; ?>
                                    </p>
                                    <details class="offer-details">
                                        <summary>Ver detalle</summary>
                                        <table class="offer-groups">
                                            <thead><tr><th>Tutor</th><th>Horario</th><th>Modalidad</th><th>Aula</th><th>Cupos</th><th>Estado</th></tr></thead>
                                            <tbody>
                                                <?php foreach ($s['grupos'] as $g): ?>
                                                    <tr>
                                                        <td><?= e($g['tutor']) ?></td>
                                                        <td><?= e($g['dia_semana']) ?> <?= e($hora($g['hora_inicio'])) ?>–<?= e($hora($g['hora_fin'])) ?></td>
                                                        <td><?= e(ucfirst((string) $g['modalidad'])) ?></td>
                                                        <td><?= e($g['aula']) ?></td>
                                                        <td><?= (int) $g['cupo_max'] - (int) $g['cupo_ocupado'] ?> de <?= (int) $g['cupo_max'] ?></td>
                                                        <td><?= $g['estado'] === 'confirmado' ? 'Confirmado' : 'En formación (' . (int) $g['cupo_ocupado'] . '/' . (int) $periodo['cupo_min_grupo'] . ')' ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <p class="offer-hint">Al solicitar, el sistema te ubica en el grupo con cupo que no choque con tus otras tutorías. Si ninguno es compatible, intentará abrir uno nuevo o te dejará en espera. <?php if (count($s['grupos']) === 1): ?>El horario mostrado es el del único grupo con cupo.<?php endif; ?></p>
                                    </details>

                                <?php elseif ($s['estado'] === OfertaMateria::ESTADO_POR_ABRIR): ?>
                                    <p class="offer-key">
                                        <?= e($plural($s['tutores_habilitados'], 'tutor habilitado', 'tutores habilitados')) ?>
                                        <?php if ($pref['turnos']): ?> · Turnos: <?= e(implode(', ', array_map(static fn (string $t): string => $turnoLabels[$t] ?? $t, $pref['turnos']))) ?><?php if ($pref['sabados']): ?> y sábados<?php endif; ?><?php endif; ?>
                                        <?php if ($pref['modalidades']): ?> · <?= e(in_array('ambas', $pref['modalidades'], true) || count($pref['modalidades']) > 1 ? 'Presencial o virtual' : ucfirst($pref['modalidades'][0])) ?><?php else: ?> · Presencial o virtual (según aula asignada)<?php endif; ?>
                                        · Hasta <?= (int) $periodo['cupo_max_default'] ?> cupos
                                    </p>
                                    <details class="offer-details">
                                        <summary>¿Qué pasa al solicitar?</summary>
                                        <p class="offer-hint">Aún no hay un grupo abierto. Al solicitar, el sistema creará un grupo con el primer horario compatible entre tu agenda, los horarios que los tutores habilitados configuraron para la materia y un aula libre. Verás tutor, horario y aula en el resultado. El grupo se confirma cuando alcance <?= (int) $periodo['cupo_min_grupo'] ?> estudiantes.</p>
                                        <?php if ($s['interesados'] > 0): ?><p class="offer-hint"><?= e($plural($s['interesados'], 'estudiante ya está', 'estudiantes ya están')) ?> en espera en esta materia.</p><?php endif; ?>
                                    </details>

                                <?php elseif ($s['estado'] === OfertaMateria::ESTADO_EN_ESPERA): ?>
                                    <p class="offer-key">
                                        Solicitado el <?= e(date('d/m/Y', strtotime((string) $s['espera_desde']))) ?>
                                        · Motivo: <?= e($motivoLabels[$s['motivo_espera']] ?? 'en espera') ?>
                                    </p>
                                    <details class="offer-details">
                                        <summary>¿Qué sigue?</summary>
                                        <p class="offer-hint">
                                            <?php if (!$s['hay_oferta']): ?>
                                                Tu interés cuenta como demanda para que la universidad asigne un tutor. En cuanto un tutor se habilite, el sistema intentará asignarte automáticamente y te avisará. No necesitas volver a solicitarla.
                                            <?php else: ?>
                                                Hoy hay <?= e($plural($s['tutores_habilitados'], 'tutor habilitado', 'tutores habilitados')) ?><?php if ($s['grupos']): ?> y <?= e($plural(count($s['grupos']), 'grupo con cupo', 'grupos con cupo')) ?><?php endif; ?>. El sistema reintenta solo cuando cambia la oferta; si tu agenda cambió, puedes reintentar ahora.
                                            <?php endif; ?>
                                        </p>
                                        <div class="offer-actions">
                                            <?php if ($s['hay_oferta']): ?>
                                                <button type="submit" class="small" form="form-accion-<?= (int) $s['id_materia'] ?>" name="accion" value="reintentar">Reintentar asignación</button>
                                            <?php endif; ?>
                                            <button type="submit" class="small secondary" form="form-accion-<?= (int) $s['id_materia'] ?>" name="accion" value="quitar">Quitar de la espera</button>
                                        </div>
                                    </details>

                                <?php else: /* sin tutor */ ?>
                                    <p class="offer-key">
                                        Ningún tutor habilitado con horarios por ahora
                                        <?php if ($s['interesados'] > 0): ?> · <?= e($plural($s['interesados'], 'estudiante ya registró interés', 'estudiantes ya registraron interés')) ?><?php endif; ?>
                                    </p>
                                    <details class="offer-details">
                                        <summary>¿Para qué registrar interés?</summary>
                                        <p class="offer-hint">No se te asignará grupo todavía. Tu registro le muestra a la universidad la demanda real de esta materia para asignarle un tutor. Si se abre un grupo, te avisaremos.</p>
                                    </details>
                                    <div class="offer-actions">
                                        <button type="submit" class="small secondary" form="form-accion-<?= (int) $s['id_materia'] ?>" name="accion" value="interes">Registrar interés</button>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($selectable): ?>
                        <div class="offer-submit">
                            <button type="submit">Solicitar apoyo en las materias marcadas</button>
                            <a class="button secondary" href="<?= e(app_url('mis-tutorias/')) ?>">Mis tutorías</a>
                        </div>
                    <?php else: ?>
                        <p class="offer-hint" style="margin-top:1rem;">No hay materias con oferta para solicitar ahora mismo. Puedes registrar interés en las que no tienen tutor o revisar <a href="<?= e(app_url('mis-tutorias/')) ?>">Mis tutorías</a>.</p>
                    <?php endif; ?>
                </form>

                <?php /* Formularios de accion por tarjeta (fuera del formulario principal para no anidar <form>). */ ?>
                <?php foreach ($subjects as $s): ?>
                    <?php if (in_array($s['estado'], [OfertaMateria::ESTADO_EN_ESPERA, OfertaMateria::ESTADO_SIN_TUTOR], true)): ?>
                        <form method="post" action="<?= e(app_url('tutorias/create.php')) ?>" id="form-accion-<?= (int) $s['id_materia'] ?>" hidden>
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="carrera" value="<?= e($carreraParam) ?>">
                            <input type="hidden" name="id_materia" value="<?= (int) $s['id_materia'] ?>">
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php elseif ($periodo && !$studentId): ?>
            <p class="alert" role="alert">Tu perfil de estudiante no está completo. Contacta al administrador.</p>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
