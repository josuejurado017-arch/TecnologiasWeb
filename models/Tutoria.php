<?php

declare(strict_types=1);

final class Tutoria
{
    private function selectSql(): string
    {
        return <<<'SQL'
            SELECT t.id_tutoria, t.id_estudiante, t.id_tutor, t.id_materia, t.id_disponibilidad,
                   t.fecha, t.hora_inicio, t.hora_fin, t.modalidad,
                   t.lugar_o_enlace, t.estado, t.observaciones, t.fecha_solicitud,
                   t.motivo_cancelacion, t.fecha_cancelacion, t.usuario_cancelacion,
                   COALESCE(a.estado_asistencia, 'sin_registro') AS asistencia,
                   a.minutos_retraso, a.observaciones AS observaciones_asistencia,
                   CONCAT(eu.nombre, ' ', eu.apellido) AS estudiante,
                   CONCAT(tu.nombre, ' ', tu.apellido) AS tutor,
                   m.nombre_materia
            FROM tutorias t
            INNER JOIN estudiantes e ON e.id_estudiante = t.id_estudiante
            INNER JOIN usuarios eu ON eu.id_usuario = e.id_usuario
            INNER JOIN tutores tr ON tr.id_tutor = t.id_tutor
            INNER JOIN usuarios tu ON tu.id_usuario = tr.id_usuario
            INNER JOIN materias m ON m.id_materia = t.id_materia
            LEFT JOIN asistencias_tutorias a ON a.id_tutoria = t.id_tutoria
        SQL;
    }

    public function allForViewer(string $role, int $userId, array $filters = []): array
    {
        $sql = $this->selectSql();
        $params = [];
        $where = [];
        if ($role === 'estudiante') {
            $where[] = 'eu.id_usuario = :viewer_id';
            $params['viewer_id'] = $userId;
        } elseif ($role === 'tutor') {
            $where[] = 'tu.id_usuario = :viewer_id';
            $params['viewer_id'] = $userId;
        }
        if (!empty($filters['estado'])) {
            $where[] = 't.estado = :estado';
            $params['estado'] = $filters['estado'];
        }
        if (!empty($filters['id_materia'])) {
            $where[] = 't.id_materia = :filter_materia';
            $params['filter_materia'] = $filters['id_materia'];
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

    public function filterOptions(string $role, int $userId): array
    {
        $sql = <<<'SQL'
            SELECT DISTINCT m.id_materia, m.nombre_materia
            FROM tutorias t
            INNER JOIN materias m ON m.id_materia = t.id_materia
        SQL;
        $params = [];
        if ($role === 'estudiante') {
            $sql .= ' INNER JOIN estudiantes e ON e.id_estudiante = t.id_estudiante WHERE e.id_usuario = :viewer_id';
            $params['viewer_id'] = $userId;
        } elseif ($role === 'tutor') {
            $sql .= ' INNER JOIN tutores tr ON tr.id_tutor = t.id_tutor WHERE tr.id_usuario = :viewer_id';
            $params['viewer_id'] = $userId;
        }
        $sql .= ' ORDER BY m.nombre_materia';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function findForViewer(int $id, string $role, int $userId): ?array
    {
        $sql = $this->selectSql() . ' WHERE t.id_tutoria = :id_tutoria';
        $params = ['id_tutoria' => $id];
        if ($role === 'estudiante') {
            $sql .= ' AND eu.id_usuario = :id_usuario';
            $params['id_usuario'] = $userId;
        } elseif ($role === 'tutor') {
            $sql .= ' AND tu.id_usuario = :id_usuario';
            $params['id_usuario'] = $userId;
        }
        $statement = Database::connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);
        $tutoring = $statement->fetch();

        return $tutoring ?: null;
    }

    public function studentIdByUserId(int $userId): ?int
    {
        $statement = Database::connection()->prepare(
            'SELECT id_estudiante FROM estudiantes WHERE id_usuario = :id_usuario LIMIT 1'
        );
        $statement->execute(['id_usuario' => $userId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function tutorIdByUserId(int $userId): ?int
    {
        $statement = Database::connection()->prepare(
            'SELECT id_tutor FROM tutores WHERE id_usuario = :id_usuario LIMIT 1'
        );
        $statement->execute(['id_usuario' => $userId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function subjectOptions(int $studentId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT DISTINCT m.id_materia, m.nombre_materia, c.nombre_carrera
             FROM materias m
             INNER JOIN tutor_materia tm ON tm.id_materia = m.id_materia
             INNER JOIN tutores t ON t.id_tutor = tm.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND u.estado = 'activo'
             INNER JOIN disponibilidad_tutor d ON d.id_tutor = t.id_tutor
             INNER JOIN estudiantes e ON e.id_estudiante = :id_estudiante
             LEFT JOIN carreras c ON c.id_carrera = m.id_carrera
             WHERE m.id_carrera IS NULL OR m.id_carrera = e.id_carrera
             ORDER BY m.nombre_materia"
        );
        $statement->execute(['id_estudiante' => $studentId]);

        return $statement->fetchAll();
    }

    public function offerings(?int $studentId = null): array
    {
        $sql = <<<'SQL'
            SELECT tm.id_tutor, tm.id_materia,
                   CONCAT(u.nombre, ' ', u.apellido) AS tutor,
                   m.nombre_materia,
                   GROUP_CONCAT(
                       DISTINCT CONCAT(d.dia_semana, ' ', TIME_FORMAT(d.hora_inicio, '%H:%i'), '-', TIME_FORMAT(d.hora_fin, '%H:%i'))
                       ORDER BY FIELD(d.dia_semana, 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'), d.hora_inicio
                       SEPARATOR ', '
                   ) AS disponibilidad
            FROM tutor_materia tm
            INNER JOIN tutores t ON t.id_tutor = tm.id_tutor
            INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
            INNER JOIN materias m ON m.id_materia = tm.id_materia
            INNER JOIN disponibilidad_tutor d ON d.id_tutor = tm.id_tutor
        SQL;
        $params = [];
        $where = ["u.estado = 'activo'"];
        if ($studentId !== null) {
            $sql .= ' INNER JOIN estudiantes es ON es.id_estudiante = :student_id';
            $where[] = '(m.id_carrera IS NULL OR m.id_carrera = es.id_carrera)';
            $params['student_id'] = $studentId;
        }
        $sql .= ' WHERE ' . implode(' AND ', $where) . ' ';
        $sql .= <<<'SQL'
            GROUP BY tm.id_tutor, tm.id_materia, u.nombre, u.apellido, m.nombre_materia
            ORDER BY m.nombre_materia, u.apellido, u.nombre
        SQL;
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function create(array $data): int
    {
        $sql = <<<'SQL'
            INSERT INTO tutorias
                (id_estudiante, id_tutor, id_materia, id_disponibilidad, fecha, hora_inicio, hora_fin,
                  modalidad, lugar_o_enlace, estado, observaciones)
            SELECT :id_estudiante, tm.id_tutor, tm.id_materia, :id_disponibilidad, :fecha, :hora_inicio,
                   :hora_fin, :modalidad, :lugar_o_enlace, 'pendiente', :observaciones
            FROM tutor_materia tm
            INNER JOIN tutores tr ON tr.id_tutor = tm.id_tutor
            INNER JOIN usuarios u ON u.id_usuario = tr.id_usuario
            INNER JOIN materias m ON m.id_materia = tm.id_materia
            INNER JOIN estudiantes es ON es.id_estudiante = :career_student
            WHERE tm.id_tutor = :id_tutor AND tm.id_materia = :id_materia
              AND u.estado = 'activo'
              AND (m.id_carrera IS NULL OR m.id_carrera = es.id_carrera)
        SQL;
        $statement = Database::connection()->prepare($sql);
        $statement->execute([
            'id_estudiante' => $data['id_estudiante'],
            'career_student' => $data['id_estudiante'],
            'id_tutor' => $data['id_tutor'],
            'id_materia' => $data['id_materia'],
            'id_disponibilidad' => $data['id_disponibilidad'] ?? null,
            'fecha' => $data['fecha'],
            'hora_inicio' => $data['hora_inicio'],
            'hora_fin' => $data['hora_fin'],
            'modalidad' => $data['modalidad'],
            'lugar_o_enlace' => $data['lugar_o_enlace'] !== '' ? $data['lugar_o_enlace'] : null,
            'observaciones' => $data['observaciones'] !== '' ? $data['observaciones'] : null,
        ]);
        if ($statement->rowCount() < 1) {
            throw new RuntimeException('La materia no esta asignada al tutor o no corresponde a la carrera del estudiante.');
        }

        return (int) Database::connection()->lastInsertId();
    }

    public function findByIdForUpdate(PDO $pdo, int $id): ?array
    {
        $statement = $pdo->prepare(
            'SELECT id_tutoria, id_estudiante, id_tutor, id_materia, id_disponibilidad, fecha, hora_inicio, hora_fin, modalidad, estado, observaciones FROM tutorias WHERE id_tutoria = :id_tutoria LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['id_tutoria' => $id]);
        $tutoria = $statement->fetch();

        return $tutoria ?: null;
    }

    public function lockSchedulingActors(PDO $pdo, int $studentId, int $tutorId): void
    {
        $student = $pdo->prepare('SELECT id_estudiante FROM estudiantes WHERE id_estudiante = :id_estudiante FOR UPDATE');
        $student->execute(['id_estudiante' => $studentId]);
        $tutor = $pdo->prepare('SELECT id_tutor FROM tutores WHERE id_tutor = :id_tutor FOR UPDATE');
        $tutor->execute(['id_tutor' => $tutorId]);
    }

    public function updateSchedule(PDO $pdo, int $id, array $data): void
    {
        $statement = $pdo->prepare(
            'UPDATE tutorias SET id_tutor = :id_tutor, id_disponibilidad = NULL, fecha = :fecha, hora_inicio = :hora_inicio, hora_fin = :hora_fin, modalidad = :modalidad, lugar_o_enlace = :lugar_o_enlace WHERE id_tutoria = :id_tutoria'
        );
        $statement->execute([
            'id_tutoria' => $id,
            'id_tutor' => $data['id_tutor'],
            'fecha' => $data['fecha'],
            'hora_inicio' => $data['hora_inicio'],
            'hora_fin' => $data['hora_fin'],
            'modalidad' => $data['modalidad'],
            'lugar_o_enlace' => $data['lugar_o_enlace'] !== '' ? $data['lugar_o_enlace'] : null,
        ]);
    }

    public function isOfferingForStudent(int $tutorId, int $matterId, int $studentId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM tutor_materia tm INNER JOIN materias m ON m.id_materia = tm.id_materia INNER JOIN estudiantes e ON e.id_estudiante = :id_estudiante INNER JOIN tutores t ON t.id_tutor = tm.id_tutor INNER JOIN usuarios u ON u.id_usuario = t.id_usuario WHERE tm.id_tutor = :id_tutor AND tm.id_materia = :id_materia AND u.estado = \'activo\' AND (m.id_carrera IS NULL OR m.id_carrera = e.id_carrera) LIMIT 1'
        );
        $statement->execute([
            'id_estudiante' => $studentId,
            'id_tutor' => $tutorId,
            'id_materia' => $matterId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function tutorsForMatter(int $matterId, int $studentId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT t.id_tutor, CONCAT(u.nombre, ' ', u.apellido) AS tutor FROM tutor_materia tm INNER JOIN tutores t ON t.id_tutor = tm.id_tutor INNER JOIN usuarios u ON u.id_usuario = t.id_usuario INNER JOIN materias m ON m.id_materia = tm.id_materia INNER JOIN estudiantes e ON e.id_estudiante = :id_estudiante WHERE tm.id_materia = :id_materia AND u.estado = 'activo' AND (m.id_carrera IS NULL OR m.id_carrera = e.id_carrera) GROUP BY t.id_tutor, u.nombre, u.apellido ORDER BY u.apellido, u.nombre"
        );
        $statement->execute(['id_estudiante' => $studentId, 'id_materia' => $matterId]);

        return $statement->fetchAll();
    }

    public function availableSlots(int $studentId, int $tutorId, int $matterId, int $daysAhead = 42): array
    {
        if (!$this->isOfferingForStudent($tutorId, $matterId, $studentId)) {
            return [];
        }

        $availabilityStatement = Database::connection()->prepare(
            'SELECT d.id_disponibilidad, d.dia_semana, TIME_FORMAT(d.hora_inicio, \'%H:%i\') AS hora_inicio, TIME_FORMAT(d.hora_fin, \'%H:%i\') AS hora_fin FROM disponibilidad_tutor d WHERE d.id_tutor = :id_tutor ORDER BY d.dia_semana, d.hora_inicio'
        );
        $availabilityStatement->execute(['id_tutor' => $tutorId]);
        $availability = $availabilityStatement->fetchAll();
        if (!$availability) {
            return [];
        }

        $today = new DateTimeImmutable('today');
        $endDate = $today->modify('+' . max(1, min($daysAhead, 90)) . ' days')->format('Y-m-d');
        $busyStatement = Database::connection()->prepare(
            "SELECT fecha, hora_inicio, hora_fin, id_tutor, id_estudiante FROM tutorias WHERE fecha BETWEEN :fecha_desde AND :fecha_hasta AND estado IN ('pendiente', 'confirmada') AND (id_tutor = :id_tutor OR id_estudiante = :id_estudiante)"
        );
        $busyStatement->execute([
            'fecha_desde' => $today->format('Y-m-d'),
            'fecha_hasta' => $endDate,
            'id_tutor' => $tutorId,
            'id_estudiante' => $studentId,
        ]);
        $busy = $busyStatement->fetchAll();
        $dayNames = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
        $slots = [];

        for ($offset = 0; $offset <= max(1, min($daysAhead, 90)); $offset++) {
            $date = $today->modify('+' . $offset . ' days');
            $dateValue = $date->format('Y-m-d');
            $dayName = $dayNames[(int) $date->format('N') - 1] ?? null;
            if ($dayName === null) {
                continue;
            }
            foreach ($availability as $row) {
                if ($row['dia_semana'] !== $dayName) {
                    continue;
                }
                if ($offset === 0 && $row['hora_inicio'] <= date('H:i')) {
                    continue;
                }
                $blocked = false;
                foreach ($busy as $reserved) {
                    if ($reserved['fecha'] !== $dateValue) {
                        continue;
                    }
                    $isTutorConflict = (int) $reserved['id_tutor'] === $tutorId;
                    $isStudentConflict = (int) $reserved['id_estudiante'] === $studentId;
                    if (($isTutorConflict || $isStudentConflict)
                        && $row['hora_inicio'] < substr($reserved['hora_fin'], 0, 5)
                        && $row['hora_fin'] > substr($reserved['hora_inicio'], 0, 5)) {
                        $blocked = true;
                        break;
                    }
                }
                if ($blocked) {
                    continue;
                }
                $slots[] = [
                    'slot_key' => $row['id_disponibilidad'] . '|' . $dateValue,
                    'id_disponibilidad' => (int) $row['id_disponibilidad'],
                    'fecha' => $dateValue,
                    'dia_semana' => $dayName,
                    'hora_inicio' => $row['hora_inicio'],
                    'hora_fin' => $row['hora_fin'],
                ];
            }
        }

        return $slots;
    }

    public function resolveAvailableSlot(int $studentId, int $tutorId, int $matterId, string $slotKey): ?array
    {
        $slots = $this->availableSlots($studentId, $tutorId, $matterId);
        foreach ($slots as $slot) {
            if ($slot['slot_key'] === $slotKey) {
                return $slot;
            }
        }

        return null;
    }

    public function hasAvailability(int $tutorId, string $date, string $start, string $end): bool
    {
        $sql = <<<'SQL'
            SELECT 1
            FROM disponibilidad_tutor d
            WHERE d.id_tutor = :id_tutor
              AND d.dia_semana = CASE WEEKDAY(:fecha)
                    WHEN 0 THEN 'Lunes'
                    WHEN 1 THEN 'Martes'
                    WHEN 2 THEN 'Miercoles'
                    WHEN 3 THEN 'Jueves'
                    WHEN 4 THEN 'Viernes'
                    WHEN 5 THEN 'Sabado'
                  END
              AND d.hora_inicio <= :hora_inicio
              AND d.hora_fin >= :hora_fin
            LIMIT 1
        SQL;
        $statement = Database::connection()->prepare($sql);
        $statement->execute([
            'id_tutor' => $tutorId,
            'fecha' => $date,
            'hora_inicio' => $start,
            'hora_fin' => $end,
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function hasTutorConflict(int $tutorId, string $date, string $start, string $end, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM tutorias WHERE id_tutor = :id_tutor AND fecha = :fecha AND estado IN ('pendiente', 'confirmada') AND hora_inicio < :hora_fin AND hora_fin > :hora_inicio";
        $params = [
            'id_tutor' => $tutorId,
            'fecha' => $date,
            'hora_inicio' => $start,
            'hora_fin' => $end,
        ];
        if ($excludeId !== null) {
            $sql .= ' AND id_tutoria <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $statement = Database::connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);

        return (bool) $statement->fetchColumn();
    }

    public function hasStudentConflict(int $studentId, string $date, string $start, string $end, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM tutorias WHERE id_estudiante = :id_estudiante AND fecha = :fecha AND estado IN ('pendiente', 'confirmada') AND hora_inicio < :hora_fin AND hora_fin > :hora_inicio";
        $params = [
            'id_estudiante' => $studentId,
            'fecha' => $date,
            'hora_inicio' => $start,
            'hora_fin' => $end,
        ];
        if ($excludeId !== null) {
            $sql .= ' AND id_tutoria <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $statement = Database::connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);

        return (bool) $statement->fetchColumn();
    }

    public function changeStatus(int $id, string $status, string $role, int $userId, string $currentState, string $reason = ''): bool
    {
        $sql = 'UPDATE tutorias SET estado = :estado';
        $params = ['estado' => $status, 'id_tutoria' => $id, 'estado_actual' => $currentState];
        if ($status === 'cancelada') {
            $sql .= ', motivo_cancelacion = :motivo_cancelacion, fecha_cancelacion = CURRENT_TIMESTAMP, usuario_cancelacion = :usuario_cancelacion';
            $params['motivo_cancelacion'] = $reason;
            $params['usuario_cancelacion'] = $userId;
        }
        $sql .= ' WHERE id_tutoria = :id_tutoria AND estado = :estado_actual';
        if ($role === 'tutor') {
            $sql .= ' AND id_tutor = (SELECT id_tutor FROM tutores WHERE id_usuario = :id_usuario)';
            $params['id_usuario'] = $userId;
        } elseif ($role === 'estudiante') {
            $sql .= ' AND id_estudiante = (SELECT id_estudiante FROM estudiantes WHERE id_usuario = :id_usuario)';
            $params['id_usuario'] = $userId;
        }
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount() > 0;
    }
}
