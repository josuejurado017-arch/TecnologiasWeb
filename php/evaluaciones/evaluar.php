<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('estudiante');
Auth::requireModule('evaluaciones');
$title = 'Evaluar tutoria';
$activePage = 'mis-evaluaciones';
$user = Auth::user();

$studentId = (new Tutoria())->studentIdByUserId((int) $user['id_usuario']);
$inscripcionId = filter_input(INPUT_GET, 'inscripcion', FILTER_VALIDATE_INT);
$controller = new EvaluacionGrupoController();
$inscripcion = ($studentId && $inscripcionId) ? $controller->findEvaluable($inscripcionId, $studentId) : null;

if (!$inscripcion) {
    http_response_code(404);
    exit('Tutoria no disponible para evaluar.');
}

$errors = [];
$data = ['general' => 5, 'puntualidad' => 5, 'dominio' => 5, 'claridad' => 5, 'utilidad' => 5, 'comentario' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesion del formulario no es valida. Recargue la pagina.';
    } else {
        $data = array_merge($data, array_intersect_key($_POST, $data));
        $errors = $controller->save((int) $inscripcionId, $studentId, $_POST);
        if (!$errors) {
            header('Location: ' . app_url('mis-evaluaciones/?message=saved'));
            exit;
        }
    }
}

require dirname(__DIR__, 2) . '/views/evaluaciones/evaluar.php';
