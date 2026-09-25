<?php

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Materias';
$activePage = 'materias';

$messages = [
    'created' => 'Materia creada correctamente.',
    'updated' => 'Materia actualizada correctamente.',
    'deleted' => 'Materia eliminada correctamente.',
];
$messageCode = isset($_GET['message']) && is_string($_GET['message']) ? $_GET['message'] : '';
$message = $messages[$messageCode] ?? null;
if ($messageCode === 'updated_incompatibles') {
    $tutores = max(1, (int) ($_GET['tutores'] ?? 1));
    $message = sprintf(
        'Materia actualizada. %d tutor(es) tienen configurada otra modalidad para esta materia: no recibirán grupos en ella hasta ajustarla. Se les avisó.',
        $tutores
    );
}
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
$materias = (new MateriasController())->index();

require dirname(__DIR__, 2) . '/views/materias/index.php';
