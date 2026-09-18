<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAnyRole(['administrador', 'tutor', 'estudiante']);
Auth::requireModule('tutorias');

$user = Auth::user();
$role = (string) $user['nombre_rol'];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$controller = new TutoriasController();
$model = new Tutoria();
$current = $id ? $model->findForViewer((int) $id, $role, (int) $user['id_usuario']) : null;
if (!$current) {
    http_response_code(404);
    exit('La tutoria no existe o no puede ser modificada.');
}

$title = 'Reprogramar tutoria';
$activePage = 'tutorias';
$data = [
    'id_tutor' => (string) $current['id_tutor'],
    'fecha' => (string) $current['fecha'],
    'hora_inicio' => substr((string) $current['hora_inicio'], 0, 5),
    'hora_fin' => substr((string) $current['hora_fin'], 0, 5),
    'modalidad' => (string) $current['modalidad'],
    'lugar_o_enlace' => (string) ($current['lugar_o_enlace'] ?? ''),
    'motivo' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesion del formulario no es valida. Recargue la pagina.';
    } else {
        [$data, $errors] = $controller->reschedule((int) $id, $_POST, $role, (int) $user['id_usuario']);
        if (!$errors) {
            header('Location: ' . app_url('tutorias/?message=rescheduled'));
            exit;
        }
    }
}

$tutors = $role === 'administrador'
    ? $model->tutorsForMatter((int) $current['id_materia'], (int) $current['id_estudiante'])
    : [['id_tutor' => (int) $current['id_tutor'], 'tutor' => (string) $current['tutor']]];
require dirname(__DIR__, 2) . '/views/tutorias/reschedule.php';
