<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAnyRole(['administrador', 'tutor']);
Auth::requireModule('disponibilidad');
$user = Auth::user();
$isAdmin = ($user['nombre_rol'] ?? '') === 'administrador';
$controller = new DisponibilidadController();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$data = $id ? $controller->find($id) : null;
$forcedTutorId = $isAdmin ? null : (new Tutor())->findIdByUserId((int) $user['id_usuario']);

if (!$data || (!$isAdmin && (int) $data['id_tutor'] !== (int) $forcedTutorId)) {
    http_response_code(404);
    exit('Horario no encontrado.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = $controller->update($id, $_POST, $forcedTutorId);
        $data['id_disponibilidad'] = $id;
        if (!$errors) {
            header('Location: ' . app_url('disponibilidad/?message=updated'));
            exit;
        }
    }
}

$tutors = $isAdmin ? $controller->tutors() : [];
$mode = 'edit';
require dirname(__DIR__, 2) . '/views/disponibilidad/form.php';
