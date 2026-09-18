<?php

declare(strict_types=1);

final class AccesosController
{
    public function index(?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        return (new RegistroAcceso())->all($fechaDesde, $fechaHasta);
    }
}
