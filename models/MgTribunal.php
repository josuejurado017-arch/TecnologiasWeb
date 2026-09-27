<?php

declare(strict_types=1);

/**
 * Tribunales de Modalidades de Grado (db/047, HU-028): docentes evaluadores por
 * expediente y etapa. Cada puesto (orden 1, 2...) tiene un solo vigente; un cambio
 * lo marca 'reemplazado' y crea otro.
 */
final class MgTribunal
{
    public const ETAPAS = ['mg1' => 'MG1', 'mg2' => 'MG2'];

    /** Tribunales vigentes de la etapa, indexados por orden. */
    public function vigentes(int $expedienteId, string $etapa): array
    {
        $statement = Database::connection()->prepare(
            "SELECT tr.*, CONCAT(u.nombre, ' ', u.apellido) AS docente, u.id_usuario AS id_usuario_docente
             FROM tribunales_mg tr INNER JOIN tutores t ON t.id_tutor = tr.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE tr.id_expediente = :id AND tr.etapa = :etapa AND tr.estado = 'vigente' ORDER BY tr.orden"
        );
        $statement->execute(['id' => $expedienteId, 'etapa' => $etapa]);
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $rows[(int) $row['orden']] = $row;
        }

        return $rows;
    }

    public function historial(int $expedienteId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT tr.*, CONCAT(u.nombre, ' ', u.apellido) AS docente, CONCAT(ur.nombre, ' ', ur.apellido) AS registrado
             FROM tribunales_mg tr INNER JOIN tutores t ON t.id_tutor = tr.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             LEFT JOIN usuarios ur ON ur.id_usuario = tr.registrado_por
             WHERE tr.id_expediente = :id ORDER BY tr.etapa, tr.orden, tr.fecha_registro DESC, tr.id_tribunal DESC"
        );
        $statement->execute(['id' => $expedienteId]);

        return $statement->fetchAll();
    }

    /**
     * Pone a $tutorId en el puesto. Si el puesto tenia otro vigente, lo marca
     * reemplazado. Debe correr en transaccion. Devuelve [anterior|null, id nuevo|null]
     * (null si el mismo docente ya ocupaba el puesto).
     */
    public function asignarPuesto(PDO $pdo, int $expedienteId, string $etapa, int $orden, int $tutorId, string $fecha, ?string $motivo, int $userId): array
    {
        $statement = $pdo->prepare(
            "SELECT * FROM tribunales_mg WHERE id_expediente = :id AND etapa = :etapa AND orden = :orden AND estado = 'vigente' FOR UPDATE"
        );
        $statement->execute(['id' => $expedienteId, 'etapa' => $etapa, 'orden' => $orden]);
        $anterior = $statement->fetch() ?: null;
        if ($anterior !== null && (int) $anterior['id_tutor'] === $tutorId) {
            return [$anterior, null];
        }
        if ($anterior !== null) {
            $pdo->prepare("UPDATE tribunales_mg SET estado = 'reemplazado', motivo_cambio = :m WHERE id_tribunal = :id")
                ->execute(['m' => $motivo, 'id' => $anterior['id_tribunal']]);
        }
        $pdo->prepare(
            'INSERT INTO tribunales_mg (id_expediente, etapa, id_tutor, orden, fecha_asignacion, registrado_por)
             VALUES (:e, :etapa, :t, :o, :f, :u)'
        )->execute(['e' => $expedienteId, 'etapa' => $etapa, 't' => $tutorId, 'o' => $orden, 'f' => $fecha, 'u' => $userId]);

        return [$anterior, (int) $pdo->lastInsertId()];
    }
}
