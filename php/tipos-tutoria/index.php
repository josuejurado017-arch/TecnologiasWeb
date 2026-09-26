<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Tipos de tutoría';
$activePage = 'tipos-tutoria';

$messages = [
    'created' => 'Tipo de tutoría creado correctamente.',
    'updated' => 'Tipo de tutoría actualizado correctamente.',
    'estado' => 'Tipo de tutoría actualizado.',
    'eliminado' => 'Tipo de tutoría eliminado.',
];
$messageCode = isset($_GET['message']) && is_string($_GET['message']) ? $_GET['message'] : '';
$message = $messages[$messageCode] ?? null;
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
$tipos = (new TiposTutoriaController())->index();

require dirname(__DIR__, 2) . '/views/tipos_tutoria/index.php';
