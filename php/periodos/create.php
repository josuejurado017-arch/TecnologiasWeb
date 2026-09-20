<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'periodos';

$controller = new PeriodosController();
$data = ['nombre' => '', 'fecha_inicio' => '', 'fecha_fin' => '', 'cupo_min_grupo' => 3, 'cupo_max_default' => 20, 'estado' => 'borrador'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesion del formulario no es valida. Recargue la pagina.';
    } else {
        [$data, $errors] = $controller->store($_POST);
        if (!$errors) {
            header('Location: ' . app_url('periodos/?message=created'));
            exit;
        }
    }
}

$mode = 'create';
require dirname(__DIR__, 2) . '/views/periodos/form.php';
