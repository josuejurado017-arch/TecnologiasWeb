<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'aulas';

$controller = new AulasController();
$data = ['nombre' => '', 'tipo' => 'fisica', 'capacidad' => 20, 'ubicacion' => '', 'enlace' => '', 'plataforma' => '', 'estado' => 'activa'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesion del formulario no es valida. Recargue la pagina.';
    } else {
        [$data, $errors] = $controller->store($_POST);
        if (!$errors) {
            header('Location: ' . app_url('aulas/?message=created'));
            exit;
        }
    }
}

$mode = 'create';
require dirname(__DIR__, 2) . '/views/aulas/form.php';
