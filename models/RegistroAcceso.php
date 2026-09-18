<?php

declare(strict_types=1);

final class RegistroAcceso
{
    public function all(?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $sql = <<<'SQL'
            SELECT ra.id_acceso, ra.fecha_hora, ra.ip_origen, ra.resultado,
                   u.usuario, u.nombre, u.apellido
            FROM registro_accesos ra
            INNER JOIN usuarios u ON u.id_usuario = ra.id_usuario
        SQL;
        $where = [];
        $params = [];
        if ($fechaDesde !== null) {
            $where[] = 'ra.fecha_hora >= :fecha_desde';
            $params['fecha_desde'] = $fechaDesde;
        }
        if ($fechaHasta !== null) {
            $where[] = 'ra.fecha_hora < DATE_ADD(:fecha_hasta, INTERVAL 1 DAY)';
            $params['fecha_hasta'] = $fechaHasta;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ra.fecha_hora DESC, ra.id_acceso DESC LIMIT 500';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
