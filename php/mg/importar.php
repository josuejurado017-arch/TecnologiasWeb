<?php

// Importar el padron de estudiantes desde un CSV (HU-023): subir -> vista previa -> confirmar.
// El archivo no se guarda: la vista previa vive en la sesion hasta confirmar.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAction('mg.importar');
$title = 'Importar padrón de grado';
$activePage = 'mg-importar';

$controller = new MgExpedientesController();
$error = null;
$vista = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    $accion = (string) ($_POST['accion'] ?? '');
    if ($accion === 'previsualizar') {
        [$filas, $error] = $controller->previsualizarImportacion($_FILES['archivo'] ?? []);
        if ($error === null) {
            $vista = ['token' => bin2hex(random_bytes(16)), 'archivo' => basename((string) $_FILES['archivo']['name']), 'filas' => $filas];
            $_SESSION['mg_importacion'] = $vista;
        }
    } elseif ($accion === 'confirmar') {
        $pendiente = $_SESSION['mg_importacion'] ?? null;
        if (!is_array($pendiente) || !hash_equals((string) $pendiente['token'], (string) ($_POST['token'] ?? ''))) {
            $error = 'La vista previa expiró o ya se confirmó. Vuelve a subir el archivo.';
        } else {
            unset($_SESSION['mg_importacion']);
            [$importacionId, $error] = $controller->confirmarImportacion($pendiente['filas'], $pendiente['archivo'], (int) Auth::user()['id_usuario']);
            if ($error === null) {
                mg_redirect('importar.php', ['importacion' => $importacionId]);
            }
        }
    } elseif ($accion === 'cancelar') {
        unset($_SESSION['mg_importacion']);
        mg_redirect('importar.php');
    }
}

$expedientes = new MgExpediente();
$importacionId = filter_input(INPUT_GET, 'importacion', FILTER_VALIDATE_INT) ?: null;
$detalle = $importacionId ? $expedientes->detalleImportacion($importacionId) : [];
$historial = $expedientes->importaciones();
$resultados = ['creado' => 'Crear', 'omitido' => 'Omitir', 'pendiente_cuenta' => 'Pendiente de cuenta', 'error' => 'Error'];
$clases = ['creado' => 'badge-success', 'omitido' => 'badge-cerrada', 'pendiente_cuenta' => 'badge-warning', 'error' => 'badge-danger'];

require dirname(__DIR__, 2) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Importar padrón</h1>
            <p>Carga un CSV exportado de SATS o armado en Excel. Primero verás cada fila con su resultado; nada se crea hasta que confirmes. Reimportar el mismo archivo no duplica expedientes.</p>
        </div>
        <a class="button secondary" href="<?= e(app_url('mg/expedientes/')) ?>">Expedientes</a>
    </div>

    <?php if ($error !== null): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <?php if ($vista !== null): ?>
        <?php $cuenta = array_count_values(array_column($vista['filas'], 'resultado')); ?>
        <section class="card">
            <div class="section-heading"><div><span class="eyebrow">Vista previa · <?= e($vista['archivo']) ?></span><h2><?= count($vista['filas']) ?> filas</h2></div></div>
            <p><span class="badge badge-success"><?= (int) ($cuenta['creado'] ?? 0) ?> se crearán</span> <span class="badge badge-cerrada"><?= (int) ($cuenta['omitido'] ?? 0) ?> se omiten</span> <span class="badge badge-warning"><?= (int) ($cuenta['pendiente_cuenta'] ?? 0) ?> sin cuenta</span> <span class="badge badge-danger"><?= (int) ($cuenta['error'] ?? 0) ?> con error</span></p>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Fila</th><th>R.U.</th><th>Estudiante</th><th>Modalidad</th><th>Cohorte</th><th>Resultado</th><th>Detalle</th></tr></thead>
                    <tbody>
                        <?php foreach ($vista['filas'] as $fila): ?>
                            <tr>
                                <td><?= (int) $fila['fila'] ?></td>
                                <td><?= e($fila['registro_universitario']) ?></td>
                                <td><?= e($fila['estudiante'] ?? '—') ?></td>
                                <td><?= e($fila['modalidad_texto']) ?></td>
                                <td><?= e($fila['cohorte_texto']) ?></td>
                                <td><span class="badge <?= e($clases[$fila['resultado']]) ?>"><?= e($resultados[$fila['resultado']]) ?></span></td>
                                <td><?= e($fila['mensaje']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" class="actions mg-pie-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="token" value="<?= e($vista['token']) ?>">
                <button type="submit" name="accion" value="confirmar" <?= ($cuenta['creado'] ?? 0) === 0 ? 'disabled' : '' ?>>Confirmar y crear <?= (int) ($cuenta['creado'] ?? 0) ?> expedientes</button>
                <button class="button secondary" type="submit" name="accion" value="cancelar">Cancelar</button>
            </form>
        </section>
    <?php else: ?>
        <?php if ($importacionId !== null && $detalle): ?>
            <?php $cuenta = array_count_values(array_column($detalle, 'resultado')); ?>
            <p class="success" role="status">Importación registrada: <?= (int) ($cuenta['creado'] ?? 0) ?> expedientes creados, <?= (int) ($cuenta['omitido'] ?? 0) ?> omitidos, <?= (int) ($cuenta['pendiente_cuenta'] ?? 0) ?> sin cuenta y <?= (int) ($cuenta['error'] ?? 0) ?> con error.</p>
            <section class="card">
                <div class="section-heading"><div><span class="eyebrow">Resultado</span><h2>Importación #<?= (int) $importacionId ?></h2></div></div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Fila</th><th>R.U.</th><th>Resultado</th><th>Detalle</th></tr></thead>
                        <tbody>
                            <?php foreach ($detalle as $fila): ?>
                                <tr>
                                    <td><?= (int) $fila['fila'] ?></td>
                                    <td><?= e((string) $fila['registro_universitario']) ?></td>
                                    <td><span class="badge <?= e($clases[$fila['resultado']]) ?>"><?= e($resultados[$fila['resultado']] === 'Crear' ? 'Creado' : $resultados[$fila['resultado']]) ?></span></td>
                                    <td><?= e($fila['mensaje']) ?><?php if ($fila['id_expediente']): ?> · <a href="<?= e(app_url('mg/expedientes/ver.php?id=' . (int) $fila['id_expediente'])) ?>">Ver</a><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <section class="card narrow-wide mg-seccion">
            <h2>Subir archivo</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="previsualizar">
                <label for="archivo">Archivo CSV (máx. 2 MB)</label>
                <input id="archivo" name="archivo" type="file" accept=".csv,text/csv" required>
                <p class="form-hint">Separador <code>;</code> o <code>,</code>. Columnas obligatorias: <code>registro_universitario</code>, <code>modalidad</code> (código o nombre, ej. TESIS) y <code>cohorte</code> (código o nombre). Opcionales: <code>titulo</code> y <code>fecha_inicio</code> (AAAA-MM-DD; si falta, se usa el inicio de la cohorte). Otras columnas se ignoran.</p>
                <p class="form-hint">El estudiante debe tener cuenta: las filas sin cuenta quedan como <em>pendiente de cuenta</em> para reimportar después de registrarlo.</p>
                <button type="submit">Ver vista previa</button>
            </form>
        </section>

        <?php if ($historial): ?>
            <section class="card mg-seccion">
                <div class="section-heading"><div><span class="eyebrow">Evidencia</span><h2>Importaciones anteriores</h2></div></div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>#</th><th>Fecha</th><th>Archivo</th><th>Usuario</th><th>Filas</th><th>Creados</th><th>Omitidos</th><th>Errores</th></tr></thead>
                        <tbody>
                            <?php foreach ($historial as $item): ?>
                                <tr>
                                    <td><a href="<?= e(app_url('mg/importar.php?importacion=' . (int) $item['id_importacion'])) ?>"><?= (int) $item['id_importacion'] ?></a></td>
                                    <td><?= e(date('d/m/Y H:i', strtotime((string) $item['fecha']))) ?></td>
                                    <td><?= e($item['archivo']) ?></td>
                                    <td><?= e((string) $item['usuario']) ?></td>
                                    <td><?= (int) $item['total_filas'] ?></td>
                                    <td><?= (int) $item['creados'] ?></td>
                                    <td><?= (int) $item['omitidos'] ?></td>
                                    <td><?= (int) $item['errores'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php require dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>
