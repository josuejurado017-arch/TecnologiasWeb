<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Tutores';
$activePage = 'tutores';

$messages = [
    'created' => 'Tutor creado correctamente.',
    'updated' => 'Tutor actualizado correctamente.',
    'deleted' => 'Perfil de tutor eliminado correctamente.',
    'activated' => 'Tutor activado correctamente.',
    'deactivated' => 'Tutor desactivado correctamente.',
];
$messageCode = isset($_GET['message']) && is_string($_GET['message']) ? $_GET['message'] : '';
$message = $messages[$messageCode] ?? null;
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
$tutors = (new TutoresController())->index();

require dirname(__DIR__, 2) . '/views/tutores/index.php';
