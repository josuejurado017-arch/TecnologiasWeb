<?php
$title = 'Crear cuenta de tutor';
require __DIR__ . '/../layouts/header.php';
?>

<main class="auth-container auth-register-container">
    <section class="auth-intro">
        <div><span class="brand-mark">TA</span></div>
        <div>
            <span class="hero-kicker">Comunidad de tutores</span>
            <h1>Comparte lo que sabes.</h1>
            <p>Crea tu cuenta de tutor y accede de inmediato. Luego eliges las materias que puedes impartir y configuras los turnos y días de cada una.</p>
        </div>
        <ul class="auth-points">
            <li>Tu cuenta tendrá rol tutor.</li>
            <li>Acceso inmediato, sin esperas.</li>
        </ul>
    </section>

    <section class="auth-form-panel auth-register-panel">
        <div class="card">
            <span class="eyebrow">Cuenta de tutor</span>
            <h2>Crear cuenta</h2>
            <p>Tu especialidad y biografía se guardan en tu perfil.</p>
            <?php if (!empty($errors)): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $formError): ?><li><?= e($formError) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post" action="<?= e(app_url('postular-tutor.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-grid">
                    <div><label for="nombre">Nombre</label><input id="nombre" name="nombre" type="text" maxlength="100" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]+([ '-][A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]+)*" required value="<?= e($data['nombre']) ?>"></div>
                    <div><label for="apellido">Apellido</label><input id="apellido" name="apellido" type="text" maxlength="100" pattern="[A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]+([ '-][A-Za-zÁÉÍÓÚáéíóúÑñÜüÀ-ÿ]+)*" required value="<?= e($data['apellido']) ?>"></div>
                    <div><label for="correo">Correo</label><input id="correo" name="correo" type="email" maxlength="150" required value="<?= e($data['correo']) ?>"></div>
                    <div><label for="telefono">Teléfono</label><input id="telefono" name="telefono" type="tel" inputmode="numeric" pattern="[0-9]{7,20}" maxlength="20" value="<?= e($data['telefono']) ?>"></div>
                    <div><label for="usuario">Usuario</label><input id="usuario" name="usuario" type="text" minlength="4" maxlength="50" pattern="[A-Za-z0-9._-]{4,50}" required value="<?= e($data['usuario']) ?>"></div>
                    <div><label for="especialidad">Especialidad</label><input id="especialidad" name="especialidad" type="text" maxlength="150" required value="<?= e($data['especialidad']) ?>"></div>
                    <div><label for="contrasena">Contraseña</label><input id="contrasena" name="contrasena" type="password" minlength="8" required data-password-field></div>
                    <div><label for="confirmacion">Confirmar contraseña</label><input id="confirmacion" name="confirmacion" type="password" minlength="8" required data-password-confirmation></div>
                    <div class="form-full"><label for="biografia">Biografía profesional</label><textarea id="biografia" name="biografia" rows="5" maxlength="2000"><?= e($data['biografia']) ?></textarea></div>
                </div>
                <button type="submit">Crear cuenta</button>
                <a class="button secondary" href="<?= e(app_url('login.php')) ?>">Volver al login</a>
            </form>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
