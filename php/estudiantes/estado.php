<?php

// Alta/baja de la cuenta de un estudiante sin borrar su historial academico.
// Es la accion no destructiva que faltaba en la lista: delete.php elimina el
// perfil y falla (FK) en cuanto el estudiante tiene inscripciones.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: ' . app_url('estudiantes/?error=' . rawurlencode('Estudiante no válido.')));
    exit;
}

$estado = is_string($_POST['estado'] ?? null) ? $_POST['estado'] : '';
$error = (new EstudiantesController())->setEstado($id, $estado);
if ($error !== null) {
    header('Location: ' . app_url('estudiantes/?error=' . rawurlencode($error)));
    exit;
}

header('Location: ' . app_url('estudiantes/?message=' . ($estado === 'activo' ? 'activated' : 'deactivated')));
exit;
