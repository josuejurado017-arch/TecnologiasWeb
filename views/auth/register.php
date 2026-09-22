<?php
$title = 'Crear cuenta de estudiante';
require __DIR__ . '/../layouts/header.php';
?>

<main class="auth-container auth-register-container">
    <section class="auth-intro">
        <div><span class="brand-mark">TA</span></div>
        <div>
            <span class="hero-kicker">Registro estudiantil</span>
            <h1>Tu apoyo académico comienza aquí.</h1>
            <p>Crea tu cuenta y accede de inmediato. Luego solo eliges las materias donde necesitas apoyo y el sistema te asigna un grupo de tutoría.</p>
        </div>
        <ul class="auth-points">
            <li>Tu cuenta se crea con rol estudiante.</li>
            <li>Acceso inmediato, sin esperas.</li>
        </ul>
    </section>

    <section class="auth-form-panel auth-register-panel">
        <div class="card">
            <span class="eyebrow">Cuenta de estudiante</span>
            <h2>Crear cuenta</h2>
            <p>Los campos académicos se guardan junto con tu perfil.</p>
            <?php if (!empty($errors)): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post" action="<?= e(app_url('register.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-grid">
                    <div><label for="nombre">Nombre</label><input id="nombre" name="nombre" type="text" maxlength="100" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]+([ '-][A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]+)*" title="Use solo letras, espacios y guiones." required value="<?= e($data['nombre']) ?>"></div>
                    <div><label for="apellido">Apellido</label><input id="apellido" name="apellido" type="text" maxlength="100" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]+([ '-][A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]+)*" title="Use solo letras, espacios y guiones." required value="<?= e($data['apellido']) ?>"></div>
                    <div><label for="correo">Correo</label><input id="correo" name="correo" type="email" maxlength="150" required value="<?= e($data['correo']) ?>"></div>
                    <div><label for="telefono">Teléfono</label><input id="telefono" name="telefono" type="tel" inputmode="numeric" pattern="[0-9]{7,20}" maxlength="20" title="Ingrese entre 7 y 20 números." value="<?= e($data['telefono']) ?>"></div>
                    <div><label for="usuario">Usuario</label><input id="usuario" name="usuario" type="text" minlength="4" maxlength="50" pattern="[A-Za-z0-9._-]{4,50}" title="Use entre 4 y 50 caracteres: letras, números, punto, guion o guion bajo." required autocomplete="username" value="<?= e($data['usuario']) ?>"></div>
                    <div><label for="id_carrera">Carrera</label><select id="id_carrera" name="id_carrera" required><option value="">Seleccione</option><?php foreach ($careers as $career): ?><option value="<?= (int) $career['id_carrera'] ?>" <?= (string) $data['id_carrera'] === (string) $career['id_carrera'] ? 'selected' : '' ?>><?= e($career['nombre_carrera']) ?></option><?php endforeach; ?></select></div>
                    <div><label for="semestre">Semestre</label><input id="semestre" name="semestre" type="number" min="1" max="10" required value="<?= e($data['semestre']) ?>"></div>
                    <div><label for="registro_universitario">Carnet de Identidad</label><input id="registro_universitario" name="registro_universitario" type="text" maxlength="30" pattern="[A-Za-z0-9-]{1,30}" title="Use solo letras, números y guiones." value="<?= e($data['registro_universitario']) ?>"></div>
                    <div><label for="contrasena">Contraseña</label><input id="contrasena" name="contrasena" type="password" minlength="8" required autocomplete="new-password" data-password-field></div>
                    <div><label for="confirmacion">Confirmar contraseña</label><input id="confirmacion" name="confirmacion" type="password" minlength="8" required autocomplete="new-password" data-password-confirmation></div>
                </div>
                <button type="submit">Crear cuenta</button>
                <a class="button secondary" href="<?= e(app_url('login.php')) ?>">Volver al login</a>
            </form>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
