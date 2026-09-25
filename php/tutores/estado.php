<?php

// Alta/baja de la cuenta de un tutor sin borrar su historial. Un tutor
// desactivado deja de recibir asignaciones del motor, pero conserva sus grupos,
// materias y evaluaciones. Es la accion no destructiva que faltaba en la lista.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: ' . app_url('tutores/?error=' . rawurlencode('Tutor no válido.')));
    exit;
}

$estado = is_string($_POST['estado'] ?? null) ? $_POST['estado'] : '';
$error = (new TutoresController())->setEstado($id, $estado);
if ($error !== null) {
    header('Location: ' . app_url('tutores/?error=' . rawurlencode($error)));
    exit;
}

header('Location: ' . app_url('tutores/?message=' . ($estado === 'activo' ? 'activated' : 'deactivated')));
exit;
