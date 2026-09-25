<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Grupos de tutoria';
$activePage = 'grupos';

// Etapas visibles (db/035). 'por_aprobar' se mantiene como alias de enlaces antiguos:
// muestra primero los listos para revision.
$estadosFiltro = ['listo', 'formacion', 'confirmado', 'en_curso', 'finalizado', 'cancelado', 'sin_ubicacion', 'por_aprobar'];
$filtro = in_array($_GET['estado'] ?? '', $estadosFiltro, true) ? $_GET['estado'] : null;

$periodo = (new Periodo())->activa();
$modelo = new Grupo();
if ($periodo && $filtro === 'por_aprobar') {
    $grupos = array_merge(
        $modelo->allByPeriodo((int) $periodo['id_periodo'], 'listo'),
        $modelo->allByPeriodo((int) $periodo['id_periodo'], 'formacion')
    );
} else {
    $grupos = $periodo ? $modelo->allByPeriodo((int) $periodo['id_periodo'], $filtro) : [];
}
$etapas = $periodo ? $modelo->contarPorEtapa((int) $periodo['id_periodo']) : [];
$sinUbicacion = $modelo->countSinUbicacion();
$demanda = $periodo ? (new Demanda())->summaryByPeriodo((int) $periodo['id_periodo']) : [];
$interesTotal = array_sum(array_map(static fn (array $d): int => (int) $d['solicitudes'], $demanda));
// Materias con demanda y sin tutor (db/039): no son grupos todavia; la coordinacion
// les asigna un tutor que debe aceptar la propuesta.
$sinTutor = array_values(array_filter($demanda, static fn (array $d): bool => (int) $d['sin_tutor'] > 0));
$completos = $periodo ? $modelo->completosConDemanda((int) $periodo['id_periodo']) : [];
$propuestas = (new TutorMateriaConfig())->propuestasPorMateria();

require dirname(__DIR__, 2) . '/views/grupos/index.php';
