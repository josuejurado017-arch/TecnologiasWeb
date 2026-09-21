<?php
$isEditing = ($mode ?? 'create') === 'edit';
$title = $isEditing ? 'Editar período' : 'Nuevo período';
$action = $isEditing ? app_url('periodos/edit.php?id=' . (int) $data['id_periodo']) : app_url('periodos/create.php');
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
            <label for="nombre">Nombre del período</label>
            <input id="nombre" name="nombre" type="text" minlength="3" maxlength="120" required value="<?= e($data['nombre'] ?? '') ?>" placeholder="Ej. Tutorias Invierno 2027">
            <div class="form-grid">
                <div>
                    <label for="fecha_inicio">Fecha de inicio</label>
                    <input id="fecha_inicio" name="fecha_inicio" type="date" required value="<?= e($data['fecha_inicio'] ?? '') ?>">
                </div>
                <div>
                    <label for="fecha_fin">Fecha de fin</label>
                    <input id="fecha_fin" name="fecha_fin" type="date" required value="<?= e($data['fecha_fin'] ?? '') ?>">
                </div>
                <div>
                    <label for="cupo_min_grupo">Cupo minimo por grupo</label>
                    <input id="cupo_min_grupo" name="cupo_min_grupo" type="number" min="1" max="100" required value="<?= e((string) ($data['cupo_min_grupo'] ?? 3)) ?>">
                </div>
                <div>
                    <label for="cupo_max_default">Cupo maximo por defecto</label>
                    <input id="cupo_max_default" name="cupo_max_default" type="number" min="1" max="200" required value="<?= e((string) ($data['cupo_max_default'] ?? 20)) ?>">
                </div>
            </div>
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <?php foreach (['borrador' => 'Borrador', 'activa' => 'Activa', 'cerrada' => 'Cerrada'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($data['estado'] ?? 'borrador') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Guardar</button>
            <a class="button secondary" href="<?= e(app_url('periodos/')) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
