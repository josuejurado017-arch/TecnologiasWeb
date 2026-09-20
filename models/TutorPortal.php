<?php

declare(strict_types=1);

final class TutorPortal
{
    public function profile(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT t.id_tutor, t.especialidad, t.biografia, u.id_usuario, u.nombre, u.apellido, u.correo, u.usuario, u.telefono, u.estado FROM tutores t INNER JOIN usuarios u ON u.id_usuario = t.id_usuario WHERE t.id_usuario = :id_usuario LIMIT 1'
        );
        $statement->execute(['id_usuario' => $userId]);
        $profile = $statement->fetch();

        return $profile ?: null;
    }

    public function subjects(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT m.id_materia, m.nombre_materia, c.nombre_carrera FROM tutor_materia tm INNER JOIN tutores t ON t.id_tutor = tm.id_tutor INNER JOIN materias m ON m.id_materia = tm.id_materia LEFT JOIN carreras c ON c.id_carrera = m.id_carrera WHERE t.id_usuario = :id_usuario ORDER BY m.nombre_materia'
        );
        $statement->execute(['id_usuario' => $userId]);

        return $statement->fetchAll();
    }

    public function availableSubjects(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT m.id_materia, m.nombre_materia, c.nombre_carrera FROM materias m LEFT JOIN carreras c ON c.id_carrera = m.id_carrera WHERE NOT EXISTS (SELECT 1 FROM tutor_materia tm INNER JOIN tutores t ON t.id_tutor = tm.id_tutor WHERE tm.id_materia = m.id_materia AND t.id_usuario = :id_usuario) ORDER BY m.nombre_materia'
        );
        $statement->execute(['id_usuario' => $userId]);

        return $statement->fetchAll();
    }

    public function addSubject(int $userId, int $subjectId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO tutor_materia (id_tutor, id_materia) SELECT t.id_tutor, m.id_materia FROM tutores t CROSS JOIN materias m WHERE t.id_usuario = :id_usuario AND m.id_materia = :id_materia'
        );
        $statement->execute(['id_usuario' => $userId, 'id_materia' => $subjectId]);

        if ($statement->rowCount() < 1) {
            throw new RuntimeException('La materia seleccionada no existe.');
        }
    }

    public function removeSubject(int $userId, int $subjectId): void
    {
        $statement = Database::connection()->prepare(
            "SELECT g.id_grupo FROM grupos_tutoria g INNER JOIN tutores t ON t.id_tutor = g.id_tutor WHERE t.id_usuario = :id_usuario AND g.id_materia = :id_materia AND g.estado IN ('formacion', 'confirmado', 'en_curso') LIMIT 1"
        );
        $statement->execute(['id_usuario' => $userId, 'id_materia' => $subjectId]);
        if ($statement->fetch()) {
            throw new RuntimeException('No puedes quitar una materia con grupos activos en una campana.');
        }

        $statement = Database::connection()->prepare(
            'DELETE tm FROM tutor_materia tm INNER JOIN tutores t ON t.id_tutor = tm.id_tutor WHERE t.id_usuario = :id_usuario AND tm.id_materia = :id_materia'
        );
        $statement->execute(['id_usuario' => $userId, 'id_materia' => $subjectId]);

        if ($statement->rowCount() < 1) {
            throw new RuntimeException('La materia no esta asignada a tu perfil.');
        }
    }

    public function updateProfile(int $userId, string $specialty, string $biography): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE tutores SET especialidad = :especialidad, biografia = :biografia WHERE id_usuario = :id_usuario'
        );
        $statement->execute([
            'id_usuario' => $userId,
            'especialidad' => $specialty,
            'biografia' => $biography !== '' ? $biography : null,
        ]);
    }
}
