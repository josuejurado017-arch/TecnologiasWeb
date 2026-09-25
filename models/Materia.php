<?php

declare(strict_types=1);

final class Materia
{
    /** Modalidad academica de la materia (db/029): manda sobre la preferencia del tutor. */
    public const MODALIDADES_REQUERIDAS = [
        'libre' => 'Libre (la decide el tutor)',
        'presencial' => 'Solo presencial',
        'virtual' => 'Solo virtual',
    ];

    public function all(): array
    {
        return Database::connection()
            ->query('SELECT m.id_materia, m.nombre_materia, m.id_carrera, m.modalidad_requerida, c.nombre_carrera FROM materias m LEFT JOIN carreras c ON c.id_carrera = m.id_carrera ORDER BY m.nombre_materia')
            ->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_materia, nombre_materia, id_carrera, modalidad_requerida FROM materias WHERE id_materia = :id_materia'
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

    public function create(string $name, ?int $careerId, string $modalidadRequerida = 'libre'): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO materias (nombre_materia, id_carrera, modalidad_requerida) VALUES (:nombre_materia, :id_carrera, :modalidad_requerida)'
        );
        $statement->execute([
            'nombre_materia' => $name,
            'id_carrera' => $careerId,
            'modalidad_requerida' => $modalidadRequerida,
        ]);
    }

    public function update(int $id, string $name, ?int $careerId, string $modalidadRequerida): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE materias SET nombre_materia = :nombre_materia, id_carrera = :id_carrera, modalidad_requerida = :modalidad_requerida WHERE id_materia = :id_materia'
        );
        $statement->execute([
            'id_materia' => $id,
            'nombre_materia' => $name,
            'id_carrera' => $careerId,
            'modalidad_requerida' => $modalidadRequerida,
        ]);
    }

    /**
     * Tutores cuya configuracion de la materia choca con su modalidad requerida
     * (p. ej. configuraron "virtual" y la materia pasa a "solo presencial"): el motor
     * no les propone grupos en ella hasta que la ajusten.
     */
    public function tutoresIncompatibles(int $id): array
    {
        $statement = Database::connection()->prepare(
            "SELECT t.id_tutor, t.id_usuario, c.modalidad
             FROM tutor_materia_config c
             INNER JOIN materias m ON m.id_materia = c.id_materia
             INNER JOIN tutores t ON t.id_tutor = c.id_tutor
              WHERE c.id_materia = :id AND c.id_periodo = (SELECT id_periodo FROM periodos WHERE estado = 'activa' LIMIT 1) AND NOT " . TutorMateriaConfig::sqlModalidadCompatible('c', 'm')
        );
        $statement->execute(['id' => $id]);

        return $statement->fetchAll();
    }

    public function delete(int $id): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM materias WHERE id_materia = :id_materia'
        );
        $statement->execute(['id_materia' => $id]);
    }
}
