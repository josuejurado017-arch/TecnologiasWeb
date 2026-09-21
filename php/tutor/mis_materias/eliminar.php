<?php

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireRole('tutor');
Auth::requireModule('asignaciones');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$user = Auth::user();
$error = (new TutorPortalController())->removeSubject((int) $user['id_usuario'], $_POST);
header('Location: ' . app_url('mis-materias/' . ($error ? '?error=' . rawurlencode($error) : '?message=subject-removed')));
exit;
