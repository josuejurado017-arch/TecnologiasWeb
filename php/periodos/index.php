<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Campanas de tutoria';
$activePage = 'periodos';

$messages = [
    'created' => 'Campana creada correctamente.',
    'updated' => 'Campana actualizada correctamente.',
    'deleted' => 'Campana eliminada correctamente.',
    'activated' => 'Campana activada. Las demas quedaron en borrador.',
];
$messageCode = isset($_GET['message']) && is_string($_GET['message']) ? $_GET['message'] : '';
$message = $messages[$messageCode] ?? null;
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
$periodos = (new PeriodosController())->index();

require dirname(__DIR__, 2) . '/views/periodos/index.php';
