<?php

declare(strict_types=1);

/**
 * Seguimiento de Modalidades de Grado (MVP-2): reuniones (HU-034/035), informes
 * de avance (HU-037) y atencion de alertas (HU-038).
 *
 * Quien hace que:
 *   - El tutor VIGENTE del expediente registra y edita sus reuniones mientras no
 *     esten validadas, y registra informes de sus tesistas.
 *   - La Coordinacion (mg.validar) valida u observa reuniones y las corrige con
 *     motivo; mg.informe registra informes en nombre del tutor.
 *   - El estudiante solo consulta.
 * Todo cambio despues del registro inicial va a bitacora_mg con antes/despues.
 */
final class MgSeguimientoController
{
    private const RESPUESTAS = ['si', 'no'];

    private MgReunion $reuniones;
    private MgInforme $informes;

    public function __construct()
    {
        $this->reuniones = new MgReunion();
        $this->informes = new MgInforme();
    }

    /**
     * Relacion del usuario actual con el expediente: 'gestion' (equipo MG o admin),
     * 'tutor' (tutor vigente), 'estudiante' (el titular) o null (sin acceso).
     */
    public static function acceso(array $expediente): ?string
    {
        if (Auth::canDo('mg.ver')) {
            return 'gestion';
        }
        if (!Auth::canDo('mg.propio')) {
            return null;
        }
        $user = Auth::user();
        $userId = (int) ($user['id_usuario'] ?? 0);
        if (($user['nombre_rol'] ?? '') === 'tutor' && (int) ($expediente['id_usuario_tutor'] ?? 0) === $userId) {
            return 'tutor';
        }
        if (($user['nombre_rol'] ?? '') === 'estudiante' && (int) $expediente['id_usuario_estudiante'] === $userId) {
            return 'estudiante';
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Reuniones
    // ------------------------------------------------------------------

    public function normalizarReunion(array $input): array
    {
        $modalidad = (string) ($input['modalidad'] ?? '');
        $respuesta = static fn ($v): string => in_array($v, self::RESPUESTAS, true) ? $v : '';

        return [
            'fecha' => trim((string) ($input['fecha'] ?? '')),
            'hora_inicio' => mg_hora((string) ($input['hora_inicio'] ?? '')),
            'hora_fin' => mg_hora((string) ($input['hora_fin'] ?? '')),
            'modalidad' => isset(MgReunion::MODALIDADES[$modalidad]) ? $modalidad : '',
            'lugar_o_enlace' => preg_replace('/\s+/u', ' ', trim((string) ($input['lugar_o_enlace'] ?? ''))) ?? '',
            'temas' => trim((string) ($input['temas'] ?? '')),
            'avance_sesion' => mg_texto_opcional($input['avance_sesion'] ?? '', 1000),
            'observaciones' => mg_texto_opcional($input['observaciones'] ?? '', 1000),
            'asistio_estudiante' => $respuesta($input['asistio_estudiante'] ?? ''),
            'asistio_tutor' => $respuesta($input['asistio_tutor'] ?? ''),
        ];
    }

    /**
     * Reglas de HU-034. $plazo = true aplica la ventana de registro hacia atras
     * (plazo_registro_reunion_dias); la Coordinacion la omite al corregir.
     */
    private function validarReunion(array $data, array $asignacion, int $estudianteId, ?int $excluir, bool $plazo): array
    {
        $errors = [];
        if (!mg_fecha_valida($data['fecha'])) {
            $errors[] = 'La fecha no es válida.';
        }
        if ($data['hora_inicio'] === null || $data['hora_fin'] === null) {
            $errors[] = 'Las horas deben tener formato HH:MM.';
        } elseif ($data['hora_fin'] <= $data['hora_inicio']) {
            $errors[] = 'La hora de fin debe ser posterior a la de inicio.';
        }
        if ($data['modalidad'] === '') {
            $errors[] = 'Elige si la reunión fue presencial o virtual.';
        }
        $lugar = $data['lugar_o_enlace'];
        if (mb_strlen($lugar) < 2 || mb_strlen($lugar) > 255 || preg_match('/[\x00-\x1F\x7F<>]/', $lugar)) {
            $errors[] = $data['modalidad'] === 'virtual'
                ? 'Indica el enlace o el ID de la reunión de Teams (entre 2 y 255 caracteres, sin < >).'
                : 'Indica el lugar de la reunión (entre 2 y 255 caracteres, sin < >).';
        } elseif (preg_match('#^[a-z][a-z0-9+.-]*://#i', $lugar) && !preg_match('#^https://#i', $lugar)) {
            $errors[] = 'El enlace de la reunión debe empezar con https://.';
        }
        if (mb_strlen($data['temas']) < 3 || mb_strlen($data['temas']) > 1000) {
            $errors[] = 'Describe los temas tratados (entre 3 y 1000 caracteres).';
        }
        if ($data['asistio_estudiante'] === '' || $data['asistio_tutor'] === '') {
            $errors[] = 'Marca la asistencia del estudiante y del tutor.';
        }
        if ($errors) {
            return $errors;
        }

        $ahora = date('Y-m-d H:i:s');
        if ($data['fecha'] . ' ' . $data['hora_fin'] > $ahora) {
            $errors[] = 'No se registran reuniones futuras ni en curso: regístrala cuando termine.';
        }
        $minima = date('Y-m-d', strtotime('-' . (int) MgParametro::entero('plazo_registro_reunion_dias', 7) . ' days'));
        if ($plazo && $data['fecha'] < $minima) {
            $errors[] = 'Solo se registran reuniones de los últimos ' . (int) MgParametro::entero('plazo_registro_reunion_dias', 7)
                . ' días (desde el ' . mg_fecha_corta($minima) . '). Para una anterior, pide a la Coordinación que la registre como corrección.';
        }
        if ($data['fecha'] < $asignacion['fecha_asignacion']) {
            $errors[] = 'La fecha es anterior a la asignación del tutor (' . mg_fecha_corta($asignacion['fecha_asignacion']) . ').';
        }
        if ($errors) {
            return $errors;
        }

        return $this->reuniones->choques($data['fecha'], $data['hora_inicio'], $data['hora_fin'], (int) $asignacion['id_tutor'], $estudianteId, $excluir);
    }

    /** El tutor vigente registra una reunion ya realizada. Devuelve [errores, id]. */
    public function registrarReunion(int $expedienteId, array $input, int $userId): array
    {
        $expediente = (new MgExpediente())->find($expedienteId);
        if ($expediente === null || self::acceso($expediente) !== 'tutor') {
            return [['Solo el tutor vigente registra las reuniones de este expediente.'], null];
        }
        if ($expediente['estado'] !== 'activo' || !in_array($expediente['etapa_actual'], ['mg1', 'mg2'], true)) {
            return [['Solo se registran reuniones de un expediente activo en MG1 o MG2.'], null];
        }
        $asignacion = (new MgAsignacion())->vigente($expedienteId);
        $data = $this->normalizarReunion($input);
        $errors = $this->validarReunion($data, $asignacion, (int) $expediente['id_estudiante'], null, true);
        if ($errors) {
            return [$errors, null];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Bloquear la asignacion serializa los registros del mismo tutor-expediente (choques).
            $vigente = (new MgAsignacion())->lockVigente($pdo, $expedienteId);
            if ($vigente === null || (int) $vigente['id_asignacion'] !== (int) $asignacion['id_asignacion']) {
                $pdo->rollBack();
                return [['La asignación del tutor cambió mientras registrabas.'], null];
            }
            // Re-chequeo dentro de la transaccion: el choques() de mas arriba corrio antes del lock,
            // asi que dos envios simultaneos podian pasar ambos la validacion y duplicar el horario.
            $choques = $this->reuniones->choques($data['fecha'], $data['hora_inicio'], $data['hora_fin'], (int) $asignacion['id_tutor'], (int) $expediente['id_estudiante'], null);
            if ($choques) {
                $pdo->rollBack();
                return [$choques, null];
            }
            $id = $this->reuniones->crear($pdo, $data, $expedienteId, (int) $asignacion['id_asignacion'], $userId);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Registrar reunion MG: ' . $exception->getMessage());
            return [['No se pudo registrar la reunión.'], null];
        }

        return [[], $id];
    }

    /**
     * Edita una reunion. El tutor: solo la suya, por validar u observada (vuelve a
     * "por validar"). La Coordinacion: cualquiera, con motivo, y queda en bitacora.
     */
    public function editarReunion(int $reunionId, array $input, int $userId): array
    {
        $reunion = $this->reuniones->find($reunionId);
        if ($reunion === null) {
            return [['La reunión no existe.']];
        }
        $coordinacion = Auth::canDo('mg.validar');
        $esTutor = !$coordinacion && ($reunion['id_usuario_tutor'] ?? null) !== null && (int) $reunion['id_usuario_tutor'] === $userId
            && (Auth::user()['nombre_rol'] ?? '') === 'tutor';
        if (!$coordinacion && !$esTutor) {
            return [['No puedes editar esta reunión.']];
        }
        if ($esTutor && $reunion['estado_validacion'] === 'validada') {
            return [['La reunión ya fue validada: solo la Coordinación puede corregirla.']];
        }
        $motivo = trim((string) ($input['motivo'] ?? ''));
        if ($coordinacion && (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500)) {
            return [['Indica el motivo de la corrección (entre 5 y 500 caracteres).']];
        }
        $data = $this->normalizarReunion($input);
        $asignacion = (new MgAsignacion())->find((int) $reunion['id_asignacion']);
        if ($esTutor && $asignacion['estado'] !== 'vigente') {
            return [['Ya no eres el tutor vigente de este expediente: la corrección la hace la Coordinación.']];
        }
        $errors = $this->validarReunion($data, $asignacion, (int) $reunion['id_estudiante'], $reunionId, $esTutor && $data['fecha'] !== $reunion['fecha']);
        if ($errors) {
            return [$errors];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $antes = $this->reuniones->lock($pdo, $reunionId);
            if ($esTutor && $antes['estado_validacion'] === 'validada') {
                $pdo->rollBack();
                return [['La reunión fue validada mientras editabas.']];
            }
            $nuevoEstado = $esTutor && $antes['estado_validacion'] === 'observada' ? 'registrada' : null;
            $this->reuniones->actualizar($pdo, $reunionId, $data, $nuevoEstado);
            $previo = array_intersect_key($antes, array_flip(MgReunion::CAMPOS));
            $cambios = array_filter($data, static fn ($v, $k): bool => (string) ($previo[$k] ?? '') !== (string) ($v ?? ''), ARRAY_FILTER_USE_BOTH);
            MgBitacora::registrar($pdo, $coordinacion ? 'reunion_corregida' : 'reunion_editada', 'reuniones_mg', $reunionId,
                array_intersect_key($previo, $cambios) + ['estado' => $antes['estado_validacion']],
                $cambios + ['estado' => $nuevoEstado ?? $antes['estado_validacion']] + ($motivo !== '' ? ['motivo' => $motivo] : []));
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Editar reunion MG: ' . $exception->getMessage());
            return [['No se pudo guardar la reunión.']];
        }

        return [[]];
    }

    /** HU-035: registrada/observada -> validada, o -> observada con motivo (notifica al tutor). */
    public function validarReunionCoordinacion(int $reunionId, string $accion, string $motivo, int $userId): ?string
    {
        if (!in_array($accion, ['validar', 'observar'], true)) {
            return 'Acción no válida.';
        }
        $motivo = trim($motivo);
        if ($accion === 'observar' && (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500)) {
            return 'Indica qué debe corregir el tutor (entre 5 y 500 caracteres).';
        }
        $estado = $accion === 'validar' ? 'validada' : 'observada';
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $antes = $this->reuniones->lock($pdo, $reunionId);
            if ($antes === null) {
                $pdo->rollBack();
                return 'La reunión no existe.';
            }
            if ($antes['estado_validacion'] === $estado) {
                $pdo->rollBack();
                return 'La reunión ya está ' . mb_strtolower(MgReunion::ESTADOS[$estado]) . '.';
            }
            $this->reuniones->validar($pdo, $reunionId, $estado, $accion === 'observar' ? $motivo : null, $userId);
            MgBitacora::registrar($pdo, 'reunion_' . $estado, 'reuniones_mg', $reunionId,
                ['estado' => $antes['estado_validacion']], ['estado' => $estado] + ($motivo !== '' ? ['motivo' => $motivo] : []));
            if ($estado === 'observada') {
                $reunion = $this->reuniones->find($reunionId);
                (new Notificacion())->create($pdo, (int) $reunion['id_usuario_tutor'], null, 'mg_reunion_observada', 'Reunión observada',
                    'La Coordinación observó la reunión del ' . mg_fecha_corta($reunion['fecha']) . ' con ' . $reunion['estudiante'] . ': ' . $motivo,
                    'mg/seguimiento.php?expediente=' . (int) $reunion['id_expediente'], 'mg_reunion_observada_' . $reunionId . '_' . time());
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Validar reunion MG: ' . $exception->getMessage());
            return 'No se pudo actualizar la reunión.';
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Informes de avance
    // ------------------------------------------------------------------

    /**
     * Registra o corrige el informe de un hito. Lo hace el tutor vigente o quien
     * tenga mg.informe (en su nombre). Corregir exige motivo y va a bitacora.
     * Devuelve [errores, aviso].
     */
    public function guardarInforme(int $expedienteId, int $hitoId, array $input, int $userId): array
    {
        $expediente = (new MgExpediente())->find($expedienteId);
        $acceso = $expediente ? self::acceso($expediente) : null;
        if ($expediente === null || !($acceso === 'tutor' || ($acceso === 'gestion' && Auth::canDo('mg.informe')))) {
            return [['No puedes registrar informes de este expediente.'], null];
        }
        $hito = (new MgCatalogo())->hito($hitoId);
        if ($hito === null || $hito['tipo'] !== 'informe' || (int) $hito['id_cohorte'] !== (int) $expediente['id_cohorte']) {
            return [['El hito no es un informe de la cohorte del expediente.'], null];
        }
        if ((int) $expediente['requiere_tutor'] !== 1) {
            return [['La modalidad ' . $expediente['modalidad'] . ' no presenta informes de avance.'], null];
        }

        $porcentaje = trim((string) ($input['porcentaje_avance'] ?? ''));
        $data = [
            'porcentaje_avance' => ctype_digit($porcentaje) ? (int) $porcentaje : -1,
            'fecha_presentacion' => trim((string) ($input['fecha_presentacion'] ?? '')),
            'formato' => isset(MgInforme::FORMATOS[$input['formato'] ?? '']) ? (string) $input['formato'] : '',
            'respaldo_fisico' => !empty($input['respaldo_fisico']) ? 1 : 0,
            'observaciones' => mg_texto_opcional($input['observaciones'] ?? '', 1000),
        ];
        $errors = [];
        if ($data['porcentaje_avance'] < 0 || $data['porcentaje_avance'] > 100) {
            $errors[] = 'El avance debe ser un número entero entre 0 y 100.';
        }
        if (!mg_fecha_valida($data['fecha_presentacion'])) {
            $errors[] = 'La fecha de presentación no es válida.';
        } elseif ($data['fecha_presentacion'] > date('Y-m-d')) {
            $errors[] = 'La fecha de presentación no puede ser futura.';
        } elseif ($data['fecha_presentacion'] < $expediente['fecha_inicio']) {
            $errors[] = 'La fecha es anterior al inicio del expediente.';
        }
        if ($data['formato'] === '') {
            $errors[] = 'Indica si el informe fue digital o físico.';
        }
        $existente = $this->informes->find($expedienteId, $hitoId);
        $motivo = trim((string) ($input['motivo'] ?? ''));
        if ($existente !== null && (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500)) {
            $errors[] = 'El informe ya estaba registrado: indica el motivo de la corrección (entre 5 y 500 caracteres).';
        }
        if ($existente === null && $expediente['estado'] !== 'activo') {
            $errors[] = 'El expediente está cerrado: no se registran informes nuevos.';
        }
        if ($errors) {
            return [$errors, null];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            (new MgExpediente())->lock($pdo, $expedienteId);
            $antes = $this->informes->lock($pdo, $expedienteId, $hitoId);
            if (($antes === null) !== ($existente === null)) {
                $pdo->rollBack();
                return [['Otro usuario registró este informe mientras editabas. Vuelve a abrirlo.'], null];
            }
            $tutorId = $expediente['id_tutor'] !== null ? (int) $expediente['id_tutor'] : null;
            $id = $this->informes->guardar($pdo, $antes ? (int) $antes['id_informe'] : null, $data, $expedienteId, $hitoId, $tutorId, $userId);
            if ($antes !== null) {
                MgBitacora::registrar($pdo, 'informe_corregido', 'informes_avance_mg', $id,
                    array_intersect_key($antes, $data), $data + ['motivo' => $motivo]);
            } else {
                MgBitacora::registrar($pdo, 'informe_registrado', 'informes_avance_mg', $id, null, $data + ['hito' => $hito['nombre']]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Informe MG: ' . $exception->getMessage());
            return [['No se pudo guardar el informe.'], null];
        }

        // Solo avisos (RN-MG-13): ni la tardanza ni el avance bajo rechazan el informe.
        $avisos = [];
        if ($data['fecha_presentacion'] > $hito['fecha_limite']) {
            $avisos[] = 'Presentado después de la fecha límite (' . mg_fecha_corta($hito['fecha_limite']) . '): queda como "presentado tarde".';
        }
        if ($hito['avance_esperado_pct'] !== null && $data['porcentaje_avance'] < (int) $hito['avance_esperado_pct']) {
            $avisos[] = 'El avance (' . $data['porcentaje_avance'] . '%) está por debajo del esperado (~' . (int) $hito['avance_esperado_pct'] . '%): aparecerá en el panel de alertas.';
        }

        return [[], implode("\n", $avisos)];
    }

    // ------------------------------------------------------------------
    // Alertas
    // ------------------------------------------------------------------

    /** Marca atendida una alerta abierta, con nota. La clave se toma del calculo, no del formulario. */
    public function atenderAlerta(string $clave, string $nota, int $userId): ?string
    {
        $nota = trim($nota);
        if (mb_strlen($nota) < 5 || mb_strlen($nota) > 500) {
            return 'Escribe una nota de lo que se hizo (entre 5 y 500 caracteres).';
        }
        $alerta = null;
        foreach ((new MgAlerta())->calcular() as $item) {
            if ($item['clave'] === $clave) {
                $alerta = $item;
                break;
            }
        }
        if ($alerta === null) {
            return 'La alerta ya no está vigente.';
        }
        if ($alerta['atencion'] !== null) {
            return 'La alerta ya fue atendida.';
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $id = (new MgAlerta())->atender($pdo, $clave, $alerta['codigo'], $alerta['id_expediente'], $nota, $userId);
            MgBitacora::registrar($pdo, 'alerta_atendida', 'alertas_atendidas_mg', $id, null,
                ['clave' => $clave, 'alerta' => $alerta['titulo'], 'detalle' => $alerta['detalle'], 'nota' => $nota]);
            $pdo->commit();
        } catch (PDOException $exception) {
            $pdo->rollBack();
            if ($exception->getCode() === '23000') {
                return 'La alerta ya fue atendida.';
            }
            error_log('Atender alerta MG: ' . $exception->getMessage());
            return 'No se pudo marcar la alerta.';
        }

        return null;
    }
}
