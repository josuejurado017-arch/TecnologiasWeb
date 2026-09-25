<?php
$isEditing = ($mode ?? 'create') === 'edit';
$title = $isEditing ? 'Editar espacio' : 'Nuevo espacio';
$action = $isEditing ? app_url('espacios/edit.php?id=' . (int) $data['id_espacio']) : app_url('espacios/create.php');
require __DIR__ . '/../layouts/header.php';
?>

<main class="container narrow">
    <section class="card">
        <h1><?= e($title) ?></h1>
        <?php if (!empty($errors)): ?>
            <div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" action="<?= e($action) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="nombre">Nombre del espacio</label>
            <input id="nombre" name="nombre" type="text" minlength="3" maxlength="80" required value="<?= e($data['nombre'] ?? '') ?>" placeholder="Ej. Laboratorio de redes, Microsoft Teams">
            <p class="form-hint">Nombra una categoría de lugar, no un aula concreta: el aula exacta se registra en cada grupo.</p>

            <label for="modalidad">Modalidad</label>
            <?php if ($isEditing): ?>
                <input id="modalidad" type="text" value="<?= e(EspacioTutoria::MODALIDADES[$data['modalidad']] ?? '') ?>" disabled>
                <p class="form-hint">La modalidad no se cambia: los grupos que ya usan este espacio perderían sentido.</p>
            <?php else: ?>
                <select id="modalidad" name="modalidad">
                    <?php foreach (EspacioTutoria::MODALIDADES as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($data['modalidad'] ?? 'presencial') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <label for="descripcion">Descripción (opcional)</label>
            <input id="descripcion" name="descripcion" type="text" maxlength="255" value="<?= e($data['descripcion'] ?? '') ?>">

            <button type="submit">Guardar</button>
            <a class="button secondary" href="<?= e(app_url('espacios/')) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
