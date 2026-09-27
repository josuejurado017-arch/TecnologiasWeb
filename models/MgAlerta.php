<?php

declare(strict_types=1);

/**
 * Alertas de Modalidades de Grado (HU-038, prioridad 10 del Coordinador).
 * Se calculan al abrir el panel con consultas (sin cron en el MVP) y ninguna
 * cambia datos: solo avisan. Cada alerta tiene una clave que describe la
 * situacion concreta; marcarla atendida guarda esa clave en alertas_atendidas_mg
 * y, si la situacion cambia (otra fecha, otra carga), la clave cambia y vuelve.
 */
final class MgAlerta
{
    public const CODIGOS = [
        'A1' => 'Expediente sin tutor',
        'A2' => 'Sin reuniones recientes',
        'A3' => 'Pocas reuniones en la semana (MG1)',
        'A4' => 'Informe vencido no presentado',
        'A5' => 'Avance por debajo del esperado',
        'A6' => 'Riesgo de abandono',
        'A7' => 'Defensa próxima sin tribunales completos',
        'A8' => 'Tutor sobre la carga recomendada',
        'A9' => 'Defensa sin citaciones',
    ];

    public const SEVERIDADES = ['alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];

    private const ORDEN_SEVERIDAD = ['alta' => 0, 'media' => 1, 'baja' => 2];

    /**
     * Todas las alertas vigentes. Cada una: codigo, severidad, titulo, detalle,
     * clave, id_expediente, estudiante, id_cohorte, url y atencion (fila o null).
     */
    public function calcular(?string $hoy = null): array
    {
        $hoy ??= date('Y-m-d');
        $pdo = Database::connection();
        $alertas = [];

        $expedientes = $pdo->query(
            "SELECT e.id_expediente, e.id_cohorte, e.etapa_actual, e.estado, e.fecha_cierre, m.requiere_tutor,
                    CONCAT(u.nombre, ' ', u.apellido) AS estudiante, co.nombre AS cohorte,
                    a.id_asignacion, a.id_tutor, a.fecha_asignacion, CONCAT(ut.nombre, ' ', ut.apellido) AS tutor,
                    (SELECT MAX(r.fecha) FROM reuniones_mg r WHERE r.id_expediente = e.id_expediente) AS ultima_reunion
             FROM expedientes_mg e
             INNER JOIN estudiantes es ON es.id_estudiante = e.id_estudiante
             INNER JOIN usuarios u ON u.id_usuario = es.id_usuario
             INNER JOIN modalidades_grado m ON m.id_modalidad = e.id_modalidad
             INNER JOIN cohortes_mg co ON co.id_cohorte = e.id_cohorte
             LEFT JOIN asignaciones_tutor_mg a ON a.id_expediente = e.id_expediente AND a.estado = 'vigente'
             LEFT JOIN tutores t ON t.id_tutor = a.id_tutor
             LEFT JOIN usuarios ut ON ut.id_usuario = t.id_usuario
             WHERE e.estado = 'activo'"
        )->fetchAll();

        $diasSinReunion = (int) MgParametro::entero('dias_alerta_sin_reunion', 10);
        $minSemana = (int) MgParametro::entero('reuniones_min_semana_perfil', 2);
        // Semana anterior completa (lunes a domingo): la actual todavia no termino.
        $lunes = date('Y-m-d', strtotime('monday last week', strtotime($hoy)));
        $domingo = date('Y-m-d', strtotime($lunes . ' +6 days'));
        $semana = date('o-\WW', strtotime($lunes));
        $reunionesSemana = $this->reunionesCompletas($lunes, $domingo);
        [$hitosPorCohorte, $informes] = $this->hitosEInformes();

        foreach ($expedientes as $exp) {
            $id = (int) $exp['id_expediente'];
            $base = ['id_expediente' => $id, 'estudiante' => $exp['estudiante'], 'id_cohorte' => (int) $exp['id_cohorte'], 'url' => 'mg/expedientes/ver.php?id=' . $id];
            $seguimiento = 'mg/seguimiento.php?expediente=' . $id;
            $enCurso = in_array($exp['etapa_actual'], ['mg1', 'mg2'], true);
            $requiere = (int) $exp['requiere_tutor'] === 1;

            // A1: requiere tutor, esta en MG1/MG2 y no lo tiene.
            if ($requiere && $enCurso && $exp['id_asignacion'] === null) {
                $alertas[] = $base + ['codigo' => 'A1', 'severidad' => 'alta', 'clave' => 'A1:e' . $id . ':' . $exp['etapa_actual'],
                    'detalle' => 'En ' . MgExpediente::ETAPAS[$exp['etapa_actual']] . ' sin tutor asignado.'];
            }
            if ($enCurso && $exp['id_asignacion'] !== null) {
                // A2: dias desde la ultima reunion (o desde la asignacion si nunca hubo).
                $referencia = max((string) $exp['fecha_asignacion'], (string) ($exp['ultima_reunion'] ?? ''));
                $dias = (int) floor((strtotime($hoy) - strtotime($referencia)) / 86400);
                if ($dias > $diasSinReunion) {
                    $alertas[] = $base + ['codigo' => 'A2', 'severidad' => 'media', 'clave' => 'A2:e' . $id . ':' . $referencia, 'url' => $seguimiento,
                        'detalle' => ($exp['ultima_reunion'] ? 'Última reunión el ' . mg_fecha_corta($exp['ultima_reunion']) : 'Sin reuniones desde la asignación del ' . mg_fecha_corta($exp['fecha_asignacion']))
                            . ' (' . $dias . ' días; umbral ' . $diasSinReunion . '). Tutor: ' . $exp['tutor'] . '.'];
                }
                // A3: en MG1, menos reuniones (con asistencia de ambos) que el minimo en la semana anterior.
                if ($exp['etapa_actual'] === 'mg1' && $exp['fecha_asignacion'] <= $lunes && $minSemana > 0) {
                    $cantidad = $reunionesSemana[$id] ?? 0;
                    if ($cantidad < $minSemana) {
                        $alertas[] = $base + ['codigo' => 'A3', 'severidad' => 'baja', 'clave' => 'A3:e' . $id . ':' . $semana, 'url' => $seguimiento,
                            'detalle' => $cantidad . ' de ' . $minSemana . ' reuniones con asistencia de ambos en la semana del ' . mg_fecha_corta($lunes) . ' al ' . mg_fecha_corta($domingo) . '.'];
                    }
                }
            }

            // A4, A5, A6: informes de avance de la cohorte.
            $consecutivos = [];
            foreach ($hitosPorCohorte[(int) $exp['id_cohorte']] ?? [] as $hito) {
                $informe = $informes[$id][(int) $hito['id_hito']] ?? null;
                $estado = MgInforme::estado($hito, $informe, $exp, $hoy);
                if ($estado === 'no_presentado') {
                    $alertas[] = $base + ['codigo' => 'A4', 'severidad' => 'media', 'clave' => 'A4:e' . $id . ':h' . $hito['id_hito'], 'url' => $seguimiento,
                        'detalle' => $hito['nombre'] . ': venció el ' . mg_fecha_corta($hito['fecha_limite']) . ' y no se registró.'];
                    $consecutivos[] = $hito;
                } elseif ($estado !== 'no_aplica' && $estado !== 'pendiente') {
                    $consecutivos = [];
                }
                if ($informe !== null && $hito['avance_esperado_pct'] !== null && (int) $informe['porcentaje_avance'] < (int) $hito['avance_esperado_pct']) {
                    $alertas[] = $base + ['codigo' => 'A5', 'severidad' => 'media', 'clave' => 'A5:e' . $id . ':h' . $hito['id_hito'] . ':' . $informe['porcentaje_avance'], 'url' => $seguimiento,
                        'detalle' => $hito['nombre'] . ': ' . (int) $informe['porcentaje_avance'] . '% de avance; se esperaba ~' . (int) $hito['avance_esperado_pct'] . '%.'];
                }
            }
            // A6 (RN-MG-22): dos informes seguidos no presentados. Solo etiqueta: el estado lo decide la Coordinacion.
            if (count($consecutivos) >= 2) {
                $ultimo = end($consecutivos);
                $alertas[] = $base + ['codigo' => 'A6', 'severidad' => 'alta', 'clave' => 'A6:e' . $id . ':h' . $ultimo['id_hito'],
                    'detalle' => count($consecutivos) . ' informes seguidos sin presentar (hasta «' . $ultimo['nombre'] . '»). El expediente no cambia de estado solo: decide la Coordinación.'];
            }
        }

        array_push($alertas, ...$this->alertasDefensas($hoy), ...$this->alertasCarga());

        $atendidas = $this->atendidas(array_column($alertas, 'clave'));
        foreach ($alertas as &$alerta) {
            $alerta['titulo'] = self::CODIGOS[$alerta['codigo']];
            $alerta['atencion'] = $atendidas[$alerta['clave']] ?? null;
        }
        unset($alerta);
        usort($alertas, static fn (array $a, array $b): int => [self::ORDEN_SEVERIDAD[$a['severidad']], $a['codigo'], $a['estudiante'] ?? '']
            <=> [self::ORDEN_SEVERIDAD[$b['severidad']], $b['codigo'], $b['estudiante'] ?? '']);

        return $alertas;
    }

    /** Solo las abiertas (no atendidas). */
    public function abiertas(?string $hoy = null): array
    {
        return array_values(array_filter($this->calcular($hoy), static fn (array $a): bool => $a['atencion'] === null));
    }

    /** Reuniones con asistencia de ambos, por expediente, entre dos fechas. Las observadas no cuentan. */
    private function reunionesCompletas(string $desde, string $hasta): array
    {
        $statement = Database::connection()->prepare(
            "SELECT id_expediente, COUNT(*) AS total FROM reuniones_mg
             WHERE fecha BETWEEN :d AND :h AND asistio_estudiante = 'si' AND asistio_tutor = 'si' AND estado_validacion <> 'observada'
             GROUP BY id_expediente"
        );
        $statement->execute(['d' => $desde, 'h' => $hasta]);

        return array_map('intval', array_column($statement->fetchAll(), 'total', 'id_expediente'));
    }

    /** Hitos de informe por cohorte (en orden) e informes por expediente e hito. */
    private function hitosEInformes(): array
    {
        $pdo = Database::connection();
        $hitos = [];
        foreach ($pdo->query(
            "SELECT * FROM calendario_mg WHERE tipo = 'informe' ORDER BY id_cohorte, FIELD(etapa, 'previa', 'mg1', 'mg2'), fecha_limite, orden"
        )->fetchAll() as $hito) {
            $hitos[(int) $hito['id_cohorte']][] = $hito;
        }
        $informes = [];
        foreach ($pdo->query('SELECT id_expediente, id_hito, porcentaje_avance, fecha_presentacion FROM informes_avance_mg')->fetchAll() as $fila) {
            $informes[(int) $fila['id_expediente']][(int) $fila['id_hito']] = $fila;
        }

        return [$hitos, $informes];
    }

    /** A7 (tribunales incompletos cerca de la defensa) y A9 (sin citaciones). */
    private function alertasDefensas(string $hoy): array
    {
        $alertas = [];
        $anticipacion = (int) MgParametro::entero('dias_anticipacion_tribunal', 14);
        $diasCitaciones = (int) MgParametro::entero('dias_alerta_citaciones', 3);
        $hasta = date('Y-m-d', strtotime($hoy . ' +365 days'));
        $tribunales = new MgTribunal();
        foreach ((new MgDefensa())->agenda($hoy, $hasta, 'programada') as $defensa) {
            if ($defensa['estado_expediente'] !== 'activo') {
                continue;
            }
            $id = (int) $defensa['id_defensa'];
            $dias = (int) floor((strtotime((string) $defensa['fecha']) - strtotime($hoy)) / 86400);
            $base = ['id_expediente' => (int) $defensa['id_expediente'], 'estudiante' => $defensa['estudiante'], 'id_cohorte' => (int) $defensa['id_cohorte'],
                'url' => 'mg/expedientes/ver.php?id=' . (int) $defensa['id_expediente'] . '#tribunales'];
            $cuando = 'Defensa de ' . MgTribunal::ETAPAS[$defensa['etapa']] . ' el ' . mg_fecha_corta($defensa['fecha']) . ' (en ' . $dias . ' días)';
            $requeridos = (int) MgParametro::entero('tribunales_por_defensa_' . $defensa['etapa'], 2);
            $vigentes = count($tribunales->vigentes((int) $defensa['id_expediente'], (string) $defensa['etapa']));
            if ($dias <= $anticipacion && $vigentes < $requeridos) {
                $alertas[] = $base + ['codigo' => 'A7', 'severidad' => 'alta', 'clave' => 'A7:d' . $id . ':' . $vigentes,
                    'detalle' => $cuando . ' con ' . $vigentes . ' de ' . $requeridos . ' tribunales.'];
            }
            if ((int) $defensa['citaciones'] === 0) {
                $alertas[] = $base + ['codigo' => 'A9', 'severidad' => $dias <= $diasCitaciones ? 'alta' : 'media', 'clave' => 'A9:d' . $id,
                    'detalle' => $cuando . ' sin citaciones generadas.'];
            }
        }

        return $alertas;
    }

    /** A8: tutores con mas tesistas vigentes que la carga recomendada (C-01: solo advierte). */
    private function alertasCarga(): array
    {
        $recomendada = (int) MgParametro::entero('tutor_carga_recomendada', 3);
        $statement = Database::connection()->prepare(
            "SELECT t.id_tutor, CONCAT(u.nombre, ' ', u.apellido) AS docente, COUNT(*) AS carga
             FROM asignaciones_tutor_mg a INNER JOIN expedientes_mg e ON e.id_expediente = a.id_expediente AND e.estado = 'activo'
             INNER JOIN tutores t ON t.id_tutor = a.id_tutor INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE a.estado = 'vigente' GROUP BY t.id_tutor, u.nombre, u.apellido HAVING COUNT(*) > :c"
        );
        $statement->execute(['c' => $recomendada]);
        $alertas = [];
        foreach ($statement->fetchAll() as $fila) {
            $alertas[] = ['codigo' => 'A8', 'severidad' => 'media', 'clave' => 'A8:t' . $fila['id_tutor'] . ':' . $fila['carga'],
                'id_expediente' => null, 'estudiante' => null, 'id_cohorte' => null, 'url' => 'mg/expedientes/?id_tutor=' . (int) $fila['id_tutor'],
                'detalle' => $fila['docente'] . ' tiene ' . (int) $fila['carga'] . ' tesistas vigentes (recomendado: ' . $recomendada . '). No es un límite.'];
        }

        return $alertas;
    }

    /** Atenciones registradas para las claves dadas. */
    private function atendidas(array $claves): array
    {
        if (!$claves) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($claves), '?'));
        $statement = Database::connection()->prepare(
            "SELECT a.*, CONCAT(u.nombre, ' ', u.apellido) AS usuario FROM alertas_atendidas_mg a
             LEFT JOIN usuarios u ON u.id_usuario = a.atendida_por WHERE a.clave IN ({$marcas})"
        );
        $statement->execute(array_values($claves));

        return array_column($statement->fetchAll(), null, 'clave');
    }

    public function atender(PDO $pdo, string $clave, string $codigo, ?int $expedienteId, string $nota, int $userId): int
    {
        $pdo->prepare(
            'INSERT INTO alertas_atendidas_mg (clave, codigo, id_expediente, nota, atendida_por) VALUES (:clave, :codigo, :exp, :nota, :u)'
        )->execute(['clave' => $clave, 'codigo' => $codigo, 'exp' => $expedienteId, 'nota' => $nota, 'u' => $userId]);

        return (int) $pdo->lastInsertId();
    }

    /** Alertas abiertas de un expediente (ficha, reporte y vista del tutor). */
    public function delExpediente(int $expedienteId): array
    {
        return array_values(array_filter($this->abiertas(), static fn (array $a): bool => $a['id_expediente'] === $expedienteId));
    }

    public static function badge(string $severidad): string
    {
        $clases = ['alta' => 'badge-danger', 'media' => 'badge-warning', 'baja' => 'badge-info'];

        return '<span class="badge ' . ($clases[$severidad] ?? '') . '">' . e(self::SEVERIDADES[$severidad] ?? $severidad) . '</span>';
    }
}
