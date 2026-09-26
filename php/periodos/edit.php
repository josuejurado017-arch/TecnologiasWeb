<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'periodos';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$controller = new PeriodosController();
$periodo = $id ? $controller->find($id) : null;

if (!$periodo) {
    http_response_code(404);
    exit('Período no encontrado.');
}

// Un periodo cerrado es historial: se consulta, no se edita.
if ($periodo['estado'] === 'cerrada') {
    header('Location: ' . app_url('periodos/ver.php?id=' . (int) $id));
    exit;
}

$editables = $controller->camposEditables($periodo);
$data = $periodo;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = $controller->update($id, $_POST);
        $data['id_periodo'] = $id;
        $data['estado'] = $periodo['estado'];
        if (!$errors) {
            header('Location: ' . app_url('periodos/?message=updated'));
            exit;
        }
    }
}

$tipos = $controller->tiposParaFormulario((int) $periodo['id_tipo_tutoria']);
$mode = 'edit';
require dirname(__DIR__, 2) . '/views/periodos/form.php';
