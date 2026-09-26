<?php

declare(strict_types=1);

// Load the local environment file when it exists. The real .env stays out of Git.
$environmentFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (is_readable($environmentFile)) {
    $lines = file($environmentFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

// Zona horaria institucional: sin esto PHP usa la de php.ini (XAMPP trae
// Europe/Berlin, la imagen Docker UTC) y "hoy" cambia de dia antes de tiempo.
date_default_timezone_set(getenv('TZ') ?: 'America/La_Paz');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Validation.php';
require_once __DIR__ . '/Csv.php';
require_once __DIR__ . '/Xlsx.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once dirname(__DIR__) . '/models/Usuario.php';
require_once dirname(__DIR__) . '/models/Carrera.php';
require_once dirname(__DIR__) . '/models/Materia.php';
require_once dirname(__DIR__) . '/models/Periodo.php';
require_once dirname(__DIR__) . '/models/TipoTutoria.php';
require_once dirname(__DIR__) . '/models/EspacioTutoria.php';
require_once dirname(__DIR__) . '/models/Grupo.php';
require_once dirname(__DIR__) . '/models/Inscripcion.php';
require_once dirname(__DIR__) . '/models/Demanda.php';
require_once dirname(__DIR__) . '/models/Sesion.php';
require_once dirname(__DIR__) . '/models/Asistencia.php';
require_once dirname(__DIR__) . '/models/Evaluacion.php';
require_once dirname(__DIR__) . '/models/ReporteCampania.php';
require_once dirname(__DIR__) . '/models/HistorialGrupo.php';
require_once dirname(__DIR__) . '/models/Dashboard.php';
require_once dirname(__DIR__) . '/models/Estudiante.php';
require_once dirname(__DIR__) . '/models/Tutor.php';
require_once dirname(__DIR__) . '/models/TutorMateriaConfig.php';
require_once dirname(__DIR__) . '/models/OfertaMateria.php';
require_once dirname(__DIR__) . '/models/Tutoria.php';
require_once dirname(__DIR__) . '/models/RegistroAcceso.php';
require_once dirname(__DIR__) . '/models/Notificacion.php';
require_once dirname(__DIR__) . '/models/RegistroEstudiante.php';
require_once dirname(__DIR__) . '/models/RegistroTutor.php';
require_once dirname(__DIR__) . '/models/TutorPortal.php';
require_once dirname(__DIR__) . '/models/EstadoCuenta.php';
require_once dirname(__DIR__) . '/controller/AuthController.php';
require_once dirname(__DIR__) . '/controller/UsuariosController.php';
require_once dirname(__DIR__) . '/controller/CarrerasController.php';
require_once dirname(__DIR__) . '/controller/MateriasController.php';
require_once dirname(__DIR__) . '/controller/PeriodosController.php';
require_once dirname(__DIR__) . '/controller/TiposTutoriaController.php';
require_once dirname(__DIR__) . '/controller/EspaciosController.php';
require_once dirname(__DIR__) . '/controller/AsignacionController.php';
require_once dirname(__DIR__) . '/controller/AsistenciaSesionController.php';
require_once dirname(__DIR__) . '/controller/EvaluacionGrupoController.php';
require_once dirname(__DIR__) . '/controller/GruposController.php';
require_once dirname(__DIR__) . '/controller/DivisionGrupoController.php';
require_once dirname(__DIR__) . '/controller/HabilitacionTutorController.php';
require_once dirname(__DIR__) . '/controller/OfertaTutorController.php';
require_once dirname(__DIR__) . '/controller/DashboardController.php';
require_once dirname(__DIR__) . '/controller/EstudiantesController.php';
require_once dirname(__DIR__) . '/controller/TutoresController.php';
require_once dirname(__DIR__) . '/controller/AccesosController.php';
require_once dirname(__DIR__) . '/controller/RegistroController.php';
require_once dirname(__DIR__) . '/controller/RegistroTutorController.php';
require_once dirname(__DIR__) . '/controller/TutorPortalController.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Una cuenta desactivada no conserva la sesion abierta (Auth::revalidar).
if (PHP_SAPI !== 'cli' && !Auth::revalidar()) {
    header('Location: ' . app_url('login.php?cuenta=inactiva'));
    exit;
}

// "En curso" automatico (db/035): no hay tareas programadas, asi que se revisa al
// navegar autenticado, como maximo cada 5 minutos por sesion.
if (PHP_SAPI !== 'cli' && Auth::check() && (int) ($_SESSION['en_curso_revisado'] ?? 0) < time() - 300) {
    $_SESSION['en_curso_revisado'] = time();
    try {
        (new Grupo())->activarEnCurso();
    } catch (Throwable $exception) {
        error_log('Activar grupos en curso: ' . $exception->getMessage());
    }
}

function app_url(string $path = ''): string
{
    $baseUrl = rtrim((string) (getenv('APP_URL') ?: ''), '/');
    return $baseUrl . '/' . ltrim($path, '/');
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Un grupo tiene la ubicacion pendiente mientras la coordinacion no registre el
 * aula (presencial) o el enlace (virtual). $grupo necesita modalidad, ubicacion y enlace.
 */
function ubicacion_pendiente(array $grupo): bool
{
    $valor = ($grupo['modalidad'] ?? '') === 'virtual' ? ($grupo['enlace'] ?? null) : ($grupo['ubicacion'] ?? null);

    return $valor === null || trim((string) $valor) === '';
}

/** Etiqueta legible del estado de un grupo de tutoria (sin contexto de cantidad: ver estado_grupo_visual). */
function estado_grupo_label(string $estado): string
{
    return [
        'por_aprobar' => 'En formación',
        // 'formacion' es anterior a db/035 (aprobado sin llegar al minimo): hoy equivale a confirmado.
        'formacion' => 'Grupo confirmado',
        'confirmado' => 'Grupo confirmado',
        'en_curso' => 'En curso',
        'finalizado' => 'Finalizado',
        'cancelado' => 'Cancelado',
    ][$estado] ?? ucfirst(str_replace('_', ' ', $estado));
}

/**
 * Estado visible de un grupo (db/035): icono, etiqueta, clase CSS y una frase segun
 * quien mira ('estudiante', 'tutor' o 'administrador'). Un grupo por aprobar se
 * muestra "En formacion" o, desde Grupo::UMBRAL_GRUPO_NORMAL estudiantes, "Listo
 * para revision". $grupo necesita estado y cupo_ocupado (fecha_aprobacion opcional).
 */
function estado_grupo_visual(array $grupo, string $rol = 'administrador'): array
{
    $estado = (string) ($grupo['estado'] ?? '');
    $inscritos = (int) ($grupo['cupo_ocupado'] ?? 0);
    if ($estado === 'por_aprobar') {
        $estado = $inscritos >= Grupo::UMBRAL_GRUPO_NORMAL ? 'listo' : 'formacion';
    } elseif ($estado === 'formacion') {
        $estado = 'confirmado';
    } elseif ($estado === 'cancelado' && array_key_exists('fecha_aprobacion', $grupo) && $grupo['fecha_aprobacion'] === null) {
        $estado = 'no_abierto';
    }

    $estados = [
        'formacion' => ['🟡', 'En formación', [
            'estudiante' => 'Tu grupo está reuniendo estudiantes y espera la revisión académica.',
            'tutor' => 'Grupo propuesto: está reuniendo estudiantes y espera la aprobación de la coordinación.',
            'administrador' => 'Puede aprobarse como grupo reducido (LMV o MJS) o esperar a que crezca.',
        ]],
        'listo' => ['🟣', 'Listo para revisión', [
            'estudiante' => 'Tu grupo alcanzó la cantidad recomendada y espera la aprobación de la coordinación.',
            'tutor' => 'Grupo completo: espera la aprobación de la coordinación.',
            'administrador' => 'Alcanzó la cantidad recomendada: revísalo con prioridad (lunes a viernes).',
        ]],
        'confirmado' => ['✅', 'Grupo confirmado', [
            'estudiante' => 'Aprobado: ya tiene horario, lugar y calendario de sesiones.',
            'tutor' => 'Aprobado: revisa el horario, el lugar y la fecha de inicio.',
            'administrador' => 'Aprobado; todavía no llega su primera sesión.',
        ]],
        'en_curso' => ['▶️', 'En curso', [
            'estudiante' => 'Las sesiones ya comenzaron.',
            'tutor' => 'Las sesiones ya comenzaron: registra la asistencia.',
            'administrador' => 'Las sesiones ya comenzaron.',
        ]],
        'finalizado' => ['🔵', 'Finalizado', [
            'estudiante' => 'La tutoría terminó. Es histórico.',
            'tutor' => 'La tutoría terminó. Es histórico.',
            'administrador' => 'Histórico: no se puede editar.',
        ]],
        'no_abierto' => ['⚪', 'No se abrió', [
            'estudiante' => 'El grupo no se aprobó: volviste a la lista de espera.',
            'tutor' => 'La coordinación no aprobó este grupo.',
            'administrador' => 'Rechazado antes de aprobarse; sus estudiantes volvieron a la lista de espera.',
        ]],
        'cancelado' => ['⚪', 'Cancelado', [
            'estudiante' => 'El grupo se canceló: volviste a la lista de espera.',
            'tutor' => 'El grupo se canceló.',
            'administrador' => 'Cancelado; sus estudiantes volvieron a la lista de espera.',
        ]],
    ];
    [$icono, $etiqueta, $textos] = $estados[$estado] ?? ['•', estado_grupo_label($estado), []];

    return [
        'clave' => $estado,
        'icono' => $icono,
        'etiqueta' => $etiqueta,
        'texto' => $textos[$rol] ?? ($textos['administrador'] ?? ''),
        'inscritos' => $inscritos,
    ];
}

/** Insignia HTML del estado visible de un grupo: icono + etiqueta (+ cantidad), con la frase como ayuda. */
function estado_grupo_badge(array $grupo, string $rol = 'administrador', bool $conCantidad = true): string
{
    $v = estado_grupo_visual($grupo, $rol);
    $cantidad = $conCantidad ? ' · ' . $v['inscritos'] . ' estudiante' . ($v['inscritos'] === 1 ? '' : 's') : '';

    return '<span class="estado-grupo estado-grupo--' . e($v['clave']) . '" title="' . e($v['texto']) . '">'
        . '<span aria-hidden="true">' . $v['icono'] . '</span> ' . e($v['etiqueta']) . e($cantidad) . '</span>';
}

/** Insignia de interes registrado (sin grupo todavia): reunidos de un minimo. */
function interes_badge(?int $reunidos = null, ?int $minimo = null): string
{
    $detalle = $reunidos !== null && $minimo !== null ? ' · ' . $reunidos . ' de ' . $minimo : '';

    return '<span class="estado-grupo estado-grupo--interes" title="Aún no hay suficientes estudiantes para formar un grupo.">'
        . '<span aria-hidden="true">📌</span> Interés registrado' . e($detalle) . '</span>';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}
