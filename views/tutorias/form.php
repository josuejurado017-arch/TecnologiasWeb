<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container narrow-wide"><section class="card"><h1>Nueva solicitud de tutoria</h1><p class="form-intro">Seleccione un espacio publicado por el tutor. La fecha y el horario se completan automaticamente.</p>
    <?php if (!empty($errors)): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form class="slot-selector" method="get" action="<?= e(app_url('tutorias/create.php')) ?>">
        <div class="form-grid">
            <div><label for="id_materia">1. Materia</label><select id="id_materia" name="id_materia" required onchange="this.form.submit()"><option value="">Seleccione una materia</option><?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id_materia'] ?>" <?= (int) $selectedMatter === (int) $subject['id_materia'] ? 'selected' : '' ?>><?= e($subject['nombre_materia']) ?></option><?php endforeach; ?></select><?php if (!$subjects): ?><small>No hay materias con tutores y disponibilidad activa.</small><?php endif; ?></div>
            <div><label for="id_tutor">2. Tutor</label><select id="id_tutor" name="id_tutor" required onchange="this.form.submit()" <?= !$tutors ? 'disabled' : '' ?>><option value="">Seleccione un tutor</option><?php foreach ($tutors as $tutor): ?><option value="<?= (int) $tutor['id_tutor'] ?>" <?= (int) $selectedTutor === (int) $tutor['id_tutor'] ? 'selected' : '' ?>><?= e($tutor['tutor']) ?></option><?php endforeach; ?></select><?php if ($selectedMatter && !$tutors): ?><small>No hay tutores habilitados para esta materia.</small><?php endif; ?></div>
            <div class="form-full"><label for="slot_key">3. Espacio disponible</label><select id="slot_key" name="slot_key" required <?= !$slots ? 'disabled' : '' ?>><option value="">Seleccione fecha y horario</option><?php foreach ($slots as $slot): ?><option value="<?= e($slot['slot_key']) ?>" <?= ($data['slot_key'] ?? '') === $slot['slot_key'] ? 'selected' : '' ?>><?= e($slot['dia_semana'] . ' ' . $slot['fecha'] . ' | ' . $slot['hora_inicio'] . ' - ' . $slot['hora_fin']) ?></option><?php endforeach; ?></select><?php if ($selectedTutor && !$slots): ?><small>No hay espacios disponibles en los proximos 42 dias.</small><?php endif; ?></div>
        </div>
        <button type="submit" class="secondary">Actualizar espacios</button>
    </form>
    <?php if ($selectedSlot): ?><div class="selected-slot"><strong>Espacio seleccionado</strong><span><?= e($selectedSlot['dia_semana'] . ' ' . $selectedSlot['fecha'] . ' | ' . $selectedSlot['hora_inicio'] . ' - ' . $selectedSlot['hora_fin']) ?></span></div><?php endif; ?>
    <?php if ($selectedMatter && $selectedTutor && $selectedSlot): ?><form method="post" action="<?= e(app_url('tutorias/create.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id_materia" value="<?= (int) $selectedMatter ?>"><input type="hidden" name="id_tutor" value="<?= (int) $selectedTutor ?>"><input type="hidden" name="slot_key" value="<?= e($selectedSlot['slot_key']) ?>"><div class="form-grid">
        <div><label for="modalidad">Modalidad</label><select id="modalidad" name="modalidad" required><option value="presencial" <?= ($data['modalidad'] ?? '') === 'presencial' ? 'selected' : '' ?>>Presencial</option><option value="virtual" <?= ($data['modalidad'] ?? '') === 'virtual' ? 'selected' : '' ?>>Virtual</option></select></div>
        <div class="form-full"><label for="lugar_o_enlace">Lugar o enlace</label><input id="lugar_o_enlace" name="lugar_o_enlace" type="text" maxlength="200" value="<?= e($data['lugar_o_enlace'] ?? '') ?>"></div>
        <div class="form-full"><label for="observaciones">Observaciones</label><textarea id="observaciones" name="observaciones" rows="4" maxlength="2000"><?= e($data['observaciones'] ?? '') ?></textarea></div>
    </div><button type="submit">Enviar solicitud</button></form><?php endif; ?>
    <p class="form-switch"><a href="<?= e(app_url('tutorias/especial.php')) ?>">Solicitar horario especial</a><span>Si necesitas una fecha u hora fuera de los espacios publicados.</span></p>
    <a class="button secondary" href="<?= e(app_url('tutorias/')) ?>">Cancelar</a>
</section></main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
