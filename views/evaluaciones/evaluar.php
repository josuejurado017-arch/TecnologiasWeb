<?php
require __DIR__ . '/../layouts/header.php';
$criterios = [
    'general' => 'Calificacion general',
    'puntualidad' => 'Puntualidad',
    'dominio' => 'Dominio del tema',
    'claridad' => 'Claridad de la explicacion',
    'utilidad' => 'Utilidad del apoyo',
];
?>

<main class="container narrow">
    <section class="card">
        <h1>Evaluar tutoria</h1>
        <p class="panel-note"><strong><?= e($inscripcion['nombre_materia']) ?></strong> · Tutor <?= e($inscripcion['tutor']) ?></p>

        <?php if (!empty($errors)): ?>
            <div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" action="<?= e(app_url('evaluaciones/evaluar.php?inscripcion=' . (int) $inscripcion['id_inscripcion'])) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php foreach ($criterios as $key => $label): ?>
                <label for="<?= e($key) ?>"><?= e($label) ?> (1 a 5)</label>
                <select id="<?= e($key) ?>" name="<?= e($key) ?>" required>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= (int) ($data[$key] ?? 5) === $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            <?php endforeach; ?>
            <label for="comentario">Comentario (opcional)</label>
            <textarea id="comentario" name="comentario" rows="4" maxlength="1000"><?= e($data['comentario'] ?? '') ?></textarea>
            <button type="submit">Enviar evaluacion</button>
            <a class="button secondary" href="<?= e(app_url('mis-evaluaciones/')) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
