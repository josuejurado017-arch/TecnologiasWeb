<?php

require dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'usuarios';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$controller = new UsuariosController();
$user = $id ? $controller->find($id) : null;

if (!$user) {
    http_response_code(404);
    exit('Usuario no encontrado.');
}

$roles = $controller->roles();
$data = [
    'id_usuario' => $user['id_usuario'],
    'id_rol' => (string) $user['id_rol'],
    'nombre' => $user['nombre'],
    'apellido' => $user['apellido'],
    'correo' => $user['correo'],
    'usuario' => $user['usuario'],
    'contrasena' => '',
    'telefono' => $user['telefono'] ?? '',
    'carnet_identidad' => $user['carnet_identidad'] ?? '',
    'estado' => $user['estado'],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesion del formulario no es valida. Recargue la pagina.';
    } else {
        [$data, $errors] = $controller->update($id, $_POST);
        $data['id_usuario'] = $id;
        if (!$errors) {
            header('Location: ' . app_url('usuarios/?message=updated'));
            exit;
        }
    }
}

$mode = 'edit';
require dirname(__DIR__) . '/views/usuarios/form.php';
