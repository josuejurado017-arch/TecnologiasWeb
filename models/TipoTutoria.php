<?php

declare(strict_types=1);

/**
 * Tipos de tutoria (db/043): catalogo de nombre libre (Pregrado, Postgrado...).
 * Cada periodo pertenece a un tipo y hay como maximo un periodo activo por tipo,
 * asi que tutorias de distinto tipo corren en paralelo.
 *
 * El portal trabaja sobre un tipo a la vez: el que el usuario elige en la barra
 * superior (actual()). Todo lo que dice "el periodo activo" es el periodo activo
 * de ese tipo (Periodo::activa, Periodo::sqlIdActivo).
 */
final class TipoTutoria
{
    private const SESSION_KEY = 'tipo_tutoria';

    /** Cache por peticion de actual(). */
    private static ?int $actual = null;

    public function all(): array
    {
        return Database::connection()->query(
            "SELECT t.id_tipo_tutoria, t.nombre, t.descripcion, t.duracion_max_dias, t.estado, t.fecha_registro,
                    (SELECT COUNT(*) FROM periodos p WHERE p.id_tipo_tutoria = t.id_tipo_tutoria) AS periodos,
                    (SELECT p.nombre FROM periodos p WHERE p.id_tipo_tutoria = t.id_tipo_tutoria AND p.estado = 'activa' LIMIT 1) AS periodo_activo
             FROM tipos_tutoria t
             ORDER BY t.estado, t.nombre"
        )->fetchAll();
    }

    /** Tipos que se pueden elegir al crear un periodo. */
    public function activos(): array
    {
        return Database::connection()->query(
            "SELECT id_tipo_tutoria, nombre, duracion_max_dias FROM tipos_tutoria WHERE estado = 'activo' ORDER BY nombre"
        )->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_tipo_tutoria, nombre, descripcion, duracion_max_dias, estado, fecha_registro
             FROM tipos_tutoria WHERE id_tipo_tutoria = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $tipo = $statement->fetch();

        return $tipo ?: null;
    }

    public function nameExists(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM tipos_tutoria WHERE nombre = :nombre';
        $params = ['nombre' => $name];
        if ($ignoreId !== null) {
            $sql .= ' AND id_tipo_tutoria <> :id';
            $params['id'] = $ignoreId;
        }
        $statement = Database::connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);

        return (bool) $statement->fetchColumn();
    }

    public function create(array $data): void
    {
        Database::connection()->prepare(
            'INSERT INTO tipos_tutoria (nombre, descripcion, duracion_max_dias) VALUES (:nombre, :descripcion, :duracion)'
        )->execute([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'],
            'duracion' => $data['duracion_max_dias'],
        ]);
    }

    public function update(int $id, array $data): void
    {
        Database::connection()->prepare(
            'UPDATE tipos_tutoria SET nombre = :nombre, descripcion = :descripcion, duracion_max_dias = :duracion
             WHERE id_tipo_tutoria = :id'
        )->execute([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'],
            'duracion' => $data['duracion_max_dias'],
            'id' => $id,
        ]);
    }

    public function setEstado(int $id, string $estado): void
    {
        Database::connection()->prepare('UPDATE tipos_tutoria SET estado = :estado WHERE id_tipo_tutoria = :id')
            ->execute(['estado' => $estado, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM tipos_tutoria WHERE id_tipo_tutoria = :id')->execute(['id' => $id]);
    }

    public function countPeriodos(int $id, ?string $estado = null): int
    {
        $sql = 'SELECT COUNT(*) FROM periodos WHERE id_tipo_tutoria = :id';
        $params = ['id' => $id];
        if ($estado !== null) {
            $sql .= ' AND estado = :estado';
            $params['estado'] = $estado;
        }
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /** Bloquea la fila del tipo (dentro de una transaccion): serializa las activaciones de sus periodos. */
    public function lock(int $id): void
    {
        Database::connection()->prepare('SELECT id_tipo_tutoria FROM tipos_tutoria WHERE id_tipo_tutoria = :id FOR UPDATE')
            ->execute(['id' => $id]);
    }

    // ------------------------------------------------------------------
    // Tipo de trabajo del portal (barra superior)
    // ------------------------------------------------------------------

    /**
     * Tipos que el usuario puede elegir, con su periodo activo (o null). El
     * administrador ve todos los tipos activos; tutor y estudiante, solo los que
     * tienen un periodo activo (no tienen nada que hacer en los demas).
     */
    public static function seleccionables(): array
    {
        $rows = Database::connection()->query(
            "SELECT t.id_tipo_tutoria, t.nombre, p.id_periodo, p.nombre AS periodo_activo
             FROM tipos_tutoria t
             LEFT JOIN periodos p ON p.id_tipo_tutoria = t.id_tipo_tutoria AND p.estado = 'activa'
             WHERE t.estado = 'activo'
             ORDER BY p.id_periodo IS NULL, t.id_tipo_tutoria"
        )->fetchAll();

        if ((Auth::user()['nombre_rol'] ?? '') === 'administrador') {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (array $row): bool => $row['id_periodo'] !== null));
    }

    /**
     * Tipo sobre el que trabaja la peticion: el elegido en la sesion si sigue siendo
     * valido para el usuario; si no, el mas antiguo con periodo activo, y se recuerda
     * en la sesion para que crear otro tipo no cambie la vista de nadie a mitad de
     * trabajo. Sin sesion (CLI) o sin tipos elegibles, el mas antiguo con periodo
     * activo o el tipo 1.
     */
    public static function actual(): int
    {
        if (self::$actual !== null) {
            return self::$actual;
        }

        $opciones = Auth::check() ? self::seleccionables() : [];
        $ids = array_map(static fn (array $row): int => (int) $row['id_tipo_tutoria'], $opciones);
        $elegido = (int) ($_SESSION[self::SESSION_KEY] ?? 0);

        if (in_array($elegido, $ids, true)) {
            self::$actual = $elegido;
        } elseif ($ids !== []) {
            self::$actual = $ids[0];
            $_SESSION[self::SESSION_KEY] = $ids[0];
        } else {
            $conActivo = Database::connection()->query(
                "SELECT id_tipo_tutoria FROM periodos WHERE estado = 'activa' ORDER BY id_tipo_tutoria LIMIT 1"
            )->fetchColumn();
            self::$actual = $conActivo !== false ? (int) $conActivo : 1;
        }

        return self::$actual;
    }

    /** Cambia el tipo de trabajo; false si el usuario no puede elegirlo. */
    public static function seleccionar(int $id): bool
    {
        foreach (self::seleccionables() as $row) {
            if ((int) $row['id_tipo_tutoria'] === $id) {
                $_SESSION[self::SESSION_KEY] = $id;
                self::$actual = $id;
                return true;
            }
        }

        return false;
    }
}
