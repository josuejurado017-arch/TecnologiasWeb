<?php

declare(strict_types=1);

/** Registro de asistencia por sesion, restringido al tutor propietario del grupo. */
final class AsistenciaSesionController
{
    private Sesion $sesiones;
    private Asistencia $asistencias;
    private Inscripcion $inscripciones;

    private const ESTADOS = ['asistio', 'no_asistio', 'parcial', 'retraso'];

    public function __construct()
    {
        $this->sesiones = new Sesion();
        $this->asistencias = new Asistencia();
        $this->inscripciones = new Inscripcion();
    }

    /** Datos para el formulario, verificando que la sesion pertenezca al tutor. */
    public function form(int $sesionId, int $tutorUserId): ?array
    {
        $sesion = $this->sesiones->detail($sesionId);
        if ($sesion === null || (int) $sesion['tutor_usuario'] !== $tutorUserId) {
            return null;
        }

        return [
            'sesion' => $sesion,
            'inscritos' => $this->inscripciones->forGroup((int) $sesion['id_grupo']),
            'registradas' => $this->asistencias->forSession($sesionId),
        ];
    }

    /** Guarda la asistencia de todos los inscritos y marca la sesion como realizada. */
    public function save(int $sesionId, int $tutorUserId, array $input): ?string
    {
        $sesion = $this->sesiones->detail($sesionId);
        if ($sesion === null || (int) $sesion['tutor_usuario'] !== $tutorUserId) {
            return 'No tienes permiso para registrar esta sesion.';
        }

        $estados = isset($input['estado']) && is_array($input['estado']) ? $input['estado'] : [];
        $minutos = isset($input['minutos']) && is_array($input['minutos']) ? $input['minutos'] : [];
        $observaciones = isset($input['observaciones']) && is_array($input['observaciones']) ? $input['observaciones'] : [];

        $inscritos = $this->inscripciones->forGroup((int) $sesion['id_grupo']);
        $validIds = array_map(static fn ($i) => (int) $i['id_inscripcion'], $inscritos);

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            foreach ($validIds as $inscripcionId) {
                $estado = (string) ($estados[$inscripcionId] ?? '');
                if (!in_array($estado, self::ESTADOS, true)) {
                    continue; // sin marcar: se omite
                }
                $min = null;
                if ($estado === 'retraso') {
                    $parsed = filter_var($minutos[$inscripcionId] ?? null, FILTER_VALIDATE_INT);
                    $min = ($parsed !== false && $parsed >= 0 && $parsed <= 300) ? $parsed : null;
                }
                $obs = trim((string) ($observaciones[$inscripcionId] ?? ''));
                if ($obs !== '') {
                    $obs = mb_substr($obs, 0, 500);
                    if (preg_match('/[\x00-\x1F\x7F]/', $obs)) {
                        $obs = '';
                    }
                }
                $this->asistencias->upsert($sesionId, $inscripcionId, $estado, $min, $obs === '' ? null : $obs, $tutorUserId);
            }
            $this->sesiones->markRealizada($sesionId);
            $connection->commit();
            try {
                (new Notificacion())->notifyEvaluationPending(Database::connection(), (int) $sesion['id_grupo'], (string) $sesion['nombre_materia']);
            } catch (Throwable $notifError) {
                error_log('Notificacion evaluacion pendiente: ' . $notifError->getMessage());
            }
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log($exception->getMessage());
            return 'No se pudo registrar la asistencia.';
        }

        return null;
    }
}
