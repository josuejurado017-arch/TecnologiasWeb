<?php

declare(strict_types=1);

/**
 * Expedientes de Modalidades de Grado (db/045, HU-024): un estudiante en un proceso
 * de grado. La etapa actual y el historial de etapas se cambian siempre juntos,
 * en la transaccion que abre el controlador.
 */
final class MgExpediente
{
    public const ETAPAS = ['previa' => 'Etapa previa', 'mg1' => 'MG1 · Perfil', 'mg2' => 'MG2 · Ejecución', 'finalizado' => 'Finalizado'];

    /** [PENDIENTE] estados institucionales oficiales (pregunta 2 al Coordinador). */
    public const ESTADOS = ['activo' => 'Activo', 'aprobado' => 'Aprobado', 'reprobado' => 'Reprobado', 'abandono' => 'Abandono', 'retirado' => 'Retirado'];

    /** Estados que cierran el expediente y exigen motivo (RN-MG-22: nunca automaticos). */
    public const ESTADOS_CON_MOTIVO = ['reprobado', 'abandono', 'retirado'];

    private const SELECT = "SELECT e.id_expediente, e.id_estudiante, e.id_modalidad, e.id_cohorte, e.etapa_actual, e.estado,
                e.titulo_trabajo, e.fecha_inicio, e.fecha_cierre, e.observaciones, e.origen, e.fecha_registro,
                es.registro_universitario, es.semestre, u.id_usuario AS id_usuario_estudiante,
                CONCAT(u.nombre, ' ', u.apellido) AS estudiante, u.correo AS correo_estudiante,
                c.nombre_carrera AS carrera, m.nombre AS modalidad, m.codigo AS modalidad_codigo, m.requiere_tutor,
                m.regla_por_validar, co.nombre AS cohorte, co.codigo AS cohorte_codigo,
                a.id_asignacion, a.id_tutor, CONCAT(ut.nombre, ' ', ut.apellido) AS tutor, ut.id_usuario AS id_usuario_tutor
             FROM expedientes_mg e
             INNER JOIN estudiantes es ON es.id_estudiante = e.id_estudiante
             INNER JOIN usuarios u ON u.id_usuario = es.id_usuario
             INNER JOIN carreras c ON c.id_carrera = es.id_carrera
             INNER JOIN modalidades_grado m ON m.id_modalidad = e.id_modalidad
             INNER JOIN cohortes_mg co ON co.id_cohorte = e.id_cohorte
             LEFT JOIN asignaciones_tutor_mg a ON a.id_expediente = e.id_expediente AND a.estado = 'vigente'
             LEFT JOIN tutores t ON t.id_tutor = a.id_tutor
             LEFT JOIN usuarios ut ON ut.id_usuario = t.id_usuario";

    /**
     * Listado con filtros: q (nombre o R.U.), id_cohorte, id_modalidad, etapa, estado,
     * id_tutor (tesistas vigentes de un tutor) e id_estudiante.
     */
    public function listar(array $filtros = []): array
    {
        [$where, $params] = $this->filtros($filtros);
        $statement = Database::connection()->prepare(
            self::SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY co.fecha_inicio DESC, u.apellido, u.nombre'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function filtros(array $filtros): array
    {
        $where = [];
        $params = [];
        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE :q1 OR es.registro_universitario LIKE :q2 OR e.titulo_trabajo LIKE :q3)";
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        foreach (['id_cohorte' => 'e.id_cohorte', 'id_modalidad' => 'e.id_modalidad', 'id_estudiante' => 'e.id_estudiante', 'id_tutor' => 'a.id_tutor'] as $campo => $columna) {
            if (!empty($filtros[$campo])) {
                $where[] = "{$columna} = :{$campo}";
                $params[$campo] = (int) $filtros[$campo];
            }
        }
        if (!empty($filtros['etapa']) && isset(self::ETAPAS[$filtros['etapa']])) {
            $where[] = 'e.etapa_actual = :etapa';
            $params['etapa'] = $filtros['etapa'];
        }
        if (!empty($filtros['estado']) && isset(self::ESTADOS[$filtros['estado']])) {
            $where[] = 'e.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }

        return [$where, $params];
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(self::SELECT . ' WHERE e.id_expediente = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /** Bloquea el expediente dentro de una transaccion y devuelve etapa y estado. */
    public function lock(PDO $pdo, int $id): ?array
    {
        $statement = $pdo->prepare('SELECT * FROM expedientes_mg WHERE id_expediente = :id FOR UPDATE');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function existe(int $estudianteId, int $modalidadId, int $cohorteId): ?int
    {
        $statement = Database::connection()->prepare(
            'SELECT id_expediente FROM expedientes_mg WHERE id_estudiante = :e AND id_modalidad = :m AND id_cohorte = :c'
        );
        $statement->execute(['e' => $estudianteId, 'm' => $modalidadId, 'c' => $cohorteId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** Crea el expediente y abre su primera etapa. Debe correr en transaccion. */
    public function crear(PDO $pdo, array $data, int $userId): int
    {
        $pdo->prepare(
            'INSERT INTO expedientes_mg (id_estudiante, id_modalidad, id_cohorte, etapa_actual, titulo_trabajo, fecha_inicio, observaciones, origen, registrado_por)
             VALUES (:est, :mod, :coh, :etapa, :titulo, :inicio, :obs, :origen, :u)'
        )->execute([
            'est' => $data['id_estudiante'], 'mod' => $data['id_modalidad'], 'coh' => $data['id_cohorte'],
            'etapa' => $data['etapa_actual'], 'titulo' => $data['titulo_trabajo'], 'inicio' => $data['fecha_inicio'],
            'obs' => $data['observaciones'] ?? null, 'origen' => $data['origen'] ?? 'manual', 'u' => $userId,
        ]);
        $id = (int) $pdo->lastInsertId();
        $this->abrirEtapa($pdo, $id, $data['etapa_actual'], $data['fecha_inicio'], $userId);

        return $id;
    }

    public function actualizarDatos(PDO $pdo, int $id, ?string $titulo, ?string $observaciones): void
    {
        $pdo->prepare('UPDATE expedientes_mg SET titulo_trabajo = :t, observaciones = :o WHERE id_expediente = :id')
            ->execute(['t' => $titulo, 'o' => $observaciones, 'id' => $id]);
    }

    public function etapas(int $id): array
    {
        $statement = Database::connection()->prepare(
            "SELECT h.*, CONCAT(u.nombre, ' ', u.apellido) AS registrado
             FROM expediente_etapas_mg h LEFT JOIN usuarios u ON u.id_usuario = h.registrado_por
             WHERE h.id_expediente = :id ORDER BY h.fecha_inicio, h.id_etapa"
        );
        $statement->execute(['id' => $id]);

        return $statement->fetchAll();
    }

    private function abrirEtapa(PDO $pdo, int $id, string $etapa, string $fecha, int $userId): void
    {
        $pdo->prepare(
            'INSERT INTO expediente_etapas_mg (id_expediente, etapa, fecha_inicio, registrado_por) VALUES (:id, :etapa, :fecha, :u)'
        )->execute(['id' => $id, 'etapa' => $etapa, 'fecha' => $fecha, 'u' => $userId]);
    }

    private function cerrarEtapaAbierta(PDO $pdo, int $id, string $fecha, ?string $resultado): void
    {
        $pdo->prepare(
            'UPDATE expediente_etapas_mg SET fecha_fin = :fecha, resultado = :r WHERE id_expediente = :id AND fecha_fin IS NULL'
        )->execute(['fecha' => $fecha, 'r' => $resultado, 'id' => $id]);
    }

    /** Cierra la etapa abierta y abre la siguiente. Debe correr en transaccion, con el expediente bloqueado. */
    public function cambiarEtapa(PDO $pdo, int $id, string $nueva, string $fecha, ?string $resultado, int $userId): void
    {
        $this->cerrarEtapaAbierta($pdo, $id, $fecha, $resultado);
        $pdo->prepare('UPDATE expedientes_mg SET etapa_actual = :e WHERE id_expediente = :id')->execute(['e' => $nueva, 'id' => $id]);
        $this->abrirEtapa($pdo, $id, $nueva, $fecha, $userId);
    }

    /** Cambia el estado. Si cierra el expediente, cierra tambien la etapa abierta. */
    public function cambiarEstado(PDO $pdo, int $id, string $estado, string $fecha, ?string $motivo): void
    {
        $cierra = $estado !== 'activo';
        $pdo->prepare(
            'UPDATE expedientes_mg SET estado = :estado, fecha_cierre = :cierre,
                observaciones = CASE WHEN :motivo IS NULL THEN observaciones ELSE CONCAT_WS(:sep, observaciones, :motivo2) END
             WHERE id_expediente = :id'
        )->execute([
            'estado' => $estado, 'cierre' => $cierra ? $fecha : null, 'motivo' => $motivo, 'motivo2' => $motivo,
            'sep' => "\n", 'id' => $id,
        ]);
        if ($cierra) {
            $this->cerrarEtapaAbierta($pdo, $id, $fecha, self::ESTADOS[$estado] . ($motivo ? ': ' . $motivo : ''));
        }
    }

    // ------------------------------------------------------------------
    // Estudiantes (para crear expedientes e importar)
    // ------------------------------------------------------------------

    public function estudiantePorRegistro(string $registro): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT es.id_estudiante, es.registro_universitario, CONCAT(u.nombre, ' ', u.apellido) AS nombre, u.estado
             FROM estudiantes es INNER JOIN usuarios u ON u.id_usuario = es.id_usuario
             WHERE es.registro_universitario = :r LIMIT 1"
        );
        $statement->execute(['r' => $registro]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function estudiantesActivos(): array
    {
        return Database::connection()->query(
            "SELECT es.id_estudiante, es.registro_universitario, CONCAT(u.apellido, ', ', u.nombre) AS nombre, c.nombre_carrera AS carrera
             FROM estudiantes es INNER JOIN usuarios u ON u.id_usuario = es.id_usuario
             INNER JOIN carreras c ON c.id_carrera = es.id_carrera
             WHERE u.estado = 'activo' ORDER BY u.apellido, u.nombre"
        )->fetchAll();
    }

    public function estudianteIdPorUsuario(int $userId): ?int
    {
        $statement = Database::connection()->prepare('SELECT id_estudiante FROM estudiantes WHERE id_usuario = :u');
        $statement->execute(['u' => $userId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    // ------------------------------------------------------------------
    // Importaciones (HU-023)
    // ------------------------------------------------------------------

    public function registrarImportacion(PDO $pdo, string $archivo, int $userId, array $filas): int
    {
        $conteo = ['creado' => 0, 'omitido' => 0, 'pendiente_cuenta' => 0, 'error' => 0];
        foreach ($filas as $fila) {
            $conteo[$fila['resultado']]++;
        }
        $pdo->prepare(
            'INSERT INTO importaciones_mg (archivo, id_usuario, total_filas, creados, omitidos, errores) VALUES (:a, :u, :t, :c, :o, :e)'
        )->execute([
            'a' => mb_substr($archivo, 0, 255), 'u' => $userId, 't' => count($filas), 'c' => $conteo['creado'],
            'o' => $conteo['omitido'] + $conteo['pendiente_cuenta'], 'e' => $conteo['error'],
        ]);
        $importacionId = (int) $pdo->lastInsertId();
        $detalle = $pdo->prepare(
            'INSERT INTO importaciones_mg_detalle (id_importacion, fila, registro_universitario, resultado, mensaje, id_expediente)
             VALUES (:i, :f, :r, :res, :m, :e)'
        );
        foreach ($filas as $fila) {
            $detalle->execute([
                'i' => $importacionId, 'f' => $fila['fila'], 'r' => mb_substr((string) $fila['registro_universitario'], 0, 30) ?: null,
                'res' => $fila['resultado'], 'm' => mb_substr($fila['mensaje'], 0, 255), 'e' => $fila['id_expediente'] ?? null,
            ]);
        }

        return $importacionId;
    }

    public function importaciones(int $limite = 20): array
    {
        return Database::connection()->query(
            "SELECT i.*, CONCAT(u.nombre, ' ', u.apellido) AS usuario FROM importaciones_mg i
             LEFT JOIN usuarios u ON u.id_usuario = i.id_usuario ORDER BY i.fecha DESC LIMIT " . max(1, min($limite, 100))
        )->fetchAll();
    }

    public function detalleImportacion(int $id): array
    {
        $statement = Database::connection()->prepare('SELECT * FROM importaciones_mg_detalle WHERE id_importacion = :id ORDER BY fila');
        $statement->execute(['id' => $id]);

        return $statement->fetchAll();
    }

    // ------------------------------------------------------------------
    // Panel del Coordinador (HU-039; ver tambien cargaPorTutor y MgAlerta)
    // ------------------------------------------------------------------

    public function panel(int $diasDefensas, int $cargaRecomendada, int $diasTribunal): array
    {
        $pdo = Database::connection();
        $porEtapa = array_column($pdo->query(
            "SELECT etapa_actual, COUNT(*) AS total FROM expedientes_mg WHERE estado = 'activo' GROUP BY etapa_actual"
        )->fetchAll(), 'total', 'etapa_actual');

        $sinTutor = $pdo->query(
            "SELECT e.id_expediente, CONCAT(u.nombre, ' ', u.apellido) AS estudiante, m.nombre AS modalidad, co.nombre AS cohorte, e.etapa_actual
             FROM expedientes_mg e INNER JOIN estudiantes es ON es.id_estudiante = e.id_estudiante
             INNER JOIN usuarios u ON u.id_usuario = es.id_usuario
             INNER JOIN modalidades_grado m ON m.id_modalidad = e.id_modalidad AND m.requiere_tutor = 1
             INNER JOIN cohortes_mg co ON co.id_cohorte = e.id_cohorte
             WHERE e.estado = 'activo' AND e.etapa_actual IN ('previa','mg1','mg2')
               AND NOT EXISTS (SELECT 1 FROM asignaciones_tutor_mg a WHERE a.id_expediente = e.id_expediente AND a.estado = 'vigente')
             ORDER BY FIELD(e.etapa_actual, 'mg2', 'mg1', 'previa'), u.apellido LIMIT 50"
        )->fetchAll();

        $hoy = date('Y-m-d');
        $hasta = date('Y-m-d', strtotime('+' . $diasDefensas . ' days'));
        $proximas = (new MgDefensa())->agenda($hoy, $hasta, 'programada');
        foreach ($proximas as &$defensa) {
            $requeridos = (int) MgParametro::entero('tribunales_por_defensa_' . $defensa['etapa'], 2);
            $defensa['tribunales'] = count((new MgTribunal())->vigentes((int) $defensa['id_expediente'], (string) $defensa['etapa']));
            $defensa['faltan_tribunales'] = $defensa['tribunales'] < $requeridos;
            $defensa['tribunal_urgente'] = $defensa['faltan_tribunales'] && (strtotime((string) $defensa['fecha']) - strtotime($hoy)) / 86400 < $diasTribunal;
        }
        unset($defensa);

        $sobrecarga = $pdo->prepare(
            "SELECT t.id_tutor, CONCAT(u.nombre, ' ', u.apellido) AS docente, COUNT(*) AS carga
             FROM asignaciones_tutor_mg a INNER JOIN expedientes_mg e ON e.id_expediente = a.id_expediente AND e.estado = 'activo'
             INNER JOIN tutores t ON t.id_tutor = a.id_tutor INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE a.estado = 'vigente' GROUP BY t.id_tutor, u.nombre, u.apellido HAVING COUNT(*) > :c ORDER BY carga DESC"
        );
        $sobrecarga->execute(['c' => $cargaRecomendada]);

        $cartasMes = (int) $pdo->query(
            "SELECT COUNT(*) FROM documentos_generados_mg WHERE fecha_generacion >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')"
        )->fetchColumn();

        return [
            'por_etapa' => $porEtapa,
            'activos' => array_sum(array_map('intval', $porEtapa)),
            'sin_tutor' => $sinTutor,
            'proximas' => $proximas,
            'sobrecarga' => $sobrecarga->fetchAll(),
            'documentos_mes' => $cartasMes,
        ];
    }

    /** Tesistas vigentes (expediente activo) por tutor, de mayor a menor (HU-039). */
    public function cargaPorTutor(int $limite = 15): array
    {
        return Database::connection()->query(
            "SELECT t.id_tutor, CONCAT(u.nombre, ' ', u.apellido) AS docente, COUNT(*) AS carga
             FROM asignaciones_tutor_mg a INNER JOIN expedientes_mg e ON e.id_expediente = a.id_expediente AND e.estado = 'activo'
             INNER JOIN tutores t ON t.id_tutor = a.id_tutor INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE a.estado = 'vigente' GROUP BY t.id_tutor, u.nombre, u.apellido ORDER BY carga DESC, docente LIMIT " . max(1, min($limite, 50))
        )->fetchAll();
    }

    // ------------------------------------------------------------------
    // Reporte por cohorte (HU-033)
    // ------------------------------------------------------------------

    public function totales(array $filtros): array
    {
        [$where, $params] = $this->filtros($filtros);
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $base = ' FROM expedientes_mg e
             INNER JOIN estudiantes es ON es.id_estudiante = e.id_estudiante
             INNER JOIN usuarios u ON u.id_usuario = es.id_usuario
             LEFT JOIN asignaciones_tutor_mg a ON a.id_expediente = e.id_expediente AND a.estado = \'vigente\'' . $sqlWhere;

        $porEtapa = Database::connection()->prepare('SELECT e.etapa_actual AS clave, COUNT(*) AS total' . $base . ' GROUP BY e.etapa_actual');
        $porEtapa->execute($params);
        $porEstado = Database::connection()->prepare('SELECT e.estado AS clave, COUNT(*) AS total' . $base . ' GROUP BY e.estado');
        $porEstado->execute($params);

        return [
            'etapa' => array_column($porEtapa->fetchAll(), 'total', 'clave'),
            'estado' => array_column($porEstado->fetchAll(), 'total', 'clave'),
        ];
    }
}
