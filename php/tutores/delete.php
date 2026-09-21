<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: ' . app_url('tutores/?error=Tutor no valido.'));
    exit;
}

$error = (new TutoresController())->delete($id);
header('Location: ' . app_url('tutores/' . ($error ? '?error=' . rawurlencode($error) : '?message=deleted')));
exit;
