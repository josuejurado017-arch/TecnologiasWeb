<?php

// Detalle de un periodo en cualquier estado: datos, ciclo de vida, resumen del
// cierre (si esta cerrado) y observaciones administrativas.

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

$title = 'Período: ' . $periodo['nombre'];
$observaciones = $controller->observaciones($id);
// Cerrado: se muestra lo congelado al cierre. Activo: el estado de hoy.
$resumen = $periodo['estado'] === 'cerrada'
    ? (json_decode((string) ($periodo['resumen_cierre'] ?? ''), true) ?: null)
    : ($periodo['estado'] === 'activa' ? $controller->impactoCierre($id) : null);

$messages = ['observacion' => 'Observación registrada.'];
$message = $messages[$_GET['message'] ?? ''] ?? null;
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;

require dirname(__DIR__, 2) . '/views/periodos/ver.php';
