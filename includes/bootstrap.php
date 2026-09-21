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

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Validation.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once dirname(__DIR__) . '/models/Usuario.php';
require_once dirname(__DIR__) . '/models/Carrera.php';
require_once dirname(__DIR__) . '/models/Materia.php';
require_once dirname(__DIR__) . '/models/Periodo.php';
require_once dirname(__DIR__) . '/models/Aula.php';
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
require_once dirname(__DIR__) . '/models/DisponibilidadTutor.php';
require_once dirname(__DIR__) . '/models/Tutoria.php';
require_once dirname(__DIR__) . '/models/RegistroAcceso.php';
require_once dirname(__DIR__) . '/models/Notificacion.php';
require_once dirname(__DIR__) . '/models/RegistroEstudiante.php';
require_once dirname(__DIR__) . '/models/RegistroTutor.php';
require_once dirname(__DIR__) . '/models/TutorPortal.php';
require_once dirname(__DIR__) . '/controller/AuthController.php';
require_once dirname(__DIR__) . '/controller/UsuariosController.php';
require_once dirname(__DIR__) . '/controller/CarrerasController.php';
require_once dirname(__DIR__) . '/controller/MateriasController.php';
require_once dirname(__DIR__) . '/controller/PeriodosController.php';
require_once dirname(__DIR__) . '/controller/AulasController.php';
require_once dirname(__DIR__) . '/controller/AsignacionController.php';
require_once dirname(__DIR__) . '/controller/AsistenciaSesionController.php';
require_once dirname(__DIR__) . '/controller/EvaluacionGrupoController.php';
require_once dirname(__DIR__) . '/controller/GruposController.php';
require_once dirname(__DIR__) . '/controller/DashboardController.php';
require_once dirname(__DIR__) . '/controller/EstudiantesController.php';
require_once dirname(__DIR__) . '/controller/TutoresController.php';
require_once dirname(__DIR__) . '/controller/DisponibilidadController.php';
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

function app_url(string $path = ''): string
{
    $baseUrl = rtrim((string) (getenv('APP_URL') ?: ''), '/');
    return $baseUrl . '/' . ltrim($path, '/');
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
