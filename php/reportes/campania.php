<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Reportes por período';
$activePage = 'reportes-campania';

$periodoModel = new Periodo();
$periodos = $periodoModel->all();
$selectedId = filter_input(INPUT_GET, 'periodo', FILTER_VALIDATE_INT);
$periodo = $selectedId ? $periodoModel->findById($selectedId) : $periodoModel->activa();
if (!$periodo && $periodos) {
    $periodo = $periodoModel->findById((int) $periodos[0]['id_periodo']);
}

$reporte = new ReporteCampania();
$data = [];
if ($periodo) {
    $pid = (int) $periodo['id_periodo'];
    $data = [
        'totals' => $reporte->totals($pid),
        'topTutores' => $reporte->topTutores($pid),
        'topMaterias' => $reporte->topMaterias($pid),
        'topCarreras' => $reporte->topCarreras($pid),
        'espacios' => $reporte->gruposPorEspacio($pid),
        'asistencia' => $reporte->attendanceBreakdown($pid),
        'satisfaccion' => $reporte->satisfaction($pid),
        'coberturaTutores' => $reporte->coberturaTutores(),
        'demanda' => (new Demanda())->conversion($pid),
        'demandaMaterias' => (new Demanda())->summaryByPeriodo($pid),
    ];
}

require dirname(__DIR__, 2) . '/views/reportes/campania.php';
