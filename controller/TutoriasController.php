<?php

declare(strict_types=1);

final class TutoriasController
{
    private Tutoria $model;
    private HistorialTutoria $history;
    private AsistenciaTutoria $attendance;
    private Notificacion $notifications;

    public function __construct()
    {
        $this->model = new Tutoria();
        $this->history = new HistorialTutoria();
        $this->attendance = new AsistenciaTutoria();
        $this->notifications = new Notificacion();
    }

    public function index(string $role, int $userId, array $filters = []): array
    {
        return $this->model->allForViewer($role, $userId, $filters);
    }

    public function filterOptions(string $role, int $userId): array
    {
        return $this->model->filterOptions($role, $userId);
    }

    public function options(?int $studentId = null): array
    {
        return $this->model->offerings($studentId);
    }

    public function store(array $input, ?int $studentId, int $actorId = 0): array
    {
        $data = $this->normalize($input, $studentId);
        $data['actor_id'] = $actorId;
        $slot = null;
        if ($studentId && filter_var($data['id_tutor'], FILTER_VALIDATE_INT) && filter_var($data['id_materia'], FILTER_VALIDATE_INT)) {
            $slot = $this->model->resolveAvailableSlot(
                (int) $studentId,
                (int) $data['id_tutor'],
                (int) $data['id_materia'],
                $data['slot_key']
            );
            if ($slot) {
                $data['id_disponibilidad'] = $slot['id_disponibilidad'];
                $data['fecha'] = $slot['fecha'];
                $data['hora_inicio'] = $slot['hora_inicio'];
                $data['hora_fin'] = $slot['hora_fin'];
            }
        }
        $errors = $this->validate($data);
        if (!$slot) {
            $errors[] = 'Seleccione un espacio disponible vigente.';
        }
        if (!$errors && !$this->model->hasAvailability((int) $data['id_tutor'], $data['fecha'], $data['hora_inicio'], $data['hora_fin'])) {
            $errors[] = 'El tutor no tiene disponibilidad para ese dia y horario.';
        }
        if (!$errors && $this->model->hasTutorConflict((int) $data['id_tutor'], $data['fecha'], $data['hora_inicio'], $data['hora_fin'])) {
            $errors[] = 'El tutor ya tiene otra tutoria pendiente o confirmada en ese horario.';
        }
        if (!$errors && $this->model->hasStudentConflict((int) $data['id_estudiante'], $data['fecha'], $data['hora_inicio'], $data['hora_fin'])) {
            $errors[] = 'Ya tienes otra tutoria pendiente o confirmada en ese horario.';
        }
        if ($errors) {
            return [$data, $errors];
        }

        try {
            $pdo = Database::connection();
            $pdo->beginTransaction();
            $this->model->lockSchedulingActors($pdo, (int) $data['id_estudiante'], (int) $data['id_tutor']);
            if (!$this->isFutureStart($data['fecha'], $data['hora_inicio'])) {
                throw new RuntimeException('El horario seleccionado ya ha pasado. Elija otro espacio.');
            }
            if (!$this->model->hasAvailability((int) $data['id_tutor'], $data['fecha'], $data['hora_inicio'], $data['hora_fin'])) {
                throw new RuntimeException('El tutor ya no tiene disponibilidad para ese horario.');
            }
            if ($this->model->hasTutorConflict((int) $data['id_tutor'], $data['fecha'], $data['hora_inicio'], $data['hora_fin'])) {
                throw new RuntimeException('El tutor ya tiene otra tutoria en ese horario.');
            }
            if ($this->model->hasStudentConflict((int) $data['id_estudiante'], $data['fecha'], $data['hora_inicio'], $data['hora_fin'])) {
                throw new RuntimeException('Ya tienes otra tutoria en ese horario.');
            }
            $tutoriaId = $this->model->create($data);
            $this->history->record($pdo, [
                'id_tutoria' => $tutoriaId,
                'id_usuario' => $data['actor_id'],
                'tipo_evento' => 'creacion',
                'estado_nuevo' => 'pendiente',
                'motivo' => 'Solicitud de tutoria creada',
                'datos_nuevos' => $this->snapshot($data),
            ]);
            $this->notifications->forTutoriaEvent($pdo, $tutoriaId, 'tutoria_creada');
            $pdo->commit();
            return [$data, []];
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());
            if ($exception instanceof RuntimeException) {
                return [$data, [$exception->getMessage()]];
            }
            return [$data, ['No fue posible crear la solicitud de tutoria.']];
        }
    }

    public function changeStatus(int $id, string $status, string $role, int $userId, string $reason = ''): ?string
    {
        $tutoria = $this->model->findForViewer($id, $role, $userId);
        if (!$tutoria) {
            return 'La tutoria no existe o no puede ser modificada.';
        }
        $transitions = [
            'pendiente' => $role === 'estudiante' ? ['cancelada'] : ['confirmada', 'cancelada'],
            'confirmada' => $role === 'estudiante' ? [] : ['realizada', 'cancelada'],
            'realizada' => [],
            'cancelada' => [],
        ];
        if (!in_array($status, $transitions[$tutoria['estado']] ?? [], true)) {
            return 'El cambio de estado no es valido para este usuario.';
        }
        if ($status === 'cancelada' && trim($reason) === '') {
            return 'Indique el motivo de la cancelacion.';
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $locked = $this->model->findByIdForUpdate($pdo, $id);
            if (!$locked || $locked['estado'] !== $tutoria['estado']) {
                throw new RuntimeException('La tutoria ya fue modificada. Recargue la pagina.');
            }
            if (!$this->model->changeStatus($id, $status, $role, $userId, (string) $tutoria['estado'], trim($reason))) {
                throw new RuntimeException('La tutoria no existe o no puede ser modificada.');
            }
            $this->history->record($pdo, [
                'id_tutoria' => $id,
                'id_usuario' => $userId,
                'tipo_evento' => $status === 'cancelada' ? 'cancelacion' : 'cambio_estado',
                'estado_anterior' => $tutoria['estado'],
                'estado_nuevo' => $status,
                'motivo' => $status === 'cancelada' ? trim($reason) : null,
                'datos_anteriores' => $this->snapshot($tutoria),
                'datos_nuevos' => ['estado' => $status],
            ]);
            if ($status === 'confirmada') {
                $this->notifications->forTutoriaEvent($pdo, $id, 'tutoria_confirmada');
            } elseif ($status === 'cancelada') {
                $this->notifications->forTutoriaEvent($pdo, $id, 'tutoria_cancelada');
            } elseif ($status === 'realizada') {
                $this->notifications->forTutoriaEvent($pdo, $id, 'evaluacion_pendiente');
            }
            $pdo->commit();
            return null;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());
            return $exception instanceof RuntimeException ? $exception->getMessage() : 'No fue posible actualizar la tutoria.';
        }
    }

    public function reschedule(int $id, array $input, string $role, int $userId): array
    {
        $current = $this->model->findForViewer($id, $role, $userId);
        $data = $this->normalizeSchedule($input, $current['id_tutor'] ?? '');
        if (!$current) {
            return [$data, ['La tutoria no existe o no puede ser modificada.']];
        }
        if ($role !== 'administrador' && (int) $data['id_tutor'] !== (int) $current['id_tutor']) {
            $data['id_tutor'] = (string) $current['id_tutor'];
            return [$data, ['No tiene permiso para reasignar la tutoria a otro tutor.']];
        }
        $errors = $this->validateSchedule($data, $current);
        if ($errors) {
            return [$data, $errors];
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $locked = $this->model->findByIdForUpdate($pdo, $id);
            if (!$locked || !in_array($locked['estado'], ['pendiente', 'confirmada'], true)) {
                throw new RuntimeException('La tutoria ya no puede reprogramarse.');
            }
            if ($role === 'estudiante' && (int) $locked['id_estudiante'] !== $this->model->studentIdByUserId($userId)) {
                throw new RuntimeException('La tutoria ya no pertenece al estudiante actual.');
            }
            if ($role === 'tutor' && (int) $locked['id_tutor'] !== $this->model->tutorIdByUserId($userId)) {
                throw new RuntimeException('La tutoria ya no pertenece al tutor actual.');
            }
            if ($role !== 'administrador' && (int) $data['id_tutor'] !== (int) $locked['id_tutor']) {
                throw new RuntimeException('La tutoria fue reasignada y debe recargar la pagina.');
            }
            if ($role === 'estudiante' && $locked['estado'] !== 'pendiente') {
                throw new RuntimeException('Una tutoria confirmada debe ser reprogramada por el tutor o un administrador.');
            }
            $this->model->lockSchedulingActors($pdo, (int) $locked['id_estudiante'], (int) $data['id_tutor']);
            if (!$this->isFutureStart($data['fecha'], $data['hora_inicio'])) {
                throw new RuntimeException('La nueva fecha y hora ya han pasado. Elija otro horario.');
            }
            if (!$this->model->isOfferingForStudent((int) $data['id_tutor'], (int) $locked['id_materia'], (int) $locked['id_estudiante'])) {
                throw new RuntimeException('El tutor no atiende una materia compatible con la carrera del estudiante.');
            }
            if (!$this->model->hasAvailability((int) $data['id_tutor'], $data['fecha'], $data['hora_inicio'], $data['hora_fin'])) {
                throw new RuntimeException('El tutor no tiene disponibilidad para ese dia y horario.');
            }
            if ($this->model->hasTutorConflict((int) $data['id_tutor'], $data['fecha'], $data['hora_inicio'], $data['hora_fin'], $id)) {
                throw new RuntimeException('El tutor ya tiene otra tutoria en ese horario.');
            }
            if ($this->model->hasStudentConflict((int) $locked['id_estudiante'], $data['fecha'], $data['hora_inicio'], $data['hora_fin'], $id)) {
                throw new RuntimeException('El estudiante ya tiene otra tutoria en ese horario.');
            }
            $this->model->updateSchedule($pdo, $id, $data);
            $this->history->record($pdo, [
                'id_tutoria' => $id,
                'id_usuario' => $userId,
                'tipo_evento' => 'reprogramacion',
                'estado_anterior' => $locked['estado'],
                'estado_nuevo' => $locked['estado'],
                'motivo' => $data['motivo'],
                'datos_anteriores' => $this->snapshot($locked),
                'datos_nuevos' => $this->snapshot($data),
            ]);
            $this->notifications->forTutoriaEvent($pdo, $id, 'tutoria_reprogramada');
            $pdo->commit();
            return [$data, []];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());
            return [$data, [$exception instanceof RuntimeException ? $exception->getMessage() : 'No fue posible reprogramar la tutoria.']];
        }
    }

    public function recordAttendance(int $id, array $input, string $role, int $userId): array
    {
        $tutoria = $this->model->findForViewer($id, $role, $userId);
        $data = [
            'estado_asistencia' => trim((string) ($input['estado_asistencia'] ?? '')),
            'minutos_retraso' => trim((string) ($input['minutos_retraso'] ?? '')),
            'observaciones' => trim((string) ($input['observaciones'] ?? '')),
        ];
        if (!$tutoria || !in_array($role, ['administrador', 'tutor'], true)) {
            return [$data, ['No tiene permiso para registrar esta asistencia.']];
        }
        $errors = [];
        if ($tutoria['estado'] !== 'realizada') {
            $errors[] = 'La asistencia solo se registra cuando la tutoria esta realizada.';
        }
        if (!in_array($data['estado_asistencia'], ['asistio', 'no_asistio', 'parcial', 'retraso'], true)) {
            $errors[] = 'Seleccione un estado de asistencia valido.';
        }
        $minutes = filter_var($data['minutos_retraso'], FILTER_VALIDATE_INT);
        if ($data['estado_asistencia'] === 'retraso' && ($minutes === false || $minutes < 1 || $minutes > 600)) {
            $errors[] = 'Indique los minutos de retraso entre 1 y 600.';
        }
        if (strlen($data['observaciones']) > 500) {
            $errors[] = 'Las observaciones no pueden superar 500 caracteres.';
        }
        if ($errors) {
            return [$data, $errors];
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $previous = $this->attendance->find($id);
            $this->attendance->save($pdo, $id, $data, $userId);
            $this->history->record($pdo, [
                'id_tutoria' => $id,
                'id_usuario' => $userId,
                'tipo_evento' => 'asistencia',
                'estado_anterior' => $tutoria['estado'],
                'estado_nuevo' => $tutoria['estado'],
                'motivo' => $data['observaciones'],
                'datos_anteriores' => $previous,
                'datos_nuevos' => $data,
            ]);
            $pdo->commit();
            return [$data, []];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());
            return [$data, ['No fue posible registrar la asistencia.']];
        }
    }

    public function history(int $id, string $role, int $userId): array
    {
        return $this->history->forViewer($id, $role, $userId);
    }

    private function normalize(array $input, ?int $studentId): array
    {
        $offering = explode(':', trim((string) ($input['id_oferta'] ?? '')), 2);
        return [
            'id_estudiante' => $studentId,
            'actor_id' => 0,
            'id_tutor' => trim((string) ($input['id_tutor'] ?? ($offering[0] ?? ''))),
            'id_materia' => trim((string) ($input['id_materia'] ?? ($offering[1] ?? ''))),
            'id_oferta' => trim((string) ($input['id_oferta'] ?? '')),
            'slot_key' => trim((string) ($input['slot_key'] ?? '')),
            'id_disponibilidad' => null,
            'fecha' => trim((string) ($input['fecha'] ?? '')),
            'hora_inicio' => trim((string) ($input['hora_inicio'] ?? '')),
            'hora_fin' => trim((string) ($input['hora_fin'] ?? '')),
            'modalidad' => trim((string) ($input['modalidad'] ?? 'presencial')),
            'lugar_o_enlace' => trim((string) ($input['lugar_o_enlace'] ?? '')),
            'observaciones' => trim((string) ($input['observaciones'] ?? '')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        $tutorId = filter_var($data['id_tutor'], FILTER_VALIDATE_INT);
        $subjectId = filter_var($data['id_materia'], FILTER_VALIDATE_INT);

        if (!$data['id_estudiante']) {
            $errors[] = 'El usuario no tiene un perfil de estudiante.';
        }
        if ($tutorId === false || $tutorId < 1 || $subjectId === false || $subjectId < 1) {
            $errors[] = 'Seleccione una oferta de tutor y materia valida.';
        }
        $date = DateTime::createFromFormat('!Y-m-d', $data['fecha']);
        if (!$date || $date->format('Y-m-d') !== $data['fecha']) {
            $errors[] = 'Ingrese una fecha valida.';
        } elseif ($data['fecha'] < date('Y-m-d')) {
            $errors[] = 'La fecha no puede estar en el pasado.';
        } elseif (!$this->isFutureStart($data['fecha'], $data['hora_inicio'])) {
            $errors[] = 'La hora de inicio debe ser futura.';
        }
        $startError = validation_time($data['hora_inicio'], 'hora inicial');
        $endError = validation_time($data['hora_fin'], 'hora final');
        if ($startError !== null || $endError !== null) {
            $errors[] = 'Ingrese horarios validos.';
        } elseif ($data['hora_fin'] <= $data['hora_inicio']) {
            $errors[] = 'La hora final debe ser posterior a la inicial.';
        }
        if (!in_array($data['modalidad'], ['presencial', 'virtual'], true)) {
            $errors[] = 'Seleccione una modalidad valida.';
        }
        if (strlen($data['lugar_o_enlace']) > 200) {
            $errors[] = 'El lugar o enlace no puede superar 200 caracteres.';
        }
        if ($data['modalidad'] === 'virtual' && $data['lugar_o_enlace'] === '') {
            $errors[] = 'Ingrese el enlace para una tutoria virtual.';
        }

        return $errors;
    }

    /**
     * Se evalua antes de validar y de nuevo dentro de la transaccion, ya que
     * la espera por los locks puede cruzar la hora de inicio solicitada.
     */
    private function isFutureStart(string $date, string $start): bool
    {
        $startsAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . substr($start, 0, 5));

        return $startsAt !== false && $startsAt > new DateTimeImmutable();
    }

    private function normalizeSchedule(array $input, $currentTutor): array
    {
        return [
            'id_tutor' => trim((string) ($input['id_tutor'] ?? $currentTutor)),
            'fecha' => trim((string) ($input['fecha'] ?? '')),
            'hora_inicio' => trim((string) ($input['hora_inicio'] ?? '')),
            'hora_fin' => trim((string) ($input['hora_fin'] ?? '')),
            'modalidad' => trim((string) ($input['modalidad'] ?? 'presencial')),
            'lugar_o_enlace' => trim((string) ($input['lugar_o_enlace'] ?? '')),
            'motivo' => trim((string) ($input['motivo'] ?? '')),
        ];
    }

    private function validateSchedule(array $data, array $current): array
    {
        $errors = [];
        $tutorId = filter_var($data['id_tutor'], FILTER_VALIDATE_INT);
        $date = DateTime::createFromFormat('!Y-m-d', $data['fecha']);
        if ($tutorId === false || $tutorId < 1) {
            $errors[] = 'Seleccione un tutor valido.';
        }
        if (!$date || $date->format('Y-m-d') !== $data['fecha']) {
            $errors[] = 'Ingrese una fecha valida.';
        } elseif (!$this->isFutureStart($data['fecha'], $data['hora_inicio'])) {
            $errors[] = 'La nueva fecha y hora deben ser futuras.';
        }
        $startError = validation_time($data['hora_inicio'], 'hora inicial');
        $endError = validation_time($data['hora_fin'], 'hora final');
        if ($startError !== null || $endError !== null || $data['hora_fin'] <= $data['hora_inicio']) {
            $errors[] = 'Ingrese un horario valido.';
        }
        if (!in_array($data['modalidad'], ['presencial', 'virtual'], true)) {
            $errors[] = 'Seleccione una modalidad valida.';
        }
        if ($data['modalidad'] === 'virtual' && $data['lugar_o_enlace'] === '') {
            $errors[] = 'Ingrese el enlace para una tutoria virtual.';
        }
        if (strlen($data['lugar_o_enlace']) > 200 || strlen($data['motivo']) > 500) {
            $errors[] = 'Los datos de reprogramacion superan la longitud permitida.';
        }
        if ($data['motivo'] === '') {
            $errors[] = 'Indique el motivo de la reprogramacion.';
        }

        return $errors;
    }

    private function snapshot(array $data): array
    {
        return array_intersect_key($data, array_flip(['id_tutoria', 'id_estudiante', 'id_tutor', 'id_materia', 'id_disponibilidad', 'fecha', 'hora_inicio', 'hora_fin', 'modalidad', 'estado', 'lugar_o_enlace', 'estado_asistencia', 'minutos_retraso']));
    }
}
