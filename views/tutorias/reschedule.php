<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container narrow-wide">
    <section class="card">
        <div class="page-heading"><div><span class="eyebrow">Gestion de agenda</span><h1>Reprogramar tutoria</h1><p>Los cambios quedaran registrados en el historial.</p></div><span class="status status-<?= e($current['estado']) ?>"><?= e($current['estado']) ?></span></div>
        <?php if (!empty($errors)): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="<?= e(app_url('tutorias/reschedule.php?id=' . (int) $current['id_tutoria'])) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $current['id_tutoria'] ?>">
            <div class="form-grid">
                <div><label for="id_tutor">Tutor</label><?php if ($role === 'administrador'): ?><select id="id_tutor" name="id_tutor" required><?php foreach ($tutors as $tutor): ?><option value="<?= (int) $tutor['id_tutor'] ?>" <?= (string) $data['id_tutor'] === (string) $tutor['id_tutor'] ? 'selected' : '' ?>><?= e($tutor['tutor']) ?></option><?php endforeach; ?></select><?php else: ?><input type="hidden" name="id_tutor" value="<?= (int) $current['id_tutor'] ?>"><input id="id_tutor" type="text" readonly value="<?= e($current['tutor']) ?>"><?php endif; ?></div>
                <div><label for="fecha">Nueva fecha</label><input id="fecha" name="fecha" type="date" min="<?= e(date('Y-m-d')) ?>" required value="<?= e($data['fecha']) ?>"></div>
                <div><label for="hora_inicio">Hora de inicio</label><input id="hora_inicio" name="hora_inicio" type="time" required data-time-start value="<?= e($data['hora_inicio']) ?>"></div>
                <div><label for="hora_fin">Hora de fin</label><input id="hora_fin" name="hora_fin" type="time" required data-time-end value="<?= e($data['hora_fin']) ?>"></div>
                <div><label for="modalidad">Modalidad</label><select id="modalidad" name="modalidad" required><option value="presencial" <?= $data['modalidad'] === 'presencial' ? 'selected' : '' ?>>Presencial</option><option value="virtual" <?= $data['modalidad'] === 'virtual' ? 'selected' : '' ?>>Virtual</option></select></div>
                <div><label for="lugar_o_enlace">Lugar o enlace</label><input id="lugar_o_enlace" name="lugar_o_enlace" type="text" maxlength="200" value="<?= e($data['lugar_o_enlace']) ?>"></div>
                <div class="form-full"><label for="motivo">Motivo de reprogramacion</label><textarea id="motivo" name="motivo" maxlength="500" required><?= e($data['motivo']) ?></textarea></div>
            </div>
            <button type="submit">Guardar cambios</button><a class="button secondary" href="<?= e(app_url('tutorias/')) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
