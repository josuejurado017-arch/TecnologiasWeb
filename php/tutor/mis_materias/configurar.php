<?php

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireRole('tutor');
Auth::requireModule('asignaciones');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$user = Auth::user();
$controller = new TutorPortalController();
$error = $controller->saveMateriaConfig((int) $user['id_usuario'], $_POST);
$materiaId = (int) ($_POST['id_materia'] ?? 0);
$query = $error
    ? '?error=' . rawurlencode($error) . '&materia=' . $materiaId
    : '?message=' . ($controller->sinCambios ? 'subject-unchanged' : 'subject-configured');
header('Location: ' . app_url('mis-materias/' . $query . '#materia-' . $materiaId));
exit;
