<?php
// Edicion unificada: datos personales de la cuenta + expediente academico.
// La creacion de perfiles vive en Usuarios ("Nueva cuenta"), que crea la cuenta
// y el perfil en una sola transaccion; por eso aqui no hay modo "create".
$title = 'Editar estudiante';
$action = app_url('estudiantes/edit.php?id=' . (int) $data['id_estudiante']);
$estados = ['activo' => 'Activo', 'inactivo' => 'Inactivo'];
require __DIR__ . '/../layouts/header.php';
?>

<main class="container narrow-wide">
    <section class="card">
        <h1><?= e($title) ?></h1>
        <p class="form-intro">Cuenta <strong><?= e($account['usuario']) ?></strong>. Puede corregir los datos personales y el expediente academico.</p>
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
                <legend>Datos academicos</legend>
                <div class="form-grid">
                    <div>
                        <label for="id_carrera">Carrera</label>
                        <select id="id_carrera" name="id_carrera" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($options['careers'] as $career): ?>
                                <option value="<?= (int) $career['id_carrera'] ?>" <?= (string) ($data['id_carrera'] ?? '') === (string) $career['id_carrera'] ? 'selected' : '' ?>><?= e($career['nombre_carrera']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="semestre">Semestre</label>
                        <input id="semestre" name="semestre" type="number" min="1" max="10" required value="<?= e($data['semestre'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="registro_universitario">Registro universitario</label>
                        <input id="registro_universitario" name="registro_universitario" type="text" maxlength="30" pattern="[A-Za-z0-9-]{1,30}" title="Use solo letras, numeros y guiones." value="<?= e($data['registro_universitario'] ?? '') ?>">
                    </div>
                </div>
            </fieldset>

            <button type="submit">Guardar</button>
            <a class="button secondary" href="<?= e(app_url('estudiantes/')) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
