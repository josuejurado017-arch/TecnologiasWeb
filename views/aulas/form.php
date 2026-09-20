<?php
$isEditing = ($mode ?? 'create') === 'edit';
$title = $isEditing ? 'Editar aula' : 'Nueva aula';
$action = $isEditing ? app_url('aulas/edit.php?id=' . (int) $data['id_aula']) : app_url('aulas/create.php');
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
            <label for="nombre">Nombre del aula</label>
            <input id="nombre" name="nombre" type="text" minlength="3" maxlength="120" required value="<?= e($data['nombre'] ?? '') ?>" placeholder="Ej. Aula 204 o Sala Virtual UPDS 1">
            <div class="form-grid">
                <div>
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo" data-aula-tipo>
                        <option value="fisica" <?= ($data['tipo'] ?? 'fisica') === 'fisica' ? 'selected' : '' ?>>Fisica</option>
                        <option value="virtual" <?= ($data['tipo'] ?? '') === 'virtual' ? 'selected' : '' ?>>Virtual</option>
                    </select>
                </div>
                <div>
                    <label for="capacidad">Capacidad</label>
                    <input id="capacidad" name="capacidad" type="number" min="1" max="500" required value="<?= e((string) ($data['capacidad'] ?? 20)) ?>">
                </div>
            </div>
            <label for="ubicacion">Ubicacion (aula fisica)</label>
            <input id="ubicacion" name="ubicacion" type="text" maxlength="200" value="<?= e($data['ubicacion'] ?? '') ?>" placeholder="Ej. Bloque A - Segundo piso">
            <div class="form-grid">
                <div>
                    <label for="plataforma">Plataforma (sala virtual)</label>
                    <input id="plataforma" name="plataforma" type="text" maxlength="100" value="<?= e($data['plataforma'] ?? '') ?>" placeholder="Ej. Google Meet, Zoom">
                </div>
                <div>
                    <label for="enlace">Enlace (sala virtual)</label>
                    <input id="enlace" name="enlace" type="url" maxlength="300" value="<?= e($data['enlace'] ?? '') ?>" placeholder="https://...">
                </div>
            </div>
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <option value="activa" <?= ($data['estado'] ?? 'activa') === 'activa' ? 'selected' : '' ?>>Activa</option>
                <option value="inactiva" <?= ($data['estado'] ?? '') === 'inactiva' ? 'selected' : '' ?>>Inactiva</option>
            </select>
            <button type="submit">Guardar</button>
            <a class="button secondary" href="<?= e(app_url('aulas/')) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
