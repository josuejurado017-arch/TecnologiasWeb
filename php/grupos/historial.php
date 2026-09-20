<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Historial del grupo';
$activePage = 'grupos';

$id = filter_input(INPUT_GET, 'grupo', FILTER_VALIDATE_INT);
$controller = new GruposController();
$grupo = $id ? $controller->find($id) : null;

if (!$grupo) {
    http_response_code(404);
    exit('Grupo no encontrado.');
}

$historial = $controller->history($id);

require dirname(__DIR__, 2) . '/views/grupos/historial.php';
