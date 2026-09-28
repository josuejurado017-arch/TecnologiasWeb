<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php
$hora = static fn (string $h): string => substr($h, 0, 5);
$modalidadLabels = ['presencial' => 'Presencial', 'virtual' => 'Virtual', 'ambas' => 'Presencial o virtual'];
?>
<main class="container" style="max-width: 48rem;">
    <div class="page-heading">
        <div>
            <h1>Editar oferta aprobada</h1>
            <p><strong><?= e($oferta['tutor']) ?></strong> · <?= e($oferta['nombre_materia']) ?>. La oferta sigue aprobada después del cambio y se le avisa al tutor con tu motivo.</p>
        </div>
        <a class="button secondary" href="<?= e(app_url('tutores/ofertas/#aprobadas')) ?>">Volver</a>
    </div>
    <?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ((int) $oferta['grupos'] > 0): ?>
        <p class="panel-note">El tutor tiene <?= (int) $oferta['grupos'] ?> grupo(s) vigente(s) de esta materia: no se puede quitar el turno de un grupo que ya existe.</p>
    <?php endif; ?>

    <form method="post" class="card materia-form" style="padding:1.25rem;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id_tutor" value="<?= (int) $oferta['id_tutor'] ?>">
        <input type="hidden" name="id_materia" value="<?= (int) $oferta['id_materia'] ?>">

        <fieldset class="materia-field" data-max-turnos="<?= (int) $turnosInfo['max'] ?>">
            <legend>Turnos</legend>
            <p class="form-hint">Hasta <?= (int) $turnosInfo['max'] ?> turno(s) para esta materia (tope del período, sumando sus otras materias).</p>
            <div class="chip-group chip-group-2">
                <?php foreach (TutorMateriaConfig::TURNOS as $key => $turno): ?>
                    <?php $marcado = in_array($key, $valores['turnos'], true); $bloqueo = !$marcado ? ($turnosInfo['bloqueados'][$key] ?? null) : null; ?>
                    <label class="chip<?= $bloqueo ? ' chip-bloqueado' : '' ?>">
                        <input type="checkbox" name="turnos[]" value="<?= e($key) ?>" <?= $marcado ? 'checked' : '' ?> <?= $bloqueo ? 'disabled data-bloqueado' : '' ?>>
                        <span><strong><?= e($turno['label']) ?></strong><small><?= e($hora($turno['inicio'])) ?>–<?= e($hora($turno['fin'])) ?></small>
                            <small class="chip-estado"><?= e($bloqueo ?? 'Disponible') ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="materia-field">
            <legend>Modalidad</legend>
            <div class="chip-group">
                <?php foreach ($modalidades as $m): ?>
                    <label class="chip chip-radio">
                        <input type="radio" name="modalidad" value="<?= e($m) ?>" <?= $valores['modalidad'] === $m ? 'checked' : '' ?> required>
                        <span><strong><?= e($modalidadLabels[$m] ?? $m) ?></strong></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <label>Cupo máximo
            <select name="cupo_recomendado">
                <option value="">Sin preferencia</option>
                <?php foreach (TutorMateriaConfig::CUPOS_RECOMENDADOS as $cupo): ?>
                    <option value="<?= $cupo ?>" <?= (int) $valores['cupo_recomendado'] === $cupo ? 'selected' : '' ?>><?= $cupo ?> estudiantes</option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Motivo del cambio (lo verá el tutor)
            <input name="motivo" value="<?= e($valores['motivo']) ?>" required minlength="5" maxlength="150" placeholder="Ej.: se abre un turno de noche por pedido de la carrera">
        </label>

        <button type="submit">Guardar cambios</button>
    </form>
</main>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
