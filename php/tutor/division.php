<?php

// El tutor acepta o rechaza tomar la mitad de un grupo lleno (db/042).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('tutor');
Auth::requireModule('tutorias');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$acepta = ($_POST['respuesta'] ?? '') === 'aceptar';
$error = (new DivisionGrupoController())->responder(
    (int) ($_POST['id_division'] ?? 0),
    (int) Auth::user()['id_usuario'],
    $acepta,
    is_string($_POST['motivo'] ?? null) ? $_POST['motivo'] : ''
);
$query = $error
    ? '?error=' . rawurlencode($error)
    : '?message=' . ($acepta ? 'division-aceptada' : 'division-rechazada');
header('Location: ' . app_url('mis-grupos/' . $query));
exit;
