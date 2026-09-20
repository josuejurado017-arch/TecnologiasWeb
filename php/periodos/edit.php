<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'periodos';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$controller = new PeriodosController();
$periodo = $id ? $controller->find($id) : null;

if (!$periodo) {
    http_response_code(404);
    exit('Campana no encontrada.');
}

$data = [
    'id_periodo' => $periodo['id_periodo'],
    'nombre' => $periodo['nombre'],
    'fecha_inicio' => $periodo['fecha_inicio'],
    'fecha_fin' => $periodo['fecha_fin'],
    'cupo_min_grupo' => $periodo['cupo_min_grupo'],
    'cupo_max_default' => $periodo['cupo_max_default'],
    'estado' => $periodo['estado'],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesion del formulario no es valida. Recargue la pagina.';
    } else {
        [$data, $errors] = $controller->update($id, $_POST);
        $data['id_periodo'] = $id;
        if (!$errors) {
            header('Location: ' . app_url('periodos/?message=updated'));
            exit;
        }
    }
}

$mode = 'edit';
require dirname(__DIR__, 2) . '/views/periodos/form.php';
