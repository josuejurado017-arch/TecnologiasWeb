<?php

declare(strict_types=1);

final class Asistencia
{
    /** Asistencia ya registrada en una sesion, indexada por id_inscripcion. */
    public function forSession(int $sesionId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_inscripcion, estado, minutos_retraso, observaciones FROM asistencias_sesion WHERE id_sesion = :id_sesion'
        );
        $statement->execute(['id_sesion' => $sesionId]);

        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $rows[(int) $row['id_inscripcion']] = $row;
        }

        return $rows;
    }

    /** Inserta o actualiza la asistencia de una inscripcion en una sesion. */
    public function upsert(int $sesionId, int $inscripcionId, string $estado, ?int $minutos, ?string $observaciones, ?int $userId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO asistencias_sesion (id_sesion, id_inscripcion, estado, minutos_retraso, observaciones, id_usuario_registro)
             VALUES (:id_sesion, :id_inscripcion, :estado, :minutos, :observaciones, :id_usuario)
             ON DUPLICATE KEY UPDATE estado = VALUES(estado), minutos_retraso = VALUES(minutos_retraso),
                observaciones = VALUES(observaciones), id_usuario_registro = VALUES(id_usuario_registro), fecha_registro = NOW()'
        );
        $statement->execute([
            'id_sesion' => $sesionId,
            'id_inscripcion' => $inscripcionId,
            'estado' => $estado,
            'minutos' => $minutos,
            'observaciones' => $observaciones,
            'id_usuario' => $userId,
        ]);
    }

    /** Resumen de asistencia de un estudiante en una campana (para reportes). */
    public function summaryForStudent(int $studentId, int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT a.estado, COUNT(*) AS total
             FROM asistencias_sesion a
             INNER JOIN inscripciones i ON i.id_inscripcion = a.id_inscripcion
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             WHERE i.id_estudiante = :id_estudiante AND g.id_periodo = :id_periodo
             GROUP BY a.estado"
        );
        $statement->execute(['id_estudiante' => $studentId, 'id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }
}
