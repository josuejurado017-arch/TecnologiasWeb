<?php

declare(strict_types=1);

final class HistorialGrupo
{
    /** Registra un evento del grupo (creado, confirmado, cancelado, reprogramado, cambio_*). */
    /** Registra un evento del grupo y devuelve su id (sirve de clave unica para los avisos del evento). */
    public function log(PDO $pdo, int $grupoId, string $tipo, ?string $estadoAnterior, ?string $estadoNuevo, ?int $userId, ?string $motivo): int
    {
        $statement = $pdo->prepare(
            'INSERT INTO historial_grupo (id_grupo, tipo_evento, estado_anterior, estado_nuevo, id_usuario, motivo)
             VALUES (:id_grupo, :tipo, :estado_anterior, :estado_nuevo, :id_usuario, :motivo)'
        );
        $statement->execute([
            'id_grupo' => $grupoId,
            'tipo' => $tipo,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'id_usuario' => $userId,
            'motivo' => $motivo,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Historial de un grupo con el responsable. */
    public function forGroup(int $grupoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT h.tipo_evento, h.estado_anterior, h.estado_nuevo, h.motivo, h.fecha_evento,
                    CASE WHEN u.id_usuario IS NULL THEN 'Sistema' ELSE CONCAT(u.nombre, ' ', u.apellido) END AS responsable
             FROM historial_grupo h
             LEFT JOIN usuarios u ON u.id_usuario = h.id_usuario
             WHERE h.id_grupo = :id_grupo
             ORDER BY h.fecha_evento DESC, h.id_historial DESC"
        );
        $statement->execute(['id_grupo' => $grupoId]);

        return $statement->fetchAll();
    }
}
