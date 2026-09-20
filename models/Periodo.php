<?php

declare(strict_types=1);

final class Periodo
{
    public function all(): array
    {
        return Database::connection()
            ->query('SELECT id_periodo, nombre, fecha_inicio, fecha_fin, cupo_min_grupo, cupo_max_default, estado, fecha_registro FROM periodos ORDER BY fecha_inicio DESC, nombre')
            ->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_periodo, nombre, fecha_inicio, fecha_fin, cupo_min_grupo, cupo_max_default, estado FROM periodos WHERE id_periodo = :id_periodo LIMIT 1'
        );
        $statement->execute(['id_periodo' => $id]);
        $periodo = $statement->fetch();

        return $periodo ?: null;
    }

    public function activa(): ?array
    {
        $periodo = Database::connection()
            ->query("SELECT id_periodo, nombre, fecha_inicio, fecha_fin, cupo_min_grupo, cupo_max_default, estado FROM periodos WHERE estado = 'activa' ORDER BY fecha_inicio DESC LIMIT 1")
            ->fetch();

        return $periodo ?: null;
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

    public function create(array $data): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO periodos (nombre, fecha_inicio, fecha_fin, cupo_min_grupo, cupo_max_default, estado)
             VALUES (:nombre, :fecha_inicio, :fecha_fin, :cupo_min_grupo, :cupo_max_default, :estado)'
        );
        $statement->execute($data);
    }

    public function update(int $id, array $data): void
    {
        $data['id_periodo'] = $id;
        $statement = Database::connection()->prepare(
            'UPDATE periodos SET nombre = :nombre, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin,
                cupo_min_grupo = :cupo_min_grupo, cupo_max_default = :cupo_max_default, estado = :estado
             WHERE id_periodo = :id_periodo'
        );
        $statement->execute($data);
    }

    /** Solo puede haber una campana activa a la vez: cierra las demas al activar una. */
    public function setActiva(int $id): void
    {
        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $connection->prepare("UPDATE periodos SET estado = 'borrador' WHERE estado = 'activa' AND id_periodo <> :id")
                ->execute(['id' => $id]);
            $connection->prepare("UPDATE periodos SET estado = 'activa' WHERE id_periodo = :id")
                ->execute(['id' => $id]);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM periodos WHERE id_periodo = :id_periodo');
        $statement->execute(['id_periodo' => $id]);
    }
}
