<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'tipos-tutoria';

$controller = new TiposTutoriaController();
$data = ['nombre' => '', 'descripcion' => '', 'duracion_max_dias' => null];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } else {
        [$data, $errors] = $controller->store($_POST);
        if (!$errors) {
            header('Location: ' . app_url('tipos-tutoria/?message=created'));
            exit;
        }
    }
}

$mode = 'create';
require dirname(__DIR__, 2) . '/views/tipos_tutoria/form.php';
