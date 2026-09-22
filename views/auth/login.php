<?php
$title = 'Iniciar sesión';
require __DIR__ . '/../layouts/header.php';
?>

<main class="auth-container">
    <section class="auth-intro">
        <div>
            <img class="brand-logo" src="<?= e(app_url('Front/assets/img/upds-logo.svg')) ?>" alt="UPDS">
        </div>
        <div>
            <span class="hero-kicker">Portal de apoyo académico</span>
            <h1>Aprende con el acompañamiento correcto.</h1>
            <p>Solicita apoyo por materia y el sistema te asignará automáticamente un grupo de tutoría con tutor, aula y horario.</p>
        </div>
        <ul class="auth-points">
            <li>Elige las materias donde necesitas apoyo.</li>
            <li>El sistema arma tu grupo de tutoría.</li>
        </ul>
    </section>

    <section class="auth-form-panel">
        <div class="card">
            <span class="eyebrow">Acceso seguro</span>
            <h2>Bienvenido de nuevo</h2>
            <p>Ingresa tus datos para continuar.</p>

            <?php if (!empty($error)): ?>
                <p class="alert" role="alert"><?= e($error) ?></p>
            <?php endif; ?>
            <?php if (!empty($success)): ?><p class="success" role="status"><?= e($success) ?></p><?php endif; ?>

            <form method="post" action="<?= e(app_url('login.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="usuario">Usuario</label>
                <input id="usuario" name="usuario" type="text" minlength="4" maxlength="50" pattern="[A-Za-z0-9._-]{4,50}" required autocomplete="username" value="<?= e($username ?? '') ?>">

                <label for="contrasena">Contraseña</label>
                <input id="contrasena" name="contrasena" type="password" required autocomplete="current-password">

                <button type="submit">Iniciar sesión <span aria-hidden="true">-&gt;</span></button>
            </form>
            <p class="auth-switch">¿Aún no tienes una cuenta? <a href="<?= e(app_url('register.php')) ?>">Regístrate como estudiante</a></p>
            <p class="auth-switch"><a href="<?= e(app_url('postular-tutor.php')) ?>">Regístrate como tutor</a></p>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
