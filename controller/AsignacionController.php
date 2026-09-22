<?php

declare(strict_types=1);

/**
 * Motor de asignacion automatica: el estudiante declara materias y el sistema
 * lo coloca en un grupo compatible (existente o nuevo) respetando cupo y conflictos.
 * Reglas deterministas: cupo libre + sin solape de horario del estudiante,
 * del tutor y del aula.
 *
 * Al crear un grupo nuevo, las preferencias del tutor por materia
 * (TutorMateriaConfig: turnos, modalidad, sabados, cupo recomendado) se cruzan
 * con su disponibilidad_tutor real. Un tutor sin preferencia configurada para
 * la materia conserva el comportamiento anterior sin restriccion adicional.
 */
final class AsignacionController
{
    private Grupo $grupos;
    private Inscripcion $inscripciones;
    private Demanda $demanda;
    private HistorialGrupo $historial;

    public function __construct()
    {
        $this->grupos = new Grupo();
        $this->inscripciones = new Inscripcion();
        $this->demanda = new Demanda();
        $this->historial = new HistorialGrupo();
    }

    /** Materias con oferta (tutor habilitado con disponibilidad) que el estudiante aun no solicito. */
    public function matterOptions(int $studentId, int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT DISTINCT m.id_materia, m.nombre_materia, c.nombre_carrera
             FROM materias m
             INNER JOIN tutor_materia tm ON tm.id_materia = m.id_materia
             INNER JOIN tutores t ON t.id_tutor = tm.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND u.estado = 'activo'
             INNER JOIN disponibilidad_tutor d ON d.id_tutor = t.id_tutor
             LEFT JOIN carreras c ON c.id_carrera = m.id_carrera
             WHERE m.id_materia NOT IN (
                 SELECT g.id_materia FROM inscripciones i
                 INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
                 WHERE i.id_estudiante = :id_estudiante AND g.id_periodo = :id_periodo AND i.estado <> 'cancelada'
             )
             ORDER BY m.nombre_materia"
        );
        $statement->execute(['id_estudiante' => $studentId, 'id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    /**
     * Procesa la solicitud de apoyo del estudiante para varias materias.
     * Devuelve un resumen por materia: [id_materia, nombre, resultado, detalle].
     */
    public function solicitarApoyo(int $studentId, array $matterIds, array $periodo): array
    {
        $periodoId = (int) $periodo['id_periodo'];
        $cupoMin = (int) $periodo['cupo_min_grupo'];
        $cupoMax = (int) $periodo['cupo_max_default'];
        $results = [];

        $alreadyRequested = $this->inscripciones->studentMatterIds($studentId, $periodoId);

        foreach ($matterIds as $matterId) {
            $matterId = (int) $matterId;
            if ($matterId < 1) {
                continue;
            }
            $name = $this->matterName($matterId);
            if (in_array($matterId, $alreadyRequested, true)) {
                $results[] = ['id_materia' => $matterId, 'nombre' => $name, 'resultado' => 'ya_solicitada', 'detalle' => 'Ya tienes una solicitud para esta materia.'];
                continue;
            }

            $outcome = $this->assignMatter($studentId, $matterId, $periodoId, $cupoMin, $cupoMax);
            $outcome['id_materia'] = $matterId;
            $outcome['nombre'] = $name;
            $results[] = $outcome;
            $alreadyRequested[] = $matterId;
        }

        return $results;
    }

    private function assignMatter(int $studentId, int $matterId, int $periodoId, int $cupoMin, int $cupoMax): array
    {
        $busy = $this->inscripciones->studentBusySlots($studentId, $periodoId);

        // 1) Intentar grupos existentes con cupo.
        foreach ($this->grupos->candidatesForMatter($periodoId, $matterId) as $candidate) {
            if ($this->overlapsBusy($busy, $candidate['dia_semana'], $candidate['hora_inicio'], $candidate['hora_fin'])) {
                continue;
            }
            $group = $this->enrollInExisting((int) $candidate['id_grupo'], $studentId, $cupoMin);
            if ($group !== null) {
                $this->emitNotifications($group, $studentId, $cupoMin);
                return ['resultado' => 'asignado', 'detalle' => $this->describe($group)];
            }
        }

        // 2) Crear un grupo nuevo desde la disponibilidad de un tutor.
        $group = $this->createGroupForMatter($studentId, $matterId, $periodoId, $cupoMin, $cupoMax, $busy);
        if ($group !== null) {
            $this->emitNotifications($group, $studentId, $cupoMin);
            return ['resultado' => 'grupo_creado', 'detalle' => $this->describe($group)];
        }

        // 3) Demanda insatisfecha.
        $this->demanda->record($periodoId, $matterId, $studentId);

        return ['resultado' => 'lista_espera', 'detalle' => 'No hay tutor u horario disponible ahora. Quedaste registrado en lista de espera.'];
    }

    /** Inscribe al estudiante en un grupo existente con bloqueo de cupo. Devuelve datos del grupo o null. */
    private function enrollInExisting(int $grupoId, int $studentId, int $cupoMin): ?array
    {
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $grupo = $this->grupos->lockForEnroll($grupoId);
            if ($grupo === null
                || !in_array($grupo['estado'], ['formacion', 'confirmado'], true)
                || (int) $grupo['cupo_ocupado'] >= (int) $grupo['cupo_max']
                || $this->inscripciones->existsActive($grupoId, $studentId)) {
                $connection->rollBack();
                return null;
            }
            $this->inscripciones->create($grupoId, $studentId, 'inscrito', 'auto');
            $this->grupos->registerEnrollment($grupoId, $cupoMin);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log($exception->getMessage());
            return null;
        }

        return $this->groupDetail($grupoId);
    }

    /** Busca un bloque tutor+aula libre y crea el grupo, inscribiendo al estudiante. */
    private function createGroupForMatter(int $studentId, int $matterId, int $periodoId, int $cupoMin, int $cupoMax, array $busy): ?array
    {
        $rawSlots = $this->grupos->availabilityForMatter($periodoId, $matterId);
        $preferences = (new TutorMateriaConfig())->preferencesForMatter($matterId);
        $candidates = (new TutorMateriaConfig())->expandSlots($rawSlots, $preferences);

        foreach ($candidates as $slot) {
            $dia = $slot['dia_semana'];
            $hi = $slot['hora_inicio'];
            $hf = $slot['hora_fin'];
            $tutorId = (int) $slot['id_tutor'];

            if ($this->overlapsBusy($busy, $dia, $hi, $hf)) {
                continue;
            }
            if ($this->grupos->tutorHasConflict($tutorId, $dia, $hi, $hf, $periodoId)) {
                continue;
            }
            $aula = $this->grupos->findFreeAula($dia, $hi, $hf, $periodoId, $slot['modalidad_preferida']);
            if ($aula === null) {
                continue;
            }

            $periodo = (new Periodo())->findById($periodoId);
            if ($periodo === null) {
                return null;
            }
            $modalidad = $aula['tipo'] === 'virtual' ? 'virtual' : 'presencial';
            $cupoGrupo = min($cupoMax, (int) $aula['capacidad']);
            if ($slot['cupo_recomendado'] !== null) {
                $cupoGrupo = min($cupoGrupo, $slot['cupo_recomendado']);
            }

            $connection = Database::connection();
            $connection->beginTransaction();
            try {
                $grupoId = $this->grupos->create([
                    'id_periodo' => $periodoId,
                    'id_materia' => $matterId,
                    'id_tutor' => $tutorId,
                    'id_aula' => (int) $aula['id_aula'],
                    'modalidad' => $modalidad,
                    'dia_semana' => $dia,
                    'hora_inicio' => $hi,
                    'hora_fin' => $hf,
                    'cupo_max' => $cupoGrupo,
                    'estado' => 'formacion',
                ]);
                $this->grupos->generateSessions($grupoId, $dia, $periodo['fecha_inicio'], $periodo['fecha_fin']);
                $this->inscripciones->create($grupoId, $studentId, 'inscrito', 'auto');
                $this->grupos->registerEnrollment($grupoId, $cupoMin);
                $this->historial->log($connection, $grupoId, 'creado', null, 'formacion', null, 'Grupo generado automaticamente por el sistema.');
                $connection->commit();
            } catch (Throwable $exception) {
                $connection->rollBack();
                error_log($exception->getMessage());
                continue;
            }

            return $this->groupDetail($grupoId);
        }

        return null;
    }

    /** Emite notificacion de asignacion y, si el grupo acaba de confirmarse, avisa a todo el grupo. */
    private function emitNotifications(array $group, int $studentId, int $cupoMin): void
    {
        try {
            $pdo = Database::connection();
            $notif = new Notificacion();
            $studentUserId = $this->studentUserId($studentId);
            if ($studentUserId !== null) {
                $notif->notifyAssignment($pdo, (int) $group['id_grupo'], $studentUserId, (string) $group['nombre_materia'], $this->describe($group));
            }
            // El grupo acaba de cruzar el cupo minimo con esta inscripcion.
            if ($group['estado'] === 'confirmado' && (int) $group['cupo_ocupado'] === $cupoMin) {
                $notif->notifyGroupConfirmed($pdo, (int) $group['id_grupo'], (string) $group['nombre_materia']);
                $this->historial->log($pdo, (int) $group['id_grupo'], 'confirmado', 'formacion', 'confirmado', null, 'Alcanzo el cupo minimo.');
            }
        } catch (Throwable $exception) {
            error_log('Notificacion asignacion: ' . $exception->getMessage());
        }
    }

    private function studentUserId(int $studentId): ?int
    {
        $statement = Database::connection()->prepare('SELECT id_usuario FROM estudiantes WHERE id_estudiante = :id LIMIT 1');
        $statement->execute(['id' => $studentId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function overlapsBusy(array $busy, string $dia, string $horaInicio, string $horaFin): bool
    {
        foreach ($busy as $slot) {
            if ($slot['dia_semana'] === $dia && $slot['hora_inicio'] < $horaFin && $slot['hora_fin'] > $horaInicio) {
                return true;
            }
        }

        return false;
    }

    private function groupDetail(int $grupoId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT g.id_grupo, g.cupo_ocupado, g.estado, g.dia_semana, g.hora_inicio, g.hora_fin, g.modalidad,
                    m.nombre_materia, a.nombre AS aula, CONCAT(u.nombre, ' ', u.apellido) AS tutor
             FROM grupos_tutoria g
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN aulas a ON a.id_aula = g.id_aula
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE g.id_grupo = :id LIMIT 1"
        );
        $statement->execute(['id' => $grupoId]);
        $detail = $statement->fetch();

        return $detail ?: null;
    }

    private function describe(array $group): string
    {
        return sprintf(
            'Tutor %s · %s %s-%s · %s (%s)',
            $group['tutor'],
            $group['dia_semana'],
            substr((string) $group['hora_inicio'], 0, 5),
            substr((string) $group['hora_fin'], 0, 5),
            $group['aula'],
            ucfirst((string) $group['modalidad'])
        );
    }

    private function matterName(int $matterId): string
    {
        $statement = Database::connection()->prepare('SELECT nombre_materia FROM materias WHERE id_materia = :id LIMIT 1');
        $statement->execute(['id' => $matterId]);

        return (string) ($statement->fetchColumn() ?: ('Materia #' . $matterId));
    }
}
