<?php

declare(strict_types=1);

/**
 * Asignacion de tutor de Modalidades de Grado (db/046, HU-025/026). Historial que
 * nunca se borra (RN-MG-07): cambiar de tutor cierra la vigente como
 * 'reemplazada' y crea otra. La base garantiza una sola vigente por expediente.
 */
final class MgAsignacion
{
    public const ESTADOS = ['vigente' => 'Vigente', 'finalizada' => 'Finalizada', 'reemplazada' => 'Reemplazada'];

    public function vigente(int $expedienteId): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT a.*, CONCAT(u.nombre, ' ', u.apellido) AS tutor, u.id_usuario AS id_usuario_tutor, t.especialidad
             FROM asignaciones_tutor_mg a INNER JOIN tutores t ON t.id_tutor = a.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE a.id_expediente = :id AND a.estado = 'vigente' LIMIT 1"
        );
        $statement->execute(['id' => $expedienteId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT a.*, CONCAT(u.nombre, ' ', u.apellido) AS tutor, u.id_usuario AS id_usuario_tutor
             FROM asignaciones_tutor_mg a INNER JOIN tutores t ON t.id_tutor = a.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario WHERE a.id_asignacion = :id"
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /** Linea de tiempo de tutores del expediente, de la mas reciente a la primera. */
    public function historial(int $expedienteId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT a.*, CONCAT(u.nombre, ' ', u.apellido) AS tutor, CONCAT(ur.nombre, ' ', ur.apellido) AS registrado
             FROM asignaciones_tutor_mg a INNER JOIN tutores t ON t.id_tutor = a.id_tutor
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             LEFT JOIN usuarios ur ON ur.id_usuario = a.registrado_por
             WHERE a.id_expediente = :id ORDER BY a.fecha_registro DESC, a.id_asignacion DESC"
        );
        $statement->execute(['id' => $expedienteId]);

        return $statement->fetchAll();
    }

    /**
     * Docentes que pueden ser tutor o tribunal: cuenta activa y habilitacion aprobada.
     * Incluye su carga vigente de tesistas y sus materias como ayuda de afinidad.
     */
    public function docentes(): array
    {
        return Database::connection()->query(
            "SELECT t.id_tutor, CONCAT(u.apellido, ', ', u.nombre) AS nombre, t.especialidad,
                    (SELECT COUNT(*) FROM asignaciones_tutor_mg a
                      INNER JOIN expedientes_mg e ON e.id_expediente = a.id_expediente AND e.estado = 'activo'
                      WHERE a.id_tutor = t.id_tutor AND a.estado = 'vigente') AS carga,
                    (SELECT GROUP_CONCAT(m.nombre_materia ORDER BY m.nombre_materia SEPARATOR ', ')
                      FROM tutor_materia tm INNER JOIN materias m ON m.id_materia = tm.id_materia
                      WHERE tm.id_tutor = t.id_tutor) AS materias
             FROM tutores t INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE u.estado = 'activo' AND t.estado_docente = 'aprobado'
             ORDER BY u.apellido, u.nombre"
        )->fetchAll();
    }

    public function docente(int $tutorId): ?array
    {
        foreach ($this->docentes() as $docente) {
            if ((int) $docente['id_tutor'] === $tutorId) {
                return $docente;
            }
        }

        return null;
    }

    /** Asignacion nueva. Debe correr en transaccion, despues de cerrar la vigente si la hay. */
    public function crear(PDO $pdo, array $data, int $userId): int
    {
        $pdo->prepare(
            'INSERT INTO asignaciones_tutor_mg (id_expediente, id_tutor, fecha_asignacion, referencia_decanatura, disponibilidad_consultada, observaciones, registrado_por)
             VALUES (:e, :t, :f, :ref, :disp, :obs, :u)'
        )->execute([
            'e' => $data['id_expediente'], 't' => $data['id_tutor'], 'f' => $data['fecha_asignacion'],
            'ref' => $data['referencia_decanatura'], 'disp' => $data['disponibilidad_consultada'] ? 1 : 0,
            'obs' => $data['observaciones'], 'u' => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Cierra la asignacion vigente (bloqueada) como reemplazada o finalizada. */
    public function cerrar(PDO $pdo, int $asignacionId, string $estado, string $fecha, ?string $motivo, ?string $fechaNota): void
    {
        $pdo->prepare(
            "UPDATE asignaciones_tutor_mg SET estado = :estado, fecha_fin = :fecha, motivo_fin = :motivo, fecha_nota_renuncia = :nota
             WHERE id_asignacion = :id AND estado = 'vigente'"
        )->execute(['estado' => $estado, 'fecha' => $fecha, 'motivo' => $motivo, 'nota' => $fechaNota, 'id' => $asignacionId]);
    }

    public function lockVigente(PDO $pdo, int $expedienteId): ?array
    {
        $statement = $pdo->prepare("SELECT * FROM asignaciones_tutor_mg WHERE id_expediente = :id AND estado = 'vigente' FOR UPDATE");
        $statement->execute(['id' => $expedienteId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function tutorIdPorUsuario(int $userId): ?int
    {
        return (new Tutor())->findIdByUserId($userId);
    }
}
