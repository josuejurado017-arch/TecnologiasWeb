<?php

declare(strict_types=1);

/** Metricas por campana para administrador, tutor y estudiante (modelo institucional). */
final class ReporteCampania
{
    // ---------------------- Administrador ----------------------

    public function totals(int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                (SELECT COUNT(*) FROM grupos_tutoria WHERE id_periodo = :p1) AS grupos,
                (SELECT COUNT(*) FROM grupos_tutoria WHERE id_periodo = :p2 AND estado = 'confirmado') AS grupos_confirmados,
                (SELECT COUNT(*) FROM grupos_tutoria WHERE id_periodo = :p3 AND estado = 'cancelado') AS grupos_cancelados,
                (SELECT COUNT(*) FROM inscripciones i JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo WHERE g.id_periodo = :p4 AND i.estado = 'inscrito') AS inscritos,
                (SELECT COUNT(DISTINCT i.id_estudiante) FROM inscripciones i JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo WHERE g.id_periodo = :p5 AND i.estado = 'inscrito') AS estudiantes,
                (SELECT COUNT(DISTINCT g.id_tutor) FROM grupos_tutoria g WHERE g.id_periodo = :p6) AS tutores"
        );
        $statement->execute(['p1' => $periodoId, 'p2' => $periodoId, 'p3' => $periodoId, 'p4' => $periodoId, 'p5' => $periodoId, 'p6' => $periodoId]);

        return $statement->fetch() ?: [];
    }

    public function topTutores(int $periodoId, int $limit = 5): array
    {
        $statement = Database::connection()->prepare(
            "SELECT CONCAT(u.nombre, ' ', u.apellido) AS tutor,
                    COUNT(DISTINCT g.id_grupo) AS grupos,
                    COUNT(DISTINCT i.id_estudiante) AS estudiantes
             FROM grupos_tutoria g
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             LEFT JOIN inscripciones i ON i.id_grupo = g.id_grupo AND i.estado = 'inscrito'
             WHERE g.id_periodo = :id_periodo
             GROUP BY g.id_tutor, u.nombre, u.apellido
             ORDER BY estudiantes DESC, grupos DESC
             LIMIT " . (int) $limit
        );
        $statement->execute(['id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    public function topMaterias(int $periodoId, int $limit = 5): array
    {
        $statement = Database::connection()->prepare(
            "SELECT m.nombre_materia, COUNT(*) AS solicitudes
             FROM inscripciones i
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             INNER JOIN materias m ON m.id_materia = g.id_materia
             WHERE g.id_periodo = :id_periodo AND i.estado = 'inscrito'
             GROUP BY m.id_materia, m.nombre_materia
             ORDER BY solicitudes DESC, m.nombre_materia
             LIMIT " . (int) $limit
        );
        $statement->execute(['id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    public function topCarreras(int $periodoId, int $limit = 5): array
    {
        $statement = Database::connection()->prepare(
            "SELECT c.nombre_carrera, COUNT(DISTINCT i.id_estudiante) AS estudiantes
             FROM inscripciones i
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             INNER JOIN estudiantes e ON e.id_estudiante = i.id_estudiante
             INNER JOIN carreras c ON c.id_carrera = e.id_carrera
             WHERE g.id_periodo = :id_periodo AND i.estado = 'inscrito'
             GROUP BY c.id_carrera, c.nombre_carrera
             ORDER BY estudiantes DESC, c.nombre_carrera
             LIMIT " . (int) $limit
        );
        $statement->execute(['id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    public function attendanceBreakdown(int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT a.estado, COUNT(*) AS total
             FROM asistencias_sesion a
             INNER JOIN inscripciones i ON i.id_inscripcion = a.id_inscripcion
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             WHERE g.id_periodo = :id_periodo
             GROUP BY a.estado"
        );
        $statement->execute(['id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    /** Cobertura de horarios de los tutores activos (independiente del periodo). */
    public function coberturaTutores(): array
    {
        $configurada = TutorMateriaConfig::sqlMateriaConfigurada('tm');
        $row = Database::connection()->query(
            "SELECT
                (SELECT COUNT(*) FROM tutores t INNER JOIN usuarios u ON u.id_usuario = t.id_usuario WHERE u.estado = 'activo') AS tutores_activos,
                (SELECT COUNT(DISTINCT tm.id_tutor) FROM tutor_materia tm
                    INNER JOIN tutores t ON t.id_tutor = tm.id_tutor
                    INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND u.estado = 'activo'
                    WHERE {$configurada}) AS tutores_con_horarios"
        )->fetch();

        return $row ?: [];
    }

    public function satisfaction(int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) AS evaluaciones,
                    ROUND(AVG(e.calificacion_general), 2) AS general,
                    ROUND(AVG(e.puntualidad), 2) AS puntualidad,
                    ROUND(AVG(e.dominio), 2) AS dominio,
                    ROUND(AVG(e.claridad), 2) AS claridad,
                    ROUND(AVG(e.utilidad), 2) AS utilidad
             FROM evaluaciones_grupo e
             INNER JOIN inscripciones i ON i.id_inscripcion = e.id_inscripcion
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             WHERE g.id_periodo = :id_periodo"
        );
        $statement->execute(['id_periodo' => $periodoId]);

        return $statement->fetch() ?: [];
    }

    // ---------------------- Tutor ----------------------

    public function tutorSummary(int $tutorId, int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                (SELECT COUNT(*) FROM grupos_tutoria WHERE id_tutor = :t1 AND id_periodo = :p1) AS grupos,
                (SELECT COUNT(DISTINCT i.id_estudiante) FROM grupos_tutoria g JOIN inscripciones i ON i.id_grupo = g.id_grupo AND i.estado = 'inscrito' WHERE g.id_tutor = :t2 AND g.id_periodo = :p2) AS estudiantes,
                (SELECT COUNT(*) FROM grupos_tutoria g JOIN sesiones_tutoria s ON s.id_grupo = g.id_grupo AND s.estado = 'realizada' WHERE g.id_tutor = :t3 AND g.id_periodo = :p3) AS sesiones_realizadas,
                (SELECT ROUND(AVG(e.calificacion_general), 2) FROM evaluaciones_grupo e JOIN inscripciones i ON i.id_inscripcion = e.id_inscripcion JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo WHERE g.id_tutor = :t4 AND g.id_periodo = :p4) AS evaluacion_promedio"
        );
        $statement->execute(['t1' => $tutorId, 'p1' => $periodoId, 't2' => $tutorId, 'p2' => $periodoId, 't3' => $tutorId, 'p3' => $periodoId, 't4' => $tutorId, 'p4' => $periodoId]);

        return $statement->fetch() ?: [];
    }

    // ---------------------- Estudiante ----------------------

    public function studentSummary(int $studentId, int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT
                (SELECT COUNT(*) FROM inscripciones i JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo WHERE i.id_estudiante = :s1 AND g.id_periodo = :p1 AND i.estado = 'inscrito') AS tutorias,
                (SELECT COUNT(*) FROM asistencias_sesion a JOIN inscripciones i ON i.id_inscripcion = a.id_inscripcion JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo WHERE i.id_estudiante = :s2 AND g.id_periodo = :p2 AND a.estado IN ('asistio','parcial','retraso')) AS asistencias,
                (SELECT COUNT(*) FROM asistencias_sesion a JOIN inscripciones i ON i.id_inscripcion = a.id_inscripcion JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo WHERE i.id_estudiante = :s3 AND g.id_periodo = :p3) AS sesiones_registradas"
        );
        $statement->execute(['s1' => $studentId, 'p1' => $periodoId, 's2' => $studentId, 'p2' => $periodoId, 's3' => $studentId, 'p3' => $periodoId]);

        return $statement->fetch() ?: [];
    }
}
