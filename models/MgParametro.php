<?php

declare(strict_types=1);

/**
 * Parametros configurables de Modalidades de Grado (db/044, HU-020). Las cifras
 * dudosas de las entrevistas viven aqui, con su fuente y estado de evidencia, y
 * los modulos las leen con entero()/texto(): ninguna queda escrita en el codigo.
 */
final class MgParametro
{
    /** Cache por peticion. */
    private static ?array $valores = null;

    public function all(): array
    {
        return Database::connection()->query(
            "SELECT p.clave, p.valor, p.tipo, p.descripcion, p.fuente, p.estado_evidencia, p.fecha_actualizacion,
                    CONCAT(u.nombre, ' ', u.apellido) AS actualizado_por
             FROM parametros_mg p LEFT JOIN usuarios u ON u.id_usuario = p.actualizado_por
             ORDER BY FIELD(p.estado_evidencia, 'pendiente', 'propuesta', 'confirmado'), p.clave"
        )->fetchAll();
    }

    public function find(string $clave): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM parametros_mg WHERE clave = :c');
        $statement->execute(['c' => $clave]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    private static function valores(): array
    {
        if (self::$valores === null) {
            self::$valores = [];
            foreach (Database::connection()->query('SELECT clave, valor FROM parametros_mg')->fetchAll() as $row) {
                self::$valores[$row['clave']] = $row['valor'];
            }
        }

        return self::$valores;
    }

    /** Valor entero, o $defecto si el parametro no existe o esta vacio (p. ej. "sin maximo"). */
    public static function entero(string $clave, ?int $defecto = null): ?int
    {
        $valor = self::valores()[$clave] ?? null;

        return $valor === null || $valor === '' ? $defecto : (int) $valor;
    }

    public static function texto(string $clave, string $defecto = ''): string
    {
        $valor = self::valores()[$clave] ?? null;

        return $valor === null ? $defecto : (string) $valor;
    }

    public function actualizar(PDO $pdo, string $clave, ?string $valor, int $userId): void
    {
        $pdo->prepare('UPDATE parametros_mg SET valor = :v, actualizado_por = :u, fecha_actualizacion = NOW() WHERE clave = :c')
            ->execute(['v' => $valor, 'u' => $userId, 'c' => $clave]);
        self::$valores = null;
    }
}
