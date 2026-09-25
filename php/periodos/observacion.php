<?php

// Agrega una observacion administrativa a un periodo, en cualquier estado (POST).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: ' . app_url('periodos/?error=' . rawurlencode('Identificador no válido.')));
    exit;
}

$error = (new PeriodosController())->agregarObservacion($id, (string) ($_POST['texto'] ?? ''), (int) Auth::user()['id_usuario']);
$query = $error !== null ? 'error=' . rawurlencode($error) : 'message=observacion';
header('Location: ' . app_url('periodos/ver.php?id=' . $id . '&' . $query));
exit;
