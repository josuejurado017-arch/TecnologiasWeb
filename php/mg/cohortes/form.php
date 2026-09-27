<?php

// Alta y edicion de cohortes (HU-022).

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.catalogo');
$activePage = 'mg-cohortes';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$catalogo = new MgCatalogo();
$data = ['codigo' => '', 'nombre' => '', 'fecha_inicio' => '', 'fecha_fin' => ''];
if ($id !== null) {
    $data = $catalogo->cohorte($id);
    if ($data === null) {
        http_response_code(404);
        exit('Cohorte no encontrada.');
    }
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    [$data, $errors] = (new MgCatalogoController())->guardarCohorte($id, $_POST);
    if (!$errors) {
        mg_redirect('cohortes/', ['message' => $id === null ? 'creada' : 'actualizada']);
    }
}

$title = $id === null ? 'Nueva cohorte' : 'Editar cohorte';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container narrow">
    <section class="card">
        <h1><?= e($title) ?></h1>
        <?php if ($errors): ?><div class="alert" role="alert"><ul><?php foreach ($errors as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label for="codigo">Código</label>
            <input id="codigo" name="codigo" type="text" maxlength="30" required value="<?= e($data['codigo'] ?? '') ?>" placeholder="Ej. G1-2026-03">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" type="text" maxlength="120" required value="<?= e($data['nombre'] ?? '') ?>" placeholder="Ej. Grupo 1 - Marzo 2026">
            <div class="form-grid">
                <div>
                    <label for="fecha_inicio">Fecha de inicio</label>
                    <input id="fecha_inicio" name="fecha_inicio" type="date" required value="<?= e($data['fecha_inicio'] ?? '') ?>">
                </div>
                <div>
                    <label for="fecha_fin">Fecha de fin (opcional)</label>
                    <input id="fecha_fin" name="fecha_fin" type="date" value="<?= e($data['fecha_fin'] ?? '') ?>">
                </div>
            </div>
            <button type="submit">Guardar</button>
            <a class="button secondary" href="<?= e(app_url('mg/cohortes/')) ?>">Cancelar</a>
        </form>
    </section>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
