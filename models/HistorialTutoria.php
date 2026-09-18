<?php

declare(strict_types=1);

final class HistorialTutoria
{
    public function record(PDO $pdo, array $data): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO historial_tutorias (id_tutoria, id_usuario, tipo_evento, estado_anterior, estado_nuevo, motivo, datos_anteriores, datos_nuevos) VALUES (:id_tutoria, :id_usuario, :tipo_evento, :estado_anterior, :estado_nuevo, :motivo, :datos_anteriores, :datos_nuevos)'
        );
        $statement->execute([
            'id_tutoria' => $data['id_tutoria'],
            'id_usuario' => $data['id_usuario'] ?? null,
            'tipo_evento' => $data['tipo_evento'],
            'estado_anterior' => $data['estado_anterior'] ?? null,
            'estado_nuevo' => $data['estado_nuevo'] ?? null,
            'motivo' => ($data['motivo'] ?? '') !== '' ? $data['motivo'] : null,
            'datos_anteriores' => $this->encode($data['datos_anteriores'] ?? null),
            'datos_nuevos' => $this->encode($data['datos_nuevos'] ?? null),
        ]);
    }

    public function forViewer(int $tutoriaId, string $role, int $userId): array
    {
        $sql = <<<'SQL'
            SELECT h.id_historial, h.id_tutoria, h.fecha_cambio, h.tipo_evento,
                   h.estado_anterior, h.estado_nuevo, h.motivo,
                   h.datos_anteriores, h.datos_nuevos,
                   COALESCE(CONCAT(u.nombre, ' ', u.apellido), 'Sistema') AS responsable
            FROM historial_tutorias h
            LEFT JOIN usuarios u ON u.id_usuario = h.id_usuario
            INNER JOIN tutorias t ON t.id_tutoria = h.id_tutoria
            INNER JOIN estudiantes e ON e.id_estudiante = t.id_estudiante
            INNER JOIN tutores tr ON tr.id_tutor = t.id_tutor
            SQL;
        $params = ['id_tutoria' => $tutoriaId];
        $where = ['h.id_tutoria = :id_tutoria'];

        if ($role === 'estudiante') {
            $where[] = 'e.id_usuario = :viewer_id';
            $params['viewer_id'] = $userId;
        } elseif ($role === 'tutor') {
            $where[] = 'tr.id_usuario = :viewer_id';
            $params['viewer_id'] = $userId;
        }

        $statement = Database::connection()->prepare($sql . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY h.fecha_cambio DESC, h.id_historial DESC');
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function recentForUser(int $userId, string $role = '', int $limit = 8): array
    {
        $limit = max(1, min($limit, 30));
        $sql = <<<'SQL'
            SELECT h.id_historial, h.id_tutoria, h.fecha_cambio, h.tipo_evento,
                   h.estado_anterior, h.estado_nuevo, h.motivo,
                   COALESCE(CONCAT(u.nombre, ' ', u.apellido), 'Sistema') AS responsable,
                   m.nombre_materia
            FROM historial_tutorias h
            LEFT JOIN usuarios u ON u.id_usuario = h.id_usuario
            INNER JOIN tutorias t ON t.id_tutoria = h.id_tutoria
            INNER JOIN estudiantes e ON e.id_estudiante = t.id_estudiante
            INNER JOIN tutores tr ON tr.id_tutor = t.id_tutor
            INNER JOIN materias m ON m.id_materia = t.id_materia
        SQL;
        $params = [];
        if ($role !== 'administrador') {
            $sql .= ' WHERE e.id_usuario = :user_id OR tr.id_usuario = :user_id_again';
            $params = ['user_id' => $userId, 'user_id_again' => $userId];
        }
        $sql .= <<<'SQL'
            ORDER BY h.fecha_cambio DESC, h.id_historial DESC
        SQL;
        $statement = Database::connection()->prepare($sql . ' LIMIT ' . $limit);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function encode($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }
}
