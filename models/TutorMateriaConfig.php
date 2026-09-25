<?php

declare(strict_types=1);

/**
 * Oferta del tutor por materia: turnos, modalidad y cupo recomendado. Es la UNICA
 * fuente de horarios del motor de asignacion (db/025_horarios_por_materia.sql).
 *
 * Frecuencia (db/033): el tutor ya no elige dias ni patron semanal. Declara en que
 * turnos puede atender; los dias de cada grupo los fija la demanda al crearlo
 * (Grupo::diasPorDemanda) y, en grupos reducidos, la coordinacion al revisarlo.
 *
 * Modalidad (db/029): materias.modalidad_requerida manda sobre la preferencia del
 * tutor. Una configuracion incompatible (tutor "virtual" en materia "solo
 * presencial") no ofrece horarios: cuenta como no configurada.
 *
 * Aprobacion (db/032): el motor y el catalogo del estudiante solo usan ofertas
 * aprobadas por la coordinacion.
 */
final class TutorMateriaConfig
{
    public const TURNOS = [
        'Manana' => ['label' => 'Mañana', 'inicio' => '07:30:00', 'fin' => '10:30:00'],
        'Mediodia' => ['label' => 'Mediodía', 'inicio' => '11:00:00', 'fin' => '14:00:00'],
        'Tarde' => ['label' => 'Tarde', 'inicio' => '15:00:00', 'fin' => '18:00:00'],
        'Noche' => ['label' => 'Noche', 'inicio' => '19:00:00', 'fin' => '22:00:00'],
    ];

    public const CUPOS_RECOMENDADOS = [10, 15, 20, 25];

    public const ESTADOS = ['pendiente', 'aprobado', 'rechazado', 'propuesta'];

    public const MAX_MATERIAS = 2;

    private function periodoActivo(): ?int
    {
        $periodo = (new Periodo())->activa();
        return $periodo ? (int) $periodo['id_periodo'] : null;
    }

    /** Serializa decisiones de oferta del mismo tutor durante una campaña. */
    private function bloquearTutor(PDO $pdo, int $tutorId): void
    {
        $pdo->prepare('SELECT id_tutor FROM tutores WHERE id_tutor = :id FOR UPDATE')->execute(['id' => $tutorId]);
    }

    private function bloquearPeriodo(PDO $pdo, int $periodoId): void
    {
        $stmt = $pdo->prepare('SELECT estado FROM periodos WHERE id_periodo = :p FOR UPDATE');
        $stmt->execute(['p' => $periodoId]);
        if ($stmt->fetchColumn() !== 'activa') {
            throw new RuntimeException('El período ya no está activo. Renueva tu oferta en el siguiente período.');
        }
    }

    private function validarCarga(PDO $pdo, int $tutorId, int $materiaId, int $periodoId, array $turnos, bool $ampliacion = false): ?string
    {
        if ($turnos === []) { return 'La oferta necesita al menos un turno.'; }
        $count = $pdo->prepare("SELECT COUNT(*) FROM tutor_materia_config WHERE id_periodo = :p AND id_tutor = :t AND id_materia <> :m AND estado IN ('pendiente','aprobado','propuesta')");
        $count->execute(['p' => $periodoId, 't' => $tutorId, 'm' => $materiaId]);
        if ((int) $count->fetchColumn() >= self::MAX_MATERIAS) {
            return 'El tutor ya tiene dos materias asignadas en este período.';
        }
        $grupos = $pdo->prepare("SELECT COUNT(DISTINCT id_materia) FROM grupos_tutoria WHERE id_periodo = :p AND id_tutor = :t AND estado <> 'cancelado' AND id_materia <> :m");
        $grupos->execute(['p' => $periodoId, 't' => $tutorId, 'm' => $materiaId]);
        if ((int) $grupos->fetchColumn() >= self::MAX_MATERIAS) {
            return 'El tutor ya dicta dos materias en este período.';
        }
        $cupo = $pdo->prepare("SELECT COUNT(*) AS total, SUM(id_materia = :m) AS misma FROM grupos_tutoria
            WHERE id_periodo = :p AND id_tutor = :t AND estado <> 'cancelado'");
        $cupo->execute(['m' => $materiaId, 'p' => $periodoId, 't' => $tutorId]);
        $ocupacion = $cupo->fetch();
        if ((int) $ocupacion['total'] >= 2 && (int) $ocupacion['misma'] === 0) {
            return 'El tutor ya tiene dos grupos en este período.';
        }
        foreach ($turnos as $turno) {
            $ocupado = $pdo->prepare("SELECT 1 FROM tutor_materia_turno tt INNER JOIN tutor_materia_config c
                ON c.id_tutor = tt.id_tutor AND c.id_materia = tt.id_materia AND c.id_periodo = tt.id_periodo
                WHERE tt.id_periodo = :p AND tt.id_tutor = :t AND tt.id_materia <> :m AND tt.turno = :turno
                  AND c.estado IN ('aprobado','propuesta') LIMIT 1");
            $ocupado->execute(['p' => $periodoId, 't' => $tutorId, 'm' => $materiaId, 'turno' => $turno]);
            if ($ocupado->fetchColumn()) {
                return 'El tutor ya tiene otra materia en el turno ' . self::TURNOS[$turno]['label'] . '.';
            }
            $otro = $pdo->prepare("SELECT c.estado FROM tutor_materia_turno tt INNER JOIN tutor_materia_config c
                ON c.id_tutor = tt.id_tutor AND c.id_materia = tt.id_materia AND c.id_periodo = tt.id_periodo
                WHERE tt.id_periodo = :p AND tt.id_materia = :m AND tt.id_tutor <> :t AND tt.turno = :turno
                  AND c.estado IN ('aprobado','propuesta') LIMIT 1");
            $otro->execute(['p' => $periodoId, 'm' => $materiaId, 't' => $tutorId, 'turno' => $turno]);
            $estadoOtro = $otro->fetchColumn();
            if ($estadoOtro && (!$ampliacion || $estadoOtro === 'propuesta' || !$this->necesitaAmpliacion($pdo, $periodoId, $materiaId, $turno))) {
                return 'El turno ' . self::TURNOS[$turno]['label'] . ' ya está cubierto para esta materia. Solo la coordinación puede ampliar su capacidad cuando exista demanda.';
            }
        }
        return null;
    }

    private function necesitaAmpliacion(PDO $pdo, int $periodoId, int $materiaId, string $turno): bool
    {
        $query = $pdo->prepare("SELECT 1 FROM grupos_tutoria g WHERE g.id_periodo = :p AND g.id_materia = :m
            AND g.hora_inicio = :hora AND g.estado IN ('por_aprobar','formacion','confirmado','en_curso')
            AND g.cupo_ocupado >= g.cupo_max AND EXISTS
              (SELECT 1 FROM demanda_tutoria d WHERE d.id_periodo = g.id_periodo AND d.id_materia = g.id_materia AND d.estado = 'pendiente') LIMIT 1");
        $query->execute(['p' => $periodoId, 'm' => $materiaId, 'hora' => self::TURNOS[$turno]['inicio']]);
        return (bool) $query->fetchColumn();
    }

    /**
     * Modalidad final de un grupo nuevo:
     *   1. la materia exige una modalidad -> esa (si el tutor la acepta);
     *   2. el tutor eligio presencial o virtual -> esa;
     *   3. el tutor acepta ambas -> la regla del periodo (periodos.modalidad_ambas).
     * Devuelve null si la materia y el tutor son incompatibles.
     */
    public static function resolverModalidad(string $requerida, string $tutor, string $ambasPeriodo): ?string
    {
        if ($requerida === 'presencial' || $requerida === 'virtual') {
            return ($tutor === 'ambas' || $tutor === $requerida) ? $requerida : null;
        }
        if ($tutor === 'presencial' || $tutor === 'virtual') {
            return $tutor;
        }

        return $ambasPeriodo === 'presencial' ? 'presencial' : 'virtual';
    }

    /** Modalidades que un tutor puede elegir para una materia segun su modalidad requerida. */
    public static function modalidadesPermitidas(string $requerida): array
    {
        return match ($requerida) {
            'presencial' => ['presencial'],
            'virtual' => ['virtual'],
            default => ['presencial', 'virtual', 'ambas'],
        };
    }

    /** Condicion SQL equivalente a resolverModalidad() !== null ($config: tutor_materia_config, $materia: materias). */
    public static function sqlModalidadCompatible(string $config = 'c', string $materia = 'm'): string
    {
        return "({$materia}.modalidad_requerida = 'libre' OR {$config}.modalidad = 'ambas' OR {$config}.modalidad = {$materia}.modalidad_requerida)";
    }

    public function isValidTurno(string $turno): bool
    {
        return isset(self::TURNOS[$turno]);
    }

    public function materiasOcupadas(int $tutorId): int
    {
        $periodoId = $this->periodoActivo();
        if ($periodoId === null) { return 0; }
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM tutor_materia_config WHERE id_tutor = :t AND id_periodo = :p AND estado IN ('pendiente','aprobado','propuesta')");
        $stmt->execute(['t' => $tutorId, 'p' => $periodoId]);
        return (int) $stmt->fetchColumn();
    }

    /** Cobertura de los cuatro turnos para mostrar a quien ofrece una materia. */
    public function coberturaMateria(int $materiaId): array
    {
        $stmt = Database::connection()->prepare("SELECT tt.turno, CONCAT(u.nombre, ' ', u.apellido) AS tutor
            FROM tutor_materia_turno tt INNER JOIN tutor_materia_config c
              ON c.id_tutor = tt.id_tutor AND c.id_materia = tt.id_materia AND c.id_periodo = tt.id_periodo
            INNER JOIN tutores t ON t.id_tutor = tt.id_tutor INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
            WHERE tt.id_materia = :m AND tt.id_periodo = (SELECT id_periodo FROM periodos WHERE estado = 'activa' LIMIT 1)
              AND c.estado = 'aprobado' ORDER BY tt.turno, u.apellido");
        $stmt->execute(['m' => $materiaId]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) { $result[$row['turno']][] = $row['tutor']; }
        return $result;
    }

    /** Turno ('Manana', 'Tarde'...) que empieza a esa hora; null para horarios anteriores a los turnos fijos. */
    public static function turnoDeHora(string $horaInicio): ?string
    {
        foreach (self::TURNOS as $clave => $turno) {
            if ($turno['inicio'] === $horaInicio) {
                return $clave;
            }
        }

        return null;
    }

    /**
     * Tutores habilitados con oferta APROBADA de la materia en ese turno y en una
     * modalidad que sirve al grupo: los que pueden tomar un grupo ya formado (db/039).
     */
    public function tutoresConTurno(int $materiaId, string $turno, string $modalidadGrupo): array
    {
        $habilitado = self::sqlTutorHabilitado();
        $statement = Database::connection()->prepare(
            "SELECT t.id_tutor, CONCAT(u.nombre, ' ', u.apellido) AS tutor, t.especialidad, c.modalidad
             FROM tutor_materia_config c
             INNER JOIN tutor_materia_turno tt ON tt.id_tutor = c.id_tutor AND tt.id_materia = c.id_materia AND tt.id_periodo = c.id_periodo AND tt.turno = :turno
             INNER JOIN tutores t ON t.id_tutor = c.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND {$habilitado}
             WHERE c.id_periodo = (SELECT id_periodo FROM periodos WHERE estado = 'activa' LIMIT 1)
               AND c.id_materia = :id_materia AND c.estado = 'aprobado' AND c.modalidad IN ('ambas', :modalidad)
             ORDER BY u.apellido, u.nombre"
        );
        $statement->execute(['turno' => $turno, 'id_materia' => $materiaId, 'modalidad' => $modalidadGrupo]);

        return $statement->fetchAll();
    }

    /** Configuracion completa de un tutor para una materia, para precargar el formulario de edicion. */
    public function find(int $tutorId, int $materiaId): ?array
    {
        $statement = Database::connection()->prepare(
             "SELECT modalidad, cupo_recomendado, estado, motivo_rechazo
              FROM tutor_materia_config WHERE id_tutor = :id_tutor AND id_materia = :id_materia
                AND id_periodo = (SELECT id_periodo FROM periodos WHERE estado = 'activa' LIMIT 1) LIMIT 1"
        );
        $statement->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId]);
        $config = $statement->fetch();
        if (!$config) {
            return null;
        }

        $config['turnos'] = $this->turnos($tutorId, $materiaId);

        return $config;
    }

    /** Turnos configurados para una materia: ['Manana', 'Tarde']. */
    public function turnos(int $tutorId, int $materiaId): array
    {
        $statement = Database::connection()->prepare(
             'SELECT turno FROM tutor_materia_turno WHERE id_tutor = :id_tutor AND id_materia = :id_materia
                AND id_periodo = (SELECT id_periodo FROM periodos WHERE estado = \'activa\' LIMIT 1)
             ORDER BY FIELD(turno, \'Manana\', \'Mediodia\', \'Tarde\', \'Noche\')'
        );
        $statement->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId]);

        return array_map(static fn (array $row): string => $row['turno'], $statement->fetchAll());
    }

    /**
     * Resumen de configuracion de TODAS las materias de un tutor, indexado por id_materia.
     * Usado por "Mis materias" para el indicador "Sin configurar" sin consultas N+1.
     */
    public function summaryForTutor(int $tutorId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.id_materia, c.modalidad, c.cupo_recomendado, c.estado, c.motivo_rechazo,
                    m.modalidad_requerida, ' . self::sqlModalidadCompatible('c', 'm') . ' AS compatible
             FROM tutor_materia_config c INNER JOIN materias m ON m.id_materia = c.id_materia
              WHERE c.id_tutor = :id_tutor AND c.id_periodo = (SELECT id_periodo FROM periodos WHERE estado = \'activa\' LIMIT 1)'
        );
        $statement->execute(['id_tutor' => $tutorId]);
        $configs = $statement->fetchAll();
        if (!$configs) {
            return [];
        }

        $turnosStatement = Database::connection()->prepare(
             'SELECT id_materia, turno FROM tutor_materia_turno WHERE id_tutor = :id_tutor
                AND id_periodo = (SELECT id_periodo FROM periodos WHERE estado = \'activa\' LIMIT 1)
             ORDER BY FIELD(turno, \'Manana\', \'Mediodia\', \'Tarde\', \'Noche\')'
        );
        $turnosStatement->execute(['id_tutor' => $tutorId]);
        $turnosByMateria = [];
        foreach ($turnosStatement->fetchAll() as $row) {
            $turnosByMateria[(int) $row['id_materia']][] = $row['turno'];
        }

        $summary = [];
        foreach ($configs as $config) {
            $materiaId = (int) $config['id_materia'];
            $turnos = $turnosByMateria[$materiaId] ?? [];
            $compatible = (int) $config['compatible'] === 1;

            $summary[$materiaId] = [
                'modalidad' => $config['modalidad'],
                'cupo_recomendado' => $config['cupo_recomendado'] !== null ? (int) $config['cupo_recomendado'] : null,
                'turnos' => $turnos,
                'modalidad_incompatible' => !$compatible,
                'configured' => $compatible && $turnos !== [],
                'estado' => $config['estado'],
                'motivo_rechazo' => $config['motivo_rechazo'],
            ];
        }

        return $summary;
    }

    /**
     * Guarda (crea o reemplaza) la configuracion de un tutor para una materia.
     * Cualquier guardado (nuevo o editado) vuelve a dejar la oferta en 'pendiente':
     * lo que el coordinador aprobo ya no es exactamente lo que hay, asi que el
     * motor deja de usarla hasta que la revise de nuevo.
     * Devuelve el id de historial de la transicion a 'pendiente', o null si ya
     * estaba pendiente (nada que renotificar).
     */
    public function save(int $tutorId, int $materiaId, array $data): ?int
    {
        $periodoId = $this->periodoActivo();
        if ($periodoId === null) {
            throw new RuntimeException('No hay un período activo para renovar ofertas.');
        }
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $this->bloquearPeriodo($connection, $periodoId);
            $this->bloquearTutor($connection, $tutorId);
            $previo = $connection->prepare('SELECT estado FROM tutor_materia_config WHERE id_tutor = :id_tutor AND id_materia = :id_materia AND id_periodo = :p LIMIT 1');
            $previo->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId, 'p' => $periodoId]);
            $estadoAnterior = $previo->fetchColumn();
            $estadoAnterior = $estadoAnterior === false ? null : (string) $estadoAnterior;

            $carga = $this->validarCarga($connection, $tutorId, $materiaId, $periodoId, $data['turnos']);
            if ($carga !== null) {
                throw new RuntimeException($carga);
            }

            $connection->prepare(
                'INSERT INTO tutor_materia_config (id_tutor, id_materia, id_periodo, modalidad, cupo_recomendado, estado, motivo_rechazo, id_usuario_revision, fecha_revision)
                  VALUES (:id_tutor, :id_materia, :p, :modalidad, :cupo_recomendado, \'pendiente\', NULL, NULL, NULL)
                 ON DUPLICATE KEY UPDATE modalidad = VALUES(modalidad), cupo_recomendado = VALUES(cupo_recomendado),
                 estado = \'pendiente\', motivo_rechazo = NULL, id_usuario_revision = NULL, fecha_revision = NULL'
            )->execute([
                'id_tutor' => $tutorId,
                'id_materia' => $materiaId,
                'p' => $periodoId,
                'modalidad' => $data['modalidad'],
                'cupo_recomendado' => $data['cupo_recomendado'],
            ]);

            $connection->prepare(
                'DELETE FROM tutor_materia_turno WHERE id_tutor = :id_tutor AND id_materia = :id_materia AND id_periodo = :p'
            )->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId, 'p' => $periodoId]);

            $insertTurno = $connection->prepare(
                'INSERT INTO tutor_materia_turno (id_tutor, id_materia, id_periodo, turno) VALUES (:id_tutor, :id_materia, :p, :turno)'
            );
            foreach ($data['turnos'] as $turno) {
                $insertTurno->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId, 'p' => $periodoId, 'turno' => $turno]);
            }

            $historialId = null;
            if ($estadoAnterior !== 'pendiente') {
                $connection->prepare(
                    'INSERT INTO tutor_materia_historial (id_tutor, id_materia, id_periodo, estado_anterior, estado_nuevo, motivo, id_usuario_accion)
                      VALUES (:id_tutor, :id_materia, :p, :antes, \'pendiente\', :motivo, NULL)'
                )->execute([
                    'id_tutor' => $tutorId,
                    'id_materia' => $materiaId,
                    'p' => $periodoId,
                    'antes' => $estadoAnterior,
                    'motivo' => $estadoAnterior === null ? 'Oferta nueva del tutor.' : 'El tutor editó su configuración; vuelve a revisión.',
                ]);
                $historialId = (int) $connection->lastInsertId();
            }

            $connection->commit();

            return $historialId;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * Ofertas aprobadas de una materia, una fila por (tutor, turno): los bloques
     * horarios que el motor puede usar para crear grupos. No incluye dias: los
     * decide la demanda (Grupo::diasPorDemanda). Solo cuenta la oferta aprobada por
     * la coordinacion (db/032): es el unico filtro que usa el motor.
     */
    public function slotsForMatter(int $materiaId): array
    {
        $habilitado = self::sqlTutorHabilitado();
        $compatible = self::sqlModalidadCompatible('c', 'm');
        $statement = Database::connection()->prepare(
            "SELECT c.id_tutor, c.modalidad, c.cupo_recomendado, m.modalidad_requerida, tt.turno
             FROM tutor_materia_config c
             INNER JOIN materias m ON m.id_materia = c.id_materia
             INNER JOIN tutores t ON t.id_tutor = c.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND {$habilitado}
             INNER JOIN tutor_materia_turno tt ON tt.id_tutor = c.id_tutor AND tt.id_materia = c.id_materia AND tt.id_periodo = c.id_periodo
              WHERE c.id_periodo = (SELECT id_periodo FROM periodos WHERE estado = 'activa' LIMIT 1)
                AND c.id_materia = :id_materia AND c.estado = 'aprobado' AND {$compatible}
             ORDER BY c.id_tutor, FIELD(tt.turno, 'Manana', 'Mediodia', 'Tarde', 'Noche')"
        );
        $statement->execute(['id_materia' => $materiaId]);

        $slots = [];
        foreach ($statement->fetchAll() as $row) {
            $rango = self::TURNOS[$row['turno']] ?? null;
            if ($rango === null) {
                continue;
            }
            $slots[] = [
                'id_tutor' => (int) $row['id_tutor'],
                'hora_inicio' => $rango['inicio'],
                'hora_fin' => $rango['fin'],
                'modalidad_preferida' => $row['modalidad'],
                'modalidad_requerida' => $row['modalidad_requerida'],
                'cupo_recomendado' => $row['cupo_recomendado'] !== null ? (int) $row['cupo_recomendado'] : null,
            ];
        }

        return $slots;
    }

    /** Hay al menos un tutor con oferta aprobada para la materia. */
    public function hasApprovedOffer(int $materiaId): bool
    {
        return $this->slotsForMatter($materiaId) !== [];
    }

    /**
     * Condicion SQL "el tutor puede recibir grupos": cuenta activa (puede iniciar
     * sesion) y habilitacion docente aprobada por el administrador (db/028). Es la
     * unica regla de elegibilidad del motor; un tutor pendiente inicia sesion y
     * configura sus materias, pero no aparece aqui.
     */
    public static function sqlTutorHabilitado(string $tutorAlias = 't', string $userAlias = 'u'): string
    {
        return "({$userAlias}.estado = 'activo' AND {$tutorAlias}.estado_docente = 'aprobado')";
    }

    /**
     * Condicion SQL "la materia tm.id_materia tiene turnos configurados por tm.id_tutor
     * en una modalidad que la materia admite" (misma regla que summaryForTutor).
     * $alias es el alias de tutor_materia en la consulta que la usa.
     */
    public static function sqlMateriaConfigurada(string $alias = 'tm'): string
    {
        $compatible = self::sqlModalidadCompatible('tmcm', 'mm');

        return "(EXISTS (SELECT 1 FROM tutor_materia_config tmcm
                     INNER JOIN materias mm ON mm.id_materia = tmcm.id_materia
                      WHERE tmcm.id_tutor = {$alias}.id_tutor AND tmcm.id_materia = {$alias}.id_materia
                        AND tmcm.id_periodo = (SELECT id_periodo FROM periodos WHERE estado = 'activa' LIMIT 1) AND {$compatible})
                  AND EXISTS (SELECT 1 FROM tutor_materia_turno tmt
                      WHERE tmt.id_tutor = {$alias}.id_tutor AND tmt.id_materia = {$alias}.id_materia
                        AND tmt.id_periodo = (SELECT id_periodo FROM periodos WHERE estado = 'activa' LIMIT 1)))";
    }

    /**
     * Igual que sqlMateriaConfigurada(), pero ademas exige que la coordinacion ya
     * haya aprobado la oferta (db/032). Es la que debe usar cualquier vista que le
     * muestre "tutores disponibles" al estudiante: tiene que coincidir con lo que
     * el motor realmente puede usar (slotsForMatter).
     */
    public static function sqlMateriaAprobada(string $alias = 'tm'): string
    {
        $configurada = self::sqlMateriaConfigurada($alias);

        return "({$configurada} AND EXISTS (SELECT 1 FROM tutor_materia_config tmca
                      WHERE tmca.id_tutor = {$alias}.id_tutor AND tmca.id_materia = {$alias}.id_materia
                        AND tmca.id_periodo = (SELECT id_periodo FROM periodos WHERE estado = 'activa' LIMIT 1) AND tmca.estado = 'aprobado'))";
    }

    /**
     * Tutores activos con cuantas de sus materias tienen horarios configurados, para la
     * supervision del administrador. Los que no pueden recibir grupos (ninguna materia
     * configurada) aparecen primero.
     */
    public function coverageByTutor(): array
    {
        $configurada = self::sqlMateriaConfigurada('tm');
        $habilitado = self::sqlTutorHabilitado();
        $rows = Database::connection()->query(
            "SELECT t.id_tutor, CONCAT(u.nombre, ' ', u.apellido) AS tutor,
                    COUNT(tm.id_materia) AS materias,
                    COALESCE(SUM(CASE WHEN tm.id_materia IS NOT NULL AND {$configurada} THEN 1 ELSE 0 END), 0) AS configuradas
             FROM tutores t
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND {$habilitado}
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
        $habilitado = self::sqlTutorHabilitado();

        return (int) Database::connection()->query(
            "SELECT COUNT(*) FROM tutores t
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND {$habilitado}
             WHERE NOT EXISTS (SELECT 1 FROM tutor_materia tm WHERE tm.id_tutor = t.id_tutor AND {$configurada})"
        )->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Aprobacion de la oferta por materia (db/032)
    // ------------------------------------------------------------------

    /** Ofertas (tutor + materia) pendientes de revision, con el detalle para decidir. */
    public function pendientes(): array
    {
        $statement = Database::connection()->query(
            "SELECT c.id_tutor, c.id_materia, c.modalidad, c.cupo_recomendado, c.fecha_actualizacion,
                    CONCAT(u.nombre, ' ', u.apellido) AS tutor, m.nombre_materia, m.modalidad_requerida
             FROM tutor_materia_config c
             INNER JOIN tutores t ON t.id_tutor = c.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             INNER JOIN materias m ON m.id_materia = c.id_materia
              WHERE c.estado = 'pendiente' AND c.id_periodo = (SELECT id_periodo FROM periodos WHERE estado = 'activa' LIMIT 1)
             ORDER BY c.fecha_actualizacion ASC"
        );
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row['turnos'] = $this->turnos((int) $row['id_tutor'], (int) $row['id_materia']);
        }
        unset($row);

        return $rows;
    }

    public function countPendientes(): int
    {
        return (int) Database::connection()->query(
            "SELECT COUNT(*) FROM tutor_materia_config WHERE estado = 'pendiente' AND id_periodo = (SELECT id_periodo FROM periodos WHERE estado = 'activa' LIMIT 1)"
        )->fetchColumn();
    }

    /**
     * La coordinacion aprueba la oferta (tutor, materia, modalidad y turnos). No
     * lleva espacio: la ubicacion se define por grupo, al revisarlo en Grupos de
     * tutoria. Devuelve [error|null, id_historial|null].
     */
    public function aprobar(int $tutorId, int $materiaId, int $adminId): array
    {
        $periodoId = $this->periodoActivo();
        if ($periodoId === null) { return ['No hay un período activo.', null]; }
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $this->bloquearPeriodo($connection, $periodoId);
            $this->bloquearTutor($connection, $tutorId);
            $turnos = $this->turnos($tutorId, $materiaId);
            $carga = $this->validarCarga($connection, $tutorId, $materiaId, $periodoId, $turnos);
            if ($carga !== null) {
                $connection->rollBack();
                return [$carga, null];
            }
            $statement = $connection->prepare(
                "UPDATE tutor_materia_config
                 SET estado = 'aprobado', motivo_rechazo = NULL,
                     id_usuario_revision = :id_usuario, fecha_revision = NOW()
                  WHERE id_tutor = :id_tutor AND id_materia = :id_materia AND id_periodo = :p AND estado = 'pendiente'"
            );
            $statement->execute([
                'id_usuario' => $adminId,
                'id_tutor' => $tutorId,
                'id_materia' => $materiaId,
                'p' => $periodoId,
            ]);
            if ($statement->rowCount() === 0) {
                $connection->rollBack();
                return ['Esta oferta ya no está pendiente. Recarga la página.', null];
            }
            $connection->prepare(
                "INSERT INTO tutor_materia_historial (id_tutor, id_materia, id_periodo, estado_anterior, estado_nuevo, motivo, id_usuario_accion)
                  VALUES (:id_tutor, :id_materia, :p, 'pendiente', 'aprobado', :motivo, :id_usuario)"
            )->execute([
                'id_tutor' => $tutorId,
                'id_materia' => $materiaId,
                'p' => $periodoId,
                'motivo' => 'Oferta aprobada por la coordinación.',
                'id_usuario' => $adminId,
            ]);
            $historialId = (int) $connection->lastInsertId();
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log('Aprobar oferta tutor ' . $tutorId . ' materia ' . $materiaId . ': ' . $exception->getMessage());
            return ['No fue posible aprobar la oferta.', null];
        }

        return [null, $historialId];
    }

    /** Devuelve [error|null, id_historial|null]. */
    public function rechazar(int $tutorId, int $materiaId, string $motivo, int $adminId): array
    {
        $periodoId = $this->periodoActivo();
        if ($periodoId === null) { return ['No hay un período activo.', null]; }
        $motivo = trim(preg_replace('/\s+/u', ' ', $motivo) ?? '');
        if (mb_strlen($motivo) < 10) {
            return ['Indica el motivo del rechazo (al menos 10 caracteres).', null];
        }
        if (mb_strlen($motivo) > 300 || preg_match('/[\x00-\x1F\x7F]/', $motivo)) {
            return ['El motivo no puede superar 300 caracteres ni contener caracteres no válidos.', null];
        }

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $statement = $connection->prepare(
                "UPDATE tutor_materia_config
                 SET estado = 'rechazado', motivo_rechazo = :motivo,
                     id_usuario_revision = :id_usuario, fecha_revision = NOW()
                  WHERE id_tutor = :id_tutor AND id_materia = :id_materia AND id_periodo = :p AND estado = 'pendiente'"
            );
            $statement->execute([
                'motivo' => $motivo,
                'id_usuario' => $adminId,
                'id_tutor' => $tutorId,
                'id_materia' => $materiaId,
                'p' => $periodoId,
            ]);
            if ($statement->rowCount() === 0) {
                $connection->rollBack();
                return ['Esta oferta ya no está pendiente. Recarga la página.', null];
            }
            $connection->prepare(
                "INSERT INTO tutor_materia_historial (id_tutor, id_materia, id_periodo, estado_anterior, estado_nuevo, motivo, id_usuario_accion)
                  VALUES (:id_tutor, :id_materia, :p, 'pendiente', 'rechazado', :motivo, :id_usuario)"
            )->execute([
                'id_tutor' => $tutorId,
                'id_materia' => $materiaId,
                'p' => $periodoId,
                'motivo' => $motivo,
                'id_usuario' => $adminId,
            ]);
            $historialId = (int) $connection->lastInsertId();
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log('Rechazar oferta tutor ' . $tutorId . ' materia ' . $materiaId . ': ' . $exception->getMessage());
            return ['No fue posible rechazar la oferta.', null];
        }

        return [null, $historialId];
    }

    // ------------------------------------------------------------------
    // Propuesta de la coordinacion (db/039)
    // ------------------------------------------------------------------

    /**
     * La coordinacion asigna un tutor a una materia sin tutor: crea la oferta en
     * estado 'propuesta' (o reemplaza una rechazada). El motor no la usa hasta que
     * el tutor la acepte en "Mis materias". $data: modalidad, cupo_recomendado,
     * turnos (ya validados). Devuelve [error|null, id_historial|null].
     */
    public function proponer(int $tutorId, int $materiaId, array $data, int $adminId): array
    {
        $periodoId = $this->periodoActivo();
        if ($periodoId === null) { return ['No hay un período activo.', null]; }
        $actual = $this->find($tutorId, $materiaId);
        if ($actual !== null) {
            $bloqueo = [
                'aprobado' => 'Este tutor ya tiene una oferta aprobada para la materia.',
                'pendiente' => 'Este tutor ya ofreció la materia: apruébala en Ofertas de materias.',
                'propuesta' => 'Ya hay una propuesta para este tutor esperando su respuesta.',
            ][$actual['estado']] ?? null;
            if ($bloqueo !== null) {
                return [$bloqueo, null];
            }
        }

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $this->bloquearPeriodo($connection, $periodoId);
            $this->bloquearTutor($connection, $tutorId);
            $vigente = $this->find($tutorId, $materiaId);
            if ($vigente !== null && in_array($vigente['estado'], ['aprobado', 'pendiente', 'propuesta'], true)) {
                $connection->rollBack();
                return ['Esta materia ya tiene una oferta o propuesta vigente para el tutor.', null];
            }
            $carga = $this->validarCarga($connection, $tutorId, $materiaId, $periodoId, $data['turnos'], true);
            if ($carga !== null) { $connection->rollBack(); return [$carga, null]; }
            $connection->prepare('INSERT IGNORE INTO tutor_materia (id_tutor, id_materia) VALUES (:id_tutor, :id_materia)')
                ->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId]);
            $connection->prepare(
                "INSERT INTO tutor_materia_config (id_tutor, id_materia, id_periodo, modalidad, cupo_recomendado, estado, motivo_rechazo, id_usuario_revision, fecha_revision)
                  VALUES (:id_tutor, :id_materia, :p, :modalidad, :cupo, 'propuesta', NULL, :admin, NOW())
                 ON DUPLICATE KEY UPDATE modalidad = VALUES(modalidad), cupo_recomendado = VALUES(cupo_recomendado),
                 estado = 'propuesta', motivo_rechazo = NULL, id_usuario_revision = VALUES(id_usuario_revision), fecha_revision = NOW()"
            )->execute([
                'id_tutor' => $tutorId,
                'id_materia' => $materiaId,
                'p' => $periodoId,
                'modalidad' => $data['modalidad'],
                'cupo' => $data['cupo_recomendado'],
                'admin' => $adminId,
            ]);
            $connection->prepare('DELETE FROM tutor_materia_turno WHERE id_tutor = :id_tutor AND id_materia = :id_materia AND id_periodo = :p')
                ->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId, 'p' => $periodoId]);
            $insertTurno = $connection->prepare('INSERT INTO tutor_materia_turno (id_tutor, id_materia, id_periodo, turno) VALUES (:id_tutor, :id_materia, :p, :turno)');
            foreach ($data['turnos'] as $turno) {
                $insertTurno->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId, 'p' => $periodoId, 'turno' => $turno]);
            }
            $connection->prepare(
                "INSERT INTO tutor_materia_historial (id_tutor, id_materia, id_periodo, estado_anterior, estado_nuevo, motivo, id_usuario_accion)
                  VALUES (:id_tutor, :id_materia, :p, :antes, 'propuesta', 'Asignada por la coordinación; espera la aceptación del tutor.', :admin)"
            )->execute(['id_tutor' => $tutorId, 'id_materia' => $materiaId, 'p' => $periodoId, 'antes' => $actual['estado'] ?? null, 'admin' => $adminId]);
            $historialId = (int) $connection->lastInsertId();
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log('Proponer oferta tutor ' . $tutorId . ' materia ' . $materiaId . ': ' . $exception->getMessage());
            return ['No fue posible enviar la propuesta.', null];
        }

        return [null, $historialId];
    }

    /**
     * El tutor responde la propuesta de la coordinacion. Aceptada pasa a 'aprobado'
     * (ya la propuso la coordinacion: no hay segunda revision); rechazada exige
     * motivo. Devuelve [error|null, id_historial|null].
     */
    public function responderPropuesta(int $tutorId, int $materiaId, bool $acepta, string $motivo, int $tutorUserId): array
    {
        $periodoId = $this->periodoActivo();
        if ($periodoId === null) { return ['No hay un período activo.', null]; }
        $motivo = trim(preg_replace('/\s+/u', ' ', $motivo) ?? '');
        if (!$acepta && (mb_strlen($motivo) < 10 || mb_strlen($motivo) > 300 || preg_match('/[\x00-\x1F\x7F]/', $motivo))) {
            return ['Indica el motivo del rechazo (entre 10 y 300 caracteres).', null];
        }
        $nuevo = $acepta ? 'aprobado' : 'rechazado';

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $this->bloquearPeriodo($connection, $periodoId);
            $this->bloquearTutor($connection, $tutorId);
            if ($acepta && ($carga = $this->validarCarga($connection, $tutorId, $materiaId, $periodoId, $this->turnos($tutorId, $materiaId), true)) !== null) {
                $connection->rollBack(); return [$carga, null];
            }
            $statement = $connection->prepare(
                "UPDATE tutor_materia_config SET estado = :nuevo, motivo_rechazo = :motivo
                  WHERE id_tutor = :id_tutor AND id_materia = :id_materia AND id_periodo = :p AND estado = 'propuesta'"
            );
            $statement->execute([
                'nuevo' => $nuevo,
                'motivo' => $acepta ? null : $motivo,
                'id_tutor' => $tutorId,
                'id_materia' => $materiaId,
                'p' => $periodoId,
            ]);
            if ($statement->rowCount() === 0) {
                $connection->rollBack();
                return ['Esta propuesta ya no está esperando tu respuesta. Recarga la página.', null];
            }
            $connection->prepare(
                "INSERT INTO tutor_materia_historial (id_tutor, id_materia, id_periodo, estado_anterior, estado_nuevo, motivo, id_usuario_accion)
                  VALUES (:id_tutor, :id_materia, :p, 'propuesta', :nuevo, :motivo, :usuario)"
            )->execute([
                'id_tutor' => $tutorId,
                'id_materia' => $materiaId,
                'p' => $periodoId,
                'nuevo' => $nuevo,
                'motivo' => $acepta ? 'El tutor aceptó la propuesta de la coordinación.' : $motivo,
                'usuario' => $tutorUserId,
            ]);
            $historialId = (int) $connection->lastInsertId();
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log('Responder propuesta tutor ' . $tutorId . ' materia ' . $materiaId . ': ' . $exception->getMessage());
            return ['No fue posible registrar tu respuesta.', null];
        }

        return [null, $historialId];
    }

    /** Propuestas de la coordinacion esperando respuesta, indexadas por id_materia. */
    public function propuestasPorMateria(): array
    {
        $rows = Database::connection()->query(
            "SELECT c.id_materia, c.id_tutor, c.fecha_revision, CONCAT(u.nombre, ' ', u.apellido) AS tutor
             FROM tutor_materia_config c
             INNER JOIN tutores t ON t.id_tutor = c.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
              WHERE c.estado = 'propuesta' AND c.id_periodo = (SELECT id_periodo FROM periodos WHERE estado = 'activa' LIMIT 1)
             ORDER BY c.fecha_revision"
        )->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id_materia']][] = $row;
        }

        return $result;
    }

    /**
     * Tutores habilitados que la coordinacion puede proponer para una materia, con
     * su carga del periodo y los turnos que ya ofrecen en otras materias aprobadas
     * (para no proponerles un turno repetido). Incluye el estado actual de su
     * oferta en esta materia, si existe.
     */
    public function candidatosParaMateria(int $materiaId, int $periodoId): array
    {
        $habilitado = self::sqlTutorHabilitado();
        $statement = Database::connection()->prepare(
            "SELECT t.id_tutor, CONCAT(u.nombre, ' ', u.apellido) AS tutor, t.especialidad,
                     (SELECT c.estado FROM tutor_materia_config c WHERE c.id_tutor = t.id_tutor AND c.id_materia = :m1 AND c.id_periodo = :p2) AS estado_oferta,
                     (SELECT COUNT(*) FROM tutor_materia_config c WHERE c.id_tutor = t.id_tutor AND c.id_periodo = :p3 AND c.estado IN ('pendiente','aprobado','propuesta')) AS materias_periodo,
                    (SELECT COUNT(*) FROM grupos_tutoria g WHERE g.id_tutor = t.id_tutor AND g.id_periodo = :p1 AND g.estado <> 'cancelado') AS grupos_periodo,
                    (SELECT GROUP_CONCAT(DISTINCT tt.turno) FROM tutor_materia_turno tt
                        INNER JOIN tutor_materia_config c2 ON c2.id_tutor = tt.id_tutor AND c2.id_materia = tt.id_materia AND c2.id_periodo = tt.id_periodo AND c2.estado IN ('aprobado','propuesta')
                      WHERE tt.id_periodo = :p4 AND tt.id_tutor = t.id_tutor AND tt.id_materia <> :m2) AS turnos_otras
             FROM tutores t INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND {$habilitado}
             ORDER BY grupos_periodo, u.apellido, u.nombre"
        );
        $statement->execute(['m1' => $materiaId, 'm2' => $materiaId, 'p1' => $periodoId, 'p2' => $periodoId, 'p3' => $periodoId, 'p4' => $periodoId]);

        return array_map(static function (array $row): array {
            $row['turnos_otras'] = $row['turnos_otras'] ? explode(',', (string) $row['turnos_otras']) : [];

            return $row;
        }, $statement->fetchAll());
    }
}
