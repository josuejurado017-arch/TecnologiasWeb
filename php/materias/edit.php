<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'materias';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$controller = new MateriasController();
$subject = $id ? $controller->find($id) : null;

if (!$subject) {
    http_response_code(404);
    exit('Materia no encontrada.');
}

$carreras = $controller->careers();
$data = [
    'id_materia' => $subject['id_materia'],
    'nombre_materia' => $subject['nombre_materia'],
    'id_carrera' => $subject['id_carrera'],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = $controller->update($id, $_POST);
        $data['id_materia'] = $id;
        if (!$errors) {
            header('Location: ' . app_url('materias/?message=updated'));
            exit;
        }
    }
}

$mode = 'edit';
require dirname(__DIR__, 2) . '/views/materias/form.php';
