<?php

declare(strict_types=1);

final class Notificacion
{
    public function unreadForUser(int $userId, int $limit = 8): array
    {
        $limit = max(1, min($limit, 30));
        $statement = Database::connection()->prepare(
            'SELECT id_notificacion, id_usuario, id_tutoria, tipo, titulo AS title, mensaje AS text, url AS href, leida, fecha_creacion, CASE WHEN tipo IN (\'tutoria_cancelada\', \'grupo_cancelado\', \'grupo_rechazado\', \'tutor_rechazado\', \'propuesta_rechazada\') THEN \'danger\' WHEN tipo IN (\'tutoria_confirmada\', \'tutoria_proxima\', \'grupo_confirmado\', \'grupo_aprobado\', \'tutor_aprobado\', \'ubicacion_grupo\', \'propuesta_aprobada\') THEN \'success\' WHEN tipo = \'evaluacion_pendiente\' THEN \'info\' ELSE \'warning\' END AS tone FROM notificaciones WHERE id_usuario = :id_usuario AND leida = 0 ORDER BY fecha_creacion DESC, id_notificacion DESC LIMIT ' . $limit
        );
        $statement->execute(['id_usuario' => $userId]);

        return $statement->fetchAll();
    }

    public function countUnread(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM notificaciones WHERE id_usuario = :id_usuario AND leida = 0'
        );
        $statement->execute(['id_usuario' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function create(PDO $pdo, int $userId, ?int $tutoriaId, string $type, string $title, string $message, ?string $url, string $eventKey): void
    {
        $statement = $pdo->prepare(
            'INSERT IGNORE INTO notificaciones (id_usuario, id_tutoria, tipo, titulo, mensaje, url, clave_evento) VALUES (:id_usuario, :id_tutoria, :tipo, :titulo, :mensaje, :url, :clave_evento)'
        );
        $statement->execute([
            'id_usuario' => $userId,
            'id_tutoria' => $tutoriaId,
            'tipo' => $type,
            'titulo' => $title,
            'mensaje' => $message,
            'url' => $url,
            'clave_evento' => $eventKey,
        ]);
    }

    /** Notifica al estudiante que fue asignado a un grupo (modelo institucional). */
    public function notifyAssignment(PDO $pdo, int $grupoId, int $studentUserId, string $materia, string $detalle): void
    {
        $this->create(
            $pdo,
            $studentUserId,
            null,
            'asignacion_grupo',
            'Nueva asignacion de tutoria',
            'Fuiste asignado a un grupo de ' . $materia . '. ' . $detalle,
            '/mis-tutorias/',
            'asignacion_grupo:' . $grupoId . ':' . $studentUserId
        );
    }

    /** Notifica a todos los inscritos activos que su grupo quedo confirmado. */
    public function notifyGroupConfirmed(PDO $pdo, int $grupoId, string $materia): void
    {
        $statement = $pdo->prepare(
            "SELECT e.id_usuario FROM inscripciones i
             INNER JOIN estudiantes e ON e.id_estudiante = i.id_estudiante
             WHERE i.id_grupo = :id_grupo AND i.estado = 'inscrito'"
        );
        $statement->execute(['id_grupo' => $grupoId]);
        foreach ($statement->fetchAll() as $row) {
            $userId = (int) $row['id_usuario'];
            $this->create(
                $pdo,
                $userId,
                null,
                'grupo_confirmado',
                'Grupo de tutoria confirmado',
                'Tu grupo de ' . $materia . ' alcanzo el cupo minimo y quedo confirmado.',
                '/mis-tutorias/',
                'grupo_confirmado:' . $grupoId . ':' . $userId
            );
        }
    }

    /** Avisa a los inscritos de un grupo que pueden evaluar (tras una sesion realizada). */
    public function notifyEvaluationPending(PDO $pdo, int $grupoId, string $materia): void
    {
        $statement = $pdo->prepare(
            "SELECT e.id_usuario FROM inscripciones i
             INNER JOIN estudiantes e ON e.id_estudiante = i.id_estudiante
             WHERE i.id_grupo = :id_grupo AND i.estado = 'inscrito'
               AND NOT EXISTS (SELECT 1 FROM evaluaciones_grupo eg WHERE eg.id_inscripcion = i.id_inscripcion)"
        );
        $statement->execute(['id_grupo' => $grupoId]);
        foreach ($statement->fetchAll() as $row) {
            $userId = (int) $row['id_usuario'];
            $this->create(
                $pdo,
                $userId,
                null,
                'evaluacion_pendiente',
                'Evaluacion pendiente',
                'Ya puedes evaluar tu tutoria de ' . $materia . '.',
                '/mis-evaluaciones/',
                'evaluacion_pendiente_grupo:' . $grupoId . ':' . $userId
            );
        }
    }

    /** Notifica a un estudiante que su grupo fue cancelado. */
    public function notifyGroupCancelled(PDO $pdo, int $grupoId, int $studentUserId, string $materia): void
    {
        $this->create(
            $pdo,
            $studentUserId,
            null,
            'grupo_cancelado',
            'Grupo de tutoria cancelado',
            'Tu grupo de ' . $materia . ' fue cancelado. Quedaste en lista de espera: el sistema te reasignara automaticamente cuando haya un grupo compatible.',
            '/mis-tutorias/',
            'grupo_cancelado:' . $grupoId . ':' . $studentUserId
        );
    }

    /**
     * Notifica a un estudiante en espera que el reproceso automatico le consiguio grupo.
     * La clave incluye la fecha para permitir un aviso nuevo si vuelve a esperar en el mismo periodo.
     */
    public function notifyDemandAttended(PDO $pdo, int $studentUserId, int $materiaId, string $materia, string $detalle): void
    {
        $this->create(
            $pdo,
            $studentUserId,
            null,
            'demanda_atendida',
            'Se abrio un grupo para tu materia en espera',
            'Ya tienes grupo de ' . $materia . '. ' . $detalle,
            '/mis-tutorias/',
            'demanda_atendida:' . $materiaId . ':' . $studentUserId . ':' . date('Ymd')
        );
    }

    // ------------------------------------------------------------------
    // Visto bueno del administrador (db/028)
    // ------------------------------------------------------------------

    /** Cuentas de administrador activas: destinatarios de los avisos de revision. */
    private function adminUserIds(PDO $pdo): array
    {
        $statement = $pdo->query(
            "SELECT u.id_usuario FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol
             WHERE r.nombre_rol = 'administrador' AND u.estado = 'activo'"
        );

        return array_map('intval', array_column($statement->fetchAll(), 'id_usuario'));
    }

    /** Aviso a la coordinacion sobre una division de grupo (db/042): aceptada o rechazada por el tutor. */
    public function notifyAdminsDivision(PDO $pdo, int $divisionId, string $tipo, string $titulo, string $mensaje, string $url): void
    {
        foreach ($this->adminUserIds($pdo) as $adminId) {
            $this->create($pdo, $adminId, null, $tipo, $titulo, mb_substr($mensaje, 0, 500), $url, $tipo . ':' . $divisionId . ':' . $adminId);
        }
    }

    // ------------------------------------------------------------------
    // Gestion manual de la coordinacion (db/039)
    // ------------------------------------------------------------------

    /** Aviso al tutor: la coordinacion le propone dictar una materia; debe aceptar o rechazar. */
    public function notifyTutorPropuesta(PDO $pdo, int $tutorUserId, string $materia, string $detalle, int $historialId): void
    {
        $this->create(
            $pdo,
            $tutorUserId,
            null,
            'oferta_propuesta',
            'Te proponen una materia',
            mb_substr('La coordinación te propone dictar tutoría de ' . $materia . ' (' . $detalle . '). Acéptala o recházala en Mis materias.', 0, 500),
            '/mis-materias/',
            'oferta_propuesta:' . $tutorUserId . ':' . $historialId
        );
    }

    /** Aviso a la coordinacion: el tutor acepto o rechazo la materia propuesta. */
    public function notifyAdminsPropuestaRespondida(PDO $pdo, string $materia, string $tutor, bool $acepta, string $motivo, int $historialId): void
    {
        $mensaje = $acepta
            ? $tutor . ' aceptó dictar ' . $materia . '. El sistema ya puede formar grupos con los estudiantes en espera.'
            : $tutor . ' rechazó dictar ' . $materia . ': ' . $motivo;
        foreach ($this->adminUserIds($pdo) as $adminId) {
            $this->create(
                $pdo,
                $adminId,
                null,
                $acepta ? 'propuesta_aceptada' : 'propuesta_rechazada',
                $acepta ? 'Propuesta aceptada' : 'Propuesta rechazada',
                mb_substr($mensaje, 0, 500),
                '/grupos/#sin-tutor',
                'propuesta_respondida:' . $adminId . ':' . $historialId
            );
        }
    }

    /** Aviso individual por un evento de grupo (cambio de tutor, inscripcion o retiro manual, disolucion). */
    public function notifyEventoGrupo(PDO $pdo, int $userId, int $grupoId, string $tipo, string $titulo, string $mensaje, string $url, int $eventoId): void
    {
        $this->create($pdo, $userId, null, $tipo, $titulo, mb_substr($mensaje, 0, 500), $url, $tipo . ':' . $grupoId . ':' . $userId . ':' . $eventoId);
    }

    /** Aviso a la coordinacion: un tutor se registro y espera habilitacion. */
    public function notifyAdminsTutorPending(PDO $pdo, int $tutorId, string $nombre): void
    {
        foreach ($this->adminUserIds($pdo) as $adminId) {
            $this->create(
                $pdo,
                $adminId,
                null,
                'tutor_pendiente',
                'Tutor pendiente de habilitación',
                $nombre . ' se registró como tutor y espera tu revisión.',
                '/tutores/pendientes/',
                'tutor_pendiente:' . $tutorId . ':' . $adminId
            );
        }
    }

    /** Aviso a la coordinacion: el motor armo un grupo que necesita visto bueno. */
    public function notifyAdminsGroupPending(PDO $pdo, int $grupoId, string $materia, string $detalle): void
    {
        foreach ($this->adminUserIds($pdo) as $adminId) {
            $this->create(
                $pdo,
                $adminId,
                null,
                'grupo_por_aprobar',
                'Grupo por aprobar',
                mb_substr('Nuevo grupo de ' . $materia . ': ' . $detalle, 0, 500),
                '/grupos/?estado=por_aprobar',
                'grupo_por_aprobar:' . $grupoId . ':' . $adminId
            );
        }
    }

    /** Una alerta por grupo lleno y período; la coordinación decide si ampliar. */
    public function notifyAdminsCupoCompleto(PDO $pdo, int $grupoId, string $materia, string $turno): void
    {
        foreach ($this->adminUserIds($pdo) as $adminId) {
            $this->create($pdo, $adminId, null, 'cupo_completo', 'Demanda con grupo lleno',
                $materia . ' · ' . $turno . ': hay estudiantes en espera y un grupo llegó a su cupo. Revisa la demanda y propón otro tutor si corresponde.',
                '/grupos/#cupos-completos', 'cupo_completo:' . $grupoId . ':' . $adminId);
        }
    }

    public function notifyTutorApproved(PDO $pdo, int $tutorUserId, int $historialId): void
    {
        $this->create(
            $pdo,
            $tutorUserId,
            null,
            'tutor_aprobado',
            'Habilitación docente aprobada',
            'La coordinación aprobó tu habilitación. El sistema ya puede proponerte grupos en las materias que configuraste.',
            '/mis-materias/',
            'tutor_aprobado:' . $tutorUserId . ':' . $historialId
        );
    }

    public function notifyTutorRejected(PDO $pdo, int $tutorUserId, string $motivo, int $historialId): void
    {
        $this->create(
            $pdo,
            $tutorUserId,
            null,
            'tutor_rechazado',
            'Habilitación docente rechazada',
            mb_substr('La coordinación no aprobó tu habilitación. Motivo: ' . $motivo, 0, 500),
            '/mis-materias/',
            'tutor_rechazado:' . $tutorUserId . ':' . $historialId
        );
    }

    /** Aviso a la coordinacion: un tutor guardo horarios para una materia y espera revision (db/032). */
    public function notifyAdminsOfertaPendiente(PDO $pdo, int $tutorId, int $materiaId, string $tutor, string $materia, int $historialId): void
    {
        foreach ($this->adminUserIds($pdo) as $adminId) {
            $this->create(
                $pdo,
                $adminId,
                null,
                'oferta_pendiente',
                'Oferta de materia pendiente',
                $tutor . ' quiere dar tutoría de ' . $materia . '. Revisa sus horarios y asigna un espacio.',
                '/tutores/ofertas.php',
                'oferta_pendiente:' . $tutorId . ':' . $materiaId . ':' . $historialId . ':' . $adminId
            );
        }
    }

    public function notifyOfertaAprobada(PDO $pdo, int $tutorUserId, string $materia, int $historialId): void
    {
        $this->create(
            $pdo,
            $tutorUserId,
            null,
            'oferta_aprobada',
            'Oferta de materia aprobada',
            'La coordinación aprobó que dictes tutoría de ' . $materia . '. El sistema ya puede proponerte grupos en ese horario.',
            '/mis-materias/',
            'oferta_aprobada:' . $tutorUserId . ':' . $historialId
        );
    }

    public function notifyOfertaRechazada(PDO $pdo, int $tutorUserId, string $materia, string $motivo, int $historialId): void
    {
        $this->create(
            $pdo,
            $tutorUserId,
            null,
            'oferta_rechazada',
            'Oferta de materia rechazada',
            mb_substr('La coordinación no aprobó tu oferta de ' . $materia . '. Motivo: ' . $motivo, 0, 500),
            '/mis-materias/',
            'oferta_rechazada:' . $tutorUserId . ':' . $historialId
        );
    }

    /** El estudiante quedo en un grupo que aun espera el visto bueno de la coordinacion. */
    public function notifyPreassignment(PDO $pdo, int $grupoId, int $studentUserId, string $materia, string $detalle): void
    {
        $this->create(
            $pdo,
            $studentUserId,
            null,
            'asignacion_grupo',
            'Preasignación de tutoría',
            mb_substr('Quedaste preasignado a un grupo de ' . $materia . '. ' . $detalle . ' La coordinación debe aprobar el grupo antes de que empiece.', 0, 500),
            '/mis-tutorias/',
            'asignacion_grupo:' . $grupoId . ':' . $studentUserId
        );
    }

    /** Aviso a los inscritos cuando la coordinacion aprueba su grupo. */
    public function notifyGroupApproved(PDO $pdo, int $grupoId, string $materia, bool $confirmado, string $detalle = ''): void
    {
        $statement = $pdo->prepare(
            "SELECT e.id_usuario FROM inscripciones i
             INNER JOIN estudiantes e ON e.id_estudiante = i.id_estudiante
             WHERE i.id_grupo = :id_grupo AND i.estado = 'inscrito'"
        );
        $statement->execute(['id_grupo' => $grupoId]);
        $mensaje = $confirmado
            ? 'La coordinación aprobó tu grupo de ' . $materia . ' y quedó confirmado.'
            : 'La coordinación aprobó tu grupo de ' . $materia . '. Se confirmará al alcanzar el cupo mínimo.';
        if ($detalle !== '') {
            $mensaje = mb_substr($mensaje . ' ' . $detalle, 0, 500);
        }
        foreach ($statement->fetchAll() as $row) {
            $userId = (int) $row['id_usuario'];
            $this->create($pdo, $userId, null, 'grupo_aprobado', 'Grupo de tutoría aprobado', $mensaje, '/mis-tutorias/', 'grupo_aprobado:' . $grupoId . ':' . $userId);
        }
    }

    /** Aviso al tutor: tiene un grupo nuevo aprobado. */
    public function notifyTutorGroupApproved(PDO $pdo, int $grupoId, int $tutorUserId, string $materia, string $detalle): void
    {
        $this->create(
            $pdo,
            $tutorUserId,
            null,
            'grupo_aprobado',
            'Nuevo grupo asignado',
            mb_substr('La coordinación aprobó tu grupo de ' . $materia . '. ' . $detalle, 0, 500),
            '/mis-grupos/',
            'grupo_aprobado_tutor:' . $grupoId . ':' . $tutorUserId
        );
    }

    public function notifyGroupRejected(PDO $pdo, int $grupoId, int $studentUserId, string $materia): void
    {
        $this->create(
            $pdo,
            $studentUserId,
            null,
            'grupo_rechazado',
            'Grupo de tutoría no aprobado',
            'La coordinación no aprobó el grupo de ' . $materia . ' al que estabas preasignado. Quedaste en lista de espera: el sistema buscará otro grupo compatible.',
            '/mis-tutorias/',
            'grupo_rechazado:' . $grupoId . ':' . $studentUserId
        );
    }

    // ------------------------------------------------------------------
    // Ubicacion de grupos y enlaces propuestos (db/029)
    // ------------------------------------------------------------------

    /** Aviso a los inscritos: la coordinacion definio o cambio el lugar o el enlace del grupo. */
    public function notifyUbicacionGrupo(PDO $pdo, int $grupoId, string $materia, string $detalle, int $historialId): void
    {
        $statement = $pdo->prepare(
            "SELECT e.id_usuario FROM inscripciones i
             INNER JOIN estudiantes e ON e.id_estudiante = i.id_estudiante
             WHERE i.id_grupo = :id_grupo AND i.estado = 'inscrito'"
        );
        $statement->execute(['id_grupo' => $grupoId]);
        foreach ($statement->fetchAll() as $row) {
            $userId = (int) $row['id_usuario'];
            $this->create(
                $pdo,
                $userId,
                null,
                'ubicacion_grupo',
                'Ubicación de tu tutoría',
                mb_substr('Tu grupo de ' . $materia . ' ya tiene lugar: ' . $detalle . '.', 0, 500),
                '/mis-tutorias/',
                'ubicacion_grupo:' . $historialId . ':' . $userId
            );
        }
    }

    /** Aviso al tutor: la coordinacion definio o cambio la ubicacion de su grupo. */
    public function notifyTutorUbicacion(PDO $pdo, int $tutorUserId, int $grupoId, string $materia, string $detalle, int $historialId): void
    {
        $this->create(
            $pdo,
            $tutorUserId,
            null,
            'ubicacion_grupo',
            'Ubicación de tu grupo',
            mb_substr('La coordinación actualizó la ubicación de tu grupo de ' . $materia . ': ' . $detalle . '.', 0, 500),
            '/mis-grupos/',
            'ubicacion_grupo_tutor:' . $historialId . ':' . $tutorUserId
        );
    }

    /** Aviso a la coordinacion: un tutor propuso el enlace virtual de su grupo. */
    public function notifyAdminsEnlacePropuesto(PDO $pdo, int $grupoId, string $materia, string $tutor, int $historialId): void
    {
        foreach ($this->adminUserIds($pdo) as $adminId) {
            $this->create(
                $pdo,
                $adminId,
                null,
                'enlace_propuesto',
                'Enlace propuesto por un tutor',
                mb_substr($tutor . ' propuso el enlace de su grupo de ' . $materia . '. Apruébalo, corrígelo o reemplázalo.', 0, 500),
                '/grupos/ubicacion.php?grupo=' . $grupoId,
                'enlace_propuesto:' . $historialId . ':' . $adminId
            );
        }
    }

    /** Aviso al tutor: la coordinacion reviso el enlace que propuso. */
    public function notifyTutorPropuestaRevisada(PDO $pdo, int $tutorUserId, int $grupoId, string $materia, string $accion, ?string $motivo, int $historialId): void
    {
        $textos = [
            'propuesta_aprobada' => ['propuesta_aprobada', 'Enlace aprobado', 'La coordinación aprobó el enlace que propusiste para ' . $materia . '.'],
            'propuesta_corregida' => ['ubicacion_grupo', 'Enlace corregido', 'La coordinación corrigió el enlace que propusiste para ' . $materia . '. Revisa el enlace oficial en Mis grupos.'],
            'propuesta_reemplazada' => ['ubicacion_grupo', 'Enlace reemplazado', 'La coordinación reemplazó el enlace que propusiste para ' . $materia . ' por otro. Revisa el enlace oficial en Mis grupos.'],
            'propuesta_rechazada' => ['propuesta_rechazada', 'Enlace no aprobado', 'La coordinación no aprobó el enlace que propusiste para ' . $materia . '.'],
        ];
        [$tipo, $titulo, $mensaje] = $textos[$accion] ?? $textos['propuesta_corregida'];
        if ($motivo !== null && $motivo !== '') {
            $mensaje .= ' Motivo: ' . $motivo;
        }
        $this->create($pdo, $tutorUserId, null, $tipo, $titulo, mb_substr($mensaje, 0, 500), '/mis-grupos/', 'propuesta_revisada:' . $historialId . ':' . $tutorUserId);
    }

    /** Aviso al tutor: la materia cambio de modalidad requerida y su configuracion ya no aplica. */
    public function notifyTutorModalidadMateria(PDO $pdo, int $tutorUserId, int $materiaId, string $materia, string $modalidad): void
    {
        $this->create(
            $pdo,
            $tutorUserId,
            null,
            'modalidad_materia',
            'Cambio de modalidad en ' . $materia,
            mb_substr('La coordinación definió que ' . $materia . ' se dicta solo en modalidad ' . $modalidad . '. Ajusta tu configuración en Mis materias para seguir recibiendo grupos de esta materia.', 0, 500),
            '/mis-materias/',
            'modalidad_materia:' . $materiaId . ':' . $tutorUserId . ':' . date('YmdHis')
        );
    }

    /**
     * Cierre de periodo: aviso a los tutores con grupos en el periodo y a los
     * estudiantes que aun pueden evaluar (plazo de gracia).
     */
    public function notifyPeriodoCerrado(PDO $pdo, int $periodoId, string $periodo, string $evaluacionesHasta): void
    {
        $tutores = $pdo->prepare(
            "SELECT DISTINCT t.id_usuario FROM grupos_tutoria g INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             WHERE g.id_periodo = :id AND g.estado = 'finalizado'"
        );
        $tutores->execute(['id' => $periodoId]);
        foreach ($tutores->fetchAll() as $row) {
            $userId = (int) $row['id_usuario'];
            $this->create($pdo, $userId, null, 'periodo_cerrado', 'Período cerrado',
                mb_substr('La coordinación cerró el período ' . $periodo . '. Tus grupos quedaron finalizados y su asistencia ya no se modifica.', 0, 500),
                '/mis-grupos/', 'periodo_cerrado:' . $periodoId . ':' . $userId);
        }

        $estudiantes = $pdo->prepare(
            "SELECT DISTINCT e.id_usuario FROM inscripciones i
             INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             INNER JOIN estudiantes e ON e.id_estudiante = i.id_estudiante
             WHERE g.id_periodo = :id AND i.estado = 'inscrito' AND g.estado = 'finalizado'
               AND EXISTS (SELECT 1 FROM sesiones_tutoria s WHERE s.id_grupo = g.id_grupo AND s.estado = 'realizada')
               AND NOT EXISTS (SELECT 1 FROM evaluaciones_grupo ev WHERE ev.id_inscripcion = i.id_inscripcion)"
        );
        $estudiantes->execute(['id' => $periodoId]);
        $hasta = date('d/m/Y', strtotime($evaluacionesHasta));
        foreach ($estudiantes->fetchAll() as $row) {
            $userId = (int) $row['id_usuario'];
            $this->create($pdo, $userId, null, 'evaluacion_pendiente', 'Evalúa tus tutorías',
                mb_substr('El período ' . $periodo . ' cerró. Tienes hasta el ' . $hasta . ' para evaluar tus tutorías.', 0, 500),
                '/mis-evaluaciones/', 'periodo_cerrado_evaluar:' . $periodoId . ':' . $userId);
        }
    }

    /**
     * Al activar un periodo las ofertas no se heredan (db/040): cada tutor habilitado
     * y activo recibe el aviso de renovar sus materias y turnos.
     */
    public function notifyTutoresRenovarOferta(PDO $pdo, int $periodoId, string $periodo): int
    {
        $tutores = $pdo->query(
            "SELECT t.id_usuario FROM tutores t INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE t.estado_docente = 'aprobado' AND u.estado = 'activo'"
        );
        $n = 0;
        foreach ($tutores->fetchAll() as $row) {
            $userId = (int) $row['id_usuario'];
            $this->create($pdo, $userId, null, 'renovar_oferta', 'Renueva tu oferta',
                mb_substr('Se abrió el período ' . $periodo . '. Tus materias del período anterior no se renuevan solas: elige tus materias y turnos en Mis materias.', 0, 500),
                '/mis-materias/', 'renovar_oferta:' . $periodoId . ':' . $userId);
            $n++;
        }

        return $n;
    }

    public function markRead(int $id, int $userId): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE notificaciones SET leida = 1, fecha_lectura = CURRENT_TIMESTAMP WHERE id_notificacion = :id_notificacion AND id_usuario = :id_usuario AND leida = 0'
        );
        $statement->execute(['id_notificacion' => $id, 'id_usuario' => $userId]);

        return $statement->rowCount() > 0;
    }
}
