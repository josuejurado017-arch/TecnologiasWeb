<?php

declare(strict_types=1);

final class AsistenciaTutoria
{
    public function find(int $tutoriaId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT a.id_asistencia, a.id_tutoria, a.estado_asistencia, a.minutos_retraso, a.observaciones, a.fecha_registro, a.id_usuario_registro, COALESCE(CONCAT(u.nombre, \' \', u.apellido), \'Sistema\') AS registrado_por FROM asistencias_tutorias a LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario_registro WHERE a.id_tutoria = :id_tutoria LIMIT 1'
        );
        $statement->execute(['id_tutoria' => $tutoriaId]);
        $attendance = $statement->fetch();

        return $attendance ?: null;
    }

    public function save(PDO $pdo, int $tutoriaId, array $data, int $userId): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO asistencias_tutorias (id_tutoria, estado_asistencia, minutos_retraso, observaciones, id_usuario_registro) VALUES (:id_tutoria, :estado_asistencia, :minutos_retraso, :observaciones, :id_usuario_registro) ON DUPLICATE KEY UPDATE estado_asistencia = VALUES(estado_asistencia), minutos_retraso = VALUES(minutos_retraso), observaciones = VALUES(observaciones), fecha_registro = CURRENT_TIMESTAMP, id_usuario_registro = VALUES(id_usuario_registro)'
        );
        $statement->execute([
            'id_tutoria' => $tutoriaId,
            'estado_asistencia' => $data['estado_asistencia'],
            'minutos_retraso' => $data['estado_asistencia'] === 'retraso' ? $data['minutos_retraso'] : null,
            'observaciones' => $data['observaciones'] !== '' ? $data['observaciones'] : null,
            'id_usuario_registro' => $userId,
        ]);
    }
}
