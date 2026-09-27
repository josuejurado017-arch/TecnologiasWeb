<?php

declare(strict_types=1);

/**
 * Bitacora de auditoria de Modalidades de Grado (db/044): quien cambio que, con los
 * datos antes y despues. Se escribe dentro de la misma transaccion del cambio.
 */
final class MgBitacora
{
    public static function registrar(PDO $pdo, string $accion, string $tabla, $idRegistro, ?array $antes, ?array $despues): void
    {
        $pdo->prepare(
            'INSERT INTO bitacora_mg (id_usuario, accion, tabla, id_registro, datos_antes, datos_despues, ip)
             VALUES (:u, :accion, :tabla, :id, :antes, :despues, :ip)'
        )->execute([
            'u' => Auth::user()['id_usuario'] ?? null,
            'accion' => $accion,
            'tabla' => $tabla,
            'id' => (string) $idRegistro,
            'antes' => $antes !== null ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
            'despues' => $despues !== null ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    /**
     * Ultimos cambios (HU-040) con filtros opcionales: tabla, id_registro, accion,
     * id_usuario, desde y hasta (fechas Y-m-d, incluidas).
     */
    public function listar(array $filtros = [], int $limite = 200): array
    {
        $where = [];
        $params = [];
        foreach (['tabla' => 'b.tabla', 'id_registro' => 'b.id_registro', 'accion' => 'b.accion'] as $campo => $columna) {
            if (isset($filtros[$campo]) && $filtros[$campo] !== '') {
                $where[] = "{$columna} = :{$campo}";
                $params[$campo] = (string) $filtros[$campo];
            }
        }
        if (!empty($filtros['id_usuario'])) {
            $where[] = 'b.id_usuario = :usuario';
            $params['usuario'] = (int) $filtros['id_usuario'];
        }
        if (!empty($filtros['desde']) && mg_fecha_valida($filtros['desde'])) {
            $where[] = 'b.fecha >= :desde';
            $params['desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta']) && mg_fecha_valida($filtros['hasta'])) {
            $where[] = 'b.fecha < :hasta';
            $params['hasta'] = date('Y-m-d', strtotime($filtros['hasta'] . ' +1 day')) . ' 00:00:00';
        }
        $statement = Database::connection()->prepare(
            "SELECT b.id_bitacora, b.accion, b.tabla, b.id_registro, b.datos_antes, b.datos_despues, b.ip, b.fecha,
                    CASE WHEN u.id_usuario IS NULL THEN 'Usuario eliminado' ELSE CONCAT(u.nombre, ' ', u.apellido) END AS usuario
             FROM bitacora_mg b LEFT JOIN usuarios u ON u.id_usuario = b.id_usuario"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY b.fecha DESC, b.id_bitacora DESC LIMIT ' . max(1, min($limite, 5000))
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function acciones(): array
    {
        return array_column(Database::connection()->query('SELECT DISTINCT accion FROM bitacora_mg ORDER BY accion')->fetchAll(), 'accion');
    }

    /** Usuarios que aparecen en la bitacora (para el filtro). */
    public function usuarios(): array
    {
        return Database::connection()->query(
            "SELECT DISTINCT u.id_usuario, CONCAT(u.apellido, ', ', u.nombre) AS nombre, r.nombre_rol
             FROM bitacora_mg b INNER JOIN usuarios u ON u.id_usuario = b.id_usuario
             INNER JOIN roles r ON r.id_rol = u.id_rol ORDER BY nombre"
        )->fetchAll();
    }

    public function tablas(): array
    {
        return array_column(Database::connection()->query('SELECT DISTINCT tabla FROM bitacora_mg ORDER BY tabla')->fetchAll(), 'tabla');
    }
}
