<?php require __DIR__ . '/../layouts/header.php'; ?>

<main class="container">
    <div class="page-heading">
        <div>
            <h1>Parámetros y plantillas</h1>
            <p>Las cifras que las entrevistas no dejaron claras viven aquí, con su fuente y su estado de evidencia. Solo generan advertencias: ninguna bloquea una operación.</p>
        </div>
        <a class="button secondary" href="<?= e(app_url('mg/modalidades.php')) ?>">Modalidades</a>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($errores): ?><div class="alert" role="alert"><ul><?php foreach ($errores as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <form method="post" class="table-wrapper card">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <table>
            <thead><tr><th>Parámetro</th><th>Valor</th><th>Evidencia</th><th>Fuente</th><th>Última modificación</th></tr></thead>
            <tbody>
                <?php foreach ($parametros as $parametro): ?>
                    <tr>
                        <td><strong><?= e($parametro['clave']) ?></strong><br><small><?= e($parametro['descripcion']) ?></small></td>
                        <td class="mg-celda-valor">
                            <label class="sr-only" for="p-<?= e($parametro['clave']) ?>"><?= e($parametro['clave']) ?></label>
                            <input id="p-<?= e($parametro['clave']) ?>" name="valor[<?= e($parametro['clave']) ?>]" type="<?= $parametro['tipo'] === 'entero' ? 'number' : 'text' ?>" <?= $parametro['tipo'] === 'entero' ? 'min="0" max="100000"' : 'maxlength="100"' ?> value="<?= e((string) ($parametro['valor'] ?? '')) ?>" placeholder="<?= $parametro['valor'] === null ? 'Sin valor' : '' ?>">
                        </td>
                        <td><?= mg_badge_evidencia((string) $parametro['estado_evidencia']) ?></td>
                        <td><?= e($parametro['fuente']) ?></td>
                        <td><?= $parametro['fecha_actualizacion'] ? e(mg_fecha_corta($parametro['fecha_actualizacion'])) . '<br><small>' . e((string) $parametro['actualizado_por']) . '</small>' : '<small>Valor inicial</small>' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="mg-pie-form"><button type="submit">Guardar parámetros</button></div>
    </form>

    <section class="card mg-seccion">
        <div class="section-heading"><div><span class="eyebrow">Documentos</span><h2>Plantillas de cartas y citaciones</h2></div></div>
        <p class="panel-note">Reemplazar una plantilla no requiere tocar código y no cambia los documentos ya emitidos: cada uno guarda una copia exacta de cómo salió.</p>
        <ul class="mg-lista">
            <?php foreach ($plantillas as $plantilla): ?>
                <li><strong><?= e($plantilla['nombre']) ?></strong> · versión <?= (int) $plantilla['version'] ?><?= str_contains((string) $plantilla['cuerpo_html'], 'PLANTILLA PROVISIONAL') ? ' <span class="badge badge-warning">Provisional</span>' : '' ?>
                    · <a href="<?= e(app_url('mg/plantillas/editar.php?id=' . (int) $plantilla['id_plantilla'])) ?>">Editar</a></li>
            <?php endforeach; ?>
        </ul>
    </section>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
