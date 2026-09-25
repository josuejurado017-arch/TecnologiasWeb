<?php

declare(strict_types=1);

final class Sesion
{
    /** Sesiones de un grupo con su estado. */
    public function forGroup(int $grupoId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_sesion, fecha, estado FROM sesiones_tutoria WHERE id_grupo = :id_grupo ORDER BY fecha'
        );
        $statement->execute(['id_grupo' => $grupoId]);

        return $statement->fetchAll();
    }

    /** Detalle de una sesion con datos del grupo y del tutor propietario (para autorizacion). */
    public function detail(int $sesionId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT s.id_sesion, s.fecha, s.estado, g.id_grupo, g.id_tutor, g.dia_semana,
                    g.hora_inicio, g.hora_fin, m.nombre_materia, t.id_usuario AS tutor_usuario,
                    p.estado AS estado_periodo
             FROM sesiones_tutoria s
             INNER JOIN grupos_tutoria g ON g.id_grupo = s.id_grupo
             INNER JOIN periodos p ON p.id_periodo = g.id_periodo
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             WHERE s.id_sesion = :id_sesion LIMIT 1"
        );
        $statement->execute(['id_sesion' => $sesionId]);
        $sesion = $statement->fetch();

        return $sesion ?: null;
    }

    public function markRealizada(int $sesionId): void
    {
        Database::connection()->prepare(
            "UPDATE sesiones_tutoria SET estado = 'realizada' WHERE id_sesion = :id_sesion"
        )->execute(['id_sesion' => $sesionId]);
    }
}
