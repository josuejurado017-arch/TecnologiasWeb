<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAnyRole(['administrador', 'tutor']);
Auth::requireModule('tutorias');

$user = Auth::user();
$role = (string) $user['nombre_rol'];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$controller = new TutoriasController();
$tutoria = $id ? (new Tutoria())->findForViewer((int) $id, $role, (int) $user['id_usuario']) : null;
if (!$tutoria) {
    http_response_code(404);
    exit('La tutoria no existe o no puede ser modificada.');
}

$title = 'Registrar asistencia';
$activePage = 'tutorias';
$existing = (new AsistenciaTutoria())->find((int) $id);
$data = [
    'estado_asistencia' => (string) ($existing['estado_asistencia'] ?? ''),
    'minutos_retraso' => (string) ($existing['minutos_retraso'] ?? ''),
    'observaciones' => (string) ($existing['observaciones'] ?? ''),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesion del formulario no es valida. Recargue la pagina.';
    } else {
        [$data, $errors] = $controller->recordAttendance((int) $id, $_POST, $role, (int) $user['id_usuario']);
        if (!$errors) {
            header('Location: ' . app_url('tutorias/?message=attendance'));
            exit;
        }
    }
}

require dirname(__DIR__, 2) . '/views/tutorias/attendance.php';
