<?php

declare(strict_types=1);

final class SolicitudesEspecialesController
{
    private SolicitudHorarioEspecial $model;
    private Tutoria $tutoria;
    private HistorialTutoria $history;
    private Notificacion $notifications;

    public function __construct()
    {
        $this->model = new SolicitudHorarioEspecial();
        $this->tutoria = new Tutoria();
        $this->history = new HistorialTutoria();
        $this->notifications = new Notificacion();
    }

    public function subjects(int $studentId): array
    {
        return $this->model->subjects($studentId);
    }

    public function tutors(int $matterId, int $studentId): array
    {
        return $this->model->tutors($matterId, $studentId);
    }

    public function index(string $role, int $userId): array
    {
        return $this->model->allForViewer($role, $userId);
    }

    public function store(array $input, int $studentId): array
    {
        $data = [
            'id_estudiante' => $studentId,
            'id_materia' => filter_var($input['id_materia'] ?? null, FILTER_VALIDATE_INT) ?: '',
            'id_tutor' => filter_var($input['id_tutor'] ?? null, FILTER_VALIDATE_INT) ?: '',
            'fecha_propuesta' => trim((string) ($input['fecha_propuesta'] ?? '')),
            'hora_inicio' => trim((string) ($input['hora_inicio'] ?? '')),
            'hora_fin' => trim((string) ($input['hora_fin'] ?? '')),
            'modalidad' => trim((string) ($input['modalidad'] ?? 'presencial')),
            'lugar_o_enlace' => trim((string) ($input['lugar_o_enlace'] ?? '')),
            'observaciones' => trim((string) ($input['observaciones'] ?? '')),
        ];
        $errors = [];
        $date = DateTime::createFromFormat('!Y-m-d', $data['fecha_propuesta']);
        if (!$data['id_materia'] || !$data['id_tutor']) {
            $errors[] = 'Seleccione una materia y un tutor validos.';
        }
        if (!$date || $date->format('Y-m-d') !== $data['fecha_propuesta'] || $data['fecha_propuesta'] < date('Y-m-d')) {
            $errors[] = 'La fecha propuesta debe ser valida y futura.';
        }
        if (validation_time($data['hora_inicio'], 'hora inicial') !== null || validation_time($data['hora_fin'], 'hora final') !== null || $data['hora_fin'] <= $data['hora_inicio']) {
            $errors[] = 'Ingrese un horario valido.';
        }
        if ($data['fecha_propuesta'] === date('Y-m-d') && $data['hora_inicio'] <= date('H:i')) {
            $errors[] = 'La hora propuesta debe ser posterior a la hora actual.';
        }
        if (!in_array($data['modalidad'], ['presencial', 'virtual'], true)) {
            $errors[] = 'Seleccione una modalidad valida.';
        }
        if ($data['modalidad'] === 'virtual' && $data['lugar_o_enlace'] === '') {
            $errors[] = 'Ingrese el enlace para una solicitud virtual.';
        }
        if (strlen($data['lugar_o_enlace']) > 200 || strlen($data['observaciones']) > 2000) {
            $errors[] = 'Los datos superan la longitud permitida.';
        }
        if (!$errors && ($this->tutoria->hasTutorConflict((int) $data['id_tutor'], $data['fecha_propuesta'], $data['hora_inicio'], $data['hora_fin']) || $this->tutoria->hasStudentConflict($studentId, $data['fecha_propuesta'], $data['hora_inicio'], $data['hora_fin']))) {
            $errors[] = 'El horario propuesto ya entra en conflicto con otra tutoria.';
        }
        if ($errors) {
            return [$data, $errors];
        }

        try {
            $pdo = Database::connection();
            $pdo->beginTransaction();
            $this->tutoria->lockSchedulingActors($pdo, $studentId, (int) $data['id_tutor']);
            if ($this->tutoria->hasTutorConflict((int) $data['id_tutor'], $data['fecha_propuesta'], $data['hora_inicio'], $data['hora_fin']) || $this->tutoria->hasStudentConflict($studentId, $data['fecha_propuesta'], $data['hora_inicio'], $data['hora_fin'])) {
                throw new RuntimeException('El horario propuesto ya entra en conflicto con otra tutoria.');
            }
            $id = $this->model->create($data);
            $tutorUserId = $this->model->tutorUserId((int) $data['id_tutor']);
            if ($tutorUserId) {
                $this->notifications->create($pdo, $tutorUserId, null, 'horario_especial', 'Solicitud de horario especial', 'Tienes una solicitud de horario especial para revisar.', '/tutorias/especiales/', 'horario_especial:' . $id . ':' . $tutorUserId);
            }
            $pdo->commit();
            return [$data, []];
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());
            return [$data, [$exception instanceof RuntimeException ? $exception->getMessage() : 'No fue posible registrar la solicitud especial.']];
        }
    }

    public function decide(int $id, string $action, string $role, int $userId, string $response): ?string
    {
        if (!in_array($action, ['approve', 'reject'], true)) {
            return 'Accion no valida.';
        }
        if ($action === 'reject' && trim($response) === '') {
            return 'Indique el motivo del rechazo.';
        }
        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $request = $this->model->findForDecision($pdo, $id, $role, $userId);
            if (!$request) {
                throw new RuntimeException('La solicitud no existe, ya fue respondida o no le pertenece.');
            }
            if (strlen($response) > 500) {
                throw new RuntimeException('La respuesta no puede superar 500 caracteres.');
            }
            if ($action === 'approve') {
                $this->tutoria->lockSchedulingActors($pdo, (int) $request['id_estudiante'], (int) $request['id_tutor']);
                $proposedAt = DateTimeImmutable::createFromFormat(
                    '!Y-m-d H:i:s',
                    $request['fecha_propuesta'] . ' ' . $request['hora_inicio']
                );
                if (!$proposedAt || $proposedAt <= new DateTimeImmutable()) {
                    throw new RuntimeException('El horario propuesto ya ha pasado y no puede aprobarse.');
                }
                $tutoriaId = $this->model->approve($pdo, $request, trim($response));
                $this->history->record($pdo, [
                    'id_tutoria' => $tutoriaId,
                    'id_usuario' => $userId,
                    'tipo_evento' => 'solicitud_especial_aprobada',
                    'estado_nuevo' => 'confirmada',
                    'motivo' => trim($response),
                    'datos_nuevos' => $request,
                ]);
                $this->notifications->forTutoriaEvent($pdo, $tutoriaId, 'tutoria_confirmada');
            } else {
                $this->model->reject($pdo, $id, trim($response));
                $this->notifications->create($pdo, (int) $request['estudiante_usuario'], null, 'horario_especial_rechazado', 'Solicitud especial rechazada', trim($response), '/tutorias/especial.php', 'horario_especial_rechazado:' . $id . ':' . $request['estudiante_usuario']);
            }
            $pdo->commit();
            return null;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());

            return $exception instanceof RuntimeException ? $exception->getMessage() : 'No fue posible responder la solicitud.';
        }
    }
}
