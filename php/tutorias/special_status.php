<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAnyRole(['administrador', 'tutor']);
Auth::requireModule('tutorias');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no valida.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$response = trim((string) ($_POST['respuesta'] ?? ''));
$user = Auth::user();
$error = $id ? (new SolicitudesEspecialesController())->decide((int) $id, $action, (string) $user['nombre_rol'], (int) $user['id_usuario'], $response) : 'Solicitud no valida.';
header('Location: ' . app_url('tutorias/especiales.php' . ($error ? '?error=' . rawurlencode($error) : '?message=updated')));
exit;
