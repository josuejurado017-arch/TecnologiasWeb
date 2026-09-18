<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAnyRole(['administrador', 'tutor', 'estudiante']);
Auth::requireModule('tutorias');
$user = Auth::user();
$role = (string) $user['nombre_rol'];
$title = 'Tutorias';
$activePage = 'tutorias';
$controller = new TutoriasController();
$filters = [
    'estado' => in_array($_GET['estado'] ?? '', ['pendiente', 'confirmada', 'realizada', 'cancelada'], true) ? $_GET['estado'] : '',
    'id_materia' => filter_var($_GET['id_materia'] ?? null, FILTER_VALIDATE_INT) ?: '',
    'fecha_desde' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['fecha_desde'] ?? '')) ? $_GET['fecha_desde'] : '',
    'fecha_hasta' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['fecha_hasta'] ?? '')) ? $_GET['fecha_hasta'] : '',
];
$userId = (int) $user['id_usuario'];
$tutorias = $controller->index($role, $userId, $filters);
$filterOptions = $controller->filterOptions($role, $userId);
$messages = [
    'created' => 'Solicitud de tutoria creada correctamente.',
    'special-created' => 'Solicitud de horario especial enviada correctamente.',
    'updated' => 'Estado de tutoria actualizado correctamente.',
    'rescheduled' => 'Tutoria reprogramada correctamente.',
    'attendance' => 'Asistencia registrada correctamente.',
];
$message = $messages[$_GET['message'] ?? ''] ?? null;
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;

require dirname(__DIR__, 2) . '/views/tutorias/index.php';
