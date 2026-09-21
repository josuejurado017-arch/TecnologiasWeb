<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'carreras';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$controller = new CarrerasController();
$career = $id ? $controller->find($id) : null;

if (!$career) {
    http_response_code(404);
    exit('Carrera no encontrada.');
}

$data = $career;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = $controller->update($id, $_POST);
        $data['id_carrera'] = $id;
        if (!$errors) {
            header('Location: ' . app_url('carreras/?message=updated'));
            exit;
        }
    }
}

$mode = 'edit';
require dirname(__DIR__, 2) . '/views/carreras/form.php';
