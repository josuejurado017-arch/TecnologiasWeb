<?php

// Aprobar o rechazar la habilitacion docente de un tutor (POST).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: ' . app_url('tutores/pendientes/?error=' . rawurlencode('Tutor no válido.')));
    exit;
}

$adminId = (int) Auth::user()['id_usuario'];
$controller = new HabilitacionTutorController();
$accion = $_POST['accion'] ?? '';

if ($accion === 'aprobar') {
    [$error, $resumen] = $controller->aprobar($id, $adminId);
    if ($error !== null) {
        header('Location: ' . app_url('tutores/pendientes/?error=' . rawurlencode($error)));
        exit;
    }
    header('Location: ' . app_url('tutores/pendientes/?message=approved&materias=' . (int) $resumen['materias'] . '&atendidos=' . (int) $resumen['atendidos']));
    exit;
}

if ($accion === 'rechazar') {
    $error = $controller->rechazar($id, is_string($_POST['motivo'] ?? null) ? $_POST['motivo'] : '', $adminId);
    if ($error !== null) {
        header('Location: ' . app_url('tutores/pendientes/?error=' . rawurlencode($error)));
        exit;
    }
    header('Location: ' . app_url('tutores/pendientes/?message=rejected'));
    exit;
}

header('Location: ' . app_url('tutores/pendientes/?error=' . rawurlencode('Acción no válida.')));
exit;
