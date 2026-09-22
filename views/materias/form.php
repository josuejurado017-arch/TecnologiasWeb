<?php
$isEditing = ($mode ?? 'create') === 'edit';
$title = $isEditing ? 'Editar materia' : 'Nueva materia';
$action = $isEditing ? app_url('materias/edit.php?id=' . (int) $data['id_materia']) : app_url('materias/create.php');
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
            <label for="nombre_materia">Nombre de la materia</label>
            <input id="nombre_materia" name="nombre_materia" type="text" minlength="3" maxlength="150" pattern="(?=.*[A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]).{3,}" title="Debe contener al menos una letra." required value="<?= e($data['nombre_materia'] ?? '') ?>">
            <label for="id_carrera">Carrera</label>
            <select id="id_carrera" name="id_carrera">
                <option value="">Sin carrera</option>
                <?php foreach ($carreras as $career): ?>
                    <option value="<?= (int) $career['id_carrera'] ?>" <?= (string) ($data['id_carrera'] ?? '') === (string) $career['id_carrera'] ? 'selected' : '' ?>>
                        <?= e($career['nombre_carrera']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Guardar</button>
            <a class="button secondary" href="<?= e(app_url('materias/')) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
