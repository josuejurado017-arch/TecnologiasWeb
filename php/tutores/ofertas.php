<?php

// Bandeja de ofertas de materia (db/032): turnos que un tutor guardo en "Mis
// materias" y esperan que la coordinacion los apruebe o rechace. La ubicacion no
// se decide aqui: pertenece al grupo (Grupos de tutoria > Revisar grupo).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Ofertas de materias';
$activePage = 'ofertas-tutores';

$messages = [
    'rejected' => 'Oferta rechazada. Se le notificó el motivo al tutor.',
];
$messageCode = isset($_GET['message']) && is_string($_GET['message']) ? $_GET['message'] : '';
$message = $messages[$messageCode] ?? null;
if ($messageCode === 'approved') {
    $atendidos = (int) ($_GET['atendidos'] ?? 0);
    $message = $atendidos === 0
        ? 'Oferta aprobada. El sistema ya puede proponerle grupos al tutor en ese horario.'
        : sprintf('Oferta aprobada. %d estudiante%s en espera obtuvieron grupo (pendiente de tu aprobación en Grupos de tutoría).', $atendidos, $atendidos === 1 ? '' : 's');
}
$error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;

$ofertas = (new OfertaTutorController())->pendientes();
$turnoLabels = array_map(static fn (array $t): string => $t['label'] . ' (' . substr($t['inicio'], 0, 5) . '–' . substr($t['fin'], 0, 5) . ')', TutorMateriaConfig::TURNOS);

require dirname(__DIR__, 2) . '/views/tutores/ofertas.php';
