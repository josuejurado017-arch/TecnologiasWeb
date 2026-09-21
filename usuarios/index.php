<?php

require dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Cuentas de acceso';
$activePage = 'usuarios';

$messages = [
    'created' => 'Usuario creado correctamente.',
    'updated' => 'Usuario actualizado correctamente.',
    'deactivated' => 'Usuario desactivado correctamente.',
    'activated' => 'Cuenta activada correctamente.',
];
$message = $messages[$_GET['message'] ?? ''] ?? null;
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
$usuarios = (new UsuariosController())->index();

require dirname(__DIR__) . '/views/usuarios/index.php';
