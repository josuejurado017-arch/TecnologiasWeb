<?php

declare(strict_types=1);

/**
 * Defensas (HU-029) y calificaciones (HU-031) de Modalidades de Grado.
 *
 * Los choques de horario (ambiente, estudiante, tribunales y tutor) bloquean: son
 * fisicos. Lo que depende de reglas por validar (anticipacion de tribunales) solo
 * advierte. Las autorizaciones fuera de calendario se registran, no se deciden
 * aqui (RN-MG-18).
 */
final class MgDefensasController
{
    public const AUTORIZACIONES = ['decanatura' => 'Decanatura', 'vicerrectorado' => 'Vicerrectorado Académico'];

    private MgDefensa $defensas;

    public function __construct()
    {
        $this->defensas = new MgDefensa();
    }

    private function normalizar(array $input): array
    {
        $autorizado = (string) ($input['autorizado_por'] ?? '');

        return [
            'fecha' => trim((string) ($input['fecha'] ?? '')),
            'hora_inicio' => mg_hora((string) ($input['hora_inicio'] ?? '')),
            'hora_fin' => mg_hora((string) ($input['hora_fin'] ?? '')),
            'ambiente' => preg_replace('/\s+/u', ' ', trim((string) ($input['ambiente'] ?? ''))) ?? '',
            'autorizado_por' => isset(self::AUTORIZACIONES[$autorizado]) ? $autorizado : null,
            'referencia_autorizacion' => mg_texto_opcional($input['referencia_autorizacion'] ?? '', 100),
        ];
    }

    /** Validaciones comunes a programar y reprogramar. */
    private function validar(array $data, array $expediente, string $etapa, ?int $excluir): array
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
        if (($error = validation_label($data['ambiente'], 'ambiente', 100, 2)) !== null) {
            $errors[] = $error;
        }
        if ($data['autorizado_por'] !== null && $data['referencia_autorizacion'] === null) {
            $errors[] = 'Si la fecha fue autorizada por ' . self::AUTORIZACIONES[$data['autorizado_por']] . ', indica la referencia (nota o resolución).';
        }
        if ($errors) {
            return $errors;
        }
        $docentes = array_map(static fn (array $t): int => (int) $t['id_tutor'], (new MgTribunal())->vigentes((int) $expediente['id_expediente'], $etapa));
        if (!empty($expediente['id_tutor'])) {
            $docentes[] = (int) $expediente['id_tutor'];
        }

        return $this->defensas->choques($data['fecha'], $data['hora_inicio'], $data['hora_fin'], $data['ambiente'], (int) $expediente['id_estudiante'], $docentes, $excluir);
    }

    /** Advertencias: tribunales incompletos o asignados con poca anticipacion. */
    public function avisos(int $expedienteId, string $etapa, string $fecha): array
    {
        $avisos = [];
        $requeridos = (int) MgParametro::entero('tribunales_por_defensa_' . $etapa, 2);
        $vigentes = (new MgTribunal())->vigentes($expedienteId, $etapa);
        $anticipacion = (int) MgParametro::entero('dias_anticipacion_tribunal', 14);
        if (count($vigentes) < $requeridos) {
            $dias = (int) floor((strtotime($fecha) - strtotime(date('Y-m-d'))) / 86400);
            $avisos[] = 'La defensa tiene ' . count($vigentes) . ' de ' . $requeridos . ' tribunales'
                . ($dias < $anticipacion ? ' y faltan ' . max(0, $dias) . ' días (se asignan con ~' . $anticipacion . ' días de anticipación).' : '.');
        }

        return $avisos;
    }

    /** Programa la defensa de la etapa actual del expediente. Devuelve [errores, avisos, id]. */
    public function programar(int $expedienteId, array $input, int $userId): array
    {
        $expediente = (new MgExpediente())->find($expedienteId);
        if ($expediente === null) {
            return [['El expediente no existe.'], [], null];
        }
        $etapa = (string) $expediente['etapa_actual'];
        $errors = [];
        if ($expediente['estado'] !== 'activo' || !in_array($etapa, ['mg1', 'mg2'], true)) {
            $errors[] = 'Solo se programa defensa de un expediente activo en MG1 o MG2.';
        } elseif ($this->defensas->programada($expedienteId, $etapa) !== null) {
            $errors[] = 'Ya hay una defensa de ' . MgTribunal::ETAPAS[$etapa] . ' programada: reprográmala o cancélala.';
        }
        $data = $this->normalizar($input);
        if (!$errors) {
            $errors = $this->validar($data, $expediente, $etapa, null);
        }
        if (!$errors && $data['fecha'] < date('Y-m-d')) {
            $errors[] = 'La fecha de la defensa no puede estar en el pasado.';
        }
        if ($errors) {
            return [$errors, [], null];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            (new MgExpediente())->lock($pdo, $expedienteId);
            if ($this->defensas->programada($expedienteId, $etapa) !== null) {
                $pdo->rollBack();
                return [['Ya hay una defensa de ' . MgTribunal::ETAPAS[$etapa] . ' programada: reprográmala o cancélala.'], [], null];
            }
            $id = $this->defensas->crear($pdo, $data + ['id_expediente' => $expedienteId, 'etapa' => $etapa], $userId);
            MgBitacora::registrar($pdo, 'defensa_programada', 'defensas_mg', $id, null, $data + ['etapa' => $etapa]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Programar defensa MG: ' . $exception->getMessage());
            return [['No se pudo programar la defensa.'], [], null];
        }

        return [[], $this->avisos($expedienteId, $etapa, $data['fecha']), $id];
    }

    /** La defensa anterior queda 'reprogramada' (historial) y se crea la nueva. */
    public function reprogramar(int $defensaId, array $input, int $userId): array
    {
        $defensa = $this->defensas->find($defensaId);
        if ($defensa === null || $defensa['estado'] !== 'programada') {
            return [['Solo se reprograma una defensa programada.'], [], null];
        }
        $motivo = trim((string) ($input['motivo'] ?? ''));
        $expediente = (new MgExpediente())->find((int) $defensa['id_expediente']);
        $data = $this->normalizar($input);
        $errors = $this->validar($data, $expediente, (string) $defensa['etapa'], $defensaId);
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500) {
            $errors[] = 'Indica el motivo de la reprogramación (entre 5 y 500 caracteres).';
        }
        if (!$errors && $data['fecha'] < date('Y-m-d')) {
            $errors[] = 'La nueva fecha no puede estar en el pasado.';
        }
        if ($errors) {
            return [$errors, [], null];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $bloqueada = $this->defensas->lock($pdo, $defensaId);
            if ($bloqueada['estado'] !== 'programada') {
                $pdo->rollBack();
                return [['La defensa cambió de estado mientras editabas.'], [], null];
            }
            $this->defensas->cambiarEstado($pdo, $defensaId, 'reprogramada', $motivo);
            $id = $this->defensas->crear($pdo, $data + ['id_expediente' => $defensa['id_expediente'], 'etapa' => $defensa['etapa'], 'id_defensa_anterior' => $defensaId], $userId);
            MgBitacora::registrar($pdo, 'defensa_reprogramada', 'defensas_mg', $id,
                ['id_defensa' => $defensaId, 'fecha' => $defensa['fecha'], 'hora_inicio' => $defensa['hora_inicio'], 'ambiente' => $defensa['ambiente']],
                $data + ['motivo' => $motivo]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Reprogramar defensa MG: ' . $exception->getMessage());
            return [['No se pudo reprogramar la defensa.'], [], null];
        }

        return [[], $this->avisos((int) $defensa['id_expediente'], (string) $defensa['etapa'], $data['fecha']), $id];
    }

    /** Programada -> realizada (con observaciones de fondo y forma) o cancelada (con motivo). */
    public function cambiarEstado(int $defensaId, string $estado, array $input, int $userId): ?string
    {
        if (!in_array($estado, ['realizada', 'cancelada'], true)) {
            return 'Estado no válido.';
        }
        $motivo = trim((string) ($input['motivo'] ?? ''));
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $defensa = $this->defensas->lock($pdo, $defensaId);
            if ($defensa === null || $defensa['estado'] !== 'programada') {
                $pdo->rollBack();
                return 'Solo se actualiza una defensa programada.';
            }
            if ($estado === 'realizada' && $defensa['fecha'] > date('Y-m-d')) {
                $pdo->rollBack();
                return 'Una defensa futura no se puede marcar como realizada.';
            }
            if ($estado === 'cancelada' && (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500)) {
                $pdo->rollBack();
                return 'Indica el motivo de la cancelación (entre 5 y 500 caracteres).';
            }
            $fondo = mg_texto_opcional($input['obs_fondo'] ?? '', 5000);
            $forma = mg_texto_opcional($input['obs_forma'] ?? '', 5000);
            $this->defensas->cambiarEstado($pdo, $defensaId, $estado, $motivo !== '' ? $motivo : null, $fondo, $forma);
            MgBitacora::registrar($pdo, 'defensa_' . $estado, 'defensas_mg', $defensaId, ['estado' => 'programada'], ['estado' => $estado, 'motivo' => $motivo]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log($exception->getMessage());
            return 'No se pudo actualizar la defensa.';
        }

        return null;
    }

    /**
     * Registra o corrige la nota de una defensa realizada. Nunca "en silencio":
     * cada cambio va a bitacora_mg con antes y despues (HU-031). Devuelve [error, sugerencia].
     */
    public function calificar(int $defensaId, array $input, int $userId): array
    {
        $nota = str_replace(',', '.', trim((string) ($input['nota'] ?? '')));
        $minima = (float) MgParametro::entero('nota_minima', 0);
        $maxima = (float) MgParametro::entero('nota_maxima', 100);
        if (!is_numeric($nota) || (float) $nota < $minima || (float) $nota > $maxima || !preg_match('/^\d+(\.\d{1,2})?$/', $nota)) {
            return ['La nota debe ser un número entre ' . $minima . ' y ' . $maxima . ' (hasta 2 decimales).', null];
        }
        $observaciones = mg_texto_opcional($input['observaciones'] ?? '', 1000);
        $publicada = !empty($input['publicada']);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $defensa = $this->defensas->lock($pdo, $defensaId);
            if ($defensa === null || $defensa['estado'] !== 'realizada') {
                $pdo->rollBack();
                return ['Solo se califica una defensa realizada.', null];
            }
            $anterior = $this->defensas->lockCalificacion($pdo, $defensaId);
            if ($anterior !== null && trim((string) ($input['motivo_correccion'] ?? '')) === '') {
                $pdo->rollBack();
                return ['La defensa ya tiene nota: para corregirla indica el motivo.', null];
            }
            $this->defensas->guardarCalificacion($pdo, $defensaId, (float) $nota, $observaciones, $publicada, $userId, $anterior !== null);
            MgBitacora::registrar($pdo, $anterior ? 'nota_corregida' : 'nota_registrada', 'calificaciones_mg', $defensaId,
                $anterior ? ['nota' => (float) $anterior['nota'], 'publicada' => (int) $anterior['publicada'], 'observaciones' => $anterior['observaciones']] : null,
                ['nota' => (float) $nota, 'publicada' => $publicada ? 1 : 0, 'observaciones' => $observaciones, 'motivo' => trim((string) ($input['motivo_correccion'] ?? ''))]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Calificar MG: ' . $exception->getMessage());
            return ['No se pudo guardar la nota.', null];
        }

        // Sugerencia, nunca automatica (RN-MG-22): la Coordinacion decide.
        $aprobacion = (float) MgParametro::entero('nota_aprobacion', 51);
        if ($defensa['etapa'] === 'mg1') {
            $sugerencia = (float) $nota >= $aprobacion
                ? 'Nota de MG1 registrada. Si corresponde, registra el ingreso a MG2 desde la ficha.'
                : 'Nota de MG1 por debajo de ' . $aprobacion . '. Si corresponde, marca el expediente como reprobado desde la ficha.';
        } else {
            $sugerencia = (float) $nota >= $aprobacion
                ? 'Nota de MG2 registrada. Si corresponde, marca el expediente como aprobado desde la ficha.'
                : 'Nota de MG2 por debajo de ' . $aprobacion . '. Si corresponde, marca el expediente como reprobado desde la ficha.';
        }

        return [null, $sugerencia];
    }
}
