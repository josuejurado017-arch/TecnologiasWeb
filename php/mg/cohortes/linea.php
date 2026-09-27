<?php

// Linea de tiempo de una cohorte (HU-036): hitos del calendario y defensas, con
// semaforo (cumplido / vencido / proximo / programado) y cumplimiento por hito.

require dirname(__DIR__, 3) . '/includes/bootstrap.php';
Auth::requireAction('mg.ver');
$activePage = 'mg-cohortes';

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$catalogo = new MgCatalogo();
$cohorte = $id ? $catalogo->cohorte($id) : null;
if ($cohorte === null) {
    http_response_code(404);
    exit('Cohorte no encontrada.');
}

$hoy = date('Y-m-d');
$diasProximo = (int) MgParametro::entero('dias_hito_proximo', 14);
$pdo = Database::connection();

// Universo: expedientes activos o aprobados de modalidades con tutor (flujo perfil MG1 -> MG2).
$universo = array_values(array_filter((new MgExpediente())->listar(['id_cohorte' => $id]),
    static fn (array $e): bool => (int) $e['requiere_tutor'] === 1 && in_array($e['estado'], ['activo', 'aprobado'], true)));
$ids = array_map(static fn (array $e): int => (int) $e['id_expediente'], $universo);
$agrupar = static function (string $sql) use ($pdo, $ids): array {
    if (!$ids) {
        return [];
    }
    $statement = $pdo->prepare(str_replace('{ids}', implode(',', array_fill(0, count($ids), '?')), $sql));
    $statement->execute($ids);

    return $statement->fetchAll();
};
$conTutor = array_flip(array_map('intval', array_column($agrupar('SELECT DISTINCT id_expediente FROM asignaciones_tutor_mg WHERE id_expediente IN ({ids})'), 'id_expediente')));
$tribunales = [];
foreach ($agrupar("SELECT id_expediente, etapa, COUNT(*) AS total FROM tribunales_mg WHERE estado = 'vigente' AND id_expediente IN ({ids}) GROUP BY id_expediente, etapa") as $fila) {
    $tribunales[$fila['etapa']][(int) $fila['id_expediente']] = (int) $fila['total'];
}
$defendidos = [];
foreach ($agrupar("SELECT DISTINCT id_expediente, etapa FROM defensas_mg WHERE estado = 'realizada' AND id_expediente IN ({ids})") as $fila) {
    $defendidos[$fila['etapa']][(int) $fila['id_expediente']] = true;
}
$informes = [];
foreach ($agrupar('SELECT id_expediente, id_hito, fecha_presentacion FROM informes_avance_mg WHERE id_expediente IN ({ids})') as $fila) {
    $informes[(int) $fila['id_expediente']][(int) $fila['id_hito']] = $fila;
}
$rango = ['previa' => 0, 'mg1' => 1, 'mg2' => 2, 'finalizado' => 3];

/** Cumplimiento del hito: [cumplidos, total] o null si el tipo no se mide con datos. */
$cumplimiento = static function (array $hito) use ($universo, $conTutor, $tribunales, $defendidos, $informes, $rango, $hoy): ?array {
    $total = 0;
    $hechos = 0;
    foreach ($universo as $exp) {
        $expId = (int) $exp['id_expediente'];
        switch ($hito['tipo']) {
            case 'asignacion_tutor':
                $total++;
                $hechos += isset($conTutor[$expId]) ? 1 : 0;
                break;
            case 'asignacion_tribunal':
                if ($hito['etapa'] === 'previa') {
                    return null;
                }
                $total++;
                $requeridos = (int) MgParametro::entero('tribunales_por_defensa_' . $hito['etapa'], 2);
                $hechos += ($tribunales[$hito['etapa']][$expId] ?? 0) >= $requeridos || isset($defendidos[$hito['etapa']][$expId]) ? 1 : 0;
                break;
            case 'defensa':
                if ($hito['etapa'] === 'previa') {
                    return null;
                }
                $total++;
                $hechos += isset($defendidos[$hito['etapa']][$expId]) ? 1 : 0;
                break;
            case 'ingreso_mg2':
                $total++;
                $hechos += $rango[$exp['etapa_actual']] >= 2 ? 1 : 0;
                break;
            case 'informe':
                $estado = MgInforme::estado($hito, $informes[$expId][(int) $hito['id_hito']] ?? null, $exp, $hoy);
                if ($estado !== 'no_aplica') {
                    $total++;
                    $hechos += in_array($estado, ['presentado', 'tarde'], true) ? 1 : 0;
                }
                break;
            default:
                return null;
        }
    }

    return [$hechos, $total];
};

$eventos = [];
foreach ($catalogo->hitos($id) as $hito) {
    $medida = $cumplimiento($hito);
    $dias = (int) floor((strtotime((string) $hito['fecha_limite']) - strtotime($hoy)) / 86400);
    if ($medida !== null && $medida[1] > 0 && $medida[0] === $medida[1]) {
        $semaforo = 'cumplido';
    } elseif ($dias < 0) {
        $semaforo = $medida === null ? 'pasado' : 'vencido';
    } elseif ($dias <= $diasProximo) {
        $semaforo = 'proximo';
    } else {
        $semaforo = 'programado';
    }
    $eventos[] = ['fecha' => $hito['fecha_limite'], 'orden' => 0, 'tipo' => MgCatalogo::TIPOS_HITO[$hito['tipo']] ?? $hito['tipo'],
        'etapa' => MgCatalogo::ETAPAS_HITO[$hito['etapa']] ?? $hito['etapa'], 'nombre' => $hito['nombre'], 'semaforo' => $semaforo,
        'medida' => $medida, 'dias' => $dias, 'avance' => $hito['avance_esperado_pct'], 'url' => null];
}
$defensas = $pdo->prepare(
    "SELECT d.id_defensa, d.id_expediente, d.etapa, d.fecha, d.hora_inicio, d.ambiente, d.estado, CONCAT(u.nombre, ' ', u.apellido) AS estudiante
     FROM defensas_mg d INNER JOIN expedientes_mg e ON e.id_expediente = d.id_expediente
     INNER JOIN estudiantes es ON es.id_estudiante = e.id_estudiante INNER JOIN usuarios u ON u.id_usuario = es.id_usuario
     WHERE e.id_cohorte = :id AND d.estado IN ('programada','realizada') ORDER BY d.fecha, d.hora_inicio"
);
$defensas->execute(['id' => $id]);
foreach ($defensas->fetchAll() as $defensa) {
    $dias = (int) floor((strtotime((string) $defensa['fecha']) - strtotime($hoy)) / 86400);
    $eventos[] = ['fecha' => $defensa['fecha'], 'orden' => 1, 'tipo' => 'Defensa ' . ($defensa['estado'] === 'realizada' ? 'realizada' : 'programada'), 'etapa' => MgTribunal::ETAPAS[$defensa['etapa']],
        'nombre' => $defensa['estudiante'] . ' · ' . substr((string) $defensa['hora_inicio'], 0, 5) . ' · ' . $defensa['ambiente'],
        'semaforo' => $defensa['estado'] === 'realizada' ? 'cumplido' : ($dias < 0 ? 'vencido' : ($dias <= $diasProximo ? 'proximo' : 'programado')),
        'medida' => null, 'dias' => $dias, 'avance' => null, 'url' => 'mg/expedientes/ver.php?id=' . (int) $defensa['id_expediente']];
}
usort($eventos, static fn (array $a, array $b): int => [$a['fecha'], $a['orden']] <=> [$b['fecha'], $b['orden']]);
$semaforos = [
    'cumplido' => ['Cumplido', 'badge-success'],
    'vencido' => ['Vencido', 'badge-danger'],
    'proximo' => ['Próximo', 'badge-warning'],
    'programado' => ['Programado', 'badge-info'],
    'pasado' => ['Pasado', 'badge-neutral'],
];
$conteo = array_count_values(array_column($eventos, 'semaforo'));
$title = 'Línea de tiempo · ' . $cohorte['nombre'];

require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="page-heading">
        <div>
            <h1><?= e($cohorte['nombre']) ?></h1>
            <p>Línea de tiempo: talleres, informes, tribunales y defensas. <?= count($universo) ?> expedientes con tutor (activos o aprobados) cuentan para el cumplimiento.</p>
        </div>
        <div class="page-heading-actions">
            <a class="button secondary" href="<?= e(app_url('mg/cohortes/calendario.php?id=' . $id)) ?>">Editar calendario</a>
            <a class="button secondary" href="<?= e(app_url('mg/cohortes/')) ?>">Volver a cohortes</a>
        </div>
    </div>

    <p class="mg-leyenda">
        <?php foreach ($semaforos as $clave => [$label, $clase]): ?><span class="badge <?= e($clase) ?>"><?= e($label) ?> · <?= (int) ($conteo[$clave] ?? 0) ?></span> <?php endforeach; ?>
        <small>Próximo = faltan <?= $diasProximo ?> días o menos.</small>
    </p>

    <section class="card">
        <?php if (!$eventos): ?>
            <p class="empty-state">La cohorte no tiene hitos ni defensas. <a href="<?= e(app_url('mg/cohortes/calendario.php?id=' . $id)) ?>">Carga el calendario</a>.</p>
        <?php else: ?>
            <ol class="mg-linea">
                <?php $hoyMarcado = false; ?>
                <?php foreach ($eventos as $evento): ?>
                    <?php if (!$hoyMarcado && $evento['fecha'] >= $hoy): $hoyMarcado = true; ?>
                        <li class="mg-linea-hoy"><strong>Hoy · <?= e(mg_fecha_corta($hoy)) ?></strong></li>
                    <?php endif; ?>
                    <li class="mg-linea-<?= e($evento['semaforo']) ?>">
                        <div class="mg-linea-fecha"><?= e(mg_fecha_corta($evento['fecha'])) ?></div>
                        <div>
                            <strong><?= $evento['url'] ? '<a href="' . e(app_url($evento['url'])) . '">' . e($evento['nombre']) . '</a>' : e($evento['nombre']) ?></strong>
                            <span class="badge <?= e($semaforos[$evento['semaforo']][1]) ?>"><?= e($semaforos[$evento['semaforo']][0]) ?></span>
                            <br><small><?= e($evento['etapa']) ?> · <?= e($evento['tipo']) ?><?= $evento['avance'] !== null ? ' · avance esperado ~' . (int) $evento['avance'] . '%' : '' ?>
                                <?php if ($evento['medida'] !== null): ?> · <?= (int) $evento['medida'][0] ?> de <?= (int) $evento['medida'][1] ?> expedientes<?php endif; ?>
                                <?php if ($evento['semaforo'] !== 'cumplido'): ?> · <?= $evento['dias'] < 0 ? 'hace ' . abs($evento['dias']) . ' días' : ($evento['dias'] === 0 ? 'hoy' : 'en ' . $evento['dias'] . ' días') ?><?php endif; ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if (!$hoyMarcado): ?><li class="mg-linea-hoy"><strong>Hoy · <?= e(mg_fecha_corta($hoy)) ?></strong></li><?php endif; ?>
            </ol>
        <?php endif; ?>
    </section>
</main>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
