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

    /** Ultimos cambios, con filtros opcionales por tabla y registro. */
    public function listar(?string $tabla = null, ?string $idRegistro = null, int $limite = 200): array
    {
        $where = [];
        $params = [];
        if ($tabla !== null && $tabla !== '') {
            $where[] = 'b.tabla = :tabla';
            $params['tabla'] = $tabla;
        }
        if ($idRegistro !== null && $idRegistro !== '') {
            $where[] = 'b.id_registro = :id';
            $params['id'] = $idRegistro;
        }
        $statement = Database::connection()->prepare(
            "SELECT b.id_bitacora, b.accion, b.tabla, b.id_registro, b.datos_antes, b.datos_despues, b.ip, b.fecha,
                    CASE WHEN u.id_usuario IS NULL THEN 'Usuario eliminado' ELSE CONCAT(u.nombre, ' ', u.apellido) END AS usuario
             FROM bitacora_mg b LEFT JOIN usuarios u ON u.id_usuario = b.id_usuario"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY b.fecha DESC, b.id_bitacora DESC LIMIT ' . max(1, min($limite, 1000))
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function tablas(): array
    {
        return array_column(Database::connection()->query('SELECT DISTINCT tabla FROM bitacora_mg ORDER BY tabla')->fetchAll(), 'tabla');
    }
}
