<?php
/**
 * Generador de db/038_datos_operativos.sql (reemplaza a los datos de 027 y 036).
 *
 * Construye datos realistas ejecutando el flujo REAL del sistema sobre una base
 * de laboratorio con las migraciones 001-037 aplicadas (salvo 027 y 036): ofertas
 * aprobadas por la coordinacion, motor de asignacion (AsignacionController) y
 * aprobacion de cada grupo con su ubicacion (GruposController::aprobar). Asi se
 * cumplen por construccion las reglas vigentes: una sola tutoria por periodo para
 * cada estudiante, como maximo 2 grupos por tutor y en turnos distintos,
 * frecuencia por demanda (LMV/MJS o Lunes a Viernes), ningun grupo aprobado sin
 * aula o enlace.
 *
 * Calendario UPDS: la tutoria es el ultimo mes del semestre. Gestion I (feb-jul)
 * -> Tutorias Julio 2026, cerrado. Gestion II (ago-ene) -> Tutorias Enero 2027,
 * activo con inscripciones abiertas desde septiembre: grupos aprobados con su
 * calendario de enero, grupos por aprobar e interes registrado.
 *
 * ATENCION: VACIA la base a la que apunta. Nunca correrlo contra testdb.
 *
 * Uso (desde la raiz del proyecto):
 *   1. Crear una base "seedlab" y aplicar db/001..037 salvo 027 y 036 (cambiando USE testdb).
 *   2. DB_NAME=seedlab DB_USER=root DB_PASSWORD= php db/herramientas/generar_datos_operativos.php --confirmar
 *   3. php db/herramientas/volcar_datos_operativos.php (arma db/036 desde seedlab).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !in_array('--confirmar', $argv, true) || in_array(getenv('DB_NAME') ?: 'testdb', ['testdb', ''], true)) {
    fwrite(STDERR, "Vacia la base destino. Indica DB_NAME de una base de laboratorio (no testdb) y pasa --confirmar.
");
    exit(1);
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';

mt_srand(20260922);
$pdo = Database::connection();
$pdo->exec("SET time_zone = '-04:00'");
const HOY = '2026-09-22';
const PASSWORD = 'Upds2026*';
$hash = password_hash(PASSWORD, PASSWORD_DEFAULT);

function q(string $sql, array $p = []): PDOStatement
{
    $s = Database::connection()->prepare($sql);
    $s->execute($p);
    return $s;
}
function chance(float $p): bool { return mt_rand() / mt_getrandmax() < $p; }
function pick(array $a) { return $a[array_rand($a)]; }
function rnd_dt(string $desde, string $hasta, int $hMin = 7, int $hMax = 22): string
{
    $a = strtotime($desde); $b = strtotime($hasta);
    $d = mt_rand($a, $b);
    return date('Y-m-d', $d) . sprintf(' %02d:%02d:%02d', mt_rand($hMin, $hMax - 1), mt_rand(0, 59), mt_rand(0, 59));
}
function sin_tildes(string $s): string
{
    return strtolower(strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ñ'=>'n','ü'=>'u',' '=>'']));
}

// ---------------------------------------------------------------------------
// 0. Reset de datos de dominio (roles se conservan).
// ---------------------------------------------------------------------------
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['asistencias_sesion','evaluaciones_grupo','sesiones_tutoria','inscripciones','grupo_dias','historial_grupo','grupos_tutoria',
          'demanda_tutoria','notificaciones','registro_accesos','tutor_materia_turno','tutor_materia_config','tutor_materia_historial',
          'tutor_materia','disponibilidad_intervenciones','disponibilidad_tutor','tutor_estado_historial','grupo_rechazos',
          'tutores','estudiantes','usuarios','materias','carreras','periodo_observaciones','periodos','grupo_ubicacion_historial'] as $t) {
    $pdo->exec("TRUNCATE TABLE $t");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
$rol = array_column(q('SELECT id_rol, nombre_rol FROM roles')->fetchAll(), 'id_rol', 'nombre_rol');

// ---------------------------------------------------------------------------
// 1. Catalogo academico.
// ---------------------------------------------------------------------------
$catalogo = [
    'Ingeniería de Sistemas' => [
        ['Cálculo I', 1], ['Álgebra Lineal', 1], ['Programación I', 1], ['Física I', 2], ['Cálculo II', 2],
        ['Programación II', 2], ['Estructura de Datos', 3], ['Base de Datos I', 4], ['Sistemas Operativos', 4],
        ['Base de Datos II', 5], ['Tecnología Web I', 5], ['Redes de Computadoras I', 6], ['Tecnología Web II', 6],
        ['Ingeniería de Software I', 6], ['Investigación Operativa', 7],
    ],
    'Ingeniería Comercial' => [
        ['Matemática I', 1], ['Microeconomía', 2], ['Marketing I', 3], ['Macroeconomía', 3], ['Investigación de Mercados', 5],
    ],
    'Administración de Empresas' => [
        ['Administración I', 1], ['Gestión del Talento Humano', 4], ['Finanzas Corporativas', 6],
    ],
    'Contaduría Pública' => [
        ['Contabilidad General', 1], ['Contabilidad Intermedia', 2], ['Contabilidad de Costos', 3], ['Tributación I', 5], ['Auditoría I', 6],
    ],
    'Derecho' => [
        ['Introducción al Derecho', 1], ['Derecho Romano', 1], ['Derecho Constitucional', 2], ['Derecho Civil I', 3],
        ['Derecho Penal I', 3], ['Derecho Procesal Civil', 5], ['Derecho Laboral', 6],
    ],
    'Psicología' => [
        ['Psicología General', 1], ['Neuropsicología', 3], ['Psicología del Desarrollo', 3], ['Psicometría', 4], ['Psicología Clínica', 6],
    ],
];
$generales = [['Estadística I', 2], ['Matemática Financiera', 3], ['Metodología de la Investigación', 4]];
// Carreras que cursan cada materia general.
$generalesPorCarrera = [
    'Estadística I' => ['Ingeniería Comercial', 'Administración de Empresas', 'Contaduría Pública', 'Psicología', 'Ingeniería de Sistemas'],
    'Matemática Financiera' => ['Ingeniería Comercial', 'Administración de Empresas', 'Contaduría Pública'],
    'Metodología de la Investigación' => ['Derecho', 'Psicología', 'Administración de Empresas', 'Ingeniería Comercial'],
];

$carreraId = []; $materiaId = []; $materiasDeCarrera = [];
foreach ($catalogo as $carrera => $materias) {
    q('INSERT INTO carreras (nombre_carrera) VALUES (?)', [$carrera]);
    $carreraId[$carrera] = (int) $pdo->lastInsertId();
    foreach ($materias as [$nombre, $sem]) {
        q('INSERT INTO materias (nombre_materia, id_carrera) VALUES (?, ?)', [$nombre, $carreraId[$carrera]]);
        $materiaId[$nombre] = (int) $pdo->lastInsertId();
        $materiasDeCarrera[$carrera][] = [$materiaId[$nombre], $sem, $nombre];
    }
}
foreach ($generales as [$nombre, $sem]) {
    q('INSERT INTO materias (nombre_materia, id_carrera) VALUES (?, NULL)', [$nombre]);
    $materiaId[$nombre] = (int) $pdo->lastInsertId();
    foreach ($generalesPorCarrera[$nombre] as $carrera) {
        $materiasDeCarrera[$carrera][] = [$materiaId[$nombre], $sem, $nombre];
    }
}

// Materias "dificiles": concentran la demanda real de tutorias.
$peso = array_fill_keys(array_keys($materiaId), 1);
foreach (['Cálculo I' => 6, 'Álgebra Lineal' => 4, 'Programación I' => 5, 'Física I' => 4, 'Cálculo II' => 3, 'Estructura de Datos' => 3,
          'Base de Datos I' => 3, 'Contabilidad General' => 5, 'Contabilidad de Costos' => 3, 'Estadística I' => 5,
          'Matemática Financiera' => 4, 'Microeconomía' => 3, 'Derecho Romano' => 3, 'Derecho Civil I' => 3,
          'Psicometría' => 3, 'Neuropsicología' => 3, 'Investigación Operativa' => 2, 'Matemática I' => 4, 'Tributación I' => 2,
          'Derecho Procesal Civil' => 2, 'Psicología Clínica' => 1] as $m => $w) {
    $peso[$m] = $w;
}

// Espacios de tutoria: los siembra db/029 (categorias, no aulas reservadas).

// Periodos.
// La tutoria es el ultimo mes de cada semestre (db/037): julio y enero.
q("INSERT INTO periodos (nombre, fecha_inicio, fecha_fin, cupo_min_grupo, cupo_max_default, max_grupos_tutor, estado, fecha_registro) VALUES
   ('Tutorías Julio 2026 (Gestión I)', '2026-07-01', '2026-07-31', 3, 20, 2, 'borrador', '2026-06-01 10:30:00'),
   ('Tutorías Enero 2027 (Gestión II)', '2027-01-04', '2027-01-29', 3, 20, 2, 'borrador', '2026-08-25 11:05:00'),
   ('Tutorías Julio 2027 (Gestión I)', '2027-07-01', '2027-07-30', 3, 20, 2, 'borrador', '2026-09-15 16:40:00')");
[$p1, $p2] = [1, 2];

// ---------------------------------------------------------------------------
// 2. Personas. Nombres y apellidos frecuentes en Santa Cruz.
// ---------------------------------------------------------------------------
$ciUsados = [];
function ci(): string
{
    global $ciUsados;
    do { $n = (string) mt_rand(5100000, 13999999); } while (isset($ciUsados[$n]));
    $ciUsados[$n] = true;
    return $n . (chance(0.8) ? ' SC' : pick([' LP', ' CB', ' BE', ' CH', ' TJ']));
}
function tel(): string { return pick(['6', '7']) . mt_rand(0, 9) . str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT); }

function crearUsuario(string $rolNombre, string $nombre, string $apellido, string $correo, string $usuario, string $fecha, string $estado = 'activo'): int
{
    global $rol, $hash;
    q('INSERT INTO usuarios (id_rol, nombre, apellido, correo, usuario, contrasena_hash, telefono, carnet_identidad, estado, fecha_registro) VALUES (?,?,?,?,?,?,?,?,?,?)',
      [$rol[$rolNombre], $nombre, $apellido, $correo, $usuario, $hash, tel(), ci(), $estado, $fecha]);
    return (int) Database::connection()->lastInsertId();
}

$admins = [
    crearUsuario('administrador', 'Mónica', 'Suárez Arana', 'coord.tutorias@upds.edu.bo', 'msuarez', '2025-07-01 08:30:00'),
    crearUsuario('administrador', 'Ricardo', 'Añez Vaca', 'soporte.academico@upds.edu.bo', 'ranez', '2025-07-01 08:45:00'),
];

// [nombre, apellido, especialidad, bio, materias => [materia, modalidad, turnos, cupo], estado]
// Oferta = tutor + materia + turnos + modalidad; los dias los fija la demanda (db/033).
// Cada materia de un tutor usa turnos distintos: un tutor dicta una sola tutoria por turno.
$tutoresDef = [
    ['Marcelo', 'Rojas Vaca', 'Matemática aplicada', 'Ingeniero civil con maestría en Matemática Aplicada (UAGRM). Docente de ciencias básicas desde 2014.',
        [['Cálculo I', 'presencial', ['Noche', 'Manana'], 20], ['Cálculo II', 'presencial', ['Mediodia'], 15], ['Álgebra Lineal', 'ambas', ['Tarde'], 20]]],
    ['Paola', 'Céspedes Añez', 'Programación y algoritmos', 'Ingeniera de sistemas, desarrolladora backend en Java y Python. Tutora de programación desde 2021.',
        [['Programación I', 'presencial', ['Tarde'], 20], ['Programación II', 'presencial', ['Noche'], 15], ['Estructura de Datos', 'virtual', ['Mediodia'], 15]]],
    ['Rodrigo', 'Justiniano Suárez', 'Bases de datos', 'Ingeniero de sistemas, DBA en una entidad financiera local. Certificado Oracle Database SQL.',
        [['Base de Datos I', 'presencial', ['Noche'], 20], ['Base de Datos II', 'virtual', ['Tarde'], 15], ['Tecnología Web I', 'ambas', ['Mediodia'], 20]]],
    ['Karen', 'Gutiérrez Mercado', 'Redes y sistemas operativos', 'Ingeniera en redes, certificada CCNA. Administra infraestructura de servidores Linux.',
        [['Redes de Computadoras I', 'presencial', ['Tarde'], 15], ['Sistemas Operativos', 'ambas', ['Noche'], 15]]],
    ['Álvaro', 'Montaño Ribera', 'Física', 'Licenciado en Física. Docente de Física I y II en ingenierías.',
        [['Física I', 'presencial', ['Manana'], 20], ['Cálculo I', 'presencial', ['Mediodia', 'Tarde'], 20]]],
    ['Verónica', 'Salvatierra Paz', 'Contabilidad', 'Contadora pública autorizada, auditora externa. Docente de contabilidad desde 2016.',
        [['Contabilidad General', 'presencial', ['Noche'], 25], ['Contabilidad de Costos', 'presencial', ['Tarde'], 20], ['Contabilidad Intermedia', 'virtual', ['Mediodia'], 15]]],
    ['Jhonny', 'Arteaga Flores', 'Estadística y finanzas', 'Economista con diplomado en Finanzas. Analista de riesgos en banca.',
        [['Estadística I', 'ambas', ['Noche', 'Manana'], 25], ['Matemática Financiera', 'presencial', ['Tarde'], 20]]],
    ['Daniela', 'Parada Chávez', 'Economía', 'Licenciada en Economía con maestría en Gestión Empresarial.',
        [['Microeconomía', 'presencial', ['Tarde'], 20], ['Macroeconomía', 'virtual', ['Noche'], 15], ['Matemática I', 'presencial', ['Manana'], 20]]],
    ['Fernando', 'Aguilera Roca', 'Derecho civil y constitucional', 'Abogado litigante. Magíster en Derecho Constitucional.',
        [['Derecho Civil I', 'presencial', ['Noche'], 20], ['Derecho Constitucional', 'presencial', ['Tarde'], 20], ['Derecho Romano', 'ambas', ['Mediodia'], 20]]],
    ['Silvia', 'Méndez Cuéllar', 'Derecho penal', 'Abogada, ex fiscal de materia. Docente de Derecho Penal.',
        [['Derecho Penal I', 'presencial', ['Tarde'], 20], ['Introducción al Derecho', 'virtual', ['Mediodia', 'Noche'], 25]]],
    ['Carla', 'Vaca Díez Rivero', 'Psicología del desarrollo', 'Psicóloga clínica infantil. Magíster en Psicología Educativa.',
        [['Psicología del Desarrollo', 'presencial', ['Tarde'], 15], ['Psicología General', 'presencial', ['Mediodia'], 20], ['Neuropsicología', 'ambas', ['Noche'], 15]]],
    ['Mauricio', 'Gil Antelo', 'Administración', 'Administrador de empresas, gerente de RR.HH. en el sector agroindustrial.',
        [['Administración I', 'presencial', ['Noche'], 20], ['Gestión del Talento Humano', 'virtual', ['Tarde'], 15]]],
    ['Luis Fernando', 'Soria Galvarro', 'Ingeniería de software', 'Ingeniero de sistemas, líder técnico en una software factory. Scrum Master certificado.',
        [['Tecnología Web II', 'virtual', ['Noche'], 20], ['Ingeniería de Software I', 'presencial', ['Tarde'], 15], ['Tecnología Web I', 'presencial', ['Mediodia'], 20]]],
    ['Natalia', 'Rivero Saucedo', 'Investigación y psicometría', 'Psicóloga organizacional. Investigadora en evaluación psicométrica.',
        [['Metodología de la Investigación', 'virtual', ['Noche'], 25], ['Psicometría', 'presencial', ['Mediodia'], 15]]],
    ['Gabriela', 'Moreno Terrazas', 'Tributación y auditoría', 'Contadora pública, especialista en tributación boliviana (SIN).',
        [['Tributación I', 'presencial', ['Noche'], 15], ['Auditoría I', null, [], null]]],
    ['Cristian', 'Égüez Pinto', 'Investigación operativa', 'Ingeniero industrial. Actualmente con licencia académica.',
        [['Investigación Operativa', 'presencial', ['Noche'], 15]], 'inactivo'],
    ['Diego', 'Paz Lijerón', 'Desarrollo web', 'Ingeniero de sistemas egresado de UPDS. Se incorporó al programa de tutorías en septiembre de 2026.',
        [['Programación I', null, [], null], ['Tecnología Web I', null, [], null]]],
];

$tutorIds = []; $tutorUser = []; $calidadTutor = [];
foreach ($tutoresDef as $i => $def) {
    [$nombre, $apellido, $esp, $bio, $materias] = $def;
    $estado = $def[5] ?? 'activo';
    $primerApellido = explode(' ', $apellido)[0];
    $user = sin_tildes(mb_substr($nombre, 0, 1) . $primerApellido);
    $fechaAlta = $i === 16 ? '2026-09-08 10:12:00' : rnd_dt('2024-02-01', '2025-12-15', 8, 18);
    $uid = crearUsuario('tutor', $nombre, $apellido, sin_tildes(explode(' ', $nombre)[0]) . '.' . sin_tildes($primerApellido) . '@upds.edu.bo', $user, $fechaAlta, $estado);
    // Habilitacion docente (db/028): la coordinacion aprobo al tutor al incorporarlo.
    $revision = date('Y-m-d H:i:s', strtotime($fechaAlta . ' +' . mt_rand(1, 3) . ' days'));
    q("INSERT INTO tutores (id_usuario, especialidad, biografia, estado_docente, fecha_revision, id_revisor) VALUES (?,?,?,'aprobado',?,?)",
      [$uid, $esp, $bio, $revision, $admins[0]]);
    $tid = (int) $pdo->lastInsertId();
    q("INSERT INTO tutor_estado_historial (id_tutor, estado_anterior, estado_nuevo, motivo, id_usuario_accion, fecha) VALUES (?, 'pendiente', 'aprobado', 'Habilitado por la coordinación.', ?, ?)",
      [$tid, $admins[0], $revision]);
    $tutorIds[$i] = $tid; $tutorUser[$tid] = $uid;
    $calidadTutor[$tid] = [4.7, 4.5, 4.4, 4.2, 4.0, 4.6, 3.9, 4.3, 4.5, 4.1, 4.8, 3.8, 4.4, 4.2, 4.0, 4.0, 4.3][$i];
    foreach ($materias as [$m, $modalidad, $turnos, $cupo]) {
        q('INSERT INTO tutor_materia (id_tutor, id_materia) VALUES (?,?)', [$tid, $materiaId[$m]]);
        if ($modalidad === null) {
            continue; // materia agregada pero aun sin configurar horarios
        }
        // Oferta guardada por el tutor y aprobada por la coordinacion (db/032).
        $guardada = rnd_dt('2026-01-15', '2026-02-18', 8, 21);
        $aprobada = date('Y-m-d H:i:s', strtotime($guardada . ' +' . mt_rand(4, 40) . ' hours'));
        q("INSERT INTO tutor_materia_config (id_tutor, id_materia, modalidad, cupo_recomendado, estado, id_usuario_revision, fecha_revision, fecha_registro, fecha_actualizacion) VALUES (?,?,?,?,'aprobado',?,?,?,?)",
          [$tid, $materiaId[$m], $modalidad, $cupo, $admins[0], $aprobada, $guardada, $guardada]);
        q("INSERT INTO tutor_materia_historial (id_tutor, id_materia, estado_anterior, estado_nuevo, motivo, id_usuario_accion, fecha) VALUES (?, ?, 'pendiente', 'aprobado', 'Oferta aprobada por la coordinación.', ?, ?)",
          [$tid, $materiaId[$m], $admins[0], $aprobada]);
        foreach ($turnos as $t) {
            q('INSERT INTO tutor_materia_turno (id_tutor, id_materia, turno) VALUES (?,?,?)', [$tid, $materiaId[$m], $t]);
        }
    }
}

// Estudiantes.
$nombresF = ['María José', 'Camila', 'Valeria', 'Daniela', 'Andrea', 'Gabriela', 'Fernanda', 'Mariana', 'Natalia', 'Paola', 'Sofía', 'Lucía', 'Jimena', 'Carla', 'Alejandra', 'Nicole', 'Adriana', 'Micaela', 'Rocío', 'Melissa', 'Belén', 'Karen', 'Brenda', 'Ana Paula'];
$nombresM = ['José Luis', 'Juan Pablo', 'Carlos', 'Diego', 'Luis Fernando', 'Jorge', 'Miguel Ángel', 'Rodrigo', 'Sebastián', 'Mauricio', 'Álvaro', 'Daniel', 'Gonzalo', 'Marcelo', 'Andrés', 'Ricardo', 'Fabián', 'Óscar', 'Kevin', 'Brayan', 'Joaquín', 'Esteban', 'Rubén', 'Cristhian'];
$apellidos = ['Suárez', 'Rojas', 'Vaca', 'Justiniano', 'Áñez', 'Gutiérrez', 'Pérez', 'Mendoza', 'Flores', 'Quispe', 'Mamani', 'Cuéllar', 'Ribera', 'Salvatierra', 'Parada', 'Chávez', 'Moreno', 'Antelo', 'Saucedo', 'Terrazas', 'Paz', 'Égüez', 'Montaño', 'Vargas', 'Rivero', 'Arteaga', 'Céspedes', 'Hurtado', 'Aguilera', 'Soliz', 'Zabala', 'Roca', 'Peña', 'Durán', 'Castedo', 'Arias', 'Lijerón', 'Ortiz', 'Coimbra', 'Melgar', 'Choque', 'Guzmán', 'Barba', 'Sandoval'];
$distribucion = ['Ingeniería de Sistemas' => 85, 'Ingeniería Comercial' => 32, 'Administración de Empresas' => 34, 'Contaduría Pública' => 42, 'Derecho' => 44, 'Psicología' => 33];
$estudiantes = []; $usernames = []; $ruUsados = [];
foreach ($distribucion as $carrera => $n) {
    for ($k = 0; $k < $n; $k++) {
        $fem = chance(0.52);
        $nombre = pick($fem ? $nombresF : $nombresM);
        do { $a1 = pick($apellidos); $a2 = pick($apellidos); } while ($a1 === $a2);
        $semestre = pick([1, 1, 2, 2, 2, 3, 3, 3, 4, 4, 5, 5, 6, 6, 7, 8, 9]);
        $ingreso = 2026 - intdiv($semestre - 1, 2);
        do { $ru = sprintf('%d%s%04d', $ingreso, $semestre % 2 === 1 ? '1' : '2', mt_rand(1, 2999)); } while (isset($ruUsados[$ru]));
        $ruUsados[$ru] = true;
        $base = sin_tildes(explode(' ', $nombre)[0]) . '.' . sin_tildes($a1);
        $u = $base; $sfx = 2;
        while (isset($usernames[$u])) { $u = $base . $sfx++; }
        $usernames[$u] = true;
        // Semestre 1 hoy = ingreso en la gestion II-2026; el resto ya usaba la plataforma desde la gestion I.
        $fechaAlta = $semestre === 1 ? rnd_dt('2026-07-20', '2026-08-09', 8, 22) : rnd_dt('2025-08-01', '2026-02-20', 8, 22);
        $estadoEst = chance(0.03) ? 'inactivo' : 'activo';
        $uid = crearUsuario('estudiante', $nombre, "$a1 $a2", $u . '@est.upds.edu.bo', $u, $fechaAlta, $estadoEst);
        q('INSERT INTO estudiantes (id_usuario, id_carrera, semestre, registro_universitario) VALUES (?,?,?,?)', [$uid, $carreraId[$carrera], $semestre, $ru]);
        $estudiantes[] = ['id' => (int) $pdo->lastInsertId(), 'uid' => $uid, 'carrera' => $carrera, 'semestre' => $semestre,
                          'constancia' => 0.55 + mt_rand(0, 45) / 100, 'alta' => $fechaAlta, 'activo' => $estadoEst === 'activo'];
    }
}

// ---------------------------------------------------------------------------
// 3. Simulacion de campanas con el motor real.
// ---------------------------------------------------------------------------
$T0 = q('SELECT NOW()')->fetchColumn();

/** Reubica en $fecha todo lo que el motor escribio "ahora" (marcas >= T0). */
function fechar(string $fecha): void
{
    global $T0;
    foreach ([['inscripciones', 'fecha_inscripcion'], ['demanda_tutoria', 'fecha_solicitud'], ['demanda_tutoria', 'fecha_atencion'],
              ['notificaciones', 'fecha_creacion'], ['grupos_tutoria', 'fecha_registro'], ['grupos_tutoria', 'fecha_estado'],
              ['grupos_tutoria', 'fecha_aprobacion'], ['grupos_tutoria', 'fecha_ubicacion'], ['grupo_ubicacion_historial', 'fecha'],
              ['grupo_rechazos', 'fecha'], ['historial_grupo', 'fecha_evento'], ['sesiones_tutoria', 'fecha_registro']] as [$t, $c]) {
        q("UPDATE $t SET $c = ? WHERE $c >= ?", [$fecha, $T0]);
    }
}

/** Ubicacion que la coordinacion registra al aprobar: aula (presencial) o enlace (virtual). */
function ubicacionSimulada(string $modalidad): array
{
    if ($modalidad === 'presencial') {
        return ['ubicacion' => sprintf('Aula %d%02d - Bloque %s', mt_rand(1, 3), mt_rand(1, 12), pick(['A', 'B', 'C']))];
    }
    $enlace = pick([
        static fn (): string => 'https://meet.google.com/' . substr(md5((string) mt_rand()), 0, 3) . '-' . substr(md5((string) mt_rand()), 0, 4) . '-' . substr(md5((string) mt_rand()), 0, 3),
        static fn (): string => 'https://us02web.zoom.us/j/' . mt_rand(80000000000, 89999999999),
        static fn (): string => 'https://teams.microsoft.com/l/meetup-join/19%3ameeting_' . substr(md5((string) mt_rand()), 0, 16),
    ])();

    return ['enlace' => $enlace];
}

/**
 * La coordinacion revisa los grupos que el motor propuso: aprueba cada uno unos
 * cuatro dias despues de creado (GruposController::aprobar, con la frecuencia y la
 * ubicacion obligatoria) y el calendario arranca desde esa fecha simulada.
 * $hasta: solo los creados antes de esa fecha menos $espera horas (null = todos).
 */
function aprobarPendientes(int $periodoId, ?string $hasta, int $espera = 96): void
{
    global $admins;
    $limite = $hasta === null ? '9999-12-31' : date('Y-m-d H:i:s', strtotime($hasta) - $espera * 3600);
    $grupos = q("SELECT id_grupo, modalidad, fecha_registro FROM grupos_tutoria WHERE id_periodo = ? AND estado = 'por_aprobar' AND fecha_registro <= ? ORDER BY fecha_registro",
        [$periodoId, $limite])->fetchAll();
    $periodo = (new Periodo())->findById($periodoId);
    foreach ($grupos as $g) {
        $grupoId = (int) $g['id_grupo'];
        $modelo = new Grupo();
        $patron = Grupo::patronReducido($modelo->dias($grupoId));
        $input = ubicacionSimulada((string) $g['modalidad']) + ['patron' => $patron ?? 'lmv'];
        $error = (new GruposController())->aprobar($grupoId, $input, $admins[0]);
        if ($error !== null) {
            fwrite(STDERR, "No se aprobo el grupo $grupoId: $error\n");
            continue;
        }
        $aprobado = date('Y-m-d H:i:s', strtotime((string) $g['fecha_registro']) + $espera * 3600 + mt_rand(0, 6 * 3600));
        // El calendario se genero desde la fecha real de ejecucion: se rehace desde la aprobacion simulada.
        q('DELETE FROM sesiones_tutoria WHERE id_grupo = ?', [$grupoId]);
        $desde = max(date('Y-m-d', strtotime($aprobado . ' +1 day')), (string) $periodo['fecha_inicio']);
        $modelo->generateSessions($grupoId, $modelo->dias($grupoId), $desde, (string) $periodo['fecha_fin']);
        fechar($aprobado);
    }
}

function materiasSolicitadas(array $est): array
{
    global $materiasDeCarrera, $peso;
    $candidatas = array_values(array_filter($materiasDeCarrera[$est['carrera']], static fn ($m) => $m[1] <= $est['semestre'] && $m[1] >= $est['semestre'] - 2));
    if (!$candidatas) {
        $candidatas = $materiasDeCarrera[$est['carrera']];
    }
    // Una sola tutoria por periodo (db/037): el estudiante elige una materia.
    $n = 1;
    $elegidas = [];
    while (count($elegidas) < $n) {
        $bolsa = [];
        foreach ($candidatas as $m) {
            if (!isset($elegidas[$m[0]])) {
                $bolsa = array_merge($bolsa, array_fill(0, $peso[$m[2]], $m[0]));
            }
        }
        $elegidas[pick($bolsa)] = true;
    }
    return array_keys($elegidas);
}

function simularSolicitudes(int $periodoId, array $participantes, string $desde, string $hasta): void
{
    $periodo = (new Periodo())->findById($periodoId);
    $motor = new AsignacionController();
    $fechas = [];
    foreach ($participantes as $idx => $est) {
        $fechas[$idx] = rnd_dt($desde, $hasta, 7, 23);
    }
    asort($fechas);
    foreach ($fechas as $idx => $fecha) {
        aprobarPendientes($periodoId, $fecha);
        $motor->solicitarApoyo($participantes[$idx]['id'], materiasSolicitadas($participantes[$idx]), $periodo);
        fechar($fecha);
    }
}

$feriados = ['2026-02-16', '2026-02-17', '2026-04-03', '2026-05-01', '2026-06-04', '2026-06-22', '2026-08-06', '2026-09-24', '2026-11-02', '2026-12-25'];
$observaciones = ['Llegó por tema de transporte.', 'Trabaja en horario de oficina, avisó con anticipación.', 'Se retiró antes por examen.', 'Participó activamente en la práctica.', 'Conexión inestable, se reconectó.', 'Presentó certificado médico.'];

/** Marca sesiones pasadas como realizadas con asistencia, y cancela las de feriados. */
function simularSesiones(int $periodoId, string $hasta, float $pendientesRegistro): void
{
    global $feriados, $observaciones, $estudiantes, $tutorUser;
    $constancia = array_column($estudiantes, 'constancia', 'id');
    $grupos = q("SELECT id_grupo, id_tutor, estado, DATE(fecha_registro) AS creado, hora_inicio FROM grupos_tutoria WHERE id_periodo = ?", [$periodoId])->fetchAll();
    foreach ($grupos as $g) {
        // Un grupo no puede reunirse antes de existir: se descartan sesiones previas a su creacion.
        q('DELETE FROM sesiones_tutoria WHERE id_grupo = ? AND fecha <= ?', [$g['id_grupo'], $g['creado']]);
        if (!in_array($g['estado'], ['confirmado', 'en_curso', 'finalizado'], true)) {
            continue;
        }
        $confirmado = q("SELECT MIN(fecha_evento) FROM historial_grupo WHERE id_grupo = ? AND estado_nuevo = 'confirmado'", [$g['id_grupo']])->fetchColumn();
        $sesiones = q("SELECT id_sesion, fecha FROM sesiones_tutoria WHERE id_grupo = ? AND fecha < ? ORDER BY fecha", [$g['id_grupo'], $hasta])->fetchAll();
        foreach ($sesiones as $s) {
            if (in_array($s['fecha'], $feriados, true)) {
                q("UPDATE sesiones_tutoria SET estado = 'cancelada' WHERE id_sesion = ?", [$s['id_sesion']]);
                continue;
            }
            if ($confirmado === false || $confirmado === null || $s['fecha'] <= substr((string) $confirmado, 0, 10)) {
                q("UPDATE sesiones_tutoria SET estado = 'cancelada' WHERE id_sesion = ?", [$s['id_sesion']]);
                continue;
            }
            // Algunas sesiones recientes aun sin registrar por el tutor.
            if ($s['fecha'] >= date('Y-m-d', strtotime($hasta . ' -10 days')) && chance($pendientesRegistro)) {
                continue;
            }
            q("UPDATE sesiones_tutoria SET estado = 'realizada' WHERE id_sesion = ?", [$s['id_sesion']]);
            $registro = $s['fecha'] . ' ' . date('H:i:s', strtotime($g['hora_inicio'] . ' +' . mt_rand(150, 200) . ' minutes'));
            $insc = q("SELECT id_inscripcion, id_estudiante FROM inscripciones WHERE id_grupo = ? AND fecha_inscripcion < ? AND (estado = 'inscrito' OR estado = 'cancelada')", [$g['id_grupo'], $s['fecha']])->fetchAll();
            foreach ($insc as $i) {
                $c = $constancia[(int) $i['id_estudiante']] ?? 0.8;
                $r = mt_rand() / mt_getrandmax();
                if ($r < $c * 0.86) { $estado = 'asistio'; $min = null; }
                elseif ($r < $c * 0.95) { $estado = 'retraso'; $min = pick([5, 10, 10, 15, 20, 25]); }
                elseif ($r < $c) { $estado = 'parcial'; $min = null; }
                else { $estado = 'no_asistio'; $min = null; }
                $obs = $estado !== 'asistio' && chance(0.25) ? pick($observaciones) : null;
                q('INSERT INTO asistencias_sesion (id_sesion, id_inscripcion, estado, minutos_retraso, observaciones, id_usuario_registro, fecha_registro) VALUES (?,?,?,?,?,?,?)',
                  [$s['id_sesion'], $i['id_inscripcion'], $estado, $min, $obs, $tutorUser[(int) $g['id_tutor']], $registro]);
            }
        }
    }
}

$comentarios = [
    5 => ['Excelente tutor, explica con paciencia y con ejemplos de examen.', 'Me ayudó muchísimo a aprobar el parcial.', 'Muy buena organización de los temas, recomendado.', 'Las prácticas resueltas fueron clave.', 'Siempre puntual y resuelve todas las dudas.', null, null],
    4 => ['Buen dominio del tema, a veces va un poco rápido.', 'Las sesiones son útiles, sería bueno tener más ejercicios.', 'Buena tutoría, el horario de noche me acomoda por el trabajo.', null, null],
    3 => ['El contenido es bueno pero el grupo era muy numeroso.', 'Faltó más práctica antes del examen.', 'A veces la conexión virtual fallaba.', null],
    2 => ['Poco tiempo para resolver dudas individuales.', 'Se cancelaron varias sesiones.'],
];

function simularEvaluaciones(int $periodoId, float $proporcion, string $desde, string $hasta): void
{
    global $calidadTutor, $comentarios;
    $rows = q("SELECT i.id_inscripcion, g.id_tutor FROM inscripciones i INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
               WHERE g.id_periodo = ? AND i.estado = 'inscrito'
                 AND (SELECT COUNT(*) FROM asistencias_sesion a WHERE a.id_inscripcion = i.id_inscripcion AND a.estado <> 'no_asistio') >= 2", [$periodoId])->fetchAll();
    foreach ($rows as $r) {
        if (!chance($proporcion)) {
            continue;
        }
        $q = $calidadTutor[(int) $r['id_tutor']];
        $nota = static fn (float $sesgo = 0.0): int => max(1, min(5, (int) round($q + $sesgo + (mt_rand(-100, 100) / 100) * 0.9)));
        $general = $nota();
        $pool = $comentarios[max(2, $general)] ?? [null];
        q('INSERT INTO evaluaciones_grupo (id_inscripcion, calificacion_general, puntualidad, dominio, claridad, utilidad, comentario, fecha_evaluacion) VALUES (?,?,?,?,?,?,?,?)',
          [$r['id_inscripcion'], $general, $nota(0.2), $nota(0.3), $nota(-0.1), $nota(), pick($pool), rnd_dt($desde, $hasta, 8, 23)]);
    }
}

// ---- Tutorias Julio 2026, Gestion I (cerrada) ----
// Inscripciones del 15 al 30 de junio; tutorias durante julio.
$adminCoord = $admins[0];
q("UPDATE periodos SET estado = 'activa', fecha_activacion = '2026-06-15 09:15:00', id_usuario_activacion = ? WHERE id_periodo = ?", [$adminCoord, $p1]);
$participantes1 = array_values(array_filter($estudiantes, static fn ($e) => $e['activo'] && $e['semestre'] >= 2 && chance(0.7)));
simularSolicitudes($p1, $participantes1, '2026-06-15', '2026-06-30');
// Antes del cierre de inscripciones la coordinacion revisa todo lo pendiente.
aprobarPendientes($p1, null);
// Al cierre de inscripciones, los grupos que no llegaron al cupo minimo se cancelan.
foreach (q("SELECT id_grupo FROM grupos_tutoria WHERE id_periodo = ? AND estado = 'formacion'", [$p1])->fetchAll() as $g) {
    (new GruposController())->cancel((int) $g['id_grupo'], 'No alcanzó el cupo mínimo al cierre de inscripciones (30/06).', $adminCoord);
    fechar('2026-07-01 09:' . sprintf('%02d', mt_rand(0, 59)) . ':00');
}
simularSesiones($p1, '2026-08-01', 0.0);
simularEvaluaciones($p1, 0.72, '2026-07-24', '2026-08-07');
foreach (q("SELECT id_grupo FROM grupos_tutoria WHERE id_periodo = ? AND estado = 'confirmado'", [$p1])->fetchAll() as $g) {
    q("UPDATE grupos_tutoria SET estado = 'finalizado', motivo_estado = 'Cierre del período', fecha_estado = '2026-07-31 18:00:00' WHERE id_grupo = ?", [$g['id_grupo']]);
    q("INSERT INTO historial_grupo (id_grupo, tipo_evento, estado_anterior, estado_nuevo, id_usuario, motivo, fecha_evento) VALUES (?, 'finalizado', 'confirmado', 'finalizado', ?, 'Cierre de Tutorías Julio 2026.', '2026-07-31 18:00:00')",
      [$g['id_grupo'], $adminCoord]);
}
q("UPDATE sesiones_tutoria s INNER JOIN grupos_tutoria g ON g.id_grupo = s.id_grupo SET s.estado = 'cancelada' WHERE g.id_periodo = ? AND s.estado = 'programada'", [$p1]);
q("UPDATE periodos SET estado = 'cerrada', fecha_cierre = '2026-07-31 18:00:00', id_usuario_cierre = ?, evaluaciones_hasta = '2026-08-07',
   resumen_cierre = 'Cierre de Tutorías Julio 2026: grupos finalizados, evaluaciones abiertas hasta el 07/08.' WHERE id_periodo = ?", [$adminCoord, $p1]);

// ---- Tutorias Enero 2027, Gestion II (activa, inscripciones abiertas) ----
// Se activa en septiembre para medir la demanda con tiempo: los grupos aprobados
// ya tienen su calendario de enero; aun no hay sesiones realizadas.
q("UPDATE periodos SET estado = 'activa', fecha_activacion = '2026-09-01 10:00:00', id_usuario_activacion = ? WHERE id_periodo = ?", [$adminCoord, $p2]);
$participantes2 = array_values(array_filter($estudiantes, static fn ($e) => $e['activo'] && chance(0.82)));
$tardios = array_splice($participantes2, 0, 25);
simularSolicitudes($p2, $participantes2, '2026-09-01', '2026-09-12');

// El tutor de un grupo confirmado no estara en enero: la coordinacion cancela y el motor reubica.
$cancelable = q("SELECT g.id_grupo FROM grupos_tutoria g INNER JOIN tutores t ON t.id_tutor = g.id_tutor
                 WHERE g.id_periodo = ? AND g.estado = 'confirmado' AND g.id_tutor = ? LIMIT 1", [$p2, $tutorIds[3]])->fetchColumn();
if ($cancelable) {
    (new GruposController())->cancel((int) $cancelable, 'El tutor no estará disponible en enero (viaje de posgrado). Estudiantes reubicados por el sistema.', $adminCoord);
    fechar('2026-09-13 10:20:00');
}
simularSolicitudes($p2, $tardios, '2026-09-14', '2026-09-21');
// Los grupos propuestos en los ultimos cuatro dias quedan por aprobar (bandeja de la coordinacion).
aprobarPendientes($p2, HOY . ' 12:00:00');

// Oleada final (21/09): las dos materias con mas interes registrado reciben nuevas
// solicitudes de estudiantes de su carrera. El motor forma sus grupos, que quedan en
// la bandeja de la coordinacion: uno en formacion (5) y otro listo para revision (9).
$periodo2 = (new Periodo())->findById($p2);
$motorOleada = new AsignacionController();
$materiasEspera = q("SELECT id_materia FROM demanda_tutoria WHERE id_periodo = ? AND estado = 'pendiente' AND motivo = 'esperando_companeros'
                     GROUP BY id_materia ORDER BY COUNT(*) DESC, id_materia LIMIT 2", [$p2])->fetchAll(PDO::FETCH_COLUMN);
foreach ($materiasEspera as $k => $materiaOleada) {
    $materiaOleada = (int) $materiaOleada;
    $objetivo = [5, 9][$k];
    $minuto = 0;
    foreach ($estudiantes as $est) {
        $grupo = q("SELECT cupo_ocupado FROM grupos_tutoria WHERE id_periodo = ? AND id_materia = ? AND estado = 'por_aprobar' ORDER BY cupo_ocupado DESC LIMIT 1",
            [$p2, $materiaOleada])->fetchColumn();
        if ($grupo !== false && (int) $grupo >= $objetivo) {
            break;
        }
        $deSuCarrera = in_array($materiaOleada, array_column($materiasDeCarrera[$est['carrera']], 0), true);
        // Una sola tutoria por periodo: solo estudiantes que aun no pidieron nada.
        if (!$est['activo'] || !$deSuCarrera || $motorOleada->tutoriaDelPeriodo($est['id'], $p2) !== null) {
            continue;
        }
        $motorOleada->solicitarApoyo($est['id'], [$materiaOleada], $periodo2);
        fechar(sprintf('2026-09-21 %02d:%02d:00', 9 + intdiv($minuto, 60), $minuto % 60));
        $minuto += 13;
    }
}
// Enero 2027 aun no empieza: sin sesiones realizadas, asistencia ni evaluaciones.
// Solo se descartan sesiones anteriores a la creacion de cada grupo (no hay).
simularSesiones($p2, HOY, 0.0);

// ---------------------------------------------------------------------------
// 4. Notificaciones leidas, accesos al sistema.
// ---------------------------------------------------------------------------
q("UPDATE notificaciones SET leida = 1, fecha_lectura = DATE_ADD(fecha_creacion, INTERVAL FLOOR(1 + RAND(7) * 40) HOUR)
   WHERE fecha_creacion < '2026-09-15' AND RAND(11) < 0.9");
q("UPDATE notificaciones SET leida = 1, fecha_lectura = DATE_ADD(fecha_creacion, INTERVAL FLOOR(1 + RAND(3) * 20) HOUR)
   WHERE leida = 0 AND fecha_creacion >= '2026-09-15' AND RAND(5) < 0.45");
q("UPDATE notificaciones SET fecha_lectura = NULL, leida = 0 WHERE fecha_lectura > ?", [HOY . ' 12:00:00']);

$ips = ['181.114.', '181.188.', '190.129.', '200.87.', '186.27.'];
foreach (q("SELECT u.id_usuario, r.nombre_rol, u.fecha_registro, u.estado FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol")->fetchAll() as $u) {
    $n = ['administrador' => 45, 'tutor' => 22, 'estudiante' => 9][$u['nombre_rol']];
    if ($u['estado'] === 'inactivo') {
        $n = 2;
    }
    $ip = pick($ips) . mt_rand(1, 254) . '.' . mt_rand(1, 254);
    $desde = max('2026-06-01', substr((string) $u['fecha_registro'], 0, 10));
    for ($k = 0; $k < mt_rand((int) ($n * 0.6), $n); $k++) {
        $fecha = rnd_dt($desde, '2026-09-22', 7, 23);
        if ($fecha > HOY . ' 12:00:00') {
            continue;
        }
        if (chance(0.06)) {
            q("INSERT INTO registro_accesos (id_usuario, fecha_hora, ip_origen, resultado) VALUES (?,?,?,'fallido')", [$u['id_usuario'], $fecha, $ip]);
            $fecha = date('Y-m-d H:i:s', strtotime($fecha) + mt_rand(20, 90));
        }
        q("INSERT INTO registro_accesos (id_usuario, fecha_hora, ip_origen, resultado) VALUES (?,?,?,'exitoso')", [$u['id_usuario'], $fecha, chance(0.8) ? $ip : pick($ips) . mt_rand(1, 254) . '.' . mt_rand(1, 254)]);
    }
}

// Resumen.
foreach (['usuarios', 'tutores', 'estudiantes', 'materias', 'grupos_tutoria', 'inscripciones', 'sesiones_tutoria', 'asistencias_sesion', 'evaluaciones_grupo', 'demanda_tutoria', 'notificaciones', 'historial_grupo', 'registro_accesos'] as $t) {
    echo str_pad($t, 22), q("SELECT COUNT(*) FROM $t")->fetchColumn(), "\n";
}
