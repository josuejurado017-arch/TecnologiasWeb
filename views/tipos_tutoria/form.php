<?php
$isEditing = ($mode ?? 'create') === 'edit';
$title = $isEditing ? 'Editar tipo de tutoría' : 'Nuevo tipo de tutoría';
$action = $isEditing ? app_url('tipos-tutoria/edit.php?id=' . (int) $data['id_tipo_tutoria']) : app_url('tipos-tutoria/create.php');
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
            <label for="nombre">Nombre del tipo</label>
            <input id="nombre" name="nombre" type="text" minlength="3" maxlength="80" required value="<?= e($data['nombre'] ?? '') ?>" placeholder="Ej. Postgrado, Nivelación, Verano">

            <label for="descripcion">Descripción (opcional)</label>
            <input id="descripcion" name="descripcion" type="text" maxlength="255" value="<?= e($data['descripcion'] ?? '') ?>">

            <label for="duracion_max_dias">Duración máxima de sus períodos (días)</label>
            <input id="duracion_max_dias" name="duracion_max_dias" type="number" min="1" max="<?= TiposTutoriaController::DURACION_MAXIMA_PERMITIDA ?>" value="<?= e((string) ($data['duracion_max_dias'] ?? '')) ?>" aria-describedby="duracion_ayuda">
            <p class="form-hint" id="duracion_ayuda">Déjalo vacío si los períodos de este tipo no tienen tope. Un período puede durar menos que este máximo (no tiene que ocupar todo el mes).<?= $isEditing ? ' El cambio aplica a los períodos que se creen o editen desde ahora.' : '' ?></p>

            <button type="submit">Guardar</button>
            <a class="button secondary" href="<?= e(app_url('tipos-tutoria/')) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
