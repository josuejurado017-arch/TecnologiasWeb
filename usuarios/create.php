<?php

require dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'usuarios';

$controller = new UsuariosController();
$roles = $controller->roles();
$careers = $controller->careers();

// Los modulos Estudiantes y Tutores enlazan aqui con ?rol=... para que el
// administrador llegue con el rol ya seleccionado (el formulario muestra
// entonces los campos de ese perfil sin pasos extra).
$rolSolicitado = is_string($_GET['rol'] ?? null) ? $_GET['rol'] : '';
$rolPreseleccionado = '';
if (in_array($rolSolicitado, ['estudiante', 'tutor'], true)) {
    foreach ($roles as $role) {
        if ($role['nombre_rol'] === $rolSolicitado) {
            $rolPreseleccionado = (string) $role['id_rol'];
            break;
        }
    }
}

$data = [
    'id_rol' => $rolPreseleccionado,
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
