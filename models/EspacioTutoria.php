<?php

declare(strict_types=1);

/**
 * Espacios de tutoria (db/029): categorias como "Aula presencial", "Laboratorio de
 * computacion", "Microsoft Teams" o "Google Meet". No son aulas reales ni se
 * reservan: el sistema no conoce la ocupacion de la universidad. El lugar o el
 * enlace concreto de cada grupo vive en grupos_tutoria (ubicacion / enlace).
 *
 * Cada modalidad tiene un espacio predeterminado: el que el motor asigna al crear
 * un grupo. El predeterminado no se puede desactivar.
 */
final class EspacioTutoria
{
    public const MODALIDADES = ['presencial' => 'Presencial', 'virtual' => 'Virtual'];

    public function all(): array
    {
        return Database::connection()->query(
            "SELECT e.id_espacio, e.nombre, e.modalidad, e.descripcion, e.predeterminado, e.estado,
                    (SELECT COUNT(*) FROM grupos_tutoria g WHERE g.id_espacio = e.id_espacio
                       AND g.estado IN ('por_aprobar','formacion','confirmado','en_curso')) AS grupos_activos
             FROM espacios_tutoria e
             ORDER BY FIELD(e.modalidad, 'presencial', 'virtual'), e.predeterminado DESC, e.nombre"
        )->fetchAll();
    }

    /** Espacios activos, opcionalmente de una modalidad, para los selectores. */
    public function activos(?string $modalidad = null): array
    {
        $sql = "SELECT id_espacio, nombre, modalidad, predeterminado FROM espacios_tutoria WHERE estado = 'activo'";
        $params = [];
        if ($modalidad !== null) {
            $sql .= ' AND modalidad = :modalidad';
            $params['modalidad'] = $modalidad;
        }
        $sql .= " ORDER BY FIELD(modalidad, 'presencial', 'virtual'), predeterminado DESC, nombre";
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_espacio, nombre, modalidad, descripcion, predeterminado, estado FROM espacios_tutoria WHERE id_espacio = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $espacio = $statement->fetch();

        return $espacio ?: null;
    }

    /** Dominio del enlace => palabra que identifica la plataforma en el nombre del espacio. */
    private const PLATAFORMAS = [
        'meet.google.com' => 'meet',
        'zoom.us' => 'zoom',
        'teams.microsoft.com' => 'teams',
        'teams.live.com' => 'teams',
    ];

    /**
     * Espacio de un grupo, calculado y nunca elegido a mano:
     *   - virtual: la plataforma que indica el dominio del enlace (Meet, Zoom, Teams);
     *   - presencial: el espacio actual del grupo si ya es presencial;
     *   - si no, el predeterminado de la modalidad.
     */
    public function deducir(string $modalidad, ?string $enlace, ?int $espacioActualId = null): ?array
    {
        if ($modalidad === 'virtual' && $enlace !== null) {
            $host = strtolower((string) parse_url($enlace, PHP_URL_HOST));
            foreach (self::PLATAFORMAS as $dominio => $clave) {
                if ($host === $dominio || str_ends_with($host, '.' . $dominio)) {
                    foreach ($this->activos('virtual') as $espacio) {
                        if (str_contains(mb_strtolower((string) $espacio['nombre']), $clave)) {
                            return $espacio;
                        }
                    }
                }
            }
        }
        if ($modalidad === 'presencial' && $espacioActualId !== null) {
            $actual = $this->findById($espacioActualId);
            if ($actual !== null && $actual['modalidad'] === 'presencial') {
                return $actual;
            }
        }

        return $this->predeterminado($modalidad);
    }

    /** Espacio que el motor asigna a un grupo nuevo de esa modalidad. */
    public function predeterminado(string $modalidad): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT id_espacio, nombre, modalidad FROM espacios_tutoria
             WHERE modalidad = :modalidad AND estado = 'activo'
             ORDER BY predeterminado DESC, id_espacio ASC LIMIT 1"
        );
        $statement->execute(['modalidad' => $modalidad]);
        $espacio = $statement->fetch();

        return $espacio ?: null;
    }

    public function nameExists(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM espacios_tutoria WHERE nombre = :nombre';
        $params = ['nombre' => $name];
        if ($ignoreId !== null) {
            $sql .= ' AND id_espacio <> :id';
            $params['id'] = $ignoreId;
        }
        $statement = Database::connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);

        return (bool) $statement->fetchColumn();
    }

    public function create(array $data): void
    {
        Database::connection()->prepare(
            'INSERT INTO espacios_tutoria (nombre, modalidad, descripcion) VALUES (:nombre, :modalidad, :descripcion)'
        )->execute([
            'nombre' => $data['nombre'],
            'modalidad' => $data['modalidad'],
            'descripcion' => $data['descripcion'],
        ]);
    }

    /** La modalidad no se edita: cambiaria el sentido de los grupos que ya usan el espacio. */
    public function update(int $id, array $data): void
    {
        Database::connection()->prepare(
            'UPDATE espacios_tutoria SET nombre = :nombre, descripcion = :descripcion WHERE id_espacio = :id'
        )->execute(['nombre' => $data['nombre'], 'descripcion' => $data['descripcion'], 'id' => $id]);
    }

    public function setEstado(int $id, string $estado): void
    {
        Database::connection()->prepare(
            'UPDATE espacios_tutoria SET estado = :estado WHERE id_espacio = :id'
        )->execute(['estado' => $estado, 'id' => $id]);
    }

    /** Marca el espacio como predeterminado de su modalidad (y desmarca el anterior). */
    public function setPredeterminado(int $id, string $modalidad): void
    {
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $connection->prepare('UPDATE espacios_tutoria SET predeterminado = 0 WHERE modalidad = :modalidad')
                ->execute(['modalidad' => $modalidad]);
            $connection->prepare("UPDATE espacios_tutoria SET predeterminado = 1, estado = 'activo' WHERE id_espacio = :id")
                ->execute(['id' => $id]);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
