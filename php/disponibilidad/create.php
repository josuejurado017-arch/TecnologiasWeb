<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAnyRole(['administrador', 'tutor']);
Auth::requireModule('disponibilidad');
$title = 'Nuevo horario';
$activePage = 'disponibilidad';
$user = Auth::user();
$isAdmin = ($user['nombre_rol'] ?? '') === 'administrador';
$forcedTutorId = $isAdmin ? null : (new Tutor())->findIdByUserId((int) $user['id_usuario']);
if (!$isAdmin && !$forcedTutorId) {
    http_response_code(403);
    exit('El usuario no tiene perfil de tutor.');
}

$data = [
    'id_tutor' => $forcedTutorId ?? '',
    'dia_semana' => 'Lunes',
    'hora_inicio' => '',
    'hora_fin' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = (new DisponibilidadController())->store($_POST, $forcedTutorId);
        if (!$errors) {
            header('Location: ' . app_url('disponibilidad/?message=created'));
            exit;
        }
    }
}

$controller = new DisponibilidadController();
$tutors = $isAdmin ? $controller->tutors() : [];
$mode = 'create';
require dirname(__DIR__, 2) . '/views/disponibilidad/form.php';
