<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Editar tutor';
$activePage = 'tutores';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$controller = new TutoresController();
$data = $id ? $controller->find($id) : null;

if (!$data) {
    http_response_code(404);
    exit('Tutor no encontrado.');
}

// Datos de la cuenta tal como estan guardados: la cabecera los muestra aunque
// el POST falle y $data traiga lo que el administrador acaba de escribir.
$account = $data;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = $controller->update($id, $_POST);
        $data['id_tutor'] = $id;
        if (!$errors) {
            header('Location: ' . app_url('tutores/?message=updated'));
            exit;
        }
    }
}

require dirname(__DIR__, 2) . '/views/tutores/form.php';
