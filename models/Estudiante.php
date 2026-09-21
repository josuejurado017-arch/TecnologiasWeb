<?php

declare(strict_types=1);

final class Estudiante
{
    public function all(): array
    {
        $sql = <<<'SQL'
            SELECT e.id_estudiante, e.id_usuario, e.id_carrera, e.semestre,
                   e.registro_universitario, u.nombre, u.apellido, u.correo,
                   u.usuario, u.estado, c.nombre_carrera
            FROM estudiantes e
            INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
            INNER JOIN carreras c ON c.id_carrera = e.id_carrera
            ORDER BY e.id_estudiante DESC
        SQL;

        return Database::connection()->query($sql)->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT e.id_estudiante, e.id_usuario, e.id_carrera, e.semestre, e.registro_universitario, u.nombre, u.apellido, u.correo, u.usuario, u.estado, c.nombre_carrera FROM estudiantes e INNER JOIN usuarios u ON u.id_usuario = e.id_usuario INNER JOIN carreras c ON c.id_carrera = e.id_carrera WHERE e.id_estudiante = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $student = $statement->fetch();

        return $student ?: null;
    }

    public function usersForForm(?int $currentUserId = null): array
    {
        $sql = <<<'SQL'
            SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.usuario, u.estado
            FROM usuarios u
            INNER JOIN roles r ON r.id_rol = u.id_rol
            WHERE r.nombre_rol = 'estudiante'
              AND (u.estado = 'activo' OR u.id_usuario = :current_user)
              AND (NOT EXISTS (SELECT 1 FROM estudiantes e WHERE e.id_usuario = u.id_usuario) OR u.id_usuario = :current_user_again)
            ORDER BY u.apellido, u.nombre
        SQL;
        $statement = Database::connection()->prepare($sql);
        $currentUserId = $currentUserId ?? 0;
        $statement->execute([
            'current_user' => $currentUserId,
            'current_user_again' => $currentUserId,
        ]);

        return $statement->fetchAll();
    }

    public function careers(): array
    {
        return Database::connection()->query(
            'SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera'
        )->fetchAll();
    }

    public function careerExists(int $careerId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM carreras WHERE id_carrera = :id_carrera LIMIT 1'
        );
        $statement->execute(['id_carrera' => $careerId]);

        return (bool) $statement->fetchColumn();
    }

    public function userIsEligible(int $userId, ?int $currentUserId = null): bool
    {
        $sql = <<<'SQL'
            SELECT 1
            FROM usuarios u
            INNER JOIN roles r ON r.id_rol = u.id_rol
            WHERE u.id_usuario = :id_usuario
              AND r.nombre_rol = 'estudiante'
              AND (u.estado = 'activo' OR u.id_usuario = :current_user)
              AND (NOT EXISTS (SELECT 1 FROM estudiantes e WHERE e.id_usuario = u.id_usuario) OR u.id_usuario = :current_user)
            LIMIT 1
        SQL;
        $statement = Database::connection()->prepare($sql);
        $statement->execute([
            'id_usuario' => $userId,
            'current_user' => $currentUserId ?? 0,
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function create(array $data): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO estudiantes (id_usuario, id_carrera, semestre, registro_universitario) VALUES (:id_usuario, :id_carrera, :semestre, :registro_universitario)'
        );
        $statement->execute([
            'id_usuario' => $data['id_usuario'],
            'id_carrera' => $data['id_carrera'],
            'semestre' => $data['semestre'],
            'registro_universitario' => $data['registro_universitario'] !== '' ? $data['registro_universitario'] : null,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE estudiantes SET id_usuario = :id_usuario, id_carrera = :id_carrera, semestre = :semestre, registro_universitario = :registro_universitario WHERE id_estudiante = :id_estudiante'
        );
        $statement->execute([
            'id_estudiante' => $id,
            'id_usuario' => $data['id_usuario'],
            'id_carrera' => $data['id_carrera'],
            'semestre' => $data['semestre'],
            'registro_universitario' => $data['registro_universitario'] !== '' ? $data['registro_universitario'] : null,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM estudiantes WHERE id_estudiante = :id_estudiante'
        );
        $statement->execute(['id_estudiante' => $id]);
    }
}
