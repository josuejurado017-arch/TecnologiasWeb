<?php

declare(strict_types=1);

/**
 * Horarios del tutor por materia (turnos + patron semanal, franjas de sabado,
 * modalidad, cupo). Es la UNICA fuente de horarios del motor de asignacion: la
 * antigua disponibilidad_tutor global quedo obsoleta (db/025_horarios_por_materia.sql).
 * Los dias salen de un patron semanal por materia, no de una matriz turno x dia
 * (db/026_tutor_materia_patron.sql).
 */
final class TutorMateriaConfig
{
    public const TURNOS = [
        'Manana' => ['label' => 'Mañana', 'inicio' => '07:30:00', 'fin' => '10:30:00'],
        'Mediodia' => ['label' => 'Mediodía', 'inicio' => '11:00:00', 'fin' => '14:00:00'],
        'Tarde' => ['label' => 'Tarde', 'inicio' => '15:00:00', 'fin' => '18:00:00'],
        'Noche' => ['label' => 'Noche', 'inicio' => '19:00:00', 'fin' => '22:00:00'],
    ];

    /**
     * Patrones semanales ofrecidos al tutor. El sabado no aparece aqui a proposito:
     * tiene franjas propias que no coinciden con los rangos de turno, asi que se
     * sigue configurando aparte (tutor_materia_sabado). 'uno' es el unico que
     * necesita precisar el dia (columna patron_dia).
     */
    public const PATRONES = [
        'lmv' => ['label' => 'Lunes, miércoles y viernes', 'dias' => ['Lunes', 'Miercoles', 'Viernes']],
        'mj' => ['label' => 'Martes y jueves', 'dias' => ['Martes', 'Jueves']],
        'diario' => ['label' => 'Todos los días (lunes a viernes)', 'dias' => ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes']],
        'uno' => ['label' => 'Un día por semana', 'dias' => []],
    ];

    public const FRANJAS_SABADO = [
        '08:00-10:00' => ['inicio' => '08:00:00', 'fin' => '10:00:00'],
        '10:00-12:00' => ['inicio' => '10:00:00', 'fin' => '12:00:00'],
        '14:00-16:00' => ['inicio' => '14:00:00', 'fin' => '16:00:00'],
    ];

    public const CUPOS_RECOMENDADOS = [10, 15, 20, 25];

    public const DIAS_HABILES = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'];

    public function isValidTurno(string $turno): bool
    {
        return isset(self::TURNOS[$turno]);
    }

    public function isValidPatron(string $patron): bool
    {
        return isset(self::PATRONES[$patron]);
    }

    /** Dias habiles que implica un patron. 'uno' depende del dia que eligio el tutor. */
    public function diasDePatron(string $patron, ?string $patronDia): array
    {
        if ($patron === 'uno') {
            return $patronDia !== null && $this->isValidDia($patronDia) ? [$patronDia] : [];
        }

        return self::PATRONES[$patron]['dias'] ?? [];
    }

    /**
     * Ventanas (dia + rango horario) que declara una configuracion: producto del
     * patron por los turnos elegidos, mas las franjas de sabado si aplica.
     */
    public function ventanas(string $patron, ?string $patronDia, array $turnos, bool $disponibleSabados, array $franjas): array
    {
        $dias = $this->diasDePatron($patron, $patronDia);
        $ventanas = [];

        foreach ($turnos as $turno) {
            $rango = self::TURNOS[$turno] ?? null;
            if ($rango === null) {
                continue;
            }
            foreach ($dias as $dia) {
                $ventanas[] = ['dia_semana' => $dia, 'hora_inicio' => $rango['inicio'], 'hora_fin' => $rango['fin']];
            }
        }

        if ($disponibleSabados) {
            foreach ($franjas as $franja) {
                $rango = self::FRANJAS_SABADO[$franja] ?? null;
                if ($rango === null) {
                    continue;
                }
                $ventanas[] = ['dia_semana' => 'Sabado', 'hora_inicio' => $rango['inicio'], 'hora_fin' => $rango['fin']];
            }
        }

        return $ventanas;
    }

    public function isValidFranja(string $franja): bool
    {
        return isset(self::FRANJAS_SABADO[$franja]);
    }

    public function isValidDia(string $dia): bool
    {
        return in_array($dia, self::DIAS_HABILES, true);
    }

    /** Configuracion completa de un tutor para una materia, para precargar el formulario de edicion. */
    public function find(int $tutorId, int $materiaId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT modalidad, patron, patron_dia, disponible_sabados, cupo_recomendado FROM tutor_materia_config WHERE id_tutor = :id_tutor AND id_materia = :id_materia LIMIT 1'
        );
        $statement->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId]);
        $config = $statement->fetch();
        if (!$config) {
            return null;
        }

        $config['turnos'] = $this->turnos($tutorId, $materiaId);
        $config['sabados_franjas'] = $this->franjasSabado($tutorId, $materiaId);

        return $config;
    }

    /** Turnos configurados para una materia: ['Manana', 'Tarde']. */
    public function turnos(int $tutorId, int $materiaId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT turno FROM tutor_materia_turno WHERE id_tutor = :id_tutor AND id_materia = :id_materia
             ORDER BY FIELD(turno, \'Manana\', \'Mediodia\', \'Tarde\', \'Noche\')'
        );
        $statement->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId]);

        return array_map(static fn (array $row): string => $row['turno'], $statement->fetchAll());
    }

    public function franjasSabado(int $tutorId, int $materiaId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT franja FROM tutor_materia_sabado WHERE id_tutor = :id_tutor AND id_materia = :id_materia ORDER BY franja'
        );
        $statement->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId]);

        return array_map(static fn (array $row): string => $row['franja'], $statement->fetchAll());
    }

    /**
     * Resumen de configuracion de TODAS las materias de un tutor, indexado por id_materia.
     * Usado por "Mis materias" para el indicador "Sin configurar" sin consultas N+1.
     */
    public function summaryForTutor(int $tutorId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_materia, modalidad, patron, patron_dia, disponible_sabados, cupo_recomendado FROM tutor_materia_config WHERE id_tutor = :id_tutor'
        );
        $statement->execute(['id_tutor' => $tutorId]);
        $configs = $statement->fetchAll();
        if (!$configs) {
            return [];
        }

        $turnosStatement = Database::connection()->prepare(
            'SELECT id_materia, turno FROM tutor_materia_turno WHERE id_tutor = :id_tutor
             ORDER BY FIELD(turno, \'Manana\', \'Mediodia\', \'Tarde\', \'Noche\')'
        );
        $turnosStatement->execute(['id_tutor' => $tutorId]);
        $turnosByMateria = [];
        foreach ($turnosStatement->fetchAll() as $row) {
            $turnosByMateria[(int) $row['id_materia']][] = $row['turno'];
        }

        $sabadosStatement = Database::connection()->prepare(
            'SELECT id_materia, franja FROM tutor_materia_sabado WHERE id_tutor = :id_tutor ORDER BY franja'
        );
        $sabadosStatement->execute(['id_tutor' => $tutorId]);
        $franjasByMateria = [];
        foreach ($sabadosStatement->fetchAll() as $row) {
            $franjasByMateria[(int) $row['id_materia']][] = $row['franja'];
        }

        $summary = [];
        foreach ($configs as $config) {
            $materiaId = (int) $config['id_materia'];
            $turnos = $turnosByMateria[$materiaId] ?? [];
            $disponibleSabados = (int) $config['disponible_sabados'] === 1;
            $franjas = $franjasByMateria[$materiaId] ?? [];

            $summary[$materiaId] = [
                'modalidad' => $config['modalidad'],
                'patron' => $config['patron'],
                'patron_dia' => $config['patron_dia'],
                'disponible_sabados' => $disponibleSabados,
                'cupo_recomendado' => $config['cupo_recomendado'] !== null ? (int) $config['cupo_recomendado'] : null,
                'turnos' => $turnos,
                'sabados_franjas' => $franjas,
                'configured' => $turnos !== [] || ($disponibleSabados && $franjas !== []),
            ];
        }

        return $summary;
    }

    /** Guarda (crea o reemplaza) la configuracion de un tutor para una materia. */
    public function save(int $tutorId, int $materiaId, array $data): void
    {
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $statement = $connection->prepare(
                'INSERT INTO tutor_materia_config (id_tutor, id_materia, modalidad, patron, patron_dia, disponible_sabados, cupo_recomendado)
                 VALUES (:id_tutor, :id_materia, :modalidad, :patron, :patron_dia, :disponible_sabados, :cupo_recomendado)
                 ON DUPLICATE KEY UPDATE modalidad = VALUES(modalidad), patron = VALUES(patron), patron_dia = VALUES(patron_dia),
                 disponible_sabados = VALUES(disponible_sabados), cupo_recomendado = VALUES(cupo_recomendado)'
            );
            $statement->execute([
                'id_tutor' => $tutorId,
                'id_materia' => $materiaId,
                'modalidad' => $data['modalidad'],
                'patron' => $data['patron'],
                'patron_dia' => $data['patron_dia'],
                'disponible_sabados' => $data['disponible_sabados'] ? 1 : 0,
                'cupo_recomendado' => $data['cupo_recomendado'],
            ]);

            $connection->prepare(
                'DELETE FROM tutor_materia_turno WHERE id_tutor = :id_tutor AND id_materia = :id_materia'
            )->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId]);

            if ($data['turnos']) {
                $insertTurno = $connection->prepare(
                    'INSERT INTO tutor_materia_turno (id_tutor, id_materia, turno) VALUES (:id_tutor, :id_materia, :turno)'
                );
                foreach ($data['turnos'] as $turno) {
                    $insertTurno->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId, 'turno' => $turno]);
                }
            }

            $connection->prepare(
                'DELETE FROM tutor_materia_sabado WHERE id_tutor = :id_tutor AND id_materia = :id_materia'
            )->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId]);

            if ($data['sabados_franjas']) {
                $insertFranja = $connection->prepare(
                    'INSERT INTO tutor_materia_sabado (id_tutor, id_materia, franja) VALUES (:id_tutor, :id_materia, :franja)'
                );
                foreach ($data['sabados_franjas'] as $franja) {
                    $insertFranja->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId, 'franja' => $franja]);
                }
            }

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * Horarios de todos los tutores activos configurados para una materia, indexados
     * por id_tutor. Un tutor sin configuracion para la materia no aparece: no ofrece
     * horarios para ella.
     */
    public function preferencesForMatter(int $materiaId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT c.id_tutor, c.modalidad, c.patron, c.patron_dia, c.disponible_sabados, c.cupo_recomendado
             FROM tutor_materia_config c
             INNER JOIN tutores t ON t.id_tutor = c.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND u.estado = 'activo'
             WHERE c.id_materia = :id_materia"
        );
        $statement->execute(['id_materia' => $materiaId]);
        $configs = $statement->fetchAll();
        if (!$configs) {
            return [];
        }

        $turnosStatement = Database::connection()->prepare(
            'SELECT id_tutor, turno FROM tutor_materia_turno WHERE id_materia = :id_materia'
        );
        $turnosStatement->execute(['id_materia' => $materiaId]);
        $turnosByTutor = [];
        foreach ($turnosStatement->fetchAll() as $row) {
            $turnosByTutor[(int) $row['id_tutor']][] = $row['turno'];
        }

        $franjasStatement = Database::connection()->prepare(
            'SELECT id_tutor, franja FROM tutor_materia_sabado WHERE id_materia = :id_materia'
        );
        $franjasStatement->execute(['id_materia' => $materiaId]);
        $franjasByTutor = [];
        foreach ($franjasStatement->fetchAll() as $row) {
            $franjasByTutor[(int) $row['id_tutor']][] = $row['franja'];
        }

        $preferences = [];
        foreach ($configs as $config) {
            $tutorId = (int) $config['id_tutor'];
            $ventanas = $this->ventanas(
                $config['patron'],
                $config['patron_dia'],
                $turnosByTutor[$tutorId] ?? [],
                (int) $config['disponible_sabados'] === 1,
                $franjasByTutor[$tutorId] ?? []
            );

            if (!$ventanas) {
                // Configuracion incompleta (no deberia ocurrir via save()): sin horarios.
                continue;
            }

            $preferences[$tutorId] = [
                'modalidad' => $config['modalidad'],
                'cupo_recomendado' => $config['cupo_recomendado'] !== null ? (int) $config['cupo_recomendado'] : null,
                'ventanas' => $ventanas,
            ];
        }

        return $preferences;
    }

    /**
     * Bloques semanales que el motor puede usar para crear grupos de una materia:
     * una fila por (tutor, dia, turno/franja) con la modalidad y cupo que el tutor
     * declaro para esa materia.
     */
    public function slotsForMatter(int $materiaId): array
    {
        $slots = [];
        foreach ($this->preferencesForMatter($materiaId) as $tutorId => $preference) {
            foreach ($preference['ventanas'] as $ventana) {
                $slots[] = [
                    'id_tutor' => $tutorId,
                    'dia_semana' => $ventana['dia_semana'],
                    'hora_inicio' => $ventana['hora_inicio'],
                    'hora_fin' => $ventana['hora_fin'],
                    'modalidad_preferida' => $preference['modalidad'],
                    'cupo_recomendado' => $preference['cupo_recomendado'],
                ];
            }
        }

        return $slots;
    }

    /**
     * Condicion SQL "la materia tm.id_materia tiene horarios configurados por tm.id_tutor"
     * (misma regla que preferencesForMatter/summaryForTutor). $alias es el alias de
     * tutor_materia en la consulta que la usa.
     */
    public static function sqlMateriaConfigurada(string $alias = 'tm'): string
    {
        return "(EXISTS (SELECT 1 FROM tutor_materia_turno tmt
                     WHERE tmt.id_tutor = {$alias}.id_tutor AND tmt.id_materia = {$alias}.id_materia)
                 OR EXISTS (SELECT 1 FROM tutor_materia_config tmc
                     INNER JOIN tutor_materia_sabado tms ON tms.id_tutor = tmc.id_tutor AND tms.id_materia = tmc.id_materia
                     WHERE tmc.id_tutor = {$alias}.id_tutor AND tmc.id_materia = {$alias}.id_materia AND tmc.disponible_sabados = 1))";
    }

    /**
     * Tutores activos con cuantas de sus materias tienen horarios configurados, para la
     * supervision del administrador. Los que no pueden recibir grupos (ninguna materia
     * configurada) aparecen primero.
     */
    public function coverageByTutor(): array
    {
        $configurada = self::sqlMateriaConfigurada('tm');
        $rows = Database::connection()->query(
            "SELECT t.id_tutor, CONCAT(u.nombre, ' ', u.apellido) AS tutor,
                    COUNT(tm.id_materia) AS materias,
                    COALESCE(SUM(CASE WHEN tm.id_materia IS NOT NULL AND {$configurada} THEN 1 ELSE 0 END), 0) AS configuradas
             FROM tutores t
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND u.estado = 'activo'
             LEFT JOIN tutor_materia tm ON tm.id_tutor = t.id_tutor
             GROUP BY t.id_tutor, u.nombre, u.apellido
             ORDER BY u.apellido, u.nombre"
        )->fetchAll();

        $coverage = array_map(static fn (array $row): array => [
            'id_tutor' => (int) $row['id_tutor'],
            'tutor' => $row['tutor'],
            'materias' => (int) $row['materias'],
            'configuradas' => (int) $row['configuradas'],
        ], $rows);
        // usort es estable: conserva el orden alfabetico dentro de cada grupo.
        usort($coverage, static fn (array $a, array $b): int => ($a['configuradas'] > 0) <=> ($b['configuradas'] > 0));

        return $coverage;
    }

    /** Tutores activos sin ninguna materia con horarios configurados: no pueden recibir grupos. */
    public function countTutorsWithoutSchedule(): int
    {
        $configurada = self::sqlMateriaConfigurada('tm');

        return (int) Database::connection()->query(
            "SELECT COUNT(*) FROM tutores t
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND u.estado = 'activo'
             WHERE NOT EXISTS (SELECT 1 FROM tutor_materia tm WHERE tm.id_tutor = t.id_tutor AND {$configurada})"
        )->fetchColumn();
    }
}
