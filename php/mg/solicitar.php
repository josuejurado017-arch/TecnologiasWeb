<?php

// Solicitud de Modalidad de Grado del estudiante (db/049): elige modalidad, propone un
// tema y adjunta su documento de notas (obligatorio). Tambien corrige una observada.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireRole('estudiante');
Auth::requireAction('mg.solicitar');
$title = 'Solicitar modalidad de grado';
$activePage = 'mg-solicitar';

$userId = (int) Auth::user()['id_usuario'];
$modelo = new MgSolicitud();
$perfil = $modelo->perfilEstudiante($userId);
$estudianteId = (int) ($perfil['id_estudiante'] ?? 0);

if ($modelo->tieneExpedienteActivo($estudianteId)) {
    mg_redirect('mi-modalidad.php');
}
$abierta = $modelo->abierta($estudianteId);
// Una pendiente solo se consulta; una observada se corrige aqui mismo.
if ($abierta !== null && $abierta['estado'] === 'pendiente') {
    mg_redirect('mi-modalidad.php');
}
$idCorreccion = $abierta !== null ? (int) $abierta['id_solicitud'] : null;
// Bajo el semestre minimo no hay formulario (una observada ya enviada si se puede corregir).
$habilitado = $idCorreccion !== null || MgSolicitud::semestreHabilitado((int) ($perfil['semestre'] ?? 0));

$data = [
    'id_modalidad' => (int) ($abierta['id_modalidad'] ?? 0),
    'situacion' => (string) ($abierta['situacion'] ?? ''),
    'titulo_propuesto' => (string) ($abierta['titulo_propuesto'] ?? ''),
    'mensaje' => (string) ($abierta['mensaje'] ?? ''),
    'carnet_identidad' => '',
];
$errors = [];
if ($habilitado && $_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    $controller = new MgSolicitudesController();
    [$data, $errors, $id] = $idCorreccion !== null
        ? $controller->reenviar($idCorreccion, $_POST, $_FILES['documento'] ?? [], $userId)
        : $controller->enviar($_POST, $_FILES['documento'] ?? [], $userId);
    if (!$errors) {
        mg_redirect('mi-modalidad.php', ['message' => $idCorreccion !== null ? 'reenviada' : 'enviada']);
    }
}

$modalidades = (new MgCatalogo())->modalidades(true);
$tieneCarnet = trim((string) ($perfil['carnet_identidad'] ?? '')) !== '';

require dirname(__DIR__, 2) . '/views/layouts/header.php';
if (!$habilitado) {
    ?>
<main class="container narrow-wide">
    <section class="card">
        <h1>Solicitar modalidad de grado</h1>
        <p class="alert" role="alert">La solicitud de modalidad de grado se habilita desde el <strong>semestre <?= MgSolicitud::semestreMinimo() ?></strong>. Tu cuenta figura en el semestre <?= (int) $perfil['semestre'] ?>. Si tu semestre está desactualizado, pide a la Coordinación que lo corrija.</p>
        <p><a class="button secondary" href="<?= e(app_url('dashboard.php')) ?>">Volver al inicio</a></p>
    </section>
</main>
<?php
    require dirname(__DIR__, 2) . '/views/layouts/footer.php';
    exit;
}
?>
<main class="container narrow-wide">
    <section class="card">
        <h1><?= $idCorreccion !== null ? 'Corregir mi solicitud' : 'Solicitar modalidad de grado' ?></h1>
        <p class="panel-note">La Coordinación revisará tu documento y te avisará en el sistema si aprueba, observa o rechaza tu solicitud. Al aprobarla se abre tu expediente de grado.</p>
        <?php if ($idCorreccion !== null && $abierta['motivo_revision']): ?>
            <p class="alert" role="alert"><strong>Observación de la Coordinación:</strong> <?= e($abierta['motivo_revision']) ?></p>
        <?php endif; ?>
        <?php if ($errors): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <h2 class="mg-subtitulo">Tus datos</h2>
        <ul class="mg-lista">
            <li><strong>Nombre:</strong> <?= e(trim($perfil['nombre'] . ' ' . $perfil['apellido'])) ?></li>
            <li><strong>R.U.:</strong> <?= e((string) $perfil['registro_universitario']) ?> · <strong>Carrera:</strong> <?= e($perfil['carrera']) ?> · <strong>Semestre:</strong> <?= (int) $perfil['semestre'] ?></li>
            <?php if ($tieneCarnet): ?><li><strong>Carnet de identidad:</strong> <?= e((string) $perfil['carnet_identidad']) ?></li><?php endif; ?>
        </ul>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php if (!$tieneCarnet): ?>
                <label for="carnet_identidad">Carnet de identidad</label>
                <input id="carnet_identidad" name="carnet_identidad" type="text" maxlength="20" required value="<?= e($data['carnet_identidad']) ?>" placeholder="Ej.: 1234567 LP">
                <p class="panel-note">Tu cuenta aún no tiene carnet registrado; la Coordinación lo usa para identificarte.</p>
            <?php endif; ?>
            <fieldset class="mg-situacion">
                <legend>¿Cuál es tu situación?</legend>
                <?php foreach (['cursando_ultimo' => 'Estoy cursando mi último semestre', 'egresado' => 'Ya egresé'] as $valor => $texto): ?>
                    <label><input type="radio" name="situacion" value="<?= e($valor) ?>" required <?= $data['situacion'] === $valor ? 'checked' : '' ?>> <?= e($texto) ?></label>
                <?php endforeach; ?>
                <p class="panel-note">Si cursas el último semestre, adjunta tu record de notas hasta hoy: podrás empezar por la etapa previa (talleres) mientras terminas. Si ya egresaste, adjunta tu certificado de notas completo. La Coordinación verifica el documento y decide en qué etapa comienzas.</p>
            </fieldset>
            <label for="id_modalidad">Modalidad de grado</label>
            <select id="id_modalidad" name="id_modalidad" required>
                <option value="">Selecciona…</option>
                <?php foreach ($modalidades as $item): ?><option value="<?= (int) $item['id_modalidad'] ?>" <?= (int) $data['id_modalidad'] === (int) $item['id_modalidad'] ? 'selected' : '' ?>><?= e($item['nombre']) ?></option><?php endforeach; ?>
            </select>
            <label for="titulo_propuesto">Tema o título tentativo (opcional)</label>
            <input id="titulo_propuesto" name="titulo_propuesto" type="text" maxlength="255" value="<?= e((string) $data['titulo_propuesto']) ?>">
            <label for="documento">Record académico o certificado de notas (foto o PDF)<?= $idCorreccion !== null ? ' — súbelo solo si quieres reemplazar el anterior' : '' ?></label>
            <input id="documento" name="documento" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" <?= $idCorreccion === null ? 'required' : '' ?>>
            <p class="panel-note">Debe mostrar tus materias y notas. PDF, JPG, PNG o WEBP, hasta 8 MB. Solo lo ve la Coordinación.</p>
            <?php if ($idCorreccion !== null): ?><p class="panel-note">Documento actual: <?= e($abierta['documento_nombre']) ?> (<?= e(mg_tamano_legible((int) $abierta['documento_tamano'])) ?>).</p><?php endif; ?>
            <label for="mensaje">Mensaje para la Coordinación (opcional)</label>
            <textarea id="mensaje" name="mensaje" maxlength="1000" rows="3"><?= e((string) $data['mensaje']) ?></textarea>
            <button type="submit"><?= $idCorreccion !== null ? 'Reenviar solicitud' : 'Enviar solicitud' ?></button>
            <a class="button secondary" href="<?= e(app_url('mg/mi-modalidad.php')) ?>">Cancelar</a>
        </form>
    </section>
</main>
<?php require dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>
