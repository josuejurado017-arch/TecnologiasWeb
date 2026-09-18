<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAnyRole(['administrador', 'tutor', 'estudiante']);
Auth::requireModule('tutorias');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no valida.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$status = is_string($_POST['estado'] ?? null) ? $_POST['estado'] : '';
$reason = is_string($_POST['motivo'] ?? null) ? $_POST['motivo'] : '';
$user = Auth::user();
$error = !$id ? 'Tutoria no valida.' : (new TutoriasController())->changeStatus($id, $status, (string) $user['nombre_rol'], (int) $user['id_usuario'], $reason);
header('Location: ' . app_url('tutorias/' . ($error ? '?error=' . rawurlencode($error) : '?message=updated')));
exit;
