<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$accionLabels = [
    'migrada' => 'Migrada desde aulas',
    'definida' => 'Definida',
    'cambiada' => 'Cambiada',
    'propuesta' => 'Propuesta del tutor',
    'propuesta_aprobada' => 'Propuesta aprobada',
    'propuesta_corregida' => 'Propuesta corregida',
    'propuesta_reemplazada' => 'Propuesta reemplazada',
    'propuesta_rechazada' => 'Propuesta rechazada',
];
$lugar = static function (?string $modalidad, ?string $espacio, ?string $ubicacion, ?string $enlace): string {
    if ($espacio === null && $ubicacion === null && $enlace === null) {
        return '—';
    }
    $detalle = $modalidad === 'virtual' ? ($enlace ?? 'sin enlace') : ($ubicacion ?? 'sin aula');

    return trim(($espacio ?? '') . ' · ' . $detalle, ' ·');
};
$hora = static fn ($h): string => substr((string) $h, 0, 5);
$pendiente = ubicacion_pendiente($grupo);
$porAprobar = $grupo['estado'] === 'por_aprobar';
$presencial = $grupo['modalidad'] === 'presencial';
$turno = '—';
foreach (TutorMateriaConfig::TURNOS as $t) {
    if ($t['inicio'] === (string) $grupo['hora_inicio']) {
        $turno = $t['label'];
    }
}
$origenModalidad = $grupo['modalidad_requerida'] !== 'libre' ? 'la exige la materia' : 'la definieron el tutor y la regla del período';
$umbral = Grupo::UMBRAL_GRUPO_NORMAL;
$patronElegido = (string) ($input['patron'] ?? ($patronActual ?? 'lmv'));
$enlaceForm = (string) ($input['enlace'] ?? ($grupo['enlace_propuesto'] ?? ($grupo['enlace'] ?? '')));
$ubicacionForm = (string) ($input['ubicacion'] ?? ($grupo['ubicacion'] ?? ''));
// La coordinacion puede cambiar la modalidad, salvo que la materia exija una.
$modalidadFija = $grupo['modalidad_requerida'] !== 'libre';
$modalidadForm = (string) ($input['modalidad'] ?? $grupo['modalidad']);
$camposUbicacion = static function (bool $conAyudaAula) use ($grupo, $modalidadFija, $modalidadForm, $ubicacionForm, $enlaceForm): void {
    ?>
    <?php if ($modalidadFija): ?>
        <input type="hidden" name="modalidad" value="<?= e($grupo['modalidad']) ?>">
        <p class="form-hint">Modalidad: <strong><?= e(ucfirst((string) $grupo['modalidad'])) ?></strong> (la exige la materia).</p>
    <?php else: ?>
        <label for="modalidad">Modalidad</label>
        <select id="modalidad" name="modalidad" data-modalidad-grupo>
            <?php foreach (EspacioTutoria::MODALIDADES as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $modalidadForm === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="form-hint">Viene de la oferta del tutor. Cámbiala solo si es necesario: el espacio se recalcula.</p>
    <?php endif; ?>
    <div data-campo-modalidad="presencial" <?= $modalidadForm === 'presencial' ? '' : 'hidden' ?>>
        <label for="ubicacion">Aula o lugar</label>
        <input id="ubicacion" name="ubicacion" minlength="2" maxlength="200" placeholder="Ej. Aula 204 - Bloque A" value="<?= e($ubicacionForm) ?>" <?= $modalidadForm === 'presencial' ? 'required' : 'disabled' ?>>
        <?php if ($conAyudaAula): ?><p class="form-hint">El sistema no reserva aulas: confírmala con la planificación académica de la UPDS.</p><?php endif; ?>
    </div>
    <div data-campo-modalidad="virtual" <?= $modalidadForm === 'virtual' ? '' : 'hidden' ?>>
        <label for="enlace">Enlace de la reunión</label>
        <input id="enlace" name="enlace" type="url" maxlength="300" placeholder="https://meet.google.com/…" value="<?= e($enlaceForm) ?>" <?= $modalidadForm === 'virtual' ? 'required' : 'disabled' ?>>
        <?php if ($conAyudaAula): ?><p class="form-hint">La plataforma (Meet, Zoom o Teams) se reconoce por el enlace.</p><?php endif; ?>
    </div>
    <?php
};
?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Revisar grupo</h1>
            <p><?= e($grupo['nombre_materia']) ?> · <?= e($grupo['tutor']) ?> ·
                <?= estado_grupo_badge($grupo) ?>
                <?php if ($vigente && !$porAprobar && $pendiente): ?> <span class="badge badge-warning">&#9888; Ubicación pendiente</span><?php endif; ?>
                <span class="estado-grupo-texto"><?= e(estado_grupo_visual($grupo)['texto']) ?></span></p>
        </div>
        <a class="button secondary" href="<?= e(app_url('grupos/')) ?>">Volver a grupos</a>
    </div>

    <?php if ($message): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($avisoAccion)): ?><p class="notice-warning" role="status"><?= e($avisoAccion) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <section class="card">
        <h2>Datos del grupo</h2>
        <dl class="revision-datos">
            <div><dt>Materia</dt><dd><?= e($grupo['nombre_materia']) ?></dd></div>
            <div><dt>Tutor</dt><dd><?= e($grupo['tutor']) ?></dd></div>
            <div><dt>Período</dt><dd><?= e($periodo['nombre'] ?? '—') ?></dd></div>
            <div><dt>Modalidad</dt><dd><?= e(ucfirst((string) $grupo['modalidad'])) ?><small><?= e(ucfirst($origenModalidad)) ?><?= $modalidadFija || !$vigente ? '' : '; la coordinación puede cambiarla' ?></small></dd></div>
            <div><dt>Turno</dt><dd><?= e($turno) ?> · <?= e($hora($grupo['hora_inicio'])) ?>–<?= e($hora($grupo['hora_fin'])) ?></dd></div>
            <div><dt>Frecuencia</dt><dd><?= e(GruposController::etiquetaDias($diasGrupo)) ?><small><?= e(implode(', ', $diasGrupo)) ?></small></dd></div>
            <div><dt>Estudiantes</dt><dd><?= (int) $grupo['cupo_ocupado'] ?> de <?= (int) $grupo['cupo_max'] ?> cupos</dd></div>
            <div><dt>Inscripción</dt><dd><?php if ($cierreInscripcion === null): ?>Abierta<small>Aún sin calendario</small><?php elseif ($cierreInscripcion >= date('Y-m-d')): ?>Abierta hasta el <?= e(date('d/m/Y', strtotime($cierreInscripcion))) ?><small><?= Grupo::DIAS_INSCRIPCION_TARDIA ?> días después de la primera sesión</small><?php else: ?>Cerrada desde el <?= e(date('d/m/Y', strtotime($cierreInscripcion . ' +1 day'))) ?><small>Pasaron <?= Grupo::DIAS_INSCRIPCION_TARDIA ?> días de la primera sesión</small><?php endif; ?></dd></div>
            <div><dt>Espacio</dt><dd><?= e($grupo['espacio']) ?><small>Calculado según la modalidad<?= $presencial ? '' : ' y el enlace' ?></small></dd></div>
        </dl>
    </section>

    <?php if ($vigente): ?>
        <section class="card" id="tutor">
            <h2>Tutor</h2>
            <p><strong><?= e($grupo['tutor']) ?></strong></p>
            <?php if (!$tutoresDisponibles): ?>
                <p class="panel-note">Ningún otro tutor puede tomar este grupo: hace falta la materia aprobada en el turno <?= e($turno) ?>, estar libre en ese turno y no superar su tope de grupos. Para sumar un tutor a la materia, usa <a href="<?= e(app_url('grupos/asignar_tutor.php?materia=' . (int) $grupo['id_materia'])) ?>">Asignar tutor</a>.</p>
            <?php else: ?>
                <details>
                    <summary>Cambiar tutor</summary>
                    <form method="post" class="inline-form" onsubmit="return confirm('¿Cambiar el tutor de este grupo? Se avisará a los dos tutores y a los inscritos.');">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="cambiar_tutor">
                        <label for="id_tutor_nuevo">Nuevo tutor</label>
                        <select id="id_tutor_nuevo" name="id_tutor" required>
                            <?php foreach ($tutoresDisponibles as $t): ?>
                                <option value="<?= (int) $t['id_tutor'] ?>"><?= e($t['tutor']) ?><?= $t['especialidad'] ? ' · ' . e($t['especialidad']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="motivo_tutor">Motivo</label>
                        <input id="motivo_tutor" name="motivo_tutor" required minlength="4" maxlength="300" placeholder="Ej. licencia médica del tutor">
                        <button type="submit" class="secondary">Cambiar tutor</button>
                    </form>
                    <p class="form-hint">Solo aparecen tutores con esta materia aprobada en el turno <?= e($turno) ?>, libres en ese turno y bajo su tope de grupos. Se conservan el horario, las sesiones y la asistencia.</p>
                </details>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($mostrarDivision): ?>
        <section class="card" id="dividir">
            <h2>Dividir grupo</h2>
            <?php if ($divisionPendiente !== null): ?>
                <p>⏳ Propuesta enviada a <strong><?= e($divisionPendiente['tutor']) ?></strong> el <?= e(date('d/m/Y H:i', strtotime((string) $divisionPendiente['fecha_solicitud']))) ?>. El grupo se divide cuando el tutor la acepte.</p>
                <form method="post" class="inline-form" onsubmit="return confirm('¿Retirar la propuesta de división?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="cancelar_division">
                    <input type="hidden" name="id_division" value="<?= (int) $divisionPendiente['id_division'] ?>">
                    <button type="submit" class="secondary">Retirar propuesta</button>
                </form>
            <?php elseif ($bloqueoDivision !== null): ?>
                <p class="panel-note"><?= e($bloqueoDivision) ?></p>
            <?php elseif (!$tutoresDivision): ?>
                <p class="panel-note">Ningún tutor puede tomar la mitad: hace falta un tutor habilitado, libre en el turno <?= e($turno) ?>, sin otra materia en ese turno y bajo su tope de materias y grupos.</p>
            <?php else: ?>
                <p class="panel-note">El grupo está lleno. Divídelo en dos grupos parejos con otro tutor: mismo turno y mismos días, así que a nadie le cambia el horario. Pasan los últimos en inscribirse y entran quienes esperan la materia.</p>
                <form method="get" action="<?= e(app_url('grupos/ubicacion.php')) ?>#dividir" class="inline-form">
                    <input type="hidden" name="grupo" value="<?= (int) $grupo['id_grupo'] ?>">
                    <label for="dividir_tutor">Segundo tutor</label>
                    <select id="dividir_tutor" name="dividir_tutor" required>
                        <option value="">Elige un tutor…</option>
                        <?php foreach ($tutoresDivision as $t): ?>
                            <option value="<?= (int) $t['id_tutor'] ?>" <?= (int) $t['id_tutor'] === $tutorPrevia ? 'selected' : '' ?>><?= e($t['tutor']) ?><?= $t['especialidad'] ? ' · ' . e($t['especialidad']) : '' ?> (<?= (int) $t['grupos_periodo'] ?> grupo<?= (int) $t['grupos_periodo'] === 1 ? '' : 's' ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="secondary">Ver vista previa</button>
                </form>
                <?php if ($planDivision !== null): ?>
                    <h3>Vista previa</h3>
                    <?php if ($planDivision['error'] !== null): ?>
                        <p class="alert" role="alert"><?= e($planDivision['error']) ?></p>
                    <?php else: ?>
                        <div class="revision-datos">
                            <div>
                                <h4>Grupo actual · <?= e($grupo['tutor']) ?> (<?= count($planDivision['quedan']) ?>)</h4>
                                <ul class="plain-list"><?php foreach ($planDivision['quedan'] as $est): ?><li><?= e($est['estudiante']) ?></li><?php endforeach; ?></ul>
                            </div>
                            <div>
                                <h4>Grupo nuevo · <?= e($tutorPreviaNombre) ?> (<?= count($planDivision['pasan']) + count($planDivision['desde_espera']) ?>)</h4>
                                <ul class="plain-list">
                                    <?php foreach ($planDivision['pasan'] as $est): ?><li><?= e($est['estudiante']) ?> <small>· se traslada</small></li><?php endforeach; ?>
                                    <?php foreach ($planDivision['desde_espera'] as $est): ?><li><?= interes_badge() ?> <?= e($est['estudiante']) ?> <small>· estaba en espera</small></li><?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php if ($planDivision['siguen_esperando'] > 0): ?><p class="form-hint"><?= (int) $planDivision['siguen_esperando'] ?> estudiante(s) seguirán en espera: los dos grupos quedan llenos.</p><?php endif; ?>
                        <p class="form-hint">El reparto se recalcula cuando el tutor acepte, por si alguien se inscribe o se retira mientras tanto.</p>
                        <form method="post" onsubmit="return confirm(<?= e(json_encode('¿Enviar la propuesta de división a ' . $tutorPreviaNombre . '?', JSON_UNESCAPED_UNICODE)) ?>);">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="accion" value="dividir">
                            <input type="hidden" name="id_tutor_division" value="<?= (int) $tutorPrevia ?>">
                            <button type="submit">Trasladar y enviar propuesta al tutor</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card" id="inscritos">
        <h2>Estudiantes inscritos (<?= count($inscritos) ?>)</h2>
        <?php if (!$inscritos): ?>
            <p class="empty-state">Sin inscritos por ahora.</p>
        <?php else: ?>
            <ul class="plain-list">
                <?php foreach ($inscritos as $i): ?>
                    <li><?= e($i['estudiante']) ?> <small>· <?= e($i['correo']) ?> · <?= e(ucfirst(str_replace('_', ' ', (string) $i['estado']))) ?></small>
                        <?php if ($vigente && $i['estado'] === 'inscrito'): ?>
                            <details class="inline-details">
                                <summary>Retirar</summary>
                                <form method="post" class="inline-form" onsubmit="return confirm('¿Retirar a este estudiante del grupo?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="retirar">
                                    <input type="hidden" name="id_estudiante" value="<?= (int) $i['id_estudiante'] ?>">
                                    <input name="motivo_retiro" required minlength="4" maxlength="300" placeholder="Motivo del retiro" aria-label="Motivo del retiro">
                                    <button type="submit" class="link-button">Retirar</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($vigente && $grupo['estado'] === 'por_aprobar'): ?>
                <p class="form-hint">Si al retirar a alguien el grupo queda con menos de <?= (int) ($periodo['cupo_min_grupo'] ?? 3) ?> estudiantes, se disuelve y los demás vuelven a interés registrado.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($vigente): ?>
        <section class="card" id="agregar">
            <h2>Agregar estudiante</h2>
            <?php if ((int) $grupo['cupo_ocupado'] >= (int) $grupo['cupo_max']): ?>
                <p class="panel-note">El grupo no tiene cupo libre.</p>
            <?php else: ?>
                <form method="get" action="<?= e(app_url('grupos/ubicacion.php')) ?>#agregar" class="inline-form">
                    <input type="hidden" name="grupo" value="<?= (int) $grupo['id_grupo'] ?>">
                    <label for="buscar" class="sr-only">Buscar estudiante</label>
                    <input id="buscar" name="buscar" value="<?= e($busqueda) ?>" minlength="2" maxlength="60" placeholder="Nombre, usuario o registro universitario">
                    <button type="submit" class="secondary">Buscar</button>
                </form>
                <p class="form-hint">Se aplican las mismas reglas que al asignar automáticamente: una tutoría por período, una por turno y cupo del grupo.</p>
                <?php if (!$candidatos): ?>
                    <p class="panel-note"><?= $busqueda !== '' ? 'Ningún estudiante activo coincide con la búsqueda.' : 'Nadie está esperando esta materia. Busca a un estudiante por nombre, usuario o registro.' ?></p>
                <?php else: ?>
                    <ul class="plain-list">
                        <?php foreach ($candidatos as $c): ?>
                            <li>
                                <?= $c['origen'] === 'espera' ? interes_badge() . ' ' : '' ?><strong><?= e($c['estudiante']) ?></strong>
                                <?php if (!empty($c['usuario'])): ?><small>· <?= e($c['usuario']) ?><?= !empty($c['nombre_carrera']) ? ' · ' . e($c['nombre_carrera']) : '' ?></small><?php endif; ?>
                                <?php if ($c['bloqueo'] !== null): ?>
                                    <small class="materia-aviso"><?= e($c['bloqueo']) ?></small>
                                <?php else: ?>
                                    <?php if ($c['aviso'] !== null): ?><small class="materia-nota"><?= e($c['aviso']) ?></small><?php endif; ?>
                                    <form method="post" class="inline-form" style="display:inline-flex;">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="accion" value="inscribir">
                                        <input type="hidden" name="id_estudiante" value="<?= (int) $c['id_estudiante'] ?>">
                                        <button type="submit" class="link-button">Inscribir</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Demanda asociada</h2>
        <?php if (!$demandaPendiente): ?>
            <p class="panel-note">Nadie más está esperando <?= e($grupo['nombre_materia']) ?> en este período.</p>
        <?php else: ?>
            <p class="panel-note"><strong><?= count($demandaPendiente) ?></strong> estudiante(s) más en espera de <?= e($grupo['nombre_materia']) ?> en este período, sin horario compatible con este grupo todavía.</p>
        <?php endif; ?>
    </section>

    <?php if (!$porAprobar): ?>
        <section class="card">
            <h2>Ubicación actual</h2>
            <p>
                <strong><?= e(ucfirst((string) $grupo['modalidad'])) ?></strong> · <?= e($grupo['espacio']) ?> ·
                <?php if ($pendiente): ?>
                    <span class="badge badge-warning">Ubicación pendiente</span>
                <?php elseif (!$presencial): ?>
                    <a href="<?= e($grupo['enlace']) ?>" target="_blank" rel="noopener noreferrer"><?= e($grupo['enlace']) ?></a>
                <?php else: ?>
                    <?= e($grupo['ubicacion']) ?>
                <?php endif; ?>
            </p>
        </section>
    <?php endif; ?>

    <?php if ($grupo['enlace_propuesto'] !== null && $vigente): ?>
        <section class="card">
            <h2>Enlace propuesto por el tutor</h2>
            <p><?= e($grupo['espacio_propuesto'] ?? 'Virtual') ?> ·
                <a href="<?= e($grupo['enlace_propuesto']) ?>" target="_blank" rel="noopener noreferrer"><?= e($grupo['enlace_propuesto']) ?></a>
                <small>(<?= e(date('d/m/Y H:i', strtotime((string) $grupo['fecha_propuesta']))) ?>)</small></p>
            <?php if (!$porAprobar): ?>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="aprobar_propuesta">
                    <button type="submit">Aprobar enlace propuesto</button>
                </form>
            <?php endif; ?>
            <form method="post" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="rechazar_propuesta">
                <label for="motivo_rechazo">Motivo para rechazar</label>
                <input id="motivo_rechazo" name="motivo_rechazo" required minlength="4" maxlength="300">
                <button class="secondary" type="submit">Rechazar propuesta</button>
            </form>
            <p class="form-hint"><?= $porAprobar ? 'Viene precargado en el enlace de la aprobación: apruébalo tal cual o corrígelo ahí.' : 'Para corregirlo o reemplazarlo, edita el enlace en el formulario de abajo.' ?></p>
        </section>
    <?php endif; ?>

    <?php if ($porAprobar): ?>
        <section class="card form-card">
            <h2>Aprobación</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="aprobar">

                <fieldset class="materia-field">
                    <legend>Frecuencia</legend>
                    <?php if ($tipoFrecuencia === 'elegible'): ?>
                        <div class="chip-group chip-group-2" role="radiogroup" aria-label="Frecuencia del grupo">
                            <?php foreach (Grupo::PATRONES_REDUCIDOS as $key => $p): ?>
                                <label class="chip chip-radio">
                                    <input type="radio" name="patron" value="<?= e($key) ?>" required <?= $key === $patronElegido ? 'checked' : '' ?>>
                                    <span><strong><?= e($p['corto']) ?></strong><small><?= e($p['label']) ?></small></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="form-hint">Grupo reducido: menos de <?= $umbral ?> estudiantes. Elige la frecuencia.</p>
                    <?php elseif ($tipoFrecuencia === 'pasa_a_normal'): ?>
                        <p><strong>Lunes a viernes</strong> <small>(automático)</small></p>
                        <p class="form-hint">El grupo ya tiene <?= (int) $grupo['cupo_ocupado'] ?> estudiantes: al aprobarlo pasa de <?= e(GruposController::etiquetaDias($diasGrupo)) ?> a lunes a viernes.</p>
                    <?php elseif ($tipoFrecuencia === 'normal'): ?>
                        <p><strong>Lunes a viernes</strong> <small>(automático)</small></p>
                        <p class="form-hint"><?= $umbral ?> o más estudiantes: la frecuencia no se edita.</p>
                    <?php else: ?>
                        <p><strong><?= e(implode(', ', $diasGrupo)) ?></strong></p>
                        <p class="form-hint">Grupo creado antes de la regla de frecuencia: conserva su patrón.</p>
                    <?php endif; ?>
                </fieldset>

                <?php $camposUbicacion(true); ?>

                <label for="observaciones">Observaciones (opcional)</label>
                <input id="observaciones" name="motivo" maxlength="300" value="<?= e($input['motivo'] ?? '') ?>" placeholder="Notas para el historial del grupo">

                <div class="form-actions">
                    <button type="submit">Aprobar grupo</button>
                </div>
            </form>

            <details class="form-card" style="margin-top:1rem;">
                <summary>Rechazar este grupo</summary>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="rechazar">
                    <label for="motivo_rechazo_grupo">Motivo del rechazo</label>
                    <input id="motivo_rechazo_grupo" name="motivo_rechazo_grupo" required minlength="4" maxlength="300" placeholder="Ej. horario no viable, tutor sin disponibilidad real">
                    <p class="form-hint">Los inscritos vuelven a la lista de espera y el sistema no volverá a proponer este mismo tutor y horario para la materia.</p>
                    <button class="secondary" type="submit" onclick="return confirm('¿Rechazar este grupo? Los inscritos vuelven a la lista de espera.');">Rechazar grupo</button>
                </form>
            </details>
        </section>
    <?php elseif ($vigente): ?>
        <section class="card form-card">
            <h2><?= $pendiente ? 'Definir ubicación' : 'Cambiar modalidad o ubicación' ?></h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="guardar">
                <?php $camposUbicacion(false); ?>
                <label for="observaciones">Motivo del cambio<?= $pendiente ? ' (opcional; obligatorio si cambias la modalidad)' : '' ?></label>
                <input id="observaciones" name="motivo" maxlength="300" value="<?= e($input['motivo'] ?? '') ?>" <?= $pendiente ? '' : 'required minlength="4"' ?>>
                <div class="form-actions">
                    <button type="submit">Guardar ubicación</button>
                </div>
            </form>
        </section>
    <?php else: ?>
        <p class="panel-note">El grupo está <?= e(mb_strtolower(estado_grupo_label((string) $grupo['estado']))) ?>: su ubicación ya no se puede modificar.</p>
    <?php endif; ?>

    <h2>Historial de ubicación</h2>
    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Fecha</th><th>Acción</th><th>Antes</th><th>Después</th><th>Responsable</th><th>Motivo</th></tr></thead>
            <tbody>
                <?php foreach ($historialUbicacion as $h): ?>
                    <tr>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $h['fecha']))) ?></td>
                        <td><span class="badge badge-info"><?= e($accionLabels[$h['accion']] ?? $h['accion']) ?></span></td>
                        <td><?= e($lugar($h['modalidad_anterior'], $h['espacio_anterior'], $h['ubicacion_anterior'], $h['enlace_anterior'])) ?></td>
                        <td><?= e($lugar($h['modalidad_nueva'], $h['espacio_nuevo'], $h['ubicacion_nueva'], $h['enlace_nuevo'])) ?></td>
                        <td><?= e($h['responsable']) ?></td>
                        <td><?= e($h['motivo'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$historialUbicacion): ?><tr><td colspan="6" class="empty-state">Sin cambios de ubicación registrados.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
