<?php

declare(strict_types=1);

final class Estudiante
{
    /** Valores del filtro "Tutoria del periodo activo". */
    public const FILTROS_TUTORIA = [
        'inscrito' => 'Con grupo',
        'espera' => 'En espera',
        'sin_solicitud' => 'Sin solicitud',
    ];

    /**
     * Padron de estudiantes con su tutoria del periodo activo (materia del grupo o
     * de la espera). $filtros (ya normalizados por EstudiantesController::filtros):
     *   q         texto en nombre, apellido, carnet, correo, usuario, telefono o registro;
     *   carrera   id_carrera;  semestre  1-12;  estado  activo|inactivo;
     *   tutoria   inscrito|espera|sin_solicitud (periodo activo).
     */
    public function all(array $filtros = []): array
    {
        $where = [];
        $params = [];
        if (($filtros['q'] ?? '') !== '') {
            $campos = ['u.nombre', 'u.apellido', "CONCAT(u.nombre, ' ', u.apellido)", 'u.carnet_identidad', 'u.correo', 'u.usuario', 'u.telefono', 'e.registro_universitario'];
            $partes = [];
            foreach ($campos as $i => $campo) {
                $partes[] = "{$campo} LIKE :q{$i}";
                $params["q{$i}"] = '%' . addcslashes((string) $filtros['q'], '%_\\') . '%';
            }
            $where[] = '(' . implode(' OR ', $partes) . ')';
        }
        if (!empty($filtros['carrera'])) {
            $where[] = 'e.id_carrera = :carrera';
            $params['carrera'] = (int) $filtros['carrera'];
        }
        if (!empty($filtros['semestre'])) {
            $where[] = 'e.semestre = :semestre';
            $params['semestre'] = (int) $filtros['semestre'];
        }
        if (!empty($filtros['estado'])) {
            $where[] = 'u.estado = :estado';
            $params['estado'] = (string) $filtros['estado'];
        }
        $tutoria = [
            'inscrito' => 'ti.materia IS NOT NULL',
            'espera' => 'ti.materia IS NULL AND td.materia IS NOT NULL',
            'sin_solicitud' => 'ti.materia IS NULL AND td.materia IS NULL',
        ];
        if (isset($tutoria[$filtros['tutoria'] ?? ''])) {
            $where[] = $tutoria[$filtros['tutoria']];
        }

        $sql = "SELECT e.id_estudiante, e.id_usuario, e.id_carrera, e.semestre,
                       e.registro_universitario, u.nombre, u.apellido, u.correo,
                       u.telefono, u.carnet_identidad, u.usuario, u.estado, c.nombre_carrera,
                       ti.materia AS tutoria_grupo, td.materia AS tutoria_espera
                FROM estudiantes e
                INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
                INNER JOIN carreras c ON c.id_carrera = e.id_carrera
                LEFT JOIN (
                    SELECT i.id_estudiante, MIN(m.nombre_materia) AS materia
                    FROM inscripciones i
                    INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
                    INNER JOIN periodos p ON p.id_periodo = g.id_periodo AND p.id_periodo = " . Periodo::sqlIdActivo() . "
                    INNER JOIN materias m ON m.id_materia = g.id_materia
                    WHERE i.estado = 'inscrito' AND g.estado <> 'cancelado'
                    GROUP BY i.id_estudiante
                ) ti ON ti.id_estudiante = e.id_estudiante
                LEFT JOIN (
                    SELECT d.id_estudiante, MIN(m.nombre_materia) AS materia
                    FROM demanda_tutoria d
                    INNER JOIN periodos p ON p.id_periodo = d.id_periodo AND p.id_periodo = " . Periodo::sqlIdActivo() . "
                    INNER JOIN materias m ON m.id_materia = d.id_materia
                    WHERE d.estado = 'pendiente'
                    GROUP BY d.id_estudiante
                ) td ON td.id_estudiante = e.id_estudiante"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY u.apellido, u.nombre';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT e.id_estudiante, e.id_usuario, e.id_carrera, e.semestre, e.registro_universitario, u.nombre, u.apellido, u.correo, u.telefono, u.carnet_identidad, u.usuario, u.estado, c.nombre_carrera FROM estudiantes e INNER JOIN usuarios u ON u.id_usuario = e.id_usuario INNER JOIN carreras c ON c.id_carrera = e.id_carrera WHERE e.id_estudiante = :id LIMIT 1'
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

    /**
     * Actualiza solo los datos academicos del perfil. A diferencia de update(),
     * no reasigna id_usuario: la cuenta de un perfil no se cambia desde la
     * pantalla de edicion (ver views/estudiantes/form.php).
     */
    public function updateAcademic(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE estudiantes SET id_carrera = :id_carrera, semestre = :semestre,
             registro_universitario = :registro_universitario WHERE id_estudiante = :id_estudiante'
        );
        $statement->execute([
            'id_estudiante' => $id,
            'id_carrera' => $data['id_carrera'],
            'semestre' => $data['semestre'],
            'registro_universitario' => $data['registro_universitario'] !== '' ? $data['registro_universitario'] : null,
        ]);
    }

    /** El registro universitario ya pertenece a OTRO estudiante (indice unico). */
    public function registroTaken(string $registro, int $exceptStudentId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM estudiantes WHERE registro_universitario = :registro AND id_estudiante <> :id LIMIT 1'
        );
        $statement->execute(['registro' => $registro, 'id' => $exceptStudentId]);

        return (bool) $statement->fetchColumn();
    }
}
