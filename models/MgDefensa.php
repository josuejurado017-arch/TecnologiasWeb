<?php

declare(strict_types=1);

/**
 * Defensas y calificaciones de Modalidades de Grado (db/047, HU-029/031).
 * Reprogramar conserva la defensa anterior ('reprogramada') y crea otra.
 */
final class MgDefensa
{
    public const ESTADOS = ['programada' => 'Programada', 'realizada' => 'Realizada', 'reprogramada' => 'Reprogramada', 'cancelada' => 'Cancelada'];

    /** Estados que ocupan agenda (se usan para detectar choques). */
    private const OCUPA = "('programada','realizada')";

    private const SELECT = "SELECT d.*, e.id_estudiante, e.titulo_trabajo, e.id_cohorte, e.estado AS estado_expediente,
                CONCAT(u.nombre, ' ', u.apellido) AS estudiante, es.registro_universitario, m.nombre AS modalidad,
                co.nombre AS cohorte, c.id_calificacion, c.nota, c.publicada, c.observaciones AS obs_calificacion,
                (SELECT COUNT(*) FROM documentos_generados_mg dg WHERE dg.id_defensa = d.id_defensa) AS citaciones
             FROM defensas_mg d
             INNER JOIN expedientes_mg e ON e.id_expediente = d.id_expediente
             INNER JOIN estudiantes es ON es.id_estudiante = e.id_estudiante
             INNER JOIN usuarios u ON u.id_usuario = es.id_usuario
             INNER JOIN modalidades_grado m ON m.id_modalidad = e.id_modalidad
             INNER JOIN cohortes_mg co ON co.id_cohorte = e.id_cohorte
             LEFT JOIN calificaciones_mg c ON c.id_defensa = d.id_defensa";

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(self::SELECT . ' WHERE d.id_defensa = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function porExpediente(int $expedienteId): array
    {
        $statement = Database::connection()->prepare(self::SELECT . ' WHERE d.id_expediente = :id ORDER BY d.fecha DESC, d.hora_inicio DESC');
        $statement->execute(['id' => $expedienteId]);

        return $statement->fetchAll();
    }

    /** Agenda entre dos fechas (incluidas), opcionalmente solo un estado. */
    public function agenda(string $desde, string $hasta, ?string $estado = null): array
    {
        $sql = self::SELECT . ' WHERE d.fecha BETWEEN :desde AND :hasta';
        $params = ['desde' => $desde, 'hasta' => $hasta];
        if ($estado !== null && isset(self::ESTADOS[$estado])) {
            $sql .= ' AND d.estado = :estado';
            $params['estado'] = $estado;
        }
        $statement = Database::connection()->prepare($sql . ' ORDER BY d.fecha, d.hora_inicio, d.ambiente');
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** Defensa vigente (programada) del expediente en la etapa, si la hay. */
    public function programada(int $expedienteId, string $etapa): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT * FROM defensas_mg WHERE id_expediente = :id AND etapa = :etapa AND estado = 'programada' LIMIT 1"
        );
        $statement->execute(['id' => $expedienteId, 'etapa' => $etapa]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /**
     * Choques en el rango (RN-MG-15): mismo ambiente, mismo estudiante o algun
     * docente (tribunal o tutor) ocupado en otra defensa. Devuelve mensajes.
     */
    public function choques(string $fecha, string $inicio, string $fin, string $ambiente, int $estudianteId, array $docentes, ?int $excluir = null): array
    {
        $pdo = Database::connection();
        $choques = [];
        $base = ' FROM defensas_mg d INNER JOIN expedientes_mg e ON e.id_expediente = d.id_expediente
             WHERE d.fecha = :fecha AND d.hora_inicio < :fin AND d.hora_fin > :inicio AND d.estado IN ' . self::OCUPA . ' AND d.id_defensa <> :ex';
        $params = ['fecha' => $fecha, 'inicio' => $inicio, 'fin' => $fin, 'ex' => $excluir ?? 0];

        $ambienteStmt = $pdo->prepare('SELECT d.hora_inicio, d.hora_fin' . $base . ' AND LOWER(TRIM(d.ambiente)) = :amb LIMIT 1');
        $ambienteStmt->execute($params + ['amb' => mb_strtolower(trim($ambiente))]);
        if ($row = $ambienteStmt->fetch()) {
            $choques[] = 'El ambiente "' . $ambiente . '" ya tiene una defensa de ' . substr($row['hora_inicio'], 0, 5) . ' a ' . substr($row['hora_fin'], 0, 5) . '.';
        }

        $estStmt = $pdo->prepare('SELECT d.hora_inicio' . $base . ' AND e.id_estudiante = :est LIMIT 1');
        $estStmt->execute($params + ['est' => $estudianteId]);
        if ($estStmt->fetch()) {
            $choques[] = 'El estudiante ya tiene otra defensa en ese horario.';
        }

        foreach (array_unique(array_map('intval', $docentes)) as $tutorId) {
            // Docente ocupado: es tribunal vigente o tutor vigente de la otra defensa.
            $docStmt = $pdo->prepare(
                'SELECT 1' . $base . "
                   AND (EXISTS (SELECT 1 FROM tribunales_mg tr WHERE tr.id_expediente = d.id_expediente AND tr.etapa = d.etapa AND tr.estado = 'vigente' AND tr.id_tutor = :t1)
                     OR EXISTS (SELECT 1 FROM asignaciones_tutor_mg a WHERE a.id_expediente = d.id_expediente AND a.estado = 'vigente' AND a.id_tutor = :t2))
                 LIMIT 1"
            );
            $docStmt->execute($params + ['t1' => $tutorId, 't2' => $tutorId]);
            if ($docStmt->fetch()) {
                $nombre = $pdo->prepare("SELECT CONCAT(u.nombre, ' ', u.apellido) FROM tutores t INNER JOIN usuarios u ON u.id_usuario = t.id_usuario WHERE t.id_tutor = :t");
                $nombre->execute(['t' => $tutorId]);
                $choques[] = 'El docente ' . $nombre->fetchColumn() . ' ya está en otra defensa en ese horario.';
            }
        }

        return $choques;
    }

    public function crear(PDO $pdo, array $data, int $userId): int
    {
        $pdo->prepare(
            'INSERT INTO defensas_mg (id_expediente, etapa, fecha, hora_inicio, hora_fin, ambiente, autorizado_por, referencia_autorizacion, id_defensa_anterior, registrado_por)
             VALUES (:e, :etapa, :fecha, :ini, :fin, :amb, :aut, :ref, :ant, :u)'
        )->execute([
            'e' => $data['id_expediente'], 'etapa' => $data['etapa'], 'fecha' => $data['fecha'], 'ini' => $data['hora_inicio'],
            'fin' => $data['hora_fin'], 'amb' => $data['ambiente'], 'aut' => $data['autorizado_por'],
            'ref' => $data['referencia_autorizacion'], 'ant' => $data['id_defensa_anterior'] ?? null, 'u' => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function lock(PDO $pdo, int $id): ?array
    {
        $statement = $pdo->prepare('SELECT * FROM defensas_mg WHERE id_defensa = :id FOR UPDATE');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function cambiarEstado(PDO $pdo, int $id, string $estado, ?string $motivo, ?string $obsFondo = null, ?string $obsForma = null): void
    {
        $pdo->prepare(
            'UPDATE defensas_mg SET estado = :estado, motivo_estado = :motivo,
                obs_fondo = COALESCE(:fondo, obs_fondo), obs_forma = COALESCE(:forma, obs_forma) WHERE id_defensa = :id'
        )->execute(['estado' => $estado, 'motivo' => $motivo, 'fondo' => $obsFondo, 'forma' => $obsForma, 'id' => $id]);
    }

    // ------------------------------------------------------------------
    // Calificaciones (HU-031)
    // ------------------------------------------------------------------

    public function lockCalificacion(PDO $pdo, int $defensaId): ?array
    {
        $statement = $pdo->prepare('SELECT * FROM calificaciones_mg WHERE id_defensa = :id FOR UPDATE');
        $statement->execute(['id' => $defensaId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function guardarCalificacion(PDO $pdo, int $defensaId, float $nota, ?string $observaciones, bool $publicada, int $userId, bool $existe): void
    {
        if ($existe) {
            $pdo->prepare(
                'UPDATE calificaciones_mg SET nota = :n, observaciones = :o, publicada = :p, registrada_por = :u, fecha_actualizacion = NOW()
                 WHERE id_defensa = :id'
            )->execute(['n' => $nota, 'o' => $observaciones, 'p' => $publicada ? 1 : 0, 'u' => $userId, 'id' => $defensaId]);
            return;
        }
        $pdo->prepare(
            'INSERT INTO calificaciones_mg (id_defensa, nota, observaciones, publicada, registrada_por) VALUES (:id, :n, :o, :p, :u)'
        )->execute(['id' => $defensaId, 'n' => $nota, 'o' => $observaciones, 'p' => $publicada ? 1 : 0, 'u' => $userId]);
    }

    /** Ultima nota registrada por etapa (defensas realizadas), para el promedio informativo. */
    public function notasPorEtapa(int $expedienteId, bool $soloPublicadas = false): array
    {
        $statement = Database::connection()->prepare(
            "SELECT d.etapa, c.nota, c.publicada FROM defensas_mg d INNER JOIN calificaciones_mg c ON c.id_defensa = d.id_defensa
             WHERE d.id_expediente = :id AND d.estado = 'realizada'" . ($soloPublicadas ? ' AND c.publicada = 1' : '') . '
             ORDER BY d.fecha, d.id_defensa'
        );
        $statement->execute(['id' => $expedienteId]);
        $notas = [];
        foreach ($statement->fetchAll() as $row) {
            $notas[$row['etapa']] = (float) $row['nota'];
        }

        return $notas;
    }
}
