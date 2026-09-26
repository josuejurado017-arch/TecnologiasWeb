<?php

declare(strict_types=1);

/** Supervision administrativa de grupos: listado, historial, cancelacion, aprobacion y ubicacion. */
final class GruposController
{
    private Grupo $grupos;
    private Inscripcion $inscripciones;
    private HistorialGrupo $historial;
    private Demanda $demanda;

    public function __construct()
    {
        $this->grupos = new Grupo();
        $this->inscripciones = new Inscripcion();
        $this->historial = new HistorialGrupo();
        $this->demanda = new Demanda();
    }

    public function history(int $grupoId): array
    {
        return $this->historial->forGroup($grupoId);
    }

    public function find(int $grupoId): ?array
    {
        return $this->grupos->findBasic($grupoId);
    }

    public function ubicacionHistorial(int $grupoId): array
    {
        return $this->grupos->ubicacionHistorial($grupoId);
    }

    /** Estudiantes inscritos en el grupo, para la pantalla de revision. */
    public function enrolled(int $grupoId): array
    {
        return $this->inscripciones->forGroup($grupoId);
    }

    /** Demanda pendiente de la materia del grupo, para dar contexto a la revision. */
    public function demandaPendiente(int $periodoId, int $materiaId): array
    {
        return $this->demanda->pendingForMatter($periodoId, $materiaId);
    }

    // ------------------------------------------------------------------
    // Frecuencia del grupo (db/033)
    // ------------------------------------------------------------------

    /**
     * Como se resuelve la frecuencia al aprobar un grupo (regla institucional):
     *   'elegible'      reducido (LMV/MJS) con menos de UMBRAL_GRUPO_NORMAL inscritos:
     *                   la coordinacion elige LMV o MJS;
     *   'pasa_a_normal' reducido que ya alcanzo el umbral: Lunes a Viernes, automatico;
     *   'normal'        ya es Lunes a Viernes, no editable;
     *   'anterior'      patron previo a la regla (db/033): se conserva.
     */
    public static function tipoFrecuencia(array $dias, int $inscritos): string
    {
        if (Grupo::patronReducido($dias) !== null) {
            return $inscritos < Grupo::UMBRAL_GRUPO_NORMAL ? 'elegible' : 'pasa_a_normal';
        }

        return self::mismosDias($dias, Grupo::PATRON_NORMAL) ? 'normal' : 'anterior';
    }

    /** Etiqueta corta de un patron: LMV, MJS, Lunes a viernes o la lista de dias. */
    public static function etiquetaDias(array $dias): string
    {
        $reducido = Grupo::patronReducido($dias);
        if ($reducido !== null) {
            return Grupo::PATRONES_REDUCIDOS[$reducido]['corto'];
        }

        return self::mismosDias($dias, Grupo::PATRON_NORMAL) ? 'Lunes a viernes' : implode('/', $dias);
    }

    private static function mismosDias(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }

    /** Valida que el nuevo patron no choque con otros grupos del tutor ni de los inscritos. */
    private function conflictoFrecuencia(array $grupo, int $grupoId, array $dias): ?string
    {
        $inicio = (string) $grupo['hora_inicio'];
        $fin = (string) $grupo['hora_fin'];
        $periodoId = (int) $grupo['id_periodo'];
        $etiqueta = self::etiquetaDias($dias);

        if ($this->grupos->tutorHasConflict((int) $grupo['id_tutor'], $dias, $inicio, $fin, $periodoId, $grupoId)) {
            return 'El tutor ya tiene otro grupo en ese horario con el patrón ' . $etiqueta . '.';
        }
        foreach ($this->inscripciones->activeStudentsOfGroup($grupoId) as $est) {
            foreach ($this->inscripciones->studentBusySlots((int) $est['id_estudiante'], $periodoId, $grupoId) as $slot) {
                if (in_array($slot['dia_semana'], $dias, true) && $slot['hora_inicio'] < $fin && $slot['hora_fin'] > $inicio) {
                    return 'Un estudiante inscrito ya tiene otra tutoría en ese horario con el patrón ' . $etiqueta . '.';
                }
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Ubicacion del grupo (db/029)
    // ------------------------------------------------------------------

    /**
     * Valida la ubicacion pedida para un grupo (ya bloqueado con lockUbicacion) y
     * devuelve [error|null, plan|null]. La coordinacion puede cambiar la modalidad
     * del grupo ($input['modalidad']) dentro de lo que permite la materia
     * ($modalidadRequerida); el espacio se calcula (EspacioTutoria::deducir) y la
     * coordinacion solo escribe el aula (presencial) o el enlace (virtual), ambos
     * obligatorios. Si el tutor habia propuesto un enlace, guardarlo tal cual lo
     * aprueba, cambiar solo el enlace lo corrige y cambiar de plataforma lo
     * reemplaza. $alAprobar: se registra junto con la aprobacion (no exige cambios
     * ni motivo).
     */
    private function planUbicacion(array $actual, array $input, bool $aprobarProp, bool $alAprobar, string $modalidadRequerida): array
    {
        $modalidad = (string) ($input['modalidad'] ?? $actual['modalidad']);
        if (!isset(EspacioTutoria::MODALIDADES[$modalidad])) {
            return ['Elige una modalidad válida: presencial o virtual.', null];
        }
        if ($modalidadRequerida !== 'libre' && $modalidad !== $modalidadRequerida) {
            return ['La materia exige modalidad ' . $modalidadRequerida . ': no se puede cambiar.', null];
        }
        $cambiaModalidad = $modalidad !== (string) $actual['modalidad'];
        $tieneProp = $actual['enlace_propuesto'] !== null;
        if ($aprobarProp && (!$tieneProp || $modalidad !== 'virtual')) {
            return ['El grupo no tiene un enlace propuesto que aprobar.', null];
        }

        $ubicacion = null;
        $enlace = null;
        if ($modalidad === 'presencial') {
            $ubicacion = trim(preg_replace('/\s+/u', ' ', (string) ($input['ubicacion'] ?? '')) ?? '');
            if ($ubicacion === '') {
                return ['Indica el aula o lugar: es obligatorio en un grupo presencial.', null];
            }
            if (($error = validation_label($ubicacion, 'aula o lugar', 200, 2)) !== null) {
                return [$error, null];
            }
        } else {
            $enlace = $aprobarProp ? (string) $actual['enlace_propuesto'] : trim((string) ($input['enlace'] ?? ''));
            if ($enlace === '') {
                return ['Indica el enlace de la reunión: es obligatorio en un grupo virtual.', null];
            }
            if (($error = self::validarEnlace($enlace)) !== null) {
                return [$error, null];
            }
        }

        $espacio = (new EspacioTutoria())->deducir($modalidad, $enlace, (int) $actual['id_espacio']);
        if ($espacio === null) {
            return ['No hay un espacio activo de modalidad ' . $modalidad . '. Revisa el catálogo de espacios.', null];
        }
        $espacioId = (int) $espacio['id_espacio'];

        $despues = ['modalidad' => $modalidad, 'id_espacio' => $espacioId, 'ubicacion' => $ubicacion, 'enlace' => $enlace];
        $antes = [
            'modalidad' => $actual['modalidad'],
            'id_espacio' => (int) $actual['id_espacio'],
            'ubicacion' => $actual['ubicacion'],
            'enlace' => $actual['enlace'],
        ];

        if ($tieneProp && $modalidad === 'virtual') {
            if ($enlace === $actual['enlace_propuesto']) {
                $accion = 'propuesta_aprobada';
            } elseif ($espacioId === (int) $actual['id_espacio_propuesto']) {
                $accion = 'propuesta_corregida';
            } else {
                $accion = 'propuesta_reemplazada';
            }
        } elseif ($antes === $despues) {
            if (!$alAprobar) {
                return ['No hay cambios que guardar.', null];
            }
            $accion = null;
        } else {
            $accion = ubicacion_pendiente($actual) ? 'definida' : 'cambiada';
        }

        $motivo = trim(preg_replace('/\s+/u', ' ', (string) ($input['motivo'] ?? '')) ?? '');
        $motivo = $motivo === '' ? null : $motivo;
        if (!$alAprobar && ($accion === 'cambiada' || $cambiaModalidad) && ($motivo === null || mb_strlen($motivo) < 4)) {
            return [$cambiaModalidad
                ? 'Indica el motivo del cambio de modalidad (al menos 4 caracteres): el grupo ya fue aprobado.'
                : 'Indica el motivo del cambio (al menos 4 caracteres): la ubicación ya estaba definida.', null];
        }
        if ($motivo !== null && (mb_strlen($motivo) > 300 || preg_match('/[\x00-\x1F\x7F]/', $motivo))) {
            return ['Las observaciones no pueden superar 300 caracteres ni contener caracteres no válidos.', null];
        }

        return [null, [
            'antes' => $antes,
            'despues' => $despues,
            'espacio' => $espacio,
            'accion' => $accion,
            'motivo' => $motivo,
            'resuelvePropuesta' => $tieneProp,
        ]];
    }

    /** Guarda el plan de ubicacion dentro de la transaccion abierta. Devuelve el id del historial de ubicacion. */
    private function aplicarUbicacion(PDO $connection, int $grupoId, string $estado, array $plan, int $adminUserId): ?int
    {
        if ($plan['accion'] === null) {
            return null;
        }
        $d = $plan['despues'];
        $this->grupos->updateUbicacion($grupoId, $d['modalidad'], $d['id_espacio'], $d['ubicacion'], $d['enlace'], $adminUserId, $plan['resuelvePropuesta']);
        $historialId = $this->grupos->logUbicacion($connection, $grupoId, $plan['accion'], $plan['antes'], $d, $plan['motivo'], $adminUserId);
        $this->historial->log(
            $connection,
            $grupoId,
            'ubicacion_' . $plan['accion'],
            $estado,
            $estado,
            $adminUserId,
            mb_substr(self::describirUbicacion($plan['espacio']['nombre'], $d) . ($plan['motivo'] !== null ? ' · ' . $plan['motivo'] : ''), 0, 300)
        );

        return $historialId;
    }

    /**
     * Cambia la ubicacion de un grupo YA aprobado (o aprueba el enlace que propuso
     * su tutor). La de un grupo por aprobar se registra al aprobarlo (aprobar()).
     * Cambiar una ubicacion definida exige motivo. $input: ubicacion, enlace, motivo,
     * accion ('guardar' | 'aprobar_propuesta').
     */
    public function definirUbicacion(int $grupoId, array $input, int $adminUserId): ?string
    {
        $grupo = $this->grupos->findBasic($grupoId);
        if ($grupo === null) {
            return 'El grupo no existe.';
        }

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $actual = $this->grupos->lockUbicacion($grupoId);
            if ($actual === null || !in_array($actual['estado'], Grupo::ESTADOS_VIGENTES, true)) {
                $connection->rollBack();
                return 'Solo se puede cambiar la ubicación de un grupo en formación, confirmado o en curso.';
            }
            if ($actual['estado'] === 'por_aprobar') {
                $connection->rollBack();
                return 'La ubicación de un grupo por aprobar se registra al aprobarlo.';
            }
            [$error, $plan] = $this->planUbicacion($actual, $input, ($input['accion'] ?? '') === 'aprobar_propuesta', false, (string) $grupo['modalidad_requerida']);
            if ($error !== null) {
                $connection->rollBack();
                return $error;
            }
            $historialId = (int) $this->aplicarUbicacion($connection, $grupoId, (string) $actual['estado'], $plan, $adminUserId);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            error_log('Ubicacion grupo ' . $grupoId . ': ' . $exception->getMessage());
            return 'No se pudo guardar la ubicación.';
        }

        try {
            $notif = new Notificacion();
            $materia = (string) $grupo['nombre_materia'];
            $detalle = self::describirUbicacion($plan['espacio']['nombre'], $plan['despues'], false);
            $notif->notifyUbicacionGrupo($connection, $grupoId, $materia, $detalle, $historialId);
            if (str_starts_with((string) $plan['accion'], 'propuesta_')) {
                $notif->notifyTutorPropuestaRevisada($connection, (int) $grupo['id_usuario_tutor'], $grupoId, $materia, (string) $plan['accion'], $plan['motivo'], $historialId);
            } else {
                $notif->notifyTutorUbicacion($connection, (int) $grupo['id_usuario_tutor'], $grupoId, $materia, $detalle, $historialId);
            }
        } catch (Throwable $exception) {
            error_log('Notificacion ubicacion grupo: ' . $exception->getMessage());
        }

        return null;
    }

    /** La coordinacion descarta el enlace propuesto por el tutor sin cambiar la ubicacion oficial. */
    public function rechazarPropuesta(int $grupoId, string $motivo, int $adminUserId): ?string
    {
        $motivo = trim(preg_replace('/\s+/u', ' ', $motivo) ?? '');
        if (mb_strlen($motivo) < 4 || mb_strlen($motivo) > 300 || preg_match('/[\x00-\x1F\x7F]/', $motivo)) {
            return 'Indica un motivo de 4 a 300 caracteres.';
        }
        $grupo = $this->grupos->findBasic($grupoId);
        if ($grupo === null) {
            return 'El grupo no existe.';
        }

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $actual = $this->grupos->lockUbicacion($grupoId);
            if ($actual === null || $actual['enlace_propuesto'] === null) {
                $connection->rollBack();
                return 'El grupo no tiene un enlace propuesto pendiente.';
            }
            $this->grupos->clearPropuesta($grupoId);
            $historialId = $this->grupos->logUbicacion(
                $connection,
                $grupoId,
                'propuesta_rechazada',
                ['modalidad' => $actual['modalidad'], 'id_espacio' => $actual['id_espacio_propuesto'], 'enlace' => $actual['enlace_propuesto']],
                [],
                $motivo,
                $adminUserId
            );
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            error_log('Rechazar propuesta grupo ' . $grupoId . ': ' . $exception->getMessage());
            return 'No se pudo rechazar la propuesta.';
        }

        try {
            (new Notificacion())->notifyTutorPropuestaRevisada($connection, (int) $grupo['id_usuario_tutor'], $grupoId, (string) $grupo['nombre_materia'], 'propuesta_rechazada', $motivo, $historialId);
        } catch (Throwable $exception) {
            error_log('Notificacion propuesta rechazada: ' . $exception->getMessage());
        }

        return null;
    }

    /**
     * El tutor propone el enlace virtual de su grupo (Teams, Meet o Zoom). No cambia la
     * ubicacion oficial: la coordinacion lo aprueba, corrige, reemplaza o rechaza.
     */
    public function proponerEnlace(int $grupoId, int $tutorUserId, array $input): ?string
    {
        $grupo = $this->grupos->findBasic($grupoId);
        if ($grupo === null || (int) $grupo['id_usuario_tutor'] !== $tutorUserId) {
            return 'Ese grupo no es tuyo.';
        }
        $enlace = trim((string) ($input['enlace'] ?? ''));
        if (($error = self::validarEnlace($enlace)) !== null) {
            return $error;
        }
        // La plataforma se deduce del enlace (Meet, Zoom, Teams); no se elige a mano.
        $espacio = (new EspacioTutoria())->deducir('virtual', $enlace);
        if ($espacio === null) {
            return 'No hay una plataforma virtual activa. Avisa a la coordinación.';
        }
        $espacioId = (int) $espacio['id_espacio'];

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $actual = $this->grupos->lockUbicacion($grupoId);
            if ($actual === null || !in_array($actual['estado'], Grupo::ESTADOS_VIGENTES, true)) {
                $connection->rollBack();
                return 'El grupo ya no está vigente.';
            }
            if ($actual['modalidad'] !== 'virtual') {
                $connection->rollBack();
                return 'Solo los grupos virtuales tienen enlace. El aula de un grupo presencial la define la coordinación.';
            }
            if ($enlace === $actual['enlace']) {
                $connection->rollBack();
                return 'Ese ya es el enlace oficial del grupo.';
            }
            if ($enlace === $actual['enlace_propuesto']) {
                $connection->rollBack();
                return 'Ya propusiste ese enlace; la coordinación lo revisará.';
            }
            $this->grupos->setPropuesta($grupoId, $espacioId, $enlace, $tutorUserId);
            $historialId = $this->grupos->logUbicacion(
                $connection,
                $grupoId,
                'propuesta',
                ['modalidad' => $actual['modalidad'], 'id_espacio' => $actual['id_espacio'], 'enlace' => $actual['enlace']],
                ['modalidad' => 'virtual', 'id_espacio' => $espacioId, 'enlace' => $enlace],
                null,
                $tutorUserId
            );
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            error_log('Proponer enlace grupo ' . $grupoId . ': ' . $exception->getMessage());
            return 'No se pudo registrar la propuesta.';
        }

        try {
            (new Notificacion())->notifyAdminsEnlacePropuesto($connection, $grupoId, (string) $grupo['nombre_materia'], (string) $grupo['tutor'], $historialId);
        } catch (Throwable $exception) {
            error_log('Notificacion enlace propuesto: ' . $exception->getMessage());
        }

        return null;
    }

    /** Enlace de reunion: URL https completa, sin espacios, hasta 300 caracteres. */
    public static function validarEnlace(string $enlace): ?string
    {
        if ($enlace === '') {
            return 'Indica el enlace de la reunión.';
        }
        if (mb_strlen($enlace) > 300 || preg_match('/\s/', $enlace)
            || filter_var($enlace, FILTER_VALIDATE_URL) === false
            || strtolower((string) parse_url($enlace, PHP_URL_SCHEME)) !== 'https') {
            return 'El enlace debe ser una dirección https:// completa (por ejemplo https://meet.google.com/abc-defg-hij).';
        }

        return null;
    }

    /** Texto corto de la ubicacion para historial y avisos. $conEnlace=false omite la URL. */
    public static function describirUbicacion(string $espacio, array $ubicacion, bool $conEnlace = true): string
    {
        if ($ubicacion['modalidad'] === 'presencial') {
            return $espacio . ': ' . ($ubicacion['ubicacion'] ?? 'ubicación pendiente');
        }
        if (($ubicacion['enlace'] ?? null) === null) {
            return $espacio . ': enlace pendiente';
        }

        return $conEnlace ? $espacio . ': ' . $ubicacion['enlace'] : $espacio . ' (enlace disponible en Mis tutorías)';
    }

    /**
     * Cancela un grupo: cancela inscripciones, devuelve a los estudiantes a la demanda,
     * los notifica y registra el evento en el historial.
     */
    public function cancel(int $grupoId, string $motivo, int $adminUserId): ?string
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 4) {
            return 'Indica un motivo de al menos 4 caracteres.';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $motivo)) {
            return 'El motivo contiene caracteres no válidos.';
        }

        $grupo = $this->grupos->findBasic($grupoId);
        if ($grupo === null) {
            return 'El grupo no existe.';
        }
        if ($grupo['estado'] === 'cancelado') {
            return 'El grupo ya estaba cancelado.';
        }

        $estudiantes = $this->inscripciones->activeStudentsOfGroup($grupoId);
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $estadoAnterior = (string) $grupo['estado'];
            $this->inscripciones->cancelByGroup($grupoId);
            $this->grupos->cancel($grupoId, $motivo);
            foreach ($estudiantes as $est) {
                $this->demanda->record((int) $grupo['id_periodo'], (int) $grupo['id_materia'], (int) $est['id_estudiante'], Demanda::MOTIVO_GRUPO_CANCELADO);
            }
            $this->historial->log($connection, $grupoId, 'cancelado', $estadoAnterior, 'cancelado', $adminUserId, $motivo);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log($exception->getMessage());
            return 'No se pudo cancelar el grupo.';
        }

        try {
            $notif = new Notificacion();
            foreach ($estudiantes as $est) {
                $notif->notifyGroupCancelled($connection, $grupoId, (int) $est['id_usuario'], (string) $grupo['nombre_materia']);
            }
        } catch (Throwable $exception) {
            error_log('Notificacion cancelacion: ' . $exception->getMessage());
        }

        // Reasignacion automatica SOLO a grupos ya existentes de la materia: crear grupos
        // nuevos aqui recrearia al instante el que el administrador acaba de cancelar
        // (mismo tutor, mismo bloque). El bloque liberado se aprovechara en el siguiente
        // disparador natural (nueva disponibilidad, tutor habilitado o solicitud nueva).
        (new AsignacionController())->reprocesarMateriaDelGrupo($grupo, false);

        return null;
    }

    // ------------------------------------------------------------------
    // Visto bueno de la coordinacion (db/028)
    // ------------------------------------------------------------------

    /**
     * Aprueba un grupo propuesto por el motor en una sola transaccion: aplica la
     * frecuencia (LMV/MJS elegida por la coordinacion si es reducido, Lunes a Viernes
     * si ya alcanzo el umbral), registra la ubicacion obligatoria (aula o enlace),
     * pasa el grupo a confirmado (si tiene el cupo minimo) o a formacion y genera su
     * calendario desde hoy. Luego avisa al tutor y a los inscritos.
     * $input: patron, ubicacion, enlace, motivo (observaciones).
     */
    public function aprobar(int $grupoId, array $input, int $adminUserId): ?string
    {
        $grupo = $this->grupos->findBasic($grupoId);
        if ($grupo === null) {
            return 'El grupo no existe.';
        }
        if ($grupo['estado'] !== 'por_aprobar') {
            return 'Solo se puede aprobar un grupo por aprobar.';
        }
        $periodo = (new Periodo())->findById((int) $grupo['id_periodo']);
        if ($periodo === null || $periodo['estado'] !== 'activa') {
            return 'El período del grupo no está activo.';
        }
        // Quorum (db/035): un grupo aprobado nace confirmado, nunca por debajo del minimo.
        if ((int) $grupo['cupo_ocupado'] < (int) $periodo['cupo_min_grupo']) {
            return sprintf('El grupo tiene %d estudiante(s) y el mínimo para aprobarlo es %d. Espera a que se sumen más o recházalo.', (int) $grupo['cupo_ocupado'], (int) $periodo['cupo_min_grupo']);
        }
        $tutor = (new Tutor())->findById((int) $grupo['id_tutor']);
        if ($tutor === null || $tutor['estado'] !== 'activo' || $tutor['estado_docente'] !== 'aprobado') {
            return 'El tutor del grupo no está habilitado. Rechaza el grupo o habilita primero al tutor.';
        }

        $diasActuales = $this->grupos->dias($grupoId);
        $tipo = self::tipoFrecuencia($diasActuales, (int) $grupo['cupo_ocupado']);
        $dias = $diasActuales;
        if ($tipo === 'elegible') {
            $patron = (string) ($input['patron'] ?? '');
            if (!isset(Grupo::PATRONES_REDUCIDOS[$patron])) {
                return 'Elige la frecuencia del grupo: LMV o MJS.';
            }
            $dias = Grupo::PATRONES_REDUCIDOS[$patron]['dias'];
        } elseif ($tipo === 'pasa_a_normal') {
            $dias = Grupo::PATRON_NORMAL;
        }
        $cambiaFrecuencia = !self::mismosDias($diasActuales, $dias);
        if ($cambiaFrecuencia && ($error = $this->conflictoFrecuencia($grupo, $grupoId, $dias)) !== null) {
            return $error;
        }

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $actual = $this->grupos->lockUbicacion($grupoId);
            if ($actual === null || $actual['estado'] !== 'por_aprobar') {
                $connection->rollBack();
                return 'Otro administrador ya revisó este grupo. Recarga la página.';
            }
            [$error, $plan] = $this->planUbicacion($actual, $input, false, true, (string) $grupo['modalidad_requerida']);
            if ($error !== null) {
                $connection->rollBack();
                return $error;
            }
            if ($cambiaFrecuencia) {
                $this->grupos->cambiarDias($grupoId, $dias);
                $detalle = self::etiquetaDias($diasActuales) . ' → ' . self::etiquetaDias($dias)
                    . ($tipo === 'pasa_a_normal' ? ' (alcanzó ' . Grupo::UMBRAL_GRUPO_NORMAL . ' inscritos)' : '');
                $this->historial->log($connection, $grupoId, 'frecuencia_cambiada', 'por_aprobar', 'por_aprobar', $adminUserId, $detalle);
            }
            $historialUbicacionId = $this->aplicarUbicacion($connection, $grupoId, 'por_aprobar', $plan, $adminUserId);
            $estadoNuevo = $this->grupos->approve($grupoId, (int) $periodo['cupo_min_grupo'], $adminUserId);
            if ($estadoNuevo === null) {
                $connection->rollBack();
                return 'Otro administrador ya revisó este grupo. Recarga la página.';
            }
            // Calendario desde hoy: no se generan sesiones pasadas que nadie impartio.
            $desde = max(date('Y-m-d'), (string) $periodo['fecha_inicio']);
            $this->grupos->generateSessions($grupoId, $dias, $desde, (string) $periodo['fecha_fin']);
            $this->historial->log(
                $connection,
                $grupoId,
                'aprobado',
                'por_aprobar',
                $estadoNuevo,
                $adminUserId,
                mb_substr('Visto bueno de la coordinación.' . ($plan['motivo'] !== null ? ' · ' . $plan['motivo'] : ''), 0, 300)
            );
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            error_log('Aprobar grupo ' . $grupoId . ': ' . $exception->getMessage());
            return 'No se pudo aprobar el grupo.';
        }

        try {
            $notif = new Notificacion();
            $materia = (string) $grupo['nombre_materia'];
            $horario = sprintf('%s %s-%s', self::etiquetaDias($dias), substr((string) $grupo['hora_inicio'], 0, 5), substr((string) $grupo['hora_fin'], 0, 5));
            $lugar = self::describirUbicacion($plan['espacio']['nombre'], $plan['despues'], false);
            $notif->notifyGroupApproved($connection, $grupoId, $materia, $estadoNuevo === 'confirmado', $horario . ' · ' . $lugar . '.');
            $notif->notifyTutorGroupApproved($connection, $grupoId, (int) $tutor['id_usuario'], $materia, $horario . ' · ' . $lugar . '.');
            if ($historialUbicacionId !== null && str_starts_with((string) $plan['accion'], 'propuesta_')) {
                $notif->notifyTutorPropuestaRevisada($connection, (int) $tutor['id_usuario'], $grupoId, $materia, (string) $plan['accion'], $plan['motivo'], $historialUbicacionId);
            }
        } catch (Throwable $exception) {
            error_log('Notificacion aprobacion grupo: ' . $exception->getMessage());
        }

        return null;
    }

    /**
     * Rechaza un grupo propuesto: se cancela (inscripciones a la lista de espera), se
     * recuerda la combinacion tutor+horario para que el motor no la repita, y se
     * reintenta la asignacion de los afectados con otras combinaciones.
     */
    public function rechazar(int $grupoId, string $motivo, int $adminUserId): ?string
    {
        $motivo = trim(preg_replace('/\s+/u', ' ', $motivo) ?? '');
        if (mb_strlen($motivo) < 4) {
            return 'Indica un motivo de al menos 4 caracteres.';
        }
        if (mb_strlen($motivo) > 300 || preg_match('/[\x00-\x1F\x7F]/', $motivo)) {
            return 'El motivo no puede superar 300 caracteres ni contener caracteres no válidos.';
        }

        $grupo = $this->grupos->findBasic($grupoId);
        if ($grupo === null) {
            return 'El grupo no existe.';
        }
        if ($grupo['estado'] !== 'por_aprobar') {
            return 'Solo se puede rechazar un grupo por aprobar. Para un grupo en marcha usa Cancelar.';
        }

        $estudiantes = $this->inscripciones->activeStudentsOfGroup($grupoId);
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $lock = $this->grupos->lockForEnroll($grupoId);
            if ($lock === null || $lock['estado'] !== 'por_aprobar') {
                $connection->rollBack();
                return 'Otro administrador ya revisó este grupo. Recarga la página.';
            }
            $this->inscripciones->cancelByGroup($grupoId);
            $this->grupos->cancel($grupoId, 'Rechazado por la coordinación: ' . $motivo);
            $this->grupos->recordRejection($grupo, $motivo, $adminUserId);
            foreach ($estudiantes as $est) {
                $this->demanda->record((int) $grupo['id_periodo'], (int) $grupo['id_materia'], (int) $est['id_estudiante'], Demanda::MOTIVO_GRUPO_CANCELADO);
            }
            $this->historial->log($connection, $grupoId, 'rechazado', 'por_aprobar', 'cancelado', $adminUserId, $motivo);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            error_log('Rechazar grupo ' . $grupoId . ': ' . $exception->getMessage());
            return 'No se pudo rechazar el grupo.';
        }

        try {
            $notif = new Notificacion();
            foreach ($estudiantes as $est) {
                $notif->notifyGroupRejected($connection, $grupoId, (int) $est['id_usuario'], (string) $grupo['nombre_materia']);
            }
        } catch (Throwable $exception) {
            error_log('Notificacion rechazo grupo: ' . $exception->getMessage());
        }

        // A diferencia de cancel(), aqui SI se permite crear grupos: la combinacion
        // rechazada quedo registrada y el motor la salta, asi que solo puede proponer
        // otro tutor u otro horario (que a su vez quedara por aprobar).
        (new AsignacionController())->reprocesarMateriaDelGrupo($grupo);

        return null;
    }

    // ------------------------------------------------------------------
    // Gestion manual de la coordinacion (db/039)
    // ------------------------------------------------------------------

    /**
     * Tutores que pueden tomar el grupo: oferta aprobada de la materia en ese turno y
     * modalidad, libres en el turno (un grupo por turno) y por debajo de su tope de
     * grupos del periodo (db/037). Como ya ofrecieron ese turno, no necesitan aceptar.
     */
    public function tutoresDisponibles(array $grupo): array
    {
        $periodo = (new Periodo())->findById((int) $grupo['id_periodo']);
        $turno = TutorMateriaConfig::turnoDeHora((string) $grupo['hora_inicio']);
        if ($periodo === null || $turno === null) {
            return [];
        }
        $maxGrupos = min(2, (int) ($periodo['max_grupos_tutor'] ?? 2));
        $disponibles = [];
        foreach ((new TutorMateriaConfig())->tutoresConTurno((int) $grupo['id_materia'], $turno, (string) $grupo['modalidad'], (int) $grupo['id_periodo']) as $tutor) {
            $tutorId = (int) $tutor['id_tutor'];
            if ($tutorId === (int) $grupo['id_tutor']
                || $this->grupos->tutorOcupaTurno($tutorId, (string) $grupo['hora_inicio'], (string) $grupo['hora_fin'], (int) $grupo['id_periodo'])
                || $this->grupos->countTutorGrupos($tutorId, (int) $grupo['id_periodo']) >= $maxGrupos) {
                continue;
            }
            $disponibles[] = $tutor;
        }

        return $disponibles;
    }

    /**
     * Cambia el tutor de un grupo vigente (licencia, renuncia...). Se conservan
     * horario, sesiones y asistencia; el tutor nuevo toma las sesiones siguientes.
     */
    public function cambiarTutor(int $grupoId, int $tutorId, string $motivo, int $adminUserId): ?string
    {
        $motivo = trim(preg_replace('/\s+/u', ' ', $motivo) ?? '');
        if (mb_strlen($motivo) < 4 || mb_strlen($motivo) > 300 || preg_match('/[\x00-\x1F\x7F]/', $motivo)) {
            return 'Indica el motivo del cambio de tutor (de 4 a 300 caracteres).';
        }
        $grupo = $this->grupos->findBasic($grupoId);
        if ($grupo === null || !in_array($grupo['estado'], Grupo::ESTADOS_VIGENTES, true)) {
            return 'Solo se puede cambiar el tutor de un grupo vigente.';
        }
        $nuevo = null;
        foreach ($this->tutoresDisponibles($grupo) as $candidato) {
            if ((int) $candidato['id_tutor'] === $tutorId) {
                $nuevo = $candidato;
            }
        }
        if ($nuevo === null) {
            return 'Ese tutor no puede tomar el grupo: necesita oferta aprobada de la materia en este turno, estar libre en él y no superar su tope de grupos.';
        }

        $estudiantes = $this->inscripciones->activeStudentsOfGroup($grupoId);
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $lock = $this->grupos->lockForEnroll($grupoId);
            if ($lock === null || !in_array($lock['estado'], Grupo::ESTADOS_VIGENTES, true)) {
                $connection->rollBack();
                return 'El grupo cambió de estado. Recarga la página.';
            }
            $this->grupos->cambiarTutor($grupoId, $tutorId);
            $eventoId = $this->historial->log($connection, $grupoId, 'tutor_cambiado', $grupo['estado'], $grupo['estado'], $adminUserId,
                mb_substr($grupo['tutor'] . ' → ' . $nuevo['tutor'] . ' · ' . $motivo, 0, 300));
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            error_log('Cambiar tutor grupo ' . $grupoId . ': ' . $exception->getMessage());
            return 'No se pudo cambiar el tutor.';
        }

        try {
            $notif = new Notificacion();
            $materia = (string) $grupo['nombre_materia'];
            $nuevoTutor = (new Tutor())->findById($tutorId);
            $notif->notifyEventoGrupo($connection, (int) $grupo['id_usuario_tutor'], $grupoId, 'grupo_tutor_salida', 'Cambio de tutor',
                'La coordinación asignó tu grupo de ' . $materia . ' a otro tutor. Motivo: ' . $motivo, '/mis-grupos/', $eventoId);
            if ($nuevoTutor !== null) {
                $notif->notifyEventoGrupo($connection, (int) $nuevoTutor['id_usuario'], $grupoId, 'grupo_tutor_entrada', 'Nuevo grupo asignado',
                    'La coordinación te asignó el grupo de ' . $materia . ' (' . implode('/', $this->grupos->dias($grupoId)) . ' ' . substr((string) $grupo['hora_inicio'], 0, 5) . '). Revisa Mis grupos.',
                    '/mis-grupos/', $eventoId);
            }
            foreach ($estudiantes as $est) {
                $notif->notifyEventoGrupo($connection, (int) $est['id_usuario'], $grupoId, 'grupo_tutor_cambiado', 'Cambio de tutor',
                    'Tu grupo de ' . $materia . ' tiene nuevo tutor: ' . $nuevo['tutor'] . '. El horario y el lugar no cambian.', '/mis-tutorias/', $eventoId);
            }
        } catch (Throwable $exception) {
            error_log('Notificacion cambio de tutor: ' . $exception->getMessage());
        }

        return null;
    }

    /**
     * Evalua si un estudiante puede entrar a mano en el grupo con las mismas reglas
     * del motor: una tutoria por periodo (db/037), una por turno, cupo y grupo vigente.
     * Devuelve ['bloqueo' => ?string, 'aviso' => ?string] (aviso: su solicitud de otra
     * materia se reemplaza).
     */
    public function evaluarInscripcion(array $grupo, int $studentId): array
    {
        $cierre = $this->grupos->cierreInscripcion((int) $grupo['id_grupo']);
        if ($cierre !== null && $cierre < date('Y-m-d')) {
            return ['bloqueo' => 'La inscripción a este grupo cerró el ' . date('d/m/Y', strtotime($cierre))
                . ' (' . Grupo::DIAS_INSCRIPCION_TARDIA . ' días después de la primera sesión).', 'aviso' => null];
        }
        $periodoId = (int) $grupo['id_periodo'];
        $actual = (new AsignacionController())->tutoriaDelPeriodo($studentId, $periodoId);
        if ($actual !== null && $actual['tipo'] === 'inscripcion') {
            return ['bloqueo' => (int) $actual['id_materia'] === (int) $grupo['id_materia']
                ? 'Ya está inscrito en esta materia.'
                : 'Ya tiene su tutoría del período: ' . $actual['nombre'] . '.', 'aviso' => null];
        }
        foreach ($this->inscripciones->studentBusySlots($studentId, $periodoId, (int) $grupo['id_grupo']) as $slot) {
            if ($slot['hora_inicio'] < $grupo['hora_fin'] && $slot['hora_fin'] > $grupo['hora_inicio']) {
                return ['bloqueo' => 'Ya tiene otra tutoría en este turno.', 'aviso' => null];
            }
        }
        $aviso = $actual !== null && (int) $actual['id_materia'] !== (int) $grupo['id_materia']
            ? 'Reemplaza su solicitud en espera de ' . $actual['nombre'] . '.'
            : null;

        return ['bloqueo' => null, 'aviso' => $aviso];
    }

    /**
     * Candidatos para inscribir a mano: quienes esperan esta materia y, si hay texto,
     * los estudiantes que coinciden con la busqueda. Cada uno trae su evaluacion.
     */
    public function candidatosInscripcion(array $grupo, string $busqueda = ''): array
    {
        $candidatos = [];
        foreach ($this->demanda->pendingForMatter((int) $grupo['id_periodo'], (int) $grupo['id_materia']) as $pendiente) {
            $est = $this->inscripciones->estudiante((int) $pendiente['id_estudiante']);
            if ($est !== null && $est['estado'] === 'activo') {
                $candidatos[(int) $est['id_estudiante']] = $est + ['origen' => 'espera', 'desde' => $pendiente['fecha_solicitud']];
            }
        }
        $busqueda = trim($busqueda);
        if (mb_strlen($busqueda) >= 2) {
            foreach ($this->inscripciones->buscarEstudiantes($busqueda) as $est) {
                $candidatos[(int) $est['id_estudiante']] ??= $est + ['origen' => 'busqueda', 'desde' => null];
            }
        }
        foreach ($candidatos as $id => $est) {
            $candidatos[$id] += $this->evaluarInscripcion($grupo, $id);
        }

        return array_values($candidatos);
    }

    /** La coordinacion inscribe a un estudiante en un grupo vigente con cupo. */
    public function inscribirManual(int $grupoId, int $studentId, int $adminUserId): ?string
    {
        $grupo = $this->grupos->findBasic($grupoId);
        if ($grupo === null || !in_array($grupo['estado'], Grupo::ESTADOS_VIGENTES, true)) {
            return 'Solo se puede inscribir en un grupo vigente.';
        }
        $est = $this->inscripciones->estudiante($studentId);
        if ($est === null || $est['estado'] !== 'activo') {
            return 'El estudiante no existe o su cuenta no está activa.';
        }
        $evaluacion = $this->evaluarInscripcion($grupo, $studentId);
        if ($evaluacion['bloqueo'] !== null) {
            return $evaluacion['bloqueo'];
        }
        $periodo = (new Periodo())->findById((int) $grupo['id_periodo']);
        $periodoId = (int) $grupo['id_periodo'];
        $materiaId = (int) $grupo['id_materia'];
        $otraSolicitud = (new AsignacionController())->tutoriaDelPeriodo($studentId, $periodoId);

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $lock = $this->grupos->lockForEnroll($grupoId);
            if ($lock === null || !in_array($lock['estado'], Grupo::ESTADOS_VIGENTES, true)) {
                $connection->rollBack();
                return 'El grupo cambió de estado. Recarga la página.';
            }
            if ((int) $lock['cupo_ocupado'] >= (int) $lock['cupo_max']) {
                $connection->rollBack();
                return 'El grupo no tiene cupo libre.';
            }
            $this->inscripciones->inscribirManual($grupoId, $studentId);
            $this->grupos->registerEnrollment($grupoId, (int) ($periodo['cupo_min_grupo'] ?? 3));
            // Una tutoria por periodo: su solicitud de esta materia queda atendida y la
            // de otra materia, reemplazada.
            $this->demanda->markAttended($periodoId, $materiaId, $studentId);
            if ($otraSolicitud !== null && (int) $otraSolicitud['id_materia'] !== $materiaId) {
                $this->demanda->cancel($periodoId, (int) $otraSolicitud['id_materia'], $studentId);
            }
            $eventoId = $this->historial->log($connection, $grupoId, 'inscripcion_manual', $grupo['estado'], $grupo['estado'], $adminUserId,
                mb_substr($est['estudiante'] . ' inscrito por la coordinación.' . ($evaluacion['aviso'] ? ' ' . $evaluacion['aviso'] : ''), 0, 300));
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            error_log('Inscripcion manual grupo ' . $grupoId . ': ' . $exception->getMessage());
            return 'No se pudo inscribir al estudiante.';
        }

        try {
            $detalle = implode('/', $this->grupos->dias($grupoId)) . ' ' . substr((string) $grupo['hora_inicio'], 0, 5) . '-' . substr((string) $grupo['hora_fin'], 0, 5);
            (new Notificacion())->notifyEventoGrupo($connection, (int) $est['id_usuario'], $grupoId, 'inscripcion_manual', 'Te inscribieron en una tutoría',
                'La coordinación te inscribió en el grupo de ' . $grupo['nombre_materia'] . ' con ' . $grupo['tutor'] . ' (' . $detalle . ').', '/mis-tutorias/', $eventoId);
        } catch (Throwable $exception) {
            error_log('Notificacion inscripcion manual: ' . $exception->getMessage());
        }

        return null;
    }

    /**
     * La coordinacion retira a un estudiante. Si un grupo POR APROBAR queda bajo el
     * minimo del periodo se disuelve: sus estudiantes vuelven a "interes registrado"
     * y el motor reintenta formar grupo. Un grupo aprobado sigue (la coordinacion
     * decide si lo cancela). Devuelve [error|null, aviso|null].
     */
    public function retirarEstudiante(int $grupoId, int $studentId, string $motivo, int $adminUserId): array
    {
        $motivo = trim(preg_replace('/\s+/u', ' ', $motivo) ?? '');
        if (mb_strlen($motivo) < 4 || mb_strlen($motivo) > 300 || preg_match('/[\x00-\x1F\x7F]/', $motivo)) {
            return ['Indica el motivo del retiro (de 4 a 300 caracteres).', null];
        }
        $grupo = $this->grupos->findBasic($grupoId);
        if ($grupo === null || !in_array($grupo['estado'], Grupo::ESTADOS_VIGENTES, true)) {
            return ['Solo se puede retirar estudiantes de un grupo vigente.', null];
        }
        $est = $this->inscripciones->estudiante($studentId);
        $periodo = (new Periodo())->findById((int) $grupo['id_periodo']);
        $cupoMin = (int) ($periodo['cupo_min_grupo'] ?? 3);
        $materia = (string) $grupo['nombre_materia'];

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $lock = $this->grupos->lockForEnroll($grupoId);
            if ($lock === null || !in_array($lock['estado'], Grupo::ESTADOS_VIGENTES, true)) {
                $connection->rollBack();
                return ['El grupo cambió de estado. Recarga la página.', null];
            }
            if (!$this->inscripciones->retirar($grupoId, $studentId)) {
                $connection->rollBack();
                return ['El estudiante no está inscrito en este grupo.', null];
            }
            $this->grupos->releaseEnrollment($grupoId);
            $eventoId = $this->historial->log($connection, $grupoId, 'retiro_manual', $lock['estado'], $lock['estado'], $adminUserId,
                mb_substr(($est['estudiante'] ?? 'Estudiante') . ' retirado por la coordinación · ' . $motivo, 0, 300));

            $quedan = (int) $lock['cupo_ocupado'] - 1;
            $disuelto = $lock['estado'] === 'por_aprobar' && $quedan < $cupoMin;
            $restantes = [];
            if ($disuelto) {
                $restantes = $this->inscripciones->activeStudentsOfGroup($grupoId);
                $this->inscripciones->cancelByGroup($grupoId);
                $this->grupos->cancel($grupoId, 'Disuelto: quedó con menos de ' . $cupoMin . ' estudiantes.');
                foreach ($restantes as $r) {
                    $this->demanda->record((int) $grupo['id_periodo'], (int) $grupo['id_materia'], (int) $r['id_estudiante'], Demanda::MOTIVO_ESPERANDO);
                }
                $this->historial->log($connection, $grupoId, 'disuelto', 'por_aprobar', 'cancelado', $adminUserId,
                    'Quedó con ' . $quedan . ' estudiante(s), menos del mínimo (' . $cupoMin . '): vuelven a interés registrado.');
            }
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            error_log('Retiro manual grupo ' . $grupoId . ': ' . $exception->getMessage());
            return ['No se pudo retirar al estudiante.', null];
        }

        try {
            $notif = new Notificacion();
            if ($est !== null) {
                $notif->notifyEventoGrupo($connection, (int) $est['id_usuario'], $grupoId, 'retiro_manual', 'Retiro de tutoría',
                    'La coordinación te retiró del grupo de ' . $materia . '. Motivo: ' . $motivo . '. Puedes volver a solicitar apoyo.', '/mis-tutorias/', $eventoId);
            }
            foreach ($restantes as $r) {
                $notif->notifyEventoGrupo($connection, (int) $r['id_usuario'], $grupoId, 'grupo_disuelto', 'Tu grupo aún no se forma',
                    'El grupo de ' . $materia . ' quedó con menos de ' . $cupoMin . ' estudiantes. Sigues con interés registrado: te avisaremos cuando se forme.', '/mis-tutorias/', $eventoId);
            }
        } catch (Throwable $exception) {
            error_log('Notificacion retiro manual: ' . $exception->getMessage());
        }

        if ($disuelto) {
            (new AsignacionController())->reprocesarMateriaDelGrupo($grupo);
            return [null, 'El grupo quedó con menos de ' . $cupoMin . ' estudiantes y se disolvió: los demás volvieron a interés registrado.'];
        }
        if ($quedan < $cupoMin) {
            return [null, 'El grupo quedó con ' . $quedan . ' estudiante(s), menos del mínimo (' . $cupoMin . '). Evalúa si conviene cancelarlo.'];
        }

        return [null, null];
    }
}
