<?php

declare(strict_types=1);

$projectRoot = __DIR__;
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = rawurldecode($requestPath);
$legacyPrefix = '/TecnologiasWeb/php';
$hasLegacyPrefix = strpos($requestPath, $legacyPrefix) === 0;
if ($hasLegacyPrefix) {
    $requestPath = substr($requestPath, strlen($legacyPrefix)) ?: '/';
}

$routes = [
    '/' => '/php/index.php',
    '/index.php' => '/php/index.php',
    '/login.php' => '/php/login.php',
    '/register.php' => '/php/register.php',
    '/postular-tutor.php' => '/php/postular-tutor.php',
    '/logout.php' => '/php/logout.php',
    '/dashboard.php' => '/php/dashboard.php',
    '/usuarios/' => '/usuarios/index.php',
    '/usuarios' => '/usuarios/index.php',
    '/usuarios/index.php' => '/usuarios/index.php',
    '/usuarios/create.php' => '/usuarios/create.php',
    '/usuarios/edit.php' => '/usuarios/edit.php',
    '/usuarios/delete.php' => '/usuarios/delete.php',
    '/usuarios/activate.php' => '/usuarios/activate.php',
    '/roles/' => '/php/roles/index.php',
    '/roles' => '/php/roles/index.php',
    '/roles/index.php' => '/php/roles/index.php',
    '/roles/create.php' => '/php/roles/create.php',
    '/roles/edit.php' => '/php/roles/edit.php',
    '/roles/delete.php' => '/php/roles/delete.php',
    '/carreras/' => '/php/carreras/index.php',
    '/carreras' => '/php/carreras/index.php',
    '/carreras/index.php' => '/php/carreras/index.php',
    '/carreras/create.php' => '/php/carreras/create.php',
    '/carreras/edit.php' => '/php/carreras/edit.php',
    '/carreras/delete.php' => '/php/carreras/delete.php',
    '/materias/' => '/php/materias/index.php',
    '/materias' => '/php/materias/index.php',
    '/materias/index.php' => '/php/materias/index.php',
    '/materias/create.php' => '/php/materias/create.php',
    '/materias/edit.php' => '/php/materias/edit.php',
    '/materias/delete.php' => '/php/materias/delete.php',
    '/estudiantes/' => '/php/estudiantes/index.php',
    '/estudiantes' => '/php/estudiantes/index.php',
    '/estudiantes/index.php' => '/php/estudiantes/index.php',
    '/estudiantes/create.php' => '/php/estudiantes/create.php',
    '/estudiantes/edit.php' => '/php/estudiantes/edit.php',
    '/estudiantes/delete.php' => '/php/estudiantes/delete.php',
    '/tutores/' => '/php/tutores/index.php',
    '/tutores' => '/php/tutores/index.php',
    '/tutores/index.php' => '/php/tutores/index.php',
    '/tutores/create.php' => '/php/tutores/create.php',
    '/tutores/edit.php' => '/php/tutores/edit.php',
    '/tutores/delete.php' => '/php/tutores/delete.php',
    '/asignaciones/' => '/php/asignaciones/index.php',
    '/asignaciones' => '/php/asignaciones/index.php',
    '/asignaciones/index.php' => '/php/asignaciones/index.php',
    '/asignaciones/create.php' => '/php/asignaciones/create.php',
    '/asignaciones/delete.php' => '/php/asignaciones/delete.php',
    '/disponibilidad/' => '/php/disponibilidad/index.php',
    '/disponibilidad' => '/php/disponibilidad/index.php',
    '/disponibilidad/index.php' => '/php/disponibilidad/index.php',
    '/disponibilidad/create.php' => '/php/disponibilidad/create.php',
    '/disponibilidad/edit.php' => '/php/disponibilidad/edit.php',
    '/disponibilidad/delete.php' => '/php/disponibilidad/delete.php',
    '/tutorias/' => '/php/tutorias/index.php',
    '/tutorias' => '/php/tutorias/index.php',
    '/tutorias/index.php' => '/php/tutorias/index.php',
    '/tutorias/create.php' => '/php/tutorias/create.php',
    '/tutorias/especial.php' => '/php/tutorias/especial.php',
    '/tutorias/especiales.php' => '/php/tutorias/especiales.php',
    '/tutorias/special_status.php' => '/php/tutorias/special_status.php',
    '/tutorias/status.php' => '/php/tutorias/status.php',
    '/tutorias/reschedule.php' => '/php/tutorias/reschedule.php',
    '/tutorias/attendance.php' => '/php/tutorias/attendance.php',
    '/tutorias/history.php' => '/php/tutorias/history.php',
    '/evaluaciones/' => '/php/evaluaciones/index.php',
    '/evaluaciones' => '/php/evaluaciones/index.php',
    '/evaluaciones/index.php' => '/php/evaluaciones/index.php',
    '/evaluaciones/create.php' => '/php/evaluaciones/create.php',
    '/accesos/' => '/php/accesos/index.php',
    '/accesos' => '/php/accesos/index.php',
    '/accesos/index.php' => '/php/accesos/index.php',
    '/accesos/export.php' => '/php/accesos/export.php',
    '/reportes/tutorias.php' => '/php/reportes/tutorias.php',
    '/reportes/tutorias/export.php' => '/php/reportes/export.php',
    '/notificaciones/read.php' => '/php/notificaciones/read.php',
    '/permisos/' => '/php/permisos/index.php',
    '/permisos' => '/php/permisos/index.php',
    '/permisos/index.php' => '/php/permisos/index.php',
    '/permisos/save_role.php' => '/php/permisos/save_role.php',
    '/permisos/save_user.php' => '/php/permisos/save_user.php',
    '/materias-disponibles/' => '/php/catalogo/materias.php',
    '/materias-disponibles' => '/php/catalogo/materias.php',
    '/tutores-disponibles/' => '/php/catalogo/tutores.php',
    '/tutores-disponibles' => '/php/catalogo/tutores.php',
    '/horarios-disponibles/' => '/php/catalogo/disponibilidad.php',
    '/horarios-disponibles' => '/php/catalogo/disponibilidad.php',
    '/mi-perfil-tutor/' => '/php/tutor/mi_perfil.php',
    '/mi-perfil-tutor' => '/php/tutor/mi_perfil.php',
    '/mis-materias/' => '/php/tutor/mis_materias.php',
    '/mis-materias' => '/php/tutor/mis_materias.php',
    '/tutor/mis_materias/agregar.php' => '/php/tutor/mis_materias/agregar.php',
    '/tutor/mis_materias/eliminar.php' => '/php/tutor/mis_materias/eliminar.php',
];

if (isset($routes[$requestPath])) {
    require $projectRoot . $routes[$requestPath];
    return;
}

foreach (['css', 'js', 'Front/assets'] as $assetDirectory) {
    $assetRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . $assetDirectory);
    $assetPath = realpath($projectRoot . DIRECTORY_SEPARATOR . ltrim($requestPath, '/'));

    if (
        $assetRoot !== false
        && $assetPath !== false
        && is_file($assetPath)
        && ($assetPath === $assetRoot || strpos($assetPath, $assetRoot . DIRECTORY_SEPARATOR) === 0)
    ) {
        if ($hasLegacyPrefix) {
            $mimeTypes = [
                'css' => 'text/css; charset=UTF-8',
                'js' => 'application/javascript; charset=UTF-8',
                'svg' => 'image/svg+xml',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
            ];
            $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
            header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
            readfile($assetPath);
            return;
        }

        return false;
    }
}

http_response_code(404);
echo 'Pagina no encontrada.';
