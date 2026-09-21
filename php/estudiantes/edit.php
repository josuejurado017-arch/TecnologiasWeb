<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Editar estudiante';
$activePage = 'estudiantes';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$controller = new EstudiantesController();
$data = $id ? $controller->find($id) : null;

if (!$data) {
    http_response_code(404);
    exit('Estudiante no encontrado.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = $controller->update($id, $_POST);
        $data['id_estudiante'] = $id;
        if (!$errors) {
            header('Location: ' . app_url('estudiantes/?message=updated'));
            exit;
        }
    }
}

$options = $controller->options((int) $data['id_usuario']);
$mode = 'edit';
require dirname(__DIR__, 2) . '/views/estudiantes/form.php';
