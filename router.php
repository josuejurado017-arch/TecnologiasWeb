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
    '/periodos/' => '/php/periodos/index.php',
    '/periodos' => '/php/periodos/index.php',
    '/periodos/index.php' => '/php/periodos/index.php',
    '/periodos/create.php' => '/php/periodos/create.php',
    '/periodos/edit.php' => '/php/periodos/edit.php',
    '/periodos/delete.php' => '/php/periodos/delete.php',
    '/periodos/activate.php' => '/php/periodos/activate.php',
    '/periodos/ver.php' => '/php/periodos/ver.php',
    '/periodos/cerrar.php' => '/php/periodos/cerrar.php',
    '/periodos/observacion.php' => '/php/periodos/observacion.php',
    '/espacios/' => '/php/espacios/index.php',
    '/espacios' => '/php/espacios/index.php',
    '/espacios/index.php' => '/php/espacios/index.php',
    '/espacios/create.php' => '/php/espacios/create.php',
    '/espacios/edit.php' => '/php/espacios/edit.php',
    '/espacios/estado.php' => '/php/espacios/estado.php',
    // El antiguo modulo de aulas (reemplazado en db/029) redirige a espacios.
    '/aulas/' => '/php/espacios/desde_aulas.php',
    '/aulas' => '/php/espacios/desde_aulas.php',
    '/aulas/index.php' => '/php/espacios/desde_aulas.php',
    '/aulas/create.php' => '/php/espacios/desde_aulas.php',
    '/aulas/edit.php' => '/php/espacios/desde_aulas.php',
    '/estudiantes/' => '/php/estudiantes/index.php',
    '/estudiantes' => '/php/estudiantes/index.php',
    '/estudiantes/index.php' => '/php/estudiantes/index.php',
    '/estudiantes/edit.php' => '/php/estudiantes/edit.php',
    '/estudiantes/delete.php' => '/php/estudiantes/delete.php',
    '/estudiantes/estado.php' => '/php/estudiantes/estado.php',
    '/estudiantes/export.php' => '/php/estudiantes/export.php',
    '/tutores/' => '/php/tutores/index.php',
    '/tutores' => '/php/tutores/index.php',
    '/tutores/index.php' => '/php/tutores/index.php',
    '/tutores/edit.php' => '/php/tutores/edit.php',
    '/tutores/delete.php' => '/php/tutores/delete.php',
    '/tutores/estado.php' => '/php/tutores/estado.php',
    '/tutores/export.php' => '/php/tutores/export.php',
    '/tutores/pendientes/' => '/php/tutores/pendientes.php',
    '/tutores/pendientes' => '/php/tutores/pendientes.php',
    '/tutores/pendientes.php' => '/php/tutores/pendientes.php',
    '/tutores/habilitacion.php' => '/php/tutores/habilitacion.php',
    '/tutores/ofertas/' => '/php/tutores/ofertas.php',
    '/tutores/ofertas' => '/php/tutores/ofertas.php',
    '/tutores/ofertas.php' => '/php/tutores/ofertas.php',
    '/tutores/ofertas_revisar.php' => '/php/tutores/ofertas_revisar.php',
    '/cobertura-tutores/' => '/php/tutores/cobertura.php',
    '/cobertura-tutores' => '/php/tutores/cobertura.php',
    '/tutorias/create.php' => '/php/tutorias/create.php',
    '/tutorias/mis.php' => '/php/tutorias/mis.php',
    '/mis-tutorias/' => '/php/tutorias/mis.php',
    '/mis-tutorias' => '/php/tutorias/mis.php',
    '/mis-grupos/' => '/php/tutor/mis_grupos.php',
    '/mis-grupos' => '/php/tutor/mis_grupos.php',
    '/tutor/asistencia.php' => '/php/tutor/asistencia.php',
    '/tutor/proponer_enlace.php' => '/php/tutor/proponer_enlace.php',
    '/grupos/' => '/php/grupos/index.php',
    '/grupos' => '/php/grupos/index.php',
    '/grupos/index.php' => '/php/grupos/index.php',
    '/grupos/cancel.php' => '/php/grupos/cancel.php',
    '/grupos/historial.php' => '/php/grupos/historial.php',
    '/grupos/ubicacion.php' => '/php/grupos/ubicacion.php',
    '/grupos/asignar_tutor.php' => '/php/grupos/asignar_tutor.php',
    '/mis-evaluaciones/' => '/php/evaluaciones/mis.php',
    '/mis-evaluaciones' => '/php/evaluaciones/mis.php',
    '/evaluaciones/mis.php' => '/php/evaluaciones/mis.php',
    '/evaluaciones/evaluar.php' => '/php/evaluaciones/evaluar.php',
    '/reportes/campania.php' => '/php/reportes/campania.php',
    '/accesos/' => '/php/accesos/index.php',
    '/accesos' => '/php/accesos/index.php',
    '/accesos/index.php' => '/php/accesos/index.php',
    '/accesos/export.php' => '/php/accesos/export.php',
    '/notificaciones/read.php' => '/php/notificaciones/read.php',
    '/mi-perfil-tutor/' => '/php/tutor/mi_perfil.php',
    '/mi-perfil-tutor' => '/php/tutor/mi_perfil.php',
    '/mis-materias/' => '/php/tutor/mis_materias.php',
    '/mis-materias' => '/php/tutor/mis_materias.php',
    '/tutor/mis_materias/agregar.php' => '/php/tutor/mis_materias/agregar.php',
    '/tutor/mis_materias/eliminar.php' => '/php/tutor/mis_materias/eliminar.php',
    '/tutor/mis_materias/configurar.php' => '/php/tutor/mis_materias/configurar.php',
    '/tutor/mis_materias/responder.php' => '/php/tutor/mis_materias/responder.php',
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
