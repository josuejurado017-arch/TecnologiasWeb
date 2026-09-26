<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'tipos-tutoria';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$controller = new TiposTutoriaController();
$tipo = $id ? $controller->find($id) : null;

if (!$tipo) {
    http_response_code(404);
    exit('Tipo de tutoría no encontrado.');
}

$data = $tipo;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = $controller->update($id, $_POST);
        $data['id_tipo_tutoria'] = $id;
        if (!$errors) {
            header('Location: ' . app_url('tipos-tutoria/?message=updated'));
            exit;
        }
    }
}

$mode = 'edit';
require dirname(__DIR__, 2) . '/views/tipos_tutoria/form.php';
