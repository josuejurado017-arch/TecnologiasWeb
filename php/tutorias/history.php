<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAnyRole(['administrador', 'tutor', 'estudiante']);
Auth::requireModule('tutorias');

$user = Auth::user();
$role = (string) $user['nombre_rol'];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$tutoria = $id ? (new Tutoria())->findForViewer((int) $id, $role, (int) $user['id_usuario']) : null;
if (!$tutoria) {
    http_response_code(404);
    exit('La tutoria no existe.');
}

$title = 'Historial de tutoria';
$activePage = 'tutorias';
$history = (new TutoriasController())->history((int) $id, $role, (int) $user['id_usuario']);
require dirname(__DIR__, 2) . '/views/tutorias/history.php';
