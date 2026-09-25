<?php

declare(strict_types=1);

final class Tutor
{
    public function all(): array
    {
        $sql = <<<'SQL'
            SELECT t.id_tutor, t.id_usuario, t.especialidad, t.biografia,
                   u.nombre, u.apellido, u.correo, u.telefono, u.carnet_identidad,
                   u.usuario, u.estado, t.estado_docente
            FROM tutores t
            INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
            ORDER BY t.id_tutor DESC
        SQL;

        return Database::connection()->query($sql)->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT t.id_tutor, t.id_usuario, t.especialidad, t.biografia, u.nombre, u.apellido, u.correo, u.telefono, u.carnet_identidad, u.usuario, u.estado, t.estado_docente, t.motivo_rechazo FROM tutores t INNER JOIN usuarios u ON u.id_usuario = t.id_usuario WHERE t.id_tutor = :id LIMIT 1'
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

    /**
     * Alta de perfil hecha por el administrador: nace aprobado, porque quien lo crea
     * es quien aprueba. El autorregistro publico usa RegistroTutor (nace pendiente).
     */
    public function create(array $data): void
    {
        $connection = Database::connection();
        $adminId = Auth::user()['id_usuario'] ?? null;
        $statement = $connection->prepare(
            "INSERT INTO tutores (id_usuario, especialidad, biografia, estado_docente, fecha_revision, id_revisor)
             VALUES (:id_usuario, :especialidad, :biografia, 'aprobado', NOW(), :id_revisor)"
        );
        $statement->execute([
            'id_usuario' => $data['id_usuario'],
            'especialidad' => $data['especialidad'] !== '' ? $data['especialidad'] : null,
            'biografia' => $data['biografia'] !== '' ? $data['biografia'] : null,
            'id_revisor' => $adminId,
        ]);
        $this->logEstado($connection, (int) $connection->lastInsertId(), null, 'aprobado', 'Alta realizada por el administrador.', $adminId !== null ? (int) $adminId : null);
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

    /**
     * Actualiza solo el perfil profesional. A diferencia de update(), no reasigna
     * id_usuario: la cuenta de un perfil no se cambia desde la pantalla de edicion.
     */
    public function updateProfile(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE tutores SET especialidad = :especialidad, biografia = :biografia WHERE id_tutor = :id_tutor'
        );
        $statement->execute([
            'id_tutor' => $id,
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

    // ------------------------------------------------------------------
    // Habilitacion docente (db/028): controla si el motor puede proponerle grupos.
    // ------------------------------------------------------------------

    public const ESTADOS_DOCENTE = ['pendiente', 'aprobado', 'rechazado', 'suspendido'];

    /** Tutores por revisar (pendientes) y rechazados, con su oferta declarada. */
    public function forReview(): array
    {
        $configurada = TutorMateriaConfig::sqlMateriaConfigurada('tm');
        $statement = Database::connection()->query(
            "SELECT t.id_tutor, t.id_usuario, t.especialidad, t.biografia, t.estado_docente, t.motivo_rechazo, t.fecha_revision,
                    u.nombre, u.apellido, u.correo, u.telefono, u.usuario, u.estado, u.fecha_registro,
                    (SELECT COUNT(*) FROM tutor_materia tm WHERE tm.id_tutor = t.id_tutor) AS materias,
                    (SELECT COUNT(*) FROM tutor_materia tm WHERE tm.id_tutor = t.id_tutor AND {$configurada}) AS materias_configuradas,
                    (SELECT GROUP_CONCAT(m.nombre_materia ORDER BY m.nombre_materia SEPARATOR ', ')
                       FROM tutor_materia tm INNER JOIN materias m ON m.id_materia = tm.id_materia
                      WHERE tm.id_tutor = t.id_tutor) AS nombres_materias
             FROM tutores t
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE t.estado_docente IN ('pendiente', 'rechazado')
             ORDER BY FIELD(t.estado_docente, 'pendiente', 'rechazado'), u.fecha_registro ASC"
        );

        return $statement->fetchAll();
    }

    public function countPendientes(): int
    {
        return (int) Database::connection()->query(
            "SELECT COUNT(*) FROM tutores WHERE estado_docente = 'pendiente'"
        )->fetchColumn();
    }

    /** Estado docente del tutor asociado a una cuenta (null si la cuenta no tiene perfil). */
    public function estadoDocenteByUserId(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_tutor, estado_docente, motivo_rechazo FROM tutores WHERE id_usuario = :id_usuario LIMIT 1'
        );
        $statement->execute(['id_usuario' => $userId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /** Materias del tutor con horarios configurados: las que entran al motor al aprobarlo. */
    public function configuredMatterIds(int $tutorId): array
    {
        $configurada = TutorMateriaConfig::sqlMateriaConfigurada('tm');
        $statement = Database::connection()->prepare(
            "SELECT tm.id_materia FROM tutor_materia tm WHERE tm.id_tutor = :id_tutor AND {$configurada}"
        );
        $statement->execute(['id_tutor' => $tutorId]);

        return array_map('intval', array_column($statement->fetchAll(), 'id_materia'));
    }

    /**
     * Cambia el estado docente solo si sigue en $esperado (control de concurrencia:
     * dos administradores revisando al mismo tutor). Devuelve false si ya cambio.
     */
    public function transitionEstadoDocente(PDO $pdo, int $tutorId, string $esperado, string $nuevo, ?string $motivo, int $adminId): bool
    {
        $statement = $pdo->prepare(
            'UPDATE tutores SET estado_docente = :nuevo, motivo_rechazo = :motivo_rechazo, fecha_revision = NOW(), id_revisor = :id_revisor
             WHERE id_tutor = :id_tutor AND estado_docente = :esperado'
        );
        $statement->execute([
            'nuevo' => $nuevo,
            'motivo_rechazo' => $nuevo === 'rechazado' ? $motivo : null,
            'id_revisor' => $adminId,
            'id_tutor' => $tutorId,
            'esperado' => $esperado,
        ]);
        if ($statement->rowCount() === 0) {
            return false;
        }
        $this->logEstado($pdo, $tutorId, $esperado, $nuevo, $motivo, $adminId);

        return true;
    }

    public function logEstado(PDO $pdo, int $tutorId, ?string $anterior, string $nuevo, ?string $motivo, ?int $userId): int
    {
        $statement = $pdo->prepare(
            'INSERT INTO tutor_estado_historial (id_tutor, estado_anterior, estado_nuevo, motivo, id_usuario_accion)
             VALUES (:id_tutor, :anterior, :nuevo, :motivo, :id_usuario)'
        );
        $statement->execute([
            'id_tutor' => $tutorId,
            'anterior' => $anterior,
            'nuevo' => $nuevo,
            'motivo' => $motivo,
            'id_usuario' => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }
}
