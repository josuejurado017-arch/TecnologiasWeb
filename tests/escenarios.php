<?php
/**
 * Escenarios de negocio contra una COPIA de la base: cuentas, ofertas por periodo,
 * motor de asignacion y ciclo de periodos. Ejecuta los controladores reales, asi
 * que modifica datos: nunca correrlo sobre la base de uso.
 *
 * Uso (desde la raiz del proyecto, con una copia llamada testdb_audit):
 *   mysqldump -u root --routines testdb > copia.sql
 *   mysql -u root -e "CREATE DATABASE testdb_audit"; mysql -u root testdb_audit < copia.sql
 *   DB_NAME=testdb_audit php tests/escenarios.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$db = getenv('DB_NAME') ?: '';
if ($db === '' || $db === 'testdb' || !str_ends_with($db, '_audit')) {
    fwrite(STDERR, "Se niega a correr: DB_NAME debe ser una copia terminada en _audit (hoy: '{$db}').\n");
    exit(1);
}

// Sesion en memoria de CLI: sin cookies ni cabeceras.
ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');

require dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = Database::connection();
$fallos = 0;
$total = 0;

function ok(bool $condicion, string $nombre, string $detalle = ''): void
{
    global $fallos, $total;
    $total++;
    if (!$condicion) {
        $fallos++;
    }
    echo ($condicion ? '  ✔ ' : '  ✘ ') . $nombre . ($detalle !== '' && !$condicion ? "\n      → {$detalle}" : '') . "\n";
}

function seccion(string $titulo): void
{
    echo "\n== {$titulo}\n";
}

function fila(string $sql, array $p = []): ?array
{
    $s = Database::connection()->prepare($sql);
    $s->execute($p);
    $r = $s->fetch();

    return $r ?: null;
}

function valor(string $sql, array $p = [])
{
    $s = Database::connection()->prepare($sql);
    $s->execute($p);

    return $s->fetchColumn();
}

function ejecutar(string $sql, array $p = []): void
{
    Database::connection()->prepare($sql)->execute($p);
}

function cuenta(string $usuario): array
{
    $u = fila('SELECT u.*, r.nombre_rol FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol WHERE u.usuario = ?', [$usuario]);
    if ($u === null) {
        throw new RuntimeException("No existe la cuenta {$usuario}");
    }
    unset($u['contrasena_hash']);

    return $u;
}

function como(array $u): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['user'] = $u;
}

function contiene(?string $texto, string $aguja): bool
{
    return $texto !== null && mb_stripos($texto, $aguja) !== false;
}

/** Estudiante activo sin tutoria ni demanda en el periodo. */
function estudianteLibre(int $periodoId, array $excluir = []): array
{
    $excluir = $excluir ?: [0];
    $in = implode(',', array_map('intval', $excluir));
    $e = fila(
        "SELECT e.id_estudiante, e.id_usuario FROM estudiantes e JOIN usuarios u ON u.id_usuario = e.id_usuario
         WHERE u.estado = 'activo' AND e.id_estudiante NOT IN ({$in})
           AND NOT EXISTS (SELECT 1 FROM inscripciones i JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
                           WHERE i.id_estudiante = e.id_estudiante AND g.id_periodo = :p AND i.estado = 'inscrito')
           AND NOT EXISTS (SELECT 1 FROM demanda_tutoria d WHERE d.id_estudiante = e.id_estudiante AND d.id_periodo = :p2 AND d.estado = 'pendiente')
         ORDER BY e.id_estudiante LIMIT 1",
        ['p' => $periodoId, 'p2' => $periodoId]
    );
    if ($e === null) {
        throw new RuntimeException('No quedan estudiantes libres para la prueba.');
    }

    return $e;
}

$admin = cuenta('msuarez');
como($admin);
$periodo = (new Periodo())->activa();
$periodoId = (int) $periodo['id_periodo'];
echo "Base: {$db} · período activo: {$periodo['nombre']}\n";

// ---------------------------------------------------------------------------
seccion('A. Cuentas y sesión');

$karen = cuenta('karen.mamani');
como($karen);
ok(Auth::revalidar() === true, 'A1 sesión de cuenta activa sigue abierta');
ejecutar("UPDATE usuarios SET estado = 'inactivo' WHERE id_usuario = ?", [$karen['id_usuario']]);
ok(Auth::revalidar() === false && !Auth::check(), 'A1 cuenta desactivada pierde la sesión en la siguiente petición');
ejecutar("UPDATE usuarios SET estado = 'activo' WHERE id_usuario = ?", [$karen['id_usuario']]);
como($karen);
$_SESSION['user']['nombre_rol'] = 'administrador';
Auth::revalidar();
ok(Auth::user()['nombre_rol'] === 'estudiante', 'A1 el rol de la sesión se corrige con el de la base');

como($admin);
$usuarios = new UsuariosController();
ok(contiene($usuarios->deactivate((int) $admin['id_usuario']), 'propia'), 'A2 el admin no puede desactivarse con el botón');

$entradaAdmin = [
    'id_rol' => $admin['id_rol'], 'nombre' => $admin['nombre'], 'apellido' => $admin['apellido'],
    'correo' => $admin['correo'], 'usuario' => $admin['usuario'], 'telefono' => (string) $admin['telefono'],
    'carnet_identidad' => (string) $admin['carnet_identidad'], 'estado' => 'inactivo', 'contrasena' => '', 'confirmacion' => '',
];
[, $errores] = $usuarios->update((int) $admin['id_usuario'], $entradaAdmin);
ok(contiene(implode(' ', $errores), 'propia') && valor('SELECT estado FROM usuarios WHERE id_usuario = ?', [$admin['id_usuario']]) === 'activo',
    'A3 el admin no puede desactivarse desde Editar', implode(' | ', $errores));

$rolTutor = (int) valor("SELECT id_rol FROM roles WHERE nombre_rol = 'tutor'");
$entradaKaren = [
    'id_rol' => $rolTutor, 'nombre' => $karen['nombre'], 'apellido' => $karen['apellido'], 'correo' => $karen['correo'],
    'usuario' => $karen['usuario'], 'telefono' => (string) $karen['telefono'], 'carnet_identidad' => (string) $karen['carnet_identidad'],
    'estado' => 'activo', 'contrasena' => '', 'confirmacion' => '',
];
[, $errores] = $usuarios->update((int) $karen['id_usuario'], $entradaKaren);
ok(contiene(implode(' ', $errores), 'rol') && (int) valor('SELECT id_rol FROM usuarios WHERE id_usuario = ?', [$karen['id_usuario']]) === (int) $karen['id_rol'],
    'A4 el rol no cambia al editar (evita perfiles huérfanos)', implode(' | ', $errores));

$otrosAdmins = array_column($pdo->query("SELECT u.id_usuario FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol WHERE r.nombre_rol = 'administrador' AND u.estado = 'activo' AND u.usuario <> 'msuarez'")->fetchAll(), 'id_usuario');
foreach ($otrosAdmins as $id) {
    ejecutar("UPDATE usuarios SET estado = 'inactivo' WHERE id_usuario = ?", [$id]);
}
como(cuenta('mrojas'));
ok(contiene(EstadoCuenta::bloqueoDesactivar((int) $admin['id_usuario']), 'al menos un administrador'), 'A5 no se puede dejar el sistema sin administrador activo');
foreach ($otrosAdmins as $id) {
    ejecutar("UPDATE usuarios SET estado = 'activo' WHERE id_usuario = ?", [$id]);
}
como($admin);

$tutorConGrupo = fila("SELECT t.id_tutor, t.id_usuario FROM tutores t JOIN grupos_tutoria g ON g.id_tutor = t.id_tutor
                       WHERE g.estado IN ('por_aprobar','formacion','confirmado','en_curso') LIMIT 1");
$tutores = new TutoresController();
ok(contiene($tutores->setEstado((int) $tutorConGrupo['id_tutor'], 'inactivo'), 'grupo'), 'A6 tutor con grupos vigentes: Desactivar (Tutores) bloqueado');
ok(contiene($usuarios->deactivate((int) $tutorConGrupo['id_usuario']), 'grupo'), 'A6 tutor con grupos vigentes: Desactivar (Usuarios) bloqueado');
ok(valor('SELECT estado FROM usuarios WHERE id_usuario = ?', [$tutorConGrupo['id_usuario']]) === 'activo', 'A6 la cuenta del tutor sigue activa');

$tutorLibre = fila("SELECT t.id_tutor, t.id_usuario FROM tutores t JOIN usuarios u ON u.id_usuario = t.id_usuario
                    WHERE u.estado = 'activo' AND t.estado_docente = 'aprobado'
                      AND NOT EXISTS (SELECT 1 FROM grupos_tutoria g WHERE g.id_tutor = t.id_tutor AND g.estado IN ('por_aprobar','formacion','confirmado','en_curso'))
                      AND EXISTS (SELECT 1 FROM tutor_materia_config c WHERE c.id_tutor = t.id_tutor AND c.id_periodo = :p AND c.estado = 'aprobado')
                    LIMIT 1", ['p' => $periodoId]);
if ($tutorLibre !== null) {
    $materiasTutor = (new Tutor())->configuredMatterIds((int) $tutorLibre['id_tutor']);
    ok($tutores->setEstado((int) $tutorLibre['id_tutor'], 'inactivo') === null, 'A7 tutor sin grupos vigentes se puede desactivar');
    $sigueOfreciendo = false;
    foreach ($materiasTutor as $m) {
        foreach ((new TutorMateriaConfig())->slotsForMatter($m) as $b) {
            $sigueOfreciendo = $sigueOfreciendo || (int) $b['id_tutor'] === (int) $tutorLibre['id_tutor'];
        }
    }
    ok(!$sigueOfreciendo, 'A7 un tutor inactivo deja de ofrecer turnos al motor');
    ok($tutores->setEstado((int) $tutorLibre['id_tutor'], 'activo') === null, 'A7 el tutor se reactiva');
} else {
    ok(false, 'A7 no hay tutor sin grupos con oferta aprobada para probar');
}

$estInscrito = fila("SELECT i.id_estudiante FROM inscripciones i JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
                     WHERE i.estado = 'inscrito' AND g.estado IN ('por_aprobar','formacion','confirmado','en_curso') LIMIT 1");
$estudiantes = new EstudiantesController();
ok(contiene($estudiantes->setEstado((int) $estInscrito['id_estudiante'], 'inactivo'), 'inscrito'), 'A8 estudiante inscrito en grupo vigente no se desactiva');

$estEspera = fila("SELECT d.id_estudiante FROM demanda_tutoria d JOIN estudiantes e ON e.id_estudiante = d.id_estudiante JOIN usuarios u ON u.id_usuario = e.id_usuario
                   WHERE d.estado = 'pendiente' AND d.id_periodo = :p AND u.estado = 'activo'
                     AND NOT EXISTS (SELECT 1 FROM inscripciones i JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
                                     WHERE i.id_estudiante = d.id_estudiante AND i.estado = 'inscrito' AND g.estado IN ('por_aprobar','formacion','confirmado','en_curso'))
                   LIMIT 1", ['p' => $periodoId]);
ok($estudiantes->setEstado((int) $estEspera['id_estudiante'], 'inactivo') === null, 'A8 estudiante solo en espera se puede desactivar');
ok((int) valor("SELECT COUNT(*) FROM demanda_tutoria WHERE id_estudiante = ? AND estado = 'pendiente'", [$estEspera['id_estudiante']]) === 0,
    'A8 su demanda en espera queda cancelada');
$estudiantes->setEstado((int) $estEspera['id_estudiante'], 'activo');

ok(contiene($estudiantes->delete((int) $estInscrito['id_estudiante']), 'Desactivar')
    && (int) valor('SELECT COUNT(*) FROM estudiantes WHERE id_estudiante = ?', [$estInscrito['id_estudiante']]) === 1,
    'A9 "Eliminar estudiante" ya no borra el perfil');
ok(contiene($tutores->delete((int) $tutorConGrupo['id_tutor']), 'Desactivar')
    && (int) valor('SELECT COUNT(*) FROM tutores WHERE id_tutor = ?', [$tutorConGrupo['id_tutor']]) === 1,
    'A9 "Eliminar tutor" ya no borra el perfil');

$historialAntes = (int) valor("SELECT COUNT(*) FROM inscripciones WHERE id_estudiante = ?", [$estInscrito['id_estudiante']]);
foreach ([
    'A10 la base rechaza borrar un estudiante con historial' => ['DELETE FROM estudiantes WHERE id_estudiante = ?', $estInscrito['id_estudiante']],
    'A10 la base rechaza borrar la cuenta de un tutor' => ['DELETE FROM usuarios WHERE id_usuario = ?', $tutorConGrupo['id_usuario']],
    'A10 la base rechaza borrar una materia con tutores' => ['DELETE FROM materias WHERE id_materia = (SELECT id_materia FROM tutor_materia LIMIT 1)', null],
] as $nombre => [$sql, $param]) {
    try {
        ejecutar($sql, $param === null ? [] : [$param]);
        ok(false, $nombre, 'el DELETE se ejecutó');
    } catch (PDOException $e) {
        ok(str_contains($e->getMessage(), 'foreign key'), $nombre, $e->getMessage());
    }
}
ok((int) valor("SELECT COUNT(*) FROM inscripciones WHERE id_estudiante = ?", [$estInscrito['id_estudiante']]) === $historialAntes, 'A10 el historial del estudiante sigue intacto');

$carrera = (int) valor('SELECT id_carrera FROM carreras LIMIT 1');
ejecutar("INSERT INTO materias (nombre_materia, id_carrera, modalidad_requerida) VALUES ('Materia de prueba 041', ?, 'libre')", [$carrera]);
$materiaPrueba = (int) $pdo->lastInsertId();
ok((new MateriasController())->delete($materiaPrueba) === null, 'A11 una materia sin uso sí se puede eliminar');

// ---------------------------------------------------------------------------
seccion('B. Ofertas por período');

$ofertas = new TutorMateriaConfig();
$tutorLleno = fila("SELECT t.id_tutor, t.id_usuario FROM tutores t JOIN usuarios u ON u.id_usuario = t.id_usuario
                    WHERE u.estado = 'activo' AND t.estado_docente = 'aprobado'
                      AND (SELECT COUNT(*) FROM tutor_materia_config c WHERE c.id_tutor = t.id_tutor AND c.id_periodo = :p
                           AND c.estado IN ('pendiente','aprobado','propuesta')) >= 2 LIMIT 1", ['p' => $periodoId]);
$portal = new TutorPortalController();
$otraMateria = (int) valor('SELECT m.id_materia FROM materias m WHERE NOT EXISTS (SELECT 1 FROM tutor_materia tm WHERE tm.id_materia = m.id_materia AND tm.id_tutor = ?) LIMIT 1', [$tutorLleno['id_tutor']]);
como(cuenta((string) valor('SELECT usuario FROM usuarios WHERE id_usuario = ?', [$tutorLleno['id_usuario']])));
ok(contiene($portal->addSubject((int) $tutorLleno['id_usuario'], ['id_materia' => $otraMateria]), 'dos materias'), 'B1 tutor con 2 materias no puede agregar una tercera');
ok($portal->availableSubjects((int) $tutorLleno['id_usuario']) === [], 'B1 y no se le ofrecen más materias para agregar');

ejecutar('UPDATE periodos SET max_grupos_tutor = 1 WHERE id_periodo = ?', [$periodoId]);
ok($ofertas->limiteCarga() === 1, 'B2 el tope sale del período (max_grupos_tutor = 1)');
ok(contiene($portal->addSubject((int) $tutorLleno['id_usuario'], ['id_materia' => $otraMateria]), 'una materia'), 'B2 con tope 1 el mensaje dice "una materia"');
ejecutar('UPDATE periodos SET max_grupos_tutor = 2 WHERE id_periodo = ?', [$periodoId]);

// Tutor habilitado sin ofertas en el periodo, para los escenarios de turnos.
$nuevo = fila("SELECT t.id_tutor, t.id_usuario, u.usuario FROM tutores t JOIN usuarios u ON u.id_usuario = t.id_usuario
               WHERE u.estado = 'activo' AND t.estado_docente = 'aprobado'
                 AND NOT EXISTS (SELECT 1 FROM tutor_materia_config c WHERE c.id_tutor = t.id_tutor AND c.id_periodo = :p AND c.estado IN ('pendiente','aprobado','propuesta'))
                 AND NOT EXISTS (SELECT 1 FROM grupos_tutoria g WHERE g.id_tutor = t.id_tutor AND g.id_periodo = :p2 AND g.estado <> 'cancelado')
               LIMIT 1", ['p' => $periodoId, 'p2' => $periodoId]);
if ($nuevo === null) {
    ok(false, 'B3-B5 no hay tutor habilitado sin ofertas para probar turnos');
} else {
    como(cuenta($nuevo['usuario']));
    $uid = (int) $nuevo['id_usuario'];
    // Materia con un turno ya cubierto por otro tutor aprobado.
    $cubierto = fila("SELECT tt.id_materia, tt.turno FROM tutor_materia_turno tt JOIN tutor_materia_config c
                        ON c.id_tutor = tt.id_tutor AND c.id_materia = tt.id_materia AND c.id_periodo = tt.id_periodo
                      JOIN materias m ON m.id_materia = tt.id_materia
                      WHERE tt.id_periodo = :p AND c.estado = 'aprobado' AND c.id_tutor <> :t AND m.modalidad_requerida = 'libre' LIMIT 1",
        ['p' => $periodoId, 't' => $nuevo['id_tutor']]);
    $portal->addSubject($uid, ['id_materia' => (int) $cubierto['id_materia']]);
    $r = $portal->saveMateriaConfig($uid, ['id_materia' => $cubierto['id_materia'], 'modalidad' => 'ambas', 'turnos' => [$cubierto['turno']]]);
    ok(contiene($r, 'ya está cubierto'), 'B3 un turno ya cubierto por otro tutor no se puede tomar', (string) $r);
    $portal->removeSubject($uid, ['id_materia' => (int) $cubierto['id_materia']]);

    // Materia A: sin oferta, grupos ni demanda (el escenario arma su propia cola).
    // Materia B: solo sin oferta; la validacion del turno la rechaza antes de tocar su demanda.
    $sinOferta = "m.modalidad_requerida = 'libre'
           AND NOT EXISTS (SELECT 1 FROM tutor_materia_config c WHERE c.id_materia = m.id_materia AND c.id_periodo = {$periodoId} AND c.estado IN ('aprobado','propuesta','pendiente'))
           AND NOT EXISTS (SELECT 1 FROM grupos_tutoria g WHERE g.id_materia = m.id_materia AND g.id_periodo = {$periodoId})";
    $materiaA = (int) $pdo->query("SELECT m.id_materia FROM materias m WHERE {$sinOferta}
           AND NOT EXISTS (SELECT 1 FROM demanda_tutoria d WHERE d.id_materia = m.id_materia AND d.id_periodo = {$periodoId} AND d.estado = 'pendiente')
         ORDER BY m.id_materia LIMIT 1")->fetchColumn();
    $materiaB = (int) $pdo->query("SELECT m.id_materia FROM materias m WHERE {$sinOferta} AND m.id_materia <> {$materiaA} ORDER BY m.id_materia LIMIT 1")->fetchColumn();
    ok($portal->addSubject($uid, ['id_materia' => $materiaA]) === null
        && $portal->saveMateriaConfig($uid, ['id_materia' => $materiaA, 'modalidad' => 'ambas', 'turnos' => ['Tarde']]) === null,
        'B4 el tutor ofrece la materia A en Tarde (turno libre)');
    como($admin);
    [$errAprobar] = (new OfertaTutorController())->aprobar((int) $nuevo['id_tutor'], $materiaA, (int) $admin['id_usuario']);
    ok($errAprobar === null, 'B4 la coordinación aprueba la oferta', (string) $errAprobar);
    como(cuenta($nuevo['usuario']));
    $portal->addSubject($uid, ['id_materia' => $materiaB]);
    $r = $portal->saveMateriaConfig($uid, ['id_materia' => $materiaB, 'modalidad' => 'ambas', 'turnos' => ['Tarde']]);
    ok(contiene($r, 'otra materia en el turno'), 'B4 el mismo tutor no puede ofrecer otra materia en el mismo turno', (string) $r);
    $portal->removeSubject($uid, ['id_materia' => $materiaB]);

    // ---------------------------------------------------------------------------
    seccion('C. Motor de asignación');
    como($admin);
    $motor = new AsignacionController();
    $e1 = estudianteLibre($periodoId);
    $r1 = $motor->solicitarApoyo((int) $e1['id_estudiante'], [$materiaA], $periodo)[0];
    ok($r1['resultado'] === 'lista_espera' && ($r1['motivo'] ?? '') === Demanda::MOTIVO_ESPERANDO,
        'C1 primer estudiante: interés registrado, espera compañeros (quórum 3)', json_encode($r1, JSON_UNESCAPED_UNICODE));
    $otraConOferta = (int) valor("SELECT c.id_materia FROM tutor_materia_config c WHERE c.id_periodo = ? AND c.estado = 'aprobado' AND c.id_materia <> ? LIMIT 1", [$periodoId, $materiaA]);
    $r2 = $motor->solicitarApoyo((int) $e1['id_estudiante'], [$otraConOferta], $periodo)[0];
    ok(in_array($r2['resultado'], ['ya_solicitada', 'una_tutoria'], true) || contiene($r2['detalle'] ?? '', 'una sola'),
        'C2 el estudiante no puede pedir una segunda tutoría en el período', json_encode($r2, JSON_UNESCAPED_UNICODE));

    // B5: el tutor retira la materia; quien esperaba pasa a "sin tutor".
    como(cuenta($nuevo['usuario']));
    ok($portal->removeSubject($uid, ['id_materia' => $materiaA]) === null, 'B5 el tutor quita la materia sin grupos');
    ok(valor('SELECT motivo FROM demanda_tutoria WHERE id_estudiante = ? AND id_materia = ? AND estado = ?', [$e1['id_estudiante'], $materiaA, 'pendiente']) === Demanda::MOTIVO_SIN_TUTOR,
        'B5 la demanda de esa materia pasa a "sin tutor" (antes quedaba "esperando compañeros")');

    // Vuelve a ofrecerla y se forma un grupo con quorum.
    $portal->addSubject($uid, ['id_materia' => $materiaA]);
    $portal->saveMateriaConfig($uid, ['id_materia' => $materiaA, 'modalidad' => 'ambas', 'turnos' => ['Tarde']]);
    como($admin);
    (new OfertaTutorController())->aprobar((int) $nuevo['id_tutor'], $materiaA, (int) $admin['id_usuario']);
    $e2 = estudianteLibre($periodoId, [$e1['id_estudiante']]);
    $motor->solicitarApoyo((int) $e2['id_estudiante'], [$materiaA], $periodo);
    $e3 = estudianteLibre($periodoId, [$e1['id_estudiante'], $e2['id_estudiante']]);
    $r3 = $motor->solicitarApoyo((int) $e3['id_estudiante'], [$materiaA], $periodo)[0];
    $grupoA = fila("SELECT id_grupo, estado, cupo_ocupado, cupo_max FROM grupos_tutoria WHERE id_periodo = ? AND id_materia = ? AND estado <> 'cancelado'", [$periodoId, $materiaA]);
    ok($r3['resultado'] === 'grupo_creado' && $grupoA !== null && (int) $grupoA['cupo_ocupado'] === 3 && $grupoA['estado'] === 'por_aprobar',
        'C3 con el tercer estudiante se forma el grupo (3 inscritos, por aprobar)', json_encode([$r3['resultado'], $grupoA], JSON_UNESCAPED_UNICODE));

    // Grupo lleno + nuevo estudiante: la coordinacion recibe la alerta.
    ejecutar('UPDATE grupos_tutoria SET cupo_max = cupo_ocupado WHERE id_grupo = ?', [$grupoA['id_grupo']]);
    $alertasAntes = (int) valor("SELECT COUNT(*) FROM notificaciones WHERE tipo = 'cupo_completo'");
    $e4 = estudianteLibre($periodoId, [$e1['id_estudiante'], $e2['id_estudiante'], $e3['id_estudiante']]);
    $r4 = $motor->solicitarApoyo((int) $e4['id_estudiante'], [$materiaA], $periodo)[0];
    ok($r4['resultado'] === 'lista_espera', 'C4 grupo lleno: el cuarto estudiante queda en espera', json_encode($r4, JSON_UNESCAPED_UNICODE));
    ok((int) valor("SELECT COUNT(*) FROM notificaciones WHERE tipo = 'cupo_completo'") > $alertasAntes,
        'C4 la coordinación recibe la alerta "Demanda con grupo lleno"');
    ok(contiene($portal->removeSubject($uid, ['id_materia' => $materiaA]) ?? '', 'grupos activos'), 'C5 no se puede quitar una materia con grupo vigente');

    // ---------------------------------------------------------------------------
    seccion('E. Grupo lleno: ¿se abre otro grupo? ¿hay duplicados?');
    $gruposDe = static fn (): array => Database::connection()->query(
        "SELECT g.id_grupo, g.id_tutor, g.hora_inicio, g.cupo_ocupado, g.cupo_max FROM grupos_tutoria g
         WHERE g.id_periodo = {$periodoId} AND g.id_materia = {$materiaA} AND g.estado <> 'cancelado' ORDER BY g.id_grupo"
    )->fetchAll();
    ok(count($gruposDe()) === 1, 'E1 con el grupo lleno NO se abre solo un segundo grupo en el mismo turno');
    ok(valor('SELECT motivo FROM demanda_tutoria WHERE id_estudiante = ? AND id_materia = ? AND estado = ?', [$e4['id_estudiante'], $materiaA, 'pendiente']) !== null,
        'E1 el estudiante que no entró queda en espera (no se pierde)');
    $urlAlerta = (string) valor("SELECT url FROM notificaciones WHERE tipo = 'cupo_completo' AND clave_evento LIKE ? LIMIT 1", ['cupo_completo:' . $grupoA['id_grupo'] . ':%']);
    $anclaAlerta = (string) parse_url($urlAlerta, PHP_URL_FRAGMENT);
    ok($anclaAlerta !== '' && str_contains((string) file_get_contents(dirname(__DIR__) . '/views/grupos/index.php'), 'id="' . $anclaAlerta . '"')
        && str_contains((string) file_get_contents(dirname(__DIR__) . '/views/grupos/index.php'), 'id="cupos-completos"') && $anclaAlerta === 'cupos-completos',
        'E2 la alerta lleva a la sección "Grupos llenos con demanda pendiente"', "url de la alerta: {$urlAlerta}");

    // Dos tutores propios de la prueba, habilitados y sin carga (los datos de ejemplo
    // ya tienen casi todos sus dos materias).
    foreach (['e2', 'e3'] as $sufijo) {
        if (valor('SELECT COUNT(*) FROM usuarios WHERE usuario = ?', ['tutor.prueba.' . $sufijo]) == 0) {
            ejecutar("INSERT INTO usuarios (id_rol, nombre, apellido, correo, usuario, contrasena_hash, estado)
                      VALUES (?, 'Tutor', ?, ?, ?, ?, 'activo')",
                [$rolTutor, 'Prueba ' . strtoupper($sufijo), 'tutor.prueba.' . $sufijo . '@upds.edu.bo', 'tutor.prueba.' . $sufijo, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT)]);
            ejecutar("INSERT INTO tutores (id_usuario, especialidad, estado_docente) VALUES (?, 'Pruebas', 'aprobado')", [(int) $pdo->lastInsertId()]);
        }
    }
    $libresE = $pdo->query(
        "SELECT t.id_tutor, t.id_usuario, u.usuario FROM tutores t JOIN usuarios u ON u.id_usuario = t.id_usuario
         WHERE u.estado = 'activo' AND t.estado_docente = 'aprobado' AND t.id_tutor <> {$nuevo['id_tutor']}
           AND NOT EXISTS (SELECT 1 FROM tutor_materia tm WHERE tm.id_tutor = t.id_tutor AND tm.id_materia = {$materiaA})
           AND (SELECT COUNT(*) FROM tutor_materia_config c WHERE c.id_tutor = t.id_tutor AND c.id_periodo = {$periodoId} AND c.estado IN ('pendiente','aprobado','propuesta')) < 2
           AND (SELECT COUNT(*) FROM grupos_tutoria g WHERE g.id_tutor = t.id_tutor AND g.id_periodo = {$periodoId} AND g.estado <> 'cancelado') < 2
           AND NOT EXISTS (SELECT 1 FROM tutor_materia_turno tt JOIN tutor_materia_config c ON c.id_tutor = tt.id_tutor AND c.id_materia = tt.id_materia AND c.id_periodo = tt.id_periodo
                           WHERE tt.id_tutor = t.id_tutor AND tt.id_periodo = {$periodoId} AND tt.turno IN ('Tarde','Noche') AND c.estado IN ('pendiente','aprobado','propuesta'))
           AND NOT EXISTS (SELECT 1 FROM grupos_tutoria g WHERE g.id_tutor = t.id_tutor AND g.id_periodo = {$periodoId} AND g.estado <> 'cancelado' AND g.hora_inicio >= '15:00:00')
         ORDER BY t.id_tutor LIMIT 2"
    )->fetchAll();
    if (count($libresE) < 2) {
        ok(false, 'E3-E7 hacen falta dos tutores habilitados sin carga');
    } else {
        [$t2, $t3] = $libresE;
        como(cuenta($t2['usuario']));
        $portal->addSubject((int) $t2['id_usuario'], ['id_materia' => $materiaA]);
        $r = $portal->saveMateriaConfig((int) $t2['id_usuario'], ['id_materia' => $materiaA, 'modalidad' => 'ambas', 'turnos' => ['Tarde']]);
        ok(contiene($r, 'ya está cubierto'), 'E3 otro tutor no puede tomar por su cuenta el turno lleno (sería un duplicado)', (string) $r);
        $portal->removeSubject((int) $t2['id_usuario'], ['id_materia' => $materiaA]);

        como($admin);
        $ofertasCtl = new OfertaTutorController();
        $r = $ofertasCtl->proponer($materiaA, ['id_tutor' => $t2['id_tutor'], 'turnos' => ['Tarde'], 'modalidad' => 'ambas'], (int) $admin['id_usuario']);
        ok($r === null, 'E4 la coordinación sí puede ampliar el turno lleno proponiendo a otro tutor', (string) $r);
        como(cuenta($t2['usuario']));
        $r = $ofertasCtl->responderPropuesta((int) $t2['id_usuario'], $materiaA, true, '');
        ok($r === null, 'E4 el segundo tutor acepta la ampliación', (string) $r);
        ok(count($gruposDe()) === 1, 'E4 con 1 estudiante en espera todavía no se abre el segundo grupo (quórum 3)');

        como($admin);
        $usados = [$e1['id_estudiante'], $e2['id_estudiante'], $e3['id_estudiante'], $e4['id_estudiante']];
        $e5 = estudianteLibre($periodoId, $usados);
        $motor->solicitarApoyo((int) $e5['id_estudiante'], [$materiaA], $periodo);
        $e6 = estudianteLibre($periodoId, array_merge($usados, [$e5['id_estudiante']]));
        $r6 = $motor->solicitarApoyo((int) $e6['id_estudiante'], [$materiaA], $periodo)[0];
        $grupos = $gruposDe();
        $segundo = $grupos[1] ?? null;
        ok(count($grupos) === 2 && $segundo !== null && (int) $segundo['id_tutor'] === (int) $t2['id_tutor'] && (int) $segundo['cupo_ocupado'] === 3,
            'E5 al reunir 3 se abre el segundo grupo, con el segundo tutor y en el mismo turno', json_encode([$r6['resultado'], $grupos], JSON_UNESCAPED_UNICODE));
        ok(valor("SELECT COUNT(*) FROM demanda_tutoria WHERE id_estudiante = ? AND id_materia = ? AND estado = 'pendiente'", [$e4['id_estudiante'], $materiaA]) === 0
            || (int) valor("SELECT COUNT(*) FROM demanda_tutoria WHERE id_estudiante = ? AND id_materia = ? AND estado = 'pendiente'", [$e4['id_estudiante'], $materiaA]) === 0,
            'E5 el estudiante que esperaba entra en el segundo grupo');
        $mismoTutorTurno = (int) valor("SELECT COUNT(*) FROM (SELECT id_tutor, hora_inicio FROM grupos_tutoria WHERE id_periodo = ? AND id_materia = ? AND estado <> 'cancelado' GROUP BY 1, 2 HAVING COUNT(*) > 1) x", [$periodoId, $materiaA]);
        ok($mismoTutorTurno === 0, 'E5 nunca hay dos grupos del mismo tutor en el mismo turno');

        $r = $ofertasCtl->proponer($materiaA, ['id_tutor' => $t3['id_tutor'], 'turnos' => ['Tarde'], 'modalidad' => 'ambas'], (int) $admin['id_usuario']);
        ok(contiene($r, 'ya está cubierto'), 'E6 sin grupo lleno, la coordinación no puede sumar un tercer tutor al turno', (string) $r);

        como(cuenta($t3['usuario']));
        $portal->addSubject((int) $t3['id_usuario'], ['id_materia' => $materiaA]);
        $r = $portal->saveMateriaConfig((int) $t3['id_usuario'], ['id_materia' => $materiaA, 'modalidad' => 'ambas', 'turnos' => ['Noche']]);
        ok($r === null, 'E7 otro tutor sí puede ofrecer la misma materia en un turno libre (no es duplicado)', (string) $r);
        como($admin);
    }
}

// ---------------------------------------------------------------------------
seccion('F. Dividir un grupo lleno en dos (db/042)');
como($admin);
$crearTutor = static function (string $usuario) use ($rolTutor): array {
    if (valor('SELECT COUNT(*) FROM usuarios WHERE usuario = ?', [$usuario]) == 0) {
        ejecutar("INSERT INTO usuarios (id_rol, nombre, apellido, correo, usuario, contrasena_hash, estado) VALUES (?, 'Tutor', ?, ?, ?, ?, 'activo')",
            [$rolTutor, 'Prueba ' . $usuario, $usuario . '@upds.edu.bo', $usuario, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT)]);
        ejecutar("INSERT INTO tutores (id_usuario, especialidad, estado_docente) VALUES (?, 'Pruebas', 'aprobado')", [(int) Database::connection()->lastInsertId()]);
    }

    return fila('SELECT t.id_tutor, t.id_usuario, u.usuario FROM tutores t JOIN usuarios u ON u.id_usuario = t.id_usuario WHERE u.usuario = ?', [$usuario]);
};
$t4 = $crearTutor('tutor.prueba.f4');
$t5 = $crearTutor('tutor.prueba.f5');
ejecutar("INSERT INTO materias (nombre_materia, id_carrera, modalidad_requerida) VALUES ('Materia división 042', ?, 'libre')", [$carrera]);
$materiaF = (int) $pdo->lastInsertId();
como(cuenta($t4['usuario']));
$portal->addSubject((int) $t4['id_usuario'], ['id_materia' => $materiaF]);
$portal->saveMateriaConfig((int) $t4['id_usuario'], ['id_materia' => $materiaF, 'modalidad' => 'ambas', 'turnos' => ['Manana']]);
como($admin);
(new OfertaTutorController())->aprobar((int) $t4['id_tutor'], $materiaF, (int) $admin['id_usuario']);
$motorF = new AsignacionController();
$periodoF = (new Periodo())->activa();
$usadosF = [];
for ($i = 0; $i < 6; $i++) {
    $e = estudianteLibre($periodoId, $usadosF);
    $usadosF[] = $e['id_estudiante'];
    $motorF->solicitarApoyo((int) $e['id_estudiante'], [$materiaF], $periodoF);
}
$g1 = fila("SELECT * FROM grupos_tutoria WHERE id_materia = ? AND estado <> 'cancelado'", [$materiaF]);
// Grupo aprobado con calendario futuro y lleno (6/6).
ejecutar("UPDATE grupos_tutoria SET cupo_max = 6, estado = 'confirmado', fecha_aprobacion = NOW(), ubicacion = 'Aula 12', enlace = 'https://meet.google.com/abc-defg-hij' WHERE id_grupo = ?", [$g1['id_grupo']]);
(new Grupo())->generateSessions((int) $g1['id_grupo'], (new Grupo())->dias((int) $g1['id_grupo']), max(date('Y-m-d'), (string) $periodoF['fecha_inicio']), (string) $periodoF['fecha_fin']);
for ($i = 0; $i < 2; $i++) {
    $e = estudianteLibre($periodoId, $usadosF);
    $usadosF[] = $e['id_estudiante'];
    $motorF->solicitarApoyo((int) $e['id_estudiante'], [$materiaF], $periodoF);
}
$grupoF = (new Grupo())->findBasic((int) $g1['id_grupo']);
ok((int) $grupoF['cupo_ocupado'] === 6 && (int) valor("SELECT COUNT(*) FROM demanda_tutoria WHERE id_materia = ? AND estado = 'pendiente'", [$materiaF]) === 2,
    'F0 escenario: grupo aprobado 6/6 y 2 estudiantes en espera');

$div = new DivisionGrupoController();
ok($div->bloqueoGrupo($grupoF) === null, 'F1 un grupo lleno sin asistencia se puede dividir');
$elegibles = array_map('intval', array_column($div->tutoresElegibles($grupoF), 'id_tutor'));
ok(in_array((int) $t5['id_tutor'], $elegibles, true) && !in_array((int) $t4['id_tutor'], $elegibles, true),
    'F2 la lista de tutores incluye al tutor libre y excluye al que dicta el grupo');

$plan = $div->plan($grupoF);
$ultimos = array_column($pdo->query("SELECT id_estudiante FROM inscripciones WHERE id_grupo = {$grupoF['id_grupo']} AND estado = 'inscrito' ORDER BY fecha_inscripcion DESC, id_inscripcion DESC LIMIT 2")->fetchAll(), 'id_estudiante');
ok(count($plan['quedan']) === 4 && count($plan['pasan']) === 2 && count($plan['desde_espera']) === 2 && $plan['error'] === null,
    'F3 vista previa pareja: 6 + 2 en espera → 4 y 4', json_encode(array_map('count', array_intersect_key($plan, array_flip(['quedan', 'pasan', 'desde_espera'])))));
sort($ultimos);
$pasanIds = array_map('intval', array_column($plan['pasan'], 'id_estudiante'));
sort($pasanIds);
ok(array_map('intval', $ultimos) === $pasanIds, 'F3 se trasladan los últimos en inscribirse');

ok($div->proponer((int) $grupoF['id_grupo'], (int) $t4['id_tutor'], (int) $admin['id_usuario']) !== null, 'F4 no se puede proponer al mismo tutor del grupo');
ok($div->proponer((int) $grupoF['id_grupo'], (int) $t5['id_tutor'], (int) $admin['id_usuario']) === null, 'F4 la coordinación envía la propuesta');
ok(contiene($div->proponer((int) $grupoF['id_grupo'], (int) $t5['id_tutor'], (int) $admin['id_usuario']), 'esperando'), 'F4 no se duplica una propuesta pendiente');
ok(count($gruposDeF = $pdo->query("SELECT id_grupo FROM grupos_tutoria WHERE id_materia = {$materiaF} AND estado <> 'cancelado'")->fetchAll()) === 1,
    'F4 mientras el tutor no acepta, no se crea ningún grupo');

$pendientesT5 = $div->pendientesDelTutor((int) $t5['id_usuario']);
ok(count($pendientesT5) === 1, 'F5 el tutor ve la propuesta en Mis grupos');
$divisionId = (int) $pendientesT5[0]['id_division'];
ok(contiene($div->responder($divisionId, (int) $t5['id_usuario'], false, 'no'), 'motivo'), 'F6 rechazar exige motivo');
ok($div->responder($divisionId, (int) $t5['id_usuario'], false, 'No tengo disponibilidad ese mes.') === null
    && valor('SELECT estado FROM grupo_divisiones WHERE id_division = ?', [$divisionId]) === 'rechazada', 'F6 el tutor rechaza con motivo');
ok((int) valor("SELECT COUNT(*) FROM notificaciones WHERE tipo = 'division_rechazada' AND clave_evento LIKE ?", ['division_rechazada:' . $divisionId . ':%']) > 0,
    'F6 la coordinación recibe el aviso del rechazo');

$div->proponer((int) $grupoF['id_grupo'], (int) $t5['id_tutor'], (int) $admin['id_usuario']);
$divisionId = (int) valor("SELECT id_division FROM grupo_divisiones WHERE id_grupo_origen = ? AND estado = 'pendiente'", [$grupoF['id_grupo']]);
ok($div->cancelar($divisionId, (int) $admin['id_usuario']) === null && valor('SELECT estado FROM grupo_divisiones WHERE id_division = ?', [$divisionId]) === 'cancelada',
    'F7 la coordinación puede retirar la propuesta');
ok(contiene($div->responder($divisionId, (int) $t5['id_usuario'], true, ''), 'ya no'), 'F7 una propuesta retirada ya no se puede aceptar');

$div->proponer((int) $grupoF['id_grupo'], (int) $t5['id_tutor'], (int) $admin['id_usuario']);
$divisionId = (int) valor("SELECT id_division FROM grupo_divisiones WHERE id_grupo_origen = ? AND estado = 'pendiente'", [$grupoF['id_grupo']]);
ok(contiene($div->responder($divisionId, (int) $t4['id_usuario'], true, ''), 'no existe'), 'F8 otro tutor no puede responder la propuesta ajena');
$r = $div->responder($divisionId, (int) $t5['id_usuario'], true, '');
ok($r === null, 'F8 el tutor acepta la división', (string) $r);
$g2 = fila('SELECT * FROM grupos_tutoria WHERE id_grupo = (SELECT id_grupo_nuevo FROM grupo_divisiones WHERE id_division = ?)', [$divisionId]);
$diasG1 = (new Grupo())->dias((int) $grupoF['id_grupo']);
ok($g2 !== null && (int) $g2['id_tutor'] === (int) $t5['id_tutor'] && $g2['hora_inicio'] === $grupoF['hora_inicio']
    && (new Grupo())->dias((int) $g2['id_grupo']) === $diasG1 && $g2['estado'] === 'confirmado',
    'F8 el grupo nuevo tiene el segundo tutor, el mismo turno y los mismos días, y queda confirmado', json_encode($g2, JSON_UNESCAPED_UNICODE));
$g1Ahora = fila('SELECT cupo_ocupado FROM grupos_tutoria WHERE id_grupo = ?', [$grupoF['id_grupo']]);
ok((int) $g1Ahora['cupo_ocupado'] === 4 && (int) $g2['cupo_ocupado'] === 4, 'F8 quedan dos grupos parejos: 4 y 4');
ok($g2['ubicacion'] === null && $g2['enlace'] === null, 'F8 la ubicación del grupo nuevo queda pendiente para la coordinación');
ok((int) valor('SELECT COUNT(*) FROM sesiones_tutoria WHERE id_grupo = ?', [$g2['id_grupo']]) === (int) valor('SELECT COUNT(*) FROM sesiones_tutoria WHERE id_grupo = ?', [$grupoF['id_grupo']]),
    'F8 el grupo nuevo recibe el mismo calendario');
$movidos = $pdo->query("SELECT i.id_estudiante FROM inscripciones i WHERE i.id_grupo = {$grupoF['id_grupo']} AND i.estado = 'trasladada'")->fetchAll();
$okMovidos = count($movidos) === 2;
foreach ($movidos as $m) {
    $okMovidos = $okMovidos && (int) valor("SELECT COUNT(*) FROM inscripciones WHERE id_estudiante = ? AND id_grupo = ? AND estado = 'inscrito' AND origen = 'traslado'", [$m['id_estudiante'], $g2['id_grupo']]) === 1;
}
ok($okMovidos, 'F8 los 2 trasladados quedan "trasladada" en el grupo 1 e inscritos en el grupo 2');
ok((int) valor("SELECT COUNT(*) FROM demanda_tutoria WHERE id_materia = ? AND estado = 'pendiente'", [$materiaF]) === 0, 'F8 los 2 que esperaban quedan inscritos');
ok((int) valor("SELECT COUNT(*) FROM (SELECT i.id_estudiante FROM inscripciones i JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo WHERE g.id_periodo = ? AND i.estado = 'inscrito' GROUP BY i.id_estudiante HAVING COUNT(*) > 1) x", [$periodoId]) === 0,
    'F8 nadie queda con dos tutorías');
ok(valor("SELECT estado FROM tutor_materia_config WHERE id_tutor = ? AND id_materia = ? AND id_periodo = ?", [$t5['id_tutor'], $materiaF, $periodoId]) === 'aprobado',
    'F8 el segundo tutor queda con la materia aprobada en ese turno');
ok((int) valor("SELECT COUNT(*) FROM notificaciones WHERE tipo = 'traslado_grupo' AND id_usuario IN (SELECT e.id_usuario FROM estudiantes e JOIN inscripciones i ON i.id_estudiante = e.id_estudiante WHERE i.id_grupo = ? AND i.estado = 'trasladada')", [$grupoF['id_grupo']]) === 2
    && (int) valor("SELECT COUNT(*) FROM notificaciones WHERE tipo = 'division_aceptada' AND clave_evento LIKE ?", ['division_aceptada:' . $divisionId . ':%']) > 0,
    'F8 avisos: a cada trasladado y a la coordinación');
ok(count((new Inscripcion())->forGroup((int) $grupoF['id_grupo'])) === 4, 'F8 la lista y la asistencia del grupo 1 ya no muestran a los trasladados');

ejecutar("INSERT INTO sesiones_tutoria (id_grupo, fecha, estado) VALUES (?, CURDATE(), 'realizada')", [$g2['id_grupo']]);
ejecutar('UPDATE grupos_tutoria SET cupo_max = cupo_ocupado WHERE id_grupo = ?', [$g2['id_grupo']]);
ok(contiene($div->bloqueoGrupo((new Grupo())->findBasic((int) $g2['id_grupo'])), 'asistencia'), 'F9 un grupo con asistencia registrada no se divide');
ejecutar("DELETE FROM sesiones_tutoria WHERE id_grupo = ? AND fecha = CURDATE() AND estado = 'realizada'", [$g2['id_grupo']]);
ejecutar('UPDATE grupos_tutoria SET cupo_max = 6 WHERE id_grupo = ?', [$g2['id_grupo']]);

// ---------------------------------------------------------------------------
seccion('G. Inscripción hasta 4 días después de la primera sesión');
$grupoG = (int) $g2['id_grupo'];
ejecutar("UPDATE sesiones_tutoria SET estado = 'cancelada' WHERE id_grupo = ?", [$grupoG]);
ejecutar("INSERT INTO sesiones_tutoria (id_grupo, fecha, estado) VALUES (?, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'programada')", [$grupoG]);
$idsCandidatos = static fn (): array => array_map('intval', array_column((new Grupo())->candidatesForMatter($periodoId, $materiaF), 'id_grupo'));
$gruposCtl = new GruposController();
$eG = estudianteLibre($periodoId, $usadosF);
ok(in_array($grupoG, $idsCandidatos(), true) && $gruposCtl->evaluarInscripcion((new Grupo())->findBasic($grupoG), (int) $eG['id_estudiante'])['bloqueo'] === null,
    'G1 al 4.º día de la primera sesión la inscripción sigue abierta');
ejecutar("UPDATE sesiones_tutoria SET fecha = DATE_SUB(CURDATE(), INTERVAL 5 DAY) WHERE id_grupo = ? AND estado = 'programada' AND fecha = DATE_SUB(CURDATE(), INTERVAL 4 DAY)", [$grupoG]);
ok(!in_array($grupoG, $idsCandidatos(), true), 'G2 al 5.º día el motor ya no inscribe en ese grupo');
ok(contiene($gruposCtl->evaluarInscripcion((new Grupo())->findBasic($grupoG), (int) $eG['id_estudiante'])['bloqueo'], 'cerró'), 'G2 y la coordinación tampoco puede inscribir a mano');
$rG = $motorF->solicitarApoyo((int) $eG['id_estudiante'], [$materiaF], $periodoF)[0];
ok((int) valor("SELECT COUNT(*) FROM inscripciones WHERE id_grupo = ? AND id_estudiante = ?", [$grupoG, $eG['id_estudiante']]) === 0,
    'G2 el estudiante que llega tarde no entra al grupo ya iniciado', json_encode($rG, JSON_UNESCAPED_UNICODE));
ok($rG['resultado'] === 'asignado' && contiene($rG['detalle'], $t4['usuario']),
    'G4 entra en el otro grupo de la materia, que aún no empezó y tiene lugar', json_encode($rG, JSON_UNESCAPED_UNICODE));
ejecutar("UPDATE sesiones_tutoria SET estado = 'cancelada' WHERE id_grupo = ? AND fecha = DATE_SUB(CURDATE(), INTERVAL 5 DAY)", [$grupoG]);
ok(in_array($grupoG, $idsCandidatos(), true), 'G3 una sesión cancelada no cuenta como inicio');

// ---------------------------------------------------------------------------
seccion('D. Ciclo del período');
como($admin);
$periodos = new PeriodosController();
$siguiente = fila("SELECT id_periodo, nombre FROM periodos WHERE estado = 'borrador' ORDER BY fecha_inicio LIMIT 1");
ok(contiene($periodos->activate((int) $siguiente['id_periodo'], (int) $admin['id_usuario']), 'Ya hay un período activo'), 'D1 no se activa un segundo período con otro activo');
ok($periodos->cerrar($periodoId, (int) $admin['id_usuario']) === null, 'D2 se cierra el período activo');
ok((int) valor("SELECT COUNT(*) FROM demanda_tutoria WHERE id_periodo = ? AND estado = 'pendiente'", [$periodoId]) === 0, 'D2 la demanda pendiente queda vencida');
ok((int) valor("SELECT COUNT(*) FROM grupos_tutoria WHERE id_periodo = ? AND estado IN ('por_aprobar','formacion','confirmado','en_curso')", [$periodoId]) === 0, 'D2 no quedan grupos vigentes');
ok(contiene($periodos->activate($periodoId, (int) $admin['id_usuario']), 'cerrado'), 'D3 un período cerrado no se reactiva');

$habilitados = (int) valor("SELECT COUNT(*) FROM tutores t JOIN usuarios u ON u.id_usuario = t.id_usuario WHERE t.estado_docente = 'aprobado' AND u.estado = 'activo'");
ok($periodos->activate((int) $siguiente['id_periodo'], (int) $admin['id_usuario']) === null, 'D4 se activa el período siguiente');
ok((int) valor("SELECT COUNT(*) FROM notificaciones WHERE tipo = 'renovar_oferta' AND clave_evento LIKE ?", ['renovar_oferta:' . $siguiente['id_periodo'] . ':%']) === $habilitados,
    "D4 los {$habilitados} tutores habilitados reciben \"Renueva tu oferta\"");
$nuevoPeriodo = (new Periodo())->activa();
$sinOfertas = true;
foreach ($pdo->query('SELECT id_materia FROM materias')->fetchAll() as $m) {
    $sinOfertas = $sinOfertas && $ofertas->slotsForMatter((int) $m['id_materia']) === [];
}
ok($sinOfertas, 'D4 las ofertas del período anterior no pasan al nuevo');
ok($ofertas->materiasOcupadas((int) $tutorLleno['id_tutor']) === 0, 'D4 el tutor que tenía 2 materias puede renovar desde cero');
$e5 = estudianteLibre((int) $nuevoPeriodo['id_periodo']);
$r5 = (new AsignacionController())->solicitarApoyo((int) $e5['id_estudiante'], [$otraConOferta ?? $otraMateria], $nuevoPeriodo)[0];
ok($r5['resultado'] === 'interes_registrado', 'D4 sin ofertas renovadas, la solicitud queda como interés "sin tutor"', json_encode($r5, JSON_UNESCAPED_UNICODE));

echo "\n" . ($fallos === 0 ? "TODO OK: {$total} verificaciones" : "FALLARON {$fallos} de {$total} verificaciones") . "\n";
exit($fallos === 0 ? 0 : 1);
