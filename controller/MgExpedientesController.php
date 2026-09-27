<?php

declare(strict_types=1);

/**
 * Expedientes de Modalidades de Grado: alta manual e importacion CSV (HU-023/024),
 * estado y etapa (HU-024), tutor (HU-025/026) y tribunales (HU-028).
 *
 * Las cifras dudosas solo advierten (C-01, RN-MG-21); nada se borra: tutores y
 * tribunales se reemplazan dejando historial, y los cambios quedan en bitacora_mg.
 */
final class MgExpedientesController
{
    /** Tamano maximo del CSV del padron (HU-023). */
    public const MAX_CSV_BYTES = 2097152;

    private const MAX_FILAS = 2000;

    private MgExpediente $expedientes;

    public function __construct()
    {
        $this->expedientes = new MgExpediente();
    }

    // ------------------------------------------------------------------
    // Alta manual, datos, estado y etapa
    // ------------------------------------------------------------------

    public function crear(array $input, int $userId): array
    {
        $data = [
            'id_estudiante' => (int) filter_var($input['id_estudiante'] ?? 0, FILTER_VALIDATE_INT),
            'id_modalidad' => (int) filter_var($input['id_modalidad'] ?? 0, FILTER_VALIDATE_INT),
            'id_cohorte' => (int) filter_var($input['id_cohorte'] ?? 0, FILTER_VALIDATE_INT),
            'etapa_actual' => in_array($input['etapa_actual'] ?? '', ['previa', 'mg1'], true) ? $input['etapa_actual'] : 'previa',
            'titulo_trabajo' => mg_texto_opcional($input['titulo_trabajo'] ?? '', 255),
            'fecha_inicio' => trim((string) ($input['fecha_inicio'] ?? '')),
            'observaciones' => mg_texto_opcional($input['observaciones'] ?? '', 1000),
            'origen' => 'manual',
        ];
        $errors = $this->validarAlta($data);
        if ($errors) {
            return [$data, $errors, null];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $id = $this->expedientes->crear($pdo, $data, $userId);
            MgBitacora::registrar($pdo, 'expediente_creado', 'expedientes_mg', $id, null, $data);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log($exception->getMessage());
            return [$data, ['No se pudo crear el expediente.'], null];
        }

        return [$data, [], $id];
    }

    private function validarAlta(array $data): array
    {
        $errors = [];
        $catalogo = new MgCatalogo();
        $modalidad = $data['id_modalidad'] ? $catalogo->modalidad($data['id_modalidad']) : null;
        $cohorte = $data['id_cohorte'] ? $catalogo->cohorte($data['id_cohorte']) : null;
        $estudiante = array_values(array_filter($this->expedientes->estudiantesActivos(), static fn (array $e): bool => (int) $e['id_estudiante'] === $data['id_estudiante']));
        if ($estudiante === []) {
            $errors[] = 'Seleccione un estudiante con cuenta activa.';
        }
        if ($modalidad === null || (int) $modalidad['activa'] !== 1) {
            $errors[] = 'Seleccione una modalidad activa.';
        }
        if ($cohorte === null || (int) $cohorte['activa'] !== 1) {
            $errors[] = 'Seleccione una cohorte activa.';
        }
        if (!mg_fecha_valida($data['fecha_inicio'])) {
            $errors[] = 'La fecha de inicio no es válida.';
        }
        if (!$errors && $this->expedientes->existe($data['id_estudiante'], $data['id_modalidad'], $data['id_cohorte']) !== null) {
            $errors[] = 'El estudiante ya tiene un expediente de esa modalidad en esa cohorte.';
        }

        return $errors;
    }

    public function actualizarDatos(int $id, array $input): ?string
    {
        $actual = $this->expedientes->find($id);
        if ($actual === null) {
            return 'El expediente no existe.';
        }
        $titulo = mg_texto_opcional($input['titulo_trabajo'] ?? '', 255);
        $observaciones = mg_texto_opcional($input['observaciones'] ?? '', 1000);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->expedientes->actualizarDatos($pdo, $id, $titulo, $observaciones);
            MgBitacora::registrar($pdo, 'expediente_datos', 'expedientes_mg', $id,
                ['titulo_trabajo' => $actual['titulo_trabajo'], 'observaciones' => $actual['observaciones']],
                ['titulo_trabajo' => $titulo, 'observaciones' => $observaciones]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log($exception->getMessage());
            return 'No se pudieron guardar los datos.';
        }

        return null;
    }

    /**
     * Cambia el estado (RN-MG-22: nunca automatico). Reprobado, abandono y retirado
     * exigen motivo; aprobado cierra el proceso (etapa finalizado). Un expediente
     * cerrado se puede reabrir a 'activo' con motivo (correccion administrativa).
     */
    public function cambiarEstado(int $id, string $estado, string $motivo, int $userId): ?string
    {
        if (!isset(MgExpediente::ESTADOS[$estado])) {
            return 'Estado no válido.';
        }
        $motivo = trim($motivo);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $actual = $this->expedientes->lock($pdo, $id);
            if ($actual === null) {
                $pdo->rollBack();
                return 'El expediente no existe.';
            }
            if ($actual['estado'] === $estado) {
                $pdo->rollBack();
                return 'El expediente ya está en ese estado.';
            }
            $exigeMotivo = in_array($estado, MgExpediente::ESTADOS_CON_MOTIVO, true) || $estado === 'activo';
            if ($exigeMotivo && (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500)) {
                $pdo->rollBack();
                return 'Indica el motivo (entre 5 y 500 caracteres).';
            }
            $hoy = date('Y-m-d');
            $this->expedientes->cambiarEstado($pdo, $id, $estado, $hoy, $motivo !== '' ? MgExpediente::ESTADOS[$estado] . ' (' . date('d/m/Y') . '): ' . $motivo : null);
            // Un expediente cerrado libera a su tutor: la asignacion queda 'finalizada' (historial).
            if ($estado !== 'activo' && ($vigente = (new MgAsignacion())->lockVigente($pdo, $id)) !== null) {
                (new MgAsignacion())->cerrar($pdo, (int) $vigente['id_asignacion'], 'finalizada', $hoy, 'Expediente ' . mb_strtolower(MgExpediente::ESTADOS[$estado]), null);
            }
            if ($estado === 'aprobado' && $actual['etapa_actual'] !== 'finalizado') {
                $pdo->prepare("UPDATE expedientes_mg SET etapa_actual = 'finalizado' WHERE id_expediente = :id")->execute(['id' => $id]);
                $pdo->prepare('INSERT INTO expediente_etapas_mg (id_expediente, etapa, fecha_inicio, fecha_fin, resultado, registrado_por) VALUES (:id, \'finalizado\', :f, :f2, \'Aprobado\', :u)')
                    ->execute(['id' => $id, 'f' => $hoy, 'f2' => $hoy, 'u' => $userId]);
            }
            if ($estado === 'activo') {
                // Reapertura: vuelve a abrir la etapa en la que estaba.
                $etapa = $actual['etapa_actual'] === 'finalizado' ? 'mg2' : $actual['etapa_actual'];
                $pdo->prepare('UPDATE expedientes_mg SET etapa_actual = :e, fecha_cierre = NULL WHERE id_expediente = :id')->execute(['e' => $etapa, 'id' => $id]);
                $pdo->prepare('INSERT INTO expediente_etapas_mg (id_expediente, etapa, fecha_inicio, resultado, registrado_por) VALUES (:id, :e, :f, \'Reapertura\', :u)')
                    ->execute(['id' => $id, 'e' => $etapa, 'f' => $hoy, 'u' => $userId]);
            }
            MgBitacora::registrar($pdo, 'expediente_estado', 'expedientes_mg', $id, ['estado' => $actual['estado']], ['estado' => $estado, 'motivo' => $motivo]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log($exception->getMessage());
            return 'No se pudo cambiar el estado.';
        }

        return null;
    }

    /** Previa -> MG1, o MG1 -> MG2 ("Registrar ingreso a MG2", nuevo registro de etapa). */
    public function avanzarEtapa(int $id, string $resultado, int $userId): ?string
    {
        $siguientes = ['previa' => 'mg1', 'mg1' => 'mg2'];
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $actual = $this->expedientes->lock($pdo, $id);
            if ($actual === null || $actual['estado'] !== 'activo') {
                $pdo->rollBack();
                return 'Solo avanza de etapa un expediente activo.';
            }
            $nueva = $siguientes[$actual['etapa_actual']] ?? null;
            if ($nueva === null) {
                $pdo->rollBack();
                return 'El expediente está en ' . MgExpediente::ETAPAS[$actual['etapa_actual']] . ': el proceso se cierra cambiando su estado.';
            }
            $resultado = mb_substr(trim($resultado), 0, 255);
            $this->expedientes->cambiarEtapa($pdo, $id, $nueva, date('Y-m-d'), $resultado !== '' ? $resultado : null, $userId);
            MgBitacora::registrar($pdo, 'expediente_etapa', 'expedientes_mg', $id, ['etapa' => $actual['etapa_actual']], ['etapa' => $nueva, 'resultado' => $resultado]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log($exception->getMessage());
            return 'No se pudo registrar el cambio de etapa.';
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Importacion del padron (HU-023)
    // ------------------------------------------------------------------

    /**
     * Lee el CSV subido (no se guarda) y valida cada fila. Columnas: obligatorias
     * registro_universitario, modalidad y cohorte; opcionales titulo y fecha_inicio.
     * Otras columnas (nombres, correo...) se ignoran. Devuelve [filas, error].
     */
    public function previsualizarImportacion(array $archivo): array
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($archivo['tmp_name'] ?? ''))) {
            return [[], 'Selecciona un archivo CSV.'];
        }
        if ((int) $archivo['size'] > self::MAX_CSV_BYTES) {
            return [[], 'El archivo supera 2 MB.'];
        }
        if (strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION)) !== 'csv') {
            return [[], 'El archivo debe tener extensión .csv.'];
        }

        return $this->validarCsv((string) file_get_contents((string) $archivo['tmp_name']));
    }

    /** Valida el contenido CSV (tambien lo usan las pruebas). */
    public function validarCsv(string $contenido): array
    {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido;
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }
        $lineas = preg_split('/\r\n|\r|\n/', $contenido) ?: [];
        $cabecera = $lineas[0] ?? '';
        $separador = substr_count($cabecera, ';') >= substr_count($cabecera, ',') ? ';' : ',';

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contenido);
        rewind($stream);
        $columnas = fgetcsv($stream, 0, $separador, '"', '\\');
        if (!$columnas) {
            fclose($stream);
            return [[], 'El archivo está vacío.'];
        }
        $columnas = array_map(static fn ($c): string => strtolower(trim((string) $c)), $columnas);
        $indices = array_flip($columnas);
        foreach (['registro_universitario', 'modalidad', 'cohorte'] as $obligatoria) {
            if (!isset($indices[$obligatoria])) {
                fclose($stream);
                return [[], 'Falta la columna "' . $obligatoria . '". Columnas obligatorias: registro_universitario, modalidad, cohorte.'];
            }
        }

        $catalogo = new MgCatalogo();
        $filas = [];
        $vistos = [];
        $numero = 1;
        while (($celdas = fgetcsv($stream, 0, $separador, '"', '\\')) !== false) {
            $numero++;
            if ($celdas === [null] || implode('', array_map('trim', array_map('strval', $celdas))) === '') {
                continue;
            }
            if (count($filas) >= self::MAX_FILAS) {
                fclose($stream);
                return [[], 'El archivo supera ' . self::MAX_FILAS . ' filas.'];
            }
            $valor = static fn (string $col): string => trim((string) ($celdas[$indices[$col] ?? -1] ?? ''));
            $fila = [
                'fila' => $numero,
                'registro_universitario' => $valor('registro_universitario'),
                'modalidad_texto' => $valor('modalidad'),
                'cohorte_texto' => $valor('cohorte'),
                'titulo' => isset($indices['titulo']) ? mb_substr($valor('titulo'), 0, 255) : '',
                'fecha_inicio' => isset($indices['fecha_inicio']) ? $valor('fecha_inicio') : '',
                'estudiante' => null, 'id_estudiante' => null, 'id_modalidad' => null, 'id_cohorte' => null,
                'resultado' => 'creado', 'mensaje' => 'Se creará el expediente.',
            ];
            $fila = $this->validarFila($fila, $catalogo, $vistos);
            if ($fila['resultado'] === 'creado') {
                $vistos[$fila['id_estudiante'] . '-' . $fila['id_modalidad'] . '-' . $fila['id_cohorte']] = true;
            }
            $filas[] = $fila;
        }
        fclose($stream);

        return $filas === [] ? [[], 'El archivo no tiene filas de datos.'] : [$filas, null];
    }

    private function validarFila(array $fila, MgCatalogo $catalogo, array $vistos): array
    {
        $error = static function (array $fila, string $mensaje, string $resultado = 'error'): array {
            $fila['resultado'] = $resultado;
            $fila['mensaje'] = $mensaje;
            return $fila;
        };
        if ($fila['registro_universitario'] === '' || mb_strlen($fila['registro_universitario']) > 30) {
            return $error($fila, 'Registro universitario vacío o demasiado largo.');
        }
        $modalidad = $fila['modalidad_texto'] !== '' ? $catalogo->modalidadPorTexto($fila['modalidad_texto']) : null;
        if ($modalidad === null || (int) $modalidad['activa'] !== 1) {
            return $error($fila, 'Modalidad "' . $fila['modalidad_texto'] . '" no existe o está inactiva.');
        }
        $cohorte = $fila['cohorte_texto'] !== '' ? $catalogo->cohortePorTexto($fila['cohorte_texto']) : null;
        if ($cohorte === null || (int) $cohorte['activa'] !== 1) {
            return $error($fila, 'Cohorte "' . $fila['cohorte_texto'] . '" no existe o está inactiva.');
        }
        $fila['id_modalidad'] = (int) $modalidad['id_modalidad'];
        $fila['id_cohorte'] = (int) $cohorte['id_cohorte'];
        if ($fila['fecha_inicio'] === '') {
            $fila['fecha_inicio'] = (string) $cohorte['fecha_inicio'];
        } elseif (!mg_fecha_valida($fila['fecha_inicio'])) {
            return $error($fila, 'Fecha de inicio no válida (formato AAAA-MM-DD).');
        }
        $estudiante = $this->expedientes->estudiantePorRegistro($fila['registro_universitario']);
        if ($estudiante === null) {
            return $error($fila, 'Sin cuenta en el sistema: el estudiante debe registrarse (o crearlo en Cuentas) y reimportar.', 'pendiente_cuenta');
        }
        $fila['estudiante'] = $estudiante['nombre'];
        $fila['id_estudiante'] = (int) $estudiante['id_estudiante'];
        if ($estudiante['estado'] !== 'activo') {
            return $error($fila, 'La cuenta del estudiante está inactiva.');
        }
        if (isset($vistos[$fila['id_estudiante'] . '-' . $fila['id_modalidad'] . '-' . $fila['id_cohorte']])
            || $this->expedientes->existe($fila['id_estudiante'], $fila['id_modalidad'], $fila['id_cohorte']) !== null) {
            return $error($fila, 'Ya tiene expediente de esa modalidad en esa cohorte: se omite.', 'omitido');
        }

        return $fila;
    }

    /**
     * Crea los expedientes validos. Cada fila se revalida al confirmar (otra persona
     * pudo importar entretanto) y todo queda en importaciones_mg. Devuelve [id, error].
     */
    public function confirmarImportacion(array $filas, string $archivo, int $userId): array
    {
        $catalogo = new MgCatalogo();
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $vistos = [];
            $finales = [];
            foreach ($filas as $fila) {
                if ($fila['resultado'] === 'creado') {
                    $fila = $this->validarFila($fila, $catalogo, $vistos);
                }
                if ($fila['resultado'] === 'creado') {
                    $fila['id_expediente'] = $this->expedientes->crear($pdo, [
                        'id_estudiante' => $fila['id_estudiante'], 'id_modalidad' => $fila['id_modalidad'], 'id_cohorte' => $fila['id_cohorte'],
                        'etapa_actual' => 'previa', 'titulo_trabajo' => $fila['titulo'] !== '' ? $fila['titulo'] : null,
                        'fecha_inicio' => $fila['fecha_inicio'], 'origen' => 'importacion',
                    ], $userId);
                    $fila['mensaje'] = 'Expediente creado.';
                    $vistos[$fila['id_estudiante'] . '-' . $fila['id_modalidad'] . '-' . $fila['id_cohorte']] = true;
                }
                $finales[] = $fila;
            }
            $importacionId = $this->expedientes->registrarImportacion($pdo, $archivo, $userId, $finales);
            MgBitacora::registrar($pdo, 'importacion_padron', 'importaciones_mg', $importacionId, null,
                ['archivo' => $archivo, 'filas' => count($finales), 'creados' => count(array_filter($finales, static fn ($f): bool => $f['resultado'] === 'creado'))]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Importacion MG: ' . $exception->getMessage());
            return [null, 'No se pudo completar la importación. No se creó ningún expediente.'];
        }

        return [$importacionId, null];
    }

    // ------------------------------------------------------------------
    // Tutor (HU-025/026)
    // ------------------------------------------------------------------

    /** Advertencias (nunca bloqueos) para asignar $tutorId al expediente. */
    public function avisosTutor(int $expedienteId, int $tutorId): array
    {
        $avisos = [];
        $docente = (new MgAsignacion())->docente($tutorId);
        if ($docente === null) {
            return $avisos;
        }
        $vigente = (new MgAsignacion())->vigente($expedienteId);
        $carga = (int) $docente['carga'] + ($vigente !== null && (int) $vigente['id_tutor'] === $tutorId ? 0 : 1);
        $recomendada = MgParametro::entero('tutor_carga_recomendada');
        $maximo = MgParametro::entero('tutor_max_estudiantes');
        if ($recomendada !== null && $carga > $recomendada) {
            $avisos[] = 'Con esta asignación el docente tendría ' . $carga . ' tesistas vigentes (carga recomendada: ' . $recomendada . '). C-01: es una advertencia, no un bloqueo.';
        }
        if ($maximo !== null && $carga > $maximo) {
            $avisos[] = 'Supera el máximo de ' . $maximo . ' estudiantes por tutor (parámetro pendiente de validar).';
        }
        foreach (['mg1', 'mg2'] as $etapa) {
            foreach ((new MgTribunal())->vigentes($expedienteId, $etapa) as $tribunal) {
                if ((int) $tribunal['id_tutor'] === $tutorId) {
                    $avisos[] = 'El docente es tribunal de ' . MgTribunal::ETAPAS[$etapa] . ' de este mismo expediente (RN-MG-21, por validar con UPDS).';
                }
            }
        }

        return $avisos;
    }

    /**
     * Asigna tutor o, si ya hay uno vigente, lo cambia (renuncia/cambio: exige
     * motivo). Genera la carta de asignacion y notifica. Una asignacion desde la
     * etapa previa inicia MG1. Devuelve [errores, avisos, id documento].
     */
    public function asignarTutor(int $expedienteId, array $input, int $userId): array
    {
        $tutorId = (int) filter_var($input['id_tutor'] ?? 0, FILTER_VALIDATE_INT);
        $fecha = trim((string) ($input['fecha_asignacion'] ?? ''));
        $referencia = mg_texto_opcional($input['referencia_decanatura'] ?? '', 100);
        $disponibilidad = !empty($input['disponibilidad_consultada']);
        $observaciones = mg_texto_opcional($input['observaciones'] ?? '', 500);
        $motivo = trim((string) ($input['motivo_fin'] ?? ''));
        $fechaNota = trim((string) ($input['fecha_nota_renuncia'] ?? ''));

        $expediente = $this->expedientes->find($expedienteId);
        $errors = [];
        if ($expediente === null) {
            return [['El expediente no existe.'], [], null];
        }
        if ($expediente['estado'] !== 'activo') {
            $errors[] = 'Solo se asigna tutor a un expediente activo.';
        }
        if ((int) $expediente['requiere_tutor'] !== 1) {
            $errors[] = 'La modalidad ' . $expediente['modalidad'] . ' no usa tutor (RN-MG-01).';
        }
        $docente = (new MgAsignacion())->docente($tutorId);
        if ($docente === null) {
            $errors[] = 'Seleccione un docente habilitado con cuenta activa.';
        }
        if (!mg_fecha_valida($fecha) || $fecha > date('Y-m-d')) {
            $errors[] = 'La fecha de asignación debe ser válida y no futura.';
        }
        if (!$disponibilidad) {
            $errors[] = 'Confirma que se consultó la disponibilidad del docente (RN-MG-06).';
        }
        if ($referencia === null) {
            $errors[] = 'Indica la referencia de la nota o resolución de Decanatura (RN-MG-05).';
        }
        $vigente = (new MgAsignacion())->vigente($expedienteId);
        if ($vigente !== null) {
            if ((int) $vigente['id_tutor'] === $tutorId) {
                $errors[] = 'Ese docente ya es el tutor vigente.';
            }
            if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500) {
                $errors[] = 'Para cambiar de tutor indica el motivo (renuncia o cambio), entre 5 y 500 caracteres.';
            }
            if ($fechaNota !== '' && !mg_fecha_valida($fechaNota)) {
                $errors[] = 'La fecha de la nota de renuncia no es válida.';
            }
        }
        if ($errors) {
            return [$errors, [], null];
        }
        $avisos = $this->avisosTutor($expedienteId, $tutorId);

        $asignaciones = new MgAsignacion();
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $bloqueado = $this->expedientes->lock($pdo, $expedienteId);
            $actual = $asignaciones->lockVigente($pdo, $expedienteId);
            if (($actual['id_asignacion'] ?? null) !== ($vigente['id_asignacion'] ?? null)) {
                $pdo->rollBack();
                return [['El tutor del expediente cambió mientras editabas. Recarga la página.'], [], null];
            }
            if ($actual !== null) {
                $asignaciones->cerrar($pdo, (int) $actual['id_asignacion'], 'reemplazada', $fecha, $motivo, $fechaNota !== '' ? $fechaNota : null);
            }
            $nuevaId = $asignaciones->crear($pdo, [
                'id_expediente' => $expedienteId, 'id_tutor' => $tutorId, 'fecha_asignacion' => $fecha,
                'referencia_decanatura' => $referencia, 'disponibilidad_consultada' => $disponibilidad, 'observaciones' => $observaciones,
            ], $userId);
            if ($bloqueado['etapa_actual'] === 'previa') {
                $this->expedientes->cambiarEtapa($pdo, $expedienteId, 'mg1', $fecha, 'Tutor asignado', $userId);
            }
            MgBitacora::registrar($pdo, $actual !== null ? 'tutor_cambiado' : 'tutor_asignado', 'asignaciones_tutor_mg', $nuevaId,
                $actual !== null ? ['id_asignacion' => (int) $actual['id_asignacion'], 'id_tutor' => (int) $actual['id_tutor'], 'motivo' => $motivo] : null,
                ['id_tutor' => $tutorId, 'fecha' => $fecha, 'referencia_decanatura' => $referencia, 'avisos' => $avisos]);
            $documentoId = (new MgDocumentosController())->generarCartaAsignacion($pdo, $nuevaId, $userId);
            $this->notificarAsignacion($pdo, $expediente, $nuevaId, $tutorId, $actual);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Asignar tutor MG: ' . $exception->getMessage());
            return [['No se pudo registrar la asignación.'], [], null];
        }

        return [[], $avisos, $documentoId];
    }

    private function notificarAsignacion(PDO $pdo, array $expediente, int $asignacionId, int $tutorId, ?array $anterior): void
    {
        $notificaciones = new Notificacion();
        $nuevo = (new MgAsignacion())->find($asignacionId);
        $notificaciones->create($pdo, (int) $nuevo['id_usuario_tutor'], null, 'mg_tutor_asignado', 'Nuevo tesista de Modalidades de Grado',
            'Se te asignó como tutor de ' . $expediente['estudiante'] . ' (' . $expediente['modalidad'] . ').', 'mg/mis-tesistas.php', 'mg_asignacion_' . $asignacionId . '_tutor');
        $notificaciones->create($pdo, (int) $expediente['id_usuario_estudiante'], null, 'mg_tutor_asignado', 'Tutor de Modalidades de Grado',
            'Tu tutor(a) es ' . $nuevo['tutor'] . '.', 'mg/mi-modalidad.php', 'mg_asignacion_' . $asignacionId . '_estudiante');
        if ($anterior !== null) {
            $previo = (new MgAsignacion())->find((int) $anterior['id_asignacion']);
            $notificaciones->create($pdo, (int) $previo['id_usuario_tutor'], null, 'mg_tutor_reemplazado', 'Cambio de tutor en Modalidades de Grado',
                'Dejas de ser tutor de ' . $expediente['estudiante'] . '.', 'mg/mis-tesistas.php', 'mg_asignacion_' . $anterior['id_asignacion'] . '_fin');
        }
    }

    // ------------------------------------------------------------------
    // Tribunales (HU-028)
    // ------------------------------------------------------------------

    /**
     * Guarda los tribunales de la etapa: uno por puesto, docentes distintos. Un
     * cambio de docente en un puesto exige motivo. Devuelve [errores, avisos].
     */
    public function guardarTribunales(int $expedienteId, string $etapa, array $input, int $userId): array
    {
        if (!isset(MgTribunal::ETAPAS[$etapa])) {
            return [['Etapa no válida.'], []];
        }
        $expediente = $this->expedientes->find($expedienteId);
        if ($expediente === null || $expediente['estado'] !== 'activo') {
            return [['Solo se asignan tribunales a un expediente activo.'], []];
        }
        $puestos = max(1, (int) MgParametro::entero('tribunales_por_defensa_' . $etapa, 2));
        $elegidos = [];
        $errors = [];
        $asignaciones = new MgAsignacion();
        for ($orden = 1; $orden <= $puestos; $orden++) {
            $tutorId = (int) filter_var($input['tribunal'][$orden] ?? 0, FILTER_VALIDATE_INT);
            if ($tutorId === 0) {
                continue;
            }
            if ($asignaciones->docente($tutorId) === null) {
                $errors[] = 'El docente del puesto ' . $orden . ' no está habilitado.';
            }
            $elegidos[$orden] = $tutorId;
        }
        if ($elegidos === []) {
            $errors[] = 'Selecciona al menos un tribunal.';
        }
        if (count($elegidos) !== count(array_unique($elegidos))) {
            $errors[] = 'Los tribunales deben ser docentes distintos.';
        }
        $vigentes = (new MgTribunal())->vigentes($expedienteId, $etapa);
        $motivo = trim((string) ($input['motivo_cambio'] ?? ''));
        $hayCambio = false;
        foreach ($elegidos as $orden => $tutorId) {
            if (isset($vigentes[$orden]) && (int) $vigentes[$orden]['id_tutor'] !== $tutorId) {
                $hayCambio = true;
            }
        }
        if ($hayCambio && (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500)) {
            $errors[] = 'Para reemplazar un tribunal indica el motivo (entre 5 y 500 caracteres).';
        }
        if ($errors) {
            return [$errors, []];
        }

        $avisos = [];
        $tutorVigente = (int) ($expediente['id_tutor'] ?? 0);
        if ($tutorVigente && in_array($tutorVigente, $elegidos, true)) {
            $avisos[] = 'El tutor del expediente quedó también como tribunal (RN-MG-21: advertencia, por validar con UPDS).';
        }
        if (count($elegidos) < $puestos) {
            $avisos[] = 'Faltan ' . ($puestos - count($elegidos)) . ' tribunal(es) para completar los ' . $puestos . ' de ' . MgTribunal::ETAPAS[$etapa] . '.';
        }

        $tribunales = new MgTribunal();
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->expedientes->lock($pdo, $expedienteId);
            foreach ($elegidos as $orden => $tutorId) {
                [$anterior, $nuevoId] = $tribunales->asignarPuesto($pdo, $expedienteId, $etapa, $orden, $tutorId, date('Y-m-d'), $motivo !== '' ? $motivo : null, $userId);
                if ($nuevoId !== null) {
                    MgBitacora::registrar($pdo, $anterior ? 'tribunal_reemplazado' : 'tribunal_asignado', 'tribunales_mg', $nuevoId,
                        $anterior ? ['id_tribunal' => (int) $anterior['id_tribunal'], 'id_tutor' => (int) $anterior['id_tutor']] : null,
                        ['etapa' => $etapa, 'orden' => $orden, 'id_tutor' => $tutorId, 'motivo' => $motivo]);
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Tribunales MG: ' . $exception->getMessage());
            return [['No se pudieron guardar los tribunales.'], []];
        }

        return [[], $avisos];
    }
}
