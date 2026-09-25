<?php

declare(strict_types=1);

/**
 * Metricas del panel principal, alineadas al modelo institucional:
 * campanas -> grupos -> inscripciones -> asistencia/evaluacion.
 */
final class Dashboard
{
    public function summary(string $role, int $userId): array
    {
        $periodo = (new Periodo())->activa();
        $periodoId = $periodo ? (int) $periodo['id_periodo'] : 0;
        $reporte = new ReporteCampania();
        $base = ['periodo_nombre' => $periodo['nombre'] ?? null];

        if ($role === 'tutor') {
            $tutorId = (new Tutoria())->tutorIdByUserId($userId);
            $extra = $periodoId && $tutorId ? $reporte->tutorSummary($tutorId, $periodoId) : [];
            $meta = $this->tutorMeta($userId);

            return array_merge($base, $meta, [
                'etapas' => $periodoId && $tutorId ? (new Grupo())->contarPorEtapa($periodoId, $tutorId) : [],
                'grupos' => (int) ($extra['grupos'] ?? 0),
                'estudiantes' => (int) ($extra['estudiantes'] ?? 0),
                'sesiones_realizadas' => (int) ($extra['sesiones_realizadas'] ?? 0),
                'evaluacion_promedio' => $extra['evaluacion_promedio'] !== null ? (float) ($extra['evaluacion_promedio'] ?? 0) : 0.0,
            ]);
        }

        if ($role === 'estudiante') {
            $studentId = (new Tutoria())->studentIdByUserId($userId);
            $extra = $periodoId && $studentId ? $reporte->studentSummary($studentId, $periodoId) : [];
            $pendientes = $periodoId && $studentId ? count((new Evaluacion())->pendingForStudent($studentId, $periodoId)) : 0;

            $enEspera = $periodoId && $studentId ? count((new Demanda())->pendingForStudent($studentId, $periodoId)) : 0;

            return array_merge($base, $this->studentMeta($userId), [
                'tutorias' => (int) ($extra['tutorias'] ?? 0),
                'asistencias' => (int) ($extra['asistencias'] ?? 0),
                'sesiones_registradas' => (int) ($extra['sesiones_registradas'] ?? 0),
                'evaluaciones_pendientes' => $pendientes,
                'materias_disponibles' => $this->materiasConOferta($periodoId),
                'materias_total' => $this->materiasTotal(),
                'en_espera' => $enEspera,
            ]);
        }

        // Administrador
        $totals = $periodoId ? $reporte->totals($periodoId) : [];
        $sat = $periodoId ? $reporte->satisfaction($periodoId) : [];
        $asis = [];
        foreach (($periodoId ? $reporte->attendanceBreakdown($periodoId) : []) as $row) {
            $asis[$row['estado']] = (int) $row['total'];
        }
        $totalAsis = array_sum($asis);
        $presentes = ($asis['asistio'] ?? 0) + ($asis['parcial'] ?? 0) + ($asis['retraso'] ?? 0);
        $demanda = $periodoId ? (new Demanda())->conversion($periodoId) : [];

        return array_merge($base, $this->adminMeta(), [
            'etapas' => $periodoId ? (new Grupo())->contarPorEtapa($periodoId) : [],
            'demanda_esperando' => (int) ($demanda['pend_esperando'] ?? 0),
            'grupos' => (int) ($totals['grupos'] ?? 0),
            'grupos_confirmados' => (int) ($totals['grupos_confirmados'] ?? 0),
            'grupos_cancelados' => (int) ($totals['grupos_cancelados'] ?? 0),
            'inscritos' => (int) ($totals['inscritos'] ?? 0),
            'estudiantes_campania' => (int) ($totals['estudiantes'] ?? 0),
            'tutores_campania' => (int) ($totals['tutores'] ?? 0),
            'asistencia_pct' => $totalAsis > 0 ? (int) round($presentes * 100 / $totalAsis) : 0,
            'satisfaccion' => $sat['general'] !== null ? (float) ($sat['general'] ?? 0) : 0.0,
            'demanda_pendiente' => (int) ($demanda['pendientes'] ?? 0),
            'demanda_sin_tutor' => (int) ($demanda['pend_sin_tutor'] ?? 0),
            'demanda_materias_sin_tutor' => (int) ($demanda['materias_sin_tutor'] ?? 0),
            'demanda_atendidas' => (int) ($demanda['atendidas'] ?? 0),
        ]);
    }

    /** Proximas sesiones (modelo nuevo) segun el rol. */
    public function upcoming(string $role, int $userId): array
    {
        $sql = <<<'SQL'
            SELECT s.fecha, g.hora_inicio, g.hora_fin, g.modalidad, g.estado AS estado,
                   m.nombre_materia,
                   CONCAT(tu.nombre, ' ', tu.apellido) AS tutor
            FROM sesiones_tutoria s
            INNER JOIN grupos_tutoria g ON g.id_grupo = s.id_grupo
            INNER JOIN materias m ON m.id_materia = g.id_materia
            INNER JOIN tutores tr ON tr.id_tutor = g.id_tutor
            INNER JOIN usuarios tu ON tu.id_usuario = tr.id_usuario
            WHERE s.estado = 'programada' AND s.fecha >= CURRENT_DATE AND g.estado <> 'cancelado'
        SQL;
        $params = [];

        if ($role === 'estudiante') {
            $sql .= " AND EXISTS (SELECT 1 FROM inscripciones i INNER JOIN estudiantes e ON e.id_estudiante = i.id_estudiante
                        WHERE i.id_grupo = g.id_grupo AND i.estado = 'inscrito' AND e.id_usuario = :viewer_id)";
            $params['viewer_id'] = $userId;
        } elseif ($role === 'tutor') {
            $sql .= ' AND tu.id_usuario = :viewer_id';
            $params['viewer_id'] = $userId;
        }

        $sql .= ' ORDER BY s.fecha, g.hora_inicio LIMIT 6';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    // ---------------------- Metadatos por rol ----------------------

    private function adminMeta(): array
    {
        $row = Database::connection()->query(
            "SELECT
                (SELECT COUNT(*) FROM usuarios) AS total_usuarios,
                (SELECT COUNT(*) FROM usuarios WHERE estado = 'activo') AS usuarios_activos,
                (SELECT COUNT(*) FROM estudiantes) AS total_estudiantes,
                (SELECT COUNT(*) FROM tutores) AS total_tutores,
                (SELECT COUNT(*) FROM carreras) AS total_carreras,
                (SELECT COUNT(*) FROM materias) AS total_materias"
        )->fetch();
        $row = $row ?: [];
        $row['grupos_sin_ubicacion'] = (new Grupo())->countSinUbicacion();
        $row['tutores_sin_horarios'] = (new TutorMateriaConfig())->countTutorsWithoutSchedule();

        return $row;
    }

    private function tutorMeta(int $userId): array
    {
        $configurada = TutorMateriaConfig::sqlMateriaConfigurada('tm');
        $statement = Database::connection()->prepare(
            "SELECT
                (SELECT COUNT(*) FROM tutor_materia tm INNER JOIN tutores t ON t.id_tutor = tm.id_tutor WHERE t.id_usuario = :u1) AS materias_asignadas,
                (SELECT COUNT(*) FROM tutor_materia tm INNER JOIN tutores t ON t.id_tutor = tm.id_tutor WHERE t.id_usuario = :u2 AND {$configurada}) AS materias_configuradas"
        );
        $statement->execute(['u1' => $userId, 'u2' => $userId]);

        return $statement->fetch() ?: [];
    }

    private function studentMeta(int $userId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT e.semestre, c.nombre_carrera
             FROM estudiantes e LEFT JOIN carreras c ON c.id_carrera = e.id_carrera
             WHERE e.id_usuario = :id LIMIT 1"
        );
        $statement->execute(['id' => $userId]);

        return $statement->fetch() ?: [];
    }

    /**
     * Materias con oferta: tutor habilitado con horarios configurados O grupo con cupo en el
     * periodo. Misma regla que OfertaMateria (estados grupo_* y por_abrir).
     */
    private function materiasConOferta(int $periodoId): int
    {
        $configurada = TutorMateriaConfig::sqlMateriaConfigurada('tm');
        $habilitado = TutorMateriaConfig::sqlTutorHabilitado();
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) FROM materias m
             WHERE EXISTS (
                 SELECT 1 FROM tutor_materia tm
                 INNER JOIN tutores t ON t.id_tutor = tm.id_tutor
                 INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND {$habilitado}
                 WHERE tm.id_materia = m.id_materia AND {$configurada}
             ) OR EXISTS (
                 SELECT 1 FROM grupos_tutoria g
                 WHERE g.id_materia = m.id_materia AND g.id_periodo = :id_periodo
                   AND g.estado IN ('por_aprobar','formacion','confirmado','en_curso') AND g.cupo_ocupado < g.cupo_max
             )"
        );
        $statement->execute(['id_periodo' => $periodoId]);

        return (int) $statement->fetchColumn();
    }

    private function materiasTotal(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM materias')->fetchColumn();
    }
}
