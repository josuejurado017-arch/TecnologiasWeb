<?php
/**
 * Datos de prueba de Modalidades de Grado (MG) sobre testdb, "como si el sistema
 * ya estuviera en funcionamiento" al 27/09/2026.
 *
 * ADITIVO: no toca tutorias ni cuentas existentes. Solo vacia las tablas de MG
 * (expedientes, cohortes, reuniones, defensas, documentos, bitacora...) y borra las
 * cuentas que este mismo script crea (egresantes con expediente MG y sin datos de
 * tutorias, mas la Coordinacion y el auxiliar de MG). Se puede volver a correr.
 *
 * Escenario (UPDS Santa Cruz, calendario de cohortes):
 *   * Cohorte Febrero 2026 (cerrada): aprobados, un reprobado en MG1, un abandono
 *     en MG2, un retiro en etapa previa, un cambio de tutor por renuncia, Examen de
 *     Grado y Graduacion por Excelencia aprobados.
 *   * Cohorte Junio 2026 (en MG2): informes al dia, con bajo avance, sin informe y
 *     en riesgo de abandono; una defensa de MG1 reprogramada para el 30/09 con
 *     tribunal incompleto y sin citaciones; una realizada sin nota.
 *   * Cohorte Septiembre 2026 (en MG1, importada del padron CSV): reuniones de la
 *     semana, informe del 25/09, pocas reuniones, renuncia de tutor sin reemplazo,
 *     expedientes aun en etapa previa y defensas de MG1 ya programadas.
 *   Con esto aparecen las alertas A1-A9 del panel.
 *
 * Credenciales nuevas (contrasena Upds2026*): coordinacion.mg (coordinador_mg),
 * auxiliar.mg (auxiliar_mg) y los estudiantes con expediente (usuario = parte
 * local del correo). Los tutores son los docentes ya existentes.
 *
 * Uso (desde la raiz del proyecto, con respaldo previo de testdb):
 *   C:\xampp\php\php.exe db/herramientas/generar_datos_mg.php --confirmar
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !in_array('--confirmar', $argv, true)) {
    fwrite(STDERR, "Reemplaza los datos de Modalidades de Grado de la base configurada. Pasa --confirmar.\n");
    exit(1);
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';

mt_srand(20260927);
const HOY = '2026-09-27';
const PASSWORD = 'Upds2026*';

$pdo = Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function q(string $sql, array $p = []): PDOStatement
{
    $s = Database::connection()->prepare($sql);
    $s->execute($p);
    return $s;
}
function chance(float $p): bool { return mt_rand() / mt_getrandmax() < $p; }
function pick(array $a) { return $a[array_rand($a)]; }
function dias(string $fecha, int $n): string { return date('Y-m-d', strtotime($fecha . ' ' . ($n >= 0 ? '+' : '') . $n . ' days')); }
function dt(string $fecha, string $hora = ''): string
{
    return $fecha . ' ' . ($hora !== '' ? $hora : sprintf('%02d:%02d:%02d', mt_rand(8, 18), mt_rand(0, 59), mt_rand(0, 59)));
}
function sin_tildes(string $s): string
{
    return strtolower(strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ñ'=>'n','ü'=>'u',' '=>'']));
}

// ---------------------------------------------------------------------------
// 0. Limpieza de lo que genera este script (solo MG).
// ---------------------------------------------------------------------------
$previos = q(
    "SELECT DISTINCT es.id_usuario FROM expedientes_mg e INNER JOIN estudiantes es ON es.id_estudiante = e.id_estudiante
     WHERE NOT EXISTS (SELECT 1 FROM inscripciones i WHERE i.id_estudiante = es.id_estudiante)
       AND NOT EXISTS (SELECT 1 FROM demanda_tutoria d WHERE d.id_estudiante = es.id_estudiante)"
)->fetchAll(PDO::FETCH_COLUMN);
$previos = array_merge($previos, q("SELECT id_usuario FROM usuarios WHERE usuario IN ('coordinacion.mg', 'auxiliar.mg')")->fetchAll(PDO::FETCH_COLUMN));

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['alertas_atendidas_mg', 'informes_avance_mg', 'reuniones_mg', 'calificaciones_mg', 'documentos_generados_mg', 'defensas_mg',
          'tribunales_mg', 'asignaciones_tutor_mg', 'importaciones_mg_detalle', 'importaciones_mg', 'expediente_etapas_mg',
          'expedientes_mg', 'calendario_mg', 'cohortes_mg', 'contadores_documento_mg', 'bitacora_mg'] as $t) {
    $pdo->exec("DELETE FROM $t");
    $pdo->exec("ALTER TABLE $t AUTO_INCREMENT = 1");
}
$pdo->exec("DELETE FROM notificaciones WHERE tipo LIKE 'mg\\_%'");
if ($previos) {
    $in = implode(',', array_map('intval', $previos));
    $pdo->exec("DELETE FROM registro_accesos WHERE id_usuario IN ($in)");
    $pdo->exec("DELETE FROM notificaciones WHERE id_usuario IN ($in)");
    $pdo->exec("DELETE FROM estudiantes WHERE id_usuario IN ($in)");
    $pdo->exec("DELETE FROM usuarios WHERE id_usuario IN ($in)");
}
$pdo->exec("UPDATE parametros_mg SET actualizado_por = NULL, fecha_actualizacion = NULL");
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$rol = array_column(q('SELECT id_rol, nombre_rol FROM roles')->fetchAll(), 'id_rol', 'nombre_rol');
$hash = password_hash(PASSWORD, PASSWORD_DEFAULT);

// ---------------------------------------------------------------------------
// 1. Cuentas: Coordinacion de MG, auxiliar y egresantes.
// ---------------------------------------------------------------------------
$usados = array_flip(q('SELECT usuario FROM usuarios')->fetchAll(PDO::FETCH_COLUMN));
$cis = array_flip(array_filter(q('SELECT carnet_identidad FROM usuarios')->fetchAll(PDO::FETCH_COLUMN)));
$rus = array_flip(array_filter(q('SELECT registro_universitario FROM estudiantes')->fetchAll(PDO::FETCH_COLUMN)));

function ci_unico(array &$cis): string
{
    do {
        $ci = (string) mt_rand(5200000, 9899999);
    } while (isset($cis[$ci]));
    $cis[$ci] = true;
    return $ci;
}

function crear_usuario(int $rolId, string $nombre, string $apellido, string $usuario, string $correo, string $registro): int
{
    global $hash, $cis;
    q('INSERT INTO usuarios (id_rol, nombre, apellido, correo, usuario, contrasena_hash, telefono, carnet_identidad, estado, fecha_registro)
       VALUES (:r, :n, :a, :c, :u, :h, :t, :ci, "activo", :f)', [
        'r' => $rolId, 'n' => $nombre, 'a' => $apellido, 'c' => $correo, 'u' => $usuario, 'h' => $hash,
        't' => '7' . mt_rand(1000000, 9999999), 'ci' => ci_unico($cis), 'f' => $registro,
    ]);
    return (int) Database::connection()->lastInsertId();
}

$coord = crear_usuario((int) $rol['coordinador_mg'], 'Hugo Alberto', 'Salazar Rojas', 'coordinacion.mg', 'hugo.salazar@upds.edu.bo', '2026-01-19 09:12:00');
$aux = crear_usuario((int) $rol['auxiliar_mg'], 'Andrea', 'Flores Molina', 'auxiliar.mg', 'andrea.flores@upds.edu.bo', '2026-01-20 10:30:00');

$nombresF = ['María José', 'Valeria', 'Camila', 'Daniela', 'Andrea', 'Fernanda', 'Gabriela', 'Natalia', 'Paola', 'Carolina', 'Mariana', 'Alejandra',
             'Sofía', 'Lucía', 'Ximena', 'Adriana', 'Claudia', 'Rocío', 'Jimena', 'Karla', 'Estefanía', 'Melany'];
$nombresM = ['José Luis', 'Carlos', 'Luis Miguel', 'Diego', 'Mauricio', 'Juan Pablo', 'Rodrigo', 'Álvaro', 'Sergio', 'Andrés', 'Gonzalo', 'Marcelo',
             'Ricardo', 'Fabricio', 'Ignacio', 'Óscar', 'Jhonatan', 'Brayan', 'Kevin', 'Pablo', 'Rubén', 'Wilson'];
$apellidos = ['Añez', 'Suárez', 'Justiniano', 'Vaca', 'Rivero', 'Saucedo', 'Chávez', 'Roca', 'Mercado', 'Paz', 'Salvatierra', 'Cuéllar',
              'Égüez', 'Antelo', 'Terrazas', 'Parada', 'Moreno', 'Montaño', 'Arteaga', 'Aguilera', 'Soliz', 'Rojas', 'Flores', 'Guzmán',
              'Pinto', 'Ribera', 'Zabala', 'Ortiz', 'Lijerón', 'Menacho', 'Hurtado', 'Villarroel', 'Durán', 'Céspedes', 'Gutiérrez', 'Peña'];

/** Crea un egresante (semestre 10) con su cuenta de estudiante. */
function crear_egresante(int $carrera, string $registro): array
{
    global $rol, $usados, $rus, $nombresF, $nombresM, $apellidos;
    $femenino = chance(0.5);
    $nombre = pick($femenino ? $nombresF : $nombresM);
    $ap1 = pick($apellidos);
    do { $ap2 = pick($apellidos); } while ($ap2 === $ap1);
    $base = sin_tildes(explode(' ', $nombre)[0]) . '.' . sin_tildes($ap1);
    $usuario = $base;
    for ($i = 2; isset($usados[$usuario]); $i++) {
        $usuario = $base . $i;
    }
    $usados[$usuario] = true;
    do {
        $ru = (string) (mt_rand(2020, 2021) * 100000 + mt_rand(10000, 99999));
    } while (isset($rus[$ru]));
    $rus[$ru] = true;

    $userId = crear_usuario((int) $rol['estudiante'], $nombre, $ap1 . ' ' . $ap2, $usuario, $usuario . '@est.upds.edu.bo', $registro);
    q('INSERT INTO estudiantes (id_usuario, id_carrera, semestre, registro_universitario) VALUES (:u, :c, 10, :ru)', ['u' => $userId, 'c' => $carrera, 'ru' => $ru]);

    return ['id_estudiante' => (int) Database::connection()->lastInsertId(), 'id_usuario' => $userId, 'nombre' => $nombre . ' ' . $ap1 . ' ' . $ap2,
            'usuario' => $usuario, 'ru' => $ru, 'carrera' => $carrera];
}

// ---------------------------------------------------------------------------
// 2. Helpers del flujo (mismas escrituras que los controladores de MG, con fecha).
// ---------------------------------------------------------------------------
$docentes = [];
foreach (q("SELECT t.id_tutor, u.id_usuario, CONCAT(u.nombre, ' ', u.apellido) AS nombre FROM tutores t
            INNER JOIN usuarios u ON u.id_usuario = t.id_usuario WHERE u.estado = 'activo' AND t.estado_docente = 'aprobado'")->fetchAll() as $d) {
    $docentes[(int) $d['id_tutor']] = $d;
}
// Afinidad por carrera (ids de tutores existentes). El primero de cada lista recibe mas tesistas.
const POOL = [
    1 => [2, 13, 3, 17, 4, 1],   // Ingenieria de Sistemas
    2 => [7, 8, 12],             // Ingenieria Comercial
    3 => [12, 8, 7],             // Administracion de Empresas
    4 => [6, 15, 7],             // Contaduria Publica
    5 => [9, 10],                // Derecho
    6 => [11, 14, 19],           // Psicologia
];
const TRIBUNAL_EXTRA = [1, 7, 14, 12];

function bit(string $accion, string $tabla, $id, ?array $antes, ?array $despues, string $fecha, int $userId): void
{
    q('INSERT INTO bitacora_mg (id_usuario, accion, tabla, id_registro, datos_antes, datos_despues, ip, fecha) VALUES (:u, :a, :t, :id, :an, :de, :ip, :f)', [
        'u' => $userId, 'a' => $accion, 't' => $tabla, 'id' => (string) $id,
        'an' => $antes !== null ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
        'de' => $despues !== null ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
        'ip' => '192.168.10.' . mt_rand(20, 60), 'f' => strlen($fecha) === 10 ? dt($fecha) : $fecha,
    ]);
}

function notificar(int $userId, string $tipo, string $titulo, string $mensaje, string $url, string $clave, string $fecha): void
{
    $cuando = strlen($fecha) === 10 ? dt($fecha) : $fecha;
    $leida = $cuando < dias(HOY, -3) . ' 00:00:00' || chance(0.3);
    q('INSERT IGNORE INTO notificaciones (id_usuario, tipo, titulo, mensaje, url, clave_evento, leida, fecha_creacion, fecha_lectura)
       VALUES (:u, :t, :ti, :m, :url, :c, :l, :f, :fl)', [
        'u' => $userId, 't' => $tipo, 'ti' => $titulo, 'm' => $mensaje, 'url' => $url, 'c' => $clave, 'l' => $leida ? 1 : 0,
        'f' => $cuando, 'fl' => $leida ? date('Y-m-d H:i:s', strtotime($cuando) + mt_rand(1800, 3 * 86400)) : null,
    ]);
}

$modalidad = array_column(q('SELECT id_modalidad, codigo FROM modalidades_grado')->fetchAll(), 'id_modalidad', 'codigo');

function crear_expediente(array $est, string $codigoModalidad, int $cohorte, string $fecha, ?string $titulo, string $origen, int $userId): int
{
    global $modalidad;
    q('INSERT INTO expedientes_mg (id_estudiante, id_modalidad, id_cohorte, etapa_actual, titulo_trabajo, fecha_inicio, origen, registrado_por, fecha_registro)
       VALUES (:e, :m, :c, "previa", :t, :f, :o, :u, :fr)', [
        'e' => $est['id_estudiante'], 'm' => $modalidad[$codigoModalidad], 'c' => $cohorte, 't' => $titulo, 'f' => $fecha,
        'o' => $origen, 'u' => $userId, 'fr' => dt($fecha),
    ]);
    $id = (int) Database::connection()->lastInsertId();
    q('INSERT INTO expediente_etapas_mg (id_expediente, etapa, fecha_inicio, registrado_por, fecha_registro) VALUES (:id, "previa", :f, :u, :fr)',
        ['id' => $id, 'f' => $fecha, 'u' => $userId, 'fr' => dt($fecha)]);
    if ($origen === 'manual') {
        bit('expediente_creado', 'expedientes_mg', $id, null, ['id_estudiante' => $est['id_estudiante'], 'id_modalidad' => $modalidad[$codigoModalidad],
            'id_cohorte' => $cohorte, 'etapa_actual' => 'previa', 'titulo_trabajo' => $titulo, 'fecha_inicio' => $fecha], $fecha, $userId);
    }
    return $id;
}

function cambiar_etapa(int $id, string $nueva, string $fecha, ?string $resultado, int $userId, bool $bitacora = true): void
{
    $antes = q('SELECT etapa_actual FROM expedientes_mg WHERE id_expediente = :id', ['id' => $id])->fetchColumn();
    q('UPDATE expediente_etapas_mg SET fecha_fin = :f, resultado = :r WHERE id_expediente = :id AND fecha_fin IS NULL', ['f' => $fecha, 'r' => $resultado, 'id' => $id]);
    q('UPDATE expedientes_mg SET etapa_actual = :e WHERE id_expediente = :id', ['e' => $nueva, 'id' => $id]);
    q('INSERT INTO expediente_etapas_mg (id_expediente, etapa, fecha_inicio, registrado_por, fecha_registro) VALUES (:id, :e, :f, :u, :fr)',
        ['id' => $id, 'e' => $nueva, 'f' => $fecha, 'u' => $userId, 'fr' => dt($fecha)]);
    if ($bitacora) {
        bit('expediente_etapa', 'expedientes_mg', $id, ['etapa' => $antes], ['etapa' => $nueva, 'resultado' => (string) $resultado], $fecha, $userId);
    }
}

/** Cierra el expediente (aprobado, reprobado, abandono, retirado) como MgExpedientesController::cambiarEstado. */
function cerrar_expediente(int $id, string $estado, string $fecha, string $motivo, int $userId): void
{
    $actual = q('SELECT estado, etapa_actual FROM expedientes_mg WHERE id_expediente = :id', ['id' => $id])->fetch();
    $texto = MgExpediente::ESTADOS[$estado] . ' (' . date('d/m/Y', strtotime($fecha)) . '): ' . $motivo;
    q('UPDATE expedientes_mg SET estado = :e, fecha_cierre = :f, observaciones = CONCAT_WS(:sep, observaciones, :m) WHERE id_expediente = :id',
        ['e' => $estado, 'f' => $fecha, 'sep' => "\n", 'm' => $texto, 'id' => $id]);
    q('UPDATE expediente_etapas_mg SET fecha_fin = :f, resultado = :r WHERE id_expediente = :id AND fecha_fin IS NULL',
        ['f' => $fecha, 'r' => mb_substr(MgExpediente::ESTADOS[$estado] . ': ' . $motivo, 0, 255), 'id' => $id]);
    q("UPDATE asignaciones_tutor_mg SET estado = 'finalizada', fecha_fin = :f, motivo_fin = :m WHERE id_expediente = :id AND estado = 'vigente'",
        ['f' => $fecha, 'm' => 'Expediente ' . mb_strtolower(MgExpediente::ESTADOS[$estado]), 'id' => $id]);
    if ($estado === 'aprobado' && $actual['etapa_actual'] !== 'finalizado') {
        q("UPDATE expedientes_mg SET etapa_actual = 'finalizado' WHERE id_expediente = :id", ['id' => $id]);
        q("INSERT INTO expediente_etapas_mg (id_expediente, etapa, fecha_inicio, fecha_fin, resultado, registrado_por, fecha_registro)
           VALUES (:id, 'finalizado', :f, :f2, 'Aprobado', :u, :fr)", ['id' => $id, 'f' => $fecha, 'f2' => $fecha, 'u' => $userId, 'fr' => dt($fecha)]);
    }
    bit('expediente_estado', 'expedientes_mg', $id, ['estado' => $actual['estado']], ['estado' => $estado, 'motivo' => $motivo], $fecha, $userId);
}

/** Valores comunes de los documentos (MgDocumentosController::variablesExpediente) con la fecha de emision. */
function variables_documento(int $expedienteId, string $fecha): array
{
    $e = (new MgExpediente())->find($expedienteId);
    return [
        'ciudad' => MgParametro::texto('institucion_ciudad', 'Santa Cruz de la Sierra'),
        'firma' => MgParametro::texto('firma_coordinacion', 'Coordinación de Modalidades de Grado'),
        'fecha_larga' => MgDocumento::fechaLarga($fecha),
        'estudiante_nombre' => $e['estudiante'], 'registro_universitario' => $e['registro_universitario'], 'carrera' => $e['carrera'],
        'modalidad' => $e['modalidad'], 'tema' => $e['titulo_trabajo'], 'cohorte' => $e['cohorte'], 'tutor_nombre' => $e['tutor'],
    ];
}

function documento(string $codigo, int $expedienteId, ?int $asignacion, ?int $defensa, string $destinatario, array $hojas, array $valores, string $fecha, int $userId): void
{
    $modelo = new MgDocumento();
    $plantilla = $modelo->plantilla($codigo);
    $pdo = Database::connection();
    $numero = $modelo->siguienteNumero($pdo, $plantilla['prefijo'], (int) substr($fecha, 0, 4));
    $html = [];
    foreach ($hojas as $para) {
        $html[] = MgDocumento::render($codigo, $plantilla['cuerpo_html'], ['numero' => $numero, 'destinatario_nombre' => $para] + $valores);
    }
    q('INSERT INTO documentos_generados_mg (id_plantilla, version_plantilla, codigo, id_expediente, id_asignacion, id_defensa, destinatario, numero, contenido_snapshot, generado_por, fecha_generacion)
       VALUES (:p, :v, :c, :e, :a, :d, :dest, :n, :s, :u, :f)', [
        'p' => $plantilla['id_plantilla'], 'v' => $plantilla['version'], 'c' => $codigo, 'e' => $expedienteId, 'a' => $asignacion, 'd' => $defensa,
        'dest' => mb_substr($destinatario, 0, 200), 'n' => $numero, 's' => implode('<div class="doc-salto"></div>', $html), 'u' => $userId,
        'f' => dt($fecha),
    ]);
}

$refDecanatura = 0;
/** Asigna tutor (o lo cambia) con carta, notificaciones y bitacora. Desde previa pasa a MG1. */
function asignar_tutor(int $exp, int $tutor, string $fecha, int $userId, ?string $motivoCambio = null, ?string $fechaNota = null): int
{
    global $refDecanatura, $docentes;
    $refDecanatura++;
    $ref = sprintf('Nota DEC-FCE/%03d/%s', $refDecanatura + 40, substr($fecha, 0, 4));
    $e = q('SELECT e.etapa_actual, es.id_usuario, CONCAT(u.nombre, " ", u.apellido) AS estudiante, m.nombre AS modalidad
            FROM expedientes_mg e INNER JOIN estudiantes es ON es.id_estudiante = e.id_estudiante INNER JOIN usuarios u ON u.id_usuario = es.id_usuario
            INNER JOIN modalidades_grado m ON m.id_modalidad = e.id_modalidad WHERE e.id_expediente = :id', ['id' => $exp])->fetch();
    $actual = q("SELECT * FROM asignaciones_tutor_mg WHERE id_expediente = :id AND estado = 'vigente'", ['id' => $exp])->fetch() ?: null;
    if ($actual) {
        q("UPDATE asignaciones_tutor_mg SET estado = 'reemplazada', fecha_fin = :f, motivo_fin = :m, fecha_nota_renuncia = :n WHERE id_asignacion = :id",
            ['f' => $fecha, 'm' => $motivoCambio, 'n' => $fechaNota, 'id' => $actual['id_asignacion']]);
    }
    q('INSERT INTO asignaciones_tutor_mg (id_expediente, id_tutor, fecha_asignacion, referencia_decanatura, disponibilidad_consultada, observaciones, registrado_por, fecha_registro)
       VALUES (:e, :t, :f, :r, 1, :o, :u, :fr)', [
        'e' => $exp, 't' => $tutor, 'f' => $fecha, 'r' => $ref, 'o' => chance(0.25) ? 'Afinidad revisada por Decanatura con el perfil del docente.' : null,
        'u' => $userId, 'fr' => dt($fecha),
    ]);
    $id = (int) Database::connection()->lastInsertId();
    if ($e['etapa_actual'] === 'previa') {
        cambiar_etapa($exp, 'mg1', $fecha, 'Tutor asignado', $userId, false);
    }
    bit($actual ? 'tutor_cambiado' : 'tutor_asignado', 'asignaciones_tutor_mg', $id,
        $actual ? ['id_asignacion' => (int) $actual['id_asignacion'], 'id_tutor' => (int) $actual['id_tutor'], 'motivo' => $motivoCambio] : null,
        ['id_tutor' => $tutor, 'fecha' => $fecha, 'referencia_decanatura' => $ref, 'avisos' => []], $fecha, $userId);
    $valores = variables_documento($exp, $fecha);
    $valores['tutor_nombre'] = $docentes[$tutor]['nombre'];
    $valores['referencia_decanatura'] = $ref;
    documento('CARTA_ASIGNACION_TUTOR', $exp, $id, null, $docentes[$tutor]['nombre'] . ' / ' . $e['estudiante'],
        [$docentes[$tutor]['nombre'], $e['estudiante']], $valores, $fecha, $userId);
    notificar((int) $docentes[$tutor]['id_usuario'], 'mg_tutor_asignado', 'Nuevo tesista de Modalidades de Grado',
        'Se te asignó como tutor de ' . $e['estudiante'] . ' (' . $e['modalidad'] . ').', 'mg/mis-tesistas.php', 'mg_asignacion_' . $id . '_tutor', $fecha);
    notificar((int) $e['id_usuario'], 'mg_tutor_asignado', 'Tutor de Modalidades de Grado',
        'Tu tutor(a) es ' . $docentes[$tutor]['nombre'] . '.', 'mg/mi-modalidad.php', 'mg_asignacion_' . $id . '_estudiante', $fecha);
    if ($actual) {
        notificar((int) $docentes[(int) $actual['id_tutor']]['id_usuario'], 'mg_tutor_reemplazado', 'Cambio de tutor en Modalidades de Grado',
            'Dejas de ser tutor de ' . $e['estudiante'] . '.', 'mg/mis-tesistas.php', 'mg_asignacion_' . $actual['id_asignacion'] . '_fin', $fecha);
    }
    return $id;
}

function tribunales(int $exp, string $etapa, array $ids, string $fecha, int $userId): void
{
    foreach (array_values($ids) as $i => $tutor) {
        q('INSERT INTO tribunales_mg (id_expediente, etapa, id_tutor, orden, fecha_asignacion, registrado_por, fecha_registro) VALUES (:e, :et, :t, :o, :f, :u, :fr)',
            ['e' => $exp, 'et' => $etapa, 't' => $tutor, 'o' => $i + 1, 'f' => $fecha, 'u' => $userId, 'fr' => dt($fecha)]);
        bit('tribunal_asignado', 'tribunales_mg', Database::connection()->lastInsertId(), null,
            ['etapa' => $etapa, 'orden' => $i + 1, 'id_tutor' => $tutor, 'motivo' => ''], $fecha, $userId);
    }
}

/** Elige tribunales de la carrera distintos del tutor. */
function elegir_tribunales(int $carrera, ?int $tutor, int $n = 2): array
{
    $pool = array_values(array_diff(array_unique(array_merge(POOL[$carrera], TRIBUNAL_EXTRA)), [$tutor]));
    $afines = array_values(array_diff(POOL[$carrera], [$tutor]));
    shuffle($afines);
    $elegidos = array_slice($afines, 0, $n);
    while (count($elegidos) < $n) {
        $candidato = pick($pool);
        if (!in_array($candidato, $elegidos, true)) {
            $elegidos[] = $candidato;
        }
    }
    return $elegidos;
}

const AMBIENTES = ['Sala de Defensas 1 (Bloque A)', 'Sala de Defensas 2 (Bloque A)', 'Auditorio Bloque C'];
const HORARIOS = [['08:30', '10:00'], ['10:15', '11:45'], ['14:30', '16:00'], ['16:15', '17:45']];

function defensa(int $exp, string $etapa, string $fecha, int $slot, string $estado, string $registro, int $userId, array $extra = []): int
{
    [$inicio, $fin] = HORARIOS[$slot % 4];
    $ambiente = AMBIENTES[intdiv($slot, 4) % 3];
    q('INSERT INTO defensas_mg (id_expediente, etapa, fecha, hora_inicio, hora_fin, ambiente, estado, motivo_estado, autorizado_por, referencia_autorizacion,
                                obs_fondo, obs_forma, id_defensa_anterior, registrado_por, fecha_registro)
       VALUES (:e, :et, :f, :hi, :hf, :a, :es, :mo, :au, :ra, :of, :ofo, :ant, :u, :fr)', [
        'e' => $exp, 'et' => $etapa, 'f' => $fecha, 'hi' => $inicio, 'hf' => $fin, 'a' => $ambiente, 'es' => $estado,
        'mo' => $extra['motivo'] ?? null, 'au' => $extra['autorizado_por'] ?? null, 'ra' => $extra['referencia'] ?? null,
        'of' => $extra['obs_fondo'] ?? null, 'ofo' => $extra['obs_forma'] ?? null, 'ant' => $extra['anterior'] ?? null,
        'u' => $userId, 'fr' => dt($registro),
    ]);
    $id = (int) Database::connection()->lastInsertId();
    bit(isset($extra['anterior']) ? 'defensa_reprogramada' : 'defensa_programada', 'defensas_mg', $id,
        isset($extra['anterior']) ? ['id_defensa' => $extra['anterior']] : null,
        ['fecha' => $fecha, 'hora_inicio' => $inicio, 'hora_fin' => $fin, 'ambiente' => $ambiente, 'etapa' => $etapa], $registro, $userId);
    return $id;
}

function citaciones(int $defensaId, string $fecha, int $userId): void
{
    $d = (new MgDefensa())->find($defensaId);
    $tribunalesVig = (new MgTribunal())->vigentes((int) $d['id_expediente'], (string) $d['etapa']);
    $valores = variables_documento((int) $d['id_expediente'], $fecha) + [
        'etapa' => MgTribunal::ETAPAS[$d['etapa']], 'fecha_defensa' => MgDocumento::fechaLarga((string) $d['fecha']),
        'hora_inicio' => substr((string) $d['hora_inicio'], 0, 5), 'hora_fin' => substr((string) $d['hora_fin'], 0, 5),
        'ambiente' => $d['ambiente'], 'tribunales' => implode(', ', array_column($tribunalesVig, 'docente')),
    ];
    foreach ($tribunalesVig as $t) {
        documento('CITACION_TRIBUNAL', (int) $d['id_expediente'], null, $defensaId, $t['docente'], [$t['docente']], $valores, $fecha, $userId);
    }
    documento('CITACION_ESTUDIANTE', (int) $d['id_expediente'], null, $defensaId, $d['estudiante'], [$d['estudiante']], $valores, $fecha, $userId);
}

function realizar(int $defensaId, string $fecha, int $userId): void
{
    $fondo = pick(['Delimitar mejor el alcance y los objetivos específicos.', 'Fortalecer el marco teórico con fuentes recientes.',
                   'Justificar la muestra y el instrumento de recolección.', 'Precisar la metodología y el cronograma.', null]);
    $forma = pick(['Ajustar citas y referencias a normas APA 7.', 'Revisar redacción y numeración de figuras.', 'Unificar formato de tablas.', null]);
    q("UPDATE defensas_mg SET estado = 'realizada', obs_fondo = :f, obs_forma = :fo WHERE id_defensa = :id", ['f' => $fondo, 'fo' => $forma, 'id' => $defensaId]);
    bit('defensa_realizada', 'defensas_mg', $defensaId, ['estado' => 'programada'], ['estado' => 'realizada', 'motivo' => ''], $fecha . ' 18:30:00', $userId);
}

function calificar(int $defensaId, float $nota, string $fecha, int $userId, bool $publicada = true): void
{
    $obs = $nota >= 51 ? pick(['Defensa sólida.', 'Buen dominio del tema.', 'Aprobado con observaciones menores.', null]) : 'No sustentó los objetivos planteados.';
    q('INSERT INTO calificaciones_mg (id_defensa, nota, observaciones, publicada, registrada_por, fecha_registro) VALUES (:d, :n, :o, :p, :u, :f)',
        ['d' => $defensaId, 'n' => $nota, 'o' => $obs, 'p' => $publicada ? 1 : 0, 'u' => $userId, 'f' => dt($fecha)]);
    bit('nota_registrada', 'calificaciones_mg', $defensaId, null, ['nota' => $nota, 'publicada' => $publicada ? 1 : 0, 'observaciones' => $obs, 'motivo' => ''], $fecha, $userId);
}

const TEMAS_MG1 = ['Revisión del planteamiento del problema', 'Objetivos general y específicos', 'Justificación y alcance', 'Marco teórico: antecedentes',
                   'Marco teórico: bases conceptuales', 'Diseño metodológico', 'Instrumentos de recolección de datos', 'Cronograma y presupuesto',
                   'Revisión integral del perfil', 'Preparación de la defensa del perfil'];
const TEMAS_MG2 = ['Avance del diagnóstico', 'Análisis de datos recolectados', 'Desarrollo del capítulo de propuesta', 'Revisión de resultados',
                   'Validación de la propuesta', 'Conclusiones y recomendaciones', 'Revisión de observaciones del tribunal de MG1', 'Redacción del informe final',
                   'Revisión de formato y referencias', 'Preparación de la defensa final'];

$slotTutor = [];
/**
 * Reuniones entre dos fechas: en MG1 dos por semana, en MG2 una. Validadas si
 * tienen mas de una semana; las recientes quedan por validar.
 */
function reuniones(int $exp, int $asig, int $tutor, int $estudiante, string $desde, string $hasta, int $porSemana, string $etapa, float $falta = 0.06): void
{
    global $slotTutor, $docentes, $coord;
    $clave = $tutor . ':' . $exp;
    if (!isset($slotTutor[$clave])) {
        $usadas = count(array_filter(array_keys($slotTutor), static fn ($k) => str_starts_with((string) $k, $tutor . ':')));
        $slotTutor[$clave] = $usadas;
    }
    $slot = $slotTutor[$clave];
    $horas = [['08:00', '09:00'], ['10:00', '11:00'], ['14:00', '15:00'], ['16:00', '17:00'], ['18:30', '19:30'], ['11:15', '12:15']][$slot % 6];
    $diasSemana = $porSemana >= 2 ? ($slot % 2 === 0 ? [1, 4] : [2, 5]) : [($slot % 5) + 1];
    $virtual = chance(0.4);
    $n = 0;
    for ($f = $desde; $f <= $hasta; $f = dias($f, 1)) {
        if (!in_array((int) date('N', strtotime($f)), $diasSemana, true)) {
            continue;
        }
        if ($f . ' ' . $horas[1] . ':00' > HOY . ' 00:00:00') {
            break;
        }
        $n++;
        $temas = $etapa === 'mg1' ? TEMAS_MG1 : TEMAS_MG2;
        $tema = $temas[min(count($temas) - 1, intdiv($n - 1, $porSemana >= 2 ? 2 : 1) % count($temas))];
        $noEst = chance($falta);
        $esVirtual = $virtual ? chance(0.8) : chance(0.15);
        $antigua = $f < dias(HOY, -7);
        $estado = 'registrada';
        $motivo = null;
        if ($antigua) {
            $estado = chance(0.03) ? 'observada' : 'validada';
            $motivo = $estado === 'observada' ? 'La hora registrada no coincide con el reporte del estudiante; confirmar y corregir.' : null;
        }
        q('INSERT INTO reuniones_mg (id_expediente, id_asignacion, fecha, hora_inicio, hora_fin, modalidad, lugar_o_enlace, temas, avance_sesion, observaciones,
                                     asistio_estudiante, asistio_tutor, estado_validacion, motivo_observacion, registrada_por, fecha_registro, validada_por, fecha_validacion)
           VALUES (:e, :a, :f, :hi, :hf, :m, :l, :t, :av, :o, :ae, "si", :est, :mo, :rp, :fr, :vp, :fv)', [
            'e' => $exp, 'a' => $asig, 'f' => $f, 'hi' => $horas[0], 'hf' => $horas[1], 'm' => $esVirtual ? 'virtual' : 'presencial',
            'l' => $esVirtual ? 'https://teams.microsoft.com/l/meetup-join/19%3amg' . $exp . 'x' . $tutor : pick(['Cubículo docente, Bloque B', 'Sala de tutores, Bloque A', 'Biblioteca, sala de estudio 2']),
            't' => $tema, 'av' => $noEst ? null : pick(['Se corrigió la sección revisada.', 'Avance conforme a lo planificado.', 'Quedaron tareas para la próxima sesión.', 'Se revisaron las observaciones pendientes.']),
            'o' => $noEst ? 'El estudiante no asistió; se reprogramó el tema.' : null, 'ae' => $noEst ? 'no' : 'si', 'est' => $estado, 'mo' => $motivo,
            'rp' => $docentes[$tutor]['id_usuario'], 'fr' => $f . ' ' . $horas[1] . ':' . sprintf('%02d', mt_rand(5, 50)) . ':00',
            'vp' => $antigua ? $coord : null, 'fv' => $antigua ? dt(dias($f, mt_rand(2, 5))) : null,
        ]);
        if ($estado === 'observada') {
            bit('reunion_observada', 'reuniones_mg', Database::connection()->lastInsertId(), ['estado' => 'registrada'], ['estado' => 'observada', 'motivo' => $motivo], dias($f, 3), $coord);
        }
    }
}

function informe(int $exp, int $hito, int $pct, string $fecha, ?int $tutor, int $userId, string $formato = 'digital'): void
{
    global $docentes;
    $registra = $tutor !== null && chance(0.7) ? (int) $docentes[$tutor]['id_usuario'] : $userId;
    q('INSERT INTO informes_avance_mg (id_expediente, id_hito, porcentaje_avance, fecha_presentacion, formato, respaldo_fisico, presentado_por, observaciones, registrado_por, fecha_registro)
       VALUES (:e, :h, :p, :f, :fo, :r, :t, :o, :u, :fr)', [
        'e' => $exp, 'h' => $hito, 'p' => $pct, 'f' => $fecha, 'fo' => $formato, 'r' => $formato === 'fisico' || chance(0.3) ? 1 : 0, 't' => $tutor,
        'o' => $pct < 40 ? 'Avance menor al esperado; el tutor propone un plan de recuperación.' : null, 'u' => $registra, 'fr' => dt($fecha),
    ]);
    bit('informe_registrado', 'informes_avance_mg', Database::connection()->lastInsertId(), null, ['porcentaje_avance' => $pct, 'fecha_presentacion' => $fecha], $fecha, $registra);
}

// ---------------------------------------------------------------------------
// 3. Parametros ajustados por la Coordinacion.
// ---------------------------------------------------------------------------
q("UPDATE parametros_mg SET valor = 'Santa Cruz de la Sierra', actualizado_por = :u, fecha_actualizacion = '2026-01-22 11:05:00' WHERE clave = 'institucion_ciudad'", ['u' => $coord]);
bit('parametro_actualizado', 'parametros_mg', 'institucion_ciudad', ['valor' => 'Tarija'], ['valor' => 'Santa Cruz de la Sierra'], '2026-01-22 11:05:00', $coord);
q("UPDATE parametros_mg SET valor = 'Ing. Hugo A. Salazar Rojas — Coordinación de Modalidades de Grado', actualizado_por = :u, fecha_actualizacion = '2026-01-22 11:07:00' WHERE clave = 'firma_coordinacion'", ['u' => $coord]);
bit('parametro_actualizado', 'parametros_mg', 'firma_coordinacion', ['valor' => 'Coordinación de Modalidades de Grado'], ['valor' => 'Ing. Hugo A. Salazar Rojas — Coordinación de Modalidades de Grado'], '2026-01-22 11:07:00', $coord);

// ---------------------------------------------------------------------------
// 4. Cohortes y calendario.
// ---------------------------------------------------------------------------
$hitos = [];
function cohorte(string $codigo, string $nombre, string $inicio, string $fin, bool $activa, array $calendario, int $userId): int
{
    global $hitos;
    q('INSERT INTO cohortes_mg (codigo, nombre, fecha_inicio, fecha_fin, activa, fecha_registro) VALUES (:c, :n, :i, :f, :a, :fr)',
        ['c' => $codigo, 'n' => $nombre, 'i' => $inicio, 'f' => $fin, 'a' => $activa ? 1 : 0, 'fr' => dt(dias($inicio, -20))]);
    $id = (int) Database::connection()->lastInsertId();
    $orden = [];
    foreach ($calendario as $clave => [$etapa, $tipo, $nombreHito, $fecha, $pct]) {
        $orden[$etapa] = ($orden[$etapa] ?? 0) + 1;
        q('INSERT INTO calendario_mg (id_cohorte, etapa, tipo, nombre, orden, fecha_limite, avance_esperado_pct) VALUES (:c, :e, :t, :n, :o, :f, :p)',
            ['c' => $id, 'e' => $etapa, 't' => $tipo, 'n' => $nombreHito, 'o' => $orden[$etapa], 'f' => $fecha, 'p' => $pct]);
        $hitos[$id][$clave] = (int) Database::connection()->lastInsertId();
    }
    return $id;
}

$c1 = cohorte('MG-2026-1', 'Cohorte Febrero 2026', '2026-02-02', '2026-08-28', false, [
    'taller' => ['previa', 'taller', 'Taller de inducción a Modalidades de Grado', '2026-02-06', null],
    'tutor' => ['previa', 'asignacion_tutor', 'Asignación de tutores', '2026-02-13', null],
    'inf1' => ['mg1', 'informe', 'Informe de avance del perfil', '2026-03-13', 50],
    'trib1' => ['mg1', 'asignacion_tribunal', 'Asignación de tribunales de MG1', '2026-03-20', null],
    'def1' => ['mg1', 'defensa', 'Defensa del perfil (MG1)', '2026-04-03', null],
    'mg2' => ['mg2', 'ingreso_mg2', 'Ingreso a MG2', '2026-04-10', null],
    'inf2' => ['mg2', 'informe', 'Primer informe de avance de MG2', '2026-05-08', 30],
    'inf3' => ['mg2', 'informe', 'Segundo informe de avance de MG2', '2026-06-05', 55],
    'inf4' => ['mg2', 'informe', 'Tercer informe de avance de MG2', '2026-07-03', 80],
    'trib2' => ['mg2', 'asignacion_tribunal', 'Asignación de tribunales de MG2', '2026-07-31', null],
    'def2' => ['mg2', 'defensa', 'Defensa final (MG2)', '2026-08-21', null],
], $coord);
$c2 = cohorte('MG-2026-2', 'Cohorte Junio 2026', '2026-06-01', '2026-12-18', true, [
    'taller' => ['previa', 'taller', 'Taller de inducción a Modalidades de Grado', '2026-06-05', null],
    'tutor' => ['previa', 'asignacion_tutor', 'Asignación de tutores', '2026-06-12', null],
    'inf1' => ['mg1', 'informe', 'Informe de avance del perfil', '2026-07-10', 50],
    'trib1' => ['mg1', 'asignacion_tribunal', 'Asignación de tribunales de MG1', '2026-07-17', null],
    'def1' => ['mg1', 'defensa', 'Defensa del perfil (MG1)', '2026-07-31', null],
    'mg2' => ['mg2', 'ingreso_mg2', 'Ingreso a MG2', '2026-08-07', null],
    'inf2' => ['mg2', 'informe', 'Primer informe de avance de MG2', '2026-08-31', 20],
    'inf3' => ['mg2', 'informe', 'Segundo informe de avance de MG2', '2026-09-25', 40],
    'inf4' => ['mg2', 'informe', 'Tercer informe de avance de MG2', '2026-10-30', 70],
    'inf5' => ['mg2', 'informe', 'Cuarto informe de avance de MG2', '2026-11-20', 95],
    'trib2' => ['mg2', 'asignacion_tribunal', 'Asignación de tribunales de MG2', '2026-11-27', null],
    'def2' => ['mg2', 'defensa', 'Defensa final (MG2)', '2026-12-11', null],
], $coord);
$c3 = cohorte('MG-2026-3', 'Cohorte Septiembre 2026', '2026-09-01', '2027-02-26', true, [
    'taller' => ['previa', 'taller', 'Taller de inducción a Modalidades de Grado', '2026-09-04', null],
    'tutor' => ['previa', 'asignacion_tutor', 'Asignación de tutores', '2026-09-11', null],
    'inf1' => ['mg1', 'informe', 'Primer informe de avance del perfil', '2026-09-25', 40],
    'inf1b' => ['mg1', 'informe', 'Segundo informe de avance del perfil', '2026-10-16', 80],
    'trib1' => ['mg1', 'asignacion_tribunal', 'Asignación de tribunales de MG1', '2026-10-16', null],
    'def1' => ['mg1', 'defensa', 'Defensa del perfil (MG1)', '2026-10-30', null],
    'mg2' => ['mg2', 'ingreso_mg2', 'Ingreso a MG2', '2026-11-06', null],
    'inf2' => ['mg2', 'informe', 'Primer informe de avance de MG2', '2026-12-04', 30],
    'inf3' => ['mg2', 'informe', 'Segundo informe de avance de MG2', '2027-01-15', 60],
    'inf4' => ['mg2', 'informe', 'Tercer informe de avance de MG2', '2027-02-05', 90],
    'trib2' => ['mg2', 'asignacion_tribunal', 'Asignación de tribunales de MG2', '2027-02-12', null],
    'def2' => ['mg2', 'defensa', 'Defensa final (MG2)', '2027-02-26', null],
], $coord);
bit('cohorte_creada', 'cohortes_mg', $c3, null, ['codigo' => 'MG-2026-3', 'nombre' => 'Cohorte Septiembre 2026'], '2026-08-12 09:40:00', $coord);

// ---------------------------------------------------------------------------
// 5. Titulos por carrera.
// ---------------------------------------------------------------------------
$titulos = [
    1 => ['Sistema web de gestión de inventarios para ferreterías del Plan 3000', 'Aplicación móvil de seguimiento de pedidos para microempresas de delivery',
          'Plataforma de turnos médicos en línea para centros de salud de primer nivel', 'Sistema de control de asistencia con reconocimiento facial para colegios',
          'Chatbot de atención al estudiante para la UPDS', 'Sistema de trazabilidad de ganado bovino con códigos QR',
          'Modelo de detección de fraude en transacciones con aprendizaje automático', 'Sistema de gestión documental para notarías de Santa Cruz',
          'Portal de empleo para egresados universitarios', 'Sistema de monitoreo de consumo eléctrico con IoT para viviendas',
          'Aplicación de reservas para canchas deportivas del Equipetrol', 'Sistema de facturación en línea integrado al SIN para pymes'],
    2 => ['Plan de marketing digital para una cadena de heladerías cruceñas', 'Estudio de mercado para una tienda de productos orgánicos en Santa Cruz',
          'Estrategia de fidelización de clientes para una distribuidora de alimentos', 'Plan de exportación de chía a mercados asiáticos'],
    3 => ['Plan de negocios para un centro de acopio de soya en San Julián', 'Modelo de gestión del talento humano para una empresa de transporte',
          'Plan estratégico para una cooperativa de ahorro y crédito', 'Propuesta de mejora de procesos en una clínica privada'],
    4 => ['Auditoría del ciclo de ingresos en una empresa comercial de Santa Cruz', 'Impacto de la facturación electrónica en las pymes cruceñas',
          'Sistema de costos por procesos para una planta de lácteos', 'Análisis de la carga tributaria en empresas unipersonales'],
    5 => ['La conciliación como medio alternativo en conflictos laborales', 'Protección de datos personales en el comercio electrónico boliviano',
          'Régimen jurídico de la propiedad agraria en el oriente boliviano', 'La violencia digital contra la mujer en la Ley 348'],
    6 => ['Ansiedad académica en estudiantes universitarios de primer año', 'Clima laboral y satisfacción en una empresa de servicios cruceña',
          'Programa de intervención en habilidades sociales para adolescentes', 'Resiliencia en cuidadores de adultos mayores'],
];
function titulo(int $carrera): string
{
    global $titulos;
    $i = array_rand($titulos[$carrera]);
    $t = $titulos[$carrera][$i];
    unset($titulos[$carrera][$i]);
    if (!$titulos[$carrera]) {
        $titulos[$carrera] = ['Propuesta de mejora continua en una organización cruceña', 'Estudio de caso en una institución de Santa Cruz'];
    }
    return $t;
}

$carga = [];
function tutor_para(int $carrera, ?int $preferido = null): int
{
    global $carga;
    if ($preferido !== null) {
        $carga[$preferido] = ($carga[$preferido] ?? 0) + 1;
        return $preferido;
    }
    $pool = POOL[$carrera];
    usort($pool, static fn ($a, $b) => ($carga[$a] ?? 0) <=> ($carga[$b] ?? 0));
    $carga[$pool[0]] = ($carga[$pool[0]] ?? 0) + 1;
    return $pool[0];
}

$slotDefensa = [];
function slot_defensa(string $fecha): int
{
    global $slotDefensa;
    $slotDefensa[$fecha] = ($slotDefensa[$fecha] ?? -1) + 1;
    return $slotDefensa[$fecha];
}
function fecha_defensa(string $desde, int $ventana): string
{
    do {
        $f = dias($desde, mt_rand(0, $ventana));
    } while ((int) date('N', strtotime($f)) >= 6);
    return $f;
}
function nota_aprobada(): float { return (float) mt_rand(58, 94) + (chance(0.3) ? 0.5 : 0); }

$credenciales = [];

// ---------------------------------------------------------------------------
// 6. Cohorte Febrero 2026 (cerrada).
// ---------------------------------------------------------------------------
$escenariosC1 = [
    [1, 'PROYECTO', 'aprobado'], [1, 'PROYECTO', 'aprobado'], [1, 'PROYECTO', 'cambio_tutor'], [1, 'TESIS', 'aprobado'],
    [4, 'TRABAJO_DIRIGIDO', 'aprobado'], [4, 'PROYECTO', 'aprobado'], [3, 'PROYECTO', 'aprobado'], [2, 'TESIS', 'reprobado_mg1'],
    [5, 'TESIS', 'aprobado'], [6, 'TESIS', 'abandono_mg2'], [6, 'PROYECTO', 'aprobado'], [3, 'TRABAJO_DIRIGIDO', 'retirado_previa'],
    [1, 'EXAMEN', 'examen'], [4, 'EXCELENCIA', 'excelencia'], [5, 'PROYECTO', 'aprobado'],
];
foreach ($escenariosC1 as [$carrera, $mod, $caso]) {
    $est = crear_egresante($carrera, dt('2021-02-' . sprintf('%02d', mt_rand(1, 26))));
    $conTutor = in_array($mod, ['PROYECTO', 'TESIS', 'TRABAJO_DIRIGIDO'], true);
    $exp = crear_expediente($est, $mod, $c1, '2026-02-02', $conTutor ? titulo($carrera) : null, 'manual', $aux);
    $credenciales[] = [$est['usuario'], 'Cohorte Febrero 2026', $caso];

    if (!$conTutor) {
        $motivo = $mod === 'EXAMEN' ? 'Aprobó el Examen de Grado por áreas con 78/100.' : 'Promedio general de 92,4: cumple el requisito de excelencia.';
        cerrar_expediente($exp, 'aprobado', $mod === 'EXAMEN' ? '2026-04-17' : '2026-03-06', $motivo, $coord);
        continue;
    }
    if ($caso === 'retirado_previa') {
        cerrar_expediente($exp, 'retirado', '2026-02-20', 'Solicitó el retiro por motivos laborales (nota del 19/02/2026).', $coord);
        continue;
    }

    $tutor = tutor_para($carrera);
    $fAsig = dias('2026-02-09', mt_rand(0, 4));
    $asig = asignar_tutor($exp, $tutor, $fAsig, $coord);
    $finMg1 = '2026-04-03';
    $reunionesHasta = $caso === 'cambio_tutor' ? '2026-03-02' : $finMg1;
    reuniones($exp, $asig, $tutor, $est['id_estudiante'], dias($fAsig, 1), $reunionesHasta, 2, 'mg1');
    if ($caso === 'cambio_tutor') {
        $nuevo = tutor_para($carrera, POOL[$carrera][2]);
        $asig = asignar_tutor($exp, $nuevo, '2026-03-04', $coord, 'Renuncia del docente por carga académica (nota de renuncia del 02/03/2026).', '2026-03-02');
        $tutor = $nuevo;
        reuniones($exp, $asig, $tutor, $est['id_estudiante'], '2026-03-05', $finMg1, 2, 'mg1');
    }
    informe($exp, $hitos[$c1]['inf1'], mt_rand(45, 70), dias('2026-03-13', -mt_rand(0, 3)), $tutor, $aux);
    tribunales($exp, 'mg1', elegir_tribunales($carrera, $tutor), '2026-03-19', $aux);
    $fDef = fecha_defensa('2026-03-30', 4);
    $def = defensa($exp, 'mg1', $fDef, slot_defensa($fDef), 'programada', '2026-03-20', $aux);
    citaciones($def, '2026-03-23', $aux);
    realizar($def, $fDef, $coord);
    if ($caso === 'reprobado_mg1') {
        calificar($def, 42.0, dias($fDef, 1), $coord);
        cerrar_expediente($exp, 'reprobado', dias($fDef, 3), 'Nota de MG1 de 42/100, por debajo de la nota de aprobación.', $coord);
        continue;
    }
    calificar($def, nota_aprobada(), dias($fDef, 1), $coord);
    cambiar_etapa($exp, 'mg2', '2026-04-10', 'Perfil aprobado', $coord);

    if ($caso === 'abandono_mg2') {
        reuniones($exp, $asig, $tutor, $est['id_estudiante'], '2026-04-11', '2026-05-15', 1, 'mg2', 0.35);
        informe($exp, $hitos[$c1]['inf2'], 18, '2026-05-11', $tutor, $aux);
        cerrar_expediente($exp, 'abandono', '2026-06-19', 'Dejó de asistir desde mayo y no presentó el segundo informe; no respondió a las citaciones de la Coordinación.', $coord);
        continue;
    }
    reuniones($exp, $asig, $tutor, $est['id_estudiante'], '2026-04-11', '2026-08-14', 1, 'mg2');
    informe($exp, $hitos[$c1]['inf2'], mt_rand(28, 40), dias('2026-05-08', -mt_rand(0, 2)), $tutor, $aux);
    informe($exp, $hitos[$c1]['inf3'], mt_rand(50, 65), dias('2026-06-05', chance(0.2) ? 3 : -1), $tutor, $aux, chance(0.3) ? 'fisico' : 'digital');
    informe($exp, $hitos[$c1]['inf4'], mt_rand(78, 92), dias('2026-07-03', -mt_rand(0, 2)), $tutor, $aux);
    tribunales($exp, 'mg2', elegir_tribunales($carrera, $tutor), '2026-07-30', $aux);
    $fDef2 = fecha_defensa('2026-08-17', 8);
    $def2 = defensa($exp, 'mg2', $fDef2, slot_defensa($fDef2), 'programada', '2026-07-31', $aux);
    citaciones($def2, '2026-08-05', $aux);
    realizar($def2, $fDef2, $coord);
    $nota = nota_aprobada();
    calificar($def2, $nota, dias($fDef2, 1), $coord);
    cerrar_expediente($exp, 'aprobado', dias($fDef2, 3), 'Defensa final aprobada con ' . $nota . '/100.', $coord);
}

// ---------------------------------------------------------------------------
// 7. Cohorte Junio 2026 (en MG2).
// ---------------------------------------------------------------------------
$escenariosC2 = [
    [1, 'PROYECTO', 'mg2_ok', 2], [1, 'PROYECTO', 'mg2_ok', 2], [1, 'TESIS', 'mg2_ok', null], [1, 'PROYECTO', 'mg2_bajo_avance', null],
    [4, 'PROYECTO', 'mg2_ok', null], [4, 'TRABAJO_DIRIGIDO', 'mg2_sin_informe', null], [3, 'PROYECTO', 'mg2_ok', null],
    [2, 'PROYECTO', 'mg2_ok', null], [5, 'TESIS', 'mg2_bajo_avance', null], [6, 'TESIS', 'mg2_riesgo', null], [6, 'PROYECTO', 'mg2_ok', null],
    [1, 'PROYECTO', 'mg1_reprogramada', 13], [3, 'TESIS', 'mg1_realizada_sin_nota', null], [2, 'TESIS', 'reprobado_mg1', null],
    [1, 'EXAMEN', 'examen_previa', null], [5, 'PROYECTO', 'mg2_ok', null],
];
foreach ($escenariosC2 as [$carrera, $mod, $caso, $preferido]) {
    $est = crear_egresante($carrera, dt('2021-08-' . sprintf('%02d', mt_rand(2, 28))));
    $conTutor = $mod !== 'EXAMEN';
    $exp = crear_expediente($est, $mod, $c2, '2026-06-01', $conTutor ? titulo($carrera) : null, 'manual', $aux);
    $credenciales[] = [$est['usuario'], 'Cohorte Junio 2026', $caso];
    if (!$conTutor) {
        q("UPDATE expedientes_mg SET observaciones = 'En espera de completar el mínimo de interesados para abrir la convocatoria de Examen de Grado.' WHERE id_expediente = :id", ['id' => $exp]);
        continue;
    }
    $tutor = tutor_para($carrera, $preferido);
    $fAsig = dias('2026-06-08', mt_rand(0, 4));
    $asig = asignar_tutor($exp, $tutor, $fAsig, $coord);
    reuniones($exp, $asig, $tutor, $est['id_estudiante'], dias($fAsig, 1), '2026-07-31', 2, 'mg1');
    informe($exp, $hitos[$c2]['inf1'], mt_rand(45, 68), dias('2026-07-10', -mt_rand(0, 3)), $tutor, $aux);
    tribunales($exp, 'mg1', $caso === 'mg1_reprogramada' ? [elegir_tribunales($carrera, $tutor, 1)[0]] : elegir_tribunales($carrera, $tutor), '2026-07-16', $aux);

    if ($caso === 'mg1_reprogramada') {
        // Defensa del 31/07 reprogramada por salud; la nueva fecha es el 30/09 y aun falta un tribunal y las citaciones.
        $def = defensa($exp, 'mg1', '2026-07-31', slot_defensa('2026-07-31'), 'programada', '2026-07-17', $aux);
        citaciones($def, '2026-07-24', $aux);
        q("UPDATE defensas_mg SET estado = 'reprogramada', motivo_estado = 'Baja médica del estudiante (certificado del 29/07/2026).' WHERE id_defensa = :id", ['id' => $def]);
        defensa($exp, 'mg1', '2026-09-30', 2, 'programada', '2026-09-15', $aux,
            ['anterior' => $def, 'autorizado_por' => 'decanatura', 'referencia' => 'Nota DEC-FCE/118/2026']);
        reuniones($exp, $asig, $tutor, $est['id_estudiante'], '2026-08-17', HOY, 1, 'mg1');
        continue;
    }
    $fDef = fecha_defensa('2026-07-27', 4);
    $def = defensa($exp, 'mg1', $fDef, slot_defensa($fDef), 'programada', '2026-07-17', $aux);
    citaciones($def, '2026-07-21', $aux);
    if ($caso === 'mg1_realizada_sin_nota') {
        // Postergada por el tribunal: se defendio el 24/09 y la nota aun no se registra.
        q("UPDATE defensas_mg SET estado = 'cancelada', motivo_estado = 'Tribunal con viaje académico; se reprogramará.' WHERE id_defensa = :id", ['id' => $def]);
        bit('defensa_cancelada', 'defensas_mg', $def, ['estado' => 'programada'], ['estado' => 'cancelada', 'motivo' => 'Tribunal con viaje académico'], '2026-07-25', $aux);
        $def = defensa($exp, 'mg1', '2026-09-24', 1, 'programada', '2026-09-08', $aux);
        citaciones($def, '2026-09-10', $aux);
        realizar($def, '2026-09-24', $coord);
        reuniones($exp, $asig, $tutor, $est['id_estudiante'], '2026-08-03', '2026-09-23', 1, 'mg1');
        continue;
    }
    realizar($def, $fDef, $coord);
    if ($caso === 'reprobado_mg1') {
        calificar($def, 38.5, dias($fDef, 1), $coord);
        cerrar_expediente($exp, 'reprobado', dias($fDef, 4), 'Nota de MG1 de 38,5/100. Puede inscribirse en una nueva cohorte.', $coord);
        continue;
    }
    calificar($def, nota_aprobada(), dias($fDef, 1), $coord, chance(0.85));
    cambiar_etapa($exp, 'mg2', '2026-08-07', 'Perfil aprobado', $coord);

    if ($caso === 'mg2_riesgo') {
        reuniones($exp, $asig, $tutor, $est['id_estudiante'], '2026-08-08', '2026-09-04', 1, 'mg2', 0.4);
        continue;
    }
    reuniones($exp, $asig, $tutor, $est['id_estudiante'], '2026-08-08', HOY, 1, 'mg2');
    informe($exp, $hitos[$c2]['inf2'], mt_rand(20, 32), dias('2026-08-31', chance(0.15) ? 2 : -mt_rand(0, 2)), $tutor, $aux);
    if ($caso === 'mg2_sin_informe') {
        continue;
    }
    $pct = $caso === 'mg2_bajo_avance' ? mt_rand(22, 32) : mt_rand(40, 55);
    informe($exp, $hitos[$c2]['inf3'], $pct, dias('2026-09-25', -mt_rand(0, 2)), $tutor, $aux);
}

// ---------------------------------------------------------------------------
// 8. Cohorte Septiembre 2026 (en MG1, importada del padron).
// ---------------------------------------------------------------------------
$escenariosC3 = [
    [1, 'PROYECTO', 'mg1_ok', 2], [1, 'PROYECTO', 'mg1_ok', 2], [1, 'PROYECTO', 'mg1_ok', 13], [1, 'TESIS', 'mg1_ok', null],
    [1, 'PROYECTO', 'mg1_pocas_reuniones', null], [1, 'PROYECTO', 'mg1_defensa', null], [4, 'PROYECTO', 'mg1_ok', null],
    [4, 'TRABAJO_DIRIGIDO', 'mg1_sin_informe', null], [3, 'PROYECTO', 'mg1_defensa', null], [3, 'PROYECTO', 'mg1_bajo_avance', null],
    [2, 'PROYECTO', 'mg1_ok', null], [5, 'TESIS', 'mg1_sin_informe', null], [5, 'PROYECTO', 'mg1_renuncia', null],
    [6, 'TESIS', 'mg1_sin_reuniones', null], [6, 'PROYECTO', 'mg1_defensa_sin_trib', null], [2, 'TESIS', 'previa_sin_tutor', null],
    [6, 'TRABAJO_DIRIGIDO', 'previa_sin_tutor', null], [4, 'EXCELENCIA', 'excelencia_previa', null], [3, 'PROYECTO', 'mg1_ok', null],
];
$importacion = [];
$fila = 1;
foreach ($escenariosC3 as [$carrera, $mod, $caso, $preferido]) {
    $fila++;
    $est = crear_egresante($carrera, dt('2022-02-' . sprintf('%02d', mt_rand(1, 26))));
    $conTutor = in_array($mod, ['PROYECTO', 'TESIS', 'TRABAJO_DIRIGIDO'], true);
    $exp = crear_expediente($est, $mod, $c3, '2026-09-01', $conTutor ? titulo($carrera) : null, 'importacion', $aux);
    $importacion[] = [$fila, $est['ru'], 'creado', 'Expediente creado.', $exp];
    $credenciales[] = [$est['usuario'], 'Cohorte Septiembre 2026', $caso];

    if (!$conTutor || $caso === 'previa_sin_tutor') {
        if ($caso === 'previa_sin_tutor') {
            q("UPDATE expedientes_mg SET observaciones = 'Decanatura aún no remite la nota de afinidad del tutor propuesto.' WHERE id_expediente = :id", ['id' => $exp]);
        }
        continue;
    }
    $tutor = tutor_para($carrera, $preferido);
    $fAsig = dias('2026-09-08', mt_rand(0, 3));
    $asig = asignar_tutor($exp, $tutor, $fAsig, $coord);

    if ($caso === 'mg1_renuncia') {
        reuniones($exp, $asig, $tutor, $est['id_estudiante'], dias($fAsig, 1), '2026-09-18', 2, 'mg1');
        q("UPDATE asignaciones_tutor_mg SET estado = 'finalizada', fecha_fin = '2026-09-22', motivo_fin = 'Renuncia del docente por viaje de posgrado; Decanatura designará reemplazo.',
              fecha_nota_renuncia = '2026-09-21' WHERE id_asignacion = :id", ['id' => $asig]);
        bit('tutor_renuncia', 'asignaciones_tutor_mg', $asig, ['estado' => 'vigente'], ['estado' => 'finalizada', 'motivo' => 'Renuncia del docente'], '2026-09-22', $coord);
        $carga[$tutor]--;
        continue;
    }
    if ($caso === 'mg1_sin_reuniones') {
        reuniones($exp, $asig, $tutor, $est['id_estudiante'], dias($fAsig, 1), '2026-09-14', 2, 'mg1');
        continue;
    }
    if ($caso === 'mg1_pocas_reuniones') {
        reuniones($exp, $asig, $tutor, $est['id_estudiante'], dias($fAsig, 1), HOY, 1, 'mg1');
    } else {
        reuniones($exp, $asig, $tutor, $est['id_estudiante'], dias($fAsig, 1), HOY, 2, 'mg1');
    }
    if ($caso !== 'mg1_sin_informe') {
        $pct = $caso === 'mg1_bajo_avance' ? mt_rand(15, 28) : mt_rand(40, 60);
        informe($exp, $hitos[$c3]['inf1'], $pct, dias('2026-09-25', -mt_rand(0, 2)), $tutor, $aux);
    }
    if (in_array($caso, ['mg1_defensa', 'mg1_defensa_sin_trib'], true)) {
        $fDef = fecha_defensa('2026-10-28', 2);
        if ($caso === 'mg1_defensa') {
            tribunales($exp, 'mg1', elegir_tribunales($carrera, $tutor), '2026-09-24', $aux);
        }
        $def = defensa($exp, 'mg1', $fDef, slot_defensa($fDef), 'programada', '2026-09-25', $aux);
        if ($caso === 'mg1_defensa') {
            citaciones($def, '2026-09-25', $aux);
        }
    }
}
// Padron: dos filas del CSV no generaron expediente.
$importacion[] = [++$fila, (string) q('SELECT es.registro_universitario FROM expedientes_mg e INNER JOIN estudiantes es ON es.id_estudiante = e.id_estudiante WHERE e.id_cohorte = :c LIMIT 1', ['c' => $c3])->fetchColumn(),
                  'omitido', 'Fila duplicada: el estudiante ya tiene expediente en esta modalidad y cohorte.', null];
$importacion[] = [++$fila, '202199871', 'pendiente_cuenta', 'No existe un estudiante con ese R.U.: debe crear su cuenta antes de importar.', null];
q("INSERT INTO importaciones_mg (archivo, id_usuario, total_filas, creados, omitidos, errores, fecha) VALUES ('padron_cohorte_sep_2026.csv', :u, :t, :c, 1, 1, '2026-09-02 10:14:00')",
    ['u' => $aux, 't' => count($importacion), 'c' => count($importacion) - 2]);
$importacionId = (int) $pdo->lastInsertId();
foreach ($importacion as [$f, $ru, $res, $msg, $expId]) {
    q('INSERT INTO importaciones_mg_detalle (id_importacion, fila, registro_universitario, resultado, mensaje, id_expediente) VALUES (:i, :f, :ru, :r, :m, :e)',
        ['i' => $importacionId, 'f' => $f, 'ru' => $ru, 'r' => $res, 'm' => $msg, 'e' => $expId]);
}
q("UPDATE expedientes_mg SET fecha_registro = '2026-09-02 10:14:00' WHERE id_cohorte = :c", ['c' => $c3]);
bit('importacion_padron', 'importaciones_mg', $importacionId, null,
    ['archivo' => 'padron_cohorte_sep_2026.csv', 'creados' => count($importacion) - 2, 'omitidos' => 1, 'errores' => 1], '2026-09-02 10:14:00', $aux);

// ---------------------------------------------------------------------------
// 9. Accesos recientes del equipo de MG y de algunos tesistas.
// ---------------------------------------------------------------------------
$activos = q("SELECT DISTINCT es.id_usuario FROM expedientes_mg e INNER JOIN estudiantes es ON es.id_estudiante = e.id_estudiante WHERE e.estado = 'activo'")->fetchAll(PDO::FETCH_COLUMN);
foreach ([$coord => 60, $aux => 45] as $u => $n) {
    for ($i = 0; $i < $n; $i++) {
        q("INSERT INTO registro_accesos (id_usuario, fecha_hora, ip_origen, resultado) VALUES (:u, :f, :ip, 'exitoso')",
            ['u' => $u, 'f' => dt(dias(HOY, -mt_rand(1, 240))), 'ip' => '192.168.10.' . mt_rand(20, 60)]);
    }
}
foreach ($activos as $u) {
    for ($i = mt_rand(1, 6); $i > 0; $i--) {
        q("INSERT INTO registro_accesos (id_usuario, fecha_hora, ip_origen, resultado) VALUES (:u, :f, :ip, :r)",
            ['u' => $u, 'f' => dt(dias(HOY, -mt_rand(1, 40))), 'ip' => '181.115.' . mt_rand(1, 250) . '.' . mt_rand(1, 250), 'r' => chance(0.1) ? 'fallido' : 'exitoso']);
    }
}

// ---------------------------------------------------------------------------
// 10. Una alerta ya atendida por la Coordinacion (la de carga del tutor).
// ---------------------------------------------------------------------------
foreach ((new MgAlerta())->calcular(HOY) as $alerta) {
    if ($alerta['codigo'] === 'A8') {
        q("INSERT INTO alertas_atendidas_mg (clave, codigo, id_expediente, nota, atendida_por, fecha) VALUES (:c, 'A8', NULL, :n, :u, '2026-09-15 16:20:00')",
            ['c' => $alerta['clave'], 'n' => 'Decanatura autorizó la carga adicional por afinidad temática (Nota DEC-FCE/112/2026).', 'u' => $coord]);
        bit('alerta_atendida', 'alertas_atendidas_mg', $pdo->lastInsertId(), null, ['clave' => $alerta['clave'], 'nota' => 'Carga autorizada por Decanatura'], '2026-09-15 16:20:00', $coord);
        break;
    }
}

// ---------------------------------------------------------------------------
// Resumen.
// ---------------------------------------------------------------------------
echo "Datos de Modalidades de Grado generados (contraseña de las cuentas nuevas: " . PASSWORD . ")\n";
foreach (['cohortes_mg', 'calendario_mg', 'expedientes_mg', 'expediente_etapas_mg', 'asignaciones_tutor_mg', 'reuniones_mg', 'informes_avance_mg',
          'tribunales_mg', 'defensas_mg', 'calificaciones_mg', 'documentos_generados_mg', 'bitacora_mg', 'importaciones_mg_detalle'] as $t) {
    printf("  %-26s %5d\n", $t, (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn());
}
$alertas = (new MgAlerta())->calcular(HOY);
$porCodigo = array_count_values(array_column($alertas, 'codigo'));
ksort($porCodigo);
echo "Alertas: " . implode(', ', array_map(static fn ($k, $v) => "$k=$v", array_keys($porCodigo), $porCodigo)) . "\n";
echo "\nCuentas de prueba:\n  coordinacion.mg (Coordinador MG)\n  auxiliar.mg (Auxiliar MG)\n";
foreach ($credenciales as [$u, $cohorte, $caso]) {
    printf("  %-24s %-26s %s\n", $u, $cohorte, $caso);
}
