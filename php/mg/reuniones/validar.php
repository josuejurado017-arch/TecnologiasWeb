<?php

// Validar u observar una reunion (HU-035). Solo POST, solo la Coordinacion.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.validar');
mg_require_post();

$id = (int) filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
$accion = is_string($_POST['accion'] ?? null) ? $_POST['accion'] : '';
$reunion = $id ? (new MgReunion())->find($id) : null;
if ($reunion === null) {
    http_response_code(404);
    exit('Reunión no encontrada.');
}
$error = (new MgSeguimientoController())->validarReunionCoordinacion($id, $accion, (string) ($_POST['motivo'] ?? ''), (int) Auth::user()['id_usuario']);

if (($_POST['volver'] ?? '') === 'seguimiento') {
    mg_redirect('seguimiento.php?expediente=' . (int) $reunion['id_expediente'] . '#reuniones',
        $error ? ['error' => $error] : ['message' => $accion === 'validar' ? 'validada' : 'observada']);
}
$filtros = array_intersect_key($_POST, array_flip(['estado', 'id_cohorte', 'id_tutor', 'desde', 'hasta']));
$filtros = array_filter(array_map(static fn ($v) => is_string($v) ? $v : '', $filtros), static fn (string $v): bool => $v !== '');
mg_redirect('reuniones/?' . http_build_query($filtros), $error ? ['error' => $error] : ['message' => $accion === 'validar' ? 'validada' : 'observada']);
