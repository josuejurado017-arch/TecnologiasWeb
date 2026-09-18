<?php

declare(strict_types=1);

final class Notificacion
{
    public function unreadForUser(int $userId, int $limit = 8): array
    {
        $limit = max(1, min($limit, 30));
        $statement = Database::connection()->prepare(
            'SELECT id_notificacion, id_usuario, id_tutoria, tipo, titulo, mensaje AS text, url AS href, leida, fecha_creacion, CASE WHEN tipo = \'tutoria_cancelada\' THEN \'danger\' WHEN tipo IN (\'tutoria_confirmada\', \'tutoria_proxima\') THEN \'success\' WHEN tipo = \'evaluacion_pendiente\' THEN \'info\' ELSE \'warning\' END AS tone FROM notificaciones WHERE id_usuario = :id_usuario AND leida = 0 ORDER BY fecha_creacion DESC, id_notificacion DESC LIMIT ' . $limit
        );
        $statement->execute(['id_usuario' => $userId]);

        return $statement->fetchAll();
    }

    public function countUnread(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notificaciones WHERE id_usuario = :id_usuario AND leida = 0'
        );
        $statement->execute(['id_usuario' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function create(PDO $pdo, int $userId, ?int $tutoriaId, string $type, string $title, string $message, ?string $url, string $eventKey): void
    {
        $statement = $pdo->prepare(
            'INSERT IGNORE INTO notificaciones (id_usuario, id_tutoria, tipo, titulo, mensaje, url, clave_evento) VALUES (:id_usuario, :id_tutoria, :tipo, :titulo, :mensaje, :url, :clave_evento)'
        );
        $statement->execute([
            'id_usuario' => $userId,
            'id_tutoria' => $tutoriaId,
            'tipo' => $type,
            'titulo' => $title,
            'mensaje' => $message,
            'url' => $url,
            'clave_evento' => $eventKey,
        ]);
    }

    public function forTutoriaEvent(PDO $pdo, int $tutoriaId, string $type): void
    {
        $statement = $pdo->prepare(
            'SELECT t.id_tutoria, t.fecha, t.hora_inicio, t.estado, e.id_usuario AS estudiante_usuario, tr.id_usuario AS tutor_usuario, m.nombre_materia FROM tutorias t INNER JOIN estudiantes e ON e.id_estudiante = t.id_estudiante INNER JOIN tutores tr ON tr.id_tutor = t.id_tutor INNER JOIN materias m ON m.id_materia = t.id_materia WHERE t.id_tutoria = :id_tutoria LIMIT 1'
        );
        $statement->execute(['id_tutoria' => $tutoriaId]);
        $tutoria = $statement->fetch();
        if (!$tutoria) {
            return;
        }

        $recipients = match ($type) {
            'tutoria_confirmada', 'evaluacion_pendiente' => [(int) $tutoria['estudiante_usuario']],
            default => [(int) $tutoria['estudiante_usuario'], (int) $tutoria['tutor_usuario']],
        };
        [$title, $message] = match ($type) {
            'tutoria_creada' => ['Nueva solicitud de tutoria', 'Se registro una solicitud para ' . $tutoria['nombre_materia'] . '.'],
            'tutoria_confirmada' => ['Tutoria confirmada', 'Tu tutoria de ' . $tutoria['nombre_materia'] . ' fue confirmada.'],
            'tutoria_cancelada' => ['Tutoria cancelada', 'La tutoria de ' . $tutoria['nombre_materia'] . ' fue cancelada.'],
            'tutoria_reprogramada' => ['Tutoria reprogramada', 'La tutoria de ' . $tutoria['nombre_materia'] . ' cambio de fecha u horario.'],
            'evaluacion_pendiente' => ['Evaluacion pendiente', 'Puedes evaluar tu tutoria de ' . $tutoria['nombre_materia'] . '.'],
            default => ['Tutoria actualizada', 'La tutoria de ' . $tutoria['nombre_materia'] . ' fue actualizada.'],
        };

        foreach (array_unique($recipients) as $recipient) {
            $this->create($pdo, $recipient, $tutoriaId, $type, $title, $message, '/tutorias/', $type . ':' . $tutoriaId . ':' . $recipient);
        }
    }

    public function createUpcomingForUser(int $userId): void
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'SELECT t.id_tutoria, t.fecha, t.hora_inicio, m.nombre_materia FROM tutorias t INNER JOIN estudiantes e ON e.id_estudiante = t.id_estudiante INNER JOIN tutores tr ON tr.id_tutor = t.id_tutor INNER JOIN materias m ON m.id_materia = t.id_materia WHERE (e.id_usuario = :student_user OR tr.id_usuario = :tutor_user) AND t.estado IN (\'pendiente\', \'confirmada\') AND TIMESTAMP(t.fecha, t.hora_inicio) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 48 HOUR)'
        );
        $statement->execute(['student_user' => $userId, 'tutor_user' => $userId]);
        foreach ($statement->fetchAll() as $tutoria) {
            $this->create($pdo, $userId, (int) $tutoria['id_tutoria'], 'tutoria_proxima', 'Tutoria proxima', 'Tienes una tutoria de ' . $tutoria['nombre_materia'] . ' en las proximas 48 horas.', '/tutorias/', 'tutoria_proxima:' . $tutoria['id_tutoria'] . ':' . $userId);
        }
    }

    public function createEvaluationPendingForUser(int $userId): void
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'SELECT t.id_tutoria, m.nombre_materia FROM tutorias t INNER JOIN estudiantes e ON e.id_estudiante = t.id_estudiante INNER JOIN materias m ON m.id_materia = t.id_materia LEFT JOIN evaluaciones_tutoria ev ON ev.id_tutoria = t.id_tutoria WHERE e.id_usuario = :id_usuario AND t.estado = \'realizada\' AND ev.id_evaluacion IS NULL'
        );
        $statement->execute(['id_usuario' => $userId]);
        foreach ($statement->fetchAll() as $tutoria) {
            $this->create($pdo, $userId, (int) $tutoria['id_tutoria'], 'evaluacion_pendiente', 'Evaluacion pendiente', 'Puedes evaluar tu tutoria de ' . $tutoria['nombre_materia'] . '.', '/evaluaciones/', 'evaluacion_pendiente:' . $tutoria['id_tutoria'] . ':' . $userId);
        }
    }

    public function markRead(int $id, int $userId): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE notificaciones SET leida = 1, fecha_lectura = CURRENT_TIMESTAMP WHERE id_notificacion = :id_notificacion AND id_usuario = :id_usuario AND leida = 0'
        );
        $statement->execute(['id_notificacion' => $id, 'id_usuario' => $userId]);

        return $statement->rowCount() > 0;
    }
}
