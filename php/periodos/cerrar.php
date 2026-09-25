<?php

// Cierre del periodo activo: GET muestra el impacto, POST lo ejecuta tras la
// confirmacion explicita del coordinador.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$activePage = 'periodos';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$controller = new PeriodosController();
$periodo = $id ? $controller->find($id) : null;
if (!$periodo) {
    http_response_code(404);
    exit('Período no encontrado.');
}
if ($periodo['estado'] !== 'activa') {
    header('Location: ' . app_url('periodos/ver.php?id=' . (int) $id));
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario no es válida. Recarga la página.';
    } elseif (($_POST['confirmacion'] ?? '') !== 'CERRAR') {
        $error = 'Escribe CERRAR para confirmar el cierre.';
    } else {
        $error = $controller->cerrar($id, (int) Auth::user()['id_usuario']);
        if ($error === null) {
            header('Location: ' . app_url('periodos/?message=closed'));
            exit;
        }
    }
}

$title = 'Cerrar período';
$impacto = $controller->impactoCierre($id);

require dirname(__DIR__, 2) . '/views/periodos/cerrar.php';
