<?php
/**
 * Reprocesa la demanda en espera de los periodos activos (uno por tipo de
 * tutoria, db/043) materia por materia.
 *
 * Pensado para correr una vez despues de db/029: antes, parte de la demanda
 * "sin_horario" se debia a que no habia aula libre; ahora el motor ya no depende
 * de aulas y puede ubicar a esos estudiantes. Los grupos nuevos nacen "por
 * aprobar", asi que la coordinacion los revisa antes de que empiecen.
 *
 * Uso (desde la raiz del proyecto):
 *   php db/herramientas/reprocesar_demanda.php              -> solo muestra la cola
 *   php db/herramientas/reprocesar_demanda.php --confirmar  -> reprocesa
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$periodos = (new Periodo())->activas();
if ($periodos === []) {
    fwrite(STDERR, "No hay periodos activos.\n");
    exit(1);
}
$confirmar = in_array('--confirmar', $argv, true);
$motor = new AsignacionController();

foreach ($periodos as $periodo) {
    $pendientes = Database::connection()->prepare(
        "SELECT d.id_materia, m.nombre_materia, COUNT(*) AS en_espera,
                SUM(d.motivo = 'sin_horario') AS sin_horario
         FROM demanda_tutoria d INNER JOIN materias m ON m.id_materia = d.id_materia
         WHERE d.id_periodo = :id_periodo AND d.estado = 'pendiente'
         GROUP BY d.id_materia, m.nombre_materia
         ORDER BY m.nombre_materia"
    );
    $pendientes->execute(['id_periodo' => $periodo['id_periodo']]);
    $materias = $pendientes->fetchAll();

    echo "\nPeriodo: " . $periodo['nombre'] . ' (' . $periodo['tipo_nombre'] . ")\n";
    if (!$confirmar) {
        foreach ($materias as $m) {
            printf("  %-40s en espera: %3d (sin horario: %d)\n", $m['nombre_materia'], $m['en_espera'], $m['sin_horario']);
        }
        continue;
    }

    $total = ['atendidos' => 0, 'pendientes' => 0];
    foreach ($materias as $m) {
        $r = $motor->reprocesarMateria((int) $m['id_materia'], $periodo);
        $total['atendidos'] += $r['atendidos'];
        $total['pendientes'] += $r['pendientes'];
        printf("  %-40s atendidos: %3d  siguen en espera: %3d\n", $m['nombre_materia'], $r['atendidos'], $r['pendientes']);
    }
    printf("\nTotal atendidos: %d · siguen en espera: %d\n", $total['atendidos'], $total['pendientes']);
}

if (!$confirmar) {
    echo "\nSin cambios. Pasa --confirmar para reprocesar (crea grupos por aprobar y notifica).\n";
}
