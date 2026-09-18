<?php

declare(strict_types=1);

final class Dashboard
{
    public function summary(string $role, int $userId): array
    {
        if ($role === 'tutor') {
            $sql = <<<'SQL'
                SELECT
                    (SELECT COUNT(*) FROM tutor_materia tm INNER JOIN tutores t ON t.id_tutor = tm.id_tutor WHERE t.id_usuario = :tutor_user) AS materias_asignadas,
                    (SELECT COUNT(*) FROM tutorias tu INNER JOIN tutores t ON t.id_tutor = tu.id_tutor WHERE t.id_usuario = :tutor_pending_user AND tu.estado = 'pendiente') AS tutorias_pendientes,
                    (SELECT COUNT(*) FROM tutorias tu INNER JOIN tutores t ON t.id_tutor = tu.id_tutor WHERE t.id_usuario = :tutor_confirmed_user AND tu.estado = 'confirmada' AND tu.fecha >= CURRENT_DATE) AS tutorias_confirmadas,
                    (SELECT COALESCE(ROUND(AVG(ev.calificacion), 2), 0) FROM evaluaciones_tutoria ev INNER JOIN tutorias tu ON tu.id_tutoria = ev.id_tutoria INNER JOIN tutores t ON t.id_tutor = tu.id_tutor WHERE t.id_usuario = :tutor_rating_user) AS promedio_calificacion,
                    (SELECT COUNT(*) FROM disponibilidad_tutor d INNER JOIN tutores t ON t.id_tutor = d.id_tutor WHERE t.id_usuario = :tutor_availability_user) AS horarios_configurados,
                    (SELECT COUNT(*) FROM tutorias tu INNER JOIN tutores t ON t.id_tutor = tu.id_tutor WHERE t.id_usuario = :tutor_completed_user AND tu.estado = 'realizada') AS tutorias_realizadas
            SQL;
            $params = [
                'tutor_user' => $userId,
                'tutor_pending_user' => $userId,
                'tutor_confirmed_user' => $userId,
                'tutor_rating_user' => $userId,
                'tutor_availability_user' => $userId,
                'tutor_completed_user' => $userId,
            ];
        } elseif ($role === 'estudiante') {
            $sql = <<<'SQL'
                SELECT
                    (SELECT COUNT(*) FROM tutorias tu INNER JOIN estudiantes e ON e.id_estudiante = tu.id_estudiante WHERE e.id_usuario = :student_pending_user AND tu.estado = 'pendiente') AS tutorias_pendientes,
                    (SELECT COUNT(*) FROM tutorias tu INNER JOIN estudiantes e ON e.id_estudiante = tu.id_estudiante WHERE e.id_usuario = :student_confirmed_user AND tu.estado = 'confirmada' AND tu.fecha >= CURRENT_DATE) AS tutorias_confirmadas,
                    (SELECT COUNT(*) FROM tutorias tu INNER JOIN estudiantes e ON e.id_estudiante = tu.id_estudiante LEFT JOIN evaluaciones_tutoria ev ON ev.id_tutoria = tu.id_tutoria WHERE e.id_usuario = :student_evaluation_user AND tu.estado = 'realizada' AND ev.id_evaluacion IS NULL) AS evaluaciones_pendientes,
                    (SELECT COUNT(DISTINCT tm.id_materia) FROM tutor_materia tm INNER JOIN tutores t ON t.id_tutor = tm.id_tutor INNER JOIN usuarios u ON u.id_usuario = t.id_usuario WHERE u.estado = 'activo') AS materias_disponibles,
                    (SELECT e.semestre FROM estudiantes e WHERE e.id_usuario = :student_profile_user LIMIT 1) AS semestre,
                    (SELECT c.nombre_carrera FROM estudiantes e INNER JOIN carreras c ON c.id_carrera = e.id_carrera WHERE e.id_usuario = :student_career_user LIMIT 1) AS nombre_carrera,
                    (SELECT COUNT(*) FROM tutorias tu INNER JOIN estudiantes e ON e.id_estudiante = tu.id_estudiante WHERE e.id_usuario = :student_completed_user AND tu.estado = 'realizada') AS tutorias_realizadas,
                    (SELECT COUNT(*) FROM tutorias tu INNER JOIN estudiantes e ON e.id_estudiante = tu.id_estudiante WHERE e.id_usuario = :student_total_user) AS tutorias_totales
            SQL;
            $params = [
                'student_pending_user' => $userId,
                'student_confirmed_user' => $userId,
                'student_evaluation_user' => $userId,
                'student_profile_user' => $userId,
                'student_career_user' => $userId,
                'student_completed_user' => $userId,
                'student_total_user' => $userId,
            ];
        } else {
            $sql = <<<'SQL'
                SELECT
                    (SELECT COUNT(*) FROM usuarios) AS total_usuarios,
                    (SELECT COUNT(*) FROM usuarios WHERE estado = 'activo') AS usuarios_activos,
                    (SELECT COUNT(*) FROM estudiantes) AS total_estudiantes,
                    (SELECT COUNT(*) FROM tutores) AS total_tutores,
                    (SELECT COUNT(*) FROM carreras) AS total_carreras,
                    (SELECT COUNT(*) FROM materias) AS total_materias,
                    (SELECT COUNT(*) FROM tutorias WHERE estado = 'pendiente') AS tutorias_pendientes,
                    (SELECT COUNT(*) FROM tutorias WHERE estado = 'confirmada') AS tutorias_confirmadas,
                    (SELECT COUNT(*) FROM usuarios WHERE estado = 'pendiente') AS usuarios_pendientes
            SQL;
            $params = [];
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        $summary = $statement->fetch();

        return $summary ?: [];
    }

    public function upcoming(string $role, int $userId): array
    {
        $sql = <<<'SQL'
            SELECT t.id_tutoria, t.fecha, t.hora_inicio, t.hora_fin, t.modalidad, t.estado,
                   m.nombre_materia,
                   CONCAT(eu.nombre, ' ', eu.apellido) AS estudiante,
                   CONCAT(tu.nombre, ' ', tu.apellido) AS tutor
            FROM tutorias t
            INNER JOIN estudiantes e ON e.id_estudiante = t.id_estudiante
            INNER JOIN usuarios eu ON eu.id_usuario = e.id_usuario
            INNER JOIN tutores tr ON tr.id_tutor = t.id_tutor
            INNER JOIN usuarios tu ON tu.id_usuario = tr.id_usuario
            INNER JOIN materias m ON m.id_materia = t.id_materia
            WHERE t.fecha >= CURRENT_DATE
              AND t.estado IN ('pendiente', 'confirmada')
        SQL;
        $params = [];

        if ($role === 'estudiante') {
            $sql .= ' AND eu.id_usuario = :viewer_id';
            $params['viewer_id'] = $userId;
        } elseif ($role === 'tutor') {
            $sql .= ' AND tu.id_usuario = :viewer_id';
            $params['viewer_id'] = $userId;
        }

        $sql .= ' ORDER BY t.fecha, t.hora_inicio LIMIT 6';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
