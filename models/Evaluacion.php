<?php

declare(strict_types=1);

final class Evaluacion
{
    /**
     * Inscripciones evaluables del estudiante: grupo con al menos una sesion realizada
     * y aun sin evaluacion registrada.
     */
    public function pendingForStudent(int $studentId, int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT i.id_inscripcion, g.id_grupo, m.nombre_materia,
                    CONCAT(u.nombre, ' ', u.apellido) AS tutor,
                    g.dia_semana, g.hora_inicio, g.hora_fin
             FROM inscripciones i
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE i.id_estudiante = :id_estudiante AND g.id_periodo = :id_periodo
               AND i.estado = 'inscrito'
               AND EXISTS (SELECT 1 FROM sesiones_tutoria s WHERE s.id_grupo = g.id_grupo AND s.estado = 'realizada')
               AND NOT EXISTS (SELECT 1 FROM evaluaciones_grupo e WHERE e.id_inscripcion = i.id_inscripcion)
             ORDER BY m.nombre_materia"
        );
        $statement->execute(['id_estudiante' => $studentId, 'id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    /** Evaluaciones ya emitidas por el estudiante en la campana. */
    public function doneForStudent(int $studentId, int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT m.nombre_materia, CONCAT(u.nombre, ' ', u.apellido) AS tutor,
                    e.calificacion_general, e.puntualidad, e.dominio, e.claridad, e.utilidad, e.comentario
             FROM evaluaciones_grupo e
             INNER JOIN inscripciones i ON i.id_inscripcion = e.id_inscripcion
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE i.id_estudiante = :id_estudiante AND g.id_periodo = :id_periodo
             ORDER BY e.fecha_evaluacion DESC"
        );
        $statement->execute(['id_estudiante' => $studentId, 'id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    /** Verifica que la inscripcion pertenezca al estudiante y sea evaluable. */
    public function findEvaluableInscription(int $inscripcionId, int $studentId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT i.id_inscripcion, m.nombre_materia, CONCAT(u.nombre, ' ', u.apellido) AS tutor
             FROM inscripciones i
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             INNER JOIN periodos p ON p.id_periodo = g.id_periodo
             WHERE i.id_inscripcion = :id_inscripcion AND i.id_estudiante = :id_estudiante
               AND i.estado = 'inscrito'
               AND (p.estado = 'activa' OR (p.estado = 'cerrada' AND p.evaluaciones_hasta >= CURRENT_DATE))
               AND EXISTS (SELECT 1 FROM sesiones_tutoria s WHERE s.id_grupo = g.id_grupo AND s.estado = 'realizada')
               AND NOT EXISTS (SELECT 1 FROM evaluaciones_grupo e WHERE e.id_inscripcion = i.id_inscripcion)
             LIMIT 1"
        );
        $statement->execute(['id_inscripcion' => $inscripcionId, 'id_estudiante' => $studentId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function create(int $inscripcionId, array $scores, ?string $comentario): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO evaluaciones_grupo (id_inscripcion, calificacion_general, puntualidad, dominio, claridad, utilidad, comentario)
             VALUES (:id_inscripcion, :general, :puntualidad, :dominio, :claridad, :utilidad, :comentario)'
        );
        $statement->execute([
            'id_inscripcion' => $inscripcionId,
            'general' => $scores['general'],
            'puntualidad' => $scores['puntualidad'],
            'dominio' => $scores['dominio'],
            'claridad' => $scores['claridad'],
            'utilidad' => $scores['utilidad'],
            'comentario' => $comentario,
        ]);
    }
}
