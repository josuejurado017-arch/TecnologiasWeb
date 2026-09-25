<?php

// El tutor acepta o rechaza la materia que le propuso la coordinacion (db/039).

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireRole('tutor');
Auth::requireModule('asignaciones');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$user = Auth::user();
$materiaId = (int) ($_POST['id_materia'] ?? 0);
$acepta = ($_POST['respuesta'] ?? '') === 'aceptar';
$error = (new OfertaTutorController())->responderPropuesta(
    (int) $user['id_usuario'],
    $materiaId,
    $acepta,
    is_string($_POST['motivo'] ?? null) ? $_POST['motivo'] : ''
);
$query = $error
    ? '?error=' . rawurlencode($error)
    : '?message=' . ($acepta ? 'propuesta-aceptada' : 'propuesta-rechazada');
header('Location: ' . app_url('mis-materias/' . $query . '#materia-' . $materiaId));
exit;
