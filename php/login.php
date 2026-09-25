<?php

require dirname(__DIR__) . '/includes/bootstrap.php';

if (Auth::check()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$error = null;
$success = ($_GET['registered'] ?? '') === '1'
    ? 'Cuenta creada correctamente. Ya puedes iniciar sesión.'
    : (($_GET['tutor_registered'] ?? '') === '1' ? 'Cuenta de tutor creada. Ya puedes iniciar sesión y configurar tus materias; recibirás grupos cuando la coordinación apruebe tu habilitación.' : null);
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string) ($_POST['usuario'] ?? '');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario expiró. Intenta de nuevo.';
    } else {
        $error = (new AuthController())->login(
            $username,
            (string) ($_POST['contrasena'] ?? '')
        );
    }
}

require dirname(__DIR__) . '/views/auth/login.php';
