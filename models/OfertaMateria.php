<?php

declare(strict_types=1);

/**
 * Catalogo informativo de materias para la pantalla "Solicitar apoyo academico".
 *
 * A diferencia del antiguo AsignacionController::matterOptions (que ocultaba las
 * materias sin tutor), aqui se devuelven TODAS las materias con un estado de
 * oferta calculado a partir de datos ya existentes (Fase A: sin cambios de esquema):
 *
 *   grupo_disponible  -> hay grupo confirmado con cupo libre
 *   grupo_formacion   -> hay grupo en formacion con cupo libre (falta cupo minimo)
 *   por_abrir         -> sin grupo con cupo, pero hay tutor habilitado con horarios configurados
 *   sin_tutor         -> ningun tutor habilitado con horarios configurados para la materia
 *   en_espera         -> el estudiante ya tiene demanda pendiente en la materia
 *                        (motivo real: sin_tutor / sin_horario / grupo_cancelado)
 *
 * Regla de negocio: el estudiante NO elige tutor. El nombre del tutor solo se
 * expone cuando el grupo ya existe; sin grupo se informa "N tutores habilitados".
 * El enlace virtual nunca se expone aqui (solo en "Mis tutorias" tras inscribirse).
 */
final class OfertaMateria
{
    public const ESTADO_DISPONIBLE = 'grupo_disponible';
    public const ESTADO_FORMACION = 'grupo_formacion';
    public const ESTADO_POR_ABRIR = 'por_abrir';
    public const ESTADO_SIN_TUTOR = 'sin_tutor';
    public const ESTADO_EN_ESPERA = 'en_espera';

    /** Orden de presentacion: lo accionable primero, sin ocultar nada. */
    private const ORDEN = [
        self::ESTADO_DISPONIBLE => 1,
        self::ESTADO_FORMACION => 2,
        self::ESTADO_POR_ABRIR => 3,
        self::ESTADO_EN_ESPERA => 4,
        self::ESTADO_SIN_TUTOR => 5,
    ];

    /** Carrera del estudiante (id y nombre) para el filtro "Mi carrera". */
    public function studentCareer(int $studentId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.id_carrera, c.nombre_carrera
             FROM estudiantes e INNER JOIN carreras c ON c.id_carrera = e.id_carrera
             WHERE e.id_estudiante = :id LIMIT 1'
        );
        $statement->execute(['id' => $studentId]);
        $career = $statement->fetch();

        return $career ?: null;
    }

    /**
     * Catalogo completo para un estudiante en un periodo.
     * $carreraId: limita a esa carrera (mas materias sin carrera); null = todas.
     * Las materias donde el estudiante ya esta inscrito se excluyen (viven en "Mis tutorias").
     */
    public function catalog(int $studentId, int $periodoId, array $periodo, ?int $carreraId = null): array
    {
        $materias = $this->materiasBase($carreraId);
        if (!$materias) {
            return [];
        }

        $inscritas = (new Inscripcion())->studentMatterIds($studentId, $periodoId);
        $enEspera = (new Demanda())->pendingForStudent($studentId, $periodoId);
        $interesados = (new Demanda())->pendingCountByMatter($periodoId);
        $grupos = $this->gruposConCupo($periodoId);
        $oferta = $this->ofertaTutores();
        $preferencias = $this->preferenciasPorMateria();
        $cupoMin = (int) ($periodo['cupo_min_grupo'] ?? 0);

        $result = [];
        foreach ($materias as $materia) {
            $id = (int) $materia['id_materia'];
            if (in_array($id, $inscritas, true)) {
                continue;
            }

            $gruposMateria = $grupos[$id] ?? [];
            $tutores = (int) ($oferta[$id] ?? 0);
            $cuposLibres = 0;
            $hayConfirmado = false;
            $faltanConfirmar = null;
            foreach ($gruposMateria as $grupo) {
                $cuposLibres += (int) $grupo['cupo_max'] - (int) $grupo['cupo_ocupado'];
                if ($grupo['estado'] === 'confirmado') {
                    $hayConfirmado = true;
                } else {
                    $faltan = max(0, $cupoMin - (int) $grupo['cupo_ocupado']);
                    $faltanConfirmar = $faltanConfirmar === null ? $faltan : min($faltanConfirmar, $faltan);
                }
            }

            if (isset($enEspera[$id])) {
                $estado = self::ESTADO_EN_ESPERA;
            } elseif ($gruposMateria && $hayConfirmado) {
                $estado = self::ESTADO_DISPONIBLE;
            } elseif ($gruposMateria) {
                $estado = self::ESTADO_FORMACION;
            } elseif ($tutores > 0) {
                $estado = self::ESTADO_POR_ABRIR;
            } else {
                $estado = self::ESTADO_SIN_TUTOR;
            }

            // Materia en espera: motivo real registrado por el motor (db/023).
            $motivoEspera = $enEspera[$id]['motivo'] ?? null;

            $result[] = [
                'id_materia' => $id,
                'nombre_materia' => $materia['nombre_materia'],
                'nombre_carrera' => $materia['nombre_carrera'],
                'estado' => $estado,
                'grupos' => $gruposMateria,
                'cupos_libres' => $cuposLibres,
                'faltan_confirmar' => $faltanConfirmar,
                'tutores_habilitados' => $tutores,
                'hay_oferta' => $gruposMateria !== [] || $tutores > 0,
                'preferencias' => $preferencias[$id] ?? ['turnos' => [], 'modalidades' => [], 'sabados' => false],
                'interesados' => (int) ($interesados[$id] ?? 0),
                'espera_desde' => $enEspera[$id]['fecha'] ?? null,
                'motivo_espera' => $motivoEspera,
            ];
        }

        usort($result, static function (array $a, array $b): int {
            return [self::ORDEN[$a['estado']], $a['nombre_materia']] <=> [self::ORDEN[$b['estado']], $b['nombre_materia']];
        });

        return $result;
    }

    /** Conteo por estado para el resumen de cabecera. */
    public function summary(array $catalog): array
    {
        $summary = array_fill_keys(array_keys(self::ORDEN), 0);
        foreach ($catalog as $item) {
            $summary[$item['estado']]++;
        }

        return $summary;
    }

    private function materiasBase(?int $carreraId): array
    {
        $sql = 'SELECT m.id_materia, m.nombre_materia, c.nombre_carrera
                FROM materias m LEFT JOIN carreras c ON c.id_carrera = m.id_carrera';
        $params = [];
        if ($carreraId !== null) {
            $sql .= ' WHERE m.id_carrera = :id_carrera OR m.id_carrera IS NULL';
            $params['id_carrera'] = $carreraId;
        }
        $sql .= ' ORDER BY m.nombre_materia';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** Grupos del periodo con cupo libre, indexados por materia. Incluye el tutor porque el grupo ya existe. */
    private function gruposConCupo(int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT g.id_materia, g.id_grupo, g.estado, g.dia_semana, g.hora_inicio, g.hora_fin, g.modalidad,
                    g.cupo_max, g.cupo_ocupado, a.nombre AS aula,
                    CONCAT(u.nombre, ' ', u.apellido) AS tutor
             FROM grupos_tutoria g
             INNER JOIN aulas a ON a.id_aula = g.id_aula
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE g.id_periodo = :id_periodo
               AND g.estado IN ('formacion','confirmado')
               AND g.cupo_ocupado < g.cupo_max
             ORDER BY g.id_materia, FIELD(g.estado,'confirmado','formacion'),
                      FIELD(g.dia_semana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'), g.hora_inicio"
        );
        $statement->execute(['id_periodo' => $periodoId]);

        $grouped = [];
        foreach ($statement->fetchAll() as $row) {
            $grouped[(int) $row['id_materia']][] = $row;
        }

        return $grouped;
    }

    /** Tutores activos con horarios configurados para la materia, contados por materia (misma regla que el motor). */
    private function ofertaTutores(): array
    {
        $configurada = TutorMateriaConfig::sqlMateriaConfigurada('tm');
        $rows = Database::connection()->query(
            "SELECT tm.id_materia, COUNT(DISTINCT tm.id_tutor) AS tutores
             FROM tutor_materia tm
             INNER JOIN tutores t ON t.id_tutor = tm.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND u.estado = 'activo'
             WHERE {$configurada}
             GROUP BY tm.id_materia"
        )->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id_materia']] = (int) $row['tutores'];
        }

        return $result;
    }

    /**
     * Preferencias orientativas por materia (turnos, modalidades, sabados) agregadas
     * entre todos los tutores configurados. Solo informativo: no identifica tutores.
     */
    private function preferenciasPorMateria(): array
    {
        $pdo = Database::connection();
        $result = [];

        foreach ($pdo->query('SELECT id_materia, modalidad, disponible_sabados FROM tutor_materia_config')->fetchAll() as $row) {
            $id = (int) $row['id_materia'];
            $result[$id] ??= ['turnos' => [], 'modalidades' => [], 'sabados' => false];
            $result[$id]['modalidades'][$row['modalidad']] = true;
            if ((int) $row['disponible_sabados'] === 1) {
                $result[$id]['sabados'] = true;
            }
        }

        foreach ($pdo->query('SELECT DISTINCT id_materia, turno FROM tutor_materia_turno')->fetchAll() as $row) {
            $id = (int) $row['id_materia'];
            $result[$id] ??= ['turnos' => [], 'modalidades' => [], 'sabados' => false];
            $result[$id]['turnos'][] = $row['turno'];
        }

        $ordenTurnos = array_keys(TutorMateriaConfig::TURNOS);
        foreach ($result as &$item) {
            $item['modalidades'] = array_keys($item['modalidades']);
            usort($item['turnos'], static fn (string $a, string $b): int => array_search($a, $ordenTurnos, true) <=> array_search($b, $ordenTurnos, true));
        }
        unset($item);

        return $result;
    }
}
