<?php

declare(strict_types=1);

/**
 * Catalogos de Modalidades de Grado (db/044, HU-022): modalidades, cohortes y
 * calendario de hitos por cohorte. Una cohorte con expedientes no se elimina:
 * se desactiva.
 */
final class MgCatalogo
{
    public const ETAPAS_HITO = ['previa' => 'Etapa previa', 'mg1' => 'MG1', 'mg2' => 'MG2'];

    public const TIPOS_HITO = [
        'taller' => 'Taller',
        'asignacion_tutor' => 'Asignación de tutor',
        'asignacion_tribunal' => 'Asignación de tribunales',
        'informe' => 'Informe de avance',
        'defensa' => 'Defensa',
        'ingreso_mg2' => 'Ingreso a MG2',
        'otro' => 'Otro',
    ];

    // ------------------------------------------------------------------
    // Modalidades
    // ------------------------------------------------------------------

    public function modalidades(bool $soloActivas = false): array
    {
        return Database::connection()->query(
            'SELECT m.id_modalidad, m.codigo, m.nombre, m.requiere_tutor, m.flujo, m.regla_por_validar, m.activa,
                    (SELECT COUNT(*) FROM expedientes_mg e WHERE e.id_modalidad = m.id_modalidad) AS expedientes
             FROM modalidades_grado m' . ($soloActivas ? ' WHERE m.activa = 1' : '') . ' ORDER BY m.requiere_tutor DESC, m.nombre'
        )->fetchAll();
    }

    public function modalidad(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM modalidades_grado WHERE id_modalidad = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /** Busca por codigo o nombre, sin distinguir mayusculas (importacion CSV). */
    public function modalidadPorTexto(string $texto): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM modalidades_grado WHERE UPPER(codigo) = UPPER(:a) OR LOWER(nombre) = LOWER(:b) LIMIT 1'
        );
        $statement->execute(['a' => $texto, 'b' => $texto]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function setModalidadActiva(int $id, bool $activa): void
    {
        Database::connection()->prepare('UPDATE modalidades_grado SET activa = :a WHERE id_modalidad = :id')
            ->execute(['a' => $activa ? 1 : 0, 'id' => $id]);
    }

    // ------------------------------------------------------------------
    // Cohortes
    // ------------------------------------------------------------------

    public function cohortes(bool $soloActivas = false): array
    {
        return Database::connection()->query(
            "SELECT c.id_cohorte, c.codigo, c.nombre, c.fecha_inicio, c.fecha_fin, c.activa,
                    (SELECT COUNT(*) FROM expedientes_mg e WHERE e.id_cohorte = c.id_cohorte) AS expedientes,
                    (SELECT COUNT(*) FROM calendario_mg h WHERE h.id_cohorte = c.id_cohorte) AS hitos,
                    (SELECT COUNT(*) FROM calendario_mg h WHERE h.id_cohorte = c.id_cohorte AND h.tipo = 'informe') AS informes
             FROM cohortes_mg c" . ($soloActivas ? ' WHERE c.activa = 1' : '') . ' ORDER BY c.activa DESC, c.fecha_inicio DESC'
        )->fetchAll();
    }

    public function cohorte(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM cohortes_mg WHERE id_cohorte = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function cohortePorTexto(string $texto): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM cohortes_mg WHERE UPPER(codigo) = UPPER(:a) OR LOWER(nombre) = LOWER(:b) LIMIT 1'
        );
        $statement->execute(['a' => $texto, 'b' => $texto]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function cohorteExiste(string $campo, string $valor, ?int $ignorar): bool
    {
        $campo = $campo === 'codigo' ? 'codigo' : 'nombre';
        $statement = Database::connection()->prepare(
            "SELECT 1 FROM cohortes_mg WHERE {$campo} = :v AND id_cohorte <> :id LIMIT 1"
        );
        $statement->execute(['v' => $valor, 'id' => $ignorar ?? 0]);

        return (bool) $statement->fetchColumn();
    }

    public function crearCohorte(array $data): int
    {
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO cohortes_mg (codigo, nombre, fecha_inicio, fecha_fin) VALUES (:codigo, :nombre, :inicio, :fin)')
            ->execute(['codigo' => $data['codigo'], 'nombre' => $data['nombre'], 'inicio' => $data['fecha_inicio'], 'fin' => $data['fecha_fin']]);

        return (int) $pdo->lastInsertId();
    }

    public function actualizarCohorte(int $id, array $data): void
    {
        Database::connection()->prepare(
            'UPDATE cohortes_mg SET codigo = :codigo, nombre = :nombre, fecha_inicio = :inicio, fecha_fin = :fin WHERE id_cohorte = :id'
        )->execute(['codigo' => $data['codigo'], 'nombre' => $data['nombre'], 'inicio' => $data['fecha_inicio'], 'fin' => $data['fecha_fin'], 'id' => $id]);
    }

    public function setCohorteActiva(int $id, bool $activa): void
    {
        Database::connection()->prepare('UPDATE cohortes_mg SET activa = :a WHERE id_cohorte = :id')
            ->execute(['a' => $activa ? 1 : 0, 'id' => $id]);
    }

    // ------------------------------------------------------------------
    // Calendario de hitos
    // ------------------------------------------------------------------

    public function hitos(int $cohorteId): array
    {
        $statement = Database::connection()->prepare(
            "SELECT * FROM calendario_mg WHERE id_cohorte = :id
             ORDER BY FIELD(etapa, 'previa', 'mg1', 'mg2'), fecha_limite, orden"
        );
        $statement->execute(['id' => $cohorteId]);

        return $statement->fetchAll();
    }

    public function hito(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM calendario_mg WHERE id_hito = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function guardarHito(?int $id, array $data): void
    {
        $params = [
            'etapa' => $data['etapa'], 'tipo' => $data['tipo'], 'nombre' => $data['nombre'], 'orden' => $data['orden'],
            'fecha' => $data['fecha_limite'], 'avance' => $data['avance_esperado_pct'],
        ];
        if ($id === null) {
            Database::connection()->prepare(
                'INSERT INTO calendario_mg (id_cohorte, etapa, tipo, nombre, orden, fecha_limite, avance_esperado_pct)
                 VALUES (:cohorte, :etapa, :tipo, :nombre, :orden, :fecha, :avance)'
            )->execute($params + ['cohorte' => $data['id_cohorte']]);
            return;
        }
        Database::connection()->prepare(
            'UPDATE calendario_mg SET etapa = :etapa, tipo = :tipo, nombre = :nombre, orden = :orden, fecha_limite = :fecha,
                avance_esperado_pct = :avance WHERE id_hito = :id'
        )->execute($params + ['id' => $id]);
    }

    public function eliminarHito(int $id): void
    {
        Database::connection()->prepare('DELETE FROM calendario_mg WHERE id_hito = :id')->execute(['id' => $id]);
    }
}
