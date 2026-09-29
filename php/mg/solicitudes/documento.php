<?php

// Entrega el documento de una solicitud solo a su dueño y a la Coordinacion (db/049).
// El archivo vive fuera de la raiz publica: no hay otra forma de llegar a el.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireLogin();

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$solicitud = $id ? (new MgSolicitud())->find($id) : null;
$esDueno = $solicitud !== null && (int) $solicitud['id_usuario_estudiante'] === (int) Auth::user()['id_usuario'];
if ($solicitud === null || !($esDueno || Auth::canDo('mg.solicitudes'))) {
    http_response_code(404);
    exit('Documento no encontrado.');
}
$ruta = MgSolicitudesController::rutaDocumento((string) $solicitud['documento_archivo']);
if ($ruta === null) {
    http_response_code(404);
    exit('El archivo ya no está disponible.');
}

$nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $solicitud['documento_nombre']) ?: 'documento';
header('Content-Type: ' . $solicitud['documento_mime']);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="' . $nombre . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($ruta);
exit;
