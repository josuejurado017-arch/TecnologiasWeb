<?php

require dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'usuarios';

$controller = new UsuariosController();
$roles = $controller->roles();
$careers = $controller->careers();
$data = [
    'id_rol' => '',
    'nombre' => '',
    'apellido' => '',
    'correo' => '',
    'usuario' => '',
    'contrasena' => '',
    'telefono' => '',
    'carnet_identidad' => '',
    'estado' => 'activo',
    'id_carrera' => '',
    'semestre' => '',
    'registro_universitario' => '',
    'especialidad' => '',
    'biografia' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = $controller->store($_POST);
        if (!$errors) {
            header('Location: ' . app_url('usuarios/?message=created'));
            exit;
        }
    }
}

$mode = 'create';
require dirname(__DIR__) . '/views/usuarios/form.php';
