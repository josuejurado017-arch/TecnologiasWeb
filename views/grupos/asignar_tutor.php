<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$modalidadLabels = ['presencial' => 'Presencial', 'virtual' => 'Virtual', 'ambas' => 'Presencial o virtual'];
$estadoOferta = [
    'aprobado' => 'ya la dicta',
    'pendiente' => 'la ofreció: apruébala en Ofertas',
    'propuesta' => 'propuesta enviada, esperando respuesta',
    'rechazado' => 'la rechazó antes',
];
$maxGrupos = max(1, (int) ($periodo['max_grupos_tutor'] ?? TutorMateriaConfig::MAX_MATERIAS));
$turnosElegidos = is_array($input['turnos'] ?? null) ? $input['turnos'] : [];
?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Asignar tutor</h1>
            <p><strong><?= e($materia['nombre_materia']) ?></strong> · <?= (int) $esperando ?> estudiante(s) esperando. El tutor recibe una propuesta y <strong>debe aceptarla</strong> en "Mis materias"; al aceptarla, el sistema forma grupos con los estudiantes en espera.</p>
        </div>
        <a class="button secondary" href="<?= e(app_url('grupos/#demanda')) ?>">Volver a grupos</a>
    </div>

    <?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($completos): ?>
        <p class="notice-warning">Grupo(s) con cupo agotado en: <?= e(implode(', ', array_unique(array_map(static fn (array $g): string => TutorMateriaConfig::TURNOS[TutorMateriaConfig::turnoDeHora((string) $g['hora_inicio'])]['label'] ?? substr((string) $g['hora_inicio'], 0, 5), $completos)))) ?>. Propón solo los turnos que necesitan más capacidad.</p>
    <?php endif; ?>

    <?php if ($propuestas): ?>
        <p class="notice-warning" role="status">
            <?php foreach ($propuestas as $p): ?>⏳ Propuesta enviada a <strong><?= e($p['tutor']) ?></strong> el <?= e(date('d/m/Y', strtotime((string) $p['fecha_revision']))) ?>, esperando su respuesta. <?php endforeach; ?>
        </p>
    <?php endif; ?>

    <section class="card form-card">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <label for="id_tutor">Tutor</label>
            <select id="id_tutor" name="id_tutor" required>
                <option value="">Elige un tutor habilitado…</option>
                <?php foreach ($candidatos as $c): ?>
                    <?php
                     $bloqueado = in_array($c['estado_oferta'], ['aprobado', 'pendiente', 'propuesta'], true)
                         || (int) $c['materias_periodo'] >= $maxGrupos
                         || (int) $c['grupos_periodo'] >= $maxGrupos;
                     $detalle = [(int) $c['materias_periodo'] . '/' . $maxGrupos . ' materias', (int) $c['grupos_periodo'] . '/' . $maxGrupos . ' grupos'];
                    if ($c['turnos_otras']) {
                        $detalle[] = 'ya ocupa: ' . implode(', ', array_map(static fn (string $t): string => TutorMateriaConfig::TURNOS[$t]['label'] ?? $t, $c['turnos_otras']));
                    }
                    if ($c['estado_oferta'] !== null) {
                        $detalle[] = $estadoOferta[$c['estado_oferta']] ?? $c['estado_oferta'];
                    }
                    ?>
                    <option value="<?= (int) $c['id_tutor'] ?>" <?= $bloqueado ? 'disabled' : '' ?> <?= (int) ($input['id_tutor'] ?? 0) === (int) $c['id_tutor'] ? 'selected' : '' ?>>
                        <?= e($c['tutor']) ?><?= $c['especialidad'] ? ' · ' . e($c['especialidad']) : '' ?> — <?= e(implode(' · ', $detalle)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="form-hint">Entre paréntesis: su carga del período y los turnos que ya ocupa en otras materias. Evita proponerle un turno que ya ocupa: un tutor dicta una sola tutoría por turno.</p>

            <fieldset class="materia-field">
                <legend>Turnos</legend>
                <div class="chip-group chip-group-2">
                    <?php foreach (TutorMateriaConfig::TURNOS as $clave => $t): ?>
                        <label class="chip">
                            <input type="checkbox" name="turnos[]" value="<?= e($clave) ?>" <?= in_array($clave, $turnosElegidos, true) ? 'checked' : '' ?>>
                            <span><strong><?= e($t['label']) ?></strong><small><?= e(substr($t['inicio'], 0, 5)) ?>–<?= e(substr($t['fin'], 0, 5)) ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="materia-field">
                <legend>Modalidad</legend>
                <div class="chip-group chip-group-2">
                    <?php foreach ($modalidades as $m): ?>
                        <label class="chip chip-radio">
                            <input type="radio" name="modalidad" value="<?= e($m) ?>" required <?= ($input['modalidad'] ?? $modalidades[0]) === $m ? 'checked' : '' ?>>
                            <span><strong><?= e($modalidadLabels[$m] ?? $m) ?></strong></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <label for="cupo_recomendado">Cupo recomendado</label>
            <select id="cupo_recomendado" name="cupo_recomendado">
                <option value="">Sin preferencia (cupo del período)</option>
                <?php foreach (TutorMateriaConfig::CUPOS_RECOMENDADOS as $cupo): ?>
                    <option value="<?= $cupo ?>" <?= (int) ($input['cupo_recomendado'] ?? 0) === $cupo ? 'selected' : '' ?>><?= $cupo ?> estudiantes</option>
                <?php endforeach; ?>
            </select>

            <div class="form-actions">
                <button type="submit">Enviar propuesta al tutor</button>
            </div>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
