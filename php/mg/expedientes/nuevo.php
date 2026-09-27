<?php

// Alta manual de un expediente para casos sueltos (HU-024).

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.expediente');
$title = 'Nuevo expediente';
$activePage = 'mg-expedientes';

$data = ['id_estudiante' => 0, 'id_modalidad' => 0, 'id_cohorte' => 0, 'etapa_actual' => 'previa', 'titulo_trabajo' => '', 'fecha_inicio' => date('Y-m-d'), 'observaciones' => ''];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    [$data, $errors, $id] = (new MgExpedientesController())->crear($_POST, (int) Auth::user()['id_usuario']);
    if (!$errors) {
        mg_redirect('expedientes/ver.php?id=' . $id, ['message' => 'creado']);
    }
}

$catalogo = new MgCatalogo();
$estudiantes = (new MgExpediente())->estudiantesActivos();
$modalidades = $catalogo->modalidades(true);
$cohortes = $catalogo->cohortes(true);

require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container narrow-wide">
    <section class="card">
        <h1>Nuevo expediente</h1>
        <p class="panel-note">Para cargar muchos estudiantes a la vez usa <a href="<?= e(app_url('mg/importar.php')) ?>">Importar padrón</a>.</p>
        <?php if ($errors): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if (!$cohortes): ?><p class="alert" role="alert">No hay cohortes activas. <?php if (Auth::canDo('mg.catalogo')): ?><a href="<?= e(app_url('mg/cohortes/form.php')) ?>">Crea una</a>.<?php endif; ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="id_estudiante">Estudiante</label>
            <select id="id_estudiante" name="id_estudiante" required>
                <option value="">Selecciona…</option>
                <?php foreach ($estudiantes as $item): ?><option value="<?= (int) $item['id_estudiante'] ?>" <?= (int) $data['id_estudiante'] === (int) $item['id_estudiante'] ? 'selected' : '' ?>><?= e($item['nombre']) ?> · R.U. <?= e((string) $item['registro_universitario']) ?> · <?= e($item['carrera']) ?></option><?php endforeach; ?>
            </select>
            <div class="form-grid">
                <div>
                    <label for="id_modalidad">Modalidad</label>
                    <select id="id_modalidad" name="id_modalidad" required><option value="">Selecciona…</option><?php foreach ($modalidades as $item): ?><option value="<?= (int) $item['id_modalidad'] ?>" <?= (int) $data['id_modalidad'] === (int) $item['id_modalidad'] ? 'selected' : '' ?>><?= e($item['nombre']) ?></option><?php endforeach; ?></select>
                </div>
                <div>
                    <label for="id_cohorte">Cohorte</label>
                    <select id="id_cohorte" name="id_cohorte" required><option value="">Selecciona…</option><?php foreach ($cohortes as $item): ?><option value="<?= (int) $item['id_cohorte'] ?>" <?= (int) $data['id_cohorte'] === (int) $item['id_cohorte'] ? 'selected' : '' ?>><?= e($item['nombre']) ?></option><?php endforeach; ?></select>
                </div>
                <div>
                    <label for="etapa_actual">Etapa inicial</label>
                    <select id="etapa_actual" name="etapa_actual"><?php foreach (['previa' => 'Etapa previa (talleres)', 'mg1' => 'MG1 · Perfil'] as $valor => $label): ?><option value="<?= e($valor) ?>" <?= $data['etapa_actual'] === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                </div>
                <div>
                    <label for="fecha_inicio">Fecha de inicio</label>
                    <input id="fecha_inicio" name="fecha_inicio" type="date" required value="<?= e($data['fecha_inicio']) ?>">
                </div>
            </div>
            <label for="titulo_trabajo">Título o tema (opcional)</label>
            <input id="titulo_trabajo" name="titulo_trabajo" type="text" maxlength="255" value="<?= e((string) $data['titulo_trabajo']) ?>">
            <label for="observaciones">Observaciones (opcional)</label>
            <textarea id="observaciones" name="observaciones" maxlength="1000" rows="3"><?= e((string) $data['observaciones']) ?></textarea>
            <button type="submit">Crear expediente</button>
            <a class="button secondary" href="<?= e(app_url('mg/expedientes/')) ?>">Cancelar</a>
        </form>
    </section>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
