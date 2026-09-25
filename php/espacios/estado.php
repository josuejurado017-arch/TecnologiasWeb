<?php

// Activa, desactiva o marca como predeterminado un espacio de tutoria (POST).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: ' . app_url('espacios/?error=' . rawurlencode('Identificador no válido.')));
    exit;
}

$error = (new EspaciosController())->cambiarEstado($id, (string) ($_POST['accion'] ?? ''));
if ($error !== null) {
    header('Location: ' . app_url('espacios/?error=' . rawurlencode($error)));
    exit;
}

header('Location: ' . app_url('espacios/?message=estado'));
exit;
