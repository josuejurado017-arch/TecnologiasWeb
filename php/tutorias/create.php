<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('estudiante');
Auth::requireModule('tutorias');
$title = 'Solicitar apoyo';
$activePage = 'tutorias';
$user = Auth::user();

$periodo = (new Periodo())->activa();
$studentId = (new Tutoria())->studentIdByUserId((int) $user['id_usuario']);
$controller = new AsignacionController();
$oferta = new OfertaMateria();

$career = $studentId ? $oferta->studentCareer($studentId) : null;
// Filtro: "Mi carrera" por defecto; ?carrera=todas muestra el catalogo completo.
$showAll = (($_GET['carrera'] ?? $_POST['carrera'] ?? 'mia') === 'todas') || $career === null;
$careerFilter = $showAll ? null : (int) $career['id_carrera'];

$results = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario no es válida. Recargue la página.';
    } elseif (!$periodo) {
        $errors[] = 'No hay un periodo de tutoría activo en este momento.';
    } elseif (!$studentId) {
        $errors[] = 'Tu perfil de estudiante no está completo.';
    } else {
        $accion = (string) ($_POST['accion'] ?? 'solicitar');
        $materiaId = (int) ($_POST['id_materia'] ?? 0);

        if ($accion === 'interes' && $materiaId > 0) {
            $results = [$controller->registrarInteres($studentId, $materiaId, $periodo)];
        } elseif ($accion === 'reintentar' && $materiaId > 0) {
            $results = [$controller->reintentar($studentId, $materiaId, $periodo)];
        } elseif ($accion === 'quitar' && $materiaId > 0) {
            $results = [$controller->quitarEspera($studentId, $materiaId, $periodo)];
        } else {
            $selected = isset($_POST['materias']) && is_array($_POST['materias']) ? $_POST['materias'] : [];
            $selected = array_slice(array_unique(array_map('intval', $selected)), 0, 20);
            if (!$selected) {
                $errors[] = 'Selecciona al menos una materia.';
            } else {
                $results = $controller->solicitarApoyo($studentId, $selected, $periodo);
            }
        }
    }
}

// El catalogo se calcula despues del POST para reflejar el estado actualizado.
$subjects = ($periodo && $studentId) ? $controller->catalog($studentId, $periodo, $careerFilter) : [];
$summary = $oferta->summary($subjects);

require dirname(__DIR__, 2) . '/views/tutorias/form.php';
