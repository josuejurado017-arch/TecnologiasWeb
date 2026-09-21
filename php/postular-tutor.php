<?php

require dirname(__DIR__) . '/includes/bootstrap.php';

if (Auth::check()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$title = 'Crear cuenta de tutor';
$data = [
    'nombre' => '',
    'apellido' => '',
    'correo' => '',
    'usuario' => '',
    'contrasena' => '',
    'confirmacion' => '',
    'telefono' => '',
    'especialidad' => '',
    'biografia' => '',
];
$errors = [];
$controller = new RegistroTutorController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = $controller->register($_POST);
        if (!$errors) {
            header('Location: ' . app_url('login.php?tutor_registered=1'));
            exit;
        }
    }
}

require dirname(__DIR__) . '/views/auth/tutor-application.php';
