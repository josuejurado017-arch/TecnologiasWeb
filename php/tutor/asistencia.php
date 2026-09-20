<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('tutor');
Auth::requireModule('tutorias');
$title = 'Registrar asistencia';
$activePage = 'mis-grupos';
$user = Auth::user();
$tutorUserId = (int) $user['id_usuario'];

$sesionId = filter_input(INPUT_GET, 'sesion', FILTER_VALIDATE_INT);
$controller = new AsistenciaSesionController();
$data = $sesionId ? $controller->form($sesionId, $tutorUserId) : null;

if (!$data) {
    http_response_code(404);
    exit('Sesion no encontrada o sin permiso.');
}

$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesion del formulario no es valida. Recargue la pagina.';
    } else {
        $error = $controller->save((int) $sesionId, $tutorUserId, $_POST);
        if ($error !== null) {
            $errors[] = $error;
        } else {
            header('Location: ' . app_url('tutor/asistencia.php?sesion=' . (int) $sesionId . '&message=saved'));
            exit;
        }
    }
}

$saved = ($_GET['message'] ?? '') === 'saved';
// Recargar estado tras guardar.
$data = $controller->form((int) $sesionId, $tutorUserId);

require dirname(__DIR__, 2) . '/views/tutor/asistencia.php';
