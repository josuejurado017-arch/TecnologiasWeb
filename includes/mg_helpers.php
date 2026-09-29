<?php

declare(strict_types=1);

// Funciones de apoyo del modulo Modalidades de Grado (validacion y vistas).

function mg_fecha_valida(?string $valor): bool
{
    if ($valor === null || $valor === '') {
        return false;
    }
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

    return $fecha !== false && $fecha->format('Y-m-d') === $valor;
}

/** Normaliza HH:MM o HH:MM:SS a HH:MM:00; null si no es una hora valida. */
function mg_hora(?string $valor): ?string
{
    if ($valor === null || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $valor, $m)) {
        return null;
    }

    return $m[1] . ':' . $m[2] . ':00';
}

function mg_texto_opcional($valor, int $max): ?string
{
    $texto = trim((string) $valor);

    return $texto === '' ? null : mb_substr($texto, 0, $max);
}

function mg_fecha_corta(?string $fecha): string
{
    return $fecha ? date('d/m/Y', strtotime($fecha)) : '—';
}

function mg_badge_etapa(string $etapa): string
{
    $clases = ['previa' => 'badge-borrador', 'mg1' => 'badge-info', 'mg2' => 'badge-por_aprobar', 'finalizado' => 'badge-finalizado'];

    return '<span class="badge ' . ($clases[$etapa] ?? '') . '">' . e(MgExpediente::ETAPAS[$etapa] ?? $etapa) . '</span>';
}

function mg_badge_estado(string $estado): string
{
    $clases = ['activo' => 'badge-activa', 'aprobado' => 'badge-success', 'reprobado' => 'badge-danger', 'abandono' => 'badge-danger', 'retirado' => 'badge-cerrada'];

    return '<span class="badge ' . ($clases[$estado] ?? '') . '">' . e(MgExpediente::ESTADOS[$estado] ?? $estado) . '</span>';
}

function mg_badge_solicitud(string $estado): string
{
    $clases = ['pendiente' => 'badge-warning', 'observada' => 'badge-info', 'aprobada' => 'badge-success', 'rechazada' => 'badge-danger'];

    return '<span class="badge ' . ($clases[$estado] ?? '') . '">' . e(MgSolicitud::ESTADOS[$estado] ?? $estado) . '</span>';
}

function mg_tamano_legible(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
}

function mg_badge_evidencia(string $estado): string
{
    $clases = ['confirmado' => 'badge-success', 'pendiente' => 'badge-warning', 'propuesta' => 'badge-info'];

    return '<span class="badge ' . ($clases[$estado] ?? '') . '">' . e(ucfirst($estado)) . '</span>';
}

/** Mensaje ?message=... de una lista blanca y ?error=... libre (se escapa al mostrar). */
function mg_flash(array $mensajes): array
{
    $codigo = isset($_GET['message']) && is_string($_GET['message']) ? $_GET['message'] : '';
    $error = isset($_GET['error']) && is_string($_GET['error']) ? $_GET['error'] : null;
    $aviso = isset($_GET['aviso']) && is_string($_GET['aviso']) ? $_GET['aviso'] : null;

    return [$mensajes[$codigo] ?? null, $error, $aviso];
}

/** Redirige dentro del modulo MG con un mensaje, error o aviso. */
function mg_redirect(string $ruta, array $query = []): void
{
    $query = array_filter($query, static fn ($v): bool => $v !== null && $v !== '');
    [$ruta, $ancla] = array_pad(explode('#', $ruta, 2), 2, '');
    header('Location: ' . app_url('mg/' . ltrim($ruta, '/'))
        . ($query ? (str_contains($ruta, '?') ? '&' : '?') . http_build_query($query) : '')
        . ($ancla !== '' ? '#' . $ancla : ''));
    exit;
}

/** Corta el POST si el token CSRF no es valido. */
function mg_require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud no válida.');
    }
}
