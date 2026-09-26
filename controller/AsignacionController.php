<?php

declare(strict_types=1);

/**
 * Motor de asignacion automatica: el estudiante declara materias y el sistema
 * lo coloca en un grupo compatible (existente o nuevo) respetando cupo y conflictos.
 * Reglas deterministas: cupo libre + una sola tutoria por turno para el estudiante
 * y para el tutor (sin importar los dias: un grupo reducido debe poder pasar a
 * Lunes a Viernes).
 *
 * Al crear un grupo nuevo, el horario (turno) sale de la oferta aprobada del
 * tutor para esa materia (TutorMateriaConfig: turnos, modalidad, cupo
 * recomendado) y los dias de la regla institucional de frecuencia por demanda
 * (Grupo::diasPorDemanda, db/033). Un tutor sin oferta aprobada para la materia
 * no ofrece grupos en ella.
 *
 * El motor no reserva aulas (db/029): no conoce la ocupacion real de la
 * universidad. La modalidad sale de la materia, el tutor y el periodo
 * (TutorMateriaConfig::resolverModalidad), el grupo recibe el espacio
 * predeterminado de esa modalidad y el aula o el enlace los registra la
 * coordinacion al aprobarlo (GruposController::aprobar).
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
     * Regla institucional: el estudiante lleva UNA sola tutoria por periodo (la
     * tutoria coincide con la ultima materia del semestre y la UPDS permite dos
     * materias al mes). Devuelve la materia que ya ocupa ese lugar, sea una
     * inscripcion vigente o una solicitud en espera, o null si esta libre:
     * ['id_materia', 'nombre', 'tipo' => 'inscripcion'|'espera'].
     */
    public function tutoriaDelPeriodo(int $studentId, int $periodoId): ?array
    {
        $inscritas = $this->inscripciones->studentMatterIds($studentId, $periodoId);
        if ($inscritas !== []) {
            return ['id_materia' => $inscritas[0], 'nombre' => $this->matterName($inscritas[0]), 'tipo' => 'inscripcion'];
        }
        $enEspera = array_keys($this->demanda->pendingForStudent($studentId, $periodoId));
        if ($enEspera !== []) {
            $id = (int) $enEspera[0];
            return ['id_materia' => $id, 'nombre' => $this->matterName($id), 'tipo' => 'espera'];
        }

        return null;
    }

    /** Resultado 'ya_solicitada' cuando el estudiante ya tiene su tutoria del periodo. */
    private function unaTutoria(int $matterId, array $actual): array
    {
        $mismo = $actual['id_materia'] === $matterId;
        $detalle = $mismo
            ? ($actual['tipo'] === 'inscripcion' ? 'Ya estás inscrito en esta materia.' : 'Ya tienes una solicitud en espera para esta materia.')
            : sprintf(
                'Solo puedes llevar una tutoría por período y ya tienes %s en %s.%s',
                $actual['tipo'] === 'inscripcion' ? 'un grupo' : 'una solicitud',
                $actual['nombre'],
                $actual['tipo'] === 'espera' ? ' Para cambiar de materia, primero quítala de la espera.' : ''
            );

        return ['id_materia' => $matterId, 'nombre' => $this->matterName($matterId), 'resultado' => 'ya_solicitada', 'detalle' => $detalle];
    }

    /**
     * Procesa la solicitud de apoyo del estudiante: una sola materia por periodo
     * (tutoriaDelPeriodo). Devuelve un resumen: [[id_materia, nombre, resultado, detalle]].
     * Una materia sin oferta (sin tutor con horarios configurados) no pasa por el motor:
     * se registra como interes para que la universidad mida la demanda real.
     */
    public function solicitarApoyo(int $studentId, array $matterIds, array $periodo): array
    {
        $periodoId = (int) $periodo['id_periodo'];
        $cupoMin = (int) $periodo['cupo_min_grupo'];
        $cupoMax = (int) $periodo['cupo_max_default'];
        $results = [];

        $matterIds = array_values(array_filter(array_map('intval', $matterIds), static fn (int $id): bool => $id > 0));
        if (count($matterIds) > 1) {
            return [['id_materia' => 0, 'nombre' => 'Solicitud', 'resultado' => 'ya_solicitada', 'detalle' => 'Solo puedes llevar una tutoría por período: elige una sola materia.']];
        }
        if ($matterIds !== [] && ($actual = $this->tutoriaDelPeriodo($studentId, $periodoId)) !== null) {
            return [$this->unaTutoria($matterIds[0], $actual)];
        }

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
                $this->alertarCupoCompleto($periodoId, $matterId);
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

        if (($actual = $this->tutoriaDelPeriodo($studentId, $periodoId)) !== null) {
            return $this->unaTutoria($matterId, $actual);
        }
        if ($this->hasOffer($matterId, $periodoId)) {
            // Tiene oferta: corresponde solicitar apoyo, no registrar interes.
            return $this->solicitarApoyo($studentId, [$matterId], $periodo)[0];
        }

        $this->demanda->record($periodoId, $matterId, $studentId, Demanda::MOTIVO_SIN_TUTOR);
        $this->alertarCupoCompleto($periodoId, $matterId);

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
     * Reprocesa la cola de espera de una materia en el periodo indicado o, sin
     * periodo, en todos los periodos activos (uno por tipo de tutoria, db/043): el
     * motor no depende del tipo que el usuario tenga elegido en el portal.
     * $allowCreate=false limita a grupos ya existentes: se usa tras una cancelacion
     * administrativa para no recrear al instante el grupo que el admin acaba de cancelar.
     * Devuelve [atendidos => n, pendientes => n].
     */
    /**
     * Reproceso tras un cambio en un grupo (cancelacion, cambio de tutor, division):
     * solo en el periodo de ese grupo. Sin id_periodo, en todos los activos.
     */
    public function reprocesarMateriaDelGrupo(array $grupo, bool $allowCreate = true): array
    {
        $periodo = isset($grupo['id_periodo']) ? (new Periodo())->findById((int) $grupo['id_periodo']) : null;
        if (isset($grupo['id_periodo']) && $periodo === null) {
            return ['atendidos' => 0, 'pendientes' => 0];
        }

        return $this->reprocesarMateria((int) $grupo['id_materia'], $periodo, null, $allowCreate);
    }

    public function reprocesarMateria(int $matterId, ?array $periodo = null, ?int $excludeStudentId = null, bool $allowCreate = true): array
    {
        $summary = ['atendidos' => 0, 'pendientes' => 0];
        if ($periodo === null) {
            try {
                $activos = (new Periodo())->activas();
            } catch (Throwable $exception) {
                error_log('Reproceso de demanda: ' . $exception->getMessage());
                return $summary;
            }
            foreach ($activos as $activo) {
                $parcial = $this->reprocesarMateria($matterId, $activo, $excludeStudentId, $allowCreate);
                $summary['atendidos'] += $parcial['atendidos'];
                $summary['pendientes'] += $parcial['pendientes'];
            }
            return $summary;
        }
        try {
            if ($periodo['estado'] !== 'activa') {
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
                    // Sigue sin grupo: el motivo se actualiza (esperando companeros o sin
                    // horario). Un 'grupo_cancelado' se conserva: es la senal que el admin necesita ver.
                    if ($pending['motivo'] !== Demanda::MOTIVO_GRUPO_CANCELADO && isset($outcome['motivo'])) {
                        $this->demanda->updateMotivo($periodoId, $matterId, $studentId, $outcome['motivo']);
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

        return (new TutorMateriaConfig())->hasApprovedOffer($matterId, $periodoId);
    }

    private function assignMatter(int $studentId, int $matterId, int $periodoId, int $cupoMin, int $cupoMax, bool $reprocessing = false, bool $allowCreate = true): array
    {
        $busy = $this->inscripciones->studentBusySlots($studentId, $periodoId);

        // 1) Intentar grupos existentes con cupo.
        foreach ($this->grupos->candidatesForMatter($periodoId, $matterId) as $candidate) {
            if ($this->ocupaTurno($busy, $candidate['hora_inicio'], $candidate['hora_fin'])) {
                continue;
            }
            $group = $this->enrollInExisting((int) $candidate['id_grupo'], $studentId, $cupoMin);
            if ($group !== null) {
                $this->demanda->markAttended($periodoId, $matterId, $studentId);
                $this->emitNotifications($group, $studentId, $cupoMin);
                $this->alertarCupoCompleto($periodoId, $matterId);
                return ['resultado' => 'asignado', 'detalle' => $this->describeForStudent($group)];
            }
        }

        // 2) Formar un grupo nuevo si hay quorum (minimo del periodo) en algun turno.
        [$group, $hayTurno, $reunidos] = $allowCreate
            ? $this->formarGrupo($studentId, $matterId, $periodoId, $cupoMin, $cupoMax, $busy)
            : [null, false, 0];
        if ($group !== null) {
            $this->demanda->markAttended($periodoId, $matterId, $studentId);
            $this->emitNotifications($group, $studentId, $cupoMin);
            return ['resultado' => 'grupo_creado', 'detalle' => $this->describeForStudent($group)];
        }

        // 3) Sin grupo: interes registrado. Si hay tutor libre en un turno del estudiante,
        // espera companeros; si no, espera un horario compatible.
        // En reproceso la fila ya existe y su motivo lo administra reprocesarMateria().
        $motivo = $hayTurno ? Demanda::MOTIVO_ESPERANDO : Demanda::MOTIVO_SIN_HORARIO;
        if (!$reprocessing) {
            $this->demanda->record($periodoId, $matterId, $studentId, $motivo);
        }
        $this->alertarCupoCompleto($periodoId, $matterId);
        $detalle = $hayTurno
            ? sprintf('Interés registrado: el grupo se abre cuando haya %d estudiantes en tu turno (hoy: %d). Te avisaremos cuando se forme.', $cupoMin, $reunidos)
            : 'Hay tutor para esta materia, pero ningún horario compatible con tu agenda por ahora. Quedaste en lista de espera.';

        return ['resultado' => 'lista_espera', 'motivo' => $motivo, 'detalle' => $detalle];
    }

    private function alertarCupoCompleto(int $periodoId, int $materiaId): void
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("SELECT id_grupo, hora_inicio FROM grupos_tutoria
                WHERE id_periodo = :p AND id_materia = :m AND estado IN ('por_aprobar','formacion','confirmado','en_curso')
                  AND cupo_ocupado >= cupo_max AND EXISTS
                    (SELECT 1 FROM demanda_tutoria d WHERE d.id_periodo = :p2 AND d.id_materia = :m2 AND d.estado = 'pendiente')");
            $stmt->execute(['p' => $periodoId, 'm' => $materiaId, 'p2' => $periodoId, 'm2' => $materiaId]);
            foreach ($stmt->fetchAll() as $grupo) {
                $turno = TutorMateriaConfig::turnoDeHora((string) $grupo['hora_inicio']);
                (new Notificacion())->notifyAdminsCupoCompleto($pdo, (int) $grupo['id_grupo'], $this->matterName($materiaId),
                    TutorMateriaConfig::TURNOS[$turno]['label'] ?? substr((string) $grupo['hora_inicio'], 0, 5));
            }
        } catch (Throwable $exception) {
            error_log('Alerta cupo completo: ' . $exception->getMessage());
        }
    }

    /** Inscribe al estudiante en un grupo existente con bloqueo de cupo. Devuelve datos del grupo o null. */
    private function enrollInExisting(int $grupoId, int $studentId, int $cupoMin): ?array
    {
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $grupo = $this->grupos->lockForEnroll($grupoId);
            if ($grupo === null
                || !in_array($grupo['estado'], ['por_aprobar', 'formacion', 'confirmado', 'en_curso'], true)
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
     * Forma un grupo nuevo solo con quorum (db/035): el estudiante mas los que ya
     * esperan la materia y estan libres en ese turno deben sumar al menos el minimo
     * del periodo (cupo_min_grupo, por defecto 3). Con menos no se crea nada: quedan
     * como "interes registrado" esperando companeros.
     * Se evalua cada bloque (tutor, turno) con oferta aprobada, libre para el tutor y
     * para el estudiante; gana el que reune mas estudiantes. La frecuencia sale de
     * cuantos forman el grupo (Grupo::diasPorDemanda): menos de 8 -> LMV (la
     * coordinacion puede pasarlo a MJS), 8 o mas -> Lunes a Viernes.
     * Devuelve [grupo|null, hay un turno compatible, estudiantes reunidos en el mejor turno].
     */
    private function formarGrupo(int $studentId, int $matterId, int $periodoId, int $cupoMin, int $cupoMax, array $busy): array
    {
        $periodo = (new Periodo())->findById($periodoId);
        if ($periodo === null) {
            return [null, false, 0];
        }
        $mejor = $this->mejorBloque($studentId, $matterId, $periodoId, $cupoMax, $busy);
        if ($mejor === null) {
            return [null, false, 0];
        }
        $reunidos = 1 + count($mejor['companeros']);
        if ($reunidos < $cupoMin) {
            return [null, true, $reunidos];
        }

        $dias = Grupo::diasPorDemanda($reunidos)[0];
        $grupoId = $this->confirmarGrupo($mejor['bloque'], $dias, array_merge([$studentId], $mejor['companeros']), $matterId, $periodoId, $cupoMin, $cupoMax, $periodo);
        if ($grupoId === null) {
            return [null, true, $reunidos];
        }
        $grupo = $this->groupDetail($grupoId);
        // Los companeros que esperaban reciben su grupo: su demanda queda atendida.
        foreach ($mejor['companeros'] as $id) {
            $this->demanda->markAttended($periodoId, $matterId, $id);
            if ($grupo !== null) {
                $this->notifyDemandAttended($id, $matterId, $this->describeForStudent($grupo));
            }
        }

        return [$grupo, true, $reunidos];
    }

    /**
     * Avance del interes de un estudiante que espera una materia (pantallas de
     * solicitud y Mis tutorias): cuantos estudiantes, contandolo a el, reuniria hoy
     * el mejor turno compatible. null si no hay tutor libre en ningun turno suyo.
     */
    public function progresoInteres(int $studentId, int $matterId, int $periodoId, int $cupoMax): ?int
    {
        $mejor = $this->mejorBloque($studentId, $matterId, $periodoId, $cupoMax, $this->inscripciones->studentBusySlots($studentId, $periodoId));

        return $mejor === null ? null : 1 + count($mejor['companeros']);
    }

    /**
     * Bloque (tutor, turno) con oferta aprobada, libre para el tutor (sin llegar a su
     * tope de grupos del periodo) y para el estudiante, que reune mas companeros en
     * espera; null si no hay ninguno compatible.
     * Devuelve ['bloque' => ..., 'companeros' => [id_estudiante...]].
     */
    private function mejorBloque(int $studentId, int $matterId, int $periodoId, int $cupoMax, array $busy): ?array
    {
        // Combinaciones (tutor, horario) que la coordinacion ya rechazo para esta materia
        // en el periodo: no se vuelven a proponer (db/028, grupo_rechazos).
        $rechazados = $this->grupos->rejectedSlotKeys($periodoId, $matterId);
        $bloques = array_values(array_filter(
            (new TutorMateriaConfig())->slotsForMatter($matterId, $periodoId),
            static fn (array $b): bool => !isset($rechazados[Grupo::slotKey($b['id_tutor'], $b['hora_inicio'], $b['hora_fin'])])
        ));

        // Companeros posibles: quienes esperan esta materia (en orden de llegada) y aun
        // no tienen grupo en ella.
        $esperando = [];
        foreach ($this->demanda->pendingForMatter($periodoId, $matterId) as $pendiente) {
            $id = (int) $pendiente['id_estudiante'];
            if ($id === $studentId || in_array($matterId, $this->inscripciones->studentMatterIds($id, $periodoId), true)) {
                continue;
            }
            $esperando[$id] = $this->inscripciones->studentBusySlots($id, $periodoId);
        }

        // Tope de carga del tutor (db/037): como maximo max_grupos_tutor grupos en el
        // periodo, siempre en turnos distintos (tutorOcupaTurno).
        $periodo = (new Periodo())->findById($periodoId);
        $maxGrupos = min(2, (int) ($periodo['max_grupos_tutor'] ?? 2));
        $tutoresLlenos = [];

        $mejor = null;
        foreach ($bloques as $bloque) {
            $tutorId = $bloque['id_tutor'];
            $tutoresLlenos[$tutorId] ??= $this->grupos->countTutorGrupos($tutorId, $periodoId) >= $maxGrupos;
            if ($tutoresLlenos[$tutorId]
                || $this->ocupaTurno($busy, $bloque['hora_inicio'], $bloque['hora_fin'])
                || $this->grupos->tutorOcupaTurno($tutorId, $bloque['hora_inicio'], $bloque['hora_fin'], $periodoId)) {
                continue;
            }
            $cupoGrupo = $bloque['cupo_recomendado'] !== null ? min($cupoMax, $bloque['cupo_recomendado']) : $cupoMax;
            $companeros = [];
            foreach ($esperando as $id => $ocupado) {
                if (count($companeros) >= $cupoGrupo - 1) {
                    break;
                }
                if (!$this->ocupaTurno($ocupado, $bloque['hora_inicio'], $bloque['hora_fin'])) {
                    $companeros[] = $id;
                }
            }
            if ($mejor === null || count($companeros) > count($mejor['companeros'])) {
                $mejor = ['bloque' => $bloque, 'companeros' => $companeros];
            }
        }

        return $mejor;
    }

    /** Crea el grupo por aprobar con el patron de dias ya decidido e inscribe a los estudiantes que lo forman. */
    private function confirmarGrupo(array $pattern, array $dias, array $studentIds, int $matterId, int $periodoId, int $cupoMin, int $cupoMax, array $periodo): ?int
    {
        // Modalidad academica (materia > tutor > regla del periodo), nunca la de un aula.
        $modalidad = TutorMateriaConfig::resolverModalidad(
            (string) $pattern['modalidad_requerida'],
            (string) ($pattern['modalidad_preferida'] ?? 'ambas'),
            (string) ($periodo['modalidad_ambas'] ?? 'virtual')
        );
        if ($modalidad === null) {
            return null;
        }
        // Espacio provisional: el predeterminado de la modalidad. La ubicacion real
        // (espacio, aula o enlace) la define la coordinacion al revisar el grupo;
        // la oferta del tutor no lleva espacio (db/034).
        $espacio = (new EspacioTutoria())->predeterminado($modalidad);
        if ($espacio === null) {
            error_log('Sin espacio de tutoria activo para la modalidad ' . $modalidad . '.');
            return null;
        }
        // Cupo: regla del periodo y, si el tutor la declaro, su capacidad recomendada.
        $cupoGrupo = $cupoMax;
        if ($pattern['cupo_recomendado'] !== null) {
            $cupoGrupo = min($cupoGrupo, $pattern['cupo_recomendado']);
        }

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $lockTutor = $connection->prepare('SELECT id_tutor FROM tutores WHERE id_tutor = :t FOR UPDATE');
            $lockTutor->execute(['t' => $pattern['id_tutor']]);
            if ($this->grupos->countTutorGrupos((int) $pattern['id_tutor'], $periodoId) >= min(2, (int) $periodo['max_grupos_tutor'])
                || $this->grupos->tutorOcupaTurno((int) $pattern['id_tutor'], (string) $pattern['hora_inicio'], (string) $pattern['hora_fin'], $periodoId)
                || !in_array($periodo['estado'], ['activa'], true)) {
                $connection->rollBack();
                return null;
            }
            $grupoId = $this->grupos->create([
                'id_periodo' => $periodoId,
                'id_materia' => $matterId,
                'id_tutor' => $pattern['id_tutor'],
                'id_espacio' => (int) $espacio['id_espacio'],
                'modalidad' => $modalidad,
                'dias' => $dias,
                'hora_inicio' => $pattern['hora_inicio'],
                'hora_fin' => $pattern['hora_fin'],
                'cupo_max' => $cupoGrupo,
                'estado' => 'por_aprobar',
            ]);
            // Las sesiones se generan al aprobar (GruposController::aprobar): un grupo
            // por aprobar no tiene calendario ni asistencia.
            foreach ($studentIds as $studentId) {
                $this->inscripciones->create($grupoId, $studentId, 'inscrito', 'auto');
                $this->grupos->registerEnrollment($grupoId, $cupoMin);
            }
            $this->historial->log($connection, $grupoId, 'creado', null, 'por_aprobar', null, sprintf(
                'Grupo formado por el sistema con %d estudiantes (%s); espera la revisión de la coordinación.',
                count($studentIds),
                implode('/', $dias)
            ));
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log($exception->getMessage());

            return null;
        }

        try {
            $detail = $this->groupDetail($grupoId);
            if ($detail !== null) {
                (new Notificacion())->notifyAdminsGroupPending($connection, $grupoId, (string) $detail['nombre_materia'], $this->describe($detail));
            }
        } catch (Throwable $exception) {
            error_log('Notificacion grupo por aprobar: ' . $exception->getMessage());
        }

        return $grupoId;
    }

    /**
     * Regla institucional: el estudiante tiene como maximo una tutoria por turno,
     * sin importar los dias. Asi un grupo reducido puede pasar a Lunes a Viernes
     * sin chocar con otra tutoria del mismo estudiante.
     */
    private function ocupaTurno(array $busy, string $horaInicio, string $horaFin): bool
    {
        foreach ($busy as $slot) {
            if ($slot['hora_inicio'] < $horaFin && $slot['hora_fin'] > $horaInicio) {
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
            if ($studentUserId !== null && $group['estado'] === 'por_aprobar') {
                $notif->notifyPreassignment($pdo, (int) $group['id_grupo'], $studentUserId, (string) $group['nombre_materia'], $this->describe($group));
            } elseif ($studentUserId !== null) {
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

    private function groupDetail(int $grupoId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT g.id_grupo, g.cupo_ocupado, g.estado, g.dia_semana, g.hora_inicio, g.hora_fin, g.modalidad,
                    g.ubicacion, g.enlace, m.nombre_materia, e.nombre AS espacio, CONCAT(u.nombre, ' ', u.apellido) AS tutor
             FROM grupos_tutoria g
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN espacios_tutoria e ON e.id_espacio = g.id_espacio
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

        // El enlace no viaja en notificaciones: se ve en "Mis tutorias" una vez aprobado el grupo.
        $lugar = $group['modalidad'] === 'presencial' && !ubicacion_pendiente($group)
            ? $group['espacio'] . ': ' . $group['ubicacion']
            : $group['espacio'] . (ubicacion_pendiente($group) ? ', ubicación por confirmar' : '');

        return sprintf(
            'Tutor %s · %s %s-%s · %s (%s)',
            $group['tutor'],
            $dias,
            substr((string) $group['hora_inicio'], 0, 5),
            substr((string) $group['hora_fin'], 0, 5),
            ucfirst((string) $group['modalidad']),
            $lugar
        );
    }

    /** Descripcion para el estudiante: aclara si el grupo aun espera el visto bueno. */
    private function describeForStudent(array $group): string
    {
        $texto = $this->describe($group);

        return $group['estado'] === 'por_aprobar'
            ? $texto . ' — Preasignado: pendiente de aprobación de la coordinación académica.'
            : $texto;
    }

    private function matterName(int $matterId): string
    {
        $statement = Database::connection()->prepare('SELECT nombre_materia FROM materias WHERE id_materia = :id LIMIT 1');
        $statement->execute(['id' => $matterId]);

        return (string) ($statement->fetchColumn() ?: ('Materia #' . $matterId));
    }
}
