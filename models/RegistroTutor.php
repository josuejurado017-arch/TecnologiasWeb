<?php

declare(strict_types=1);

final class RegistroTutor
{
    public function register(array $data): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $roleStatement = $pdo->prepare(
                "SELECT id_rol FROM roles WHERE nombre_rol = 'tutor' LIMIT 1"
            );
            $roleStatement->execute();
            $roleId = $roleStatement->fetchColumn();
            if ($roleId === false) {
                throw new RuntimeException('El rol tutor no existe.');
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

            // Autorregistro: la cuenta nace activa (puede iniciar sesion y configurar
            // sus materias) pero la habilitacion docente queda pendiente hasta que la
            // coordinacion la apruebe; mientras tanto el motor no le propone grupos.
            $profileStatement = $pdo->prepare(
                "INSERT INTO tutores (id_usuario, especialidad, biografia, estado_docente) VALUES (:id_usuario, :especialidad, :biografia, 'pendiente')"
            );
            $profileStatement->execute([
                'id_usuario' => (int) $pdo->lastInsertId(),
                'especialidad' => $data['especialidad'],
                'biografia' => $data['biografia'] !== '' ? $data['biografia'] : null,
            ]);
            $tutorId = (int) $pdo->lastInsertId();
            (new Tutor())->logEstado($pdo, $tutorId, null, 'pendiente', 'Registro público del tutor.', null);
            (new Notificacion())->notifyAdminsTutorPending($pdo, $tutorId, $data['nombre'] . ' ' . $data['apellido']);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}
