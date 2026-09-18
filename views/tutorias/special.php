<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container narrow-wide"><section class="card"><h1>Solicitar horario especial</h1><p class="form-intro">Proponga una fecha y horario fuera de los espacios publicados. El tutor debe aprobar la solicitud.</p>
    <?php if (!empty($errors)): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="get" action="<?= e(app_url('tutorias/especial.php')) ?>" class="slot-selector">
        <div class="form-grid">
            <div><label for="id_materia">Materia</label><select id="id_materia" name="id_materia" required onchange="this.form.submit()"><option value="">Seleccione una materia</option><?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id_materia'] ?>" <?= (int) $data['id_materia'] === (int) $subject['id_materia'] ? 'selected' : '' ?>><?= e($subject['nombre_materia']) ?></option><?php endforeach; ?></select></div>
            <div><label for="id_tutor">Tutor</label><select id="id_tutor" name="id_tutor" required <?= !$tutors ? 'disabled' : '' ?>><option value="">Seleccione un tutor</option><?php foreach ($tutors as $tutor): ?><option value="<?= (int) $tutor['id_tutor'] ?>" <?= (int) $data['id_tutor'] === (int) $tutor['id_tutor'] ? 'selected' : '' ?>><?= e($tutor['tutor']) ?></option><?php endforeach; ?></select></div>
        </div>
        <button type="submit" class="secondary">Actualizar tutor</button>
    </form>
    <?php if ($data['id_materia'] && $data['id_tutor']): ?><form method="post" action="<?= e(app_url('tutorias/especial.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id_materia" value="<?= (int) $data['id_materia'] ?>"><input type="hidden" name="id_tutor" value="<?= (int) $data['id_tutor'] ?>"><div class="form-grid">
        <div><label for="fecha_propuesta">Fecha propuesta</label><input id="fecha_propuesta" name="fecha_propuesta" type="date" min="<?= e(date('Y-m-d')) ?>" required value="<?= e($data['fecha_propuesta']) ?>"></div>
        <div><label for="modalidad">Modalidad</label><select id="modalidad" name="modalidad" required><option value="presencial" <?= $data['modalidad'] === 'presencial' ? 'selected' : '' ?>>Presencial</option><option value="virtual" <?= $data['modalidad'] === 'virtual' ? 'selected' : '' ?>>Virtual</option></select></div>
        <div><label for="hora_inicio">Hora de inicio</label><input id="hora_inicio" name="hora_inicio" type="time" required data-time-start value="<?= e($data['hora_inicio']) ?>"></div>
        <div><label for="hora_fin">Hora de fin</label><input id="hora_fin" name="hora_fin" type="time" required data-time-end value="<?= e($data['hora_fin']) ?>"></div>
        <div class="form-full"><label for="lugar_o_enlace">Lugar o enlace</label><input id="lugar_o_enlace" name="lugar_o_enlace" type="text" maxlength="200" value="<?= e($data['lugar_o_enlace']) ?>"></div>
        <div class="form-full"><label for="observaciones">Motivo u observaciones</label><textarea id="observaciones" name="observaciones" rows="4" maxlength="2000"><?= e($data['observaciones']) ?></textarea></div>
    </div><button type="submit">Enviar solicitud especial</button></form><?php endif; ?>
    <p class="form-switch"><a href="<?= e(app_url('tutorias/create.php')) ?>">Volver a espacios disponibles</a></p>
</section></main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
