<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('estudiante');
Auth::requireModule('tutorias');
$title = 'Nueva solicitud';
$activePage = 'tutorias';
$user = Auth::user();
$controller = new TutoriasController();
$model = new Tutoria();
$studentId = $model->studentIdByUserId((int) $user['id_usuario']);
$data = [
    'id_materia' => filter_var($_GET['id_materia'] ?? null, FILTER_VALIDATE_INT) ?: '',
    'id_tutor' => filter_var($_GET['id_tutor'] ?? null, FILTER_VALIDATE_INT) ?: '',
    'slot_key' => trim((string) ($_GET['slot_key'] ?? '')),
    'modalidad' => 'presencial',
    'lugar_o_enlace' => '',
    'observaciones' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesion del formulario no es valida. Recargue la pagina.';
    } else {
        [$data, $errors] = $controller->store($_POST, $studentId, (int) $user['id_usuario']);
        if (!$errors) {
            header('Location: ' . app_url('tutorias/?message=created'));
            exit;
        }
    }
}

$selectedMatter = filter_var($data['id_materia'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$selectedTutor = filter_var($data['id_tutor'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$subjects = $studentId ? $model->subjectOptions($studentId) : [];
$tutors = $studentId && $selectedMatter ? $model->tutorsForMatter($selectedMatter, $studentId) : [];
$slots = $studentId && $selectedMatter && $selectedTutor
    ? $model->availableSlots($studentId, $selectedTutor, $selectedMatter)
    : [];
$selectedSlot = null;
foreach ($slots as $slot) {
    if ($slot['slot_key'] === ($data['slot_key'] ?? '')) {
        $selectedSlot = $slot;
        break;
    }
}
require dirname(__DIR__, 2) . '/views/tutorias/form.php';
