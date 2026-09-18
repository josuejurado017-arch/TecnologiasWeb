<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('estudiante');
Auth::requireModule('tutorias');

$title = 'Horario especial';
$activePage = 'tutorias';
$user = Auth::user();
$tutoria = new Tutoria();
$studentId = $tutoria->studentIdByUserId((int) $user['id_usuario']);
$controller = new SolicitudesEspecialesController();
$data = [
    'id_materia' => filter_var($_GET['id_materia'] ?? null, FILTER_VALIDATE_INT) ?: '',
    'id_tutor' => filter_var($_GET['id_tutor'] ?? null, FILTER_VALIDATE_INT) ?: '',
    'fecha_propuesta' => '',
    'hora_inicio' => '',
    'hora_fin' => '',
    'modalidad' => 'presencial',
    'lugar_o_enlace' => '',
    'observaciones' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesion del formulario no es valida. Recargue la pagina.';
    } else {
        [$data, $errors] = $controller->store($_POST, (int) $studentId);
        if (!$errors) {
            header('Location: ' . app_url('tutorias/?message=special-created'));
            exit;
        }
    }
}

$subjects = $studentId ? $controller->subjects((int) $studentId) : [];
$tutors = $studentId && $data['id_materia'] ? $controller->tutors((int) $data['id_materia'], (int) $studentId) : [];
require dirname(__DIR__, 2) . '/views/tutorias/special.php';
