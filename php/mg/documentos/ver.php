<?php

// Vista de impresion de documentos emitidos (HU-027/030): muestra el snapshot tal como
// se emitio. Imprimir o "Guardar como PDF" desde el navegador.
// El snapshot es HTML seguro: plantilla saneada al guardarse y valores escapados al emitirse.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.ver');

$modelo = new MgDocumento();
$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$ids = is_string($_GET['ids'] ?? null) ? array_slice(array_filter(array_map('intval', explode(',', $_GET['ids']))), 0, 200) : [];
$documentos = $id ? array_filter([$modelo->find($id)]) : $modelo->listar(null, null, $ids);
if (!$documentos) {
    http_response_code(404);
    exit('Documento no encontrado.');
}
$documentos = array_reverse(array_values($documentos));
$volver = (int) filter_input(INPUT_GET, 'volver', FILTER_VALIDATE_INT) ?: (int) $documentos[0]['id_expediente'];
$aviso = is_string($_GET['aviso'] ?? null) ? $_GET['aviso'] : '';
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(count($documentos) === 1 ? $documentos[0]['numero'] : count($documentos) . ' documentos') ?></title>
    <link rel="stylesheet" href="<?= e(app_url('Front/assets/css/mg-documento.css')) ?>">
</head>
<body>
    <div class="doc-barra">
        <button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button>
        <a href="<?= e(app_url('mg/expedientes/ver.php?id=' . $volver)) ?>">Volver al expediente</a>
        <span><?= count($documentos) ?> documento<?= count($documentos) === 1 ? '' : 's' ?></span>
        <?php if ($aviso !== ''): ?><strong class="doc-aviso"><?= e($aviso) ?></strong><?php endif; ?>
    </div>
    <?php foreach ($documentos as $documento): ?>
        <?php // Una carta lleva una hoja por destinatario (tutor y estudiante) separadas por doc-salto. ?>
        <?php foreach (explode('<div class="doc-salto"></div>', (string) $documento['contenido_snapshot']) as $hoja): ?>
            <article class="doc-hoja">
                <header class="doc-encabezado">
                    <strong>Universidad Privada Domingo Savio · Sede Tarija</strong>
                    <span>Modalidades de Grado</span>
                </header>
                <?= $hoja ?>
                <footer class="doc-pie">Documento <?= e($documento['numero']) ?> · emitido el <?= e(date('d/m/Y H:i', strtotime((string) $documento['fecha_generacion']))) ?><?= !empty($documento['generado']) ? ' por ' . e($documento['generado']) : '' ?></footer>
            </article>
        <?php endforeach; ?>
    <?php endforeach; ?>
</body>
</html>
