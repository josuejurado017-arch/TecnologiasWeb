<?php

declare(strict_types=1);

final class Notificacion
{
    public function unreadForUser(int $userId, int $limit = 8): array
    {
        $limit = max(1, min($limit, 30));
        $statement = Database::connection()->prepare(
            'SELECT id_notificacion, id_usuario, id_tutoria, tipo, titulo AS title, mensaje AS text, url AS href, leida, fecha_creacion, CASE WHEN tipo = \'tutoria_cancelada\' THEN \'danger\' WHEN tipo IN (\'tutoria_confirmada\', \'tutoria_proxima\') THEN \'success\' WHEN tipo = \'evaluacion_pendiente\' THEN \'info\' ELSE \'warning\' END AS tone FROM notificaciones WHERE id_usuario = :id_usuario AND leida = 0 ORDER BY fecha_creacion DESC, id_notificacion DESC LIMIT ' . $limit
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

    /** Notifica al estudiante que fue asignado a un grupo (modelo institucional). */
    public function notifyAssignment(PDO $pdo, int $grupoId, int $studentUserId, string $materia, string $detalle): void
    {
        $this->create(
            $pdo,
            $studentUserId,
            null,
            'asignacion_grupo',
            'Nueva asignacion de tutoria',
            'Fuiste asignado a un grupo de ' . $materia . '. ' . $detalle,
            '/mis-tutorias/',
            'asignacion_grupo:' . $grupoId . ':' . $studentUserId
        );
    }

    /** Notifica a todos los inscritos activos que su grupo quedo confirmado. */
    public function notifyGroupConfirmed(PDO $pdo, int $grupoId, string $materia): void
    {
        $statement = $pdo->prepare(
            "SELECT e.id_usuario FROM inscripciones i
             INNER JOIN estudiantes e ON e.id_estudiante = i.id_estudiante
             WHERE i.id_grupo = :id_grupo AND i.estado = 'inscrito'"
        );
        $statement->execute(['id_grupo' => $grupoId]);
        foreach ($statement->fetchAll() as $row) {
            $userId = (int) $row['id_usuario'];
            $this->create(
                $pdo,
                $userId,
                null,
                'grupo_confirmado',
                'Grupo de tutoria confirmado',
                'Tu grupo de ' . $materia . ' alcanzo el cupo minimo y quedo confirmado.',
                '/mis-tutorias/',
                'grupo_confirmado:' . $grupoId . ':' . $userId
            );
        }
    }

    /** Avisa a los inscritos de un grupo que pueden evaluar (tras una sesion realizada). */
    public function notifyEvaluationPending(PDO $pdo, int $grupoId, string $materia): void
    {
        $statement = $pdo->prepare(
            "SELECT e.id_usuario FROM inscripciones i
             INNER JOIN estudiantes e ON e.id_estudiante = i.id_estudiante
             WHERE i.id_grupo = :id_grupo AND i.estado = 'inscrito'
               AND NOT EXISTS (SELECT 1 FROM evaluaciones_grupo eg WHERE eg.id_inscripcion = i.id_inscripcion)"
        );
        $statement->execute(['id_grupo' => $grupoId]);
        foreach ($statement->fetchAll() as $row) {
            $userId = (int) $row['id_usuario'];
            $this->create(
                $pdo,
                $userId,
                null,
                'evaluacion_pendiente',
                'Evaluacion pendiente',
                'Ya puedes evaluar tu tutoria de ' . $materia . '.',
                '/mis-evaluaciones/',
                'evaluacion_pendiente_grupo:' . $grupoId . ':' . $userId
            );
        }
    }

    /** Notifica a un estudiante que su grupo fue cancelado. */
    public function notifyGroupCancelled(PDO $pdo, int $grupoId, int $studentUserId, string $materia): void
    {
        $this->create(
            $pdo,
            $studentUserId,
            null,
            'grupo_cancelado',
            'Grupo de tutoria cancelado',
            'Tu grupo de ' . $materia . ' fue cancelado. Quedaste en lista de espera: el sistema te reasignara automaticamente cuando haya un grupo compatible.',
            '/mis-tutorias/',
            'grupo_cancelado:' . $grupoId . ':' . $studentUserId
        );
    }

    /**
     * Notifica a un estudiante en espera que el reproceso automatico le consiguio grupo.
     * La clave incluye la fecha para permitir un aviso nuevo si vuelve a esperar en el mismo periodo.
     */
    public function notifyDemandAttended(PDO $pdo, int $studentUserId, int $materiaId, string $materia, string $detalle): void
    {
        $this->create(
            $pdo,
            $studentUserId,
            null,
            'demanda_atendida',
            'Se abrio un grupo para tu materia en espera',
            'Ya tienes grupo de ' . $materia . '. ' . $detalle,
            '/mis-tutorias/',
            'demanda_atendida:' . $materiaId . ':' . $studentUserId . ':' . date('Ymd')
        );
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
