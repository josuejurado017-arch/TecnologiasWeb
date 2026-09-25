<?php

// Aprobar o rechazar la oferta de un tutor para una materia (POST, db/032).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$tutorId = filter_var($_POST['id_tutor'] ?? null, FILTER_VALIDATE_INT);
$materiaId = filter_var($_POST['id_materia'] ?? null, FILTER_VALIDATE_INT);
if ($tutorId === false || $tutorId < 1 || $materiaId === false || $materiaId < 1) {
    header('Location: ' . app_url('tutores/ofertas.php?error=' . rawurlencode('Oferta no válida.')));
    exit;
}

$adminId = (int) Auth::user()['id_usuario'];
$controller = new OfertaTutorController();
$accion = $_POST['accion'] ?? '';

if ($accion === 'aprobar') {
    [$error, $resumen] = $controller->aprobar($tutorId, $materiaId, $adminId);
    if ($error !== null) {
        header('Location: ' . app_url('tutores/ofertas.php?error=' . rawurlencode($error)));
        exit;
    }
    header('Location: ' . app_url('tutores/ofertas.php?message=approved&atendidos=' . (int) $resumen['atendidos']));
    exit;
}

if ($accion === 'rechazar') {
    $error = $controller->rechazar($tutorId, $materiaId, is_string($_POST['motivo'] ?? null) ? $_POST['motivo'] : '', $adminId);
    if ($error !== null) {
        header('Location: ' . app_url('tutores/ofertas.php?error=' . rawurlencode($error)));
        exit;
    }
    header('Location: ' . app_url('tutores/ofertas.php?message=rejected'));
    exit;
}

header('Location: ' . app_url('tutores/ofertas.php?error=' . rawurlencode('Acción no válida.')));
exit;
