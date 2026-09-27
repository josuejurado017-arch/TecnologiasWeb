<?php

declare(strict_types=1);

/**
 * Informes de avance de Modalidades de Grado (db/048, HU-037). Cada hito de tipo
 * 'informe' del calendario de la cohorte espera un informe por expediente. El
 * estado se calcula (no se guarda) comparando con la fecha limite del hito, y un
 * informe faltante nunca cambia el estado del expediente (RN-MG-12/13, RN-MG-22).
 */
final class MgInforme
{
    public const FORMATOS = ['digital' => 'Digital', 'fisico' => 'Físico'];

    public const ESTADOS = [
        'presentado' => 'Presentado',
        'tarde' => 'Presentado tarde',
        'pendiente' => 'Pendiente',
        'no_presentado' => 'No presentado',
        'no_aplica' => 'No aplica',
    ];

    private const RANGO_ETAPA = ['previa' => 0, 'mg1' => 1, 'mg2' => 2, 'finalizado' => 3];

    /**
     * Estado de un hito de informe para un expediente. $expediente necesita
     * etapa_actual, estado, fecha_cierre y requiere_tutor; $informe es la fila o null.
     */
    public static function estado(array $hito, ?array $informe, array $expediente, string $hoy): string
    {
        if ($informe !== null) {
            return $informe['fecha_presentacion'] > $hito['fecha_limite'] ? 'tarde' : 'presentado';
        }
        if ((int) ($expediente['requiere_tutor'] ?? 1) !== 1) {
            return 'no_aplica';
        }
        $alcanzada = (self::RANGO_ETAPA[$expediente['etapa_actual']] ?? 0) >= (self::RANGO_ETAPA[$hito['etapa']] ?? 0);
        $cerrado = $expediente['estado'] !== 'activo' && !empty($expediente['fecha_cierre']) && $hito['fecha_limite'] > $expediente['fecha_cierre'];
        if ($cerrado || (!$alcanzada && $hito['fecha_limite'] < $hoy)) {
            return 'no_aplica';
        }

        return $hito['fecha_limite'] < $hoy ? 'no_presentado' : 'pendiente';
    }

    public static function badge(string $estado): string
    {
        $clases = ['presentado' => 'badge-success', 'tarde' => 'badge-warning', 'pendiente' => 'badge-info', 'no_presentado' => 'badge-danger', 'no_aplica' => 'badge-neutral'];

        return '<span class="badge ' . ($clases[$estado] ?? '') . '">' . e(self::ESTADOS[$estado] ?? $estado) . '</span>';
    }

    /** Hitos de informe de la cohorte del expediente, cada uno con su informe (o null) y su estado. */
    public function porExpediente(array $expediente): array
    {
        $statement = Database::connection()->prepare(
            "SELECT h.*, i.id_informe, i.porcentaje_avance, i.fecha_presentacion, i.formato, i.respaldo_fisico, i.observaciones AS obs_informe,
                    i.fecha_registro AS informe_registrado, CONCAT(ut.nombre, ' ', ut.apellido) AS presentado_por_nombre,
                    CONCAT(ur.nombre, ' ', ur.apellido) AS registrado_por_nombre
             FROM calendario_mg h
             LEFT JOIN informes_avance_mg i ON i.id_hito = h.id_hito AND i.id_expediente = :exp
             LEFT JOIN tutores t ON t.id_tutor = i.presentado_por
             LEFT JOIN usuarios ut ON ut.id_usuario = t.id_usuario
             LEFT JOIN usuarios ur ON ur.id_usuario = i.registrado_por
             WHERE h.id_cohorte = :coh AND h.tipo = 'informe'
             ORDER BY FIELD(h.etapa, 'previa', 'mg1', 'mg2'), h.fecha_limite, h.orden"
        );
        $statement->execute(['exp' => (int) $expediente['id_expediente'], 'coh' => (int) $expediente['id_cohorte']]);
        $hoy = date('Y-m-d');
        $filas = [];
        foreach ($statement->fetchAll() as $fila) {
            $informe = $fila['id_informe'] !== null ? $fila : null;
            $fila['estado_informe'] = self::estado($fila, $informe, $expediente, $hoy);
            $fila['bajo_esperado'] = $informe !== null && $fila['avance_esperado_pct'] !== null
                && (int) $fila['porcentaje_avance'] < (int) $fila['avance_esperado_pct'];
            $filas[] = $fila;
        }

        return $filas;
    }

    /** Ultimo informe presentado del expediente (avance del reporte por cohorte). */
    public function ultimosAvances(array $expedienteIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $expedienteIds)));
        if (!$ids) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $statement = Database::connection()->prepare(
            "SELECT i.id_expediente, i.porcentaje_avance, h.nombre AS hito, i.fecha_presentacion
             FROM informes_avance_mg i INNER JOIN calendario_mg h ON h.id_hito = i.id_hito
             WHERE i.id_expediente IN ({$marcas})
             ORDER BY i.id_expediente, h.fecha_limite DESC, i.fecha_presentacion DESC"
        );
        $statement->execute($ids);
        $ultimos = [];
        foreach ($statement->fetchAll() as $fila) {
            $ultimos[(int) $fila['id_expediente']] ??= $fila;
        }

        return $ultimos;
    }

    public function find(int $expedienteId, int $hitoId): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM informes_avance_mg WHERE id_expediente = :e AND id_hito = :h');
        $statement->execute(['e' => $expedienteId, 'h' => $hitoId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function lock(PDO $pdo, int $expedienteId, int $hitoId): ?array
    {
        $statement = $pdo->prepare('SELECT * FROM informes_avance_mg WHERE id_expediente = :e AND id_hito = :h FOR UPDATE');
        $statement->execute(['e' => $expedienteId, 'h' => $hitoId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function guardar(PDO $pdo, ?int $id, array $data, int $expedienteId, int $hitoId, ?int $tutorId, int $userId): int
    {
        $params = [
            'pct' => $data['porcentaje_avance'], 'fecha' => $data['fecha_presentacion'], 'formato' => $data['formato'],
            'respaldo' => $data['respaldo_fisico'], 'obs' => $data['observaciones'], 'u' => $userId,
        ];
        if ($id === null) {
            $pdo->prepare(
                'INSERT INTO informes_avance_mg (id_expediente, id_hito, porcentaje_avance, fecha_presentacion, formato, respaldo_fisico, presentado_por, observaciones, registrado_por)
                 VALUES (:e, :h, :pct, :fecha, :formato, :respaldo, :tutor, :obs, :u)'
            )->execute($params + ['e' => $expedienteId, 'h' => $hitoId, 'tutor' => $tutorId]);

            return (int) $pdo->lastInsertId();
        }
        $pdo->prepare(
            'UPDATE informes_avance_mg SET porcentaje_avance = :pct, fecha_presentacion = :fecha, formato = :formato,
                respaldo_fisico = :respaldo, observaciones = :obs, registrado_por = :u WHERE id_informe = :id'
        )->execute($params + ['id' => $id]);

        return $id;
    }

    /** Cantidad de informes registrados en un hito (para no borrar hitos con evidencia). */
    public function contarPorHito(int $hitoId): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM informes_avance_mg WHERE id_hito = :h');
        $statement->execute(['h' => $hitoId]);

        return (int) $statement->fetchColumn();
    }
}
