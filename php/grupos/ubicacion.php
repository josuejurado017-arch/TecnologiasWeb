<?php

// Revisar grupo: pantalla de trabajo del coordinador para un grupo propuesto por
// el motor. Materia, tutor, estudiantes, turno y demanda son solo
// lectura; el coordinador elige la frecuencia (LMV/MJS, solo en grupos reducidos),
// puede cambiar la modalidad si la materia no exige una, registra el aula
// (presencial) o el enlace (virtual), agrega observaciones y aprueba o rechaza.
// La ubicacion es obligatoria para aprobar y el espacio se calcula. En un grupo ya
// aprobado solo se cambian modalidad y ubicacion (con motivo).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('administrador');
$title = 'Revisar grupo';
$activePage = 'grupos';

$id = filter_input(INPUT_GET, 'grupo', FILTER_VALIDATE_INT);
$controller = new GruposController();
$grupo = $id ? $controller->find($id) : null;
if (!$grupo) {
    http_response_code(404);
    exit('Grupo no encontrado.');
}

$error = null;
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario no es válida. Recarga la página.';
    } else {
        $adminId = (int) Auth::user()['id_usuario'];
        $accion = (string) ($_POST['accion'] ?? '');
        switch ($accion) {
            case 'aprobar':
                $error = $controller->aprobar($id, $_POST, $adminId);
                $mensaje = 'aprobado';
                break;
            case 'rechazar':
                $error = $controller->rechazar($id, (string) ($_POST['motivo_rechazo_grupo'] ?? ''), $adminId);
                $mensaje = 'grupo_rechazado';
                break;
            case 'rechazar_propuesta':
                $error = $controller->rechazarPropuesta($id, (string) ($_POST['motivo_rechazo'] ?? ''), $adminId);
                $mensaje = 'rechazada';
                break;
            case 'guardar':
            case 'aprobar_propuesta':
                $error = $controller->definirUbicacion($id, $_POST, $adminId);
                $mensaje = 'guardada';
                break;
            // Gestion manual de la coordinacion (db/039).
            case 'cambiar_tutor':
                $error = $controller->cambiarTutor($id, (int) ($_POST['id_tutor'] ?? 0), (string) ($_POST['motivo_tutor'] ?? ''), $adminId);
                $mensaje = 'tutor_cambiado';
                break;
            case 'inscribir':
                $error = $controller->inscribirManual($id, (int) ($_POST['id_estudiante'] ?? 0), $adminId);
                $mensaje = 'inscrito';
                break;
            // Division de un grupo lleno (db/042).
            case 'dividir':
                $error = (new DivisionGrupoController())->proponer($id, (int) ($_POST['id_tutor_division'] ?? 0), $adminId);
                $mensaje = 'division_enviada';
                break;
            case 'cancelar_division':
                $error = (new DivisionGrupoController())->cancelar((int) ($_POST['id_division'] ?? 0), $adminId);
                $mensaje = 'division_cancelada';
                break;
            case 'retirar':
                [$error, $aviso] = $controller->retirarEstudiante($id, (int) ($_POST['id_estudiante'] ?? 0), (string) ($_POST['motivo_retiro'] ?? ''), $adminId);
                $mensaje = 'retirado';
                break;
            default:
                $error = 'Acción no válida.';
        }
        if ($error === null) {
            $extra = !empty($aviso) ? '&aviso=' . rawurlencode($aviso) : '';
            header('Location: ' . app_url('grupos/ubicacion.php?grupo=' . $id . '&message=' . $mensaje . $extra));
            exit;
        }
        $grupo = $controller->find($id);
    }
}

$messages = [
    'guardada' => 'Ubicación guardada. Se avisó al tutor y a los inscritos.',
    'rechazada' => 'Propuesta rechazada. Se avisó al tutor.',
    'aprobado' => 'Grupo aprobado. Se generó su calendario y se avisó al tutor y a los inscritos.',
    'grupo_rechazado' => 'Grupo rechazado. Los inscritos volvieron a la lista de espera.',
    'tutor_cambiado' => 'Tutor cambiado. Se avisó a los dos tutores y a los inscritos.',
    'inscrito' => 'Estudiante inscrito. Se le avisó.',
    'retirado' => 'Estudiante retirado. Se le avisó.',
    'division_enviada' => 'Propuesta de división enviada. El grupo se divide cuando el tutor la acepte.',
    'division_cancelada' => 'Propuesta de división retirada.',
];
$message = $messages[$_GET['message'] ?? ''] ?? null;
$avisoAccion = isset($_GET['aviso']) && is_string($_GET['aviso']) ? $_GET['aviso'] : null;
$busqueda = isset($_GET['buscar']) && is_string($_GET['buscar']) ? mb_substr(trim($_GET['buscar']), 0, 60) : '';

$vigente = in_array($grupo['estado'], Grupo::ESTADOS_VIGENTES, true);
$historialUbicacion = $controller->ubicacionHistorial($id);

$periodo = (new Periodo())->findById((int) $grupo['id_periodo']);
$diasGrupo = (new Grupo())->dias($id);
$tipoFrecuencia = GruposController::tipoFrecuencia($diasGrupo, (int) $grupo['cupo_ocupado']);
$patronActual = Grupo::patronReducido($diasGrupo);
$inscritos = $controller->enrolled($id);
$demandaPendiente = $controller->demandaPendiente((int) $grupo['id_periodo'], (int) $grupo['id_materia']);
$tutoresDisponibles = $vigente ? $controller->tutoresDisponibles($grupo) : [];
$candidatos = $vigente && (int) $grupo['cupo_ocupado'] < (int) $grupo['cupo_max'] ? $controller->candidatosInscripcion($grupo, $busqueda) : [];
$cierreInscripcion = (new Grupo())->cierreInscripcion($id);

// Division (db/042): vista previa con el tutor elegido; se ejecuta cuando el tutor acepta.
$divisiones = new DivisionGrupoController();
$divisionPendiente = $vigente ? $divisiones->pendiente($id) : null;
$bloqueoDivision = $vigente ? $divisiones->bloqueoGrupo($grupo) : null;
$mostrarDivision = $vigente && ($divisionPendiente !== null || (int) $grupo['cupo_ocupado'] >= (int) $grupo['cupo_max']);
$tutoresDivision = $mostrarDivision && $bloqueoDivision === null ? $divisiones->tutoresElegibles($grupo) : [];
$tutorPrevia = filter_input(INPUT_GET, 'dividir_tutor', FILTER_VALIDATE_INT) ?: null;
$tutorPreviaNombre = null;
foreach ($tutoresDivision as $t) {
    if ((int) $t['id_tutor'] === $tutorPrevia) {
        $tutorPreviaNombre = $t['tutor'];
    }
}
$planDivision = $tutorPreviaNombre !== null ? $divisiones->plan($grupo) : null;

require dirname(__DIR__, 2) . '/views/grupos/ubicacion.php';
