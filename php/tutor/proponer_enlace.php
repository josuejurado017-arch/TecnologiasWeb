<?php

// El tutor propone el enlace virtual de su grupo (POST). La coordinacion lo revisa.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('tutor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$id = filter_var($_POST['id_grupo'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: ' . app_url('mis-grupos/?error=' . rawurlencode('Grupo no válido.')));
    exit;
}

$error = (new GruposController())->proponerEnlace($id, (int) Auth::user()['id_usuario'], $_POST);
if ($error !== null) {
    header('Location: ' . app_url('mis-grupos/?error=' . rawurlencode($error) . '#grupo-' . $id));
    exit;
}

header('Location: ' . app_url('mis-grupos/?message=propuesta#grupo-' . $id));
exit;
