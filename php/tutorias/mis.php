<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('estudiante');
Auth::requireModule('tutorias');
$title = 'Mis tutorias';
$activePage = 'mis-tutorias';
$user = Auth::user();

$periodo = (new Periodo())->activa();
$studentId = (new Tutoria())->studentIdByUserId((int) $user['id_usuario']);
$inscripciones = ($periodo && $studentId)
    ? (new Inscripcion())->forStudent($studentId, (int) $periodo['id_periodo'])
    : [];
// Materias en espera (demanda pendiente): visibles para que el estudiante sepa que su solicitud existe.
$enEspera = ($periodo && $studentId)
    ? array_filter(
        (new OfertaMateria())->catalog($studentId, (int) $periodo['id_periodo'], $periodo, null),
        static fn (array $m): bool => $m['estado'] === OfertaMateria::ESTADO_EN_ESPERA
    )
    : [];
// Interes registrado (db/035): cuantos reuniria hoy su mejor turno, contra el minimo del periodo.
$motor = new AsignacionController();
foreach ($enEspera as &$m) {
    $m['reunidos'] = $m['motivo_espera'] === Demanda::MOTIVO_ESPERANDO
        ? $motor->progresoInteres($studentId, (int) $m['id_materia'], (int) $periodo['id_periodo'], (int) $periodo['cupo_max_default'])
        : null;
}
unset($m);

require dirname(__DIR__, 2) . '/views/tutorias/mis.php';
