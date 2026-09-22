<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Cobertura de tutores';
$activePage = 'cobertura-tutores';

$coverageAll = (new TutorMateriaConfig())->coverageByTutor();
$filter = ($_GET['filtro'] ?? '') === 'sin_horarios' ? 'sin_horarios' : 'todos';
$coverage = $filter === 'sin_horarios'
    ? array_values(array_filter($coverageAll, static fn (array $t): bool => $t['configuradas'] === 0))
    : $coverageAll;
$totalTutores = count($coverageAll);
$totalSinHorarios = count(array_filter($coverageAll, static fn (array $t): bool => $t['configuradas'] === 0));

require dirname(__DIR__, 2) . '/views/tutores/cobertura.php';
