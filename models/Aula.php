<?php

declare(strict_types=1);

final class Aula
{
    public function all(): array
    {
        return Database::connection()
            ->query('SELECT id_aula, nombre, tipo, capacidad, ubicacion, enlace, plataforma, estado FROM aulas ORDER BY tipo, nombre')
            ->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_aula, nombre, tipo, capacidad, ubicacion, enlace, plataforma, estado FROM aulas WHERE id_aula = :id_aula LIMIT 1'
        );
        $statement->execute(['id_aula' => $id]);
        $aula = $statement->fetch();

        return $aula ?: null;
    }

    /** Verifica si el nombre ya existe (ignora el propio registro al editar). */
    public function nameExists(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM aulas WHERE nombre = :nombre';
        $params = ['nombre' => $name];
        if ($ignoreId !== null) {
            $sql .= ' AND id_aula <> :id';
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
            'INSERT INTO aulas (nombre, tipo, capacidad, ubicacion, enlace, plataforma, estado)
             VALUES (:nombre, :tipo, :capacidad, :ubicacion, :enlace, :plataforma, :estado)'
        );
        $statement->execute($data);
    }

    public function update(int $id, array $data): void
    {
        $data['id_aula'] = $id;
        $statement = Database::connection()->prepare(
            'UPDATE aulas SET nombre = :nombre, tipo = :tipo, capacidad = :capacidad, ubicacion = :ubicacion,
                enlace = :enlace, plataforma = :plataforma, estado = :estado
             WHERE id_aula = :id_aula'
        );
        $statement->execute($data);
    }

    public function delete(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM aulas WHERE id_aula = :id_aula');
        $statement->execute(['id_aula' => $id]);
    }
}
