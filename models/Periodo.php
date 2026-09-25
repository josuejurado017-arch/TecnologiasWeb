<?php

declare(strict_types=1);

/**
 * Periodos de tutoria (db/031). Ciclo de vida BORRADOR -> ACTIVA -> CERRADA, sin
 * vuelta atras: un periodo cerrado es historial (solo lectura y observaciones).
 * Las transiciones las orquesta PeriodosController; aqui solo hay SQL.
 */
final class Periodo
{
    /** Dias que el estudiante conserva para evaluar despues del cierre. */
    public const DIAS_GRACIA_EVALUACION = 7;

    private const COLUMNAS = 'p.id_periodo, p.nombre, p.fecha_inicio, p.fecha_fin, p.cupo_min_grupo, p.cupo_max_default,
        p.max_grupos_tutor, p.modalidad_ambas, p.estado, p.fecha_activacion, p.fecha_cierre, p.evaluaciones_hasta, p.resumen_cierre, p.fecha_registro';

    public function all(): array
    {
        return Database::connection()->query(
            'SELECT ' . self::COLUMNAS . ",
                    CONCAT(uc.nombre, ' ', uc.apellido) AS cerrado_por
             FROM periodos p
             LEFT JOIN usuarios uc ON uc.id_usuario = p.id_usuario_cierre
             ORDER BY FIELD(p.estado, 'activa', 'borrador', 'cerrada'), p.fecha_inicio DESC, p.nombre"
        )->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT ' . self::COLUMNAS . ",
                    CONCAT(ua.nombre, ' ', ua.apellido) AS activado_por,
                    CONCAT(uc.nombre, ' ', uc.apellido) AS cerrado_por
             FROM periodos p
             LEFT JOIN usuarios ua ON ua.id_usuario = p.id_usuario_activacion
             LEFT JOIN usuarios uc ON uc.id_usuario = p.id_usuario_cierre
             WHERE p.id_periodo = :id_periodo LIMIT 1"
        );
        $statement->execute(['id_periodo' => $id]);
        $periodo = $statement->fetch();

        return $periodo ?: null;
    }

    public function activa(): ?array
    {
        $periodo = Database::connection()
            ->query('SELECT ' . self::COLUMNAS . " FROM periodos p WHERE p.estado = 'activa' ORDER BY p.fecha_inicio DESC LIMIT 1")
            ->fetch();

        return $periodo ?: null;
    }

    /**
     * Periodos donde el estudiante aun puede evaluar: el activo y los cerrados dentro
     * del plazo de gracia (evaluaciones_hasta).
     */
    public function evaluables(): array
    {
        return Database::connection()->query(
            'SELECT ' . self::COLUMNAS . " FROM periodos p
             WHERE p.estado = 'activa' OR (p.estado = 'cerrada' AND p.evaluaciones_hasta >= CURRENT_DATE)
             ORDER BY FIELD(p.estado, 'activa', 'cerrada'), p.fecha_inicio DESC"
        )->fetchAll();
    }

    /** Verifica si el nombre ya existe (ignora el propio registro al editar). */
    public function nameExists(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM periodos WHERE nombre = :nombre';
        $params = ['nombre' => $name];
        if ($ignoreId !== null) {
            $sql .= ' AND id_periodo <> :id';
            $params['id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return (bool) $statement->fetchColumn();
    }

    /** Todo periodo nace en borrador: el estado solo cambia con activar() o cerrar. */
    public function create(array $data): void
    {
        Database::connection()->prepare(
            "INSERT INTO periodos (nombre, fecha_inicio, fecha_fin, cupo_min_grupo, cupo_max_default, max_grupos_tutor, modalidad_ambas, estado)
             VALUES (:nombre, :fecha_inicio, :fecha_fin, :cupo_min_grupo, :cupo_max_default, :max_grupos_tutor, :modalidad_ambas, 'borrador')"
        )->execute([
            'max_grupos_tutor' => $data['max_grupos_tutor'],
            'nombre' => $data['nombre'],
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $data['fecha_fin'],
            'cupo_min_grupo' => $data['cupo_min_grupo'],
            'cupo_max_default' => $data['cupo_max_default'],
            'modalidad_ambas' => $data['modalidad_ambas'],
        ]);
    }

    /** Actualiza la configuracion. Nunca toca el estado; no aplica a periodos cerrados. */
    public function update(int $id, array $data): void
    {
        Database::connection()->prepare(
            "UPDATE periodos SET nombre = :nombre, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin,
                cupo_min_grupo = :cupo_min_grupo, cupo_max_default = :cupo_max_default,
                max_grupos_tutor = :max_grupos_tutor, modalidad_ambas = :modalidad_ambas
             WHERE id_periodo = :id_periodo AND estado <> 'cerrada'"
        )->execute([
            'max_grupos_tutor' => $data['max_grupos_tutor'],
            'nombre' => $data['nombre'],
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $data['fecha_fin'],
            'cupo_min_grupo' => $data['cupo_min_grupo'],
            'cupo_max_default' => $data['cupo_max_default'],
            'modalidad_ambas' => $data['modalidad_ambas'],
            'id_periodo' => $id,
        ]);
    }

    /** Bloquea la fila del periodo (dentro de una transaccion) y devuelve su estado. */
    public function lockEstado(int $id): ?string
    {
        $statement = Database::connection()->prepare('SELECT estado FROM periodos WHERE id_periodo = :id FOR UPDATE');
        $statement->execute(['id' => $id]);
        $estado = $statement->fetchColumn();

        return $estado === false ? null : (string) $estado;
    }

    /** Otro periodo activo (bloqueado en la transaccion en curso), o null. */
    public function lockOtroActivo(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT id_periodo, nombre FROM periodos WHERE estado = 'activa' AND id_periodo <> :id FOR UPDATE"
        );
        $statement->execute(['id' => $id]);
        $otro = $statement->fetch();

        return $otro ?: null;
    }

    /** Borrador -> activa. Debe ejecutarse en transaccion tras las validaciones. */
    public function marcarActiva(int $id, int $userId): void
    {
        Database::connection()->prepare(
            "UPDATE periodos SET estado = 'activa', fecha_activacion = NOW(), id_usuario_activacion = :u
             WHERE id_periodo = :id AND estado = 'borrador'"
        )->execute(['u' => $userId, 'id' => $id]);
    }

    /** Registros que dependen del periodo: si hay alguno, no se puede eliminar. */
    public function countDependencias(int $id): int
    {
        $statement = Database::connection()->prepare(
            'SELECT (SELECT COUNT(*) FROM grupos_tutoria WHERE id_periodo = :a)
                  + (SELECT COUNT(*) FROM demanda_tutoria WHERE id_periodo = :b)
                  + (SELECT COUNT(*) FROM grupo_rechazos WHERE id_periodo = :c)
                  + (SELECT COUNT(*) FROM periodo_observaciones WHERE id_periodo = :d)'
        );
        $statement->execute(['a' => $id, 'b' => $id, 'c' => $id, 'd' => $id]);

        return (int) $statement->fetchColumn();
    }

    public function delete(int $id): void
    {
        Database::connection()->prepare("DELETE FROM periodos WHERE id_periodo = :id_periodo AND estado = 'borrador'")
            ->execute(['id_periodo' => $id]);
    }

    /**
     * Lo que el cierre va a cambiar (vista previa) y queda congelado en resumen_cierre.
     * "Hoy" separa sesiones pasadas sin asistencia de sesiones futuras.
     */
    public function impactoCierre(int $id): array
    {
        $pdo = Database::connection();
        $grupos = $pdo->prepare(
            "SELECT
                SUM(g.estado IN ('confirmado','en_curso')) AS grupos_a_finalizar,
                SUM(g.estado IN ('formacion','por_aprobar') AND EXISTS (SELECT 1 FROM sesiones_tutoria s WHERE s.id_grupo = g.id_grupo AND s.estado = 'realizada')) AS grupos_formacion_con_sesiones,
                SUM(g.estado IN ('formacion','por_aprobar') AND NOT EXISTS (SELECT 1 FROM sesiones_tutoria s WHERE s.id_grupo = g.id_grupo AND s.estado = 'realizada')) AS grupos_a_cancelar,
                SUM(g.estado = 'finalizado') AS grupos_ya_finalizados,
                SUM(g.estado = 'cancelado') AS grupos_ya_cancelados
             FROM grupos_tutoria g WHERE g.id_periodo = :id"
        );
        $grupos->execute(['id' => $id]);

        $sesiones = $pdo->prepare(
            "SELECT
                SUM(s.estado = 'programada' AND s.fecha < CURRENT_DATE) AS sesiones_sin_registro,
                SUM(s.estado = 'programada' AND s.fecha >= CURRENT_DATE) AS sesiones_a_cancelar,
                SUM(s.estado = 'realizada') AS sesiones_realizadas
             FROM sesiones_tutoria s INNER JOIN grupos_tutoria g ON g.id_grupo = s.id_grupo
             WHERE g.id_periodo = :id"
        );
        $sesiones->execute(['id' => $id]);

        $otros = $pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM demanda_tutoria WHERE id_periodo = :a AND estado = 'pendiente') AS demanda_a_vencer,
                (SELECT COUNT(*) FROM demanda_tutoria WHERE id_periodo = :b AND estado = 'atendida') AS demanda_atendida,
                (SELECT COUNT(*) FROM inscripciones i INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
                  WHERE g.id_periodo = :c AND i.estado = 'lista_espera') AS lista_espera_a_cancelar,
                (SELECT COUNT(DISTINCT i.id_estudiante) FROM inscripciones i INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
                  WHERE g.id_periodo = :d AND i.estado = 'inscrito') AS estudiantes_atendidos,
                (SELECT COUNT(*) FROM inscripciones i INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
                  WHERE g.id_periodo = :e AND i.estado = 'inscrito' AND g.estado IN ('confirmado','en_curso','finalizado')
                    AND EXISTS (SELECT 1 FROM sesiones_tutoria s WHERE s.id_grupo = g.id_grupo AND s.estado = 'realizada')
                    AND NOT EXISTS (SELECT 1 FROM evaluaciones_grupo e WHERE e.id_inscripcion = i.id_inscripcion)) AS evaluaciones_pendientes"
        );
        $otros->execute(['a' => $id, 'b' => $id, 'c' => $id, 'd' => $id, 'e' => $id]);

        $row = array_merge($grupos->fetch() ?: [], $sesiones->fetch() ?: [], $otros->fetch() ?: []);

        return array_map(static fn ($v): int => (int) $v, $row);
    }

    /**
     * Efectos del cierre (activa -> cerrada). Debe ejecutarse en transaccion, con el
     * periodo bloqueado y ya validado:
     *   grupos confirmados/en curso -> finalizado; en formacion/por aprobar -> finalizado
     *     si alguna sesion se dicto, si no cancelado (y sus inscripciones canceladas);
     *   sesiones programadas pasadas -> sin_registro, futuras -> cancelada;
     *   lista de espera -> cancelada; demanda pendiente -> vencida.
     */
    public function aplicarCierre(int $id, int $userId, array $resumen): void
    {
        $pdo = Database::connection();
        $conSesion = "EXISTS (SELECT 1 FROM sesiones_tutoria s WHERE s.id_grupo = g.id_grupo AND s.estado = 'realizada')";
        $estadoFinal = "IF(g.estado IN ('confirmado','en_curso') OR {$conSesion}, 'finalizado', 'cancelado')";

        // El historial se escribe antes del cambio para conservar el estado anterior.
        $pdo->prepare(
            "INSERT INTO historial_grupo (id_grupo, tipo_evento, estado_anterior, estado_nuevo, id_usuario, motivo)
             SELECT g.id_grupo, 'cierre_periodo', g.estado, {$estadoFinal}, :u, 'Cierre del período'
             FROM grupos_tutoria g
             WHERE g.id_periodo = :id AND g.estado IN ('por_aprobar','formacion','confirmado','en_curso')"
        )->execute(['u' => $userId, 'id' => $id]);

        $pdo->prepare(
            "UPDATE grupos_tutoria g
             SET g.estado = {$estadoFinal}, g.motivo_estado = 'Cierre del período', g.fecha_estado = NOW()
             WHERE g.id_periodo = :id AND g.estado IN ('por_aprobar','formacion','confirmado','en_curso')"
        )->execute(['id' => $id]);

        $pdo->prepare(
            "UPDATE sesiones_tutoria s INNER JOIN grupos_tutoria g ON g.id_grupo = s.id_grupo
             SET s.estado = IF(s.fecha < CURRENT_DATE, 'sin_registro', 'cancelada')
             WHERE g.id_periodo = :id AND s.estado = 'programada'"
        )->execute(['id' => $id]);

        $pdo->prepare(
            "UPDATE inscripciones i INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
             SET i.estado = 'cancelada'
             WHERE g.id_periodo = :id
               AND (i.estado = 'lista_espera' OR (g.estado = 'cancelado' AND g.motivo_estado = 'Cierre del período' AND i.estado = 'inscrito'))"
        )->execute(['id' => $id]);

        $pdo->prepare(
            "UPDATE demanda_tutoria SET estado = 'vencida' WHERE id_periodo = :id AND estado = 'pendiente'"
        )->execute(['id' => $id]);

        $pdo->prepare(
            "UPDATE periodos
             SET estado = 'cerrada', fecha_cierre = NOW(), id_usuario_cierre = :u,
                 evaluaciones_hasta = DATE_ADD(CURRENT_DATE, INTERVAL " . self::DIAS_GRACIA_EVALUACION . " DAY),
                 resumen_cierre = :resumen
             WHERE id_periodo = :id AND estado = 'activa'"
        )->execute(['u' => $userId, 'resumen' => json_encode($resumen, JSON_UNESCAPED_UNICODE), 'id' => $id]);
    }

    /** Grupos aprobados y vigentes del periodo (para extender su calendario). */
    public function gruposConCalendario(int $id): array
    {
        $statement = Database::connection()->prepare(
            "SELECT id_grupo FROM grupos_tutoria WHERE id_periodo = :id AND estado IN ('formacion','confirmado','en_curso')"
        );
        $statement->execute(['id' => $id]);

        return array_map('intval', array_column($statement->fetchAll(), 'id_grupo'));
    }

    public function countGrupos(int $id): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM grupos_tutoria WHERE id_periodo = :id');
        $statement->execute(['id' => $id]);

        return (int) $statement->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Observaciones administrativas (solo se agregan)
    // ------------------------------------------------------------------

    public function observaciones(int $id): array
    {
        $statement = Database::connection()->prepare(
            "SELECT o.texto, o.fecha,
                    CASE WHEN u.id_usuario IS NULL THEN 'Usuario eliminado' ELSE CONCAT(u.nombre, ' ', u.apellido) END AS autor
             FROM periodo_observaciones o LEFT JOIN usuarios u ON u.id_usuario = o.id_usuario
             WHERE o.id_periodo = :id ORDER BY o.fecha DESC, o.id_observacion DESC"
        );
        $statement->execute(['id' => $id]);

        return $statement->fetchAll();
    }

    public function agregarObservacion(int $id, string $texto, int $userId): void
    {
        Database::connection()->prepare(
            'INSERT INTO periodo_observaciones (id_periodo, texto, id_usuario) VALUES (:id, :texto, :u)'
        )->execute(['id' => $id, 'texto' => $texto, 'u' => $userId]);
    }
}
