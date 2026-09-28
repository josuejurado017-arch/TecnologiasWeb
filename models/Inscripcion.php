<?php

declare(strict_types=1);

final class Inscripcion
{
    /**
     * Bloques horarios ocupados por el estudiante en la campana (para evitar solapes):
     * una fila por cada dia del patron de sus grupos (grupo_dias), no solo el primero.
     */
    public function studentBusySlots(int $studentId, int $periodoId, ?int $excludeGrupoId = null): array
    {
        $statement = Database::connection()->prepare(
            "SELECT gd.dia_semana, g.hora_inicio, g.hora_fin
             FROM inscripciones i
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             INNER JOIN grupo_dias gd ON gd.id_grupo = g.id_grupo
             WHERE i.id_estudiante = :id_estudiante AND g.id_periodo = :id_periodo
               AND i.estado = 'inscrito' AND g.estado <> 'cancelado' AND g.id_grupo <> :excluir"
        );
        $statement->execute(['id_estudiante' => $studentId, 'id_periodo' => $periodoId, 'excluir' => $excludeGrupoId ?? 0]);

        return $statement->fetchAll();
    }

    /** Materias en las que el estudiante ya esta inscrito (o en espera) en la campana. */
    public function studentMatterIds(int $studentId, int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT DISTINCT g.id_materia
             FROM inscripciones i
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             WHERE i.id_estudiante = :id_estudiante AND g.id_periodo = :id_periodo AND i.estado NOT IN ('cancelada', 'trasladada')"
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
                    g.cupo_ocupado, g.fecha_aprobacion,
                    (SELECT GROUP_CONCAT(gd.dia_semana ORDER BY FIELD(gd.dia_semana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') SEPARATOR '/')
                       FROM grupo_dias gd WHERE gd.id_grupo = g.id_grupo) AS dias,
                    g.ubicacion, g.enlace, m.nombre_materia, e.nombre AS espacio,
                    CONCAT(u.nombre, ' ', u.apellido) AS tutor
             FROM inscripciones i
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN espacios_tutoria e ON e.id_espacio = g.id_espacio
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE i.id_estudiante = :id_estudiante AND g.id_periodo = :id_periodo
             ORDER BY FIELD(g.dia_semana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'), g.hora_inicio"
        );
        $statement->execute(['id_estudiante' => $studentId, 'id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    /**
     * Tutorias de periodos anteriores al indicado (historial del estudiante), con
     * su asistencia (asistio/retraso/parcial cuentan como presente) y si evaluo.
     */
    public function historyForStudent(int $studentId, ?int $excluirPeriodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT i.id_inscripcion, i.estado AS estado_inscripcion, g.estado AS estado_grupo, g.hora_inicio, g.hora_fin,
                    g.modalidad, m.nombre_materia, CONCAT(u.nombre, ' ', u.apellido) AS tutor,
                    p.nombre AS periodo, p.fecha_inicio,
                    (SELECT GROUP_CONCAT(gd.dia_semana ORDER BY FIELD(gd.dia_semana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') SEPARATOR '/')
                       FROM grupo_dias gd WHERE gd.id_grupo = g.id_grupo) AS dias,
                    (SELECT COUNT(*) FROM asistencias_sesion a WHERE a.id_inscripcion = i.id_inscripcion) AS sesiones,
                    (SELECT COUNT(*) FROM asistencias_sesion a WHERE a.id_inscripcion = i.id_inscripcion AND a.estado IN ('asistio','retraso','parcial')) AS presentes,
                    ev.calificacion_general
             FROM inscripciones i
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             INNER JOIN periodos p ON p.id_periodo = g.id_periodo
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             LEFT JOIN evaluaciones_grupo ev ON ev.id_inscripcion = i.id_inscripcion
             WHERE i.id_estudiante = :id_estudiante AND (:excluir IS NULL OR g.id_periodo <> :excluir2)
               AND i.estado <> 'trasladada'
             ORDER BY p.fecha_inicio DESC, m.nombre_materia"
        );
        $statement->execute(['id_estudiante' => $studentId, 'excluir' => $excluirPeriodoId, 'excluir2' => $excluirPeriodoId]);

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
             WHERE i.id_grupo = :id_grupo AND i.estado NOT IN ('cancelada', 'trasladada')
             ORDER BY u.apellido, u.nombre"
        );
        $statement->execute(['id_grupo' => $grupoId]);

        return $statement->fetchAll();
    }

    public function existsActive(int $grupoId, int $studentId): bool
    {
        $statement = Database::connection()->prepare(
            "SELECT 1 FROM inscripciones WHERE id_grupo = :id_grupo AND id_estudiante = :id_estudiante AND estado NOT IN ('cancelada', 'trasladada') LIMIT 1"
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

    /**
     * Inscripcion hecha por la coordinacion (db/039). Si el estudiante ya estuvo en
     * el grupo (fila cancelada), la reactiva: la clave (grupo, estudiante) es unica.
     */
    public function inscribirManual(int $grupoId, int $studentId): void
    {
        Database::connection()->prepare(
            "INSERT INTO inscripciones (id_grupo, id_estudiante, estado, origen) VALUES (:id_grupo, :id_estudiante, 'inscrito', 'manual_admin')
             ON DUPLICATE KEY UPDATE estado = 'inscrito', origen = 'manual_admin', fecha_inscripcion = NOW()"
        )->execute(['id_grupo' => $grupoId, 'id_estudiante' => $studentId]);
    }

    /** Retira a un estudiante de un grupo (db/039). Devuelve true si estaba inscrito. */
    public function retirar(int $grupoId, int $studentId): bool
    {
        $statement = Database::connection()->prepare(
            "UPDATE inscripciones SET estado = 'cancelada' WHERE id_grupo = :id_grupo AND id_estudiante = :id_estudiante AND estado = 'inscrito'"
        );
        $statement->execute(['id_grupo' => $grupoId, 'id_estudiante' => $studentId]);

        return $statement->rowCount() > 0;
    }

    /**
     * Estudiantes activos que coinciden con un texto (nombre, apellido, usuario o
     * registro universitario), para inscribir a mano desde Revisar grupo.
     */
    /**
     * Sugeridos para inscribir a mano sin escribir nada: estudiantes activos de la
     * carrera de la materia que aun no tienen tutoria en el periodo.
     */
    public function sugeridosParaMateria(int $materiaId, int $periodoId, int $limite = 15): array
    {
        $statement = Database::connection()->prepare(
            "SELECT e.id_estudiante, u.id_usuario, CONCAT(u.nombre, ' ', u.apellido) AS estudiante, u.usuario,
                    e.registro_universitario, e.semestre, c.nombre_carrera
             FROM estudiantes e
             INNER JOIN usuarios u ON u.id_usuario = e.id_usuario AND u.estado = 'activo'
             INNER JOIN materias m ON m.id_materia = :materia AND m.id_carrera = e.id_carrera
             LEFT JOIN carreras c ON c.id_carrera = e.id_carrera
             WHERE NOT EXISTS (SELECT 1 FROM inscripciones i INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
                               WHERE i.id_estudiante = e.id_estudiante AND i.estado = 'inscrito' AND g.id_periodo = :periodo)
             ORDER BY u.apellido, u.nombre
             LIMIT " . max(1, min(50, $limite))
        );
        $statement->execute(['materia' => $materiaId, 'periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    public function buscarEstudiantes(string $texto, int $limite = 10): array
    {
        $statement = Database::connection()->prepare(
            "SELECT e.id_estudiante, u.id_usuario, CONCAT(u.nombre, ' ', u.apellido) AS estudiante, u.usuario,
                    e.registro_universitario, e.semestre, c.nombre_carrera
             FROM estudiantes e
             INNER JOIN usuarios u ON u.id_usuario = e.id_usuario AND u.estado = 'activo'
             LEFT JOIN carreras c ON c.id_carrera = e.id_carrera
             WHERE CONCAT(u.nombre, ' ', u.apellido) LIKE :t1 OR u.usuario LIKE :t2 OR e.registro_universitario LIKE :t3
             ORDER BY u.apellido, u.nombre
             LIMIT " . max(1, min(25, $limite))
        );
        $like = '%' . addcslashes($texto, '%_\\') . '%';
        $statement->execute(['t1' => $like, 't2' => $like, 't3' => $like]);

        return $statement->fetchAll();
    }

    /** Datos basicos de un estudiante (para validar una inscripcion manual). */
    public function estudiante(int $studentId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT e.id_estudiante, u.id_usuario, u.estado, CONCAT(u.nombre, ' ', u.apellido) AS estudiante
             FROM estudiantes e INNER JOIN usuarios u ON u.id_usuario = e.id_usuario WHERE e.id_estudiante = :id LIMIT 1"
        );
        $statement->execute(['id' => $studentId]);

        return $statement->fetch() ?: null;
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
