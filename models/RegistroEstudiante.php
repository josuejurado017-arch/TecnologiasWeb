<?php

declare(strict_types=1);

final class RegistroEstudiante
{
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

    public function register(array $data): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $roleStatement = $pdo->prepare(
                "SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante' LIMIT 1"
            );
            $roleStatement->execute();
            $roleId = $roleStatement->fetchColumn();
            if ($roleId === false) {
                throw new RuntimeException('El rol estudiante no existe.');
            }

            $userStatement = $pdo->prepare(
                "INSERT INTO usuarios (id_rol, nombre, apellido, correo, usuario, contrasena_hash, telefono, estado) VALUES (:id_rol, :nombre, :apellido, :correo, :usuario, :contrasena_hash, :telefono, 'activo')"
            );
            $userStatement->execute([
                'id_rol' => $roleId,
                'nombre' => $data['nombre'],
                'apellido' => $data['apellido'],
                'correo' => $data['correo'],
                'usuario' => $data['usuario'],
                'contrasena_hash' => password_hash($data['contrasena'], PASSWORD_DEFAULT),
                'telefono' => $data['telefono'] !== '' ? $data['telefono'] : null,
            ]);

            $studentStatement = $pdo->prepare(
                'INSERT INTO estudiantes (id_usuario, id_carrera, semestre, registro_universitario) VALUES (:id_usuario, :id_carrera, :semestre, :registro_universitario)'
            );
            $studentStatement->execute([
                'id_usuario' => (int) $pdo->lastInsertId(),
                'id_carrera' => $data['id_carrera'],
                'semestre' => $data['semestre'],
                'registro_universitario' => $data['registro_universitario'] !== '' ? $data['registro_universitario'] : null,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}
