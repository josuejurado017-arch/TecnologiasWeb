<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');

$title = 'Reportes de tutorias';
$activePage = 'reportes';
$controller = new ReportesController();
$filters = $controller->filters($_GET);
[$rows, $summary] = $controller->report($filters);
$options = $controller->options();
$exportQuery = http_build_query(array_filter($filters, static fn ($value) => $value !== ''));
$exportUrl = app_url('reportes/tutorias/export.php') . ($exportQuery !== '' ? '?' . $exportQuery : '');
require dirname(__DIR__, 2) . '/views/reportes/tutorias.php';
