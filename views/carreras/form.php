<?php
$isEditing = ($mode ?? 'create') === 'edit';
$title = $isEditing ? 'Editar carrera' : 'Nueva carrera';
$action = $isEditing ? app_url('carreras/edit.php?id=' . (int) $data['id_carrera']) : app_url('carreras/create.php');
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
            <label for="nombre_carrera">Nombre de la carrera</label>
            <input id="nombre_carrera" name="nombre_carrera" type="text" minlength="3" maxlength="150" pattern="(?=.*[A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]).{3,}" title="Debe contener al menos una letra." required value="<?= e($data['nombre_carrera'] ?? '') ?>">
            <button type="submit">Guardar</button>
            <a class="button secondary" href="<?= e(app_url('carreras/')) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
