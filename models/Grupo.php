<?php

declare(strict_types=1);

final class Grupo
{
    private const DIAS = [
        'Lunes' => 1, 'Martes' => 2, 'Miercoles' => 3,
        'Jueves' => 4, 'Viernes' => 5, 'Sabado' => 6,
    ];

    /** Estados en los que un grupo sigue vivo: su ubicacion importa y puede cambiar. */
    public const ESTADOS_VIGENTES = ['por_aprobar', 'formacion', 'confirmado', 'en_curso'];

    /**
     * Regla institucional de frecuencia (db/033): la decide la demanda al crear el
     * grupo, no el tutor. Con menos de UMBRAL_GRUPO_NORMAL estudiantes el grupo es
     * reducido (LMV por defecto; la coordinacion puede pasarlo a MJS mientras esta
     * por aprobar); desde el umbral es normal (Lunes a Viernes, sin intervencion).
     */
    public const UMBRAL_GRUPO_NORMAL = 8;
    public const PATRON_NORMAL = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'];
    public const PATRONES_REDUCIDOS = [
        'lmv' => ['label' => 'Lunes, miércoles y viernes', 'corto' => 'LMV', 'dias' => ['Lunes', 'Miercoles', 'Viernes']],
        'mjs' => ['label' => 'Martes, jueves y sábado', 'corto' => 'MJS', 'dias' => ['Martes', 'Jueves', 'Sabado']],
    ];

    /** Patrones que el motor puede usar segun la demanda, en orden de preferencia. */
    public static function diasPorDemanda(int $demanda): array
    {
        if ($demanda >= self::UMBRAL_GRUPO_NORMAL) {
            return [self::PATRON_NORMAL];
        }

        return array_column(self::PATRONES_REDUCIDOS, 'dias');
    }

    /** 'lmv' o 'mjs' si los dias son exactamente un patron reducido; null en otro caso. */
    public static function patronReducido(array $dias): ?string
    {
        sort($dias);
        foreach (self::PATRONES_REDUCIDOS as $key => $patron) {
            $esperado = $patron['dias'];
            sort($esperado);
            if ($dias === $esperado) {
                return $key;
            }
        }

        return null;
    }

    /** Condicion SQL "el grupo vigente aun no tiene aula (presencial) o enlace (virtual)". */
    public const SQL_UBICACION_PENDIENTE = "g.estado IN ('por_aprobar','formacion','confirmado','en_curso')
        AND ((g.modalidad = 'presencial' AND (g.ubicacion IS NULL OR g.ubicacion = ''))
          OR (g.modalidad = 'virtual' AND (g.enlace IS NULL OR g.enlace = '')))";

    /**
     * Etapas visibles de un grupo (db/035) como condicion SQL sobre el alias g.
     * 'listo' y 'formacion' son el mismo estado interno (por_aprobar) separado por
     * la cantidad recomendada; 'confirmado' incluye el 'formacion' anterior a 036.
     */
    public static function sqlEtapa(string $etapa): ?string
    {
        $umbral = self::UMBRAL_GRUPO_NORMAL;

        return [
            'listo' => "g.estado = 'por_aprobar' AND g.cupo_ocupado >= {$umbral}",
            'formacion' => "g.estado = 'por_aprobar' AND g.cupo_ocupado < {$umbral}",
            'confirmado' => "g.estado IN ('confirmado','formacion')",
            'en_curso' => "g.estado = 'en_curso'",
            'finalizado' => "g.estado = 'finalizado'",
            'cancelado' => "g.estado = 'cancelado'",
            'sin_ubicacion' => self::SQL_UBICACION_PENDIENTE,
        ][$etapa] ?? null;
    }

    /** Cantidad de grupos del periodo (o de un tutor) en cada etapa visible: pestanas y embudos de los dashboards. */
    public function contarPorEtapa(int $periodoId, ?int $tutorId = null): array
    {
        $columnas = [];
        foreach (['listo', 'formacion', 'confirmado', 'en_curso', 'finalizado', 'cancelado'] as $etapa) {
            $columnas[] = 'COALESCE(SUM(' . self::sqlEtapa($etapa) . "), 0) AS {$etapa}";
        }
        $params = ['id_periodo' => $periodoId];
        $where = 'g.id_periodo = :id_periodo';
        if ($tutorId !== null) {
            $where .= ' AND g.id_tutor = :id_tutor';
            $params['id_tutor'] = $tutorId;
        }
        $statement = Database::connection()->prepare(
            'SELECT ' . implode(', ', $columnas) . " FROM grupos_tutoria g WHERE {$where}"
        );
        $statement->execute($params);

        return array_map('intval', $statement->fetch() ?: []);
    }

    /**
     * Grupos de una campana con tutor, materia, espacio y ubicacion (supervision del
     * administrador). $estado filtra por etapa visible (sqlEtapa); 'sin_ubicacion'
     * devuelve los vigentes con la ubicacion pendiente.
     */
    public function allByPeriodo(int $periodoId, ?string $estado = null): array
    {
        $where = '';
        $params = ['id_periodo' => $periodoId];
        if ($estado !== null && ($condicion = self::sqlEtapa($estado)) !== null) {
            $where = ' AND ' . $condicion;
        }

        $statement = Database::connection()->prepare(
            "SELECT g.id_grupo, g.dia_semana, g.hora_inicio, g.hora_fin, g.modalidad, g.fecha_aprobacion,
                    g.cupo_max, g.cupo_ocupado, g.estado, g.ubicacion, g.enlace, g.enlace_propuesto,
                    m.nombre_materia, e.nombre AS espacio,
                    CONCAT(u.nombre, ' ', u.apellido) AS tutor,
                    GROUP_CONCAT(gd.dia_semana ORDER BY FIELD(gd.dia_semana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') SEPARATOR '/') AS dias
             FROM grupos_tutoria g
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN espacios_tutoria e ON e.id_espacio = g.id_espacio
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             LEFT JOIN grupo_dias gd ON gd.id_grupo = g.id_grupo
             WHERE g.id_periodo = :id_periodo{$where}
             GROUP BY g.id_grupo, g.dia_semana, g.hora_inicio, g.hora_fin, g.modalidad, g.fecha_aprobacion,
                      g.cupo_max, g.cupo_ocupado, g.estado, g.ubicacion, g.enlace, g.enlace_propuesto,
                      m.nombre_materia, e.nombre, tutor
             ORDER BY FIELD(g.estado,'por_aprobar','confirmado','formacion','en_curso','finalizado','cancelado'),
                      (g.estado = 'por_aprobar' AND g.cupo_ocupado >= " . self::UMBRAL_GRUPO_NORMAL . ") DESC,
                      m.nombre_materia, g.hora_inicio"
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** Grupos vigentes del periodo activo con la ubicacion pendiente (contador del admin). */
    public function countSinUbicacion(): int
    {
        return (int) Database::connection()->query(
            "SELECT COUNT(*) FROM grupos_tutoria g INNER JOIN periodos p ON p.id_periodo = g.id_periodo
             WHERE p.estado = 'activa' AND " . self::SQL_UBICACION_PENDIENTE
        )->fetchColumn();
    }

    /** Grupos donde un tutor imparte, dentro de una campana. */
    public function forTutor(int $tutorId, int $periodoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT g.id_grupo, g.dia_semana, g.hora_inicio, g.hora_fin, g.modalidad,
                    g.cupo_max, g.cupo_ocupado, g.estado, g.fecha_aprobacion, g.ubicacion, g.enlace,
                    g.enlace_propuesto, g.fecha_propuesta, m.nombre_materia, e.nombre AS espacio,
                    ep.nombre AS espacio_propuesto,
                    GROUP_CONCAT(gd.dia_semana ORDER BY FIELD(gd.dia_semana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') SEPARATOR '/') AS dias
             FROM grupos_tutoria g
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN espacios_tutoria e ON e.id_espacio = g.id_espacio
             LEFT JOIN espacios_tutoria ep ON ep.id_espacio = g.id_espacio_propuesto
             LEFT JOIN grupo_dias gd ON gd.id_grupo = g.id_grupo
             WHERE g.id_tutor = :id_tutor AND g.id_periodo = :id_periodo
             GROUP BY g.id_grupo, g.dia_semana, g.hora_inicio, g.hora_fin, g.modalidad,
                      g.cupo_max, g.cupo_ocupado, g.estado, g.fecha_aprobacion, g.ubicacion, g.enlace,
                      g.enlace_propuesto, g.fecha_propuesta, m.nombre_materia, e.nombre, ep.nombre
             ORDER BY FIELD(g.dia_semana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'), g.hora_inicio"
        );
        $statement->execute(['id_tutor' => $tutorId, 'id_periodo' => $periodoId]);

        return $statement->fetchAll();
    }

    /**
     * Pasa a 'en_curso' los grupos confirmados cuya primera sesion ya llego (db/035).
     * No hay tareas programadas: se invoca al cargar paginas autenticadas (bootstrap,
     * como maximo una vez cada pocos minutos por sesion). Devuelve cuantos cambiaron.
     */
    public function activarEnCurso(): int
    {
        $pdo = Database::connection();
        $condicion = "g.estado = 'confirmado'
            AND EXISTS (SELECT 1 FROM sesiones_tutoria s WHERE s.id_grupo = g.id_grupo AND s.fecha <= CURDATE())";
        $pdo->beginTransaction();
        try {
            $pdo->exec(
                "INSERT INTO historial_grupo (id_grupo, tipo_evento, estado_anterior, estado_nuevo, id_usuario, motivo)
                 SELECT g.id_grupo, 'en_curso', 'confirmado', 'en_curso', NULL, 'Llegó la fecha de la primera sesión.'
                 FROM grupos_tutoria g WHERE {$condicion}"
            );
            $cambiados = (int) $pdo->exec("UPDATE grupos_tutoria g SET g.estado = 'en_curso', g.fecha_estado = NOW() WHERE {$condicion}");
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return $cambiados;
    }

    /** Candidatos: grupos de una materia con cupo libre, ordenados para llenar los mas ocupados primero. */
    public function candidatesForMatter(int $periodoId, int $matterId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT g.id_grupo, g.id_tutor, g.id_espacio, g.dia_semana, g.hora_inicio, g.hora_fin, g.cupo_max, g.cupo_ocupado, g.estado,
                    GROUP_CONCAT(gd.dia_semana) AS dias
             FROM grupos_tutoria g
             INNER JOIN grupo_dias gd ON gd.id_grupo = g.id_grupo
             WHERE g.id_periodo = :id_periodo AND g.id_materia = :id_materia
               AND g.estado IN ('por_aprobar','formacion','confirmado','en_curso')
               AND g.cupo_ocupado < g.cupo_max
             GROUP BY g.id_grupo
             ORDER BY g.cupo_ocupado DESC, g.id_grupo ASC"
        );
        $statement->execute(['id_periodo' => $periodoId, 'id_materia' => $matterId]);

        // 'dias' = patron semanal completo del grupo (grupo_dias), para chequear solapes en todos sus dias.
        return array_map(static function (array $row): array {
            $row['dias'] = explode(',', (string) $row['dias']);
            return $row;
        }, $statement->fetchAll());
    }

    /** Cupos agotados con estudiantes aún esperando esa materia, para revisión administrativa. */
    public function completosConDemanda(int $periodoId): array
    {
        $stmt = Database::connection()->prepare("SELECT g.id_grupo, g.id_materia, m.nombre_materia,
                g.hora_inicio, g.cupo_ocupado, g.cupo_max, COUNT(DISTINCT d.id_demanda) AS esperando
            FROM grupos_tutoria g INNER JOIN materias m ON m.id_materia = g.id_materia
            INNER JOIN demanda_tutoria d ON d.id_periodo = g.id_periodo AND d.id_materia = g.id_materia AND d.estado = 'pendiente'
            WHERE g.id_periodo = :p AND g.estado IN ('por_aprobar','formacion','confirmado','en_curso')
              AND g.cupo_ocupado >= g.cupo_max
            GROUP BY g.id_grupo, g.id_materia, m.nombre_materia, g.hora_inicio, g.cupo_ocupado, g.cupo_max
            ORDER BY esperando DESC, m.nombre_materia");
        $stmt->execute(['p' => $periodoId]);
        return $stmt->fetchAll();
    }

    /**
     * Regla institucional: un tutor tiene como maximo una tutoria por turno en la
     * campana, sin importar los dias (un grupo reducido LMV debe poder pasar a
     * Lunes a Viernes sin chocar). La usa el motor al crear grupos.
     */
    public function tutorOcupaTurno(int $tutorId, string $horaInicio, string $horaFin, int $periodoId, ?int $excludeGrupoId = null): bool
    {
        $statement = Database::connection()->prepare(
            "SELECT 1 FROM grupos_tutoria
             WHERE id_tutor = :id_tutor AND id_periodo = :id_periodo AND estado <> 'cancelado'
               AND hora_inicio < :hora_fin AND hora_fin > :hora_inicio AND id_grupo <> :excluir
             LIMIT 1"
        );
        $statement->execute([
            'id_tutor' => $tutorId,
            'id_periodo' => $periodoId,
            'hora_fin' => $horaFin,
            'hora_inicio' => $horaInicio,
            'excluir' => $excludeGrupoId ?? 0,
        ]);

        return (bool) $statement->fetchColumn();
    }

    /**
     * Grupos del tutor en el periodo que cuentan para su tope de carga
     * (periodos.max_grupos_tutor, db/037): todos salvo los cancelados o rechazados.
     */
    public function countTutorGrupos(int $tutorId, int $periodoId): int
    {
        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) FROM grupos_tutoria
             WHERE id_tutor = :id_tutor AND id_periodo = :id_periodo AND estado <> 'cancelado'"
        );
        $statement->execute(['id_tutor' => $tutorId, 'id_periodo' => $periodoId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Conflicto de horario del tutor con otro grupo de la misma campana, en
     * cualquiera de los dias del patron (rango solapado). Red de seguridad al
     * aprobar grupos anteriores a la regla de un turno por persona.
     */
    public function tutorHasConflict(int $tutorId, array $dias, string $horaInicio, string $horaFin, int $periodoId, ?int $excludeGrupoId = null): bool
    {
        if (!$dias) {
            return false;
        }
        [$placeholders, $params] = $this->diaPlaceholders($dias);
        $params += ['id_tutor' => $tutorId, 'id_periodo' => $periodoId, 'hora_fin' => $horaFin, 'hora_inicio' => $horaInicio,
            'excluir' => $excludeGrupoId ?? 0];

        $statement = Database::connection()->prepare(
            "SELECT 1 FROM grupos_tutoria g
             INNER JOIN grupo_dias gd ON gd.id_grupo = g.id_grupo
             WHERE g.id_tutor = :id_tutor AND g.id_periodo = :id_periodo AND gd.dia_semana IN ({$placeholders})
               AND g.estado <> 'cancelado' AND g.hora_inicio < :hora_fin AND g.hora_fin > :hora_inicio
               AND g.id_grupo <> :excluir
             LIMIT 1"
        );
        $statement->execute($params);

        return (bool) $statement->fetchColumn();
    }

    /** Placeholders nombrados para un IN (...) dinamico sobre una lista de dias. */
    private function diaPlaceholders(array $dias): array
    {
        $placeholders = [];
        $params = [];
        foreach (array_values($dias) as $i => $dia) {
            $key = 'dia' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $dia;
        }

        return [implode(',', $placeholders), $params];
    }

    /**
     * Crea un grupo y devuelve su id. $data['dias'] es el patron semanal completo
     * (uno o varios dias); grupos_tutoria.dia_semana se queda con el primer dia del
     * patron por retrocompatibilidad de listados/reportes que aun no leen grupo_dias
     * (ver db/024_grupo_dias.sql), y grupo_dias guarda el patron completo.
     */
    public function create(array $data): int
    {
        $dias = array_values(array_unique($data['dias']));
        $connection = Database::connection();
        $statement = $connection->prepare(
            'INSERT INTO grupos_tutoria (id_periodo, id_materia, id_tutor, id_espacio, modalidad, dia_semana, hora_inicio, hora_fin, cupo_max, cupo_ocupado, estado)
             VALUES (:id_periodo, :id_materia, :id_tutor, :id_espacio, :modalidad, :dia_semana, :hora_inicio, :hora_fin, :cupo_max, 0, :estado)'
        );
        $statement->execute([
            'id_periodo' => $data['id_periodo'],
            'id_materia' => $data['id_materia'],
            'id_tutor' => $data['id_tutor'],
            'id_espacio' => $data['id_espacio'],
            'modalidad' => $data['modalidad'],
            'dia_semana' => $dias[0],
            'hora_inicio' => $data['hora_inicio'],
            'hora_fin' => $data['hora_fin'],
            'cupo_max' => $data['cupo_max'],
            'estado' => $data['estado'],
        ]);
        $grupoId = (int) $connection->lastInsertId();

        $insertDia = $connection->prepare('INSERT INTO grupo_dias (id_grupo, dia_semana) VALUES (:id_grupo, :dia_semana)');
        foreach ($dias as $dia) {
            $insertDia->execute(['id_grupo' => $grupoId, 'dia_semana' => $dia]);
        }

        return $grupoId;
    }

    /** Genera las sesiones semanales del grupo entre las fechas de la campana, para cada dia del patron. */
    public function generateSessions(int $grupoId, array $dias, string $fechaInicio, string $fechaFin): int
    {
        $count = 0;
        foreach ($dias as $dia) {
            $count += $this->generateSessionsForDay($grupoId, $dia, $fechaInicio, $fechaFin);
        }

        return $count;
    }

    private function generateSessionsForDay(int $grupoId, string $dia, string $fechaInicio, string $fechaFin): int
    {
        $target = self::DIAS[$dia] ?? null;
        if ($target === null) {
            return 0;
        }
        $start = new DateTimeImmutable($fechaInicio);
        $end = new DateTimeImmutable($fechaFin);
        // Avanzar hasta el primer dia de la semana que coincide.
        $cursor = $start;
        while ((int) $cursor->format('N') !== $target) {
            $cursor = $cursor->modify('+1 day');
            if ($cursor > $end) {
                return 0;
            }
        }
        $statement = Database::connection()->prepare(
            'INSERT IGNORE INTO sesiones_tutoria (id_grupo, fecha) VALUES (:id_grupo, :fecha)'
        );
        $count = 0;
        for ($date = $cursor; $date <= $end; $date = $date->modify('+7 days')) {
            $statement->execute(['id_grupo' => $grupoId, 'fecha' => $date->format('Y-m-d')]);
            $count++;
        }

        return $count;
    }

    /** Datos de un grupo: materia, periodo, tutor, espacio, ubicacion y propuesta del tutor. */
    public function findBasic(int $grupoId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT g.id_grupo, g.id_periodo, g.id_materia, g.id_tutor, g.estado, g.fecha_aprobacion, g.dia_semana, g.hora_inicio, g.hora_fin,
                    g.cupo_max, g.cupo_ocupado, g.modalidad, g.id_espacio, g.ubicacion, g.enlace, g.fecha_ubicacion,
                    g.id_espacio_propuesto, g.enlace_propuesto, g.fecha_propuesta,
                    m.nombre_materia, m.modalidad_requerida, e.nombre AS espacio, ep.nombre AS espacio_propuesto,
                    CONCAT(u.nombre, ' ', u.apellido) AS tutor, t.id_usuario AS id_usuario_tutor
             FROM grupos_tutoria g
             INNER JOIN materias m ON m.id_materia = g.id_materia
             INNER JOIN espacios_tutoria e ON e.id_espacio = g.id_espacio
             LEFT JOIN espacios_tutoria ep ON ep.id_espacio = g.id_espacio_propuesto
             INNER JOIN tutores t ON t.id_tutor = g.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE g.id_grupo = :id LIMIT 1"
        );
        $statement->execute(['id' => $grupoId]);
        $grupo = $statement->fetch();

        return $grupo ?: null;
    }

    /** Cambia el tutor del grupo (db/039). El enlace que habia propuesto el tutor anterior se descarta. */
    public function cambiarTutor(int $grupoId, int $tutorId): void
    {
        Database::connection()->prepare(
            'UPDATE grupos_tutoria SET id_tutor = :id_tutor, id_espacio_propuesto = NULL, enlace_propuesto = NULL,
                    fecha_propuesta = NULL, id_usuario_propuesta = NULL
             WHERE id_grupo = :id'
        )->execute(['id_tutor' => $tutorId, 'id' => $grupoId]);
    }

    /** Libera un cupo del grupo (retiro de un estudiante, db/039). */
    public function releaseEnrollment(int $grupoId): void
    {
        Database::connection()->prepare(
            'UPDATE grupos_tutoria SET cupo_ocupado = GREATEST(cupo_ocupado - 1, 0) WHERE id_grupo = :id'
        )->execute(['id' => $grupoId]);
    }

    /** Marca el grupo como cancelado y cancela sus sesiones programadas. */
    public function cancel(int $grupoId, string $motivo): void
    {
        Database::connection()->prepare(
            "UPDATE grupos_tutoria SET estado = 'cancelado', motivo_estado = :motivo, fecha_estado = NOW() WHERE id_grupo = :id"
        )->execute(['id' => $grupoId, 'motivo' => mb_substr($motivo, 0, 300)]);

        Database::connection()->prepare(
            "UPDATE sesiones_tutoria SET estado = 'cancelada' WHERE id_grupo = :id AND estado = 'programada'"
        )->execute(['id' => $grupoId]);
    }

    /** Bloquea la fila del grupo dentro de una transaccion para controlar el cupo. */
    public function lockForEnroll(int $grupoId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_grupo, id_periodo, cupo_max, cupo_ocupado, estado FROM grupos_tutoria WHERE id_grupo = :id FOR UPDATE'
        );
        $statement->execute(['id' => $grupoId]);
        $grupo = $statement->fetch();

        return $grupo ?: null;
    }

    /** Suma un inscrito y confirma el grupo si alcanza el cupo minimo. */
    public function registerEnrollment(int $grupoId, int $cupoMin): void
    {
        Database::connection()->prepare(
            'UPDATE grupos_tutoria SET cupo_ocupado = cupo_ocupado + 1 WHERE id_grupo = :id'
        )->execute(['id' => $grupoId]);

        Database::connection()->prepare(
            "UPDATE grupos_tutoria SET estado = 'confirmado', fecha_estado = NOW()
             WHERE id_grupo = :id AND estado = 'formacion' AND cupo_ocupado >= :cupo_min"
        )->execute(['id' => $grupoId, 'cupo_min' => $cupoMin]);
    }

    // ------------------------------------------------------------------
    // Visto bueno de la coordinacion (db/028)
    // ------------------------------------------------------------------

    /** Grupos del periodo activo que esperan aprobacion (contador del menu). */
    public function countPorAprobar(): int
    {
        return (int) Database::connection()->query(
            "SELECT COUNT(*) FROM grupos_tutoria g INNER JOIN periodos p ON p.id_periodo = g.id_periodo
             WHERE g.estado = 'por_aprobar' AND p.estado = 'activa'"
        )->fetchColumn();
    }

    /**
     * Aprueba un grupo por aprobar: pasa a confirmado si ya alcanzo el cupo minimo,
     * si no a formacion. Devuelve el estado nuevo, o null si el grupo ya no estaba
     * por aprobar (otro administrador lo reviso). Debe ejecutarse en transaccion.
     */
    public function approve(int $grupoId, int $cupoMin, int $adminId): ?string
    {
        $statement = Database::connection()->prepare(
            "UPDATE grupos_tutoria
             SET estado = IF(cupo_ocupado >= :cupo_min, 'confirmado', 'formacion'),
                 fecha_estado = NOW(), fecha_aprobacion = NOW(), id_aprobador = :id_aprobador
             WHERE id_grupo = :id AND estado = 'por_aprobar'"
        );
        $statement->execute(['cupo_min' => $cupoMin, 'id_aprobador' => $adminId, 'id' => $grupoId]);
        if ($statement->rowCount() === 0) {
            return null;
        }

        $estado = Database::connection()->prepare('SELECT estado FROM grupos_tutoria WHERE id_grupo = :id');
        $estado->execute(['id' => $grupoId]);

        return (string) $estado->fetchColumn();
    }

    /** Dias del patron semanal del grupo (grupo_dias). */
    public function dias(int $grupoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT dia_semana FROM grupo_dias WHERE id_grupo = :id
             ORDER BY FIELD(dia_semana, 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado')"
        );
        $statement->execute(['id' => $grupoId]);

        return array_column($statement->fetchAll(), 'dia_semana');
    }

    /**
     * Reemplaza el patron semanal de un grupo (grupo_dias + dia_semana de
     * retrocompatibilidad). No toca sesiones: solo se usa con grupos por aprobar,
     * que aun no tienen calendario. Debe ejecutarse en transaccion.
     */
    public function cambiarDias(int $grupoId, array $dias): void
    {
        $dias = array_values(array_unique($dias));
        $connection = Database::connection();
        $connection->prepare('DELETE FROM grupo_dias WHERE id_grupo = :id')->execute(['id' => $grupoId]);
        $insert = $connection->prepare('INSERT INTO grupo_dias (id_grupo, dia_semana) VALUES (:id_grupo, :dia_semana)');
        foreach ($dias as $dia) {
            $insert->execute(['id_grupo' => $grupoId, 'dia_semana' => $dia]);
        }
        $connection->prepare('UPDATE grupos_tutoria SET dia_semana = :dia WHERE id_grupo = :id')
            ->execute(['dia' => $dias[0], 'id' => $grupoId]);
    }

    /** Registra la combinacion (tutor, materia, horario) rechazada para que el motor no la repita. */
    public function recordRejection(array $grupo, string $motivo, int $adminId): void
    {
        Database::connection()->prepare(
            'INSERT INTO grupo_rechazos (id_periodo, id_materia, id_tutor, hora_inicio, hora_fin, id_grupo, motivo, id_usuario_accion)
             VALUES (:id_periodo, :id_materia, :id_tutor, :hora_inicio, :hora_fin, :id_grupo, :motivo, :id_usuario)'
        )->execute([
            'id_periodo' => $grupo['id_periodo'],
            'id_materia' => $grupo['id_materia'],
            'id_tutor' => $grupo['id_tutor'],
            'hora_inicio' => $grupo['hora_inicio'],
            'hora_fin' => $grupo['hora_fin'],
            'id_grupo' => $grupo['id_grupo'],
            'motivo' => mb_substr($motivo, 0, 300),
            'id_usuario' => $adminId,
        ]);
    }

    /** Claves "id_tutor|hora_inicio|hora_fin" rechazadas para la materia en el periodo. */
    public function rejectedSlotKeys(int $periodoId, int $matterId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT DISTINCT id_tutor, hora_inicio, hora_fin FROM grupo_rechazos
             WHERE id_periodo = :id_periodo AND id_materia = :id_materia'
        );
        $statement->execute(['id_periodo' => $periodoId, 'id_materia' => $matterId]);
        $keys = [];
        foreach ($statement->fetchAll() as $row) {
            $keys[self::slotKey((int) $row['id_tutor'], (string) $row['hora_inicio'], (string) $row['hora_fin'])] = true;
        }

        return $keys;
    }

    public static function slotKey(int $tutorId, string $horaInicio, string $horaFin): string
    {
        return $tutorId . '|' . substr($horaInicio, 0, 5) . '|' . substr($horaFin, 0, 5);
    }

    // ------------------------------------------------------------------
    // Ubicacion del grupo (db/029): la registra la coordinacion; el tutor
    // puede proponer un enlace virtual.
    // ------------------------------------------------------------------

    /** Bloquea el grupo y devuelve su modalidad, espacio, ubicacion y propuesta vigentes. */
    public function lockUbicacion(int $grupoId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_grupo, estado, modalidad, id_espacio, ubicacion, enlace, id_espacio_propuesto, enlace_propuesto
             FROM grupos_tutoria WHERE id_grupo = :id FOR UPDATE'
        );
        $statement->execute(['id' => $grupoId]);
        $grupo = $statement->fetch();

        return $grupo ?: null;
    }

    /** Guarda la ubicacion oficial. $limpiarPropuesta: la propuesta del tutor quedo resuelta. */
    public function updateUbicacion(int $grupoId, string $modalidad, int $espacioId, ?string $ubicacion, ?string $enlace, int $userId, bool $limpiarPropuesta): void
    {
        $sql = 'UPDATE grupos_tutoria
                SET modalidad = :modalidad, id_espacio = :id_espacio, ubicacion = :ubicacion, enlace = :enlace,
                    fecha_ubicacion = NOW(), id_usuario_ubicacion = :id_usuario';
        if ($limpiarPropuesta) {
            $sql .= ', id_espacio_propuesto = NULL, enlace_propuesto = NULL, fecha_propuesta = NULL, id_usuario_propuesta = NULL';
        }
        Database::connection()->prepare($sql . ' WHERE id_grupo = :id')->execute([
            'modalidad' => $modalidad,
            'id_espacio' => $espacioId,
            'ubicacion' => $ubicacion,
            'enlace' => $enlace,
            'id_usuario' => $userId,
            'id' => $grupoId,
        ]);
    }

    public function setPropuesta(int $grupoId, int $espacioId, string $enlace, int $userId): void
    {
        Database::connection()->prepare(
            'UPDATE grupos_tutoria SET id_espacio_propuesto = :id_espacio, enlace_propuesto = :enlace,
                    fecha_propuesta = NOW(), id_usuario_propuesta = :id_usuario
             WHERE id_grupo = :id'
        )->execute(['id_espacio' => $espacioId, 'enlace' => $enlace, 'id_usuario' => $userId, 'id' => $grupoId]);
    }

    public function clearPropuesta(int $grupoId): void
    {
        Database::connection()->prepare(
            'UPDATE grupos_tutoria SET id_espacio_propuesto = NULL, enlace_propuesto = NULL,
                    fecha_propuesta = NULL, id_usuario_propuesta = NULL
             WHERE id_grupo = :id'
        )->execute(['id' => $grupoId]);
    }

    /** Registra un cambio de ubicacion o una propuesta y devuelve el id del registro. */
    public function logUbicacion(PDO $pdo, int $grupoId, string $accion, array $antes, array $despues, ?string $motivo, ?int $userId): int
    {
        $pdo->prepare(
            'INSERT INTO grupo_ubicacion_historial
               (id_grupo, accion, modalidad_anterior, modalidad_nueva, id_espacio_anterior, id_espacio_nuevo,
                ubicacion_anterior, ubicacion_nueva, enlace_anterior, enlace_nuevo, motivo, id_usuario)
             VALUES (:id_grupo, :accion, :modalidad_anterior, :modalidad_nueva, :id_espacio_anterior, :id_espacio_nuevo,
                     :ubicacion_anterior, :ubicacion_nueva, :enlace_anterior, :enlace_nuevo, :motivo, :id_usuario)'
        )->execute([
            'id_grupo' => $grupoId,
            'accion' => $accion,
            'modalidad_anterior' => $antes['modalidad'] ?? null,
            'modalidad_nueva' => $despues['modalidad'] ?? null,
            'id_espacio_anterior' => $antes['id_espacio'] ?? null,
            'id_espacio_nuevo' => $despues['id_espacio'] ?? null,
            'ubicacion_anterior' => $antes['ubicacion'] ?? null,
            'ubicacion_nueva' => $despues['ubicacion'] ?? null,
            'enlace_anterior' => $antes['enlace'] ?? null,
            'enlace_nuevo' => $despues['enlace'] ?? null,
            'motivo' => $motivo !== null ? mb_substr($motivo, 0, 300) : null,
            'id_usuario' => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Historial de ubicacion de un grupo, del mas reciente al mas antiguo. */
    public function ubicacionHistorial(int $grupoId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT h.accion, h.modalidad_anterior, h.modalidad_nueva, ea.nombre AS espacio_anterior, en.nombre AS espacio_nuevo,
                    h.ubicacion_anterior, h.ubicacion_nueva, h.enlace_anterior, h.enlace_nuevo, h.motivo, h.fecha,
                    CASE WHEN u.id_usuario IS NULL THEN 'Sistema' ELSE CONCAT(u.nombre, ' ', u.apellido) END AS responsable
             FROM grupo_ubicacion_historial h
             LEFT JOIN espacios_tutoria ea ON ea.id_espacio = h.id_espacio_anterior
             LEFT JOIN espacios_tutoria en ON en.id_espacio = h.id_espacio_nuevo
             LEFT JOIN usuarios u ON u.id_usuario = h.id_usuario
             WHERE h.id_grupo = :id
             ORDER BY h.fecha DESC, h.id_historial DESC"
        );
        $statement->execute(['id' => $grupoId]);

        return $statement->fetchAll();
    }
}
