<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$user = Auth::user();
if ($id) {
    (new Notificacion())->markRead((int) $id, (int) $user['id_usuario']);
}

$redirect = is_string($_POST['redirect'] ?? null) ? $_POST['redirect'] : 'dashboard.php';
header('Location: ' . app_url(ltrim($redirect, '/')));
exit;
