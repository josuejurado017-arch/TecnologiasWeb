<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'aulas';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$controller = new AulasController();
$aula = $id ? $controller->find($id) : null;

if (!$aula) {
    http_response_code(404);
    exit('Aula no encontrada.');
}

$data = [
    'id_aula' => $aula['id_aula'],
    'nombre' => $aula['nombre'],
    'tipo' => $aula['tipo'],
    'capacidad' => $aula['capacidad'],
    'ubicacion' => $aula['ubicacion'] ?? '',
    'enlace' => $aula['enlace'] ?? '',
    'plataforma' => $aula['plataforma'] ?? '',
    'estado' => $aula['estado'],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesion del formulario no es valida. Recargue la pagina.';
    } else {
        [$data, $errors] = $controller->update($id, $_POST);
        $data['id_aula'] = $id;
        if (!$errors) {
            header('Location: ' . app_url('aulas/?message=updated'));
            exit;
        }
    }
}

$mode = 'edit';
require dirname(__DIR__, 2) . '/views/aulas/form.php';
