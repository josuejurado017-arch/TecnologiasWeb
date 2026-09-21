<?php

require dirname(__DIR__) . '/includes/bootstrap.php';

if (Auth::check()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$title = 'Crear cuenta de estudiante';
$data = [
    'nombre' => '',
    'apellido' => '',
    'correo' => '',
    'usuario' => '',
    'contrasena' => '',
    'confirmacion' => '',
    'telefono' => '',
    'id_carrera' => '',
    'semestre' => '',
    'registro_universitario' => '',
];
$errors = [];
$controller = new RegistroController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = $controller->register($_POST);
        if (!$errors) {
            header('Location: ' . app_url('login.php?registered=1'));
            exit;
        }
    }
}

$careers = $controller->careers();
require dirname(__DIR__) . '/views/auth/register.php';
