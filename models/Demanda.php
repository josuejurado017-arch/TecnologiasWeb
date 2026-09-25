<?php

declare(strict_types=1);

/**
 * Demanda insatisfecha de tutorias (db/011 + db/023).
 * Cada fila es "un estudiante quiere apoyo en una materia en un periodo y aun
 * no tiene grupo". El motivo dice por que (sin tutor / sin horario compatible /
 * grupo cancelado) y la fila pasa a 'atendida' cuando el motor lo inscribe.
 */
final class Demanda
{
    public const MOTIVO_SIN_TUTOR = 'sin_tutor';
    public const MOTIVO_SIN_HORARIO = 'sin_horario';
    public const MOTIVO_GRUPO_CANCELADO = 'grupo_cancelado';
    /** Hay tutor y turno compatibles, pero aun no se reune el minimo para formar grupo (db/035). */
    public const MOTIVO_ESPERANDO = 'esperando_companeros';

    public const MOTIVOS = [
        self::MOTIVO_SIN_TUTOR => 'Sin tutor habilitado',
        self::MOTIVO_SIN_HORARIO => 'Sin horario compatible',
        self::MOTIVO_GRUPO_CANCELADO => 'Grupo cancelado',
        self::MOTIVO_ESPERANDO => 'Esperando compañeros',
    ];

    /**
     * Registra demanda insatisfecha (idempotente por la clave unica).
     * Si ya existia (atendida o cancelada) vuelve a 'pendiente' con fecha nueva;
     * si seguia pendiente, conserva la fecha original y solo actualiza el motivo.
     */
    public function record(int $periodoId, int $matterId, int $studentId, string $motivo = self::MOTIVO_SIN_HORARIO): void
    {
        if (!isset(self::MOTIVOS[$motivo])) {
            $motivo = self::MOTIVO_SIN_HORARIO;
        }
        $statement = Database::connection()->prepare(
            "INSERT INTO demanda_tutoria (id_periodo, id_materia, id_estudiante, estado, motivo)
             VALUES (:id_periodo, :id_materia, :id_estudiante, 'pendiente', :motivo)
             ON DUPLICATE KEY UPDATE
                fecha_solicitud = IF(estado = 'pendiente', fecha_solicitud, NOW()),
                fecha_atencion = NULL,
                motivo = VALUES(motivo),
                estado = 'pendiente'"
        );
        $statement->execute([
            'id_periodo' => $periodoId, 'id_materia' => $matterId, 'id_estudiante' => $studentId, 'motivo' => $motivo,
        ]);
    }

    /** Actualiza solo el motivo de una demanda pendiente (p. ej. aparecio un tutor pero sigue sin horario). */
    public function updateMotivo(int $periodoId, int $matterId, int $studentId, string $motivo): void
    {
        if (!isset(self::MOTIVOS[$motivo])) {
            return;
        }
        Database::connection()->prepare(
            "UPDATE demanda_tutoria SET motivo = :motivo
             WHERE id_periodo = :id_periodo AND id_materia = :id_materia AND id_estudiante = :id_estudiante AND estado = 'pendiente'"
        )->execute(['motivo' => $motivo, 'id_periodo' => $periodoId, 'id_materia' => $matterId, 'id_estudiante' => $studentId]);
    }

    /**
     * Demanda pendiente del estudiante en el periodo, indexada por
     * id_materia => ['fecha' => fecha_solicitud, 'motivo' => motivo].
     * Alimenta el estado "En espera" de la pantalla de solicitud y evita re-solicitudes.
     */
    public function pendingForStudent(int $studentId, int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT id_materia, fecha_solicitud, motivo FROM demanda_tutoria
             WHERE id_estudiante = :id_estudiante AND id_periodo = :id_periodo AND estado = 'pendiente'"
        );
        $statement->execute(['id_estudiante' => $studentId, 'id_periodo' => $periodoId]);

        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $result[(int) $row['id_materia']] = ['fecha' => $row['fecha_solicitud'], 'motivo' => $row['motivo']];
        }

        return $result;
    }

    /** Cola FIFO de estudiantes en espera para una materia (para el reproceso automatico). */
    public function pendingForMatter(int $periodoId, int $matterId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT id_demanda, id_estudiante, motivo, fecha_solicitud FROM demanda_tutoria
             WHERE id_periodo = :id_periodo AND id_materia = :id_materia AND estado = 'pendiente'
             ORDER BY fecha_solicitud ASC, id_demanda ASC"
        );
        $statement->execute(['id_periodo' => $periodoId, 'id_materia' => $matterId]);

        return $statement->fetchAll();
    }

    /** Materias con demanda pendiente en el periodo (para reprocesar por tutor o en bloque). */
    public function mattersWithPending(int $periodoId, ?array $onlyMatterIds = null): array
    {
        $sql = "SELECT DISTINCT id_materia FROM demanda_tutoria WHERE id_periodo = :id_periodo AND estado = 'pendiente'";
        $params = ['id_periodo' => $periodoId];
        if ($onlyMatterIds !== null) {
            $onlyMatterIds = array_values(array_unique(array_map('intval', $onlyMatterIds)));
            if (!$onlyMatterIds) {
                return [];
            }
            $placeholders = [];
            foreach ($onlyMatterIds as $i => $id) {
                $placeholders[] = ':m' . $i;
                $params['m' . $i] = $id;
            }
            $sql .= ' AND id_materia IN (' . implode(',', $placeholders) . ')';
        }
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return array_map('intval', array_column($statement->fetchAll(), 'id_materia'));
    }

    /** Interesados en espera por materia (id_materia => total), para mostrar demanda visible al estudiante. */
    public function pendingCountByMatter(int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT id_materia, COUNT(*) AS total FROM demanda_tutoria
             WHERE id_periodo = :id_periodo AND estado = 'pendiente'
             GROUP BY id_materia"
        );
        $statement->execute(['id_periodo' => $periodoId]);

        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $result[(int) $row['id_materia']] = (int) $row['total'];
        }

        return $result;
    }

    /** Marca la demanda como atendida cuando el estudiante queda inscrito en un grupo. Devuelve true si habia una pendiente. */
    public function markAttended(int $periodoId, int $matterId, int $studentId): bool
    {
        $statement = Database::connection()->prepare(
            "UPDATE demanda_tutoria SET estado = 'atendida', fecha_atencion = NOW()
             WHERE id_periodo = :id_periodo AND id_materia = :id_materia AND id_estudiante = :id_estudiante AND estado = 'pendiente'"
        );
        $statement->execute(['id_periodo' => $periodoId, 'id_materia' => $matterId, 'id_estudiante' => $studentId]);

        return $statement->rowCount() > 0;
    }

    /** El estudiante retira su interes: deja de contar como demanda pendiente. */
    public function cancel(int $periodoId, int $matterId, int $studentId): bool
    {
        $statement = Database::connection()->prepare(
            "UPDATE demanda_tutoria SET estado = 'cancelada'
             WHERE id_periodo = :id_periodo AND id_materia = :id_materia AND id_estudiante = :id_estudiante AND estado = 'pendiente'"
        );
        $statement->execute(['id_periodo' => $periodoId, 'id_materia' => $matterId, 'id_estudiante' => $studentId]);

        return $statement->rowCount() > 0;
    }

    /**
     * Resumen de demanda insatisfecha por materia (para el administrador), con
     * desglose por motivo: dice donde falta tutor y donde falta horario.
     */
    public function summaryByPeriodo(int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT m.id_materia, m.nombre_materia, c.nombre_carrera,
                    COUNT(*) AS solicitudes,
                    SUM(d.motivo = 'sin_tutor') AS sin_tutor,
                    SUM(d.motivo = 'sin_horario') AS sin_horario,
                    SUM(d.motivo = 'grupo_cancelado') AS grupo_cancelado,
                    SUM(d.motivo = 'esperando_companeros') AS esperando,
                    MIN(d.fecha_solicitud) AS espera_desde
             FROM demanda_tutoria d
             INNER JOIN materias m ON m.id_materia = d.id_materia
             LEFT JOIN carreras c ON c.id_carrera = m.id_carrera
             WHERE d.id_periodo = :id_periodo AND d.estado = 'pendiente'
             GROUP BY m.id_materia, m.nombre_materia, c.nombre_carrera
             ORDER BY sin_tutor DESC, solicitudes DESC, m.nombre_materia"
        );
        $statement->execute(['id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    /** Totales del periodo: registradas, sin atender (pendientes + vencidas al cierre, por motivo), atendidas, canceladas y horas medias de espera. */
    public function conversion(int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) AS registradas,
                    SUM(estado IN ('pendiente','vencida')) AS pendientes,
                    SUM(estado = 'vencida') AS vencidas,
                    SUM(estado IN ('pendiente','vencida') AND motivo = 'sin_tutor') AS pend_sin_tutor,
                    SUM(estado IN ('pendiente','vencida') AND motivo = 'sin_horario') AS pend_sin_horario,
                    SUM(estado IN ('pendiente','vencida') AND motivo = 'grupo_cancelado') AS pend_grupo_cancelado,
                    SUM(estado IN ('pendiente','vencida') AND motivo = 'esperando_companeros') AS pend_esperando,
                    SUM(estado = 'atendida') AS atendidas,
                    SUM(estado = 'cancelada') AS canceladas,
                    ROUND(AVG(CASE WHEN estado = 'atendida' AND fecha_atencion IS NOT NULL
                                   THEN TIMESTAMPDIFF(HOUR, fecha_solicitud, fecha_atencion) END), 1) AS horas_espera_promedio,
                    COUNT(DISTINCT CASE WHEN estado IN ('pendiente','vencida') THEN id_materia END) AS materias_pendientes,
                    COUNT(DISTINCT CASE WHEN estado IN ('pendiente','vencida') AND motivo = 'sin_tutor' THEN id_materia END) AS materias_sin_tutor
             FROM demanda_tutoria
             WHERE id_periodo = :id_periodo"
        );
        $statement->execute(['id_periodo' => $periodoId]);
        $row = $statement->fetch() ?: [];

        return array_map(static fn ($v) => $v === null ? null : (is_numeric($v) ? $v + 0 : $v), $row);
    }
}
