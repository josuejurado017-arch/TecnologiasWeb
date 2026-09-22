<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading"><div><h1>Mis materias</h1><p>Agrega las materias que puedes atender y configura tus preferencias de horario.</p></div></div>
    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if (!$hasSchedule): ?>
        <p class="banner-warning" role="status">No podrás recibir grupos de tutoría hasta configurar los horarios (turnos y días) de al menos una de tus materias.</p>
    <?php endif; ?>

    <section class="card form-card">
        <h2>Agregar materia</h2>
        <p class="form-hint">La materia se agregara inmediatamente a tu perfil profesional.</p>
        <?php if ($availableSubjects): ?>
            <form method="post" action="<?= e(app_url('tutor/mis_materias/agregar.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-grid">
                    <div>
                        <label for="id_materia">Materia</label>
                        <select id="id_materia" name="id_materia" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($availableSubjects as $subject): ?>
                                <option value="<?= (int) $subject['id_materia'] ?>"><?= e($subject['nombre_materia'] . ' - ' . ($subject['nombre_carrera'] ?: 'General')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit">Agregar materia</button>
            </form>
        <?php else: ?>
            <p class="empty-state">Ya tienes todas las materias del catalogo asignadas.</p>
        <?php endif; ?>
    </section>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Materia</th><th>Carrera</th><th>Configuración</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($subjects as $subject): ?>
                    <?php
                        $config = $subject['config'];
                        $materiaId = (int) $subject['id_materia'];
                        $resumenPartes = [];
                        if ($config['turnos']) {
                            $turnosLabels = array_map(
                                static fn (string $t): string => TutorMateriaConfig::TURNOS[$t]['label'] ?? $t,
                                $config['turnos']
                            );
                            $patronLabel = $config['patron'] === 'uno'
                                ? ($config['patron_dia'] ?? 'Un día por semana')
                                : (TutorMateriaConfig::PATRONES[$config['patron']]['label'] ?? $config['patron']);
                            $resumenPartes[] = implode(' y ', $turnosLabels) . ' · ' . $patronLabel;
                        }
                        if ($config['disponible_sabados']) {
                            $resumenPartes[] = 'Sábados';
                        }
                        $modalidadLabels = ['presencial' => 'Presencial', 'virtual' => 'Virtual', 'ambas' => 'Ambas'];
                    ?>
                    <tr>
                        <td><?= e($subject['nombre_materia']) ?></td>
                        <td><?= e($subject['nombre_carrera'] ?: 'General') ?></td>
                        <td>
                            <?php if ($config['configured']): ?>
                                <span class="status status-configurada">Configurada</span>
                                <p class="form-hint"><?= e(implode(' · ', $resumenPartes)) ?> · <?= e($modalidadLabels[$config['modalidad']] ?? '') ?><?= $config['cupo_recomendado'] ? ' · Cupo ' . (int) $config['cupo_recomendado'] : '' ?></p>
                            <?php else: ?>
                                <span class="status status-sin-configurar">⚠ Sin configurar</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <details<?= $openMateriaId === $materiaId ? ' open' : '' ?>>
                                <summary><?= $config['configured'] ? 'Editar' : 'Configurar' ?></summary>
                                <form method="post" action="<?= e(app_url('tutor/mis_materias/configurar.php')) ?>" class="config-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id_materia" value="<?= $materiaId ?>">

                                    <fieldset>
                                        <legend>Turnos</legend>
                                        <p class="form-hint">Marca los turnos en los que puedes atender esta materia.</p>
                                        <?php foreach (TutorMateriaConfig::TURNOS as $key => $turno): ?>
                                            <label class="checkbox-option">
                                                <input type="checkbox" name="turnos[]" value="<?= e($key) ?>" <?= in_array($key, $config['turnos'], true) ? 'checked' : '' ?>>
                                                <?= e($turno['label']) ?> (<?= e(substr($turno['inicio'], 0, 5)) ?> - <?= e(substr($turno['fin'], 0, 5)) ?>)
                                            </label>
                                        <?php endforeach; ?>
                                    </fieldset>

                                    <fieldset>
                                        <legend>Patrón semanal</legend>
                                        <p class="form-hint">Los turnos marcados aplican a estos días. El sábado se configura aparte.</p>
                                        <?php foreach (TutorMateriaConfig::PATRONES as $key => $patron): ?>
                                            <label class="checkbox-option">
                                                <input type="radio" name="patron" value="<?= e($key) ?>" <?= ($config['patron'] ?? 'diario') === $key ? 'checked' : '' ?> data-patron required>
                                                <?= e($patron['label']) ?>
                                            </label>
                                        <?php endforeach; ?>
                                        <div data-patron-dia<?= ($config['patron'] ?? 'diario') === 'uno' ? '' : ' hidden' ?>>
                                            <label for="patron_dia_<?= $materiaId ?>">¿Qué día?</label>
                                            <select id="patron_dia_<?= $materiaId ?>" name="patron_dia">
                                                <?php foreach (TutorMateriaConfig::DIAS_HABILES as $dia): ?>
                                                    <option value="<?= e($dia) ?>" <?= ($config['patron_dia'] ?? '') === $dia ? 'selected' : '' ?>><?= e($dia) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </fieldset>

                                    <fieldset>
                                        <legend>Modalidad</legend>
                                        <?php foreach ($modalidadLabels as $value => $label): ?>
                                            <label class="checkbox-option">
                                                <input type="radio" name="modalidad" value="<?= e($value) ?>" <?= ($config['modalidad'] ?? 'ambas') === $value ? 'checked' : '' ?> required>
                                                <?= e($label) ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </fieldset>

                                    <fieldset>
                                        <legend>Disponibilidad sábados</legend>
                                        <label class="checkbox-option">
                                            <input type="checkbox" name="disponible_sabados" value="1" data-sabados-toggle <?= $config['disponible_sabados'] ? 'checked' : '' ?>>
                                            Disponible sábados
                                        </label>
                                        <div data-sabados-panel>
                                            <?php foreach (TutorMateriaConfig::FRANJAS_SABADO as $franja => $rango): ?>
                                                <label class="checkbox-option">
                                                    <input type="checkbox" name="sabados_franjas[]" value="<?= e($franja) ?>" <?= in_array($franja, $config['sabados_franjas'], true) ? 'checked' : '' ?>>
                                                    <?= e($franja) ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>

                                    <div class="form-grid">
                                        <div>
                                            <label for="cupo_recomendado_<?= $materiaId ?>">Capacidad máxima recomendada (opcional)</label>
                                            <select id="cupo_recomendado_<?= $materiaId ?>" name="cupo_recomendado">
                                                <option value="" <?= $config['cupo_recomendado'] === null ? 'selected' : '' ?>>Sin preferencia</option>
                                                <?php foreach (TutorMateriaConfig::CUPOS_RECOMENDADOS as $cupo): ?>
                                                    <option value="<?= $cupo ?>" <?= $config['cupo_recomendado'] === $cupo ? 'selected' : '' ?>><?= $cupo ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <button type="submit">Guardar configuración</button>
                                </form>
                            </details>
                            <form method="post" action="<?= e(app_url('tutor/mis_materias/eliminar.php')) ?>" onsubmit="return confirm('Quitar esta materia de tu perfil?');">
                                <input type="hidden" name="id_materia" value="<?= $materiaId ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="link-button" type="submit">Quitar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$subjects): ?><tr><td colspan="4">Todavia no tienes materias asignadas.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
