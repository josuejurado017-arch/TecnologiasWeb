<?php

declare(strict_types=1);

final class Demanda
{
    /** Registra demanda insatisfecha (idempotente por la clave unica). */
    public function record(int $periodoId, int $matterId, int $studentId): void
    {
        $statement = Database::connection()->prepare(
            "INSERT INTO demanda_tutoria (id_periodo, id_materia, id_estudiante, estado)
             VALUES (:id_periodo, :id_materia, :id_estudiante, 'pendiente')
             ON DUPLICATE KEY UPDATE estado = 'pendiente'"
        );
        $statement->execute([
            'id_periodo' => $periodoId, 'id_materia' => $matterId, 'id_estudiante' => $studentId,
        ]);
    }

    /** Resumen de demanda insatisfecha por materia (para el administrador). */
    public function summaryByPeriodo(int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT m.nombre_materia, COUNT(*) AS solicitudes
             FROM demanda_tutoria d
             INNER JOIN materias m ON m.id_materia = d.id_materia
             WHERE d.id_periodo = :id_periodo AND d.estado = 'pendiente'
             GROUP BY m.id_materia, m.nombre_materia
             ORDER BY solicitudes DESC, m.nombre_materia"
        );
        $statement->execute(['id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }
}
