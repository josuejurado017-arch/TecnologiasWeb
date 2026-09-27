<?php

// Reporte imprimible por estudiante (HU-032).

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.ver');

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$expediente = $id ? (new MgExpediente())->find($id) : null;
if ($expediente === null) {
    http_response_code(404);
    exit('Expediente no encontrado.');
}
$tutores = (new MgAsignacion())->historial($id);
$tribunales = (new MgTribunal())->historial($id);
$defensas = (new MgDefensa())->porExpediente($id);
$notas = (new MgDefensa())->notasPorEtapa($id);
$documentos = (new MgDocumento())->listar($id);
$etapas = (new MgExpediente())->etapas($id);
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte de grado · <?= e($expediente['estudiante']) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('Front/assets/css/mg-documento.css')) ?>">
</head>
<body>
    <div class="doc-barra">
        <button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button>
        <a href="<?= e(app_url('mg/expedientes/ver.php?id=' . $id)) ?>">Volver al expediente</a>
    </div>
    <article class="doc-hoja doc-reporte">
        <header class="doc-encabezado"><strong>Universidad Privada Domingo Savio · Sede Tarija</strong><span>Reporte de Modalidad de Grado · <?= e(date('d/m/Y')) ?></span></header>
        <h1><?= e($expediente['estudiante']) ?></h1>
        <table>
            <tr><th>R.U.</th><td><?= e((string) $expediente['registro_universitario']) ?></td><th>Carrera</th><td><?= e($expediente['carrera']) ?></td></tr>
            <tr><th>Modalidad</th><td><?= e($expediente['modalidad']) ?></td><th>Cohorte</th><td><?= e($expediente['cohorte']) ?></td></tr>
            <tr><th>Etapa</th><td><?= e(MgExpediente::ETAPAS[$expediente['etapa_actual']]) ?></td><th>Estado</th><td><?= e(MgExpediente::ESTADOS[$expediente['estado']]) ?></td></tr>
            <tr><th>Inicio</th><td><?= e(mg_fecha_corta($expediente['fecha_inicio'])) ?></td><th>Cierre</th><td><?= e(mg_fecha_corta($expediente['fecha_cierre'])) ?></td></tr>
            <tr><th>Tema</th><td colspan="3"><?= e((string) ($expediente['titulo_trabajo'] ?? '—')) ?></td></tr>
        </table>

        <h2>Tutores</h2>
        <?php if (!$tutores): ?><p>Sin tutor asignado.</p><?php else: ?>
            <table><tr><th>Docente</th><th>Desde</th><th>Hasta</th><th>Estado</th><th>Decanatura</th></tr>
                <?php foreach ($tutores as $item): ?><tr><td><?= e($item['tutor']) ?></td><td><?= e(mg_fecha_corta($item['fecha_asignacion'])) ?></td><td><?= e(mg_fecha_corta($item['fecha_fin'])) ?></td><td><?= e(MgAsignacion::ESTADOS[$item['estado']]) ?><?= $item['motivo_fin'] ? ': ' . e($item['motivo_fin']) : '' ?></td><td><?= e((string) $item['referencia_decanatura']) ?></td></tr><?php endforeach; ?>
            </table>
        <?php endif; ?>

        <h2>Tribunales</h2>
        <?php if (!$tribunales): ?><p>Sin tribunales.</p><?php else: ?>
            <table><tr><th>Etapa</th><th>Puesto</th><th>Docente</th><th>Estado</th></tr>
                <?php foreach ($tribunales as $item): ?><tr><td><?= e(MgTribunal::ETAPAS[$item['etapa']]) ?></td><td><?= (int) $item['orden'] ?></td><td><?= e($item['docente']) ?></td><td><?= e(ucfirst($item['estado'])) ?></td></tr><?php endforeach; ?>
            </table>
        <?php endif; ?>

        <h2>Defensas y notas</h2>
        <?php if (!$defensas): ?><p>Sin defensas.</p><?php else: ?>
            <table><tr><th>Etapa</th><th>Fecha</th><th>Ambiente</th><th>Estado</th><th>Nota</th></tr>
                <?php foreach ($defensas as $item): ?><tr><td><?= e(MgTribunal::ETAPAS[$item['etapa']]) ?></td><td><?= e(mg_fecha_corta($item['fecha'])) ?> <?= e(substr((string) $item['hora_inicio'], 0, 5)) ?></td><td><?= e($item['ambiente']) ?></td><td><?= e(MgDefensa::ESTADOS[$item['estado']]) ?></td><td><?= $item['nota'] !== null ? e(number_format((float) $item['nota'], 2)) : '—' ?></td></tr><?php endforeach; ?>
            </table>
            <?php if (count($notas) === 2): ?><p>Promedio simple MG1/MG2: <strong><?= e(number_format(array_sum($notas) / 2, 2)) ?></strong> (provisional: fórmula oficial pendiente).</p><?php endif; ?>
        <?php endif; ?>

        <h2>Etapas</h2>
        <table><tr><th>Etapa</th><th>Desde</th><th>Hasta</th><th>Resultado</th></tr>
            <?php foreach ($etapas as $item): ?><tr><td><?= e(MgExpediente::ETAPAS[$item['etapa']]) ?></td><td><?= e(mg_fecha_corta($item['fecha_inicio'])) ?></td><td><?= e(mg_fecha_corta($item['fecha_fin'])) ?></td><td><?= e((string) $item['resultado']) ?></td></tr><?php endforeach; ?>
        </table>

        <h2>Documentos emitidos</h2>
        <?php if (!$documentos): ?><p>Ninguno.</p><?php else: ?>
            <table><tr><th>Número</th><th>Documento</th><th>Destinatario</th><th>Fecha</th></tr>
                <?php foreach ($documentos as $item): ?><tr><td><?= e($item['numero']) ?></td><td><?= e($item['plantilla']) ?></td><td><?= e($item['destinatario']) ?></td><td><?= e(mg_fecha_corta($item['fecha_generacion'])) ?></td></tr><?php endforeach; ?>
            </table>
        <?php endif; ?>
        <p class="doc-nota">Reuniones, informes de avance y alertas: disponibles en el MVP-2.</p>
    </article>
</body>
</html>
