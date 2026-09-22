<?php

declare(strict_types=1);

/**
 * Motor de asignacion automatica: el estudiante declara materias y el sistema
 * lo coloca en un grupo compatible (existente o nuevo) respetando cupo y conflictos.
 * Reglas deterministas: cupo libre + sin solape de horario del estudiante,
 * del tutor y del aula.
 *
 * Al crear un grupo nuevo, los horarios salen unicamente de lo que el tutor
 * configuro para esa materia en "Mis materias" (TutorMateriaConfig: turnos x dias,
 * franjas de sabado, modalidad, cupo recomendado). Un tutor sin horarios
 * configurados para la materia no ofrece grupos en ella.
 */
final class AsignacionController
{
    private Grupo $grupos;
    private Inscripcion $inscripciones;
    private Demanda $demanda;
    private HistorialGrupo $historial;

    public function __construct()
    {
        $this->grupos = new Grupo();
        $this->inscripciones = new Inscripcion();
        $this->demanda = new Demanda();
        $this->historial = new HistorialGrupo();
    }

    /**
     * Catalogo informativo de materias para el estudiante (todas, con estado de oferta).
     * Reemplaza a matterOptions(), que ocultaba las materias sin tutor y con ello la demanda real.
     */
    public function catalog(int $studentId, array $periodo, ?int $carreraId = null): array
    {
        return (new OfertaMateria())->catalog($studentId, (int) $periodo['id_periodo'], $periodo, $carreraId);
    }

    /**
     * Procesa la solicitud de apoyo del estudiante para varias materias.
     * Devuelve un resumen por materia: [id_materia, nombre, resultado, detalle].
     * Una materia sin oferta (sin tutor con horarios configurados) no pasa por el motor:
     * se registra como interes para que la universidad mida la demanda real.
     */
    public function solicitarApoyo(int $studentId, array $matterIds, array $periodo): array
    {
        $periodoId = (int) $periodo['id_periodo'];
        $cupoMin = (int) $periodo['cupo_min_grupo'];
        $cupoMax = (int) $periodo['cupo_max_default'];
        $results = [];

        // Inscripciones activas + demanda pendiente: ninguna se vuelve a solicitar.
        $alreadyRequested = array_merge(
            $this->inscripciones->studentMatterIds($studentId, $periodoId),
            array_keys($this->demanda->pendingForStudent($studentId, $periodoId))
        );

        foreach ($matterIds as $matterId) {
            $matterId = (int) $matterId;
            if ($matterId < 1) {
                continue;
            }
            $name = $this->matterName($matterId);
            if (in_array($matterId, $alreadyRequested, true)) {
                $results[] = ['id_materia' => $matterId, 'nombre' => $name, 'resultado' => 'ya_solicitada', 'detalle' => 'Ya tienes una solicitud o registro de interes para esta materia.'];
                continue;
            }

            if (!$this->hasOffer($matterId, $periodoId)) {
                $this->demanda->record($periodoId, $matterId, $studentId, Demanda::MOTIVO_SIN_TUTOR);
                $results[] = ['id_materia' => $matterId, 'nombre' => $name, 'resultado' => 'interes_registrado', 'detalle' => 'Esta materia aun no tiene tutor. Tu interes quedo registrado y te avisaremos si se abre un grupo.'];
                $alreadyRequested[] = $matterId;
                continue;
            }

            $outcome = $this->assignMatter($studentId, $matterId, $periodoId, $cupoMin, $cupoMax);
            $outcome['id_materia'] = $matterId;
            $outcome['nombre'] = $name;
            $results[] = $outcome;
            if ($outcome['resultado'] === 'grupo_creado') {
                // Un grupo nuevo es capacidad nueva: los que esperaban esta materia pueden entrar.
                $this->reprocesarMateria($matterId, $periodo, $studentId);
            }
            $alreadyRequested[] = $matterId;
        }

        return $results;
    }

    /** Registra interes explicito en una materia sin tutor (boton "Registrar interes"). */
    public function registrarInteres(int $studentId, int $matterId, array $periodo): array
    {
        $periodoId = (int) $periodo['id_periodo'];
        $name = $this->matterName($matterId);

        if (in_array($matterId, $this->inscripciones->studentMatterIds($studentId, $periodoId), true)) {
            return ['id_materia' => $matterId, 'nombre' => $name, 'resultado' => 'ya_solicitada', 'detalle' => 'Ya estas inscrito en un grupo de esta materia.'];
        }
        if (isset($this->demanda->pendingForStudent($studentId, $periodoId)[$matterId])) {
            return ['id_materia' => $matterId, 'nombre' => $name, 'resultado' => 'ya_solicitada', 'detalle' => 'Ya registraste interes en esta materia.'];
        }
        if ($this->hasOffer($matterId, $periodoId)) {
            // Tiene oferta: corresponde solicitar apoyo, no registrar interes.
            return $this->solicitarApoyo($studentId, [$matterId], $periodo)[0];
        }

        $this->demanda->record($periodoId, $matterId, $studentId, Demanda::MOTIVO_SIN_TUTOR);

        return ['id_materia' => $matterId, 'nombre' => $name, 'resultado' => 'interes_registrado', 'detalle' => 'Tu interes quedo registrado. Te avisaremos si se abre un grupo.'];
    }

    /**
     * Reintento manual del estudiante en espera: vuelve a pasar por el motor con la
     * oferta de hoy. Si consigue grupo, la demanda pasa a "atendida".
     */
    public function reintentar(int $studentId, int $matterId, array $periodo): array
    {
        $periodoId = (int) $periodo['id_periodo'];
        $name = $this->matterName($matterId);

        if (!isset($this->demanda->pendingForStudent($studentId, $periodoId)[$matterId])) {
            return ['id_materia' => $matterId, 'nombre' => $name, 'resultado' => 'ya_solicitada', 'detalle' => 'No tienes una espera pendiente en esta materia.'];
        }
        if (!$this->hasOffer($matterId, $periodoId)) {
            return ['id_materia' => $matterId, 'nombre' => $name, 'resultado' => 'lista_espera', 'detalle' => 'Aun no hay tutor habilitado para esta materia. Sigues en espera.'];
        }

        $outcome = $this->assignMatter($studentId, $matterId, $periodoId, (int) $periodo['cupo_min_grupo'], (int) $periodo['cupo_max_default']);
        $outcome['id_materia'] = $matterId;
        $outcome['nombre'] = $name;
        if ($outcome['resultado'] === 'grupo_creado') {
            $this->reprocesarMateria($matterId, $periodo, $studentId);
        }

        return $outcome;
    }

    /** El estudiante retira su interes o espera en una materia. */
    public function quitarEspera(int $studentId, int $matterId, array $periodo): array
    {
        $name = $this->matterName($matterId);
        $removed = $this->demanda->cancel((int) $periodo['id_periodo'], $matterId, $studentId);

        return [
            'id_materia' => $matterId,
            'nombre' => $name,
            'resultado' => $removed ? 'espera_retirada' : 'ya_solicitada',
            'detalle' => $removed ? 'Retiraste tu solicitud. Puedes volver a solicitarla cuando quieras.' : 'No tenias una espera pendiente en esta materia.',
        ];
    }

    // ------------------------------------------------------------------
    // Reproceso automatico de demanda (Fase C).
    // Regla institucional: cuando aparece oferta nueva (horarios de una materia, tutor
    // habilitado en una materia, grupo nuevo, cupo liberado por cancelacion) el
    // sistema intenta ubicar a los estudiantes en espera SIN intervencion del
    // administrador. Orden FIFO por fecha de solicitud. Nunca lanza: un fallo
    // aqui no debe romper el flujo del tutor o del admin que lo disparo.
    // ------------------------------------------------------------------

    /**
     * Reprocesa la cola de espera de una materia en el periodo indicado (o el activo).
     * $allowCreate=false limita a grupos ya existentes: se usa tras una cancelacion
     * administrativa para no recrear al instante el grupo que el admin acaba de cancelar.
     * Devuelve [atendidos => n, pendientes => n].
     */
    public function reprocesarMateria(int $matterId, ?array $periodo = null, ?int $excludeStudentId = null, bool $allowCreate = true): array
    {
        $summary = ['atendidos' => 0, 'pendientes' => 0];
        try {
            $periodo ??= (new Periodo())->activa();
            if (!$periodo || $periodo['estado'] !== 'activa') {
                return $summary;
            }
            $periodoId = (int) $periodo['id_periodo'];
            $cupoMin = (int) $periodo['cupo_min_grupo'];
            $cupoMax = (int) $periodo['cupo_max_default'];
            $hasOffer = $this->hasOffer($matterId, $periodoId);

            foreach ($this->demanda->pendingForMatter($periodoId, $matterId) as $pending) {
                $studentId = (int) $pending['id_estudiante'];
                if ($studentId === $excludeStudentId) {
                    continue;
                }
                // Ya quedo inscrito por otra via (p. ej. inscripcion manual del admin).
                if (in_array($matterId, $this->inscripciones->studentMatterIds($studentId, $periodoId), true)) {
                    $this->demanda->markAttended($periodoId, $matterId, $studentId);
                    $summary['atendidos']++;
                    continue;
                }
                if (!$hasOffer) {
                    $this->demanda->updateMotivo($periodoId, $matterId, $studentId, Demanda::MOTIVO_SIN_TUTOR);
                    $summary['pendientes']++;
                    continue;
                }

                $outcome = $this->assignMatter($studentId, $matterId, $periodoId, $cupoMin, $cupoMax, true, $allowCreate);
                if (in_array($outcome['resultado'], ['asignado', 'grupo_creado'], true)) {
                    $summary['atendidos']++;
                    $this->notifyDemandAttended($studentId, $matterId, $outcome['detalle']);
                    // Un grupo recien creado tiene cupo: los siguientes de la cola lo aprovechan en la misma pasada.
                } else {
                    $summary['pendientes']++;
                    // Aparecio tutor pero sigue sin horario compatible: el motivo cambia. Un
                    // 'grupo_cancelado' se conserva: es la senal que el admin necesita ver.
                    if ($pending['motivo'] === Demanda::MOTIVO_SIN_TUTOR) {
                        $this->demanda->updateMotivo($periodoId, $matterId, $studentId, Demanda::MOTIVO_SIN_HORARIO);
                    }
                }
            }
        } catch (Throwable $exception) {
            error_log('Reproceso demanda materia ' . $matterId . ': ' . $exception->getMessage());
        }

        return $summary;
    }

    /** Aviso al estudiante que estaba en espera y acaba de recibir grupo por reproceso. */
    private function notifyDemandAttended(int $studentId, int $matterId, string $detalle): void
    {
        try {
            $userId = $this->studentUserId($studentId);
            if ($userId !== null) {
                (new Notificacion())->notifyDemandAttended(Database::connection(), $userId, $matterId, $this->matterName($matterId), $detalle);
            }
        } catch (Throwable $exception) {
            error_log('Notificacion demanda atendida: ' . $exception->getMessage());
        }
    }

    /** Hay oferta si existe grupo con cupo o al menos un tutor activo con horarios configurados para la materia (misma regla que el motor). */
    private function hasOffer(int $matterId, int $periodoId): bool
    {
        if ($this->grupos->candidatesForMatter($periodoId, $matterId) !== []) {
            return true;
        }

        return (new TutorMateriaConfig())->preferencesForMatter($matterId) !== [];
    }

    private function assignMatter(int $studentId, int $matterId, int $periodoId, int $cupoMin, int $cupoMax, bool $reprocessing = false, bool $allowCreate = true): array
    {
        $busy = $this->inscripciones->studentBusySlots($studentId, $periodoId);

        // 1) Intentar grupos existentes con cupo.
        foreach ($this->grupos->candidatesForMatter($periodoId, $matterId) as $candidate) {
            if ($this->overlapsBusy($busy, $candidate['dia_semana'], $candidate['hora_inicio'], $candidate['hora_fin'])) {
                continue;
            }
            $group = $this->enrollInExisting((int) $candidate['id_grupo'], $studentId, $cupoMin);
            if ($group !== null) {
                $this->demanda->markAttended($periodoId, $matterId, $studentId);
                $this->emitNotifications($group, $studentId, $cupoMin);
                return ['resultado' => 'asignado', 'detalle' => $this->describe($group)];
            }
        }

        // 2) Crear un grupo nuevo desde los horarios que un tutor configuro para la materia.
        $group = $allowCreate ? $this->createGroupForMatter($studentId, $matterId, $periodoId, $cupoMin, $cupoMax, $busy) : null;
        if ($group !== null) {
            $this->demanda->markAttended($periodoId, $matterId, $studentId);
            $this->emitNotifications($group, $studentId, $cupoMin);
            return ['resultado' => 'grupo_creado', 'detalle' => $this->describe($group)];
        }

        // 3) Demanda insatisfecha: hay oferta pero ningun horario/aula compatible.
        // En reproceso la fila ya existe y su motivo lo administra reprocesarMateria().
        if (!$reprocessing) {
            $this->demanda->record($periodoId, $matterId, $studentId, Demanda::MOTIVO_SIN_HORARIO);
        }

        return ['resultado' => 'lista_espera', 'detalle' => 'Hay tutor para esta materia, pero ningun horario compatible con tu agenda ni aula libre por ahora. Quedaste en lista de espera.'];
    }

    /** Inscribe al estudiante en un grupo existente con bloqueo de cupo. Devuelve datos del grupo o null. */
    private function enrollInExisting(int $grupoId, int $studentId, int $cupoMin): ?array
    {
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $grupo = $this->grupos->lockForEnroll($grupoId);
            if ($grupo === null
                || !in_array($grupo['estado'], ['formacion', 'confirmado'], true)
                || (int) $grupo['cupo_ocupado'] >= (int) $grupo['cupo_max']
                || $this->inscripciones->existsActive($grupoId, $studentId)) {
                $connection->rollBack();
                return null;
            }
            $this->inscripciones->create($grupoId, $studentId, 'inscrito', 'auto');
            $this->grupos->registerEnrollment($grupoId, $cupoMin);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log($exception->getMessage());
            return null;
        }

        return $this->groupDetail($grupoId);
    }

    /**
     * Busca un patron semanal (tutor+turno+aula) libre y crea el grupo, inscribiendo
     * al estudiante. Un "patron" es el conjunto de dias que el tutor declaro para un
     * turno en esa materia (TutorMateriaConfig); la frecuencia final se ajusta segun
     * la demanda pendiente de la materia y se degrada dia por dia si no hay tutor o
     * aula libres para el patron completo (ver diasParaFrecuencia/intentarPatron).
     */
    private function createGroupForMatter(int $studentId, int $matterId, int $periodoId, int $cupoMin, int $cupoMax, array $busy): ?array
    {
        $patterns = $this->agruparPatrones((new TutorMateriaConfig())->slotsForMatter($matterId));

        $periodo = (new Periodo())->findById($periodoId);
        if ($periodo === null) {
            return null;
        }

        // Demanda pendiente de la materia (incluye a este estudiante): proxy del
        // tamano final del grupo para decidir la frecuencia semanal (punto 8 del
        // analisis: se evalua solo al crear, nunca se recalcula despues).
        $demanda = 1 + ($this->demanda->pendingCountByMatter($periodoId)[$matterId] ?? 0);

        foreach ($patterns as $pattern) {
            $grupoId = $this->intentarPatron($pattern, $demanda, $studentId, $matterId, $periodoId, $cupoMin, $cupoMax, $busy, $periodo);
            if ($grupoId !== null) {
                return $this->groupDetail($grupoId);
            }
        }

        return null;
    }

    /** Agrupa los bloques por (tutor, horario, modalidad, cupo): mismo turno real, dias candidatos del patron. */
    private function agruparPatrones(array $expanded): array
    {
        $patterns = [];
        foreach ($expanded as $slot) {
            $key = implode('|', [
                $slot['id_tutor'], $slot['hora_inicio'], $slot['hora_fin'],
                $slot['modalidad_preferida'] ?? '', $slot['cupo_recomendado'] ?? '',
            ]);
            $patterns[$key] ??= [
                'id_tutor' => (int) $slot['id_tutor'],
                'hora_inicio' => $slot['hora_inicio'],
                'hora_fin' => $slot['hora_fin'],
                'modalidad_preferida' => $slot['modalidad_preferida'],
                'cupo_recomendado' => $slot['cupo_recomendado'],
                'dias' => [],
            ];
            $patterns[$key]['dias'][] = $slot['dia_semana'];
        }

        return array_values($patterns);
    }

    /**
     * Frecuencia semanal recomendada segun demanda pendiente de la materia:
     * menos de 8 -> grupo reducido, prefiere Lunes/Miercoles/Viernes o
     * Martes/Jueves/Sabado (el que mejor coincida con lo que el tutor declaro);
     * 8 o mas -> usa el patron completo que el tutor declaro para ese turno (ya es
     * "la frecuencia estandar": lo que el propio tutor configuro). Nunca se inventan
     * dias que el tutor no declaro en Mis materias.
     */
    private function diasParaFrecuencia(array $diasDeclarados, int $demanda): array
    {
        if ($demanda >= 8) {
            return $diasDeclarados;
        }
        foreach ([['Lunes', 'Miercoles', 'Viernes'], ['Martes', 'Jueves', 'Sabado']] as $preset) {
            $match = array_values(array_intersect($preset, $diasDeclarados));
            if (count($match) >= 2) {
                return $match;
            }
        }

        return $diasDeclarados;
    }

    /**
     * Intenta crear el grupo con el patron completo recomendado; si hay conflicto de
     * tutor, aula o agenda del estudiante, degrada quitando un dia a la vez (el mas
     * reciente del patron) hasta encontrar una combinacion viable o agotar los dias.
     */
    private function intentarPatron(array $pattern, int $demanda, int $studentId, int $matterId, int $periodoId, int $cupoMin, int $cupoMax, array $busy, array $periodo): ?int
    {
        $dias = $this->diasParaFrecuencia($pattern['dias'], $demanda);

        while ($dias !== []) {
            $sinConflicto = !$this->overlapsBusyAny($busy, $dias, $pattern['hora_inicio'], $pattern['hora_fin'])
                && !$this->grupos->tutorHasConflict($pattern['id_tutor'], $dias, $pattern['hora_inicio'], $pattern['hora_fin'], $periodoId);

            if ($sinConflicto) {
                $aula = $this->grupos->findFreeAula($dias, $pattern['hora_inicio'], $pattern['hora_fin'], $periodoId, $pattern['modalidad_preferida']);
                if ($aula !== null) {
                    $grupoId = $this->confirmarGrupo($pattern, $dias, $aula, $studentId, $matterId, $periodoId, $cupoMin, $cupoMax, $periodo);
                    if ($grupoId !== null) {
                        return $grupoId;
                    }
                }
            }

            array_pop($dias);
        }

        return null;
    }

    /** Crea el grupo con el patron de dias ya validado, genera sus sesiones e inscribe al estudiante. */
    private function confirmarGrupo(array $pattern, array $dias, array $aula, int $studentId, int $matterId, int $periodoId, int $cupoMin, int $cupoMax, array $periodo): ?int
    {
        $modalidad = $aula['tipo'] === 'virtual' ? 'virtual' : 'presencial';
        $cupoGrupo = min($cupoMax, (int) $aula['capacidad']);
        if ($pattern['cupo_recomendado'] !== null) {
            $cupoGrupo = min($cupoGrupo, $pattern['cupo_recomendado']);
        }

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $grupoId = $this->grupos->create([
                'id_periodo' => $periodoId,
                'id_materia' => $matterId,
                'id_tutor' => $pattern['id_tutor'],
                'id_aula' => (int) $aula['id_aula'],
                'modalidad' => $modalidad,
                'dias' => $dias,
                'hora_inicio' => $pattern['hora_inicio'],
                'hora_fin' => $pattern['hora_fin'],
                'cupo_max' => $cupoGrupo,
                'estado' => 'formacion',
            ]);
            $this->grupos->generateSessions($grupoId, $dias, $periodo['fecha_inicio'], $periodo['fecha_fin']);
            $this->inscripciones->create($grupoId, $studentId, 'inscrito', 'auto');
            $this->grupos->registerEnrollment($grupoId, $cupoMin);
            $this->historial->log($connection, $grupoId, 'creado', null, 'formacion', null, 'Grupo generado automaticamente por el sistema (' . implode('/', $dias) . ').');
            $connection->commit();

            return $grupoId;
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log($exception->getMessage());

            return null;
        }
    }

    private function overlapsBusyAny(array $busy, array $dias, string $horaInicio, string $horaFin): bool
    {
        foreach ($dias as $dia) {
            if ($this->overlapsBusy($busy, $dia, $horaInicio, $horaFin)) {
                return true;
            }
        }

        return false;
    }

    /** Emite notificacion de asignacion y, si el grupo acaba de confirmarse, avisa a todo el grupo. */
    private function emitNotifications(array $group, int $studentId, int $cupoMin): void
    {
        try {
            $pdo = Database::connection();
            $notif = new Notificacion();
            $studentUserId = $this->studentUserId($studentId);
            if ($studentUserId !== null) {
                $notif->notifyAssignment($pdo, (int) $group['id_grupo'], $studentUserId, (string) $group['nombre_materia'], $this->describe($group));
            }
            // El grupo acaba de cruzar el cupo minimo con esta inscripcion.
            if ($group['estado'] === 'confirmado' && (int) $group['cupo_ocupado'] === $cupoMin) {
                $notif->notifyGroupConfirmed($pdo, (int) $group['id_grupo'], (string) $group['nombre_materia']);
                $this->historial->log($pdo, (int) $group['id_grupo'], 'confirmado', 'formacion', 'confirmado', null, 'Alcanzo el cupo minimo.');
            }
        } catch (Throwable $exception) {
            error_log('Notificacion asignacion: ' . $exception->getMessage());
        }
    }

    private function studentUserId(int $studentId): ?int
    {
        $statement = Database::connection()->prepare('SELECT id_usuario FROM estudiantes WHERE id_estudiante = :id LIMIT 1');
        $statement->execute(['id' => $studentId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function overlapsBusy(array $busy, string $dia, string $horaInicio, string $horaFin): bool
    {
        foreach ($busy as $slot) {
            if ($slot['dia_semana'] === $dia && $slot['hora_inicio'] < $horaFin && $slot['hora_fin'] > $horaInicio) {
                return true;
            }
        }

        return false;
    }

    private function groupDetail(int $grupoId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT g.id_grupo, g.cupo_ocupado, g.estado, g.dia_semana, g.hora_inicio, g.hora_fin, g.modalidad,
                    m.nombre_materia, a.nombre AS aula, CONCAT(u.nombre, ' ', u.apellido) AS tutor
             FROM grupos_tutoria g
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN aulas a ON a.id_aula = g.id_aula
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE g.id_grupo = :id LIMIT 1"
        );
        $statement->execute(['id' => $grupoId]);
        $detail = $statement->fetch();
        if ($detail) {
            $detail['dias'] = $this->diasDelGrupo($grupoId);
        }

        return $detail ?: null;
    }

    /** Patron semanal completo del grupo (uno o varios dias), ordenado Lunes-Sabado. */
    private function diasDelGrupo(int $grupoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT dia_semana FROM grupo_dias WHERE id_grupo = :id
             ORDER BY FIELD(dia_semana, 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado')"
        );
        $statement->execute(['id' => $grupoId]);

        return array_column($statement->fetchAll(), 'dia_semana');
    }

    private function describe(array $group): string
    {
        $dias = !empty($group['dias']) ? implode('/', $group['dias']) : $group['dia_semana'];

        return sprintf(
            'Tutor %s · %s %s-%s · %s (%s)',
            $group['tutor'],
            $dias,
            substr((string) $group['hora_inicio'], 0, 5),
            substr((string) $group['hora_fin'], 0, 5),
            $group['aula'],
            ucfirst((string) $group['modalidad'])
        );
    }

    private function matterName(int $matterId): string
    {
        $statement = Database::connection()->prepare('SELECT nombre_materia FROM materias WHERE id_materia = :id LIMIT 1');
        $statement->execute(['id' => $matterId]);

        return (string) ($statement->fetchColumn() ?: ('Materia #' . $matterId));
    }
}
