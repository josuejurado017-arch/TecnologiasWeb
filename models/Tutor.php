<?php

declare(strict_types=1);

final class Tutor
{
    public function all(): array
    {
        $sql = <<<'SQL'
            SELECT t.id_tutor, t.id_usuario, t.especialidad, t.biografia,
                   u.nombre, u.apellido, u.correo, u.usuario, u.estado
            FROM tutores t
            INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
            ORDER BY t.id_tutor DESC
        SQL;

        return Database::connection()->query($sql)->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT t.id_tutor, t.id_usuario, t.especialidad, t.biografia, u.nombre, u.apellido, u.correo, u.usuario, u.estado FROM tutores t INNER JOIN usuarios u ON u.id_usuario = t.id_usuario WHERE t.id_tutor = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $tutor = $statement->fetch();

        return $tutor ?: null;
    }

    public function usersForForm(?int $currentUserId = null): array
    {
        $sql = <<<'SQL'
            SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.usuario, u.estado
            FROM usuarios u
            INNER JOIN roles r ON r.id_rol = u.id_rol
            WHERE r.nombre_rol = 'tutor'
              AND (u.estado = 'activo' OR u.id_usuario = :current_user)
              AND (NOT EXISTS (SELECT 1 FROM tutores t WHERE t.id_usuario = u.id_usuario) OR u.id_usuario = :current_user_again)
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

    public function create(array $data): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO tutores (id_usuario, especialidad, biografia) VALUES (:id_usuario, :especialidad, :biografia)'
        );
        $statement->execute([
            'id_usuario' => $data['id_usuario'],
            'especialidad' => $data['especialidad'] !== '' ? $data['especialidad'] : null,
            'biografia' => $data['biografia'] !== '' ? $data['biografia'] : null,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE tutores SET id_usuario = :id_usuario, especialidad = :especialidad, biografia = :biografia WHERE id_tutor = :id_tutor'
        );
        $statement->execute([
            'id_tutor' => $id,
            'id_usuario' => $data['id_usuario'],
            'especialidad' => $data['especialidad'] !== '' ? $data['especialidad'] : null,
            'biografia' => $data['biografia'] !== '' ? $data['biografia'] : null,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM tutores WHERE id_tutor = :id_tutor'
        );
        $statement->execute(['id_tutor' => $id]);
    }

    public function findIdByUserId(int $userId): ?int
    {
        $statement = Database::connection()->prepare(
            'SELECT id_tutor FROM tutores WHERE id_usuario = :id_usuario LIMIT 1'
        );
        $statement->execute(['id_usuario' => $userId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function userIsEligible(int $userId, ?int $currentUserId = null): bool
    {
        $sql = <<<'SQL'
            SELECT 1
            FROM usuarios u
            INNER JOIN roles r ON r.id_rol = u.id_rol
            WHERE u.id_usuario = :id_usuario
              AND r.nombre_rol = 'tutor'
              AND (u.estado = 'activo' OR u.id_usuario = :current_user)
              AND (NOT EXISTS (SELECT 1 FROM tutores t WHERE t.id_usuario = u.id_usuario) OR u.id_usuario = :current_user)
            LIMIT 1
        SQL;
        $statement = Database::connection()->prepare($sql);
        $statement->execute([
            'id_usuario' => $userId,
            'current_user' => $currentUserId ?? 0,
        ]);

        return (bool) $statement->fetchColumn();
    }
}
