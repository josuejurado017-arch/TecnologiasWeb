<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container narrow-wide">
    <section class="card">
        <div class="page-heading"><div><span class="eyebrow">Seguimiento institucional</span><h1>Registrar asistencia</h1><p><?= e($tutoria['nombre_materia']) ?> · <?= e($tutoria['fecha']) ?></p></div><span class="status status-<?= e($tutoria['estado']) ?>"><?= e($tutoria['estado']) ?></span></div>
        <?php if (!empty($errors)): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="<?= e(app_url('tutorias/attendance.php?id=' . (int) $tutoria['id_tutoria'])) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $tutoria['id_tutoria'] ?>">
            <div class="form-grid">
                <div><label for="estado_asistencia">Estado de asistencia</label><select id="estado_asistencia" name="estado_asistencia" required><option value="">Seleccione</option><?php foreach (['asistio' => 'Asistio', 'no_asistio' => 'No asistio', 'parcial' => 'Asistencia parcial', 'retraso' => 'Retraso'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $data['estado_asistencia'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div><label for="minutos_retraso">Minutos de retraso</label><input id="minutos_retraso" name="minutos_retraso" type="number" min="1" max="600" value="<?= e($data['minutos_retraso']) ?>"></div>
                <div class="form-full"><label for="observaciones">Observaciones</label><textarea id="observaciones" name="observaciones" maxlength="500"><?= e($data['observaciones']) ?></textarea></div>
            </div>
            <button type="submit">Guardar asistencia</button><a class="button secondary" href="<?= e(app_url('tutorias/')) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
