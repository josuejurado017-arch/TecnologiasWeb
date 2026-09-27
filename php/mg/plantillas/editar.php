<?php

// Editar una plantilla de documento (HU-027/030) sin tocar codigo. Guarda una version
// nueva; los documentos ya emitidos conservan su snapshot.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.parametros');
$activePage = 'mg-parametros';

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$plantilla = $id ? (new MgDocumento())->plantillaPorId($id) : null;
if ($plantilla === null) {
    http_response_code(404);
    exit('Plantilla no encontrada.');
}
$data = $plantilla;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    [$data, $errors] = (new MgDocumentosController())->guardarPlantilla($id, $_POST, (int) Auth::user()['id_usuario']);
    if (!$errors) {
        mg_redirect('plantillas/editar.php?id=' . $id, ['message' => 'guardada']);
    }
}

[$message] = mg_flash(['guardada' => 'Plantilla guardada como versión nueva. Los documentos ya emitidos no cambian.']);
$ejemplo = [
    'numero' => 'MG-' . $plantilla['prefijo'] . '-' . date('Y') . '-0000', 'fecha_larga' => MgDocumento::fechaLarga(date('Y-m-d')),
    'ciudad' => MgParametro::texto('institucion_ciudad'), 'firma' => MgParametro::texto('firma_coordinacion'),
    'destinatario_nombre' => 'Nombre del destinatario', 'estudiante_nombre' => 'Nombre del estudiante', 'registro_universitario' => '000000',
    'carrera' => 'Ingeniería de Sistemas', 'modalidad' => 'Tesis', 'tema' => 'Tema del trabajo', 'tutor_nombre' => 'Nombre del tutor',
    'cohorte' => 'Grupo 1 - Marzo', 'referencia_decanatura' => 'Nota 000/2026', 'etapa' => 'MG1', 'fecha_defensa' => MgDocumento::fechaLarga(date('Y-m-d')),
    'hora_inicio' => '09:00', 'hora_fin' => '10:00', 'ambiente' => 'Auditorio', 'tribunales' => 'Tribunal 1, Tribunal 2',
];
$title = 'Plantilla · ' . $plantilla['nombre'];

require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1><?= e($plantilla['nombre']) ?></h1>
            <p>Versión <?= (int) $plantilla['version'] ?> · código <?= e($plantilla['codigo']) ?> · numeración MG-<?= e($plantilla['prefijo']) ?>-AÑO-0001 (formato real pendiente, pregunta 4).</p>
        </div>
        <a class="button secondary" href="<?= e(app_url('mg/parametros.php')) ?>">Volver</a>
    </div>
    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if ($errors): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="mg-grid">
        <section class="card">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="nombre">Nombre</label>
                <input id="nombre" name="nombre" type="text" maxlength="120" required value="<?= e($data['nombre']) ?>">
                <label for="cuerpo_html">Contenido (HTML)</label>
                <textarea id="cuerpo_html" name="cuerpo_html" rows="22" class="mg-codigo" required><?= e($data['cuerpo_html']) ?></textarea>
                <p class="form-hint">Variables permitidas: <?= e('{{' . implode('}} {{', MgDocumento::VARIABLES[$plantilla['codigo']] ?? []) . '}}') ?></p>
                <p class="form-hint">Se admiten párrafos, negritas, listas y tablas, con el atributo <code>class</code> (<code>doc-derecha</code>, <code>doc-ref</code>, <code>doc-firma</code>). Scripts, estilos y enlaces se eliminan al guardar. Quita <code>[PLANTILLA PROVISIONAL]</code> cuando cargues la versión real.</p>
                <button type="submit">Guardar versión nueva</button>
            </form>
        </section>
        <section class="card">
            <div class="section-heading"><div><span class="eyebrow">Vista previa con datos de ejemplo</span><h2>Así se verá</h2></div></div>
            <div class="mg-preview"><?= MgDocumento::render($plantilla['codigo'], MgDocumento::sanear((string) $data['cuerpo_html']), $ejemplo) ?></div>
        </section>
    </div>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
