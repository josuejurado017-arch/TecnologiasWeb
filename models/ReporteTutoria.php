<?php

declare(strict_types=1);

final class ReporteTutoria
{
    public function all(array $filters = []): array
    {
        $sql = <<<'SQL'
            SELECT t.id_tutoria, t.fecha, t.hora_inicio, t.hora_fin, t.modalidad,
                   t.estado, t.motivo_cancelacion, t.fecha_cancelacion,
                   m.nombre_materia,
                   CONCAT(eu.nombre, ' ', eu.apellido) AS estudiante,
                   CONCAT(tu.nombre, ' ', tu.apellido) AS tutor,
                   COALESCE(a.estado_asistencia, 'sin_registro') AS asistencia,
                   a.minutos_retraso
            FROM tutorias t
            INNER JOIN estudiantes e ON e.id_estudiante = t.id_estudiante
            INNER JOIN usuarios eu ON eu.id_usuario = e.id_usuario
            INNER JOIN tutores tr ON tr.id_tutor = t.id_tutor
            INNER JOIN usuarios tu ON tu.id_usuario = tr.id_usuario
            INNER JOIN materias m ON m.id_materia = t.id_materia
            LEFT JOIN asistencias_tutorias a ON a.id_tutoria = t.id_tutoria
        SQL;
        $where = [];
        $params = [];
        if (!empty($filters['id_tutor'])) {
            $where[] = 't.id_tutor = :id_tutor';
            $params['id_tutor'] = $filters['id_tutor'];
        }
        if (!empty($filters['id_estudiante'])) {
            $where[] = 't.id_estudiante = :id_estudiante';
            $params['id_estudiante'] = $filters['id_estudiante'];
        }
        if (!empty($filters['id_materia'])) {
            $where[] = 't.id_materia = :id_materia';
            $params['id_materia'] = $filters['id_materia'];
        }
        if (!empty($filters['estado'])) {
            $where[] = 't.estado = :estado';
            $params['estado'] = $filters['estado'];
        }
        if (!empty($filters['fecha_desde'])) {
            $where[] = 't.fecha >= :fecha_desde';
            $params['fecha_desde'] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[] = 't.fecha <= :fecha_hasta';
            $params['fecha_hasta'] = $filters['fecha_hasta'];
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY t.fecha DESC, t.hora_inicio DESC';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function tutors(): array
    {
        return Database::connection()->query(
            "SELECT t.id_tutor, CONCAT(u.nombre, ' ', u.apellido) AS nombre FROM tutores t INNER JOIN usuarios u ON u.id_usuario = t.id_usuario ORDER BY u.apellido, u.nombre"
        )->fetchAll();
    }

    public function students(): array
    {
        return Database::connection()->query(
            "SELECT e.id_estudiante, CONCAT(u.nombre, ' ', u.apellido) AS nombre FROM estudiantes e INNER JOIN usuarios u ON u.id_usuario = e.id_usuario ORDER BY u.apellido, u.nombre"
        )->fetchAll();
    }

    public function subjects(): array
    {
        return Database::connection()->query(
            'SELECT id_materia, nombre_materia AS nombre FROM materias ORDER BY nombre_materia'
        )->fetchAll();
    }
}
