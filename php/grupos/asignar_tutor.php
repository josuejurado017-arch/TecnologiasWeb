<?php

// Asignar tutor a una materia (db/039): la coordinacion propone a un tutor habilitado
// los turnos, la modalidad y el cupo; queda como propuesta hasta que el tutor la
// acepte en "Mis materias". Aceptada, el motor forma grupos con la demanda en espera.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Asignar tutor';
$activePage = 'grupos';

$materiaId = filter_input(INPUT_GET, 'materia', FILTER_VALIDATE_INT);
$materia = $materiaId ? (new Materia())->findById($materiaId) : null;
if (!$materia) {
    http_response_code(404);
    exit('Materia no encontrada.');
}

$error = null;
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario no es válida. Recarga la página.';
    } else {
        $error = (new OfertaTutorController())->proponer((int) $materiaId, $_POST, (int) Auth::user()['id_usuario']);
        if ($error === null) {
            header('Location: ' . app_url('grupos/?message=propuesta_enviada#demanda'));
            exit;
        }
    }
}

$periodo = (new Periodo())->activa();
$config = new TutorMateriaConfig();
$candidatos = $config->candidatosParaMateria((int) $materiaId, $periodo ? (int) $periodo['id_periodo'] : 0);
$propuestas = $config->propuestasPorMateria()[(int) $materiaId] ?? [];
$esperando = $periodo ? count((new Demanda())->pendingForMatter((int) $periodo['id_periodo'], (int) $materiaId)) : 0;
$completos = $periodo ? array_values(array_filter((new Grupo())->completosConDemanda((int) $periodo['id_periodo']), static fn (array $g): bool => (int) $g['id_materia'] === (int) $materiaId)) : [];
$modalidades = TutorMateriaConfig::modalidadesPermitidas((string) ($materia['modalidad_requerida'] ?? 'libre'));

require dirname(__DIR__, 2) . '/views/grupos/asignar_tutor.php';
