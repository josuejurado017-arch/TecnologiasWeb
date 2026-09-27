<?php

// Genera citaciones (HU-030) de una defensa (id_defensa) o de todas las del dia (fecha)
// y abre la vista de impresion.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.documentos');
mg_require_post();

$controller = new MgDocumentosController();
$userId = (int) Auth::user()['id_usuario'];
$defensaId = (int) filter_var($_POST['id_defensa'] ?? 0, FILTER_VALIDATE_INT);
$fecha = is_string($_POST['fecha'] ?? null) ? $_POST['fecha'] : '';

if ($defensaId > 0) {
    $defensa = (new MgDefensa())->find($defensaId);
    [$ids, $error] = $controller->generarCitaciones($defensaId, $userId);
    if ($error !== null) {
        mg_redirect('expedientes/ver.php?id=' . (int) ($defensa['id_expediente'] ?? 0), ['error' => $error]);
    }
    mg_redirect('documentos/ver.php?ids=' . implode(',', $ids) . '&volver=' . (int) $defensa['id_expediente']);
}

if (mg_fecha_valida($fecha)) {
    [$ids, $errores] = $controller->generarCitacionesDelDia($fecha, $userId);
    if ($ids === []) {
        mg_redirect('defensas/?desde=' . $fecha . '&hasta=' . $fecha, ['error' => $errores ? implode(' ', $errores) : 'No había defensas sin citaciones ese día.']);
    }
    mg_redirect('documentos/ver.php?ids=' . implode(',', $ids), ['aviso' => implode("\n", $errores)]);
}

http_response_code(400);
exit('Solicitud no válida.');
