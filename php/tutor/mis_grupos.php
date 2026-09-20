<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('tutor');
Auth::requireModule('tutorias');
$title = 'Mis grupos';
$activePage = 'mis-grupos';
$user = Auth::user();

$periodo = (new Periodo())->activa();
$tutorId = (new Tutoria())->tutorIdByUserId((int) $user['id_usuario']);

$grupos = ($periodo && $tutorId) ? (new Grupo())->forTutor($tutorId, (int) $periodo['id_periodo']) : [];
$inscripcionModel = new Inscripcion();
$sesionModel = new Sesion();
$inscritosPorGrupo = [];
$sesionesPorGrupo = [];
foreach ($grupos as $grupo) {
    $inscritosPorGrupo[$grupo['id_grupo']] = $inscripcionModel->forGroup((int) $grupo['id_grupo']);
    $sesionesPorGrupo[$grupo['id_grupo']] = $sesionModel->forGroup((int) $grupo['id_grupo']);
}

require dirname(__DIR__, 2) . '/views/tutor/mis-grupos.php';
