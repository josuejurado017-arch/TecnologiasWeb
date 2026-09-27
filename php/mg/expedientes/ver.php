<?php

// Ficha del expediente (HU-024): datos, tutor, tribunales, defensas y notas, documentos, etapas.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.ver');
$activePage = 'mg-expedientes';

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$expediente = $id ? (new MgExpediente())->find($id) : null;
if ($expediente === null) {
    http_response_code(404);
    exit('Expediente no encontrado.');
}

[$message, $error, $aviso] = mg_flash([
    'creado' => 'Expediente creado.',
    'datos' => 'Datos guardados.',
    'estado' => 'Estado actualizado.',
    'etapa' => 'Cambio de etapa registrado.',
    'tutor' => 'Tutor asignado. Se generó la carta de asignación y se notificó al tutor y al estudiante.',
    'tribunales' => 'Tribunales guardados.',
    'defensa' => 'Defensa programada.',
    'reprogramada' => 'Defensa reprogramada. La anterior queda en el historial.',
    'realizada' => 'Defensa marcada como realizada. Ya puedes registrar la nota.',
    'cancelada' => 'Defensa cancelada.',
    'nota' => 'Nota guardada. El cambio queda en la bitácora.',
    'citaciones' => 'Citaciones generadas.',
]);

$asignaciones = new MgAsignacion();
$tribunalesModel = new MgTribunal();
$defensasModel = new MgDefensa();
$tutorVigente = $asignaciones->vigente($id);
$historialTutores = $asignaciones->historial($id);
$tribunales = ['mg1' => $tribunalesModel->vigentes($id, 'mg1'), 'mg2' => $tribunalesModel->vigentes($id, 'mg2')];
$historialTribunales = $tribunalesModel->historial($id);
$defensas = $defensasModel->porExpediente($id);
$notas = $defensasModel->notasPorEtapa($id);
$documentos = (new MgDocumento())->listar($id);
$etapas = (new MgExpediente())->etapas($id);
$activo = $expediente['estado'] === 'activo';
$enDefensa = $activo && in_array($expediente['etapa_actual'], ['mg1', 'mg2'], true);
$hayProgramada = $enDefensa && $defensasModel->programada($id, (string) $expediente['etapa_actual']) !== null;
$documentoNuevo = (int) filter_input(INPUT_GET, 'documento', FILTER_VALIDATE_INT);
$title = 'Expediente · ' . $expediente['estudiante'];

require dirname(__DIR__, 3) . '/views/mg/expediente.php';
