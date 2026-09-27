<?php

declare(strict_types=1);

/**
 * Reuniones tutor-estudiante de Modalidades de Grado (db/048, HU-034/035).
 * No hay horario fijo: el tutor vigente registra cada reunion ya ocurrida. La
 * Coordinacion la valida u observa; nada se borra y las correcciones van a la
 * bitacora desde el controlador.
 */
final class MgReunion
{
    public const MODALIDADES = ['presencial' => 'Presencial', 'virtual' => 'Virtual (Teams)'];

    public const ESTADOS = ['registrada' => 'Por validar', 'validada' => 'Validada', 'observada' => 'Observada'];

    /** Campos que se comparan en la bitacora al corregir. */
    public const CAMPOS = ['fecha', 'hora_inicio', 'hora_fin', 'modalidad', 'lugar_o_enlace', 'temas', 'avance_sesion', 'observaciones', 'asistio_estudiante', 'asistio_tutor'];

    private const SELECT = "SELECT r.*, e.id_cohorte, e.etapa_actual, e.id_estudiante,
                CONCAT(u.nombre, ' ', u.apellido) AS estudiante, es.registro_universitario, m.nombre AS modalidad_grado,
                co.nombre AS cohorte, a.id_tutor, CONCAT(ut.nombre, ' ', ut.apellido) AS tutor, ut.id_usuario AS id_usuario_tutor,
                CONCAT(uv.nombre, ' ', uv.apellido) AS validador
             FROM reuniones_mg r
             INNER JOIN expedientes_mg e ON e.id_expediente = r.id_expediente
             INNER JOIN estudiantes es ON es.id_estudiante = e.id_estudiante
             INNER JOIN usuarios u ON u.id_usuario = es.id_usuario
             INNER JOIN modalidades_grado m ON m.id_modalidad = e.id_modalidad
             INNER JOIN cohortes_mg co ON co.id_cohorte = e.id_cohorte
             INNER JOIN asignaciones_tutor_mg a ON a.id_asignacion = r.id_asignacion
             INNER JOIN tutores t ON t.id_tutor = a.id_tutor
             INNER JOIN usuarios ut ON ut.id_usuario = t.id_usuario
             LEFT JOIN usuarios uv ON uv.id_usuario = r.validada_por";

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(self::SELECT . ' WHERE r.id_reunion = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function porExpediente(int $expedienteId): array
    {
        $statement = Database::connection()->prepare(self::SELECT . ' WHERE r.id_expediente = :id ORDER BY r.fecha DESC, r.hora_inicio DESC');
        $statement->execute(['id' => $expedienteId]);

        return $statement->fetchAll();
    }

    /** Listado para la Coordinacion: estado, cohorte, tutor y rango de fechas. */
    public function listar(array $filtros, int $limite = 300): array
    {
        $where = [];
        $params = [];
        if (!empty($filtros['estado']) && isset(self::ESTADOS[$filtros['estado']])) {
            $where[] = 'r.estado_validacion = :estado';
            $params['estado'] = $filtros['estado'];
        }
        foreach (['id_cohorte' => 'e.id_cohorte', 'id_tutor' => 'a.id_tutor', 'id_expediente' => 'r.id_expediente'] as $campo => $columna) {
            if (!empty($filtros[$campo])) {
                $where[] = "{$columna} = :{$campo}";
                $params[$campo] = (int) $filtros[$campo];
            }
        }
        if (!empty($filtros['desde']) && mg_fecha_valida($filtros['desde'])) {
            $where[] = 'r.fecha >= :desde';
            $params['desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta']) && mg_fecha_valida($filtros['hasta'])) {
            $where[] = 'r.fecha <= :hasta';
            $params['hasta'] = $filtros['hasta'];
        }
        $statement = Database::connection()->prepare(
            self::SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY r.fecha DESC, r.hora_inicio DESC LIMIT ' . max(1, min($limite, 1000))
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function contarPorEstado(): array
    {
        return array_map('intval', array_column(Database::connection()->query(
            'SELECT estado_validacion, COUNT(*) AS total FROM reuniones_mg GROUP BY estado_validacion'
        )->fetchAll(), 'total', 'estado_validacion'));
    }

    /**
     * Choques (HU-034): otra reunion del mismo tutor (con cualquier tesista) o del
     * mismo estudiante que se superpone en el horario. Devuelve mensajes.
     */
    public function choques(string $fecha, string $inicio, string $fin, int $tutorId, int $estudianteId, ?int $excluir = null): array
    {
        $statement = Database::connection()->prepare(
            "SELECT r.id_reunion, r.hora_inicio, r.hora_fin, a.id_tutor, e.id_estudiante
             FROM reuniones_mg r
             INNER JOIN asignaciones_tutor_mg a ON a.id_asignacion = r.id_asignacion
             INNER JOIN expedientes_mg e ON e.id_expediente = r.id_expediente
             WHERE r.fecha = :fecha AND r.hora_inicio < :fin AND r.hora_fin > :inicio
               AND (a.id_tutor = :tutor OR e.id_estudiante = :est) AND r.id_reunion <> :excluir"
        );
        $statement->execute(['fecha' => $fecha, 'inicio' => $inicio, 'fin' => $fin, 'tutor' => $tutorId, 'est' => $estudianteId, 'excluir' => $excluir ?? 0]);
        $errores = [];
        foreach ($statement->fetchAll() as $fila) {
            $rango = substr((string) $fila['hora_inicio'], 0, 5) . '–' . substr((string) $fila['hora_fin'], 0, 5);
            if ((int) $fila['id_tutor'] === $tutorId) {
                $errores['tutor'] = 'El tutor ya tiene otra reunión registrada de ' . $rango . ' ese día.';
            }
            if ((int) $fila['id_estudiante'] === $estudianteId) {
                $errores['estudiante'] = 'El estudiante ya tiene otra reunión registrada de ' . $rango . ' ese día.';
            }
        }

        return array_values($errores);
    }

    public function crear(PDO $pdo, array $data, int $expedienteId, int $asignacionId, int $userId): int
    {
        $pdo->prepare(
            'INSERT INTO reuniones_mg (id_expediente, id_asignacion, fecha, hora_inicio, hora_fin, modalidad, lugar_o_enlace, temas,
                avance_sesion, observaciones, asistio_estudiante, asistio_tutor, registrada_por)
             VALUES (:exp, :asig, :fecha, :inicio, :fin, :modalidad, :lugar, :temas, :avance, :obs, :ae, :at, :u)'
        )->execute($this->params($data) + ['exp' => $expedienteId, 'asig' => $asignacionId, 'u' => $userId]);

        return (int) $pdo->lastInsertId();
    }

    /** Edicion: una reunion observada que corrige el tutor vuelve a quedar por validar. */
    public function actualizar(PDO $pdo, int $id, array $data, ?string $estado): void
    {
        $pdo->prepare(
            'UPDATE reuniones_mg SET fecha = :fecha, hora_inicio = :inicio, hora_fin = :fin, modalidad = :modalidad,
                lugar_o_enlace = :lugar, temas = :temas, avance_sesion = :avance, observaciones = :obs,
                asistio_estudiante = :ae, asistio_tutor = :at,
                estado_validacion = COALESCE(:estado, estado_validacion)
             WHERE id_reunion = :id'
        )->execute($this->params($data) + ['estado' => $estado, 'id' => $id]);
    }

    private function params(array $data): array
    {
        return [
            'fecha' => $data['fecha'], 'inicio' => $data['hora_inicio'], 'fin' => $data['hora_fin'], 'modalidad' => $data['modalidad'],
            'lugar' => $data['lugar_o_enlace'], 'temas' => $data['temas'], 'avance' => $data['avance_sesion'],
            'obs' => $data['observaciones'], 'ae' => $data['asistio_estudiante'], 'at' => $data['asistio_tutor'],
        ];
    }

    public function lock(PDO $pdo, int $id): ?array
    {
        $statement = $pdo->prepare('SELECT * FROM reuniones_mg WHERE id_reunion = :id FOR UPDATE');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function validar(PDO $pdo, int $id, string $estado, ?string $motivo, int $userId): void
    {
        $pdo->prepare(
            'UPDATE reuniones_mg SET estado_validacion = :estado, motivo_observacion = :motivo, validada_por = :u, fecha_validacion = NOW()
             WHERE id_reunion = :id'
        )->execute(['estado' => $estado, 'motivo' => $motivo, 'u' => $userId, 'id' => $id]);
    }

    /** Resumen para fichas y reportes: total, por estado, asistencias y ultima fecha. */
    public function resumen(int $expedienteId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(estado_validacion = 'validada') AS validadas,
                    SUM(estado_validacion = 'registrada') AS por_validar,
                    SUM(estado_validacion = 'observada') AS observadas,
                    SUM(asistio_estudiante = 'no') AS faltas_estudiante,
                    SUM(asistio_tutor = 'no') AS faltas_tutor,
                    MAX(fecha) AS ultima
             FROM reuniones_mg WHERE id_expediente = :id"
        );
        $statement->execute(['id' => $expedienteId]);
        $row = $statement->fetch() ?: [];

        return array_map(static fn ($v) => $v === null ? null : (is_numeric($v) ? (int) $v : $v), $row);
    }
}
