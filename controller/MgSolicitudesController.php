<?php

declare(strict_types=1);

/**
 * Solicitud de Modalidad de Grado (db/049): el estudiante pide iniciar su proceso con un
 * documento de respaldo obligatorio y la Coordinacion lo aprueba (crea el expediente),
 * lo observa o lo rechaza. El sistema no verifica las notas: la revision es humana.
 */
final class MgSolicitudesController
{
    public const MAX_BYTES = 8388608;

    /** Tipos aceptados (contenido real, no la extension): foto o PDF del documento de notas. */
    private const TIPOS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private MgSolicitud $solicitudes;

    public function __construct()
    {
        $this->solicitudes = new MgSolicitud();
    }

    /** Carpeta fuera de la raiz publica (php/): solo PHP la lee, ver php/mg/solicitudes/documento.php. */
    public static function directorioAlmacen(): string
    {
        $configurado = getenv('MG_STORAGE_DIR');

        return rtrim($configurado !== false && $configurado !== '' ? $configurado : dirname(__DIR__) . '/storage/mg_solicitudes', '/\\');
    }

    public static function rutaDocumento(string $archivo): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png|webp)$/', $archivo)) {
            return null;
        }
        $ruta = self::directorioAlmacen() . DIRECTORY_SEPARATOR . $archivo;

        return is_file($ruta) ? $ruta : null;
    }

    // ------------------------------------------------------------------
    // Estudiante
    // ------------------------------------------------------------------

    /** Devuelve [datos, errores, idSolicitud]. */
    public function enviar(array $input, array $archivo, int $userId): array
    {
        return $this->guardar(null, $input, $archivo, $userId);
    }

    /** Corrige y reenvia una solicitud observada. Devuelve [datos, errores, idSolicitud]. */
    public function reenviar(int $id, array $input, array $archivo, int $userId): array
    {
        return $this->guardar($id, $input, $archivo, $userId);
    }

    private function guardar(?int $id, array $input, array $archivo, int $userId): array
    {
        $perfil = $this->solicitudes->perfilEstudiante($userId);
        $data = [
            'id_estudiante' => (int) ($perfil['id_estudiante'] ?? 0),
            'id_modalidad' => (int) filter_var($input['id_modalidad'] ?? 0, FILTER_VALIDATE_INT),
            'situacion' => isset(MgSolicitud::SITUACIONES[$input['situacion'] ?? '']) ? $input['situacion'] : '',
            'titulo_propuesto' => mg_texto_opcional($input['titulo_propuesto'] ?? '', 255),
            'mensaje' => mg_texto_opcional($input['mensaje'] ?? '', 1000),
            'carnet_identidad' => trim((string) ($input['carnet_identidad'] ?? '')),
        ];
        $errors = [];
        if ($perfil === null) {
            return [$data, ['Tu cuenta no tiene perfil de estudiante.'], null];
        }

        $modalidad = $data['id_modalidad'] ? (new MgCatalogo())->modalidad($data['id_modalidad']) : null;
        if ($modalidad === null || (int) $modalidad['activa'] !== 1) {
            $errors[] = 'Selecciona una modalidad de grado activa.';
        }
        if ($data['situacion'] === '') {
            $errors[] = 'Indica si cursas el último semestre o ya egresaste.';
        }
        if (!MgSolicitud::semestreHabilitado((int) $perfil['semestre'])) {
            $errors[] = 'La solicitud se habilita desde el semestre ' . MgSolicitud::semestreMinimo() . ' (tu cuenta figura en el semestre ' . (int) $perfil['semestre'] . ').';
        }
        if ($this->solicitudes->tieneExpedienteActivo($data['id_estudiante'])) {
            $errors[] = 'Ya tienes un proceso de modalidad de grado activo.';
        }

        $actual = null;
        if ($id !== null) {
            $actual = $this->solicitudes->find($id);
            if ($actual === null || (int) $actual['id_estudiante'] !== $data['id_estudiante'] || $actual['estado'] !== 'observada') {
                return [$data, ['Solo puedes corregir una solicitud observada tuya.'], null];
            }
        } elseif ($this->solicitudes->abierta($data['id_estudiante']) !== null) {
            $errors[] = 'Ya tienes una solicitud en revisión. Espera la respuesta de la Coordinación.';
        }

        $carnetActual = trim((string) ($perfil['carnet_identidad'] ?? ''));
        if ($carnetActual === '') {
            if (($mensaje = validation_ci($data['carnet_identidad'])) !== null) {
                $errors[] = $mensaje;
            }
        }

        $documento = null;
        $hayArchivo = ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hayArchivo || $actual === null) {
            [$documento, $errorDocumento] = $this->validarDocumento($archivo);
            if ($errorDocumento !== null) {
                $errors[] = $errorDocumento;
            }
        }
        if ($errors) {
            return [$data, $errors, null];
        }

        $pdo = Database::connection();
        $guardado = null;
        try {
            if ($documento !== null) {
                $guardado = $this->almacenar($archivo, $documento);
                $data['documento'] = $guardado;
            }
            $pdo->beginTransaction();
            if ($carnetActual === '') {
                $duplicado = $pdo->prepare('SELECT 1 FROM usuarios WHERE carnet_identidad = :ci AND id_usuario <> :u LIMIT 1');
                $duplicado->execute(['ci' => $data['carnet_identidad'], 'u' => $userId]);
                if ($duplicado->fetchColumn()) {
                    throw new DomainException('Ese carnet de identidad ya está registrado en otra cuenta.');
                }
                $pdo->prepare('UPDATE usuarios SET carnet_identidad = :ci WHERE id_usuario = :u')->execute(['ci' => $data['carnet_identidad'], 'u' => $userId]);
            }
            if ($id === null) {
                $id = $this->solicitudes->crear($pdo, $data);
                MgBitacora::registrar($pdo, 'solicitud_creada', 'solicitudes_mg', $id, null, [
                    'modalidad' => $modalidad['nombre'], 'situacion' => $data['situacion'], 'titulo' => $data['titulo_propuesto'], 'documento' => $guardado['nombre'] ?? null, 'hash' => $guardado['hash'] ?? null,
                ]);
            } else {
                $this->solicitudes->reenviar($pdo, $id, $data);
                MgBitacora::registrar($pdo, 'solicitud_reenviada', 'solicitudes_mg', $id,
                    ['estado' => 'observada', 'modalidad' => $actual['modalidad'], 'documento' => $actual['documento_nombre']],
                    ['estado' => 'pendiente', 'modalidad' => $modalidad['nombre'], 'situacion' => $data['situacion'], 'documento' => $guardado['nombre'] ?? $actual['documento_nombre']]);
            }
            (new Notificacion())->notifyMgSolicitudNueva($pdo, $id, trim($perfil['nombre'] . ' ' . $perfil['apellido']), (string) $modalidad['nombre'], $actual !== null);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($guardado !== null) {
                @unlink(self::directorioAlmacen() . DIRECTORY_SEPARATOR . $guardado['archivo']);
            }
            if ($exception instanceof DomainException) {
                return [$data, [$exception->getMessage()], null];
            }
            error_log($exception->getMessage());
            $mensaje = $exception instanceof PDOException && $exception->getCode() === '23000'
                ? 'Ya tienes una solicitud en revisión.'
                : 'No se pudo enviar la solicitud.';

            return [$data, [$mensaje], null];
        }

        return [$data, [], $id];
    }

    /** Valida el archivo subido por su contenido real. Devuelve [meta, error]. */
    private function validarDocumento(array $archivo): array
    {
        $codigo = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($codigo === UPLOAD_ERR_NO_FILE) {
            return [null, 'Adjunta tu documento de notas (foto o PDF): es obligatorio.'];
        }
        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
            return [null, 'El documento supera el tamaño máximo de 8 MB.'];
        }
        $temporal = (string) ($archivo['tmp_name'] ?? '');
        if ($codigo !== UPLOAD_ERR_OK || !is_uploaded_file($temporal)) {
            return [null, 'No se pudo recibir el documento. Vuelve a intentarlo.'];
        }
        $tamano = (int) ($archivo['size'] ?? 0);
        if ($tamano <= 0) {
            return [null, 'El documento está vacío.'];
        }
        if ($tamano > self::MAX_BYTES) {
            return [null, 'El documento supera el tamaño máximo de 8 MB.'];
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($temporal);
        if (!isset(self::TIPOS[$mime])) {
            return [null, 'El documento debe ser un PDF o una foto (JPG, PNG o WEBP).'];
        }
        if ($mime !== 'application/pdf' && @getimagesize($temporal) === false) {
            return [null, 'La imagen no es válida.'];
        }
        $nombre = preg_replace('/[\x00-\x1F\x7F\\\\\/]+/u', '', basename(str_replace('\\', '/', (string) ($archivo['name'] ?? 'documento')))) ?? 'documento';

        return [[
            'mime' => $mime,
            'tamano' => $tamano,
            'nombre' => mb_substr(trim($nombre) !== '' ? trim($nombre) : 'documento', 0, 150),
            'hash' => hash_file('sha256', $temporal),
        ], null];
    }

    /** Mueve el archivo a la carpeta privada con nombre aleatorio. */
    private function almacenar(array $archivo, array $meta): array
    {
        $directorio = self::directorioAlmacen();
        if (!is_dir($directorio) && !@mkdir($directorio, 0750, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear la carpeta de documentos: ' . $directorio);
        }
        $nombre = bin2hex(random_bytes(16)) . '.' . self::TIPOS[$meta['mime']];
        if (!move_uploaded_file((string) $archivo['tmp_name'], $directorio . DIRECTORY_SEPARATOR . $nombre)) {
            throw new RuntimeException('No se pudo guardar el documento.');
        }

        return $meta + ['archivo' => $nombre];
    }

    // ------------------------------------------------------------------
    // Coordinacion
    // ------------------------------------------------------------------

    /**
     * Aprobar (crea el expediente con cohorte y etapa inicial), observar o rechazar.
     * Devuelve [error|null, idExpediente|null].
     */
    public function decidir(int $id, string $accion, array $input, int $revisorId): array
    {
        $estados = ['aprobar' => 'aprobada', 'observar' => 'observada', 'rechazar' => 'rechazada'];
        if (!isset($estados[$accion])) {
            return ['Acción no válida.', null];
        }
        $estado = $estados[$accion];
        $motivo = trim((string) ($input['motivo'] ?? ''));
        if ($estado !== 'aprobada' && (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500)) {
            return ['Indica el motivo (entre 5 y 500 caracteres).', null];
        }
        $motivo = $motivo !== '' ? mb_substr($motivo, 0, 500) : null;

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $actual = $this->solicitudes->lock($pdo, $id);
            if ($actual === null) {
                $pdo->rollBack();
                return ['La solicitud no existe.', null];
            }
            $permitido = $estado === 'rechazada' ? in_array($actual['estado'], MgSolicitud::ABIERTAS, true) : $actual['estado'] === 'pendiente';
            if (!$permitido) {
                $pdo->rollBack();
                return ['La solicitud ya no está pendiente de revisión (estado: ' . (MgSolicitud::ESTADOS[$actual['estado']] ?? $actual['estado']) . ').', null];
            }
            $ficha = $this->solicitudes->find($id);

            $idExpediente = null;
            if ($estado === 'aprobada') {
                [$idExpediente, $error] = $this->crearExpediente($pdo, $actual, $input, $revisorId);
                if ($error !== null) {
                    $pdo->rollBack();
                    return [$error, null];
                }
            }
            $this->solicitudes->resolver($pdo, $id, $estado, $motivo, $revisorId, $idExpediente);
            MgBitacora::registrar($pdo, 'solicitud_' . $estado, 'solicitudes_mg', $id,
                ['estado' => $actual['estado']], ['estado' => $estado, 'motivo' => $motivo, 'id_expediente' => $idExpediente]);
            (new Notificacion())->notifyMgSolicitudResuelta($pdo, (int) $ficha['id_usuario_estudiante'], $id, $estado, (string) $ficha['modalidad'], $motivo);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());
            return ['No se pudo registrar la decisión.', null];
        }

        return [null, $idExpediente];
    }

    /** Devuelve [idExpediente|null, error|null]. Corre dentro de la transaccion de decidir(). */
    private function crearExpediente(PDO $pdo, array $solicitud, array $input, int $revisorId): array
    {
        $catalogo = new MgCatalogo();
        $cohorte = ($idCohorte = (int) filter_var($input['id_cohorte'] ?? 0, FILTER_VALIDATE_INT)) ? $catalogo->cohorte($idCohorte) : null;
        $etapa = in_array($input['etapa_actual'] ?? '', ['previa', 'mg1'], true) ? $input['etapa_actual'] : 'previa';
        $fecha = trim((string) ($input['fecha_inicio'] ?? ''));
        if ($cohorte === null || (int) $cohorte['activa'] !== 1) {
            return [null, 'Selecciona una cohorte activa para crear el expediente.'];
        }
        if (!mg_fecha_valida($fecha)) {
            return [null, 'La fecha de inicio no es válida.'];
        }
        $estudianteId = (int) $solicitud['id_estudiante'];
        $modalidadId = (int) $solicitud['id_modalidad'];
        $expedientes = new MgExpediente();
        if ($this->solicitudes->tieneExpedienteActivo($estudianteId)) {
            return [null, 'El estudiante ya tiene un expediente activo.'];
        }
        if ($expedientes->existe($estudianteId, $modalidadId, (int) $cohorte['id_cohorte']) !== null) {
            return [null, 'El estudiante ya tiene un expediente de esa modalidad en esa cohorte.'];
        }
        $estado = $pdo->prepare("SELECT u.estado FROM estudiantes es INNER JOIN usuarios u ON u.id_usuario = es.id_usuario WHERE es.id_estudiante = :e");
        $estado->execute(['e' => $estudianteId]);
        if ($estado->fetchColumn() !== 'activo') {
            return [null, 'La cuenta del estudiante no está activa.'];
        }

        $data = [
            'id_estudiante' => $estudianteId, 'id_modalidad' => $modalidadId, 'id_cohorte' => (int) $cohorte['id_cohorte'],
            'etapa_actual' => $etapa, 'titulo_trabajo' => $solicitud['titulo_propuesto'], 'fecha_inicio' => $fecha,
            'observaciones' => $solicitud['mensaje'], 'origen' => 'solicitud',
        ];
        $id = $expedientes->crear($pdo, $data, $revisorId);
        MgBitacora::registrar($pdo, 'expediente_creado', 'expedientes_mg', $id, null, $data + ['id_solicitud' => (int) $solicitud['id_solicitud']]);

        return [$id, null];
    }
}
