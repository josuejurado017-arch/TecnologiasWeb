<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'periodos';

$controller = new PeriodosController();
$data = ['nombre' => '', 'id_tipo_tutoria' => TipoTutoria::actual(), 'fecha_inicio' => '', 'fecha_fin' => '', 'cupo_min_grupo' => 3, 'cupo_max_default' => 20, 'modalidad_ambas' => 'virtual', 'estado' => 'borrador'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = $controller->store($_POST);
        if (!$errors) {
            header('Location: ' . app_url('periodos/?message=created'));
            exit;
        }
    }
}

$tipos = $controller->tiposParaFormulario();
$mode = 'create';
require dirname(__DIR__, 2) . '/views/periodos/form.php';
