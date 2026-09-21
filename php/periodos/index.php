<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Períodos de tutoría';
$activePage = 'periodos';

$messages = [
    'created' => 'Período creado correctamente.',
    'updated' => 'Período actualizado correctamente.',
    'deleted' => 'Período eliminado correctamente.',
    'activated' => 'Período activado. Los demás quedaron en borrador.',
];
$messageCode = isset($_GET['message']) && is_string($_GET['message']) ? $_GET['message'] : '';
$message = $messages[$messageCode] ?? null;
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
$periodos = (new PeriodosController())->index();

require dirname(__DIR__, 2) . '/views/periodos/index.php';
