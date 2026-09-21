<?php

declare(strict_types=1);

final class Materia
{
    public function all(): array
    {
        return Database::connection()
            ->query('SELECT m.id_materia, m.nombre_materia, m.id_carrera, c.nombre_carrera FROM materias m LEFT JOIN carreras c ON c.id_carrera = m.id_carrera ORDER BY m.nombre_materia')
            ->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_materia, nombre_materia, id_carrera FROM materias WHERE id_materia = :id_materia'
        );
        $statement->execute(['id_materia' => $id]);
        $subject = $statement->fetch();

        return $subject ?: null;
    }

    public function careers(): array
    {
        return Database::connection()
            ->query('SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera')
            ->fetchAll();
    }

    public function careerExists(int $careerId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM carreras WHERE id_carrera = :id_carrera LIMIT 1'
        );
        $statement->execute(['id_carrera' => $careerId]);

        return (bool) $statement->fetchColumn();
    }

    /** Verifica duplicado por nombre (colacion case/acento-insensible); ignora el propio registro al editar. */
    public function nameExists(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM materias WHERE nombre_materia = :nombre';
        $params = ['nombre' => $name];
        if ($ignoreId !== null) {
            $sql .= ' AND id_materia <> :id';
            $params['id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return (bool) $statement->fetchColumn();
    }

    public function create(string $name, ?int $careerId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO materias (nombre_materia, id_carrera) VALUES (:nombre_materia, :id_carrera)'
        );
        $statement->execute([
            'nombre_materia' => $name,
            'id_carrera' => $careerId,
        ]);
    }

    public function update(int $id, string $name, ?int $careerId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE materias SET nombre_materia = :nombre_materia, id_carrera = :id_carrera WHERE id_materia = :id_materia'
        );
        $statement->execute([
            'id_materia' => $id,
            'nombre_materia' => $name,
            'id_carrera' => $careerId,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM materias WHERE id_materia = :id_materia'
        );
        $statement->execute(['id_materia' => $id]);
    }
}
