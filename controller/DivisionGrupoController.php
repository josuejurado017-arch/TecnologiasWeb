<?php

declare(strict_types=1);

/**
 * Division de un grupo lleno en dos grupos parejos (db/042).
 *
 * La coordinacion elige un segundo tutor y ve la vista previa; el tutor recibe la
 * propuesta y, al aceptarla, se crea el grupo nuevo en el mismo turno y con los
 * mismos dias: pasan los ultimos en inscribirse y entran quienes esperaban la
 * materia. El horario de los trasladados no cambia; cambian el tutor y la
 * ubicacion, que la coordinacion define para el grupo nuevo.
 * Solo se divide un grupo sin asistencia registrada: moverlo despues partiria su
 * historial de asistencia.
 */
final class DivisionGrupoController
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
     * Motivo por el que el grupo no se puede dividir, o null. Al responder el tutor
     * ya no se exige que siga lleno (alguien pudo retirarse): basta con que el
     * reparto deje dos grupos con el minimo.
     */
    public function bloqueoGrupo(array $grupo, bool $alResponder = false): ?string
    {
        if (!in_array($grupo['estado'], Grupo::ESTADOS_VIGENTES, true)) {
            return 'Solo se puede dividir un grupo vigente.';
        }
        if (!$alResponder && (int) $grupo['cupo_ocupado'] < (int) $grupo['cupo_max']) {
            return 'Solo se divide un grupo lleno. Mientras tenga cupo, los estudiantes nuevos entran en él.';
        }
        $asistencia = Database::connection()->prepare(
            "SELECT COUNT(*) FROM sesiones_tutoria s
             WHERE s.id_grupo = :id AND (s.estado = 'realizada' OR EXISTS (SELECT 1 FROM asistencias_sesion a WHERE a.id_sesion = s.id_sesion))"
        );
        $asistencia->execute(['id' => $grupo['id_grupo']]);
        if ((int) $asistencia->fetchColumn() > 0) {
            return 'El grupo ya tiene asistencia registrada: dividirlo partiría su historial. Para quienes esperan, usa "Proponer otro tutor".';
        }
        if (!$alResponder && $this->pendiente((int) $grupo['id_grupo']) !== null) {
            return 'Ya hay una propuesta de división esperando la respuesta del tutor.';
        }

        return null;
    }

    /** Propuesta de division que espera respuesta para el grupo, o null. */
    public function pendiente(int $grupoId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT d.*, CONCAT(u.nombre, ' ', u.apellido) AS tutor
             FROM grupo_divisiones d INNER JOIN tutores t ON t.id_tutor = d.id_tutor INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE d.id_grupo_origen = :id AND d.estado = 'pendiente' LIMIT 1"
        );
        $statement->execute(['id' => $grupoId]);

        return $statement->fetch() ?: null;
    }

    /** Propuestas pendientes de un tutor, con los datos del grupo y el reparto estimado. */
    public function pendientesDelTutor(int $tutorUserId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT d.id_division, d.id_grupo_origen, d.fecha_solicitud
             FROM grupo_divisiones d INNER JOIN tutores t ON t.id_tutor = d.id_tutor
             WHERE t.id_usuario = :u AND d.estado = 'pendiente' ORDER BY d.fecha_solicitud"
        );
        $statement->execute(['u' => $tutorUserId]);
        $propuestas = [];
        foreach ($statement->fetchAll() as $d) {
            $grupo = $this->grupos->findBasic((int) $d['id_grupo_origen']);
            if ($grupo === null) {
                continue;
            }
            $propuestas[] = $d + [
                'grupo' => $grupo,
                'dias' => $this->grupos->dias((int) $grupo['id_grupo']),
                'plan' => $this->plan($grupo, true),
            ];
        }

        return $propuestas;
    }

    /** Tutores que pueden tomar la mitad del grupo, con su carga del periodo. */
    public function tutoresElegibles(array $grupo): array
    {
        $pdo = Database::connection();
        $habilitado = TutorMateriaConfig::sqlTutorHabilitado();
        $statement = $pdo->prepare(
            "SELECT t.id_tutor, CONCAT(u.nombre, ' ', u.apellido) AS tutor, t.especialidad,
                    (SELECT COUNT(*) FROM grupos_tutoria g WHERE g.id_tutor = t.id_tutor AND g.id_periodo = :p AND g.estado <> 'cancelado') AS grupos_periodo
             FROM tutores t INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND {$habilitado}
             WHERE t.id_tutor <> :actual ORDER BY u.apellido, u.nombre"
        );
        $statement->execute(['p' => $grupo['id_periodo'], 'actual' => $grupo['id_tutor']]);
        $elegibles = [];
        foreach ($statement->fetchAll() as $tutor) {
            if ($this->bloqueoTutor($pdo, (int) $tutor['id_tutor'], $grupo) === null) {
                $elegibles[] = $tutor;
            }
        }

        return $elegibles;
    }

    /** Motivo por el que el tutor no puede tomar la mitad del grupo, o null. */
    public function bloqueoTutor(PDO $pdo, int $tutorId, array $grupo): ?string
    {
        if ($tutorId === (int) $grupo['id_tutor']) {
            return 'Elige un tutor distinto del que dicta el grupo.';
        }
        $tutor = (new Tutor())->findById($tutorId);
        if ($tutor === null || $tutor['estado'] !== 'activo' || $tutor['estado_docente'] !== 'aprobado') {
            return 'El tutor no está habilitado.';
        }
        if ($this->grupos->tutorOcupaTurno($tutorId, (string) $grupo['hora_inicio'], (string) $grupo['hora_fin'], (int) $grupo['id_periodo'])) {
            return 'El tutor ya tiene un grupo en ese turno.';
        }
        $turno = TutorMateriaConfig::turnoDeHora((string) $grupo['hora_inicio']);
        if ($turno === null) {
            return 'El grupo no está en un turno reconocido.';
        }

        return (new TutorMateriaConfig())->validarDivision($pdo, $tutorId, (int) $grupo['id_materia'], (int) $grupo['id_periodo'], $turno);
    }

    /**
     * Reparto parejo: el grupo de origen conserva a los primeros en inscribirse, al
     * nuevo pasan los ultimos y se suman quienes esperan la materia (en orden de
     * llegada) hasta equilibrar. Ninguno de los dos queda bajo el minimo del periodo.
     * Devuelve [quedan, pasan, desde_espera, siguen_esperando, error].
     */
    public function plan(array $grupo, bool $alResponder = false): array
    {
        $periodo = (new Periodo())->findById((int) $grupo['id_periodo']);
        $minimo = (int) ($periodo['cupo_min_grupo'] ?? 3);
        $cupo = (int) $grupo['cupo_max'];

        $statement = Database::connection()->prepare(
            "SELECT i.id_inscripcion, i.id_estudiante, u.id_usuario, CONCAT(u.nombre, ' ', u.apellido) AS estudiante, i.fecha_inscripcion
             FROM inscripciones i INNER JOIN estudiantes e ON e.id_estudiante = i.id_estudiante INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
             WHERE i.id_grupo = :id AND i.estado = 'inscrito'
             ORDER BY i.fecha_inscripcion ASC, i.id_inscripcion ASC"
        );
        $statement->execute(['id' => $grupo['id_grupo']]);
        $inscritos = $statement->fetchAll();

        $espera = [];
        foreach ($this->demanda->pendingForMatter((int) $grupo['id_periodo'], (int) $grupo['id_materia']) as $pendiente) {
            $est = $this->inscripciones->estudiante((int) $pendiente['id_estudiante']);
            if ($est === null || $est['estado'] !== 'activo') {
                continue;
            }
            foreach ($this->inscripciones->studentBusySlots((int) $est['id_estudiante'], (int) $grupo['id_periodo']) as $slot) {
                if ($slot['hora_inicio'] < $grupo['hora_fin'] && $slot['hora_fin'] > $grupo['hora_inicio']) {
                    continue 2;
                }
            }
            $espera[] = $est;
        }

        $n = count($inscritos);
        $total = $n + count($espera);
        $quedanN = min($n, (int) ceil($total / 2));
        $nuevoN = min($cupo, $total - $quedanN);
        $pasanN = $n - $quedanN;
        $desdeEsperaN = max(0, $nuevoN - $pasanN);

        $error = null;
        if ($quedanN < $minimo || $nuevoN < $minimo) {
            $error = sprintf('Con %d estudiante(s) (%d inscritos y %d en espera) no alcanza para dos grupos de al menos %d.', $total, $n, count($espera), $minimo);
        }

        return [
            'quedan' => array_slice($inscritos, 0, $quedanN),
            'pasan' => array_slice($inscritos, $quedanN),
            'desde_espera' => array_slice($espera, 0, $desdeEsperaN),
            'siguen_esperando' => count($espera) - $desdeEsperaN,
            'error' => $error,
        ];
    }

    /** La coordinacion envia la propuesta de division al tutor elegido. */
    public function proponer(int $grupoId, int $tutorId, int $adminUserId): ?string
    {
        $grupo = $this->grupos->findBasic($grupoId);
        if ($grupo === null) {
            return 'El grupo no existe.';
        }
        if (($bloqueo = $this->bloqueoGrupo($grupo)) !== null) {
            return $bloqueo;
        }
        if (($error = $this->plan($grupo)['error']) !== null) {
            return $error;
        }
        $pdo = Database::connection();
        if (($bloqueo = $this->bloqueoTutor($pdo, $tutorId, $grupo)) !== null) {
            return $bloqueo;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO grupo_divisiones (id_grupo_origen, id_tutor, id_usuario_solicitud) VALUES (:g, :t, :u)')
                ->execute(['g' => $grupoId, 't' => $tutorId, 'u' => $adminUserId]);
            $divisionId = (int) $pdo->lastInsertId();
            $tutor = (new Tutor())->findById($tutorId);
            $eventoId = $this->historial->log($pdo, $grupoId, 'division_propuesta', $grupo['estado'], $grupo['estado'], $adminUserId,
                'Propuesta de división enviada a ' . $tutor['nombre'] . ' ' . $tutor['apellido'] . '.');
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Proponer division grupo ' . $grupoId . ': ' . $exception->getMessage());
            return 'No se pudo enviar la propuesta de división.';
        }

        try {
            (new Notificacion())->notifyEventoGrupo($pdo, (int) $tutor['id_usuario'], $grupoId, 'division_propuesta', 'Propuesta: tomar la mitad de un grupo',
                'La coordinación te propone dictar la mitad del grupo de ' . $grupo['nombre_materia'] . ' (' . implode('/', $this->grupos->dias($grupoId)) . ' '
                . substr((string) $grupo['hora_inicio'], 0, 5) . '). Acéptala o recházala en Mis grupos.', '/mis-grupos/#divisiones', $eventoId);
        } catch (Throwable $exception) {
            error_log('Notificacion propuesta de division: ' . $exception->getMessage());
        }

        return null;
    }

    /** La coordinacion retira una propuesta que aun no fue respondida. */
    public function cancelar(int $divisionId, int $adminUserId): ?string
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare("UPDATE grupo_divisiones SET estado = 'cancelada', motivo = 'Retirada por la coordinación.', fecha_respuesta = NOW()
                                    WHERE id_division = :id AND estado = 'pendiente'");
        $statement->execute(['id' => $divisionId]);
        if ($statement->rowCount() === 0) {
            return 'La propuesta ya no está pendiente.';
        }
        $division = $this->division($divisionId);
        $this->historial->log($pdo, (int) $division['id_grupo_origen'], 'division_cancelada', null, null, $adminUserId, 'La coordinación retiró la propuesta de división.');

        return null;
    }

    /** El tutor acepta (se ejecuta la division) o rechaza (con motivo) la propuesta. */
    public function responder(int $divisionId, int $tutorUserId, bool $acepta, string $motivo): ?string
    {
        $division = $this->division($divisionId);
        $tutorId = (new Tutor())->findIdByUserId($tutorUserId);
        if ($division === null || $tutorId === null || (int) $division['id_tutor'] !== $tutorId) {
            return 'La propuesta no existe.';
        }
        if ($division['estado'] !== 'pendiente') {
            return 'Esta propuesta ya no está esperando tu respuesta.';
        }
        $pdo = Database::connection();
        $grupoId = (int) $division['id_grupo_origen'];

        if (!$acepta) {
            $motivo = trim(preg_replace('/\s+/u', ' ', $motivo) ?? '');
            if (mb_strlen($motivo) < 10 || mb_strlen($motivo) > 300 || preg_match('/[\x00-\x1F\x7F]/', $motivo)) {
                return 'Indica el motivo del rechazo (entre 10 y 300 caracteres).';
            }
            $pdo->prepare("UPDATE grupo_divisiones SET estado = 'rechazada', motivo = :m, fecha_respuesta = NOW() WHERE id_division = :id AND estado = 'pendiente'")
                ->execute(['m' => $motivo, 'id' => $divisionId]);
            $this->historial->log($pdo, $grupoId, 'division_rechazada', null, null, $tutorUserId, 'El tutor rechazó la división: ' . $motivo);
            try {
                $grupo = $this->grupos->findBasic($grupoId);
                (new Notificacion())->notifyAdminsDivision($pdo, $divisionId, 'division_rechazada', 'División rechazada',
                    'El tutor no aceptó dividir el grupo de ' . ($grupo['nombre_materia'] ?? '') . '. Motivo: ' . $motivo, '/grupos/ubicacion.php?grupo=' . $grupoId . '#dividir');
            } catch (Throwable $exception) {
                error_log('Notificacion division rechazada: ' . $exception->getMessage());
            }

            return null;
        }

        return $this->ejecutar($division, $tutorId, $tutorUserId);
    }

    /** Crea el grupo nuevo y traslada estudiantes en una sola transaccion. */
    private function ejecutar(array $division, int $tutorId, int $tutorUserId): ?string
    {
        $pdo = Database::connection();
        $grupoId = (int) $division['id_grupo_origen'];
        $pdo->beginTransaction();
        try {
            $lock = $this->grupos->lockForEnroll($grupoId);
            $grupo = $this->grupos->findBasic($grupoId);
            $bloqueo = $lock === null || $grupo === null ? 'El grupo ya no existe.' : $this->bloqueoGrupo($grupo, true);
            $plan = $bloqueo === null ? $this->plan($grupo, true) : null;
            $bloqueo ??= $plan['error'] ?? null;
            $bloqueo ??= $this->bloqueoTutor($pdo, $tutorId, $grupo);
            if ($bloqueo !== null) {
                $pdo->rollBack();
                // La propuesta ya no se puede cumplir: se cierra para que la coordinacion decida.
                $pdo->prepare("UPDATE grupo_divisiones SET estado = 'cancelada', motivo = :m, fecha_respuesta = NOW() WHERE id_division = :id AND estado = 'pendiente'")
                    ->execute(['m' => mb_substr('No se pudo ejecutar: ' . $bloqueo, 0, 300), 'id' => $division['id_division']]);
                try {
                    (new Notificacion())->notifyAdminsDivision($pdo, (int) $division['id_division'], 'division_fallida', 'La división no se pudo hacer',
                        'El tutor aceptó, pero la división del grupo #' . $grupoId . ' ya no era posible: ' . $bloqueo, '/grupos/ubicacion.php?grupo=' . $grupoId . '#dividir');
                } catch (Throwable $exception) {
                    error_log('Notificacion division fallida: ' . $exception->getMessage());
                }

                return 'La división ya no se puede hacer: ' . $bloqueo . ' Se avisó a la coordinación.';
            }

            $periodo = (new Periodo())->findById((int) $grupo['id_periodo']);
            $turno = TutorMateriaConfig::turnoDeHora((string) $grupo['hora_inicio']);
            (new TutorMateriaConfig())->aprobarPorDivision($pdo, $tutorId, (int) $grupo['id_materia'], (int) $grupo['id_periodo'], $turno,
                (string) $grupo['modalidad'], $division['id_usuario_solicitud'] !== null ? (int) $division['id_usuario_solicitud'] : null, $grupoId);

            // Mismo turno, dias, modalidad y cupo; la ubicacion la define la coordinacion.
            $dias = $this->grupos->dias($grupoId);
            $aprobado = in_array($grupo['estado'], ['confirmado', 'en_curso'], true);
            $nuevoId = $this->grupos->create([
                'id_periodo' => (int) $grupo['id_periodo'],
                'id_materia' => (int) $grupo['id_materia'],
                'id_tutor' => $tutorId,
                'id_espacio' => (int) $grupo['id_espacio'],
                'modalidad' => (string) $grupo['modalidad'],
                'dias' => $dias,
                'hora_inicio' => (string) $grupo['hora_inicio'],
                'hora_fin' => (string) $grupo['hora_fin'],
                'cupo_max' => (int) $grupo['cupo_max'],
                'estado' => $aprobado ? 'confirmado' : (string) $grupo['estado'],
            ]);
            if ($aprobado) {
                $pdo->prepare('UPDATE grupos_tutoria SET fecha_aprobacion = NOW(), id_aprobador = :a, fecha_estado = NOW() WHERE id_grupo = :id')
                    ->execute(['a' => $division['id_usuario_solicitud'], 'id' => $nuevoId]);
                $this->grupos->generateSessions($nuevoId, $dias, max(date('Y-m-d'), (string) $periodo['fecha_inicio']), (string) $periodo['fecha_fin']);
            }

            $trasladar = $pdo->prepare("UPDATE inscripciones SET estado = 'trasladada' WHERE id_inscripcion = :id AND estado = 'inscrito'");
            foreach ($plan['pasan'] as $est) {
                $trasladar->execute(['id' => $est['id_inscripcion']]);
                $this->inscripciones->create($nuevoId, (int) $est['id_estudiante'], 'inscrito', 'traslado');
            }
            foreach ($plan['desde_espera'] as $est) {
                $this->inscripciones->create($nuevoId, (int) $est['id_estudiante'], 'inscrito', 'manual_admin');
                $this->demanda->markAttended((int) $grupo['id_periodo'], (int) $grupo['id_materia'], (int) $est['id_estudiante']);
            }
            $pdo->prepare(
                "UPDATE grupos_tutoria g SET g.cupo_ocupado = (SELECT COUNT(*) FROM inscripciones i WHERE i.id_grupo = g.id_grupo AND i.estado = 'inscrito')
                 WHERE g.id_grupo IN (:a, :b)"
            )->execute(['a' => $grupoId, 'b' => $nuevoId]);

            $pasan = count($plan['pasan']);
            $desdeEspera = count($plan['desde_espera']);
            $pdo->prepare("UPDATE grupo_divisiones SET estado = 'aceptada', id_grupo_nuevo = :n, trasladados = :t, desde_espera = :e, fecha_respuesta = NOW()
                           WHERE id_division = :id")
                ->execute(['n' => $nuevoId, 't' => $pasan, 'e' => $desdeEspera, 'id' => $division['id_division']]);
            $tutorNuevo = (new Tutor())->findById($tutorId);
            $nombreNuevo = $tutorNuevo['nombre'] . ' ' . $tutorNuevo['apellido'];
            $eventoOrigen = $this->historial->log($pdo, $grupoId, 'division', $grupo['estado'], $grupo['estado'], $tutorUserId,
                sprintf('Grupo dividido: %d estudiante(s) pasaron al grupo #%d con %s. Quedan %d.', $pasan, $nuevoId, $nombreNuevo, count($plan['quedan'])));
            $eventoNuevo = $this->historial->log($pdo, $nuevoId, 'creado', null, $aprobado ? 'confirmado' : (string) $grupo['estado'], $tutorUserId,
                sprintf('Formado al dividir el grupo #%d: %d trasladado(s) y %d desde la espera.', $grupoId, $pasan, $desdeEspera));
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Ejecutar division ' . $division['id_division'] . ': ' . $exception->getMessage());

            return 'No se pudo completar la división. Intenta de nuevo.';
        }

        $this->notificarDivision($grupo, $nuevoId, $nombreNuevo, $plan, $dias, $division, $eventoOrigen, $eventoNuevo);
        // Si aun quedara gente en espera y algun grupo con lugar, el motor la ubica.
        (new AsignacionController())->reprocesarMateriaDelGrupo($grupo);

        return null;
    }

    private function notificarDivision(array $grupo, int $nuevoId, string $tutorNuevo, array $plan, array $dias, array $division, int $eventoOrigen, int $eventoNuevo): void
    {
        $pdo = Database::connection();
        $notif = new Notificacion();
        $materia = (string) $grupo['nombre_materia'];
        $horario = implode('/', $dias) . ' ' . substr((string) $grupo['hora_inicio'], 0, 5) . '-' . substr((string) $grupo['hora_fin'], 0, 5);
        try {
            foreach ($plan['pasan'] as $est) {
                $notif->notifyEventoGrupo($pdo, (int) $est['id_usuario'], $nuevoId, 'traslado_grupo', 'Cambio de grupo',
                    'Tu grupo de ' . $materia . ' se dividió: ahora estás con ' . $tutorNuevo . '. El horario no cambia (' . $horario . '); la coordinación confirmará el aula o el enlace.',
                    '/mis-tutorias/', $eventoNuevo);
            }
            foreach ($plan['desde_espera'] as $est) {
                $notif->notifyEventoGrupo($pdo, (int) $est['id_usuario'], $nuevoId, 'inscripcion_division', 'Ya tienes grupo',
                    'Se abrió un grupo nuevo de ' . $materia . ' con ' . $tutorNuevo . ' (' . $horario . ') y quedaste inscrito.', '/mis-tutorias/', $eventoNuevo);
            }
            $notif->notifyEventoGrupo($pdo, (int) $grupo['id_usuario_tutor'], (int) $grupo['id_grupo'], 'grupo_dividido', 'Tu grupo se dividió',
                'Tu grupo de ' . $materia . ' se dividió en dos para equilibrarlo: ahora tienes ' . count($plan['quedan']) . ' estudiantes.', '/mis-grupos/', $eventoOrigen);
            $notif->notifyAdminsDivision($pdo, (int) $division['id_division'], 'division_aceptada', 'División aceptada',
                $tutorNuevo . ' aceptó la mitad del grupo de ' . $materia . '. Define el aula o el enlace del grupo nuevo.', '/grupos/ubicacion.php?grupo=' . $nuevoId);
        } catch (Throwable $exception) {
            error_log('Notificaciones de division: ' . $exception->getMessage());
        }
    }

    private function division(int $divisionId): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM grupo_divisiones WHERE id_division = :id');
        $statement->execute(['id' => $divisionId]);

        return $statement->fetch() ?: null;
    }
}
