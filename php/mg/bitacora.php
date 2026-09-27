<?php

// Consulta de la bitacora de Modalidades de Grado (lectura; la pantalla completa es HU-040).

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAction('mg.bitacora');
$title = 'Bitácora de Modalidades de Grado';
$activePage = 'mg-bitacora';

$modelo = new MgBitacora();
$tabla = is_string($_GET['tabla'] ?? null) ? $_GET['tabla'] : '';
$registro = is_string($_GET['registro'] ?? null) ? trim($_GET['registro']) : '';
$tablas = $modelo->tablas();
if ($tabla !== '' && !in_array($tabla, $tablas, true)) {
    $tabla = '';
}
$filas = $modelo->listar($tabla, $registro);

$resumen = static function (?string $json): string {
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
        $partes[] = $clave . ': ' . (mb_strlen($texto) > 80 ? mb_substr($texto, 0, 80) . '…' : $texto);
    }

    return implode(' · ', $partes);
};

require dirname(__DIR__, 2) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Bitácora</h1>
            <p>Cambios sensibles del módulo: asignaciones, tribunales, defensas, notas, estados, parámetros y plantillas, con los datos antes y después.</p>
        </div>
    </div>
    <form method="get" class="card mg-filtros">
        <div><label for="tabla">Tabla</label><select id="tabla" name="tabla"><option value="">Todas</option><?php foreach ($tablas as $item): ?><option value="<?= e($item) ?>" <?= $tabla === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></div>
        <div><label for="registro">Id del registro</label><input id="registro" name="registro" type="text" maxlength="60" value="<?= e($registro) ?>"></div>
        <div class="mg-filtros-acciones"><button type="submit">Filtrar</button></div>
    </form>
    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Registro</th><th>Antes</th><th>Después</th></tr></thead>
            <tbody>
                <?php foreach ($filas as $fila): ?>
                    <tr>
                        <td><?= e(date('d/m/Y H:i', strtotime((string) $fila['fecha']))) ?></td>
                        <td><?= e($fila['usuario']) ?><br><small><?= e((string) $fila['ip']) ?></small></td>
                        <td><?= e($fila['accion']) ?></td>
                        <td><?= e($fila['tabla']) ?> #<?= e($fila['id_registro']) ?></td>
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
