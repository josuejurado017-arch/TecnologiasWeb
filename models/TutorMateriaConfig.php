<?php

declare(strict_types=1);

/**
 * Preferencias academicas del tutor por materia (Fase 1: capa de filtro sobre
 * disponibilidad_tutor, no la reemplaza). Ver db/020_tutor_materia_preferencias.sql.
 */
final class TutorMateriaConfig
{
    public const TURNOS = [
        'Manana' => ['label' => 'Mañana', 'inicio' => '07:30:00', 'fin' => '10:30:00'],
        'Mediodia' => ['label' => 'Mediodía', 'inicio' => '11:00:00', 'fin' => '14:00:00'],
        'Tarde' => ['label' => 'Tarde', 'inicio' => '15:00:00', 'fin' => '18:00:00'],
        'Noche' => ['label' => 'Noche', 'inicio' => '19:00:00', 'fin' => '22:00:00'],
    ];

    public const FRANJAS_SABADO = [
        '08:00-10:00' => ['inicio' => '08:00:00', 'fin' => '10:00:00'],
        '10:00-12:00' => ['inicio' => '10:00:00', 'fin' => '12:00:00'],
        '14:00-16:00' => ['inicio' => '14:00:00', 'fin' => '16:00:00'],
    ];

    public const CUPOS_RECOMENDADOS = [10, 15, 20, 25];

    private const DIAS_HABILES = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'];

    public function isValidTurno(string $turno): bool
    {
        return isset(self::TURNOS[$turno]);
    }

    public function isValidFranja(string $franja): bool
    {
        return isset(self::FRANJAS_SABADO[$franja]);
    }

    /** Configuracion completa de un tutor para una materia, para precargar el formulario de edicion. */
    public function find(int $tutorId, int $materiaId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT modalidad, disponible_sabados, cupo_recomendado FROM tutor_materia_config WHERE id_tutor = :id_tutor AND id_materia = :id_materia LIMIT 1'
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

    public function turnos(int $tutorId, int $materiaId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT turno FROM tutor_materia_turno WHERE id_tutor = :id_tutor AND id_materia = :id_materia ORDER BY turno'
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
            'SELECT id_materia, modalidad, disponible_sabados, cupo_recomendado FROM tutor_materia_config WHERE id_tutor = :id_tutor'
        );
        $statement->execute(['id_tutor' => $tutorId]);
        $configs = $statement->fetchAll();
        if (!$configs) {
            return [];
        }

        $turnosStatement = Database::connection()->prepare(
            'SELECT id_materia, turno FROM tutor_materia_turno WHERE id_tutor = :id_tutor ORDER BY turno'
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
                'INSERT INTO tutor_materia_config (id_tutor, id_materia, modalidad, disponible_sabados, cupo_recomendado)
                 VALUES (:id_tutor, :id_materia, :modalidad, :disponible_sabados, :cupo_recomendado)
                 ON DUPLICATE KEY UPDATE modalidad = VALUES(modalidad), disponible_sabados = VALUES(disponible_sabados), cupo_recomendado = VALUES(cupo_recomendado)'
            );
            $statement->execute([
                'id_tutor' => $tutorId,
                'id_materia' => $materiaId,
                'modalidad' => $data['modalidad'],
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
     * Preferencias de todos los tutores configurados para una materia, indexadas por id_tutor.
     * Un tutor SIN fila aqui no aparece: el motor debe tratarlo como sin restriccion.
     */
    public function preferencesForMatter(int $materiaId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_tutor, modalidad, disponible_sabados, cupo_recomendado FROM tutor_materia_config WHERE id_materia = :id_materia'
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
            $ventanas = [];

            foreach ($turnosByTutor[$tutorId] ?? [] as $turno) {
                $rango = self::TURNOS[$turno] ?? null;
                if ($rango === null) {
                    continue;
                }
                foreach (self::DIAS_HABILES as $dia) {
                    $ventanas[] = ['dia_semana' => $dia, 'hora_inicio' => $rango['inicio'], 'hora_fin' => $rango['fin']];
                }
            }

            if ((int) $config['disponible_sabados'] === 1) {
                foreach ($franjasByTutor[$tutorId] ?? [] as $franja) {
                    $rango = self::FRANJAS_SABADO[$franja] ?? null;
                    if ($rango === null) {
                        continue;
                    }
                    $ventanas[] = ['dia_semana' => 'Sabado', 'hora_inicio' => $rango['inicio'], 'hora_fin' => $rango['fin']];
                }
            }

            if (!$ventanas) {
                // Configuracion incompleta (no deberia ocurrir via save(), pero por
                // seguridad no se restringe nada si no hay ventanas declaradas).
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
     * Cruza los bloques reales de disponibilidad (rawSlots, como los devuelve
     * Grupo::availabilityForMatter) con las preferencias por materia. Un tutor sin
     * preferencia se devuelve intacto (retrocompatibilidad). Con preferencia, se
     * intersectan los rangos horarios: nunca se amplia mas alla de la disponibilidad
     * real del tutor ni del turno/franja que declaro para esa materia.
     */
    public function expandSlots(array $rawSlots, array $preferences): array
    {
        $expanded = [];
        foreach ($rawSlots as $slot) {
            $tutorId = (int) $slot['id_tutor'];
            $preference = $preferences[$tutorId] ?? null;

            if ($preference === null) {
                $expanded[] = $slot + ['modalidad_preferida' => null, 'cupo_recomendado' => null];
                continue;
            }

            foreach ($preference['ventanas'] as $ventana) {
                if ($ventana['dia_semana'] !== $slot['dia_semana']) {
                    continue;
                }
                $inicio = max($slot['hora_inicio'], $ventana['hora_inicio']);
                $fin = min($slot['hora_fin'], $ventana['hora_fin']);
                if ($inicio >= $fin) {
                    continue;
                }
                $expanded[] = [
                    'id_tutor' => $tutorId,
                    'dia_semana' => $slot['dia_semana'],
                    'hora_inicio' => $inicio,
                    'hora_fin' => $fin,
                    'modalidad_preferida' => $preference['modalidad'],
                    'cupo_recomendado' => $preference['cupo_recomendado'],
                ];
            }
        }

        return $expanded;
    }
}
