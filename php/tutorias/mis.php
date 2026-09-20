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

require dirname(__DIR__, 2) . '/views/tutorias/mis.php';
