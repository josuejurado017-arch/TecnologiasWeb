<?php
$isEditing = ($mode ?? 'create') === 'edit';
$title = $isEditing ? 'Editar cuenta de acceso' : 'Nueva cuenta de acceso';
$action = $isEditing
    ? app_url('usuarios/edit.php?id=' . (int) $data['id_usuario'])
    : app_url('usuarios/create.php');
$careers = $careers ?? [];
require __DIR__ . '/../layouts/header.php';
?>

<main class="container narrow-wide">
    <section class="card">
        <h1><?= e($title) ?></h1>
        <p class="form-intro"><?= $isEditing ? 'Edita los datos de acceso de la cuenta.' : 'La cuenta y su perfil academico se crean en un solo paso, segun el rol elegido.' ?></p>

        <?php if (!empty($errors)): ?>
            <div class="alert" role="alert">
                <ul>
                    <?php foreach ($errors as $formError): ?>
                        <li><?= e($formError) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e($action) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="form-grid">
                <div>
                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" type="text" maxlength="100" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]+([ '-][A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]+)*" title="Use solo letras, espacios y guiones." required value="<?= e($data['nombre'] ?? '') ?>">
                </div>
                <div>
                    <label for="apellido">Apellido</label>
                    <input id="apellido" name="apellido" type="text" maxlength="100" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]+([ '-][A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]+)*" title="Use solo letras, espacios y guiones." required value="<?= e($data['apellido'] ?? '') ?>">
                </div>
                <div>
                    <label for="carnet_identidad">Carnet de Identidad (CI)</label>
                    <input id="carnet_identidad" name="carnet_identidad" type="text" maxlength="20" pattern="[0-9]{4,10}(?:[ -]?[0-9A-Za-z]{1,3}){0,2}" <?= $isEditing ? '' : 'required' ?> title="Numero de carnet; complemento o extension opcional (ej. 1234567 LP)." value="<?= e($data['carnet_identidad'] ?? '') ?>">
                </div>
                <div>
                    <label for="correo">Correo</label>
                    <input id="correo" name="correo" type="email" maxlength="150" required value="<?= e($data['correo'] ?? '') ?>">
                </div>
                <div>
                    <label for="usuario">Usuario</label>
                    <input id="usuario" name="usuario" type="text" minlength="4" maxlength="50" pattern="[A-Za-z0-9._-]{4,50}" title="Use entre 4 y 50 caracteres: letras, numeros, punto, guion o guion bajo." required value="<?= e($data['usuario'] ?? '') ?>">
                </div>
                <div>
                    <label for="telefono">Teléfono</label>
                    <input id="telefono" name="telefono" type="tel" inputmode="numeric" pattern="[0-9]{7,20}" maxlength="20" title="Ingrese entre 7 y 20 numeros." value="<?= e($data['telefono'] ?? '') ?>">
                </div>
                <div>
                    <label for="contrasena">Contraseña <?= $isEditing ? '(opcional)' : '' ?></label>
                    <input id="contrasena" name="contrasena" type="password" minlength="8" <?= $isEditing ? '' : 'required' ?> autocomplete="new-password" data-password-field>
                </div>
                <div>
                    <label for="confirmacion">Confirmar contraseña <?= $isEditing ? '(opcional)' : '' ?></label>
                    <input id="confirmacion" name="confirmacion" type="password" minlength="8" <?= $isEditing ? '' : 'required' ?> autocomplete="new-password" data-password-confirmation>
                </div>
                <div>
                    <label for="id_rol">Rol</label>
                    <select id="id_rol" name="id_rol" required <?= $isEditing ? '' : 'data-rol-select' ?>>
                        <option value="">Seleccione</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role['id_rol'] ?>" data-rol="<?= e($role['nombre_rol']) ?>" <?= (string) ($data['id_rol'] ?? '') === (string) $role['id_rol'] ? 'selected' : '' ?>>
                                <?= e($role['nombre_rol']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($isEditing): ?>
                    <div>
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado" required>
                            <option value="activo" <?= ($data['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="inactivo" <?= ($data['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$isEditing): ?>
                <!-- Perfil de ESTUDIANTE (se muestra si el rol es estudiante) -->
                <fieldset data-role-section="estudiante" hidden style="border:0;padding:0;margin-top:1rem;">
                    <legend class="eyebrow">Datos academicos del estudiante</legend>
                    <div class="form-grid">
                        <div>
                            <label for="id_carrera">Carrera</label>
                            <select id="id_carrera" name="id_carrera" required disabled>
                                <option value="">Seleccione</option>
                                <?php foreach ($careers as $career): ?>
                                    <option value="<?= (int) $career['id_carrera'] ?>" <?= (string) ($data['id_carrera'] ?? '') === (string) $career['id_carrera'] ? 'selected' : '' ?>><?= e($career['nombre_carrera']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="semestre">Semestre</label>
                            <input id="semestre" name="semestre" type="number" min="1" max="10" required disabled value="<?= e($data['semestre'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="registro_universitario">Registro Universitario (opcional)</label>
                            <input id="registro_universitario" name="registro_universitario" type="text" maxlength="30" pattern="[A-Za-z0-9-]{1,30}" title="Solo letras, numeros y guiones." disabled value="<?= e($data['registro_universitario'] ?? '') ?>">
                        </div>
                    </div>
                </fieldset>

                <!-- Perfil de TUTOR (se muestra si el rol es tutor) -->
                <fieldset data-role-section="tutor" hidden style="border:0;padding:0;margin-top:1rem;">
                    <legend class="eyebrow">Datos del tutor</legend>
                    <div class="form-grid">
                        <div>
                            <label for="especialidad">Especialidad</label>
                            <input id="especialidad" name="especialidad" type="text" maxlength="150" required disabled value="<?= e($data['especialidad'] ?? '') ?>">
                        </div>
                        <div class="form-full">
                            <label for="biografia">Biografía profesional (opcional)</label>
                            <textarea id="biografia" name="biografia" rows="4" maxlength="2000" disabled><?= e($data['biografia'] ?? '') ?></textarea>
                        </div>
                    </div>
                </fieldset>
            <?php endif; ?>

            <button type="submit">Guardar</button>
            <a class="button secondary" href="<?= e(app_url('usuarios/')) ?>">Cancelar</a>
        </form>
    </section>
</main>

<?php if (!$isEditing): ?>
<script>
(function () {
    var sel = document.querySelector('[data-rol-select]');
    if (!sel) return;
    var sections = document.querySelectorAll('[data-role-section]');
    function toggle() {
        var rol = sel.selectedOptions.length ? (sel.selectedOptions[0].dataset.rol || '') : '';
        sections.forEach(function (s) {
            var show = s.getAttribute('data-role-section') === rol;
            s.hidden = !show;
            s.querySelectorAll('input, select, textarea').forEach(function (f) { f.disabled = !show; });
        });
    }
    sel.addEventListener('change', toggle);
    toggle();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
