<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('estudiante');
Auth::requireModule('evaluaciones');
$title = 'Evaluaciones';
$activePage = 'mis-evaluaciones';
$user = Auth::user();

$studentId = (new Tutoria())->studentIdByUserId((int) $user['id_usuario']);
$controller = new EvaluacionGrupoController();

// Periodo activo y periodos cerrados cuyo plazo de gracia para evaluar sigue abierto.
$periodosEvaluables = (new Periodo())->evaluables();
$periodo = $periodosEvaluables[0] ?? null;
$pendientes = [];
$realizadas = [];
$plazos = [];
foreach ($studentId ? $periodosEvaluables : [] as $p) {
    $pend = $controller->pending($studentId, (int) $p['id_periodo']);
    $pendientes = array_merge($pendientes, $pend);
    $realizadas = array_merge($realizadas, $controller->done($studentId, (int) $p['id_periodo']));
    if ($pend && $p['estado'] === 'cerrada') {
        $plazos[] = ['nombre' => $p['nombre'], 'hasta' => $p['evaluaciones_hasta']];
    }
}
$message = ($_GET['message'] ?? '') === 'saved' ? 'Evaluacion registrada. Gracias por tu opinion.' : null;

require dirname(__DIR__, 2) . '/views/evaluaciones/mis.php';
