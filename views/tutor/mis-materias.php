<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$modalidadLabels = ['presencial' => 'Presencial', 'virtual' => 'Virtual', 'ambas' => 'Ambas'];
$estadoMeta = [
    'aprobado' => ['Aprobada', 'aprobado'],
    'pendiente' => ['En revisión', 'pendiente'],
    'rechazado' => ['Rechazada', 'rechazado'],
    'propuesta' => ['Te la proponen', 'pendiente'],
];
$hora = static fn (string $h): string => substr($h, 0, 5);
?>

<main class="container materias-page">
    <div class="page-heading">
        <div>
            <h1>Mis materias</h1>
            <p>Renueva tu oferta en cada período (máximo dos materias y dos grupos). La coordinación revisa tus turnos y modalidad antes de formar grupos.</p>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($bloqueoHabilitacion !== null): ?>
        <p class="alert" role="status"><?= e($bloqueoHabilitacion) ?> Mientras tanto puedes completar <a href="<?= e(app_url('mi-perfil-tutor/')) ?>">tu perfil</a>.</p>
        <?php if ($subjects): ?>
            <ul class="plain-list">
                <?php foreach ($subjects as $subject): ?>
                    <li><?= e($subject['nombre_materia']) ?> <small>· <?= e($subject['nombre_carrera'] ?: 'General') ?></small></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php else: ?>
    <?php if (!$hasSchedule && $subjects): ?>
        <p class="notice-warning materias-notice" role="status">No podrás recibir grupos hasta configurar los turnos de al menos una de tus materias.</p>
    <?php endif; ?>

    <div class="materias-toolbar">
        <?php if ($availableSubjects): ?>
            <form method="post" action="<?= e(app_url('tutor/mis_materias/agregar.php')) ?>" class="materias-add">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="id_materia" class="sr-only">Materia</label>
                <select id="id_materia" name="id_materia" required>
                    <option value="">Agregar una materia…</option>
                    <?php foreach ($availableSubjects as $subject): ?>
                        <option value="<?= (int) $subject['id_materia'] ?>"><?= e($subject['nombre_materia'] . ' · ' . ($subject['nombre_carrera'] ?: 'General')) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Agregar</button>
            </form>
        <?php else: ?>
            <p class="materias-muted">No hay más materias para agregar en este período, o ya alcanzaste el límite de dos ofertas.</p>
        <?php endif; ?>

        <ul class="frecuencia-regla" aria-label="Cómo se definen los días de cada grupo">
            <li><strong>Menos de <?= Grupo::UMBRAL_GRUPO_NORMAL ?> estudiantes</strong> Grupo reducido: LMV o MJS</li>
            <li><strong><?= Grupo::UMBRAL_GRUPO_NORMAL ?> o más</strong> Grupo normal: lunes a viernes</li>
            <li><strong>Los días</strong> los define la demanda y la coordinación, no tú</li>
        </ul>
    </div>

    <?php if (!$subjects): ?>
        <p class="empty-state">Todavía no tienes materias. Agrega la primera con el selector de arriba.</p>
    <?php else: ?>
        <div class="materia-grid">
            <?php foreach ($subjects as $subject): ?>
                <?php
                $config = $subject['config'];
                $materiaId = (int) $subject['id_materia'];
                $requerida = (string) $subject['modalidad_requerida'];
                $permitidas = TutorMateriaConfig::modalidadesPermitidas($requerida);
                $modalidadActual = in_array($config['modalidad'] ?? 'ambas', $permitidas, true) ? ($config['modalidad'] ?? 'ambas') : $permitidas[0];

                if ($config['modalidad_incompatible']) {
                    [$estadoLabel, $estadoTono] = ['Modalidad incompatible', 'rechazado'];
                } elseif (!$config['configured']) {
                    [$estadoLabel, $estadoTono] = ['Sin configurar', 'sin-configurar'];
                } else {
                    [$estadoLabel, $estadoTono] = $estadoMeta[$config['estado']] ?? ['Aprobada', 'aprobado'];
                }
                ?>
                <article class="materia-card materia-<?= e($estadoTono) ?>" id="materia-<?= $materiaId ?>">
                    <header class="materia-card-head">
                        <div>
                            <h2><?= e($subject['nombre_materia']) ?></h2>
                            <small><?= e($subject['nombre_carrera'] ?: 'General') ?><?= $requerida !== 'libre' ? ' · solo ' . e($requerida) : '' ?></small>
                        </div>
                     <span class="materia-estado"><?= e($estadoLabel) ?></span>
                    </header>
                    <p class="materia-nota">Turnos de esta materia en el período:
                        <?php foreach (TutorMateriaConfig::TURNOS as $clave => $turno): ?>
                            <span><?= e($turno['label']) ?>: <?= e(isset($subject['cobertura'][$clave]) ? implode(', ', $subject['cobertura'][$clave]) : 'libre') ?>.</span>
                        <?php endforeach; ?>
                    </p>

                    <?php if ($config['modalidad_incompatible']): ?>
                        <p class="materia-aviso">La materia se dicta solo en modalidad <?= e($requerida) ?>. Ajusta la modalidad para volver a recibir grupos.</p>
                    <?php elseif ($config['configured'] && $config['estado'] === 'rechazado'): ?>
                        <p class="materia-aviso">La coordinación no aprobó esta oferta<?= $config['motivo_rechazo'] ? ': ' . e($config['motivo_rechazo']) : '' ?>. Puedes ajustarla y guardarla de nuevo.</p>
                    <?php elseif ($config['configured'] && $config['estado'] === 'pendiente'): ?>
                        <p class="materia-nota">Esperando la revisión de la coordinación. Aún no recibirás grupos en esta materia.</p>
                    <?php elseif ($config['estado'] === 'propuesta'): ?>
                        <div class="materia-nota">
                            <p><strong>La coordinación te propone dictar esta materia</strong>: <?= e(implode(', ', array_map(static fn (string $t): string => TutorMateriaConfig::TURNOS[$t]['label'] ?? $t, $config['turnos']))) ?> · <?= e($modalidadLabels[$config['modalidad']] ?? $config['modalidad']) ?><?= $config['cupo_recomendado'] ? ' · cupo ' . (int) $config['cupo_recomendado'] : '' ?>. Hay estudiantes esperando. No recibirás grupos hasta que respondas.</p>
                            <form method="post" action="<?= e(app_url('tutor/mis_materias/responder.php')) ?>" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id_materia" value="<?= $materiaId ?>">
                                <input type="hidden" name="respuesta" value="aceptar">
                                <button type="submit">Aceptar</button>
                            </form>
                            <details>
                                <summary>Rechazar</summary>
                                <form method="post" action="<?= e(app_url('tutor/mis_materias/responder.php')) ?>" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id_materia" value="<?= $materiaId ?>">
                                    <input type="hidden" name="respuesta" value="rechazar">
                                    <input name="motivo" required minlength="10" maxlength="300" placeholder="Motivo (lo verá la coordinación)" aria-label="Motivo del rechazo">
                                    <button type="submit" class="secondary">Rechazar</button>
                                </form>
                            </details>
                            <p class="form-hint">Si prefieres otros turnos, ajústalos abajo y guarda: tu cambio vuelve a la coordinación para su revisión.</p>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(app_url('tutor/mis_materias/configurar.php')) ?>" class="materia-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id_materia" value="<?= $materiaId ?>">

                        <div class="materia-form-grid">
                            <fieldset class="materia-field">
                                <legend>Turnos</legend>
                                <div class="chip-group chip-group-2">
                                    <?php foreach (TutorMateriaConfig::TURNOS as $key => $turno): ?>
                                        <label class="chip">
                                            <input type="checkbox" name="turnos[]" value="<?= e($key) ?>" <?= in_array($key, $config['turnos'], true) ? 'checked' : '' ?>>
                                            <span><strong><?= e($turno['label']) ?></strong><small><?= e($hora($turno['inicio'])) ?>–<?= e($hora($turno['fin'])) ?></small></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>

                            <fieldset class="materia-field">
                                <legend>Modalidad</legend>
                                <div class="chip-group">
                                    <?php foreach ($modalidadLabels as $value => $label): ?>
                                        <?php if (!in_array($value, $permitidas, true)) { continue; } ?>
                                        <label class="chip chip-radio">
                                            <input type="radio" name="modalidad" value="<?= e($value) ?>" <?= $modalidadActual === $value ? 'checked' : '' ?> required>
                                            <span><strong><?= e($label) ?></strong></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>

                        </div>

                        <footer class="materia-card-foot">
                            <div class="materia-cupo">
                                <label for="cupo_recomendado_<?= $materiaId ?>">Cupo máximo</label>
                                <select id="cupo_recomendado_<?= $materiaId ?>" name="cupo_recomendado">
                                    <option value="" <?= $config['cupo_recomendado'] === null ? 'selected' : '' ?>>Sin preferencia</option>
                                    <?php foreach (TutorMateriaConfig::CUPOS_RECOMENDADOS as $cupo): ?>
                                        <option value="<?= $cupo ?>" <?= $config['cupo_recomendado'] === $cupo ? 'selected' : '' ?>><?= $cupo ?> estudiantes</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="materia-actions">
                                <button type="submit" class="link-button" form="quitar-<?= $materiaId ?>">Quitar</button>
                                <button type="submit" class="small">Guardar</button>
                            </div>
                        </footer>
                    </form>
                    <form method="post" action="<?= e(app_url('tutor/mis_materias/eliminar.php')) ?>" id="quitar-<?= $materiaId ?>" onsubmit="return confirm('¿Quitar esta materia de tu perfil?');">
                        <input type="hidden" name="id_materia" value="<?= $materiaId ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php endif; /* bloqueoHabilitacion */ ?>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
