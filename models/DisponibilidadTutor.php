<?php

declare(strict_types=1);

final class DisponibilidadTutor
{
    public function all(?int $tutorId = null): array
    {
        $sql = <<<'SQL'
            SELECT d.id_disponibilidad, d.id_tutor, d.dia_semana,
                   d.hora_inicio, d.hora_fin,
                   CONCAT(u.nombre, ' ', u.apellido) AS tutor
            FROM disponibilidad_tutor d
            INNER JOIN tutores t ON t.id_tutor = d.id_tutor
            INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
        SQL;
        $params = [];
        if ($tutorId !== null) {
            $sql .= ' WHERE d.id_tutor = :id_tutor';
            $params['id_tutor'] = $tutorId;
        }
        $sql .= " ORDER BY FIELD(d.dia_semana, 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'), d.hora_inicio";
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT d.id_disponibilidad, d.id_tutor, d.dia_semana, d.hora_inicio, d.hora_fin, CONCAT(u.nombre, \' \', u.apellido) AS tutor FROM disponibilidad_tutor d INNER JOIN tutores t ON t.id_tutor = d.id_tutor INNER JOIN usuarios u ON u.id_usuario = t.id_usuario WHERE d.id_disponibilidad = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $availability = $statement->fetch();

        return $availability ?: null;
    }

    public function tutors(): array
    {
        return Database::connection()->query(
            "SELECT t.id_tutor, CONCAT(u.nombre, ' ', u.apellido) AS tutor FROM tutores t INNER JOIN usuarios u ON u.id_usuario = t.id_usuario WHERE u.estado = 'activo' ORDER BY u.apellido, u.nombre"
        )->fetchAll();
    }

    public function create(array $data): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO disponibilidad_tutor (id_tutor, dia_semana, hora_inicio, hora_fin) VALUES (:id_tutor, :dia_semana, :hora_inicio, :hora_fin)'
        );
        $statement->execute($data);
    }

    public function overlaps(array $data, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM disponibilidad_tutor WHERE id_tutor = :id_tutor AND dia_semana = :dia_semana AND hora_inicio < :hora_fin AND hora_fin > :hora_inicio';
        $params = [
            'id_tutor' => $data['id_tutor'],
            'dia_semana' => $data['dia_semana'],
            'hora_inicio' => $data['hora_inicio'],
            'hora_fin' => $data['hora_fin'],
        ];
        if ($excludeId !== null) {
            $sql .= ' AND id_disponibilidad <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return (bool) $statement->fetchColumn();
    }

    public function overlapsActiveTutoring(array $data): bool
    {
        $sql = <<<'SQL'
            SELECT 1
            FROM grupos_tutoria g
            WHERE g.id_tutor = :id_tutor
              AND g.estado IN ('formacion', 'confirmado', 'en_curso')
              AND g.dia_semana = :dia_semana
              AND g.hora_inicio < :hora_fin
              AND g.hora_fin > :hora_inicio
            LIMIT 1
        SQL;
        $statement = Database::connection()->prepare($sql);
        $statement->execute([
            'id_tutor' => $data['id_tutor'],
            'dia_semana' => $data['dia_semana'],
            'hora_inicio' => $data['hora_inicio'],
            'hora_fin' => $data['hora_fin'],
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function update(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE disponibilidad_tutor SET id_tutor = :id_tutor, dia_semana = :dia_semana, hora_inicio = :hora_inicio, hora_fin = :hora_fin WHERE id_disponibilidad = :id_disponibilidad'
        );
        $data['id_disponibilidad'] = $id;
        $statement->execute($data);
    }

    public function delete(int $id): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM disponibilidad_tutor WHERE id_disponibilidad = :id_disponibilidad'
        );
        $statement->execute(['id_disponibilidad' => $id]);
    }
}
