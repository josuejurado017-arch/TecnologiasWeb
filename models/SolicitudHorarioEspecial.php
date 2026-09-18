<?php

declare(strict_types=1);

final class SolicitudHorarioEspecial
{
    public function subjects(int $studentId): array
    {
        return (new Tutoria())->subjectOptions($studentId);
    }

    public function tutors(int $matterId, int $studentId): array
    {
        return (new Tutoria())->tutorsForMatter($matterId, $studentId);
    }

    public function allForViewer(string $role, int $userId): array
    {
        $sql = <<<'SQL'
            SELECT s.id_solicitud, s.id_estudiante, s.id_tutor, s.id_materia,
                   s.fecha_propuesta, s.hora_inicio, s.hora_fin, s.modalidad,
                   s.lugar_o_enlace, s.observaciones, s.estado, s.respuesta_tutor,
                   s.fecha_respuesta, s.id_tutoria, s.fecha_solicitud,
                   CONCAT(eu.nombre, ' ', eu.apellido) AS estudiante,
                   CONCAT(tu.nombre, ' ', tu.apellido) AS tutor,
                   m.nombre_materia
            FROM solicitudes_horario_especial s
            INNER JOIN estudiantes e ON e.id_estudiante = s.id_estudiante
            INNER JOIN usuarios eu ON eu.id_usuario = e.id_usuario
            INNER JOIN tutores tr ON tr.id_tutor = s.id_tutor
            INNER JOIN usuarios tu ON tu.id_usuario = tr.id_usuario
            INNER JOIN materias m ON m.id_materia = s.id_materia
        SQL;
        $where = [];
        $params = [];
        if ($role === 'tutor') {
            $where[] = 'tu.id_usuario = :viewer_id';
            $params['viewer_id'] = $userId;
        } elseif ($role === 'estudiante') {
            $where[] = 'eu.id_usuario = :viewer_id';
            $params['viewer_id'] = $userId;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY s.fecha_solicitud DESC, s.id_solicitud DESC';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            "INSERT INTO solicitudes_horario_especial
                (id_estudiante, id_tutor, id_materia, fecha_propuesta, hora_inicio, hora_fin,
                 modalidad, lugar_o_enlace, observaciones)
             SELECT :id_estudiante, tm.id_tutor, tm.id_materia, :fecha_propuesta, :hora_inicio,
                    :hora_fin, :modalidad, :lugar_o_enlace, :observaciones
             FROM tutor_materia tm
             INNER JOIN tutores tr ON tr.id_tutor = tm.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = tr.id_usuario
             INNER JOIN materias m ON m.id_materia = tm.id_materia
             INNER JOIN estudiantes e ON e.id_estudiante = :career_student
             WHERE tm.id_tutor = :id_tutor
               AND tm.id_materia = :id_materia
               AND u.estado = 'activo'
               AND (m.id_carrera IS NULL OR m.id_carrera = e.id_carrera)"
        );
        $statement->execute([
            'id_estudiante' => $data['id_estudiante'],
            'career_student' => $data['id_estudiante'],
            'id_tutor' => $data['id_tutor'],
            'id_materia' => $data['id_materia'],
            'fecha_propuesta' => $data['fecha_propuesta'],
            'hora_inicio' => $data['hora_inicio'],
            'hora_fin' => $data['hora_fin'],
            'modalidad' => $data['modalidad'],
            'lugar_o_enlace' => $data['lugar_o_enlace'] !== '' ? $data['lugar_o_enlace'] : null,
            'observaciones' => $data['observaciones'] !== '' ? $data['observaciones'] : null,
        ]);
        if ($statement->rowCount() < 1) {
            throw new RuntimeException('El tutor no atiende esa materia o no es compatible con la carrera del estudiante.');
        }

        return (int) Database::connection()->lastInsertId();
    }

    public function tutorUserId(int $tutorId): ?int
    {
        $statement = Database::connection()->prepare('SELECT id_usuario FROM tutores WHERE id_tutor = :id_tutor LIMIT 1');
        $statement->execute(['id_tutor' => $tutorId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function findForDecision(PDO $pdo, int $id, string $role, int $userId): ?array
    {
        $sql = <<<'SQL'
            SELECT s.*, eu.id_usuario AS estudiante_usuario, tu.id_usuario AS tutor_usuario,
                   m.nombre_materia, CONCAT(eu.nombre, ' ', eu.apellido) AS estudiante,
                   CONCAT(tu.nombre, ' ', tu.apellido) AS tutor
            FROM solicitudes_horario_especial s
            INNER JOIN estudiantes e ON e.id_estudiante = s.id_estudiante
            INNER JOIN usuarios eu ON eu.id_usuario = e.id_usuario
            INNER JOIN tutores tr ON tr.id_tutor = s.id_tutor
            INNER JOIN usuarios tu ON tu.id_usuario = tr.id_usuario
            INNER JOIN materias m ON m.id_materia = s.id_materia
            WHERE s.id_solicitud = :id_solicitud AND s.estado = 'pendiente'
        SQL;
        $params = ['id_solicitud' => $id];
        if ($role === 'tutor') {
            $sql .= ' AND tu.id_usuario = :user_id';
            $params['user_id'] = $userId;
        } elseif ($role !== 'administrador') {
            return null;
        }
        $statement = $pdo->prepare($sql . ' LIMIT 1 FOR UPDATE');
        $statement->execute($params);
        $request = $statement->fetch();

        return $request ?: null;
    }

    public function approve(PDO $pdo, array $request, string $response): int
    {
        $conflict = $pdo->prepare(
            "SELECT 1 FROM tutorias WHERE fecha = :fecha AND estado IN ('pendiente', 'confirmada') AND (id_tutor = :id_tutor OR id_estudiante = :id_estudiante) AND hora_inicio < :hora_fin AND hora_fin > :hora_inicio LIMIT 1"
        );
        $conflict->execute([
            'fecha' => $request['fecha_propuesta'],
            'id_tutor' => $request['id_tutor'],
            'id_estudiante' => $request['id_estudiante'],
            'hora_inicio' => $request['hora_inicio'],
            'hora_fin' => $request['hora_fin'],
        ]);
        if ($conflict->fetchColumn()) {
            throw new RuntimeException('El horario especial ya entra en conflicto con otra tutoria.');
        }

        $insert = $pdo->prepare(
            "INSERT INTO tutorias (id_estudiante, id_tutor, id_materia, fecha, hora_inicio, hora_fin, modalidad, lugar_o_enlace, estado, observaciones) VALUES (:id_estudiante, :id_tutor, :id_materia, :fecha, :hora_inicio, :hora_fin, :modalidad, :lugar_o_enlace, 'confirmada', :observaciones)"
        );
        $insert->execute([
            'id_estudiante' => $request['id_estudiante'],
            'id_tutor' => $request['id_tutor'],
            'id_materia' => $request['id_materia'],
            'fecha' => $request['fecha_propuesta'],
            'hora_inicio' => $request['hora_inicio'],
            'hora_fin' => $request['hora_fin'],
            'modalidad' => $request['modalidad'],
            'lugar_o_enlace' => $request['lugar_o_enlace'],
            'observaciones' => $request['observaciones'],
        ]);
        $tutoriaId = (int) $pdo->lastInsertId();
        $update = $pdo->prepare(
            "UPDATE solicitudes_horario_especial SET estado = 'convertida', respuesta_tutor = :respuesta_tutor, fecha_respuesta = CURRENT_TIMESTAMP, id_tutoria = :id_tutoria WHERE id_solicitud = :id_solicitud"
        );
        $update->execute([
            'respuesta_tutor' => $response !== '' ? $response : 'Solicitud aprobada.',
            'id_tutoria' => $tutoriaId,
            'id_solicitud' => $request['id_solicitud'],
        ]);

        return $tutoriaId;
    }

    public function reject(PDO $pdo, int $id, string $response): void
    {
        $statement = $pdo->prepare(
            "UPDATE solicitudes_horario_especial SET estado = 'rechazada', respuesta_tutor = :respuesta_tutor, fecha_respuesta = CURRENT_TIMESTAMP WHERE id_solicitud = :id_solicitud AND estado = 'pendiente'"
        );
        $statement->execute([
            'respuesta_tutor' => $response,
            'id_solicitud' => $id,
        ]);
        if ($statement->rowCount() < 1) {
            throw new RuntimeException('La solicitud ya fue respondida.');
        }
    }
}
