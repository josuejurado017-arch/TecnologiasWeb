<?php require __DIR__ . '/../layouts/header.php'; ?>

<?php
$resultLabels = [
    'asignado' => ['Asignado a un grupo', 'success'],
    'grupo_creado' => ['Grupo creado y asignado', 'success'],
    'ya_solicitada' => ['Ya solicitada', 'info'],
    'lista_espera' => ['En lista de espera', 'warning'],
];
?>
<main class="container narrow-wide">
    <section class="card">
        <h1>Solicitar apoyo académico</h1>
        <p class="form-intro">Selecciona las materias en las que necesitas apoyo. El sistema te asignará automáticamente un grupo con tutor, aula y horario.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if (!$periodo): ?>
            <p class="alert" role="alert">No hay un período de tutoría activo. Vuelve cuando el administrador active uno.</p>
        <?php else: ?>
            <p class="panel-note">Período activo: <strong><?= e($periodo['nombre']) ?></strong> (<?= e($periodo['fecha_inicio']) ?> al <?= e($periodo['fecha_fin']) ?>)</p>
        <?php endif; ?>

        <?php if ($results !== null): ?>
            <div class="card" style="margin:1rem 0;padding:1rem;">
                <h2>Resultado de tu solicitud</h2>
                <ul class="result-list">
                    <?php foreach ($results as $r): ?>
                        <?php [$label, $tone] = $resultLabels[$r['resultado']] ?? ['Procesado', 'info']; ?>
                        <li>
                            <span class="badge badge-<?= e($tone) ?>"><?= e($label) ?></span>
                            <strong><?= e($r['nombre']) ?></strong>
                            <small><?= e($r['detalle']) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a class="button" href="<?= e(app_url('mis-tutorias/')) ?>">Ver mis tutorias</a>
            </div>
        <?php endif; ?>

        <?php if ($periodo && $subjects): ?>
            <form method="post" action="<?= e(app_url('tutorias/create.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <fieldset class="checkbox-grid">
                    <legend>Materias disponibles</legend>
                    <?php foreach ($subjects as $subject): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="materias[]" value="<?= (int) $subject['id_materia'] ?>">
                            <span><strong><?= e($subject['nombre_materia']) ?></strong><?php if (!empty($subject['nombre_carrera'])): ?><small><?= e($subject['nombre_carrera']) ?></small><?php endif; ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <button type="submit">Solicitar apoyo</button>
                <a class="button secondary" href="<?= e(app_url('mis-tutorias/')) ?>">Mis tutorías</a>
            </form>
        <?php elseif ($periodo && !$subjects && $results === null): ?>
            <p class="empty-state">No hay materias disponibles para solicitar en este momento (o ya solicitaste todas las que tienen oferta).</p>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
