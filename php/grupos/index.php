<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Grupos de tutoria';
$activePage = 'grupos';

$periodo = (new Periodo())->activa();
$grupos = $periodo ? (new Grupo())->allByPeriodo((int) $periodo['id_periodo']) : [];
$demanda = $periodo ? (new Demanda())->summaryByPeriodo((int) $periodo['id_periodo']) : [];

require dirname(__DIR__, 2) . '/views/grupos/index.php';
