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

$subjects = [];
$results = null;
$errors = [];

if ($periodo && $studentId) {
    $subjects = $controller->matterOptions($studentId, (int) $periodo['id_periodo']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesion del formulario no es valida. Recargue la pagina.';
    } elseif (!$periodo) {
        $errors[] = 'No hay una campana de tutorias activa en este momento.';
    } elseif (!$studentId) {
        $errors[] = 'Tu perfil de estudiante no esta completo.';
    } else {
        $selected = isset($_POST['materias']) && is_array($_POST['materias']) ? $_POST['materias'] : [];
        $selected = array_slice(array_unique(array_map('intval', $selected)), 0, 20);
        if (!$selected) {
            $errors[] = 'Selecciona al menos una materia.';
        } else {
            $results = $controller->solicitarApoyo($studentId, $selected, $periodo);
            // Refrescar opciones para quitar las ya solicitadas.
            $subjects = $controller->matterOptions($studentId, (int) $periodo['id_periodo']);
        }
    }
}

require dirname(__DIR__, 2) . '/views/tutorias/form.php';
