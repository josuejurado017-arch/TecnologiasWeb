<?php

declare(strict_types=1);

/**
 * Catalogo informativo de materias para la pantalla "Solicitar apoyo academico".
 *
 * A diferencia del antiguo AsignacionController::matterOptions (que ocultaba las
 * materias sin tutor), aqui se devuelven TODAS las materias con un estado de
 * oferta calculado a partir de datos ya existentes (Fase A: sin cambios de esquema):
 *
 *   grupo_disponible  -> hay grupo aprobado (confirmado o en curso) con cupo libre
 *   grupo_formacion   -> solo hay grupos por aprobar (en formacion o listos para
 *                        revision, db/035) con cupo libre
 *   por_abrir         -> sin grupo con cupo, pero hay tutor con oferta aprobada: al
 *                        solicitar queda interes registrado hasta reunir el minimo
 *   sin_tutor         -> ningun tutor habilitado con horarios configurados para la materia
 *   en_espera         -> el estudiante ya tiene demanda pendiente en la materia (interes
 *                        registrado; motivo: esperando_companeros / sin_tutor / sin_horario /
 *                        grupo_cancelado)
 *
 * Regla de negocio: el estudiante NO elige tutor, pero si puede conocerlo: cada
 * materia trae los tutores que la dictan (el del grupo o los que tienen oferta
 * aprobada) y perfilesTutores() arma su perfil publico (especialidad, biografia,
 * calificacion de los estudiantes, experiencia). Nunca se exponen datos de
 * contacto. El enlace virtual tampoco (solo en "Mis tutorias" tras inscribirse).
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
        $tutoresOferta = $this->tutoresPorMateria();
        $preferencias = $this->preferenciasPorMateria();

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
            // Grupo en formacion (por aprobar, db/035) con mas estudiantes: avance hacia la cantidad recomendada.
            $inscritosFormacion = null;
            foreach ($gruposMateria as $grupo) {
                $cuposLibres += (int) $grupo['cupo_max'] - (int) $grupo['cupo_ocupado'];
                if ($grupo['estado'] === 'por_aprobar') {
                    $inscritosFormacion = max($inscritosFormacion ?? 0, (int) $grupo['cupo_ocupado']);
                } else {
                    $hayConfirmado = true;
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
                'inscritos_formacion' => $inscritosFormacion,
                'tutores_habilitados' => $tutores,
                'hay_oferta' => $gruposMateria !== [] || $tutores > 0,
                'preferencias' => $preferencias[$id] ?? ['turnos' => [], 'modalidades' => []],
                'interesados' => (int) ($interesados[$id] ?? 0),
                // Tutores a mostrar: los de los grupos con cupo; si no hay grupo, los que ofrecen la materia.
                'tutor_ids' => $gruposMateria
                    ? array_values(array_unique(array_map(static fn (array $g): int => (int) $g['id_tutor'], $gruposMateria)))
                    : ($tutoresOferta[$id] ?? []),
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
        // Solo grupos a los que todavia se puede entrar (Grupo::DIAS_INSCRIPCION_TARDIA).
        $abierta = Grupo::sqlInscripcionAbierta('g');
        $statement = Database::connection()->prepare(
            "SELECT g.id_materia, g.id_grupo, g.id_tutor, g.estado, g.dia_semana, g.hora_inicio, g.hora_fin, g.modalidad,
                    g.cupo_max, g.cupo_ocupado, g.fecha_aprobacion,
                    (SELECT GROUP_CONCAT(gd.dia_semana ORDER BY FIELD(gd.dia_semana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') SEPARATOR '/')
                       FROM grupo_dias gd WHERE gd.id_grupo = g.id_grupo) AS dias,
                    CONCAT(u.nombre, ' ', u.apellido) AS tutor
             FROM grupos_tutoria g
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE g.id_periodo = :id_periodo
               AND g.estado IN ('por_aprobar','formacion','confirmado','en_curso')
               AND g.cupo_ocupado < g.cupo_max
               AND {$abierta}
             ORDER BY g.id_materia, FIELD(g.estado,'confirmado','en_curso','formacion','por_aprobar'),
                      FIELD(g.dia_semana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'), g.hora_inicio"
        );
        $statement->execute(['id_periodo' => $periodoId]);

        $grouped = [];
        foreach ($statement->fetchAll() as $row) {
            $grouped[(int) $row['id_materia']][] = $row;
        }

        return $grouped;
    }

    /** Tutores activos con oferta aprobada para la materia, contados por materia (misma regla que el motor). */
    private function ofertaTutores(): array
    {
        $configurada = TutorMateriaConfig::sqlMateriaAprobada('tm');
        $habilitado = TutorMateriaConfig::sqlTutorHabilitado();
        $rows = Database::connection()->query(
            "SELECT tm.id_materia, COUNT(DISTINCT tm.id_tutor) AS tutores
             FROM tutor_materia tm
             INNER JOIN tutores t ON t.id_tutor = tm.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND {$habilitado}
             WHERE {$configurada}
             GROUP BY tm.id_materia"
        )->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id_materia']] = (int) $row['tutores'];
        }

        return $result;
    }

    /** Tutores habilitados con oferta aprobada, por materia: [id_materia => [id_tutor...]]. */
    private function tutoresPorMateria(): array
    {
        $configurada = TutorMateriaConfig::sqlMateriaAprobada('tm');
        $habilitado = TutorMateriaConfig::sqlTutorHabilitado();
        $rows = Database::connection()->query(
            "SELECT DISTINCT tm.id_materia, tm.id_tutor
             FROM tutor_materia tm
             INNER JOIN tutores t ON t.id_tutor = tm.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND {$habilitado}
             WHERE {$configurada}
             ORDER BY tm.id_materia, tm.id_tutor"
        )->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id_materia']][] = (int) $row['id_tutor'];
        }

        return $result;
    }

    /**
     * Perfil publico de los tutores (sin correo ni telefono) para la pantalla de
     * solicitud: especialidad, biografia, calificacion promedio que le dieron sus
     * estudiantes, grupos que ya dicto y materias que ofrece con turnos y modalidad.
     * Devuelve [id_tutor => perfil].
     */
    public function perfilesTutores(array $tutorIds): array
    {
        $tutorIds = array_values(array_unique(array_map('intval', $tutorIds)));
        if (!$tutorIds) {
            return [];
        }
        $pdo = Database::connection();
        $in = implode(',', array_fill(0, count($tutorIds), '?'));

        $statement = $pdo->prepare(
            "SELECT t.id_tutor, u.nombre, u.apellido, t.especialidad, t.biografia,
                    (SELECT ROUND(AVG(ev.calificacion_general), 1) FROM evaluaciones_grupo ev
                       INNER JOIN inscripciones i ON i.id_inscripcion = ev.id_inscripcion
                       INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo WHERE g.id_tutor = t.id_tutor) AS calificacion,
                    (SELECT COUNT(*) FROM evaluaciones_grupo ev
                       INNER JOIN inscripciones i ON i.id_inscripcion = ev.id_inscripcion
                       INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo WHERE g.id_tutor = t.id_tutor) AS evaluaciones,
                    (SELECT COUNT(*) FROM grupos_tutoria g WHERE g.id_tutor = t.id_tutor AND g.estado = 'finalizado') AS grupos_dictados
             FROM tutores t INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE t.id_tutor IN ({$in})"
        );
        $statement->execute($tutorIds);
        $perfiles = [];
        foreach ($statement->fetchAll() as $row) {
            $row['materias'] = [];
            $perfiles[(int) $row['id_tutor']] = $row;
        }

        $materias = $pdo->prepare(
            "SELECT c.id_tutor, m.nombre_materia, c.modalidad,
                    GROUP_CONCAT(tt.turno ORDER BY FIELD(tt.turno, 'Manana', 'Mediodia', 'Tarde', 'Noche')) AS turnos
             FROM tutor_materia_config c
             INNER JOIN materias m ON m.id_materia = c.id_materia
              LEFT JOIN tutor_materia_turno tt ON tt.id_tutor = c.id_tutor AND tt.id_materia = c.id_materia AND tt.id_periodo = c.id_periodo
              WHERE c.estado = 'aprobado' AND c.id_periodo = " . Periodo::sqlIdActivo() . " AND c.id_tutor IN ({$in})
             GROUP BY c.id_tutor, m.nombre_materia, c.modalidad
             ORDER BY m.nombre_materia"
        );
        $materias->execute($tutorIds);
        foreach ($materias->fetchAll() as $row) {
            if (isset($perfiles[(int) $row['id_tutor']])) {
                $perfiles[(int) $row['id_tutor']]['materias'][] = [
                    'nombre' => $row['nombre_materia'],
                    'modalidad' => $row['modalidad'],
                    'turnos' => $row['turnos'] !== null ? explode(',', (string) $row['turnos']) : [],
                ];
            }
        }

        return $perfiles;
    }

    /**
     * Preferencias orientativas por materia (turnos, modalidades) agregadas entre
     * las ofertas aprobadas (db/032). Solo informativo: no identifica tutores.
     */
    private function preferenciasPorMateria(): array
    {
        $pdo = Database::connection();
        $result = [];

        foreach ($pdo->query("SELECT id_materia, modalidad FROM tutor_materia_config WHERE estado = 'aprobado' AND id_periodo = " . Periodo::sqlIdActivo() . "")->fetchAll() as $row) {
            $id = (int) $row['id_materia'];
            $result[$id] ??= ['turnos' => [], 'modalidades' => []];
            $result[$id]['modalidades'][$row['modalidad']] = true;
        }

        $turnos = $pdo->query(
            "SELECT DISTINCT tt.id_materia, tt.turno FROM tutor_materia_turno tt
              INNER JOIN tutor_materia_config c ON c.id_tutor = tt.id_tutor AND c.id_materia = tt.id_materia AND c.id_periodo = tt.id_periodo AND c.estado = 'aprobado'
              WHERE c.id_periodo = " . Periodo::sqlIdActivo() . ""
        )->fetchAll();
        foreach ($turnos as $row) {
            $id = (int) $row['id_materia'];
            $result[$id] ??= ['turnos' => [], 'modalidades' => []];
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
