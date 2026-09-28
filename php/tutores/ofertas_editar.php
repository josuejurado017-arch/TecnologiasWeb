<?php

// La coordinacion edita una oferta aprobada (turnos, modalidad, cupo). El tutor ya
// no puede editarla desde "Mis materias": una oferta aprobada queda bloqueada.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Editar oferta';
$activePage = 'ofertas-tutores';

$tutorId = (int) filter_var($_GET['tutor'] ?? $_POST['id_tutor'] ?? 0, FILTER_VALIDATE_INT);
$materiaId = (int) filter_var($_GET['materia'] ?? $_POST['id_materia'] ?? 0, FILTER_VALIDATE_INT);
$modelo = new TutorMateriaConfig();
$oferta = null;
foreach ($modelo->aprobadas() as $fila) {
    if ((int) $fila['id_tutor'] === $tutorId && (int) $fila['id_materia'] === $materiaId) {
        $oferta = $fila;
    }
}
if ($oferta === null) {
    header('Location: ' . app_url('tutores/ofertas/?error=' . rawurlencode('La oferta no existe o ya no está aprobada.')));
    exit;
}

$error = null;
$valores = ['turnos' => $oferta['turnos'], 'modalidad' => $oferta['modalidad'], 'cupo_recomendado' => $oferta['cupo_recomendado'], 'motivo' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario no es válida. Recarga la página.';
    } else {
        $error = (new OfertaTutorController())->editar($tutorId, $materiaId, $_POST, (int) Auth::user()['id_usuario']);
        if ($error === null) {
            header('Location: ' . app_url('tutores/ofertas/?message=edited#aprobadas'));
            exit;
        }
        $valores = [
            'turnos' => is_array($_POST['turnos'] ?? null) ? $_POST['turnos'] : [],
            'modalidad' => (string) ($_POST['modalidad'] ?? ''),
            'cupo_recomendado' => $_POST['cupo_recomendado'] ?? null,
            'motivo' => (string) ($_POST['motivo'] ?? ''),
        ];
    }
}
$turnosInfo = $modelo->turnosDisponibles($tutorId, $materiaId);
$modalidades = TutorMateriaConfig::modalidadesPermitidas((string) $oferta['modalidad_requerida']);

require dirname(__DIR__, 2) . '/views/tutores/ofertas_editar.php';
