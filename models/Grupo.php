<?php

declare(strict_types=1);

final class Grupo
{
    private const DIAS = [
        'Lunes' => 1, 'Martes' => 2, 'Miercoles' => 3,
        'Jueves' => 4, 'Viernes' => 5, 'Sabado' => 6,
    ];

    /** Grupos de una campana con datos de tutor, materia y aula (para admin/supervision). */
    public function allByPeriodo(int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT g.id_grupo, g.dia_semana, g.hora_inicio, g.hora_fin, g.modalidad,
                    g.cupo_max, g.cupo_ocupado, g.estado,
                    m.nombre_materia, a.nombre AS aula,
                    CONCAT(u.nombre, ' ', u.apellido) AS tutor
             FROM grupos_tutoria g
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN aulas a ON a.id_aula = g.id_aula
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE g.id_periodo = :id_periodo
             ORDER BY m.nombre_materia, FIELD(g.dia_semana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'), g.hora_inicio"
        );
        $statement->execute(['id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    /** Grupos donde un tutor imparte, dentro de una campana. */
    public function forTutor(int $tutorId, int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT g.id_grupo, g.dia_semana, g.hora_inicio, g.hora_fin, g.modalidad,
                    g.cupo_max, g.cupo_ocupado, g.estado, m.nombre_materia, a.nombre AS aula
             FROM grupos_tutoria g
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN aulas a ON a.id_aula = g.id_aula
             WHERE g.id_tutor = :id_tutor AND g.id_periodo = :id_periodo
             ORDER BY FIELD(g.dia_semana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'), g.hora_inicio"
        );
        $statement->execute(['id_tutor' => $tutorId, 'id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    /** Candidatos: grupos de una materia con cupo libre, ordenados para llenar los mas ocupados primero. */
    public function candidatesForMatter(int $periodoId, int $matterId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT id_grupo, id_tutor, id_aula, dia_semana, hora_inicio, hora_fin, cupo_max, cupo_ocupado, estado
             FROM grupos_tutoria
             WHERE id_periodo = :id_periodo AND id_materia = :id_materia
               AND estado IN ('formacion','confirmado')
               AND cupo_ocupado < cupo_max
             ORDER BY cupo_ocupado DESC, id_grupo ASC"
        );
        $statement->execute(['id_periodo' => $periodoId, 'id_materia' => $matterId]);

        return $statement->fetchAll();
    }

    /**
     * Bloques de disponibilidad de tutores habilitados para una materia,
     * que aun NO tienen un grupo del mismo tutor solapado en la campana.
     */
    public function availabilityForMatter(int $periodoId, int $matterId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT t.id_tutor, d.dia_semana, d.hora_inicio, d.hora_fin
             FROM tutor_materia tm
             INNER JOIN tutores t ON t.id_tutor = tm.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND u.estado = 'activo'
             INNER JOIN disponibilidad_tutor d ON d.id_tutor = t.id_tutor
             WHERE tm.id_materia = :id_materia
             ORDER BY d.dia_semana, d.hora_inicio"
        );
        $statement->execute(['id_materia' => $matterId]);

        return $statement->fetchAll();
    }

    /** Conflicto de horario del tutor con otro grupo de la misma campana (mismo dia, rango solapado). */
    public function tutorHasConflict(int $tutorId, string $dia, string $horaInicio, string $horaFin, int $periodoId): bool
    {
        $statement = Database::connection()->prepare(
            "SELECT 1 FROM grupos_tutoria
             WHERE id_tutor = :id_tutor AND id_periodo = :id_periodo AND dia_semana = :dia
               AND estado <> 'cancelado' AND hora_inicio < :hora_fin AND hora_fin > :hora_inicio
             LIMIT 1"
        );
        $statement->execute([
            'id_tutor' => $tutorId, 'id_periodo' => $periodoId, 'dia' => $dia,
            'hora_fin' => $horaFin, 'hora_inicio' => $horaInicio,
        ]);

        return (bool) $statement->fetchColumn();
    }

    /** Conflicto de reserva del aula (mismo dia, rango solapado) en la campana. */
    public function aulaHasConflict(int $aulaId, string $dia, string $horaInicio, string $horaFin, int $periodoId): bool
    {
        $statement = Database::connection()->prepare(
            "SELECT 1 FROM grupos_tutoria
             WHERE id_aula = :id_aula AND id_periodo = :id_periodo AND dia_semana = :dia
               AND estado <> 'cancelado' AND hora_inicio < :hora_fin AND hora_fin > :hora_inicio
             LIMIT 1"
        );
        $statement->execute([
            'id_aula' => $aulaId, 'id_periodo' => $periodoId, 'dia' => $dia,
            'hora_fin' => $horaFin, 'hora_inicio' => $horaInicio,
        ]);

        return (bool) $statement->fetchColumn();
    }

    /** Aula libre para un bloque, priorizando capacidad ajustada; devuelve la fila o null. */
    public function findFreeAula(string $dia, string $horaInicio, string $horaFin, int $periodoId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT a.id_aula, a.tipo, a.capacidad
             FROM aulas a
             WHERE a.estado = 'activa'
               AND NOT EXISTS (
                   SELECT 1 FROM grupos_tutoria g
                   WHERE g.id_aula = a.id_aula AND g.id_periodo = :id_periodo AND g.dia_semana = :dia
                     AND g.estado <> 'cancelado' AND g.hora_inicio < :hora_fin AND g.hora_fin > :hora_inicio
               )
             ORDER BY a.capacidad ASC
             LIMIT 1"
        );
        $statement->execute([
            'id_periodo' => $periodoId, 'dia' => $dia,
            'hora_fin' => $horaFin, 'hora_inicio' => $horaInicio,
        ]);
        $aula = $statement->fetch();

        return $aula ?: null;
    }

    /** Crea un grupo y devuelve su id. */
    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO grupos_tutoria (id_periodo, id_materia, id_tutor, id_aula, modalidad, dia_semana, hora_inicio, hora_fin, cupo_max, cupo_ocupado, estado)
             VALUES (:id_periodo, :id_materia, :id_tutor, :id_aula, :modalidad, :dia_semana, :hora_inicio, :hora_fin, :cupo_max, 0, :estado)'
        );
        $statement->execute($data);

        return (int) Database::connection()->lastInsertId();
    }

    /** Genera las sesiones semanales del grupo entre las fechas de la campana. */
    public function generateSessions(int $grupoId, string $dia, string $fechaInicio, string $fechaFin): int
    {
        $target = self::DIAS[$dia] ?? null;
        if ($target === null) {
            return 0;
        }
        $start = new DateTimeImmutable($fechaInicio);
        $end = new DateTimeImmutable($fechaFin);
        // Avanzar hasta el primer dia de la semana que coincide.
        $cursor = $start;
        while ((int) $cursor->format('N') !== $target) {
            $cursor = $cursor->modify('+1 day');
            if ($cursor > $end) {
                return 0;
            }
        }
        $statement = Database::connection()->prepare(
            'INSERT IGNORE INTO sesiones_tutoria (id_grupo, fecha) VALUES (:id_grupo, :fecha)'
        );
        $count = 0;
        for ($date = $cursor; $date <= $end; $date = $date->modify('+7 days')) {
            $statement->execute(['id_grupo' => $grupoId, 'fecha' => $date->format('Y-m-d')]);
            $count++;
        }

        return $count;
    }

    /** Datos basicos de un grupo (materia y periodo incluidos). */
    public function findBasic(int $grupoId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT g.id_grupo, g.id_periodo, g.id_materia, g.estado, m.nombre_materia
             FROM grupos_tutoria g INNER JOIN materias m ON m.id_materia = g.id_materia
             WHERE g.id_grupo = :id LIMIT 1"
        );
        $statement->execute(['id' => $grupoId]);
        $grupo = $statement->fetch();

        return $grupo ?: null;
    }

    /** Marca el grupo como cancelado y cancela sus sesiones programadas. */
    public function cancel(int $grupoId, string $motivo): void
    {
        Database::connection()->prepare(
            "UPDATE grupos_tutoria SET estado = 'cancelado', motivo_estado = :motivo, fecha_estado = NOW() WHERE id_grupo = :id"
        )->execute(['id' => $grupoId, 'motivo' => mb_substr($motivo, 0, 300)]);

        Database::connection()->prepare(
            "UPDATE sesiones_tutoria SET estado = 'cancelada' WHERE id_grupo = :id AND estado = 'programada'"
        )->execute(['id' => $grupoId]);
    }

    /** Bloquea la fila del grupo dentro de una transaccion para controlar el cupo. */
    public function lockForEnroll(int $grupoId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_grupo, id_periodo, cupo_max, cupo_ocupado, estado FROM grupos_tutoria WHERE id_grupo = :id FOR UPDATE'
        );
        $statement->execute(['id' => $grupoId]);
        $grupo = $statement->fetch();

        return $grupo ?: null;
    }

    /** Suma un inscrito y confirma el grupo si alcanza el cupo minimo. */
    public function registerEnrollment(int $grupoId, int $cupoMin): void
    {
        Database::connection()->prepare(
            'UPDATE grupos_tutoria SET cupo_ocupado = cupo_ocupado + 1 WHERE id_grupo = :id'
        )->execute(['id' => $grupoId]);

        Database::connection()->prepare(
            "UPDATE grupos_tutoria SET estado = 'confirmado', fecha_estado = NOW()
             WHERE id_grupo = :id AND estado = 'formacion' AND cupo_ocupado >= :cupo_min"
        )->execute(['id' => $grupoId, 'cupo_min' => $cupoMin]);
    }
}
