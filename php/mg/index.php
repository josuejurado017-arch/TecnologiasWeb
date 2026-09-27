<?php

// Panel de Modalidades de Grado: resumen y pendientes para la Coordinacion.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAction('mg.ver');
$title = 'Modalidades de Grado';
$activePage = 'mg-panel';

$diasDefensas = 14;
$panel = (new MgExpediente())->panel(
    $diasDefensas,
    (int) MgParametro::entero('tutor_carga_recomendada', 3),
    (int) MgParametro::entero('dias_anticipacion_tribunal', 14)
);

require dirname(__DIR__, 2) . '/views/mg/panel.php';
