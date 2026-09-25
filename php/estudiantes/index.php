<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Estudiantes';
$activePage = 'estudiantes';

$messages = [
    'created' => 'Estudiante creado correctamente.',
    'updated' => 'Estudiante actualizado correctamente.',
    'deleted' => 'Perfil de estudiante eliminado correctamente.',
    'activated' => 'Estudiante activado correctamente.',
    'deactivated' => 'Estudiante desactivado correctamente.',
];
$messageCode = isset($_GET['message']) && is_string($_GET['message']) ? $_GET['message'] : '';
$message = $messages[$messageCode] ?? null;
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
$controller = new EstudiantesController();
$filtros = EstudiantesController::filtros($_GET);
$queryFiltros = EstudiantesController::queryFiltros($filtros);
$students = $controller->index($filtros);
$careers = $controller->careers();
$exportUrl = app_url('estudiantes/export.php' . ($queryFiltros !== '' ? '?' . $queryFiltros : ''));

require dirname(__DIR__, 2) . '/views/estudiantes/index.php';
