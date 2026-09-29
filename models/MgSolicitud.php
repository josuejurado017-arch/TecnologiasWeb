<?php

declare(strict_types=1);

/**
 * Solicitudes de Modalidad de Grado (db/049): el estudiante pide iniciar su proceso
 * con un documento de respaldo y la Coordinacion lo aprueba, observa o rechaza.
 * Aprobar crea el expediente (MgExpedientesController); nada se borra.
 */
final class MgSolicitud
{
    public const ESTADOS = ['pendiente' => 'Pendiente', 'observada' => 'Observada', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada'];

    /** Estados en los que la solicitud sigue abierta (una sola por estudiante, ver UNIQUE en db/049). */
    public const SITUACIONES = ['cursando_ultimo' => 'Cursa el último semestre', 'egresado' => 'Ya egresó'];

    public const ABIERTAS = ['pendiente', 'observada'];

    private const SELECT = "SELECT s.id_solicitud, s.id_estudiante, s.id_modalidad, s.situacion, s.titulo_propuesto, s.mensaje,
                s.documento_archivo, s.documento_nombre, s.documento_mime, s.documento_tamano, s.documento_hash,
                s.estado, s.motivo_revision, s.id_revisor, s.id_expediente, s.fecha_solicitud, s.fecha_actualizacion, s.fecha_revision,
                es.registro_universitario, es.semestre, u.id_usuario AS id_usuario_estudiante,
                CONCAT(u.nombre, ' ', u.apellido) AS estudiante, u.carnet_identidad, u.correo AS correo_estudiante, u.telefono,
                c.nombre_carrera AS carrera, m.nombre AS modalidad, m.codigo AS modalidad_codigo, m.regla_por_validar,
                CONCAT(ur.nombre, ' ', ur.apellido) AS revisor
             FROM solicitudes_mg s
             INNER JOIN estudiantes es ON es.id_estudiante = s.id_estudiante
             INNER JOIN usuarios u ON u.id_usuario = es.id_usuario
             INNER JOIN carreras c ON c.id_carrera = es.id_carrera
             INNER JOIN modalidades_grado m ON m.id_modalidad = s.id_modalidad
             LEFT JOIN usuarios ur ON ur.id_usuario = s.id_revisor";

    /** Filtros: estado y q (nombre, R.U. o carnet de identidad). */
    public function listar(array $filtros = [], int $limite = 200): array
    {
        $where = [];
        $params = [];
        if (!empty($filtros['estado']) && isset(self::ESTADOS[$filtros['estado']])) {
            $where[] = 's.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }
        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE :q OR es.registro_universitario LIKE :q OR u.carnet_identidad LIKE :q)";
            $params['q'] = '%' . addcslashes($q, '%_\\') . '%';
        }
        $sql = self::SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY FIELD(s.estado, 'pendiente', 'observada', 'aprobada', 'rechazada'), s.fecha_solicitud DESC LIMIT " . max(1, min($limite, 500));
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(self::SELECT . ' WHERE s.id_solicitud = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function lock(PDO $pdo, int $id): ?array
    {
        $statement = $pdo->prepare('SELECT * FROM solicitudes_mg WHERE id_solicitud = :id FOR UPDATE');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function porEstudiante(int $estudianteId): array
    {
        $statement = Database::connection()->prepare(self::SELECT . ' WHERE s.id_estudiante = :e ORDER BY s.fecha_solicitud DESC, s.id_solicitud DESC');
        $statement->execute(['e' => $estudianteId]);

        return $statement->fetchAll();
    }

    /** Solicitud abierta (pendiente u observada) del estudiante, si tiene. */
    public function abierta(int $estudianteId): ?array
    {
        foreach ($this->porEstudiante($estudianteId) as $fila) {
            if (in_array($fila['estado'], self::ABIERTAS, true)) {
                return $fila;
            }
        }

        return null;
    }

    public function contarPendientes(): int
    {
        return (int) Database::connection()->query("SELECT COUNT(*) FROM solicitudes_mg WHERE estado = 'pendiente'")->fetchColumn();
    }

    /** Debe correr en transaccion. Devuelve el id de la solicitud nueva. */
    public function crear(PDO $pdo, array $data): int
    {
        $pdo->prepare(
            'INSERT INTO solicitudes_mg (id_estudiante, id_modalidad, situacion, titulo_propuesto, mensaje, documento_archivo, documento_nombre, documento_mime, documento_tamano, documento_hash)
             VALUES (:est, :mod, :situacion, :titulo, :mensaje, :archivo, :nombre, :mime, :tamano, :hash)'
        )->execute([
            'est' => $data['id_estudiante'], 'mod' => $data['id_modalidad'], 'situacion' => $data['situacion'], 'titulo' => $data['titulo_propuesto'], 'mensaje' => $data['mensaje'],
            'archivo' => $data['documento']['archivo'], 'nombre' => $data['documento']['nombre'], 'mime' => $data['documento']['mime'],
            'tamano' => $data['documento']['tamano'], 'hash' => $data['documento']['hash'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** El estudiante corrige una solicitud observada y la reenvia: vuelve a 'pendiente'. */
    public function reenviar(PDO $pdo, int $id, array $data): void
    {
        $sql = "UPDATE solicitudes_mg SET id_modalidad = :mod, situacion = :situacion, titulo_propuesto = :titulo, mensaje = :mensaje, estado = 'pendiente',
                    motivo_revision = NULL, id_revisor = NULL, fecha_revision = NULL";
        $params = ['mod' => $data['id_modalidad'], 'situacion' => $data['situacion'], 'titulo' => $data['titulo_propuesto'], 'mensaje' => $data['mensaje'], 'id' => $id];
        if (!empty($data['documento'])) {
            $sql .= ', documento_archivo = :archivo, documento_nombre = :nombre, documento_mime = :mime, documento_tamano = :tamano, documento_hash = :hash';
            $params += ['archivo' => $data['documento']['archivo'], 'nombre' => $data['documento']['nombre'], 'mime' => $data['documento']['mime'],
                'tamano' => $data['documento']['tamano'], 'hash' => $data['documento']['hash']];
        }
        $pdo->prepare($sql . ' WHERE id_solicitud = :id')->execute($params);
    }

    /** Aprobar, observar o rechazar. $idExpediente solo al aprobar. */
    public function resolver(PDO $pdo, int $id, string $estado, ?string $motivo, int $revisorId, ?int $idExpediente): void
    {
        $pdo->prepare(
            'UPDATE solicitudes_mg SET estado = :estado, motivo_revision = :motivo, id_revisor = :revisor, id_expediente = :expediente, fecha_revision = NOW()
             WHERE id_solicitud = :id'
        )->execute(['estado' => $estado, 'motivo' => $motivo, 'revisor' => $revisorId, 'expediente' => $idExpediente, 'id' => $id]);
    }

    /** Semestre desde el que se puede solicitar (parametro editable por la Coordinacion, db/049). */
    public static function semestreMinimo(): int
    {
        return (int) MgParametro::entero('semestre_minimo_solicitud_mg', 9);
    }

    public static function semestreHabilitado(int $semestre): bool
    {
        return $semestre >= self::semestreMinimo();
    }

    /** Un estudiante con expediente activo ya esta en proceso de grado (modo "solo grado"). */
    public function tieneExpedienteActivo(int $estudianteId): bool
    {
        $statement = Database::connection()->prepare("SELECT 1 FROM expedientes_mg WHERE id_estudiante = :e AND estado = 'activo' LIMIT 1");
        $statement->execute(['e' => $estudianteId]);

        return (bool) $statement->fetchColumn();
    }

    /** Datos del estudiante que ve la Coordinacion junto a la solicitud (se identifica por carnet). */
    public function perfilEstudiante(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT es.id_estudiante, es.registro_universitario, es.semestre, u.id_usuario, u.nombre, u.apellido, u.carnet_identidad,
                    u.correo, u.telefono, c.nombre_carrera AS carrera
             FROM estudiantes es INNER JOIN usuarios u ON u.id_usuario = es.id_usuario
             INNER JOIN carreras c ON c.id_carrera = es.id_carrera WHERE u.id_usuario = :u"
        );
        $statement->execute(['u' => $userId]);
        $row = $statement->fetch();

        return $row ?: null;
    }
}
