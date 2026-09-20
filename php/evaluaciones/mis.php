<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('estudiante');
Auth::requireModule('evaluaciones');
$title = 'Evaluaciones';
$activePage = 'mis-evaluaciones';
$user = Auth::user();

$periodo = (new Periodo())->activa();
$studentId = (new Tutoria())->studentIdByUserId((int) $user['id_usuario']);
$controller = new EvaluacionGrupoController();

$pendientes = ($periodo && $studentId) ? $controller->pending($studentId, (int) $periodo['id_periodo']) : [];
$realizadas = ($periodo && $studentId) ? $controller->done($studentId, (int) $periodo['id_periodo']) : [];
$message = ($_GET['message'] ?? '') === 'saved' ? 'Evaluacion registrada. Gracias por tu opinion.' : null;

require dirname(__DIR__, 2) . '/views/evaluaciones/mis.php';
