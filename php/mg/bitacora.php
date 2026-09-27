<?php

// Bitacora de auditoria de Modalidades de Grado (HU-040): quien cambio que, cuando
// y desde donde, con los datos antes y despues. Filtros y exportacion CSV.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAction('mg.bitacora');
$title = 'Bitácora de Modalidades de Grado';
$activePage = 'mg-bitacora';

$modelo = new MgBitacora();
$texto = static fn (string $clave): string => is_string($_GET[$clave] ?? null) ? trim($_GET[$clave]) : '';
$tablas = $modelo->tablas();
$acciones = $modelo->acciones();
$usuarios = $modelo->usuarios();
$filtros = [
    'tabla' => in_array($texto('tabla'), $tablas, true) ? $texto('tabla') : '',
    'accion' => in_array($texto('accion'), $acciones, true) ? $texto('accion') : '',
    'id_registro' => mb_substr($texto('registro'), 0, 60),
    'id_usuario' => (int) filter_input(INPUT_GET, 'id_usuario', FILTER_VALIDATE_INT),
    'desde' => mg_fecha_valida($texto('desde')) ? $texto('desde') : '',
    'hasta' => mg_fecha_valida($texto('hasta')) ? $texto('hasta') : '',
];

$resumen = static function (?string $json, int $corte = 80): string {
    if ($json === null) {
        return '—';
    }
    $datos = json_decode($json, true);
    if (!is_array($datos)) {
        return '—';
    }
    $partes = [];
    foreach ($datos as $clave => $valor) {
        $texto = is_scalar($valor) || $valor === null ? (string) ($valor ?? 'vacío') : json_encode($valor, JSON_UNESCAPED_UNICODE);
        $partes[] = $clave . ': ' . ($corte > 0 && mb_strlen($texto) > $corte ? mb_substr($texto, 0, $corte) . '…' : $texto);
    }

    return implode(' · ', $partes);
};

if ($texto('export') === 'csv') {
    $salida = csv_download('bitacora-mg-' . date('Y-m-d'));
    csv_row($salida, ['Fecha', 'Usuario', 'IP', 'Acción', 'Tabla', 'Registro', 'Antes', 'Después']);
    foreach ($modelo->listar($filtros, 5000) as $fila) {
        csv_row($salida, [$fila['fecha'], $fila['usuario'], (string) $fila['ip'], $fila['accion'], $fila['tabla'], $fila['id_registro'],
            $resumen($fila['datos_antes'], 0), $resumen($fila['datos_despues'], 0)]);
    }
    fclose($salida);
    exit;
}

$limite = 300;
$filas = $modelo->listar($filtros, $limite);
$query = http_build_query(array_filter(['tabla' => $filtros['tabla'], 'accion' => $filtros['accion'], 'registro' => $filtros['id_registro'],
    'id_usuario' => $filtros['id_usuario'], 'desde' => $filtros['desde'], 'hasta' => $filtros['hasta']]));

require dirname(__DIR__, 2) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Bitácora</h1>
            <p>Cambios sensibles del módulo: asignaciones, tribunales, defensas, notas, estados, reuniones, informes, alertas, parámetros y plantillas, con los datos antes y después.</p>
        </div>
        <a class="button" href="<?= e(app_url('mg/bitacora.php?' . ($query !== '' ? $query . '&' : '') . 'export=csv')) ?>">Exportar CSV (Excel)</a>
    </div>
    <form method="get" class="card mg-filtros">
        <div><label for="tabla">Tabla</label><select id="tabla" name="tabla"><option value="">Todas</option><?php foreach ($tablas as $item): ?><option value="<?= e($item) ?>" <?= $filtros['tabla'] === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></div>
        <div><label for="accion">Acción</label><select id="accion" name="accion"><option value="">Todas</option><?php foreach ($acciones as $item): ?><option value="<?= e($item) ?>" <?= $filtros['accion'] === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></div>
        <div><label for="id_usuario">Usuario</label><select id="id_usuario" name="id_usuario"><option value="">Todos</option><?php foreach ($usuarios as $item): ?><option value="<?= (int) $item['id_usuario'] ?>" <?= $filtros['id_usuario'] === (int) $item['id_usuario'] ? 'selected' : '' ?>><?= e($item['nombre'] . ' (' . $item['nombre_rol'] . ')') ?></option><?php endforeach; ?></select></div>
        <div><label for="registro">Id del registro</label><input id="registro" name="registro" type="text" maxlength="60" value="<?= e($filtros['id_registro']) ?>"></div>
        <div><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="<?= e($filtros['desde']) ?>"></div>
        <div><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= e($filtros['hasta']) ?>"></div>
        <div class="mg-filtros-acciones"><button type="submit">Filtrar</button><a class="button secondary" href="<?= e(app_url('mg/bitacora.php')) ?>">Limpiar</a></div>
    </form>
    <?php if (count($filas) === $limite): ?><p class="panel-note">Se muestran los <?= $limite ?> cambios más recientes. Acota con los filtros o exporta el CSV (hasta 5000).</p><?php endif; ?>
    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Registro</th><th>Antes</th><th>Después</th></tr></thead>
            <tbody>
                <?php foreach ($filas as $fila): ?>
                    <tr>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $fila['fecha']))) ?></td>
                        <td><?= e($fila['usuario']) ?><br><small><?= e((string) $fila['ip']) ?></small></td>
                        <td><?= e($fila['accion']) ?></td>
                        <td><a href="<?= e(app_url('mg/bitacora.php?tabla=' . rawurlencode($fila['tabla']) . '&registro=' . rawurlencode($fila['id_registro']))) ?>"><?= e($fila['tabla']) ?> #<?= e($fila['id_registro']) ?></a></td>
                        <td><small><?= e($resumen($fila['datos_antes'])) ?></small></td>
                        <td><small><?= e($resumen($fila['datos_despues'])) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$filas): ?><tr><td colspan="6" class="empty-state">Sin registros.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>
