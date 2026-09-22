<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('tutor');
Auth::requireModule('asignaciones');
$title = 'Mis materias';
$activePage = 'mis-materias';
$user = Auth::user();
$controller = new TutorPortalController();
$userId = (int) $user['id_usuario'];
$subjects = $controller->subjects($userId);
$availableSubjects = $controller->availableSubjects($userId);
// Misma regla que el motor: sin horarios configurados en ninguna materia no se reciben grupos.
$hasSchedule = array_filter($subjects, static fn (array $s): bool => $s['config']['configured']) !== [];
$messages = [
    'subject-added' => 'Materia agregada correctamente.',
    'subject-removed' => 'Materia quitada correctamente.',
    'subject-configured' => 'Configuración guardada correctamente.',
];
$message = $messages[$_GET['message'] ?? ''] ?? null;
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
$openMateriaId = $error ? filter_var($_GET['materia'] ?? null, FILTER_VALIDATE_INT) : null;

require dirname(__DIR__, 2) . '/views/tutor/mis-materias.php';
