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

// Propuestas de la coordinacion para tomar la mitad de un grupo lleno (db/042).
$divisiones = (new DivisionGrupoController())->pendientesDelTutor((int) $user['id_usuario']);

$messages = [
    'propuesta' => 'Enlace propuesto. La coordinación lo revisará y te avisará.',
    'division-aceptada' => 'Aceptaste la división: el grupo nuevo ya aparece en tu lista. La coordinación definirá su aula o enlace.',
    'division-rechazada' => 'Rechazaste la división. Se avisó a la coordinación.',
];
$message = $messages[$_GET['message'] ?? ''] ?? null;
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;

require dirname(__DIR__, 2) . '/views/tutor/mis-grupos.php';
