<?php

// Bandeja de habilitacion docente: tutores autorregistrados que esperan el visto
// bueno de la coordinacion, y los rechazados (que pueden reconsiderarse).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Tutores pendientes';
$activePage = 'tutores-pendientes';

$messages = [
    'rejected' => 'Tutor rechazado. Se le notificó el motivo.',
];
$messageCode = isset($_GET['message']) && is_string($_GET['message']) ? $_GET['message'] : '';
$message = $messages[$messageCode] ?? null;
if ($messageCode === 'approved') {
    $materias = (int) ($_GET['materias'] ?? 0);
    $atendidos = (int) ($_GET['atendidos'] ?? 0);
    $message = $materias === 0
        ? 'Tutor aprobado. Aún no tiene materias con horarios configurados: recibirá grupos cuando las configure.'
        : sprintf('Tutor aprobado. Se reprocesó la demanda de %d materia%s: %d estudiante%s en espera obtuvieron grupo (pendiente de tu aprobación en Grupos).', $materias, $materias === 1 ? '' : 's', $atendidos, $atendidos === 1 ? '' : 's');
}
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
$tutores = (new HabilitacionTutorController())->bandeja();

require dirname(__DIR__, 2) . '/views/tutores/pendientes.php';
