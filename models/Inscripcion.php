<?php

declare(strict_types=1);

final class Inscripcion
{
    /** Bloques horarios ocupados por el estudiante en la campana (para evitar solapes). */
    public function studentBusySlots(int $studentId, int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT g.dia_semana, g.hora_inicio, g.hora_fin
             FROM inscripciones i
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             WHERE i.id_estudiante = :id_estudiante AND g.id_periodo = :id_periodo
               AND i.estado = 'inscrito' AND g.estado <> 'cancelado'"
        );
        $statement->execute(['id_estudiante' => $studentId, 'id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    /** Materias en las que el estudiante ya esta inscrito (o en espera) en la campana. */
    public function studentMatterIds(int $studentId, int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT DISTINCT g.id_materia
             FROM inscripciones i
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             WHERE i.id_estudiante = :id_estudiante AND g.id_periodo = :id_periodo AND i.estado <> 'cancelada'"
        );
        $statement->execute(['id_estudiante' => $studentId, 'id_periodo' => $periodoId]);

        return array_map('intval', array_column($statement->fetchAll(), 'id_materia'));
    }

    /** Inscripciones del estudiante en una campana, con datos del grupo. */
    public function forStudent(int $studentId, int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT i.id_inscripcion, i.estado AS estado_inscripcion, g.id_grupo, g.dia_semana,
                    g.hora_inicio, g.hora_fin, g.modalidad, g.estado AS estado_grupo,
                    m.nombre_materia, a.nombre AS aula, a.enlace,
                    CONCAT(u.nombre, ' ', u.apellido) AS tutor
             FROM inscripciones i
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN aulas a ON a.id_aula = g.id_aula
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE i.id_estudiante = :id_estudiante AND g.id_periodo = :id_periodo
             ORDER BY FIELD(g.dia_semana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'), g.hora_inicio"
        );
        $statement->execute(['id_estudiante' => $studentId, 'id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    /** Estudiantes inscritos en un grupo (para el tutor). */
    public function forGroup(int $grupoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT i.id_inscripcion, i.estado, e.id_estudiante,
                    CONCAT(u.nombre, ' ', u.apellido) AS estudiante, u.correo
             FROM inscripciones i
             INNER JOIN estudiantes e ON e.id_estudiante = i.id_estudiante
             INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
             WHERE i.id_grupo = :id_grupo AND i.estado <> 'cancelada'
             ORDER BY u.apellido, u.nombre"
        );
        $statement->execute(['id_grupo' => $grupoId]);

        return $statement->fetchAll();
    }

    public function existsActive(int $grupoId, int $studentId): bool
    {
        $statement = Database::connection()->prepare(
            "SELECT 1 FROM inscripciones WHERE id_grupo = :id_grupo AND id_estudiante = :id_estudiante AND estado <> 'cancelada' LIMIT 1"
        );
        $statement->execute(['id_grupo' => $grupoId, 'id_estudiante' => $studentId]);

        return (bool) $statement->fetchColumn();
    }

    /** Ids de estudiante+usuario de los inscritos activos de un grupo. */
    public function activeStudentsOfGroup(int $grupoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT i.id_estudiante, e.id_usuario FROM inscripciones i
             INNER JOIN estudiantes e ON e.id_estudiante = i.id_estudiante
             WHERE i.id_grupo = :id_grupo AND i.estado = 'inscrito'"
        );
        $statement->execute(['id_grupo' => $grupoId]);

        return $statement->fetchAll();
    }

    /** Cancela todas las inscripciones activas de un grupo. */
    public function cancelByGroup(int $grupoId): void
    {
        Database::connection()->prepare(
            "UPDATE inscripciones SET estado = 'cancelada' WHERE id_grupo = :id_grupo AND estado = 'inscrito'"
        )->execute(['id_grupo' => $grupoId]);
    }

    public function create(int $grupoId, int $studentId, string $estado = 'inscrito', string $origen = 'auto'): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO inscripciones (id_grupo, id_estudiante, estado, origen) VALUES (:id_grupo, :id_estudiante, :estado, :origen)'
        );
        $statement->execute([
            'id_grupo' => $grupoId, 'id_estudiante' => $studentId, 'estado' => $estado, 'origen' => $origen,
        ]);
    }
}
