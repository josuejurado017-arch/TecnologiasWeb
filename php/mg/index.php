<?php

// Panel de Modalidades de Grado = dashboard del Coordinador (HU-039): KPIs,
// alertas altas, agenda, reuniones por validar y carga por tutor.

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
$alertasAbiertas = Auth::canDo('mg.alertas') ? (new MgAlerta())->abiertas() : [];
$alertasAltas = array_values(array_filter($alertasAbiertas, static fn (array $a): bool => $a['severidad'] === 'alta'));
$reunionesPorValidar = (int) ((new MgReunion())->contarPorEstado()['registrada'] ?? 0);
$carga = (new MgExpediente())->cargaPorTutor();
$graficoEtapas = ['labels' => [], 'values' => []];
foreach (['previa', 'mg1', 'mg2'] as $etapa) {
    $graficoEtapas['labels'][] = MgExpediente::ETAPAS[$etapa];
    $graficoEtapas['values'][] = (int) ($panel['por_etapa'][$etapa] ?? 0);
}
$graficoCarga = ['labels' => array_column($carga, 'docente'), 'values' => array_map('intval', array_column($carga, 'carga'))];

require dirname(__DIR__, 2) . '/views/mg/panel.php';
