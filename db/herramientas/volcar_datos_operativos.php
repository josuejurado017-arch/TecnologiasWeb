<?php
/**
 * Arma db/038_datos_operativos.sql desde la base de laboratorio que dejo
 * generar_datos_operativos.php: TRUNCATE de los datos de dominio + INSERTs.
 * Usa PDO (no mysqldump) para funcionar igual en Windows, Ubuntu y Docker.
 *
 * Uso (desde la raiz del proyecto):
 *   DB_NAME=seedlab DB_USER=root DB_PASSWORD= php db/herramientas/volcar_datos_operativos.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || in_array(getenv('DB_NAME') ?: 'testdb', ['testdb', ''], true)) {
    fwrite(STDERR, "Indica DB_NAME de la base de laboratorio (no testdb).\n");
    exit(1);
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$pdo = Database::connection();
$destino = dirname(__DIR__) . '/038_datos_operativos.sql';

// Orden de carga: padres antes que hijos (igual se desactivan las FK durante la carga).
$tablas = [
    'carreras', 'materias', 'periodos', 'periodo_observaciones', 'usuarios', 'tutores', 'tutor_estado_historial', 'estudiantes',
    'tutor_materia', 'tutor_materia_config', 'tutor_materia_turno', 'tutor_materia_historial',
    'disponibilidad_tutor', 'disponibilidad_intervenciones',
    'grupos_tutoria', 'grupo_dias', 'grupo_ubicacion_historial', 'grupo_rechazos', 'historial_grupo',
    'inscripciones', 'sesiones_tutoria', 'asistencias_sesion', 'evaluaciones_grupo',
    'demanda_tutoria', 'notificaciones', 'registro_accesos',
];

$sql = <<<'SQL'
-- Datos operativos realistas del programa de tutorias UPDS (Santa Cruz), al 22/09/2026.
-- Reemplaza los datos de 027 y 036: aquellos se generaron con reglas anteriores
-- (varias tutorias por estudiante, tutores sin tope de grupos, periodos de un
-- semestre entero).
--
-- Generado ejecutando el flujo REAL del sistema (db/herramientas/generar_datos_operativos.php):
-- ofertas aprobadas por la coordinacion, motor de asignacion y aprobacion de cada
-- grupo con su aula o enlace. Cumple por construccion:
--   * quorum (db/035): con 1 o 2 estudiantes en un turno no hay grupo (interes
--     registrado); el grupo se forma al reunir el minimo del periodo (3);
--   * una sola tutoria por periodo para cada estudiante (db/037);
--   * como maximo 2 grupos por tutor, siempre en turnos distintos (db/037);
--   * frecuencia por demanda: LMV o MJS con menos de 8 estudiantes, Lunes a
--     Viernes desde 8 (automatico al aprobar);
--   * ningun grupo aprobado sin aula (presencial) o enlace (virtual); el espacio
--     se calcula de la modalidad y del enlace;
--   * sesiones solo desde la aprobacion; en curso al llegar la primera sesion.
--
-- Calendario UPDS: la tutoria es el ultimo mes del semestre.
--   * Tutorias Julio 2026, Gestion I (cerrado): grupos finalizados, asistencia
--     completa y evaluaciones.
--   * Tutorias Enero 2027, Gestion II (activo, inscripciones abiertas desde
--     septiembre): grupos confirmados con su calendario de enero, grupos por
--     aprobar (en formacion o listos para revision) e interes registrado
--     (esperando companeros / sin tutor / sin horario). Aun sin asistencia.
--   * Tutorias Julio 2027, Gestion I (borrador).
--
-- Credenciales (todas las cuentas): contrasena Upds2026*
--   Administracion : msuarez (coordinacion de tutorias), ranez
--   Tutores        : mrojas, pcespedes, rjustiniano, vsalvatierra, dpaz (sin horarios) ...
--   Estudiantes    : usuario = parte local del correo @est.upds.edu.bo
--
-- ATENCION: reemplaza TODOS los datos de dominio (conserva roles y espacios de tutoria).
-- Compatible con MySQL 8.4 y MariaDB 10.4.
-- Ejecutar despues de 037_carga_por_periodo.sql sobre testdb.

USE testdb;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

SQL;

foreach (array_reverse($tablas) as $t) {
    $sql .= "TRUNCATE TABLE $t;\n";
}
$sql .= "\n";

$total = 0;
foreach ($tablas as $t) {
    $filas = $pdo->query("SELECT * FROM $t")->fetchAll(PDO::FETCH_ASSOC);
    if (!$filas) {
        continue;
    }
    $columnas = '(' . implode(', ', array_map(static fn (string $c): string => "`$c`", array_keys($filas[0]))) . ')';
    foreach (array_chunk($filas, 200) as $lote) {
        $valores = array_map(static function (array $fila) use ($pdo): string {
            return '(' . implode(', ', array_map(static fn ($v): string => $v === null ? 'NULL' : $pdo->quote((string) $v), $fila)) . ')';
        }, $lote);
        $sql .= "INSERT INTO $t $columnas VALUES\n" . implode(",\n", $valores) . ";\n";
    }
    $sql .= "\n";
    $total += count($filas);
    printf("%-30s %d\n", $t, count($filas));
}

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
file_put_contents($destino, $sql);
printf("%d filas -> %s (%.1f MB)\n", $total, $destino, filesize($destino) / 1048576);
