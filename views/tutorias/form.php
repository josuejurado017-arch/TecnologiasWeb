<?php require __DIR__ . '/../layouts/header.php'; ?>

<?php
$resultLabels = [
    'asignado' => ['Asignado a un grupo', 'success'],
    'grupo_creado' => ['Grupo creado y asignado', 'success'],
    'ya_solicitada' => ['No se registró', 'info'],
    'lista_espera' => ['En lista de espera', 'warning'],
    'interes_registrado' => ['Interés registrado', 'info'],
    'espera_retirada' => ['Solicitud retirada', 'info'],
];

// Estado de oferta -> [icono, etiqueta, tono visual]
$stateMeta = [
    OfertaMateria::ESTADO_DISPONIBLE => ['✅', 'Grupo abierto', 'ok'],
    OfertaMateria::ESTADO_FORMACION => ['🟡', 'Grupo en formación', 'forming'],
    OfertaMateria::ESTADO_POR_ABRIR => ['📌', 'Sin grupo todavía', 'pending'],
    OfertaMateria::ESTADO_EN_ESPERA => ['⏳', 'Tu solicitud', 'waiting'],
    OfertaMateria::ESTADO_SIN_TUTOR => ['—', 'Sin tutor', 'none'],
];
// Secciones de la pagina, en orden: lo que el estudiante ya pidio, lo que puede pedir y lo que no tiene tutor.
$secciones = [
    'espera' => ['Tu solicitud en curso', 'La materia que ya pediste en este período.', [OfertaMateria::ESTADO_EN_ESPERA]],
    'grupo' => ['Materias con grupo', 'Ya hay un grupo con cupo: al solicitar entras en él si el horario te sirve.', [OfertaMateria::ESTADO_DISPONIBLE, OfertaMateria::ESTADO_FORMACION]],
    'abrir' => ['Materias por abrir', 'Hay tutor, pero aún no hay grupo. Se abre cuando se reúnen suficientes estudiantes en un mismo turno.', [OfertaMateria::ESTADO_POR_ABRIR]],
    'sin_tutor' => ['Materias sin tutor', 'Todavía no hay tutor. Registrar interés le muestra a la universidad que hace falta uno.', [OfertaMateria::ESTADO_SIN_TUTOR]],
];
$minimo = (int) ($periodo['cupo_min_grupo'] ?? 3);
$recomendado = Grupo::UMBRAL_GRUPO_NORMAL;

$turnoLabels = array_map(static fn (array $t): string => $t['label'], TutorMateriaConfig::TURNOS);
$motivoLabels = [
    Demanda::MOTIVO_SIN_TUTOR => 'Aún no hay tutor habilitado',
    Demanda::MOTIVO_SIN_HORARIO => 'No hubo un horario compatible con tu agenda',
    Demanda::MOTIVO_GRUPO_CANCELADO => 'Tu grupo fue cancelado; esperas reasignación',
    Demanda::MOTIVO_ESPERANDO => 'Esperando más estudiantes en tu turno para formar el grupo',
];
$hora = static fn ($h): string => substr((string) $h, 0, 5);
$plural = static fn (int $n, string $one, string $many): string => $n . ' ' . ($n === 1 ? $one : $many);
$iniciales = static fn (array $p): string => mb_strtoupper(mb_substr((string) $p['nombre'], 0, 1) . mb_substr((string) $p['apellido'], 0, 1));
$selectable = array_filter($subjects, static fn (array $s): bool => in_array($s['estado'], [OfertaMateria::ESTADO_DISPONIBLE, OfertaMateria::ESTADO_FORMACION, OfertaMateria::ESTADO_POR_ABRIR], true));
$carreraParam = $showAll ? 'todas' : 'mia';
$perfiles = $perfiles ?? [];

/** Ficha corta del tutor dentro de la tarjeta; abre su perfil completo. */
$tutorChip = static function (int $tutorId) use ($perfiles, $iniciales): void {
    $p = $perfiles[$tutorId] ?? null;
    if ($p === null) {
        return;
    } ?>
    <button type="button" class="tutor-chip" data-tutor-perfil="tutor-perfil-<?= $tutorId ?>" aria-haspopup="dialog">
        <span class="tutor-avatar" aria-hidden="true"><?= e($iniciales($p)) ?></span>
        <span class="tutor-chip-texto">
            <strong><?= e($p['nombre'] . ' ' . $p['apellido']) ?></strong>
            <small><?= e($p['especialidad'] ?: 'Tutor UPDS') ?><?php if ((int) $p['evaluaciones'] > 0): ?> · ★ <?= e(number_format((float) $p['calificacion'], 1)) ?><?php endif; ?></small>
        </span>
        <span class="tutor-chip-ver">Ver perfil</span>
    </button>
<?php };
?>
<main class="container solicitar">
    <div class="page-heading">
        <div>
            <h1>Solicitar apoyo académico</h1>
            <p>Elige <strong>una materia</strong>: el sistema te ubica en un grupo compatible con tu horario. No eliges tutor, pero puedes ver su perfil.</p>
        </div>
        <a class="button secondary" href="<?= e(app_url('mis-tutorias/')) ?>">Mis tutorías</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if (!$periodo): ?>
        <p class="alert" role="alert">No hay un período de tutoría activo. Vuelve cuando la coordinación active uno.</p>
    <?php else: ?>
        <section class="solicitar-intro card">
            <p class="solicitar-periodo">Período <strong><?= e($periodo['nombre']) ?></strong> · del <?= e(date('d/m/Y', strtotime((string) $periodo['fecha_inicio']))) ?> al <?= e(date('d/m/Y', strtotime((string) $periodo['fecha_fin']))) ?> · <strong>una tutoría por estudiante</strong></p>
            <ol class="solicitar-pasos">
                <li><span class="paso-num">1</span><div><strong>Eliges una materia</strong><small>Si no hay grupo, quedas con interés registrado.</small></div></li>
                <li><span class="paso-num">2</span><div><strong>Se forma el grupo</strong><small>Con <?= $minimo ?> estudiantes en un mismo turno. Con <?= $recomendado ?> o más se dicta de lunes a viernes.</small></div></li>
                <li><span class="paso-num">3</span><div><strong>La coordinación lo aprueba</strong><small>Recibes el horario y el aula o el enlace.</small></div></li>
            </ol>
        </section>
    <?php endif; ?>

    <?php if ($results !== null): ?>
        <div class="result-panel" role="status">
            <h2>Resultado</h2>
            <ul class="result-list">
                <?php foreach ($results as $r): ?>
                    <?php [$label, $tone] = ($r['motivo'] ?? null) === Demanda::MOTIVO_ESPERANDO
                        ? ['📌 Interés registrado', 'info']
                        : ($resultLabels[$r['resultado']] ?? ['Procesado', 'info']); ?>
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

    <?php if ($periodo && $studentId && $tutoriaActual !== null): ?>
        <p class="solicitar-aviso" role="status">
            <?php if ($tutoriaActual['tipo'] === 'inscripcion'): ?>
                Ya tienes tu tutoría del período: <strong><?= e($tutoriaActual['nombre']) ?></strong>. Solo se permite una. <a href="<?= e(app_url('mis-tutorias/')) ?>">Ver mis tutorías</a>
            <?php else: ?>
                Tu solicitud del período es <strong><?= e($tutoriaActual['nombre']) ?></strong>. Solo se permite una: para cambiar de materia, primero quítala de la espera.
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($periodo && $studentId): ?>
        <div class="offer-toolbar">
            <nav class="segmented" aria-label="Filtro de carrera">
                <a class="<?= $showAll ? '' : 'is-active' ?>" href="<?= e(app_url('tutorias/create.php')) ?>" <?= $career === null ? 'aria-disabled="true"' : '' ?>>Mi carrera<?php if ($career): ?> <small>(<?= e($career['nombre_carrera']) ?>)</small><?php endif; ?></a>
                <a class="<?= $showAll ? 'is-active' : '' ?>" href="<?= e(app_url('tutorias/create.php?carrera=todas')) ?>">Todas</a>
            </nav>
            <div class="search-field offer-search">
                <label class="sr-only" for="buscar-materia">Buscar materia o tutor</label>
                <input id="buscar-materia" type="search" placeholder="Buscar materia o tutor…" data-offer-search>
            </div>
        </div>
        <p class="offer-summary">
            <span><b><?= (int) ($summary[OfertaMateria::ESTADO_DISPONIBLE] + $summary[OfertaMateria::ESTADO_FORMACION]) ?></b> con grupo</span>
            <span><b><?= (int) $summary[OfertaMateria::ESTADO_POR_ABRIR] ?></b> por abrir</span>
            <span><b><?= (int) $summary[OfertaMateria::ESTADO_SIN_TUTOR] ?></b> sin tutor</span>
        </p>

        <?php if (!$subjects): ?>
            <p class="empty-state">No hay materias para mostrar<?= $showAll ? '' : ' en tu carrera' ?>. <?php if (!$showAll): ?><a href="<?= e(app_url('tutorias/create.php?carrera=todas')) ?>">Ver todas las materias</a>.<?php endif; ?></p>
        <?php else: ?>
            <form method="post" action="<?= e(app_url('tutorias/create.php')) ?>" id="form-solicitar" data-solicitar>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="carrera" value="<?= e($carreraParam) ?>">
                <input type="hidden" name="accion" value="solicitar">

                <?php foreach ($secciones as $clave => [$tituloSeccion, $ayudaSeccion, $estados]): ?>
                    <?php $items = array_values(array_filter($subjects, static fn (array $s): bool => in_array($s['estado'], $estados, true))); ?>
                    <?php if (!$items) { continue; } ?>
                    <?php if ($clave === 'sin_tutor'): ?><details class="offer-section offer-section--plegable" data-offer-section><summary><?php else: ?><section class="offer-section" data-offer-section><?php endif; ?>
                        <div class="offer-section-head">
                            <h2><?= e($tituloSeccion) ?> <span class="offer-count"><?= count($items) ?></span></h2>
                            <p><?= e($ayudaSeccion) ?></p>
                        </div>
                    <?php if ($clave === 'sin_tutor'): ?></summary><?php endif; ?>

                    <ul class="offer-grid">
                        <?php foreach ($items as $s): ?>
                            <?php
                            [$icon, $stateLabel, $tone] = $stateMeta[$s['estado']];
                            $isSelectable = $tutoriaActual === null && in_array($s['estado'], [OfertaMateria::ESTADO_DISPONIBLE, OfertaMateria::ESTADO_FORMACION, OfertaMateria::ESTADO_POR_ABRIR], true);
                            $pref = $s['preferencias'];
                            $firstGroup = $s['grupos'][0] ?? null;
                            $nombresTutores = implode(' ', array_map(static fn (int $id): string => isset($perfiles[$id]) ? $perfiles[$id]['nombre'] . ' ' . $perfiles[$id]['apellido'] : '', $s['tutor_ids']));
                            ?>
                            <li class="offer-card offer-<?= e($tone) ?>" data-offer-card data-buscar="<?= e(mb_strtolower($s['nombre_materia'] . ' ' . ($s['nombre_carrera'] ?? '') . ' ' . $nombresTutores)) ?>">
                                <div class="offer-head">
                                    <span class="offer-state"><span aria-hidden="true"><?= $icon ?></span> <?= e($stateLabel) ?></span>
                                    <span class="offer-carrera"><?= e($s['nombre_carrera'] ?: 'Materia general') ?></span>
                                </div>
                                <h3 class="offer-title"><?= e($s['nombre_materia']) ?></h3>

                                <?php if ($firstGroup !== null && in_array($s['estado'], [OfertaMateria::ESTADO_DISPONIBLE, OfertaMateria::ESTADO_FORMACION], true)): ?>
                                    <dl class="offer-facts">
                                        <div><dt>Horario</dt><dd><?= e(GruposController::etiquetaDias(explode('/', (string) ($firstGroup['dias'] ?: $firstGroup['dia_semana'])))) ?> · <?= e($hora($firstGroup['hora_inicio'])) ?>–<?= e($hora($firstGroup['hora_fin'])) ?></dd></div>
                                        <div><dt>Modalidad</dt><dd><?= e(ucfirst((string) $firstGroup['modalidad'])) ?></dd></div>
                                        <div><dt>Cupos libres</dt><dd class="<?= $s['cupos_libres'] <= 3 ? 'is-low' : '' ?>"><?= (int) $s['cupos_libres'] ?><?= $s['cupos_libres'] <= 3 ? ' · últimos' : '' ?></dd></div>
                                    </dl>
                                    <?php if ($s['estado'] === OfertaMateria::ESTADO_FORMACION && $s['inscritos_formacion'] !== null): ?>
                                        <?php $avance = min(100, (int) round($s['inscritos_formacion'] * 100 / $recomendado)); ?>
                                        <div class="avance-grupo" aria-label="Avance del grupo">
                                            <div class="avance-grupo-barra"><span style="width: <?= $avance ?>%"></span></div>
                                            <small><?= (int) $s['inscritos_formacion'] ?> estudiantes · espera la aprobación de la coordinación</small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (count($s['grupos']) > 1): ?>
                                        <details class="offer-details">
                                            <summary>Ver los <?= count($s['grupos']) ?> grupos</summary>
                                            <ul class="offer-grupos">
                                                <?php foreach ($s['grupos'] as $g): ?>
                                                    <li><?= e(GruposController::etiquetaDias(explode('/', (string) ($g['dias'] ?: $g['dia_semana'])))) ?> <?= e($hora($g['hora_inicio'])) ?>–<?= e($hora($g['hora_fin'])) ?> · <?= e(ucfirst((string) $g['modalidad'])) ?> · <?= (int) $g['cupo_max'] - (int) $g['cupo_ocupado'] ?> cupos · <?= e($g['tutor']) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    <?php endif; ?>

                                <?php elseif ($s['estado'] === OfertaMateria::ESTADO_POR_ABRIR): ?>
                                    <dl class="offer-facts">
                                        <div><dt>Turnos</dt><dd><?= e($pref['turnos'] ? implode(', ', array_map(static fn (string $t): string => $turnoLabels[$t] ?? $t, $pref['turnos'])) : 'Por definir') ?></dd></div>
                                        <div><dt>Modalidad</dt><dd><?= e(!$pref['modalidades'] || in_array('ambas', $pref['modalidades'], true) || count($pref['modalidades']) > 1 ? 'Presencial o virtual' : ucfirst($pref['modalidades'][0])) ?></dd></div>
                                        <div><dt>Interesados</dt><dd><?= (int) $s['interesados'] ?> de <?= $minimo ?> para abrir</dd></div>
                                    </dl>

                                <?php elseif ($s['estado'] === OfertaMateria::ESTADO_EN_ESPERA): ?>
                                    <dl class="offer-facts">
                                        <div><dt>Solicitada</dt><dd><?= e(date('d/m/Y', strtotime((string) $s['espera_desde']))) ?></dd></div>
                                        <div class="offer-facts-ancho"><dt>Situación</dt><dd><?= e($motivoLabels[$s['motivo_espera']] ?? 'En espera') ?></dd></div>
                                    </dl>
                                    <p class="offer-hint"><?= $s['hay_oferta'] ? 'El sistema te asigna solo cuando cambia la oferta. Si tu horario cambió, puedes reintentar ahora.' : 'En cuanto un tutor se habilite, el sistema intentará asignarte y te avisará.' ?></p>

                                <?php else: ?>
                                    <p class="offer-hint"><?= $s['interesados'] > 0 ? e($plural($s['interesados'], 'estudiante ya registró', 'estudiantes ya registraron')) . ' interés.' : 'Nadie registró interés todavía.' ?></p>
                                <?php endif; ?>

                                <?php if ($s['tutor_ids']): ?>
                                    <div class="offer-tutores">
                                        <span class="offer-tutores-titulo"><?= count($s['tutor_ids']) === 1 ? 'Tutor' : 'Tutores que la dictan' ?></span>
                                        <?php foreach ($s['tutor_ids'] as $tutorId) { $tutorChip((int) $tutorId); } ?>
                                    </div>
                                <?php endif; ?>

                                <div class="offer-foot">
                                    <?php if ($isSelectable): ?>
                                        <label class="offer-select">
                                            <input type="radio" name="materias[]" value="<?= (int) $s['id_materia'] ?>" data-materia="<?= e($s['nombre_materia']) ?>" required>
                                            <span>Elegir esta materia</span>
                                        </label>
                                    <?php elseif ($s['estado'] === OfertaMateria::ESTADO_EN_ESPERA): ?>
                                        <?php if ($s['hay_oferta']): ?>
                                            <button type="submit" class="small" form="form-accion-<?= (int) $s['id_materia'] ?>" name="accion" value="reintentar">Reintentar asignación</button>
                                        <?php endif; ?>
                                        <button type="submit" class="small secondary" form="form-accion-<?= (int) $s['id_materia'] ?>" name="accion" value="quitar">Quitar de la espera</button>
                                    <?php elseif ($s['estado'] === OfertaMateria::ESTADO_SIN_TUTOR && $tutoriaActual === null): ?>
                                        <button type="submit" class="small secondary" form="form-accion-<?= (int) $s['id_materia'] ?>" name="accion" value="interes">Registrar interés</button>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($clave === 'sin_tutor'): ?></details><?php else: ?></section><?php endif; ?>
                <?php endforeach; ?>

                <p class="empty-state" data-offer-vacio hidden>Ninguna materia coincide con la búsqueda.</p>

                <?php if ($tutoriaActual === null && $selectable): ?>
                    <div class="solicitar-barra" data-solicitar-barra>
                        <p><span data-elegida>Elige una materia</span></p>
                        <button type="submit" data-solicitar-enviar disabled>Solicitar apoyo</button>
                    </div>
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

            <?php /* Perfil publico de cada tutor (sin datos de contacto). */ ?>
            <?php foreach ($perfiles as $tutorId => $p): ?>
                <dialog class="tutor-perfil" id="tutor-perfil-<?= (int) $tutorId ?>" aria-labelledby="tutor-perfil-titulo-<?= (int) $tutorId ?>">
                    <div class="tutor-perfil-head">
                        <span class="tutor-avatar tutor-avatar--grande" aria-hidden="true"><?= e($iniciales($p)) ?></span>
                        <div>
                            <h2 id="tutor-perfil-titulo-<?= (int) $tutorId ?>"><?= e($p['nombre'] . ' ' . $p['apellido']) ?></h2>
                            <p><?= e($p['especialidad'] ?: 'Tutor UPDS') ?></p>
                        </div>
                        <form method="dialog"><button class="tutor-perfil-cerrar" aria-label="Cerrar">✕</button></form>
                    </div>
                    <dl class="tutor-perfil-datos">
                        <div><dt>Calificación</dt><dd><?= (int) $p['evaluaciones'] > 0 ? '★ ' . e(number_format((float) $p['calificacion'], 1)) . ' <small>de 5 · ' . (int) $p['evaluaciones'] . ' evaluaciones</small>' : '<small>Aún sin evaluaciones</small>' ?></dd></div>
                        <div><dt>Experiencia</dt><dd><?= (int) $p['grupos_dictados'] > 0 ? e($plural((int) $p['grupos_dictados'], 'grupo dictado', 'grupos dictados')) : '<small>Nuevo en el programa</small>' ?></dd></div>
                    </dl>
                    <?php if (trim((string) $p['biografia']) !== ''): ?>
                        <h3>Sobre el tutor</h3>
                        <p class="tutor-perfil-bio"><?= nl2br(e((string) $p['biografia'])) ?></p>
                    <?php endif; ?>
                    <?php if ($p['materias']): ?>
                        <h3>Materias que ofrece</h3>
                        <ul class="tutor-perfil-materias">
                            <?php foreach ($p['materias'] as $m): ?>
                                <li><strong><?= e($m['nombre']) ?></strong>
                                    <small><?= e(implode(', ', array_map(static fn (string $t): string => $turnoLabels[$t] ?? $t, $m['turnos'])) ?: 'Turnos por definir') ?> · <?= e($m['modalidad'] === 'ambas' ? 'Presencial o virtual' : ucfirst((string) $m['modalidad'])) ?></small></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </dialog>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php elseif ($periodo && !$studentId): ?>
        <p class="alert" role="alert">Tu perfil de estudiante no está completo. Contacta a la coordinación.</p>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
