<?php

// Panel de alertas de Modalidades de Grado (HU-038). Se calculan al abrir la
// pagina (sin cron) y no cambian datos: solo avisan. Se marcan atendidas con nota.

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
Auth::requireAction('mg.alertas');
$title = 'Alertas de grado';
$activePage = 'mg-alertas';

$texto = static fn (string $clave): string => is_string($_REQUEST[$clave] ?? null) ? trim($_REQUEST[$clave]) : '';
$filtros = [
    'ver' => in_array($texto('ver'), ['abiertas', 'atendidas', 'todas'], true) ? $texto('ver') : 'abiertas',
    'severidad' => isset(MgAlerta::SEVERIDADES[$texto('severidad')]) ? $texto('severidad') : '',
    'codigo' => isset(MgAlerta::CODIGOS[$texto('codigo')]) ? $texto('codigo') : '',
    'id_cohorte' => (int) filter_var($_REQUEST['id_cohorte'] ?? 0, FILTER_VALIDATE_INT),
    'q' => mb_substr($texto('q'), 0, 80),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mg_require_post();
    $error = (new MgSeguimientoController())->atenderAlerta($texto('clave'), (string) ($_POST['nota'] ?? ''), (int) Auth::user()['id_usuario']);
    mg_redirect('alertas.php?' . http_build_query(array_filter($filtros)), $error ? ['error' => $error] : ['message' => 'atendida']);
}

$todas = (new MgAlerta())->calcular();
$abiertas = array_filter($todas, static fn (array $a): bool => $a['atencion'] === null);
$alertas = array_filter($todas, static function (array $a) use ($filtros): bool {
    if ($filtros['ver'] === 'abiertas' && $a['atencion'] !== null) {
        return false;
    }
    if ($filtros['ver'] === 'atendidas' && $a['atencion'] === null) {
        return false;
    }
    if ($filtros['severidad'] !== '' && $a['severidad'] !== $filtros['severidad']) {
        return false;
    }
    if ($filtros['codigo'] !== '' && $a['codigo'] !== $filtros['codigo']) {
        return false;
    }
    if ($filtros['id_cohorte'] && $a['id_cohorte'] !== $filtros['id_cohorte']) {
        return false;
    }

    return $filtros['q'] === '' || mb_stripos(($a['estudiante'] ?? '') . ' ' . $a['detalle'], $filtros['q']) !== false;
});
$porSeveridad = array_count_values(array_column($abiertas, 'severidad'));
$porCodigo = array_count_values(array_column($abiertas, 'codigo'));
$cohortes = (new MgCatalogo())->cohortes();
[$message, $error] = mg_flash(['atendida' => 'Alerta marcada como atendida. Si la situación cambia, volverá a aparecer.']);
$query = http_build_query(array_filter($filtros));

require dirname(__DIR__, 2) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1>Alertas</h1>
            <p>Se calculan al abrir esta página con los datos del momento. Ninguna cambia el estado de un expediente: la decisión es de la Coordinación (RN-MG-22).</p>
        </div>
    </div>

    <?php if (!empty($message)): ?><p class="success" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>

    <section class="stat-grid" aria-label="Alertas abiertas por severidad">
        <article class="stat-card stat-card-purple"><div class="stat-card-top"><span class="stat-label">Abiertas</span><span class="stat-icon">AL</span></div><strong class="stat-value"><?= count($abiertas) ?></strong><span class="stat-caption"><?= count($todas) - count($abiertas) ?> atendidas vigentes</span></article>
        <?php foreach (['alta' => 'stat-card-gold', 'media' => 'stat-card-teal', 'baja' => 'stat-card-blue'] as $severidad => $clase): ?>
            <article class="stat-card <?= e($clase) ?>"><div class="stat-card-top"><span class="stat-label">Severidad <?= e(mb_strtolower(MgAlerta::SEVERIDADES[$severidad])) ?></span><span class="stat-icon"><?= e(mb_strtoupper(mb_substr($severidad, 0, 2))) ?></span></div><strong class="stat-value"><?= (int) ($porSeveridad[$severidad] ?? 0) ?></strong><span class="stat-caption"><a href="<?= e(app_url('mg/alertas.php?severidad=' . $severidad)) ?>">Ver solo estas</a></span></article>
        <?php endforeach; ?>
    </section>

    <form method="get" class="card mg-filtros mg-seccion">
        <div><label for="ver">Mostrar</label><select id="ver" name="ver"><?php foreach (['abiertas' => 'Abiertas', 'atendidas' => 'Atendidas', 'todas' => 'Todas'] as $valor => $label): ?><option value="<?= e($valor) ?>" <?= $filtros['ver'] === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div><label for="severidad">Severidad</label><select id="severidad" name="severidad"><option value="">Todas</option><?php foreach (MgAlerta::SEVERIDADES as $valor => $label): ?><option value="<?= e($valor) ?>" <?= $filtros['severidad'] === $valor ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div><label for="codigo">Tipo</label><select id="codigo" name="codigo"><option value="">Todos</option><?php foreach (MgAlerta::CODIGOS as $valor => $label): ?><option value="<?= e($valor) ?>" <?= $filtros['codigo'] === $valor ? 'selected' : '' ?>><?= e($valor . ' · ' . $label) ?> (<?= (int) ($porCodigo[$valor] ?? 0) ?>)</option><?php endforeach; ?></select></div>
        <div><label for="id_cohorte">Cohorte</label><select id="id_cohorte" name="id_cohorte"><option value="">Todas</option><?php foreach ($cohortes as $item): ?><option value="<?= (int) $item['id_cohorte'] ?>" <?= $filtros['id_cohorte'] === (int) $item['id_cohorte'] ? 'selected' : '' ?>><?= e($item['nombre']) ?></option><?php endforeach; ?></select></div>
        <div><label for="q">Buscar</label><input id="q" name="q" type="search" maxlength="80" value="<?= e($filtros['q']) ?>" placeholder="Estudiante o detalle"></div>
        <div class="mg-filtros-acciones"><button type="submit">Filtrar</button><a class="button secondary" href="<?= e(app_url('mg/alertas.php')) ?>">Limpiar</a></div>
    </form>

    <div class="table-wrapper card">
        <table>
            <thead><tr><th>Severidad</th><th>Alerta</th><th>Estudiante</th><th>Detalle</th><th>Atención</th></tr></thead>
            <tbody>
                <?php foreach ($alertas as $alerta): ?>
                    <tr>
                        <td><?= MgAlerta::badge($alerta['severidad']) ?></td>
                        <td><strong><?= e($alerta['codigo']) ?></strong> · <?= e($alerta['titulo']) ?></td>
                        <td><?php if ($alerta['estudiante']): ?><a href="<?= e(app_url($alerta['url'])) ?>"><?= e($alerta['estudiante']) ?></a><?php else: ?><a href="<?= e(app_url($alerta['url'])) ?>">Ver tesistas</a><?php endif; ?></td>
                        <td><?= e($alerta['detalle']) ?></td>
                        <td>
                            <?php if ($alerta['atencion']): ?>
                                <span class="badge badge-success">Atendida</span><br><small><?= e((string) $alerta['atencion']['usuario']) ?> · <?= e(date('d/m/Y H:i', strtotime((string) $alerta['atencion']['fecha']))) ?><br><?= e($alerta['atencion']['nota']) ?></small>
                            <?php else: ?>
                                <details><summary>Marcar atendida</summary>
                                    <form method="post" action="<?= e(app_url('mg/alertas.php' . ($query !== '' ? '?' . $query : ''))) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="clave" value="<?= e($alerta['clave']) ?>">
                                        <label for="nota-<?= e(md5($alerta['clave'])) ?>">Qué se hizo</label>
                                        <textarea id="nota-<?= e(md5($alerta['clave'])) ?>" name="nota" rows="2" maxlength="500" required></textarea>
                                        <button class="small" type="submit">Guardar</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$alertas): ?><tr><td colspan="5" class="empty-state"><?= $filtros['ver'] === 'abiertas' && !$abiertas ? 'No hay alertas abiertas.' : 'No hay alertas con esos filtros.' ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <section class="card mg-seccion">
        <h2>Cómo se calculan</h2>
        <ul class="mg-lista">
            <li><strong>A1</strong> expediente en MG1/MG2 cuya modalidad requiere tutor y no lo tiene.</li>
            <li><strong>A2</strong> más de <?= (int) MgParametro::entero('dias_alerta_sin_reunion', 10) ?> días sin reuniones (parámetro <em>dias_alerta_sin_reunion</em>).</li>
            <li><strong>A3</strong> en MG1, menos de <?= (int) MgParametro::entero('reuniones_min_semana_perfil', 2) ?> reuniones con asistencia de ambos la semana pasada (las observadas no cuentan).</li>
            <li><strong>A4</strong> informe con fecha límite vencida y sin registrar · <strong>A5</strong> avance por debajo del esperado del hito · <strong>A6</strong> dos informes seguidos sin presentar.</li>
            <li><strong>A7</strong> defensa en menos de <?= (int) MgParametro::entero('dias_anticipacion_tribunal', 14) ?> días sin los tribunales completos · <strong>A9</strong> defensa programada sin citaciones (alta a <?= (int) MgParametro::entero('dias_alerta_citaciones', 3) ?> días o menos).</li>
            <li><strong>A8</strong> tutor con más de <?= (int) MgParametro::entero('tutor_carga_recomendada', 3) ?> tesistas vigentes (C-01: no es un límite).</li>
        </ul>
        <p class="panel-note">Los umbrales se cambian en <?= Auth::canDo('mg.parametros') ? '<a href="' . e(app_url('mg/parametros.php')) . '">Parámetros</a>' : 'Parámetros (Coordinación)' ?>. El aviso por correo con un proceso programado queda para P3.</p>
    </section>
</main>
<?php require dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>
