<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Aulas';
$activePage = 'aulas';

$messages = [
    'created' => 'Aula creada correctamente.',
    'updated' => 'Aula actualizada correctamente.',
    'deleted' => 'Aula eliminada correctamente.',
];
$messageCode = isset($_GET['message']) && is_string($_GET['message']) ? $_GET['message'] : '';
$message = $messages[$messageCode] ?? null;
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
$aulas = (new AulasController())->index();

require dirname(__DIR__, 2) . '/views/aulas/index.php';
