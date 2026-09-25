<?php
// Edicion unificada: datos personales de la cuenta + perfil profesional.
// La creacion de perfiles vive en Usuarios ("Nueva cuenta"); aqui no hay modo "create".
$title = 'Editar tutor';
$action = app_url('tutores/edit.php?id=' . (int) $data['id_tutor']);
$estados = ['activo' => 'Activo', 'inactivo' => 'Inactivo'];
require __DIR__ . '/../layouts/header.php';
?>

<main class="container narrow-wide">
    <section class="card">
        <h1><?= e($title) ?></h1>
        <p class="form-intro">Cuenta <strong><?= e($account['usuario']) ?></strong>. Puede corregir los datos de contacto y el perfil profesional.</p>
        <?php if (!empty($errors)): ?>
            <div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" action="<?= e($action) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <fieldset>
                <legend>Datos personales</legend>
                <div class="form-grid">
                    <div>
                        <label for="nombre">Nombres</label>
                        <input id="nombre" name="nombre" type="text" maxlength="100" required value="<?= e($data['nombre'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="apellido">Apellidos</label>
                        <input id="apellido" name="apellido" type="text" maxlength="100" required value="<?= e($data['apellido'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="correo">Correo</label>
                        <input id="correo" name="correo" type="email" maxlength="150" required value="<?= e($data['correo'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="telefono">Telefono</label>
                        <input id="telefono" name="telefono" type="text" maxlength="20" value="<?= e($data['telefono'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="carnet_identidad">Carnet de identidad</label>
                        <input id="carnet_identidad" name="carnet_identidad" type="text" maxlength="20" value="<?= e($data['carnet_identidad'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado" required>
                            <?php foreach ($estados as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($data['estado'] ?? 'activo') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Perfil profesional</legend>
                <div class="form-grid">
                    <div>
                        <label for="especialidad">Especialidad</label>
                        <input id="especialidad" name="especialidad" type="text" maxlength="150" value="<?= e($data['especialidad'] ?? '') ?>">
                    </div>
                    <div class="form-full">
                        <label for="biografia">Biografia</label>
                        <textarea id="biografia" name="biografia" rows="5" maxlength="2000"><?= e($data['biografia'] ?? '') ?></textarea>
                    </div>
                </div>
            </fieldset>

            <p class="form-hint">Las materias y horarios del tutor se configuran desde su portal, en "Mis materias".</p>

            <button type="submit">Guardar</button>
            <a class="button secondary" href="<?= e(app_url('tutores/')) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
