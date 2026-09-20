<?php

declare(strict_types=1);

/**
 * Resolucion de identidad academica (estudiante/tutor) a partir del usuario.
 * El resto del modelo de "tutoria individual" fue reemplazado por el modelo
 * institucional: grupos_tutoria, inscripciones y sesiones_tutoria.
 */
final class Tutoria
{
    public function studentIdByUserId(int $userId): ?int
    {
        $statement = Database::connection()->prepare(
            'SELECT id_estudiante FROM estudiantes WHERE id_usuario = :id_usuario LIMIT 1'
        );
        $statement->execute(['id_usuario' => $userId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function tutorIdByUserId(int $userId): ?int
    {
        $statement = Database::connection()->prepare(
            'SELECT id_tutor FROM tutores WHERE id_usuario = :id_usuario LIMIT 1'
        );
        $statement->execute(['id_usuario' => $userId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}
